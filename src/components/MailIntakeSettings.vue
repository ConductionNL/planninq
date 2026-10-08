<template>
	<!-- Create tasks by email (tasks-create-by-email): one IMAP mailbox, its password kept by OpenRegister's credential broker -->
	<CnSettingsSection
		:name="t('planninq', 'Create tasks by email')"
		:description="t('planninq', 'Members mail a project address and find a task in its backlog. Planninq reads one mailbox and never stores its password.')"
		data-testid="mail-intake-settings">
		<form novalidate @submit.prevent="save">
			<NcCheckboxRadioSwitch v-model="form.enabled" type="switch" data-testid="mail-intake-enabled">
				{{ t('planninq', 'Create tasks from email') }}
			</NcCheckboxRadioSwitch>
			<NcTextField v-model="form.host"
				:label="t('planninq', 'Mail server')"
				autocomplete="off"
				data-testid="mail-intake-host" />
			<NcTextField v-model="form.port"
				:label="t('planninq', 'Port')"
				inputmode="numeric"
				autocomplete="off" />
			<div class="mail-intake-settings__field">
				<label for="mail-intake-encryption">{{ t('planninq', 'Encryption') }}</label>
				<select id="mail-intake-encryption" v-model="form.encryption">
					<option value="ssl">
						{{ t('planninq', 'TLS') }}
					</option>
					<option value="none">
						{{ t('planninq', 'None') }}
					</option>
				</select>
			</div>
			<NcTextField v-model="form.username" :label="t('planninq', 'Username')" autocomplete="off" />
			<NcPasswordField
				v-model="form.password"
				:label="t('planninq', 'Password')"
				:helperText="form.hasPassword ? t('planninq', 'A password is stored. Leave empty to keep it.') : ''"
				autocomplete="new-password"
				data-testid="mail-intake-password" />
			<NcTextField v-model="form.folder" :label="t('planninq', 'Folder')" autocomplete="off" />
			<NcTextField
				v-model="form.address"
				:label="t('planninq', 'Mailbox address')"
				:helperText="t('planninq', 'Each project gets this address with its key after a plus sign, such as planninq+VC@gemeente.nl.')"
				autocomplete="off"
				data-testid="mail-intake-address" />
			<NcCheckboxRadioSwitch v-model="form.requireAuth" type="switch">
				{{ t('planninq', 'Only accept mail that passed SPF and DKIM') }}
			</NcCheckboxRadioSwitch>
			<NcTextField v-model="form.sizeLimit"
				:label="t('planninq', 'Largest attachments per mail (MB)')"
				inputmode="numeric"
				autocomplete="off" />

			<div v-if="message"
				role="status"
				:class="ok ? 'success-message' : 'error-message'"
				data-testid="mail-intake-message">
				{{ message }}
			</div>
			<div class="mail-intake-settings__actions">
				<NcButton variant="primary" type="submit" :disabled="busy">
					{{ t('planninq', 'Save') }}
				</NcButton>
				<NcButton variant="secondary"
					:disabled="busy"
					data-testid="mail-intake-test"
					@click="testConnection">
					{{ t('planninq', 'Test connection') }}
				</NcButton>
			</div>
		</form>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcButton, NcCheckboxRadioSwitch, NcPasswordField, NcTextField } from '@nextcloud/vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { mailFormFrom, mailSettingsPayload } from '../utils/mailIntake.js'

/**
 * MailIntakeSettings: the admin section that connects the intake mailbox,
 * saves it and tests the connection.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.2
 */
export default {
	name: 'MailIntakeSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcCheckboxRadioSwitch,
		NcPasswordField,
		NcTextField,
	},

	data() {
		return {
			form: mailFormFrom(useSettingsStore().settings),
			busy: false,
			message: '',
			ok: false,
		}
	},

	methods: {
		t,

		/**
		 * Save the form; the password field is emptied once it is stored.
		 *
		 * @return {Promise<boolean>} Whether the settings were stored.
		 *
		 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.2
		 */
		async save() {
			this.busy = true
			this.message = ''
			const sent = mailSettingsPayload(this.form)
			const store = useSettingsStore()
			const result = await store.saveSettings(sent)
			// saveSettings() keeps the POST answer as the settings; read them back.
			await store.fetchSettings()
			this.busy = false
			const stored = store.settings
			// A value the server refused is not echoed back; compare what came back with what was sent.
			this.ok = !!result && !!stored
				&& stored.mail_intake_port === sent.mail_intake_port
				&& stored.mail_intake_address === sent.mail_intake_address.toLowerCase()
			if (this.ok) {
				this.form = mailFormFrom(stored)
				this.message = t('planninq', 'Mail settings saved')
			} else {
				this.message = t('planninq', 'The mail settings were not saved. Check the port and the address.')
			}

			return this.ok
		},

		/**
		 * Save, then log in to the mailbox and report how many messages the folder holds.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.2
		 */
		async testConnection() {
			if (!await this.save()) {
				return
			}

			this.busy = true
			try {
				const response = await fetch(generateUrl('/apps/planninq/api/settings/mail-test'), {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
				})
				const data = await response.json()
				this.ok = response.ok && data.success === true
				this.message = this.ok
					? t('planninq', 'The connection works. The folder holds {count} messages.', { count: data.messages })
					: t('planninq', 'The connection failed: {reason}', { reason: data.error ?? '' })
			} catch (error) {
				this.ok = false
				this.message = t('planninq', 'The connection failed: {reason}', { reason: String(error?.message ?? error) })
			} finally {
				this.busy = false
			}
		},
	},
}
</script>

<style scoped>
.mail-intake-settings__field {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	margin-block: calc(var(--default-grid-baseline) * 2);
}

.mail-intake-settings__actions {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-top: calc(var(--default-grid-baseline) * 3);
}
</style>
