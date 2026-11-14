<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog
		:name="isEdit ? t('planninq', 'Edit time entry') : t('planninq', 'Log time')"
		@closing="$emit('close')">
		<template #default>
			<div class="time-entry-dialog__body">
				<p v-if="!hoursApp"
					class="time-entry-dialog__error"
					role="alert"
					data-testid="time-entry-needs-hours-app">
					{{ hoursAppMissing }}
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
				:disabled="saving || !isValid || !hoursApp"
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
 * The hours are stored on humaniq's TimeEntry: without humaniq the dialog
 * says so and cannot save (plannedtimeentry-reads-humaniqs-hours).
 *
 * @spec openspec/specs/time-tracking/spec.md
 */
import { NcButton, NcDialog, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import { hoursAppMissingText, useTimeEntriesStore } from '../store/timeEntries.js'
import { formatDuration, parseDuration } from '../utils/durationParser.js'
import { localIsoDate } from '../utils/localDate.js'
import { hoursAppAvailable } from '../utils/loggedHours.js'

export default {
	name: 'TimeEntryDialog',

	components: { NcButton, NcDialog, NcLoadingIcon, NcTextField },

	props: {
		/** The task UUID this entry belongs to. */
		taskId: {
			type: String,
			required: true,
		},

		/** The project UUID of that task, stored as humaniq's `projectId`. */
		projectId: {
			type: String,
			default: '',
		},

		/** The entry being edited, or null when logging a new one. */
		entry: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'saved'],

	data() {
		return {
			durationInput: this.entry?.duration ? formatDuration(this.entry.duration) : '',
			date: this.entry?.date || localIsoDate(),
			description: this.entry?.description || '',
			saving: false,
			submitError: '',
			hoursApp: hoursAppAvailable(),
		}
	},

	computed: {
		/**
		 * The sentence shown instead of a save when humaniq is absent.
		 *
		 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#scenario-no-humaniq-logging-time-says-why
		 */
		hoursAppMissing() {
			return hoursAppMissingText()
		},

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
			return this.parsedMinutes !== null && !!this.date
		},
	},

	methods: {
		/**
		 * Validate and persist the entry via the timeEntries store.
		 *
		 * @spec openspec/specs/time-tracking/spec.md
		 */
		async save() {
			if (!this.isValid || !this.hoursApp) {
				return
			}
			this.saving = true
			this.submitError = ''
			const store = useTimeEntriesStore()
			const payload = {
				task: this.taskId,
				...(this.projectId && !this.isEdit ? { project: this.projectId } : {}),
				duration: this.parsedMinutes,
				date: this.date,
				description: this.description,
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

.time-entry-dialog__error {
	color: var(--color-error);
}
</style>
