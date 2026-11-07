<template>
	<NcAppSettingsSection
		id="notifications"
		:name="t('planninq', 'Notifications')">
		<template #icon>
			<BellIcon :size="20" />
		</template>
		<!-- @nextcloud/vue@9 renamed NcCheckboxRadioSwitch's model prop from
		     `checked`/`update:checked` to `modelValue`/`update:modelValue`.
		     The old spelling neither reads nor writes — the switch renders
		     permanently off and the handler never fires, silently. -->
		<NcCheckboxRadioSwitch
			:modelValue="notifyAssigned"
			type="switch"
			data-testid="notify-assigned"
			@update:modelValue="onToggleAssigned">
			{{ t('planninq', 'Notify me when a task is assigned to me') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:modelValue="notifyDueReminder"
			type="switch"
			@update:modelValue="onToggleDueReminder">
			{{ t('planninq', 'Notify me 1 day before a task\'s due date') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:modelValue="notifyByEmail && hasEmail"
			:disabled="!hasEmail"
			type="switch"
			aria-describedby="planninq-email-hint"
			data-testid="notify-by-email"
			@update:modelValue="onToggleEmail">
			{{ t('planninq', 'Also send these to me by email') }}
		</NcCheckboxRadioSwitch>
		<p
			v-if="!hasEmail"
			id="planninq-email-hint"
			class="user-settings__hint"
			data-testid="notify-by-email-hint">
			{{ t('planninq', 'Add an email address in your Nextcloud personal settings to get mail.') }}
		</p>
	</NcAppSettingsSection>
	<NcAppSettingsSection
		id="nextcloud-tasks"
		:name="t('planninq', 'Nextcloud Tasks')">
		<template #icon>
			<CalendarCheckOutlineIcon :size="20" />
		</template>
		<NcCheckboxRadioSwitch
			:modelValue="exportToTasks && caldavAvailable"
			:disabled="!caldavAvailable"
			type="switch"
			aria-describedby="planninq-tasks-export-hint"
			data-testid="export-to-tasks"
			@update:modelValue="onToggleExport">
			{{ t('planninq', 'Show my tasks in Nextcloud Tasks') }}
		</NcCheckboxRadioSwitch>
		<p id="planninq-tasks-export-hint" class="user-settings__hint" data-testid="export-to-tasks-hint">
			<template v-if="caldavAvailable">
				{{ t('planninq', 'The tasks assigned to you or shared with you appear in a "Planninq" list in Nextcloud Tasks and Calendar. Changes made there are replaced by the next change in Planninq.') }}
			</template>
			<template v-else>
				{{ t('planninq', 'This server cannot write to Nextcloud Tasks, so the export is not available.') }}
			</template>
		</p>
	</NcAppSettingsSection>
</template>

<script>
/**
 * Planninq's own user-settings pane.
 *
 * Renders a bare NcAppSettingsSection, NOT a dialog: it is passed to
 * CnAppRoot's `#user-settings` slot, which supplies the host
 * NcAppSettingsDialog and the navigation entry that opens it. Before the
 * manifest shell this component owned its own NcAppSettingsDialog and its own
 * `open` prop, wired to a MainMenu footer button; both are the shell's job now,
 * so the wrapper and the prop are gone.
 */
import { NcAppSettingsSection, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import BellIcon from 'vue-material-design-icons/Bell.vue'
import CalendarCheckOutlineIcon from 'vue-material-design-icons/CalendarCheckOutline.vue'
import { useSettingsStore } from '../../store/modules/settings.js'

export default {
	name: 'UserSettings',
	components: {
		NcAppSettingsSection,
		NcCheckboxRadioSwitch,
		BellIcon,
		CalendarCheckOutlineIcon,
	},

	computed: {
		/**
		 * Whether assignment notifications are on for the current user (default on).
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
		 */
		notifyAssigned() {
			const value = useSettingsStore().settings?.notify_assigned
			return value !== false && value !== 'false'
		},

		/**
		 * Whether planninq notifications also come by email (default off).
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.3
		 */
		notifyByEmail() {
			const value = useSettingsStore().settings?.notify_by_email
			return value === true || value === 'true'
		},

		/**
		 * Whether the account has an email address; without one the email switch is disabled.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.3
		 */
		hasEmail() {
			return useSettingsStore().settings?.hasEmail === true
		},

		/**
		 * Whether the user's tasks are exported to Nextcloud Tasks (default off).
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.1
		 */
		exportToTasks() {
			const value = useSettingsStore().settings?.export_tasks_to_caldav
			return value === true || value === 'true'
		},

		/**
		 * Whether this server can write to Nextcloud Tasks (the DAV backend resolves).
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.1
		 */
		caldavAvailable() {
			return useSettingsStore().settings?.caldavAvailable === true
		},

		/**
		 * Whether due-date reminders are enabled for the current user.
		 * Defaults to true (matches the backend default) when unset.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
		 */
		notifyDueReminder() {
			const value = useSettingsStore().settings?.notify_due_reminder
			return value !== false && value !== 'false'
		},
	},

	/**
	 * @spec exclude Lifecycle glue — fetches settings so the toggle reflects the stored value.
	 */
	created() {
		useSettingsStore().fetchSettings()
	},

	methods: {
		/**
		 * Persist the email switch; the email rules' resolver reads it on every dispatch.
		 *
		 * @param {boolean} checked The new switch state
		 *
		 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.3
		 */
		async onToggleEmail(checked) {
			await useSettingsStore().saveUserSettings({ notify_by_email: checked })
		},

		/**
		 * Persist the export switch; on queues the export of the user's tasks, off removes the list.
		 *
		 * @param {boolean} checked The new switch state
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.1
		 */
		async onToggleExport(checked) {
			await useSettingsStore().saveUserSettings({ export_tasks_to_caldav: checked })
		},

		/**
		 * Persist the assignment notification switch; the server writes it
		 * through to OpenRegister's override of both assignment rules.
		 *
		 * @param {boolean} checked The new switch state
		 *
		 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
		 */
		async onToggleAssigned(checked) {
			await useSettingsStore().saveUserSettings({ notify_assigned: checked })
		},

		/**
		 * Persist the due-date reminder toggle through saveUserSettings, which
		 * writes the OpenRegister per-user notification override server-side.
		 *
		 * @param {boolean} checked The new toggle state
		 *
		 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
		 */
		async onToggleDueReminder(checked) {
			await useSettingsStore().saveUserSettings({ notify_due_reminder: checked })
		},
	},
}
</script>

<style scoped>
.user-settings__hint {
	margin: 0 0 8px;
	color: var(--color-text-maxcontrast);
}
</style>
