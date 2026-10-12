/**
 * Task intake by email (tasks-create-by-email): the address a project's mail
 * goes to and the draft the admin form sends.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.3
 */

/**
 * The address that mail for a project goes to: the mailbox address with the
 * project key as plus-suffix. Empty when intake is off, no address is set, or
 * the project has no key.
 *
 * @param {object} settings The settings document (`mail_intake_enabled`, `mail_intake_address`).
 * @param {string} key The project key.
 * @return {string}
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.3
 */
export function projectMailAddress(settings, key) {
	const address = String(settings?.mail_intake_address ?? '').trim()
	const at = address.lastIndexOf('@')
	const cleanKey = String(key ?? '').trim().toUpperCase()
	if (settings?.mail_intake_enabled !== 'true' || at < 1 || cleanKey === '') {
		return ''
	}

	return address.slice(0, at) + '+' + cleanKey + address.slice(at)
}

/**
 * The body the admin form posts. The password is sent only when typed, so
 * saving other fields never replaces or clears the stored credential.
 *
 * @param {object} form The form state.
 * @return {object}
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.2
 */
export function mailSettingsPayload(form) {
	const payload = {
		mail_intake_enabled: form.enabled ? 'true' : 'false',
		mail_intake_host: String(form.host ?? '').trim(),
		mail_intake_port: String(form.port ?? '').trim(),
		mail_intake_encryption: form.encryption,
		mail_intake_username: String(form.username ?? '').trim(),
		mail_intake_folder: String(form.folder ?? '').trim(),
		mail_intake_address: String(form.address ?? '').trim(),
		mail_intake_require_auth: form.requireAuth ? 'true' : 'false',
		mail_intake_size_limit_mb: String(form.sizeLimit ?? '').trim(),
	}
	if (String(form.password ?? '') !== '') {
		payload.mail_intake_password = form.password
	}

	return payload
}

/**
 * The form state for the stored settings. The password is never read back.
 *
 * @param {object} settings The settings document.
 * @return {object}
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.2
 */
export function mailFormFrom(settings) {
	return {
		enabled: settings?.mail_intake_enabled === 'true',
		host: settings?.mail_intake_host ?? '',
		port: settings?.mail_intake_port ?? '993',
		encryption: settings?.mail_intake_encryption ?? 'ssl',
		username: settings?.mail_intake_username ?? '',
		folder: settings?.mail_intake_folder ?? 'INBOX',
		address: settings?.mail_intake_address ?? '',
		requireAuth: settings?.mail_intake_require_auth !== 'false',
		sizeLimit: settings?.mail_intake_size_limit_mb ?? '10',
		password: '',
		hasPassword: String(settings?.mail_intake_credential_ref ?? '') !== '',
	}
}
