/**
 * The pure parts of the mail intake settings (tasks-create-by-email tasks
 * 1.2 and 1.3): the form an admin edits, what is saved from it, what a test
 * result says, and a project's task address.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { translate as t } from '@nextcloud/l10n'

/** The encryption choices the server accepts. */
export const ENCRYPTIONS = ['ssl', 'tls', 'none']

/**
 * The form an admin edits, from the settings the server returned.
 *
 * @param {object} settings The settings (mail_intake_* keys).
 * @return {object} The form; the password is always empty, it is never sent back.
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
export function mailIntakeForm(settings = {}) {
	return {
		enabled: settings.mail_intake_enabled === '1',
		host: settings.mail_intake_host ?? '',
		port: settings.mail_intake_port ?? '993',
		encryption: ENCRYPTIONS.includes(settings.mail_intake_encryption) ? settings.mail_intake_encryption : 'ssl',
		username: settings.mail_intake_username ?? '',
		password: '',
		folder: settings.mail_intake_folder || 'INBOX',
		address: settings.mail_intake_address ?? '',
		requireAuth: settings.mail_intake_require_auth !== '0',
		sizeLimitMb: settings.mail_intake_size_limit_mb ?? '10',
	}
}

/**
 * Whether a password is stored for the mailbox.
 *
 * @param {object} settings The settings.
 * @return {boolean}
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
export function hasStoredPassword(settings = {}) {
	return (settings.mail_intake_credential_ref ?? '') !== ''
}

/**
 * What to save from the form. A password goes along only when one was typed,
 * so saving without one keeps the stored password.
 *
 * @param {object} form The form.
 * @return {object} The mail_intake_* keys to post.
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
export function mailIntakePayload(form) {
	const payload = {
		mail_intake_enabled: form.enabled ? '1' : '0',
		mail_intake_host: String(form.host).trim(),
		mail_intake_port: String(form.port).trim(),
		mail_intake_encryption: form.encryption,
		mail_intake_username: String(form.username).trim(),
		mail_intake_folder: String(form.folder).trim(),
		mail_intake_address: String(form.address).trim(),
		mail_intake_require_auth: form.requireAuth ? '1' : '0',
		mail_intake_size_limit_mb: String(form.sizeLimitMb).trim(),
	}
	if (form.password !== '') {
		payload.mail_intake_password = form.password
	}
	return payload
}

/**
 * The keys of a saved payload the server did not keep (it refuses invalid values).
 *
 * @param {object} payload What was posted.
 * @param {object} saved The settings the server answered with.
 * @return {Array<string>} The refused keys; the password counts as refused when no reference came back.
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
export function refusedKeys(payload, saved = {}) {
	return Object.keys(payload).filter((key) => {
		if (key === 'mail_intake_password') {
			return !hasStoredPassword(saved)
		}
		return String(saved[key] ?? '') !== payload[key]
	})
}

/**
 * The message for a connection test result.
 *
 * @param {object|null} result `{ok, reason, messages}`, or null when the request failed.
 * @return {string}
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
export function testMessage(result) {
	if (result?.ok === true) {
		return t('planninq', 'Connected. Messages in the folder: {count}', { count: result.messages })
	}
	switch (result?.reason) {
		case 'incomplete':
			return t('planninq', 'Fill in the server, username and password and save before testing.')
		case 'no-client':
			return t('planninq', 'No IMAP client is installed on this server, so mailboxes cannot be read yet.')
		case 'no-password':
			return t('planninq', 'The stored password could not be read. Enter it again and save.')
		default:
			return t('planninq', 'The mail server refused the connection, the login or the folder.')
	}
}

/**
 * A project's task address: the intake address with the project key as plus-address.
 *
 * @param {string} address The intake address, for example `planninq@gemeente.nl`.
 * @param {string} key The project key, for example `VC`.
 * @return {string|null} `planninq+VC@gemeente.nl`, or null without an address or key.
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-each-project-with-a-key-has-a-task-address
 */
export function projectAddress(address, key) {
	const at = String(address ?? '').lastIndexOf('@')
	if (!key || at < 1) {
		return null
	}
	return address.slice(0, at) + '+' + key + address.slice(at)
}
