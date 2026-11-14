<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<!-- Create tasks by email (tasks-create-by-email task 1.2): the intake mailbox -->
	<CnSettingsSection
		:name="t('planninq', 'Create tasks by email')"
		:description="t('planninq', 'A project member\'s mail to the project\'s address becomes a task in its backlog.')"
		data-testid="mail-intake-settings">
		<NcCheckboxRadioSwitch
			v-model="form.enabled"
			type="switch"
			data-testid="mail-intake-enabled">
			{{ t('planninq', 'Turn mail into tasks') }}
		</NcCheckboxRadioSwitch>
		<div class="mail-intake-settings__fields">
			<NcTextField
				v-model="form.host"
				:label="t('planninq', 'Mail server')"
				data-testid="mail-intake-host" />
			<NcTextField
				v-model="form.port"
				:label="t('planninq', 'Port')"
				type="number"
				data-testid="mail-intake-port" />
			<NcSelect
				v-model="encryptionOption"
				:options="encryptionOptions"
				:inputLabel="t('planninq', 'Encryption')"
				:clearable="false"
				label="label"
				data-testid="mail-intake-encryption" />
			<NcTextField
				v-model="form.username"
				:label="t('planninq', 'Username')"
				autocomplete="off"
				data-testid="mail-intake-username" />
			<NcPasswordField
				v-model="form.password"
				:label="t('planninq', 'Password')"
				:helperText="passwordHelp"
				autocomplete="new-password"
				data-testid="mail-intake-password" />
			<NcTextField
				v-model="form.folder"
				:label="t('planninq', 'Folder')"
				data-testid="mail-intake-folder" />
			<NcTextField
				v-model="form.address"
				:label="t('planninq', 'Address people mail to')"
				type="email"
				data-testid="mail-intake-address" />
			<NcTextField
				v-model="form.sizeLimitMb"
				:label="t('planninq', 'Largest attachment (MB)')"
				type="number"
				data-testid="mail-intake-size-limit" />
		</div>
		<NcCheckboxRadioSwitch
			v-model="form.requireAuth"
			type="switch"
			data-testid="mail-intake-require-auth">
			{{ t('planninq', 'Only accept mail the sender\'s domain has signed') }}
		</NcCheckboxRadioSwitch>
		<div class="mail-intake-settings__actions">
			<NcButton
				variant="primary"
				:disabled="saving"
				data-testid="mail-intake-save"
				@click="save">
				{{ t('planninq', 'Save') }}
			</NcButton>
			<NcButton
				variant="secondary"
				:disabled="testing || saving"
				data-testid="mail-intake-test"
				@click="test">
				<template v-if="testing" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Test connection') }}
			</NcButton>
		</div>
		<p
			v-if="message"
			class="mail-intake-settings__message"
			:class="[{ 'mail-intake-settings__message--error': failed }]"
			role="status"
			data-testid="mail-intake-message">
			{{ message }}
		</p>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcButton, NcCheckboxRadioSwitch, NcLoadingIcon, NcPasswordField, NcSelect, NcTextField } from '@nextcloud/vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { ENCRYPTIONS, hasStoredPassword, mailIntakeForm, mailIntakePayload, refusedKeys, testMessage } from '../utils/mailIntake.js'

/**
 * MailIntakeSettings: the admin section that connects the intake mailbox,
 * saves it (the password goes to OpenRegister's credential broker) and
 * tests the connection.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
export default {
	name: 'MailIntakeSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcPasswordField,
		NcSelect,
		NcTextField,
	},

	data() {
		return {
			form: mailIntakeForm(useSettingsStore().settings),
			saving: false,
			testing: false,
			message: '',
			failed: false,
		}
	},

	computed: {
		/**
		 * @return {object} The settings as the store holds them.
		 */
		storeSettings() {
			return useSettingsStore().settings
		},

		/**
		 * @return {Array<{id: string, label: string}>} The encryption choices.
		 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
		 */
		encryptionOptions() {
			const labels = {
				ssl: 'SSL/TLS',
				tls: 'STARTTLS',
				none: t('planninq', 'None'),
			}
			return ENCRYPTIONS.map((id) => ({ id, label: labels[id] }))
		},

		encryptionOption: {
			/**
			 * @return {{id: string, label: string}} The chosen encryption.
			 */
			get() {
				return this.encryptionOptions.find((option) => option.id === this.form.encryption)
			},

			/**
			 * @param {{id: string}} option The chosen option.
			 */
			set(option) {
				this.form.encryption = option?.id ?? 'ssl'
			},
		},

		/**
		 * @return {string} A hint while a password is stored; it is never shown.
		 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
		 */
		passwordHelp() {
			return hasStoredPassword(this.storeSettings)
				? t('planninq', 'Leave empty to keep the stored password.')
				: ''
		},
	},

	watch: {
		/**
		 * Fill the form once the settings arrive (the page may fetch them after mounting).
		 *
		 * @param {object} settings The settings.
		 */
		storeSettings(settings) {
			this.form = mailIntakeForm(settings)
		},
	},

	methods: {
		t,

		/**
		 * Save the mailbox settings and say which values the server refused.
		 *
		 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
		 */
		async save() {
			this.saving = true
			const payload = mailIntakePayload(this.form)
			const saved = await useSettingsStore().saveSettings(payload)
			this.saving = false
			if (saved === null) {
				this.show(t('planninq', 'Not every value was saved. Check the server, port, address and size.'), true)
				return
			}

			const refused = refusedKeys(payload, saved)
			this.form = mailIntakeForm(saved)
			if (refused.length > 0) {
				this.show(t('planninq', 'Not every value was saved. Check the server, port, address and size.'), true)
				return
			}
			this.show(t('planninq', 'Settings saved successfully'), false)
		},

		/**
		 * Test the connection with the saved settings.
		 *
		 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
		 */
		async test() {
			this.testing = true
			let result = null
			try {
				const response = await fetch(generateUrl('/apps/planninq/api/settings/mail-intake/test'), {
					method: 'POST',
					headers: { requesttoken: OC.requestToken },
				})
				if (response.ok) {
					result = await response.json()
				}
			} catch (error) {
				console.error('Mail intake connection test failed:', error)
			}
			this.testing = false
			this.show(testMessage(result), result?.ok !== true)
		},

		/**
		 * @param {string} message The message.
		 * @param {boolean} failed Whether it reports a failure.
		 */
		show(message, failed) {
			this.message = message
			this.failed = failed
		},
	},
}
</script>

<style scoped>
.mail-intake-settings__fields {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(calc(var(--default-grid-baseline) * 60), 1fr));
	gap: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
	margin-block: calc(var(--default-grid-baseline) * 3);
}

.mail-intake-settings__actions {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block-start: calc(var(--default-grid-baseline) * 3);
}

.mail-intake-settings__message {
	margin-block-start: calc(var(--default-grid-baseline) * 2);
	color: var(--color-success-text);
}

.mail-intake-settings__message--error {
	color: var(--color-error-text);
}
</style>
