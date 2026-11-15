/**
 * The pure parts of the mail intake settings: the form, what is saved, what a
 * connection test says, and a project's task address (tasks-create-by-email
 * tasks 1.2 and 1.3).
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-each-project-with-a-key-has-a-task-address
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match)),
}))

const { hasStoredPassword, mailIntakeForm, mailIntakePayload, projectAddress, refusedKeys, testMessage } = await import('../../src/utils/mailIntake.js')

describe('mailIntakeForm', () => {
	it('reads the saved settings and never carries a password', () => {
		const form = mailIntakeForm({
			mail_intake_enabled: '1',
			mail_intake_host: 'imap.example.org',
			mail_intake_port: '143',
			mail_intake_encryption: 'tls',
			mail_intake_username: 'planninq',
			mail_intake_credential_ref: 'cred-1',
			mail_intake_folder: 'Taken',
			mail_intake_address: 'planninq@gemeente.nl',
			mail_intake_require_auth: '0',
			mail_intake_size_limit_mb: '25',
		})

		expect(form).toEqual({
			enabled: true,
			host: 'imap.example.org',
			port: '143',
			encryption: 'tls',
			username: 'planninq',
			password: '',
			folder: 'Taken',
			address: 'planninq@gemeente.nl',
			requireAuth: false,
			sizeLimitMb: '25',
		})
	})

	it('falls back to the server defaults, and to ssl for an unknown encryption', () => {
		const form = mailIntakeForm({ mail_intake_encryption: 'rot13' })

		expect(form.enabled).toBe(false)
		expect(form.port).toBe('993')
		expect(form.encryption).toBe('ssl')
		expect(form.folder).toBe('INBOX')
		expect(form.requireAuth).toBe(true)
	})
})

describe('mailIntakePayload', () => {
	it('sends a typed password and trims the text fields', () => {
		const payload = mailIntakePayload({ ...mailIntakeForm({}), enabled: true, host: ' imap.example.org ', password: 's3cret!' })

		expect(payload.mail_intake_enabled).toBe('1')
		expect(payload.mail_intake_host).toBe('imap.example.org')
		expect(payload.mail_intake_password).toBe('s3cret!')
		expect(payload.mail_intake_require_auth).toBe('1')
	})

	it('leaves the password out when none was typed, so the stored one is kept', () => {
		expect(mailIntakePayload(mailIntakeForm({}))).not.toHaveProperty('mail_intake_password')
	})
})

describe('refusedKeys', () => {
	it('names the values the server did not keep, and a password that left no reference', () => {
		const payload = { mail_intake_port: '99999', mail_intake_host: 'imap.example.org', mail_intake_password: 'x' }
		const saved = { mail_intake_port: '993', mail_intake_host: 'imap.example.org', mail_intake_credential_ref: '' }

		expect(refusedKeys(payload, saved)).toEqual(['mail_intake_port', 'mail_intake_password'])
		expect(hasStoredPassword(saved)).toBe(false)
	})

	it('is empty when everything was kept', () => {
		const payload = { mail_intake_host: 'imap.example.org', mail_intake_password: 'x' }

		expect(refusedKeys(payload, { mail_intake_host: 'imap.example.org', mail_intake_credential_ref: 'cred-1' })).toEqual([])
	})
})

describe('testMessage', () => {
	it('reports a working connection with the message count', () => {
		expect(testMessage({ ok: true, reason: null, messages: 4 })).toBe('Connected. Messages in the folder: 4')
	})

	it('says why a test failed, and treats a failed request as a refused connection', () => {
		expect(testMessage({ ok: false, reason: 'incomplete' })).toContain('Fill in the server')
		expect(testMessage({ ok: false, reason: 'no-client' })).toContain('No IMAP client')
		expect(testMessage({ ok: false, reason: 'no-password' })).toContain('stored password')
		expect(testMessage({ ok: false, reason: 'connect-failed' })).toBe('The mail server refused the connection, the login or the folder.')
		expect(testMessage(null)).toBe('The mail server refused the connection, the login or the folder.')
	})
})

describe('projectAddress', () => {
	it('adds the project key as plus-address', () => {
		expect(projectAddress('planninq@gemeente.nl', 'VC')).toBe('planninq+VC@gemeente.nl')
	})

	it('is null without a key or a usable address', () => {
		expect(projectAddress('planninq@gemeente.nl', '')).toBeNull()
		expect(projectAddress('', 'VC')).toBeNull()
		expect(projectAddress('@gemeente.nl', 'VC')).toBeNull()
	})
})
