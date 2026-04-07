<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@spec openspec/changes/time-tracking-mvp/tasks.md#task-4
-->
<template>
	<form class="time-entry-form" @submit.prevent="onSubmit">
		<h4>{{ t('planix', 'Log Time') }}</h4>

		<div class="time-entry-form__field">
			<DurationInput
				:value="duration"
				:label="t('planix', 'Duration')"
				:placeholder="t('planix', 'e.g. 1h 30m')"
				:input-only="true"
				@input="duration = $event" />
		</div>

		<div class="time-entry-form__field">
			<NcTextField
				:value="date"
				:label="t('planix', 'Date')"
				type="date"
				@update:value="date = $event" />
		</div>

		<div class="time-entry-form__field">
			<NcTextField
				:value="description"
				:label="t('planix', 'Description (optional)')"
				:placeholder="t('planix', 'What did you work on?')"
				@update:value="description = $event" />
		</div>

		<div class="time-entry-form__actions">
			<NcButton type="tertiary" @click="$emit('cancel')">
				{{ t('planix', 'Cancel') }}
			</NcButton>
			<NcButton
				type="primary"
				native-type="submit"
				:disabled="!isValid || saving">
				{{ saving ? t('planix', 'Saving…') : t('planix', 'Log Time') }}
			</NcButton>
		</div>

		<NcNoteCard v-if="success" type="success">
			{{ t('planix', 'Time entry logged successfully.') }}
		</NcNoteCard>
		<NcNoteCard v-if="error" type="error">
			{{ error }}
		</NcNoteCard>
	</form>
</template>

<script>
import { NcButton, NcNoteCard, NcTextField } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import DurationInput from './DurationInput.vue'

/**
 * Form component for logging a time entry against a task.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-4
 */
export default {
	name: 'TimeEntryForm',

	components: { NcButton, NcNoteCard, NcTextField, DurationInput },

	props: {
		taskId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			duration: null,
			date: new Date().toISOString().slice(0, 10),
			description: '',
			saving: false,
			success: false,
			error: null,
		}
	},

	computed: {
		isValid() {
			return this.duration != null && this.duration > 0 && !!this.date
		},
	},

	methods: {
		async onSubmit() {
			if (!this.isValid) return

			this.saving = true
			this.error = null
			this.success = false

			try {
				// POST to the server-side controller which substitutes the
				// authenticated session UID for the 'user' field (SEC-W-001).
				const payload = {
					task: this.taskId,
					duration: this.duration,
					date: this.date,
					description: this.description || undefined,
				}

				const response = await axios.post(
					generateUrl('/apps/planix/api/time-entries'),
					payload
				)
				const result = response.data
				if (!result) {
					this.error = this.t('planix', 'Failed to save time entry.')
					return
				}

				this.success = true
				this.$emit('saved', result)

				// Reset form
				this.duration = null
				this.description = ''
				this.date = new Date().toISOString().slice(0, 10)

				setTimeout(() => { this.success = false }, 3000)
			} catch (err) {
				this.error = err.message || this.t('planix', 'An error occurred.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.time-entry-form {
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.time-entry-form h4 {
	margin: 0 0 12px;
	font-size: 16px;
	font-weight: 600;
}

.time-entry-form__field {
	margin-bottom: 12px;
}

.time-entry-form__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 16px;
}
</style>
