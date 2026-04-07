<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@spec openspec/changes/time-tracking-mvp/tasks.md#task-6
-->
<template>
	<div class="timesheet">
		<div class="timesheet__header">
			<h2>{{ t('planix', 'Timesheet') }}</h2>
			<div class="timesheet__total">
				{{ t('planix', 'Total: {duration}', { duration: formatDuration(totalMinutes) }) }}
			</div>
		</div>

		<!-- Date range selector -->
		<div class="timesheet__filters">
			<NcButton
				v-for="preset in presets"
				:key="preset.key"
				:type="activePreset === preset.key ? 'primary' : 'secondary'"
				@click="applyPreset(preset.key)">
				{{ preset.label }}
			</NcButton>
			<div class="timesheet__custom-range">
				<NcTextField
					:value="startDate"
					:label="t('planix', 'From')"
					type="date"
					@update:value="onStartDateChange" />
				<NcTextField
					:value="endDate"
					:label="t('planix', 'To')"
					type="date"
					@update:value="onEndDateChange" />
			</div>
		</div>

		<!-- Loading -->
		<div v-if="loading" class="timesheet__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Entries grouped by date -->
		<template v-else-if="groupedEntries.length > 0">
			<div
				v-for="group in groupedEntries"
				:key="group.date"
				class="timesheet__day">
				<div class="timesheet__day-header">
					<span class="timesheet__day-date">{{ group.date }}</span>
					<span class="timesheet__day-total">{{ formatDuration(group.total) }}</span>
				</div>
				<div
					v-for="entry in group.entries"
					:key="entry.id"
					class="timesheet__entry">
					<span class="timesheet__entry-duration">{{ formatDuration(entry.duration) }}</span>
					<NcButton
						type="tertiary-no-background"
						class="timesheet__entry-task"
						@click="goToTask(entry.task)">
						{{ getTaskTitle(entry.task) }}
					</NcButton>
					<span v-if="entry.description" class="timesheet__entry-desc">{{ entry.description }}</span>
				</div>
			</div>
		</template>

		<NcEmptyContent
			v-else
			:name="t('planix', 'No time entries')"
			:description="t('planix', 'You have not logged any time in this period.')">
			<template #icon>
				<TimerOffOutline :size="20" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import { getCurrentUser } from '@nextcloud/auth'
import { useObjectStore } from '@conduction/nextcloud-vue'
import TimerOffOutline from 'vue-material-design-icons/TimerOffOutline.vue'
import { formatDuration } from '../utils/duration.js'

/**
 * Personal timesheet view showing time entries grouped by day.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-6
 */
export default {
	name: 'TimesheetView',

	components: { NcButton, NcEmptyContent, NcLoadingIcon, NcTextField, TimerOffOutline },

	data() {
		const today = new Date()
		const monday = new Date(today)
		monday.setDate(today.getDate() - ((today.getDay() + 6) % 7))

		return {
			entries: [],
			tasks: {},
			loading: false,
			startDate: monday.toISOString().slice(0, 10),
			endDate: today.toISOString().slice(0, 10),
			activePreset: 'this-week',
		}
	},

	computed: {
		presets() {
			return [
				{ key: 'this-week', label: this.t('planix', 'This week') },
				{ key: 'last-week', label: this.t('planix', 'Last week') },
				{ key: 'custom', label: this.t('planix', 'Custom') },
			]
		},

		filteredEntries() {
			return this.entries.filter((e) => {
				if (!e.date) return false
				return e.date >= this.startDate && e.date <= this.endDate
			})
		},

		groupedEntries() {
			const map = {}
			for (const entry of this.filteredEntries) {
				if (!map[entry.date]) {
					map[entry.date] = { date: entry.date, entries: [], total: 0 }
				}
				map[entry.date].entries.push(entry)
				map[entry.date].total += entry.duration || 0
			}
			return Object.values(map).sort((a, b) => b.date.localeCompare(a.date))
		},

		totalMinutes() {
			return this.filteredEntries.reduce((sum, e) => sum + (e.duration || 0), 0)
		},
	},

	async mounted() {
		await this.loadEntries()
	},

	methods: {
		formatDuration,

		async loadEntries() {
			this.loading = true
			try {
				const objectStore = useObjectStore()
				const uid = getCurrentUser()?.uid || ''
				this.entries = await objectStore.fetchCollection('timeEntry', { user: uid })

				// Fetch referenced tasks for display
				const taskIds = [...new Set(this.entries.map((e) => e.task).filter(Boolean))]
				for (const taskId of taskIds) {
					if (!this.tasks[taskId]) {
						try {
							const task = await objectStore.fetchObject('task', taskId)
							if (task) {
								this.$set(this.tasks, taskId, task)
							}
						} catch {
							// task may have been deleted
						}
					}
				}
			} catch (err) {
				console.error('Failed to load time entries:', err)
			} finally {
				this.loading = false
			}
		},

		getTaskTitle(taskId) {
			return this.tasks[taskId]?.title || taskId
		},

		goToTask(taskId) {
			this.$router.push({ name: 'TaskDetail', params: { taskId } })
		},

		applyPreset(key) {
			this.activePreset = key
			const today = new Date()

			if (key === 'this-week') {
				const monday = new Date(today)
				monday.setDate(today.getDate() - ((today.getDay() + 6) % 7))
				this.startDate = monday.toISOString().slice(0, 10)
				this.endDate = today.toISOString().slice(0, 10)
			} else if (key === 'last-week') {
				const lastMonday = new Date(today)
				lastMonday.setDate(today.getDate() - ((today.getDay() + 6) % 7) - 7)
				const lastSunday = new Date(lastMonday)
				lastSunday.setDate(lastMonday.getDate() + 6)
				this.startDate = lastMonday.toISOString().slice(0, 10)
				this.endDate = lastSunday.toISOString().slice(0, 10)
			}
			// 'custom' — keep current dates
		},

		onStartDateChange(val) {
			this.startDate = val
			this.activePreset = 'custom'
		},

		onEndDateChange(val) {
			this.endDate = val
			this.activePreset = 'custom'
		},
	},
}
</script>

<style scoped>
.timesheet {
	padding: 8px 4px 24px;
	max-width: 900px;
}

.timesheet__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 16px;
}

.timesheet__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.timesheet__total {
	font-size: 18px;
	font-weight: 600;
	color: var(--color-primary);
}

.timesheet__filters {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 24px;
	flex-wrap: wrap;
}

.timesheet__custom-range {
	display: flex;
	gap: 8px;
	margin-left: auto;
}

.timesheet__loading {
	display: flex;
	justify-content: center;
	padding: 60px;
}

.timesheet__day {
	margin-bottom: 16px;
}

.timesheet__day-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 12px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
	margin-bottom: 4px;
	font-weight: 600;
}

.timesheet__day-date {
	font-size: 14px;
}

.timesheet__day-total {
	font-size: 14px;
	color: var(--color-primary);
}

.timesheet__entry {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 8px 12px 8px 24px;
	font-size: 14px;
}

.timesheet__entry-duration {
	font-weight: 600;
	min-width: 60px;
}

.timesheet__entry-task {
	color: var(--color-primary);
}

.timesheet__entry-desc {
	flex: 1;
	color: var(--color-text-maxcontrast);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
