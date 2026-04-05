<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.
-->

<template>
	<div class="time-log">
		<h3 class="time-log__title">
			{{ t('planix', 'Time Log') }}
			<span v-if="totalFormatted" class="time-log__total">
				{{ totalFormatted }}
			</span>
		</h3>

		<!-- Log time form -->
		<form class="time-log__form" @submit.prevent="submitEntry">
			<div class="time-log__form-row">
				<NcTextField
					:value.sync="form.duration"
					:label="t('planix', 'Minutes')"
					type="number"
					input-class="time-log__input"
					:min="1"
					required />
				<NcTextField
					:value.sync="form.date"
					:label="t('planix', 'Date')"
					type="date"
					input-class="time-log__input"
					required />
			</div>
			<NcTextField
				:value.sync="form.description"
				:label="t('planix', 'Description (optional)')"
				input-class="time-log__input" />
			<NcButton
				type="primary"
				native-type="submit"
				:disabled="!isFormValid || loading">
				{{ t('planix', 'Log time') }}
			</NcButton>
		</form>

		<!-- Loading state -->
		<div v-if="loading && entries.length === 0" class="time-log__loading">
			<NcLoadingIcon :size="24" />
		</div>

		<!-- Empty state -->
		<NcEmptyContent
			v-else-if="entries.length === 0"
			class="time-log__empty"
			:name="t('planix', 'No time logged yet')"
			:description="t('planix', 'Use the form above to log your first time entry.')">
			<template #icon>
				<TimerOutline :size="20" />
			</template>
		</NcEmptyContent>

		<!-- Entries list -->
		<ul v-else class="time-log__list">
			<li
				v-for="entry in sortedEntries"
				:key="entry.id"
				class="time-log__entry">
				<!-- Edit mode -->
				<template v-if="editingId === entry.id">
					<form class="time-log__edit-form" @submit.prevent="saveEdit">
						<div class="time-log__form-row">
							<NcTextField
								:value.sync="editForm.duration"
								:label="t('planix', 'Minutes')"
								type="number"
								:min="1"
								required />
							<NcTextField
								:value.sync="editForm.date"
								:label="t('planix', 'Date')"
								type="date"
								required />
						</div>
						<NcTextField
							:value.sync="editForm.description"
							:label="t('planix', 'Description (optional)')" />
						<div class="time-log__edit-actions">
							<NcButton
								type="primary"
								native-type="submit"
								:disabled="!isEditFormValid || loading">
								{{ t('planix', 'Save') }}
							</NcButton>
							<NcButton type="secondary" @click="cancelEdit">
								{{ t('planix', 'Cancel') }}
							</NcButton>
						</div>
					</form>
				</template>

				<!-- View mode -->
				<template v-else>
					<div class="time-log__entry-info">
						<span class="time-log__entry-date">{{ formatDate(entry.date) }}</span>
						<span class="time-log__entry-duration">{{ formatDuration(entry.duration) }}</span>
						<span v-if="entry.description" class="time-log__entry-description">
							{{ entry.description }}
						</span>
					</div>
					<div v-if="isOwner(entry)" class="time-log__entry-actions">
						<NcButton
							type="tertiary"
							:aria-label="t('planix', 'Edit time entry')"
							@click="beginEdit(entry)">
							<template #icon>
								<PencilOutline :size="20" />
							</template>
						</NcButton>
						<NcButton
							type="tertiary"
							:aria-label="t('planix', 'Delete time entry')"
							@click="requestDelete(entry.id)">
							<template #icon>
								<DeleteOutline :size="20" />
							</template>
						</NcButton>
					</div>
				</template>
			</li>
		</ul>

		<!-- Delete confirmation dialog -->
		<NcDialog
			v-if="pendingDeleteId !== null"
			:name="t('planix', 'Delete time entry')"
			:message="t('planix', 'Delete this time entry? This action cannot be undone.')"
			:buttons="confirmDeleteButtons"
			@closing="pendingDeleteId = null" />
	</div>
</template>

<script>
import { NcButton, NcDialog, NcEmptyContent, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import TimerOutline from 'vue-material-design-icons/TimerOutline.vue'
import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { useTimeEntriesStore } from '../store/timeEntries.js'

export default {
	name: 'TimeLog',

	components: {
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		NcTextField,
		DeleteOutline,
		PencilOutline,
		TimerOutline,
	},

	props: {
		taskId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			form: {
				duration: '',
				date: new Date().toISOString().slice(0, 10),
				description: '',
			},
			editingId: null,
			editForm: {
				duration: '',
				date: '',
				description: '',
			},
			pendingDeleteId: null,
		}
	},

	computed: {
		timeEntriesStore() {
			return useTimeEntriesStore()
		},
		entries() {
			return this.timeEntriesStore.entries
		},
		loading() {
			return this.timeEntriesStore.loading
		},
		sortedEntries() {
			return [...this.entries].sort((a, b) => (b.date || '').localeCompare(a.date || ''))
		},
		totalFormatted() {
			const total = this.timeEntriesStore.totalDuration
			if (total === 0) return ''
			return this.formatDuration(total)
		},
		isFormValid() {
			return parseInt(this.form.duration, 10) > 0 && this.form.date !== ''
		},
		isEditFormValid() {
			return parseInt(this.editForm.duration, 10) > 0 && this.editForm.date !== ''
		},
		confirmDeleteButtons() {
			return [
				{
					label: t('planix', 'Cancel'),
					callback: () => { this.pendingDeleteId = null },
				},
				{
					label: t('planix', 'Delete'),
					type: 'error',
					callback: () => this.confirmDelete(),
				},
			]
		},
	},

	watch: {
		taskId: {
			immediate: true,
			handler(id) {
				if (id) {
					this.timeEntriesStore.fetchEntries(id)
				}
			},
		},
	},

	methods: {
		formatDuration(minutes) {
			const h = Math.floor(minutes / 60)
			const m = minutes % 60
			if (h === 0) return `${m}m`
			if (m === 0) return `${h}h`
			return `${h}h ${m}m`
		},

		formatDate(dateStr) {
			if (!dateStr) return ''
			const d = new Date(dateStr + 'T00:00:00')
			return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
		},

		isOwner(entry) {
			const uid = getCurrentUser()?.uid
			return uid && entry.user === uid
		},

		async submitEntry() {
			if (!this.isFormValid) return

			const entry = await this.timeEntriesStore.createEntry({
				taskId: this.taskId,
				duration: parseInt(this.form.duration, 10),
				date: this.form.date,
				description: this.form.description,
			})

			// Reset form only on success — preserve data if request failed.
			if (entry) {
				this.form.duration = ''
				this.form.description = ''
				this.form.date = new Date().toISOString().slice(0, 10)
			}
		},

		beginEdit(entry) {
			this.editingId = entry.id
			this.editForm.duration = String(entry.duration)
			this.editForm.date = entry.date || ''
			this.editForm.description = entry.description || ''
		},

		cancelEdit() {
			this.editingId = null
		},

		async saveEdit() {
			if (!this.isEditFormValid || this.editingId === null) return

			const updated = await this.timeEntriesStore.updateEntry(this.editingId, {
				duration: parseInt(this.editForm.duration, 10),
				date: this.editForm.date,
				description: this.editForm.description,
			})

			if (updated) {
				this.editingId = null
			}
		},

		requestDelete(id) {
			this.pendingDeleteId = id
		},

		async confirmDelete() {
			const id = this.pendingDeleteId
			this.pendingDeleteId = null
			await this.timeEntriesStore.deleteEntry(id)
		},
	},
}
</script>

<style scoped>
.time-log {
	margin-top: 24px;
	border-top: 1px solid var(--color-border);
	padding-top: 16px;
}

.time-log__title {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0 0 16px;
	font-size: 16px;
	font-weight: 600;
}

.time-log__total {
	font-size: 14px;
	font-weight: 400;
	color: var(--color-text-maxcontrast);
}

.time-log__form {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 16px;
}

.time-log__form-row {
	display: flex;
	gap: 8px;
}

.time-log__form-row > * {
	flex: 1;
}

.time-log__loading {
	display: flex;
	justify-content: center;
	padding: 24px;
}

.time-log__empty {
	margin-top: 8px;
}

.time-log__list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.time-log__entry {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border-dark);
}

.time-log__entry:last-child {
	border-bottom: none;
}

.time-log__entry-info {
	display: flex;
	align-items: center;
	gap: 12px;
	flex: 1;
	min-width: 0;
}

.time-log__entry-date {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.time-log__entry-duration {
	font-weight: 600;
	font-size: 14px;
	white-space: nowrap;
}

.time-log__entry-description {
	font-size: 13px;
	color: var(--color-text-lighter);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.time-log__entry-actions {
	display: flex;
	gap: 4px;
}

.time-log__edit-form {
	display: flex;
	flex-direction: column;
	gap: 8px;
	width: 100%;
	padding: 8px 0;
}

.time-log__edit-actions {
	display: flex;
	gap: 8px;
}
</style>
