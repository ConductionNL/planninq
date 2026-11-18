/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for exporting a whole project (project-data-export, task 4.3).
 *
 *   @e2e project-data-export::a-manager-starts-an-export
 *   @e2e project-data-export::a-member-does-not-see-the-action
 *   @e2e project-data-export::the-zip-holds-the-project-and-nothing-else
 *   @e2e project-data-export::a-manager-without-the-export-verb-is-refused
 *   @e2e project-data-export::a-completed-export-is-on-the-audit-trail
 *   @e2e project-data-export::the-ready-notification-opens-the-zip
 *
 * "The endpoint refuses a member", "A file the requester cannot read is
 * skipped" and "A full quota fails cleanly" carry an `@e2e exclude` in the
 * spec: Newman (task 2.2) and the unit tests of tasks 1.2 and 3.3 cover them.
 *
 * The export runs as a queued background job. This spec runs that job with
 * `occ background-job:execute`, through the occ prefix of shared-instance.ts
 * (PLANNINQ_E2E_OCC, PLANNINQ_E2E_CONTAINER / NEXTCLOUD_CONTAINER, else
 * `php occ` on CI), then reads the zip through WebDAV as the manager.
 *
 * "A manager without the export verb is refused" needs a manager whose
 * OpenRegister role grants read but not export on `task`. Planninq's register
 * declares no export verb, so export falls back to read for every reader; the
 * scenario runs only on a rig that names such a user in
 * PLANNINQ_E2E_EXPORT_DENIED_USER (password in PLANNINQ_E2E_EXPORT_DENIED_PASSWORD).
 *
 * Every person in the UI is a regular user with an empty storage state.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { inflateRawSync } from 'node:zlib'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS } from './portfolio-api.ts'
import { occPrefix } from './shared-instance.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const JOB_CLASS = 'OCA\\Planninq\\BackgroundJob\\ExportProjectJob'
const EXPORT_FOLDER = 'Planninq exports'
const PROFILE = 'planninq-project-export'

/**
 * Run occ against the instance under test.
 *
 * @param args The occ arguments.
 */
function occ(args: string[]): string {
	const [command, ...prefix] = occPrefix()
	return execFileSync(command, [...prefix, ...args], { encoding: 'utf8', timeout: 120_000 })
}

/**
 * Run the queued export jobs of one user, and answer how many ran.
 *
 * @param userId The requester.
 */
function runExportJobs(userId: string): number {
	const jobs = JSON.parse(occ(['background-job:list', `--class=${JOB_CLASS}`, '--output=json', '--limit=500']) || '[]') as Array<{ id: number, argument: unknown }>
	const mine = jobs.filter((job) => {
		const argument = typeof job.argument === 'string' ? JSON.parse(job.argument) : job.argument
		return (argument as { userId?: string })?.userId === userId
	})
	for (const job of mine) {
		occ(['background-job:execute', String(job.id), '--force-execute'])
	}
	return mine.length
}

/**
 * An API context signed in as `username`, without the admin's stored session.
 *
 * @param username The user id.
 * @param password The password.
 */
async function userApi(username: string, password = PASSWORD): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password, send: 'always' },
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

/**
 * Create a regular Nextcloud user.
 *
 * @param api The admin context.
 * @param userid The user id.
 */
async function createUser(api: APIRequestContext, userid: string): Promise<void> {
	expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid, password: PASSWORD } })).ok()).toBe(true)
}

/**
 * The zips in a user's export folder, through WebDAV.
 *
 * @param dav The user's context.
 * @param userId The user id.
 */
async function exportedZips(dav: APIRequestContext, userId: string): Promise<string[]> {
	const res = await dav.fetch(`/remote.php/dav/files/${userId}/${encodeURIComponent(EXPORT_FOLDER)}/`, { method: 'PROPFIND', headers: { Depth: '1' } })
	if (res.status() === 404) {
		return []
	}
	expect(res.status()).toBe(207)
	return [...(await res.text()).matchAll(/<d:href>([^<]+\.zip)<\/d:href>/g)].map((match) => decodeURIComponent(match[1].split('/').pop() ?? ''))
}

/**
 * The entries of a zip archive, read from its central directory. Stored and
 * deflated entries are the two methods a PHP zip writes.
 *
 * @param zip The archive.
 */
function unzip(zip: Buffer): Map<string, Buffer> {
	const entries = new Map<string, Buffer>()
	let end = zip.length - 22
	while (end >= 0 && zip.readUInt32LE(end) !== 0x06054b50) {
		end--
	}
	expect(end, 'a zip end-of-central-directory record').toBeGreaterThanOrEqual(0)
	let offset = zip.readUInt32LE(end + 16)
	const count = zip.readUInt16LE(end + 10)
	for (let index = 0; index < count; index++) {
		const method = zip.readUInt16LE(offset + 10)
		const size = zip.readUInt32LE(offset + 20)
		const nameLength = zip.readUInt16LE(offset + 28)
		const extraLength = zip.readUInt16LE(offset + 30)
		const commentLength = zip.readUInt16LE(offset + 32)
		const local = zip.readUInt32LE(offset + 42)
		const name = zip.toString('utf8', offset + 46, offset + 46 + nameLength)
		const dataStart = local + 30 + zip.readUInt16LE(local + 26) + zip.readUInt16LE(local + 28)
		const data = zip.subarray(dataStart, dataStart + size)
		entries.set(name, method === 8 ? inflateRawSync(data) : Buffer.from(data))
		offset += 46 + nameLength + extraLength + commentLength
	}
	return entries
}

/**
 * The JSON of one zip entry.
 *
 * @param entries The zip's entries.
 * @param name The entry name.
 */
function jsonEntry(entries: Map<string, Buffer>, name: string): any {
	expect([...entries.keys()], `the zip holds ${name}`).toContain(name)
	return JSON.parse(entries.get(name)!.toString('utf8'))
}

test.describe('Project export', () => {
	let api: APIRequestContext
	const manager = `exporteur-${RUN}`
	const member = `meelezer-${RUN}`
	let projectA = ''
	let projectB = ''
	let taskWithFile = ''
	const tasksA: string[] = []
	const tasksB: string[] = []

	test.beforeAll(async () => {
		api = await adminApi()
		await createUser(api, manager)
		await createUser(api, member)
		projectA = await createObject(api, 'project', {
			title: `Herinrichting Marktplein ${RUN}`,
			key: `HM${RUN.slice(-4).toUpperCase()}`,
			status: 'active',
			owner: ADMIN_USER,
			managers: [manager],
			members: [ADMIN_USER, manager, member],
		})
		projectB = await createObject(api, 'project', { title: `Ander project ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, manager] })
		for (let index = 1; index <= 3; index++) {
			tasksA.push(await createObject(api, 'task', { title: `Marktplein ${index} ${RUN}`, project: projectA, status: 'open' }))
		}
		for (let index = 1; index <= 5; index++) {
			tasksB.push(await createObject(api, 'task', { title: `Ander ${index} ${RUN}`, project: projectB, status: 'open' }))
		}
		taskWithFile = tasksA[0]

		// One attached PDF and two comments on the first task.
		const upload = await api.post(`${OBJECTS}/task/${taskWithFile}/filesMultipart`, {
			multipart: { 'files[]': { name: `tekening-${RUN}.pdf`, mimeType: 'application/pdf', buffer: Buffer.from(`%PDF-1.4\n% planninq e2e ${RUN}\n%%EOF\n`) } },
		})
		expect(upload.ok(), `attach the PDF: ${upload.status()}`).toBe(true)
		for (const message of ['Eerste opmerking', 'Tweede opmerking']) {
			const comment = await api.post(`${OBJECTS}/task/${taskWithFile}/notes`, { data: { message: `${message} ${RUN}` } })
			expect(comment.ok(), `comment: ${comment.status()}`).toBe(true)
		}
	})

	test.afterAll(async () => {
		const dav = await userApi(manager)
		for (const zip of await exportedZips(dav, manager)) {
			await dav.delete(`/remote.php/dav/files/${manager}/${encodeURIComponent(EXPORT_FOLDER)}/${encodeURIComponent(zip)}`)
		}
		await dav.dispose()
		for (const task of [...tasksA, ...tasksB]) {
			await api.delete(`${OBJECTS}/task/${task}`, { failOnStatusCode: false })
		}
		for (const project of [projectA, projectB]) {
			if (project) {
				for (const column of (await (await api.get(`${OBJECTS}/column?project=${project}&_limit=50`)).json()).results ?? []) {
					await api.delete(`${OBJECTS}/column/${column.id}`, { failOnStatusCode: false })
				}
				await api.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
			}
		}
		for (const userid of [manager, member]) {
			await api.delete(`/ocs/v2.php/cloud/users/${userid}`)
		}
	})

	// @e2e project-data-export::a-member-does-not-see-the-action
	test('a member does not see the action', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, member, PASSWORD)
		await page.goto(new URL(`projects/${projectA}`, PLANNINQ_ROOT).toString())
		await page.getByRole('button', { name: 'Project settings' }).click()
		const dangerZone = page.getByRole('tab', { name: 'Danger zone' })
		if (await dangerZone.count()) {
			await dangerZone.click()
		}
		await expect(page.getByTestId('project-export')).toHaveCount(0)
		await expect(page.getByRole('button', { name: 'Export project' })).toHaveCount(0)
		await context.close()
	})

	// @e2e project-data-export::a-manager-starts-an-export
	// @e2e project-data-export::the-zip-holds-the-project-and-nothing-else
	// @e2e project-data-export::a-completed-export-is-on-the-audit-trail
	// @e2e project-data-export::the-ready-notification-opens-the-zip
	test('a manager exports the project and gets the zip', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, manager, PASSWORD)
		await page.goto(new URL(`projects/${projectA}`, PLANNINQ_ROOT).toString())
		await page.getByRole('button', { name: 'Project settings' }).click()
		await page.getByRole('tab', { name: 'Danger zone' }).click()
		await page.getByTestId('project-export').click()
		await page.getByTestId('project-export-confirm').click()
		await expect(page.getByText('The export is running. You will get a notification when it is in your Files.')).toBeVisible()
		await expect(page.getByTestId('project-export-confirm')).toHaveCount(0)

		// A background job for that project and that manager is queued; run it.
		expect(runExportJobs(manager)).toBe(1)

		// The zip holds the project and nothing else.
		const dav = await userApi(manager)
		const zips = await exportedZips(dav, manager)
		expect(zips).toHaveLength(1)
		const download = await dav.get(`/remote.php/dav/files/${manager}/${encodeURIComponent(EXPORT_FOLDER)}/${encodeURIComponent(zips[0])}`)
		expect(download.ok()).toBe(true)
		const entries = unzip(Buffer.from(await download.body()))

		const tasks = jsonEntry(entries, 'task.json') as Array<Record<string, any>>
		expect(tasks.map((task) => String(task.id ?? task['@self']?.id)).sort()).toEqual([...tasksA].sort())
		const withFile = tasks.find((task) => String(task.id ?? task['@self']?.id) === taskWithFile)!
		const key = String(withFile.key ?? '')
		expect([...entries.keys()].some((name) => name.startsWith(`attachments/${key}/`) && name.endsWith('.pdf'))).toBe(true)

		// comments.json holds the comments by task uuid.
		const comments = jsonEntry(entries, 'comments.json') as Record<string, unknown[]>
		expect(comments[taskWithFile]).toHaveLength(2)

		// The manifest keys each file by its name, with its value mode and rows.
		const manifest = jsonEntry(entries, 'manifest.json')
		const files = manifest.files as Record<string, { valueMode: string, rows: number }>
		expect(files['task.json']).toEqual({ valueMode: 'stored', rows: 3 })
		// The objects that left: the project and every <schema>.json, as the audit row counts them.
		const total = Object.entries(files)
			.filter(([name, file]) => file.valueMode === 'stored' && name !== 'comments.json')
			.reduce((sum, [, file]) => sum + file.rows, 0)

		// A completed export is on the audit trail, naming the manager, the profile and the rows.
		const audit = await api.get('/index.php/apps/openregister/api/audit-trails', { params: { action: 'export.completed', user: manager, _limit: 50 } })
		expect(audit.ok()).toBe(true)
		const rows = ((await audit.json()).results ?? []).filter((row: Record<string, unknown>) => JSON.stringify(row).includes(PROFILE))
		expect(rows).toHaveLength(1)
		expect(JSON.stringify(rows[0])).toContain(String(total))

		// The ready notification opens the zip's folder with the zip highlighted.
		const notifications = await dav.get('/ocs/v2.php/apps/notifications/api/v2/notifications', { params: { format: 'json' } })
		expect(notifications.ok()).toBe(true)
		const ready = ((await notifications.json()).ocs?.data ?? []).find((item: Record<string, string>) => item.app === 'planninq' && item.subject === `Export of Herinrichting Marktplein ${RUN} is ready`)
		expect(ready, 'the ready notification').toBeTruthy()
		const link = new URL(ready.link)
		expect(link.searchParams.get('dir')).toContain(EXPORT_FOLDER)
		expect(link.searchParams.get('scrollto')).toBe(zips[0])
		await page.goto(ready.link)
		await expect(page.locator(`[data-cy-files-list-row-name="${zips[0]}"]`)).toBeVisible()
		await dav.dispose()
		await context.close()
	})

	// @e2e project-data-export::a-manager-without-the-export-verb-is-refused
	test('a manager without the export verb is refused', async () => {
		const denied = process.env.PLANNINQ_E2E_EXPORT_DENIED_USER ?? ''
		test.skip(denied === '', 'Needs a rig whose OpenRegister role grants this user read but not export on task (PLANNINQ_E2E_EXPORT_DENIED_USER).')
		const deniedPassword = process.env.PLANNINQ_E2E_EXPORT_DENIED_PASSWORD ?? ''
		const project = await createObject(api, 'project', { title: `Geweigerd ${RUN}`, status: 'active', owner: ADMIN_USER, managers: [denied], members: [ADMIN_USER, denied] })
		const task = await createObject(api, 'task', { title: `Geweigerd ${RUN}`, project, status: 'open' })
		const dav = await userApi(denied, deniedPassword)
		try {
			const before = await exportedZips(dav, denied)
			const start = await dav.post(`/index.php/apps/planninq/api/projects/${project}/export`, { headers: { 'Content-Type': 'application/json' }, data: {} })
			expect(start.status()).toBe(202)
			expect(runExportJobs(denied)).toBe(1)

			expect(await exportedZips(dav, denied)).toEqual(before)
			const notifications = await dav.get('/ocs/v2.php/apps/notifications/api/v2/notifications', { params: { format: 'json' } })
			const subjects = ((await notifications.json()).ocs?.data ?? []).map((item: Record<string, string>) => item.subject)
			expect(subjects).toContain(`Export of Geweigerd ${RUN} failed: you do not have the export permission`)

			const audit = await api.get('/index.php/apps/openregister/api/audit-trails', { params: { action: 'export.refused', user: denied, _limit: 50 } })
			const rows = ((await audit.json()).results ?? []).filter((row: Record<string, unknown>) => JSON.stringify(row).includes(PROFILE) && JSON.stringify(row).includes(project))
			expect(rows.length).toBeGreaterThanOrEqual(1)
		} finally {
			await dav.dispose()
			await api.delete(`${OBJECTS}/task/${task}`, { failOnStatusCode: false })
			await api.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
		}
	})
})
