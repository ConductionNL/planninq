<template>
	<NcAppSettingsDialog
		:open="open"
		:show-navigation="false"
		:name="t('planninq', 'Planninq settings')"
		@update:open="$emit('update:open', $event)">
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
				:model-value="notifyDueReminder"
				type="switch"
				@update:modelValue="onToggleDueReminder">
				{{ t('planninq', 'Notify me 1 day before a task\'s due date') }}
			</NcCheckboxRadioSwitch>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { NcAppSettingsDialog, NcAppSettingsSection, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import BellIcon from 'vue-material-design-icons/Bell.vue'
import { useSettingsStore } from '../../store/modules/settings.js'

export default {
	name: 'UserSettings',
	components: {
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcCheckboxRadioSwitch,
		BellIcon,
	},
	props: {
		open: {
			type: Boolean,
			default: false,
		},
	},

	// Vue 3 warns about "extraneous non-emits event listeners" for any event a
	// component emits without declaring it, and lets undeclared listeners fall
	// through onto the root element as native handlers.
	emits: ['update:open'],
	computed: {
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
