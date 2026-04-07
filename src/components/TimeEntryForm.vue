<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@see openspec/changes/time-tracking-mvp/tasks.md#task-4
-->
<template>
	<form class="time-entry-form" @submit.prevent="onSubmit">
		<h4 class="time-entry-form__title">
			{{ t('planix', 'Log time') }}
		</h4>

		<DurationInput
			:value="duration"
			:label="t('planix', 'Duration')"
			:placeholder="'e.g. 1h 30m'"
			@input="duration = $event" />

		<NcTextField
			type="date"
			:value="date"
			:label="t('planix', 'Date')"
			@update:value="date = $event" />

		<NcTextField
			:value="description"
			:label="t('planix', 'Description (optional)')"
			:placeholder="t('planix', 'What did you work on?')"
			@update:value="description = $event" />

		<div class="time-entry-form__actions">
			<NcButton type="tertiary" @click="$emit('cancel')">
				{{ t('planix', 'Cancel') }}
			</NcButton>
			<NcButton
				type="primary"
				native-type="submit"
				:disabled="!isValid || saving">
				<template v-if="saving" #icon>
					<NcLoadingIcon :size="20" />
				</template>
				{{ editEntry ? t('planix', 'Update') : t('planix', 'Log time') }}
			</NcButton>
		</div>
	</form>
</template>

<script>
/**
 * Form for creating or editing a time entry on a task.
 *
 * @see openspec/changes/time-tracking-mvp/tasks.md#task-4
 */
import { NcButton, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { useObjectStore } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import DurationInput from './DurationInput.vue'

const REGISTER = 'planix'
const TIME_ENTRY_SCHEMA = 'timeEntry'

export default {
	name: 'TimeEntryForm',

	components: { NcButton, NcLoadingIcon, NcTextField, DurationInput },

	props: {
		/** UUID of the task this time entry belongs to */
		taskId: {
			type: String,
			required: true,
		},
		/** Existing time entry to edit (null = create new) */
		editEntry: {
			type: Object,
			default: null,
		},
	},

	data() {
		const today = new Date().toISOString().slice(0, 10)
		return {
			duration: this.editEntry?.duration ?? null,
			date: this.editEntry?.date ?? today,
			description: this.editEntry?.description ?? '',
			saving: false,
		}
	},

	computed: {
		isValid() {
			return this.duration != null && this.duration > 0 && !!this.date
		},
	},

	watch: {
		editEntry(entry) {
			if (entry) {
				this.duration = entry.duration
				this.date = entry.date
				this.description = entry.description || ''
			} else {
				this.reset()
			}
		},
	},

	methods: {
		t,

		/**
		 * Submit the time entry (create or update).
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-4
		 */
		async onSubmit() {
			if (!this.isValid) return
			this.saving = true

			try {
				const objectStore = useObjectStore()
				if (!objectStore.objectTypeRegistry?.[TIME_ENTRY_SCHEMA]) {
					objectStore.registerObjectType(TIME_ENTRY_SCHEMA, TIME_ENTRY_SCHEMA, REGISTER)
				}

				const data = {
					task: this.taskId,
					user: getCurrentUser()?.uid || '',
					duration: this.duration,
					date: this.date,
					description: this.description || undefined,
				}

				if (this.editEntry?.id) {
					data.id = this.editEntry.id
				}

				const result = await objectStore.saveObject(TIME_ENTRY_SCHEMA, data)
				if (result) {
					showSuccess(
						this.editEntry
							? t('planix', 'Time entry updated')
							: t('planix', 'Time logged successfully'),
					)
					this.$emit('saved', result)
					if (!this.editEntry) this.reset()
				} else {
					showError(t('planix', 'Failed to save time entry'))
				}
			} catch (err) {
				console.error('TimeEntryForm save error:', err)
				showError(t('planix', 'Failed to save time entry'))
			} finally {
				this.saving = false
			}
		},

		reset() {
			this.duration = null
			this.date = new Date().toISOString().slice(0, 10)
			this.description = ''
		},
	},
}
</script>

<style scoped>
.time-entry-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 16px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large, 8px);
}

.time-entry-form__title {
	margin: 0 0 4px;
	font-size: 14px;
	font-weight: 600;
}

.time-entry-form__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
