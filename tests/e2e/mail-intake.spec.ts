/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for creating tasks by email (tasks-create-by-email 1.2, 1.3 and 2.4).
 *
 *   @e2e task-mail-intake::an-admin-connects-a-mailbox-and-tests-it
 *   @e2e task-mail-intake::project-settings-show-the-projects-address
 *   @e2e task-mail-intake::a-members-mail-becomes-a-backlog-task-with-its-attachment
 *   @e2e task-mail-intake::a-member-receives-a-confirmation-with-the-task-key
 *   @e2e exclude task-mail-intake::the-same-message-is-not-turned-into-two-tasks MailIntakeServiceTest::testSameMessageIdCreatesNoSecondTask; a message that stays in the inbox after its task was saved cannot be forced on a live server
 *   @e2e exclude task-mail-intake::a-non-members-mail-is-rejected-without-a-reply MailIntakeServiceTest::testNonMemberSenderIsRejectedWithoutReply
 *   @e2e exclude task-mail-intake::a-mail-with-a-failed-signature-check-is-rejected MailIntakeServiceTest::testFailedDkimIsRejected; a test mail server signs nothing
 *   @e2e exclude task-mail-intake::a-member-mailing-an-unknown-project-is-told-so MailIntakeServiceTest::testUnknownProjectRepliesToMember
 *
 * "Project settings show the project's address" needs nothing but the
 * instance. The mailbox scenarios need a test mail server with IMAP and SMTP
 * (GreenMail, for one) that Nextcloud also sends its mail through, and the
 * Nextcloud container to run the intake job in; without them they skip:
 *
 *   PLANNINQ_E2E_IMAP_HOST / _IMAP_PORT / _IMAP_ENCRYPTION   the server as Nextcloud reaches it
 *   PLANNINQ_E2E_IMAP_LOCAL_HOST / _IMAP_LOCAL_PORT           the same server as this runner reaches it (plain IMAP)
 *   PLANNINQ_E2E_IMAP_USER / _IMAP_PASSWORD                   the intake mailbox's login
 *   PLANNINQ_E2E_SMTP_HOST / _SMTP_PORT                       where this runner sends mail (plain SMTP)
 *   PLANNINQ_E2E_MAIL_DOMAIN                                  the domain the test server accepts, e.g. gemeente.nl
 *   PLANNINQ_E2E_MEMBER_MAIL_PASSWORD                         the password the server gives the member's mailbox
 *   NEXTCLOUD_CONTAINER                                       the container `php occ` runs in
 *
 * Only "an admin connects a mailbox" runs as the admin, because the admin
 * settings page is the scenario; members are regular users the spec creates,
 * in contexts of their own with an empty storage state.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import net from 'node:net'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const SETTINGS = '/index.php/apps/planninq/api/settings'
const JSON_HEADERS = { 'Content-Type': 'application/json' }
const KEY = `VC${RUN.slice(-4).toUpperCase().replace(/[^A-Z]/g, 'X')}`

const env = process.env
const DOMAIN = env.PLANNINQ_E2E_MAIL_DOMAIN ?? 'gemeente.nl'
const MAILBOX_READY = Boolean(env.PLANNINQ_E2E_IMAP_HOST && env.PLANNINQ_E2E_IMAP_USER && env.PLANNINQ_E2E_IMAP_PASSWORD)
const MAIL_LOOP_READY = MAILBOX_READY && Boolean(env.PLANNINQ_E2E_SMTP_HOST && env.PLANNINQ_E2E_IMAP_LOCAL_HOST && env.NEXTCLOUD_CONTAINER && env.PLANNINQ_E2E_MEMBER_MAIL_PASSWORD)

/**
 * The intake settings as the admin sees them.
 *
 * @param admin The admin context.
 */
async function intakeSettings(admin: APIRequestContext): Promise<Record<string, string>> {
	const body = await (await admin.get(SETTINGS)).json()
	return body?.config ?? body ?? {}
}

/**
 * Store intake settings.
 *
 * @param admin The admin context.
 * @param values The `mail_intake_*` values.
 */
async function setIntake(admin: APIRequestContext, values: Record<string, string>): Promise<void> {
	const res = await admin.post(SETTINGS, { data: values, headers: JSON_HEADERS })
	expect(res.ok(), `intake settings: ${res.status()}`).toBe(true)
}

/**
 * A regular user with an email address, owning a project with this key.
 *
 * @param admin The admin context.
 * @param user The user id.
 * @param email Their email address.
 * @param title The project title.
 * @return the project id
 */
async function memberWithProject(admin: APIRequestContext, user: string, email: string, title: string): Promise<string> {
	expect((await admin.post('/ocs/v2.php/cloud/users', { data: { userid: user, password: PASSWORD, email } })).ok()).toBe(true)
	const api = await request.newContext({ baseURL: BASE_URL, httpCredentials: { username: user, password: PASSWORD, send: 'always' }, storageState: { cookies: [], origins: [] }, extraHTTPHeaders: { Accept: 'application/json', 'OCS-APIRequest': 'true' } })
	try {
		const created = await api.post('/index.php/apps/planninq/api/projects', { data: { title, key: KEY }, headers: JSON_HEADERS })
		expect(created.status()).toBe(201)
		const project = String((await created.json()).id)
		const stored = await (await api.get(`${OBJECTS}/project/${project}`)).json()
		if (stored.key !== KEY) {
			expect((await api.patch(`${OBJECTS}/project/${project}`, { data: { key: KEY }, headers: JSON_HEADERS })).ok()).toBe(true)
		}
		return project
	} finally {
		await api.dispose()
	}
}

/**
 * Talk a line-based protocol: send each command after reading the answer to the previous one.
 *
 * @param host The host.
 * @param port The port.
 * @param steps Each step: what to send (null for the greeting) and the pattern its answer ends with.
 * @return everything the server said
 */
async function converse(host: string, port: number, steps: Array<[string | null, RegExp]>): Promise<string> {
	return new Promise((resolve, reject) => {
		const socket = net.connect(port, host)
		let heard = ''
		let pending = ''
		let step = 0
		socket.setTimeout(15_000, () => socket.destroy(new Error(`${host}:${port} timed out at step ${step}`)))
		socket.on('error', reject)
		socket.on('data', (chunk) => {
			heard += chunk.toString('utf8')
			pending += chunk.toString('utf8')
			while (step < steps.length && steps[step][1].test(pending)) {
				pending = ''
				step++
				if (step === steps.length) {
					socket.end()
					resolve(heard)
					return
				}
				socket.write(String(steps[step][0]))
			}
		})
	})
}

/**
 * Send a mail with one PNG attachment over plain SMTP.
 *
 * @param mail The mail.
 * @param mail.from The sender.
 * @param mail.to The recipient.
 * @param mail.subject The subject.
 * @param mail.body The plain-text body.
 * @param mail.photo The photo's bytes.
 */
async function sendMail(mail: { from: string, to: string, subject: string, body: string, photo: Buffer }): Promise<void> {
	const boundary = `b-${RUN}`
	const data = [
		`From: ${mail.from}`,
		`To: ${mail.to}`,
		`Subject: ${mail.subject}`,
		`Message-ID: <${RUN}@e2e.${DOMAIN}>`,
		'MIME-Version: 1.0',
		`Content-Type: multipart/mixed; boundary="${boundary}"`,
		'',
		`--${boundary}`,
		'Content-Type: text/plain; charset=utf-8',
		'',
		mail.body,
		`--${boundary}`,
		'Content-Type: image/png',
		'Content-Transfer-Encoding: base64',
		'Content-Disposition: attachment; filename="printer.png"',
		'',
		...(mail.photo.toString('base64').match(/.{1,76}/g) ?? []),
		`--${boundary}--`,
		'.',
		'',
	].join('\r\n')
	await converse(String(env.PLANNINQ_E2E_SMTP_HOST), Number(env.PLANNINQ_E2E_SMTP_PORT ?? 25), [
		[null, /^220 .*\r\n$/m],
		['EHLO e2e\r\n', /^250 .*\r\n$/m],
		[`MAIL FROM:<${mail.from}>\r\n`, /^250 .*\r\n$/m],
		[`RCPT TO:<${mail.to}>\r\n`, /^250 .*\r\n$/m],
		['DATA\r\n', /^354 .*\r\n$/m],
		[data, /^250 .*\r\n$/m],
		['QUIT\r\n', /^221 .*\r\n$/m],
	])
}

/**
 * Everything a mailbox's INBOX holds, headers and bodies, read over plain IMAP.
 *
 * @param user The login.
 * @param password The password.
 */
async function inboxText(user: string, password: string): Promise<string> {
	const said = await converse(String(env.PLANNINQ_E2E_IMAP_LOCAL_HOST), Number(env.PLANNINQ_E2E_IMAP_LOCAL_PORT ?? 143), [
		[null, /^\* OK.*\r\n$/m],
		[`a1 LOGIN "${user}" "${password}"\r\n`, /^a1 (OK|NO|BAD).*\r\n$/m],
		['a2 SELECT INBOX\r\n', /^a2 (OK|NO|BAD).*\r\n$/m],
		['a3 FETCH 1:* (BODY.PEEK[])\r\n', /^a3 (OK|NO|BAD).*\r\n$/m],
		['a4 LOGOUT\r\n', /^a4 (OK|NO|BAD).*\r\n$/m],
	])
	// A soft line break of a quoted-printable body would split the sentence.
	return said.replace(/=\r\n/g, '')
}

/**
 * Run the intake job once, inside the Nextcloud container.
 */
function runIntakeJob(): void {
	const container = String(env.NEXTCLOUD_CONTAINER)
	const list = execFileSync('docker', ['exec', '-u', 'www-data', container, 'php', 'occ', 'background-job:list', '--class=OCA\\Planninq\\BackgroundJob\\MailIntakeJob', '--output=json'], { encoding: 'utf8' })
	const jobs = JSON.parse(list)
	expect(jobs.length, 'the intake job is registered').toBeGreaterThan(0)
	execFileSync('docker', ['exec', '-u', 'www-data', container, 'php', 'occ', 'background-job:execute', String(jobs[0].id), '--force-execute'], { encoding: 'utf8' })
}

test.describe('Create tasks by email', () => {
	test.describe.configure({ mode: 'serial', timeout: 180_000 })

	let admin: APIRequestContext
	let before: Record<string, string> = {}

	test.beforeAll(async () => {
		admin = await adminApi()
		const all = await intakeSettings(admin)
		before = Object.fromEntries(Object.entries(all).filter(([key]) => key.startsWith('mail_intake_') && key !== 'mail_intake_credential_ref' && key !== 'mail_intake_credential_owner').map(([k, v]) => [k, String(v)]))
	})

	test.afterAll(async () => {
		if (admin) {
			await setIntake(admin, before)
			await admin.dispose()
		}
	})

	test('an admin connects a mailbox and tests it', async ({ page }) => {
		test.skip(!MAILBOX_READY, 'no test mail server configured (PLANNINQ_E2E_IMAP_*)')
		// The admin settings page is admin-only; this page carries the admin session.
		await page.goto(`${BASE_URL}/index.php/settings/admin/planninq`)
		const section = page.getByTestId('mail-intake-settings')
		await expect(section).toBeVisible({ timeout: 30_000 })
		await section.getByTestId('mail-intake-host').locator('input').fill(String(env.PLANNINQ_E2E_IMAP_HOST))
		await section.getByTestId('mail-intake-port').locator('input').fill(String(env.PLANNINQ_E2E_IMAP_PORT ?? '993'))
		const encryption = section.getByTestId('mail-intake-encryption')
		await encryption.click()
		await page.getByRole('option', { name: new RegExp(String(env.PLANNINQ_E2E_IMAP_ENCRYPTION ?? 'ssl'), 'i') }).first().click()
		await section.getByTestId('mail-intake-username').locator('input').fill(String(env.PLANNINQ_E2E_IMAP_USER))
		await section.getByTestId('mail-intake-password').locator('input').fill(String(env.PLANNINQ_E2E_IMAP_PASSWORD))
		await section.getByTestId('mail-intake-address').locator('input').fill(`planninq@${DOMAIN}`)
		await section.getByTestId('mail-intake-save').click()
		await section.getByTestId('mail-intake-test').click()

		// THEN the page reports that the connection works and how many messages the folder holds.
		await expect(section.getByTestId('mail-intake-message')).toContainText(/\d+/, { timeout: 30_000 })

		// AND the stored settings hold a credential reference and no password.
		const stored = await intakeSettings(admin)
		expect(String(stored.mail_intake_credential_ref ?? '')).not.toBe('')
		expect(JSON.stringify(stored)).not.toContain(String(env.PLANNINQ_E2E_IMAP_PASSWORD))
	})

	test('project settings show the project\'s address', async ({ browser }) => {
		const user = `adres-${RUN}`
		let project = ''
		try {
			await setIntake(admin, { mail_intake_enabled: '1', mail_intake_address: `planninq@${DOMAIN}` })
			project = await memberWithProject(admin, user, `${user}@${DOMAIN}`, `Vergunningen Centrum ${RUN}`)

			const { context, page } = await signedInPage(browser, user, PASSWORD)
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: 'Project settings' }).click()
			const address = page.getByTestId('project-mail-address')
			await expect(address).toContainText(`Mail tasks to planninq+${KEY}@${DOMAIN}`)
			await expect(address.getByTestId('project-mail-address-copy')).toBeVisible()
			await context.close()
		} finally {
			if (project) {
				await admin.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
			}
			await admin.delete(`/ocs/v2.php/cloud/users/${user}`, { failOnStatusCode: false })
		}
	})

	test('a member\'s mail becomes a backlog task with its attachment, and they get the task key back', async ({ browser }) => {
		test.skip(!MAIL_LOOP_READY, 'no test mail server and container configured (PLANNINQ_E2E_IMAP_*, _SMTP_*, NEXTCLOUD_CONTAINER)')
		const user = `anna-${RUN}`
		const email = `${user}@${DOMAIN}`
		const subject = `Printer 2nd floor broken ${RUN}`
		let project = ''
		try {
			await setIntake(admin, { mail_intake_enabled: '1', mail_intake_address: `planninq@${DOMAIN}`, mail_intake_require_auth: '0' })
			project = await memberWithProject(admin, user, email, `Vergunningen Centrum ${RUN}`)
			const photo = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')

			// WHEN she mails the project address with a photo, and the intake job runs.
			await sendMail({ from: email, to: `planninq+${KEY}@${DOMAIN}`, subject, body: 'It prints nothing at all.', photo })
			runIntakeJob()

			// THEN the project has the task in its backlog, with the body, Anna as reporter and the photo.
			let task: Record<string, unknown> | undefined
			await expect.poll(async () => {
				const rows = (await (await admin.get(`${OBJECTS}/task?project=${project}&_limit=50`)).json()).results ?? []
				task = rows.find((row) => row.title === subject)
				return Boolean(task)
			}, { timeout: 60_000 }).toBe(true)
			expect(String(task?.description ?? '')).toContain('It prints nothing at all.')
			expect(JSON.stringify(task)).toContain(user)
			const id = String(task?.id ?? (task?.['@self'] as Record<string, unknown>)?.id)
			const files = await (await admin.get(`${OBJECTS}/task/${id}/files`)).json()
			expect(JSON.stringify(files)).toContain('printer.png')

			const { context, page } = await signedInPage(browser, user, PASSWORD)
			await page.goto(new URL(`projects/${project}/backlog`, PLANNINQ_ROOT).toString())
			await expect(page.getByText(subject).first()).toBeVisible({ timeout: 30_000 })
			await context.close()

			// AND Anna has a reply naming the new task's key, with a link to it.
			const key = String(task?.key ?? '')
			expect(key).toMatch(new RegExp(`^${KEY}-\\d+$`))
			await expect.poll(async () => inboxText(email, String(env.PLANNINQ_E2E_MEMBER_MAIL_PASSWORD)), { timeout: 60_000 })
				.toContain(`Task ${key} was created in Vergunningen Centrum ${RUN}.`)
			expect(await inboxText(email, String(env.PLANNINQ_E2E_MEMBER_MAIL_PASSWORD))).toContain(`/tasks/${id}`)
		} finally {
			if (project) {
				await admin.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
			}
			await admin.delete(`/ocs/v2.php/cloud/users/${user}`, { failOnStatusCode: false })
		}
	})
})
