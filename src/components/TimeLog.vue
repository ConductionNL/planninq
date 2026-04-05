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
				<div class="time-log__entry-info">
					<span class="time-log__entry-date">{{ formatDate(entry.date) }}</span>
					<span class="time-log__entry-duration">{{ formatDuration(entry.duration) }}</span>
					<span v-if="entry.description" class="time-log__entry-description">
						{{ entry.description }}
					</span>
				</div>
				<NcButton
					v-if="isOwner(entry)"
					type="tertiary"
					:aria-label="t('planix', 'Delete time entry')"
					@click="deleteEntry(entry.id)">
					<template #icon>
						<DeleteOutline :size="20" />
					</template>
				</NcButton>
			</li>
		</ul>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import TimerOutline from 'vue-material-design-icons/TimerOutline.vue'
import { getCurrentUser } from '@nextcloud/auth'
import { useTimeEntriesStore } from '../store/timeEntries.js'

export default {
	name: 'TimeLog',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcTextField,
		DeleteOutline,
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

			await this.timeEntriesStore.createEntry({
				task: this.taskId,
				duration: parseInt(this.form.duration, 10),
				date: this.form.date,
				description: this.form.description,
			})

			// Reset form on success.
			this.form.duration = ''
			this.form.description = ''
			this.form.date = new Date().toISOString().slice(0, 10)
		},

		async deleteEntry(id) {
			if (!window.confirm(t('planix', 'Delete this time entry? This action cannot be undone.'))) return
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
</style>
