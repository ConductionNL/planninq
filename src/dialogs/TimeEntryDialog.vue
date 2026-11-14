<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog
		:name="isEdit ? t('planninq', 'Edit time entry') : t('planninq', 'Log time')"
		@closing="$emit('close')">
		<template #default>
			<div class="time-entry-dialog__body">
				<p v-if="longHours"
					class="time-entry-dialog__warning"
					role="alert"
					data-testid="time-entry-long-timer">
					<!-- Only shown past twelve hours, so the count is always plural. -->
					{{ t('planninq', 'This timer ran for {hours} hours. Check the duration before you save.', { hours: longHours }) }}
				</p>
				<div class="time-entry-dialog__field">
					<NcTextField
						v-model="durationInput"
						data-testid="time-entry-duration"
						:label="t('planninq', 'Duration')"
						:error="!!durationError"
						:helperText="durationError || t('planninq', 'e.g. 2h 30m, 90m, 1.5h')"
						required />
				</div>

				<div class="time-entry-dialog__field">
					<label class="time-entry-dialog__label" for="time-entry-date">
						{{ t('planninq', 'Date') }}
					</label>
					<input
						id="time-entry-date"
						v-model="date"
						type="date"
						class="time-entry-dialog__date"
						:aria-label="t('planninq', 'Date')">
				</div>

				<div v-if="workTypes.length" class="time-entry-dialog__field">
					<NcSelect
						v-model="workType"
						data-testid="time-entry-work-type"
						:options="workTypes"
						:clearable="false"
						:inputLabel="t('planninq', 'Work type')"
						:placeholder="t('planninq', 'Choose a work type')"
						required />
				</div>

				<div class="time-entry-dialog__field">
					<NcTextField
						v-model="description"
						:label="t('planninq', 'Description (optional)')" />
				</div>

				<div v-if="submitError" class="time-entry-dialog__error" role="alert">
					{{ submitError }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="saving" data-testid="time-entry-cancel" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || !isValid"
				data-testid="time-entry-save"
				@click="save">
				<template v-if="saving" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ isEdit ? t('planninq', 'Save') : t('planninq', 'Log time') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
/**
 * TimeEntryDialog.
 *
 * Create or edit a TimeEntry on a task: a duration (parsed from the natural
 * formats the estimate input accepts), a date (defaulting to today) and an
 * optional description. Writes go through the timeEntries store to the
 * OpenRegister object API (ADR-022); the per-owner RBAC guard lives on the
 * schema, so a non-owner edit is rejected server-side regardless of this UI.
 *
 * @spec openspec/specs/time-tracking/spec.md
 */
import { NcButton, NcDialog, NcLoadingIcon, NcSelect, NcTextField } from '@nextcloud/vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { useTimeEntriesStore } from '../store/timeEntries.js'
import { formatDuration, parseDuration } from '../utils/durationParser.js'
import { localIsoDate } from '../utils/localDate.js'
import { workTypeNames } from '../utils/workTypes.js'

export default {
	name: 'TimeEntryDialog',

	components: { NcButton, NcDialog, NcLoadingIcon, NcSelect, NcTextField },

	props: {
		/** The task UUID this entry belongs to. */
		taskId: {
			type: String,
			required: true,
		},

		/** The entry being edited, or null when logging a new one. */
		entry: {
			type: Object,
			default: null,
		},

		/** Minutes a stopped timer measured, prefilled into the duration; null when logging by hand. */
		prefillMinutes: {
			type: Number,
			default: null,
		},

		/** Hours a long timer ran, shown as a warning before saving; 0 for none. */
		longHours: {
			type: Number,
			default: 0,
		},
	},

	emits: ['close', 'saved'],

	data() {
		return {
			durationInput: this.entry?.duration ? formatDuration(this.entry.duration) : (this.prefillMinutes ? formatDuration(this.prefillMinutes) : ''),
			workType: this.entry?.workType || null,
			settingsStore: useSettingsStore(),
			date: this.entry?.date || localIsoDate(),
			description: this.entry?.description || '',
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Presentational flag — true when editing an existing entry.
		 */
		isEdit() {
			return !!this.entry?.id
		},

		/**
		 * Parsed duration in minutes, or null when the input is unparseable.
		 *
		 * @spec openspec/specs/time-tracking/spec.md
		 */
		parsedMinutes() {
			return parseDuration(this.durationInput)
		},

		/**
		 * Inline validation error for the duration field.
		 *
		 * @spec openspec/specs/time-tracking/spec.md
		 */
		durationError() {
			if (this.durationInput.trim() === '') {
				return ''
			}
			return this.parsedMinutes === null
				? this.t('planninq', 'Enter a valid duration (e.g. 2h 30m, 90m, 1.5h)')
				: ''
		},

		/**
		 * Whether the form can be submitted (valid duration + a date).
		 *
		 * @spec openspec/specs/time-tracking/spec.md
		 */
		isValid() {
			return this.parsedMinutes !== null && !!this.date && (!this.workTypes.length || !!this.workType)
		},

		/**
		 * The admin's work types; when there are any, the entry must name one.
		 * An entry's own type stays choosable after it was retired.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.3
		 */
		workTypes() {
			const types = workTypeNames(this.settingsStore.settings?.work_types)
			const own = this.entry?.workType
			return own && types.length && !types.includes(own) ? [...types, own] : types
		},
	},

	/**
	 * @spec exclude Lifecycle glue — reads the work types when the settings are not loaded yet.
	 */
	mounted() {
		if (this.settingsStore.settings?.work_types === undefined) {
			this.settingsStore.fetchSettings()
		}
	},

	methods: {
		/**
		 * Validate and persist the entry via the timeEntries store.
		 *
		 * @spec openspec/specs/time-tracking/spec.md
		 */
		async save() {
			if (!this.isValid) {
				return
			}
			this.saving = true
			this.submitError = ''
			const store = useTimeEntriesStore()
			const payload = {
				task: this.taskId,
				duration: this.parsedMinutes,
				date: this.date,
				description: this.description,
			}
			if (this.workTypes.length) {
				payload.workType = this.workType
			}
			try {
				const result = this.isEdit
					? await store.update(this.entry.id, payload)
					: await store.create(payload)
				if (!result) {
					throw new Error(store.error || 'save-failed')
				}
				this.$emit('saved')
			} catch {
				this.submitError = store.error || this.t('planninq', 'Could not save the time entry. Please try again.')
				showError(this.submitError)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.time-entry-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 8px 0;
}

.time-entry-dialog__label {
	display: block;
	margin-bottom: 4px;
	font-weight: 600;
}

.time-entry-dialog__date {
	width: 100%;
	min-height: 44px;
	padding: 8px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.time-entry-dialog__warning {
	margin: 0;
	padding: 8px 12px;
	border-radius: var(--border-radius-large);
	background: var(--color-warning);
	color: var(--color-warning-text);
}

.time-entry-dialog__error {
	color: var(--color-error);
}
</style>
