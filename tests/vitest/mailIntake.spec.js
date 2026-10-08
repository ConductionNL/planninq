/**
 * Vitest unit tests for task intake by email (tasks-create-by-email): the
 * project address shown in the settings and the admin form payload.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.3
 */
import { describe, expect, it } from 'vitest'
import { mailFormFrom, mailSettingsPayload, projectMailAddress } from '../../src/utils/mailIntake.js'

describe('projectMailAddress (scenario: project settings show the project address)', () => {
	const on = { mail_intake_enabled: 'true', mail_intake_address: 'planninq@gemeente.nl' }

	it('puts the project key after a plus sign in the mailbox address', () => {
		expect(projectMailAddress(on, 'VC')).toBe('planninq+VC@gemeente.nl')
		expect(projectMailAddress(on, ' vc ')).toBe('planninq+VC@gemeente.nl')
	})

	it('shows nothing while intake is off, without an address, or for a project without a key', () => {
		expect(projectMailAddress({ ...on, mail_intake_enabled: 'false' }, 'VC')).toBe('')
		expect(projectMailAddress({ mail_intake_enabled: 'true', mail_intake_address: '' }, 'VC')).toBe('')
		expect(projectMailAddress(on, '')).toBe('')
		expect(projectMailAddress(on, null)).toBe('')
	})
})

describe('mailSettingsPayload (scenario: an admin connects a mailbox)', () => {
	const form = { ...mailFormFrom({ mail_intake_credential_ref: 'cred-1' }), enabled: true, host: ' imap.example.nl ', username: 'planninq', address: 'planninq@gemeente.nl' }

	it('sends the password only when one was typed', () => {
		expect(mailSettingsPayload(form)).not.toHaveProperty('mail_intake_password')
		expect(mailSettingsPayload({ ...form, password: 's3cret' }).mail_intake_password).toBe('s3cret')
	})

	it('never carries the credential reference back to the server', () => {
		expect(Object.keys(mailSettingsPayload({ ...form, password: 'x' }))).not.toContain('mail_intake_credential_ref')
	})

	it('trims the fields and writes the switches as strings', () => {
		const payload = mailSettingsPayload(form)
		expect(payload.mail_intake_host).toBe('imap.example.nl')
		expect(payload.mail_intake_enabled).toBe('true')
		expect(payload.mail_intake_require_auth).toBe('true')
	})
})

describe('mailFormFrom', () => {
	it('knows a password is stored from the reference and never holds it', () => {
		expect(mailFormFrom({ mail_intake_credential_ref: 'cred-1' })).toMatchObject({ hasPassword: true, password: '' })
		expect(mailFormFrom({})).toMatchObject({ hasPassword: false, port: '993', encryption: 'ssl', folder: 'INBOX', requireAuth: true })
	})
})
