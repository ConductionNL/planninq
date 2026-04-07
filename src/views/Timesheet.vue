<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@see openspec/changes/time-tracking-mvp/tasks.md#task-6
-->
<template>
	<div class="timesheet">
		<div class="timesheet__header">
			<h2>{{ t('planix', 'Timesheet') }}</h2>
			<div class="timesheet__range-selector">
				<NcSelect
					:value="selectedRange"
					:options="rangeOptions"
					:clearable="false"
					label="label"
					@input="onRangeChange" />
			</div>
		</div>

		<!-- Weekly total -->
		<div class="timesheet__total">
			{{ t('planix', 'Total: {total}', { total: formattedTotal }) }}
		</div>

		<!-- Loading -->
		<div v-if="loading" class="timesheet__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Grouped entries -->
		<template v-else-if="groupedEntries.length > 0">
			<div
				v-for="group in groupedEntries"
				:key="group.date"
				class="timesheet__day">
				<div class="timesheet__day-header">
					<strong>{{ formatDate(group.date) }}</strong>
					<span class="timesheet__day-total">{{ formatDuration(group.total) }}</span>
				</div>
				<div class="timesheet__entries">
					<div
						v-for="entry in group.entries"
						:key="entry.id"
						class="timesheet__entry"
						@click="goToTask(entry)">
						<div class="timesheet__entry-duration">
							{{ formatDuration(entry.duration) }}
						</div>
						<div class="timesheet__entry-info">
							<span class="timesheet__entry-task">
								{{ getTaskTitle(entry.task) }}
							</span>
							<span v-if="entry.description" class="timesheet__entry-desc">
								{{ entry.description }}
							</span>
						</div>
					</div>
				</div>
			</div>
		</template>

		<!-- Empty state -->
		<NcEmptyContent
			v-else
			:name="t('planix', 'No time entries')"
			:description="t('planix', 'You have not logged any time in this period.')">
			<template #icon>
				<TimerOutline :size="20" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
/**
 * Personal timesheet view showing time entries grouped by day.
 *
 * @see openspec/changes/time-tracking-mvp/tasks.md#task-6
 */
import { NcEmptyContent, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import { getCurrentUser } from '@nextcloud/auth'
import { useObjectStore } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import TimerOutline from 'vue-material-design-icons/TimerOutline.vue'
import { formatDuration } from '../components/DurationInput.vue'

const REGISTER = 'planix'
const TIME_ENTRY_SCHEMA = 'timeEntry'
const TASK_SCHEMA = 'task'

/**
 * Get Monday of the week containing the given date.
 *
 * @param {Date} d Reference date
 * @return {Date}
 */
function getMonday(d) {
	const date = new Date(d)
	const day = date.getDay()
	const diff = date.getDate() - day + (day === 0 ? -6 : 1)
	date.setDate(diff)
	date.setHours(0, 0, 0, 0)
	return date
}

/**
 * Format a date range as a label.
 *
 * @param {Date} start Start date
 * @param {Date} end End date
 * @return {{ start: string, end: string }}
 */
function dateRange(start, end) {
	return {
		start: start.toISOString().slice(0, 10),
		end: end.toISOString().slice(0, 10),
	}
}

export default {
	name: 'Timesheet',

	components: { NcEmptyContent, NcLoadingIcon, NcSelect, TimerOutline },

	data() {
		const now = new Date()
		const monday = getMonday(now)
		const sunday = new Date(monday)
		sunday.setDate(sunday.getDate() + 6)

		return {
			entries: [],
			tasks: {},
			loading: true,
			selectedRange: { id: 'this-week', label: t('planix', 'This week') },
			rangeStart: monday.toISOString().slice(0, 10),
			rangeEnd: sunday.toISOString().slice(0, 10),
		}
	},

	computed: {
		rangeOptions() {
			return [
				{ id: 'this-week', label: t('planix', 'This week') },
				{ id: 'last-week', label: t('planix', 'Last week') },
				{ id: 'this-month', label: t('planix', 'This month') },
			]
		},
		/**
		 * Entries filtered to the selected date range.
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-6
		 * @return {Array}
		 */
		filteredEntries() {
			return this.entries.filter((e) => {
				return e.date >= this.rangeStart && e.date <= this.rangeEnd
			})
		},
		/**
		 * Group entries by date with daily subtotals.
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-6
		 * @return {Array<{date: string, total: number, entries: Array}>}
		 */
		groupedEntries() {
			const groups = {}
			for (const entry of this.filteredEntries) {
				const key = entry.date || 'unknown'
				if (!groups[key]) {
					groups[key] = { date: key, total: 0, entries: [] }
				}
				groups[key].entries.push(entry)
				groups[key].total += entry.duration || 0
			}
			return Object.values(groups).sort((a, b) => b.date.localeCompare(a.date))
		},
		/**
		 * Total logged time in range.
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-6
		 * @return {string}
		 */
		formattedTotal() {
			const total = this.filteredEntries.reduce((sum, e) => sum + (e.duration || 0), 0)
			return formatDuration(total)
		},
	},

	async mounted() {
		await this.loadEntries()
	},

	methods: {
		t,
		formatDuration,

		_objectStore() {
			const store = useObjectStore()
			if (!store.objectTypeRegistry?.[TIME_ENTRY_SCHEMA]) {
				store.registerObjectType(TIME_ENTRY_SCHEMA, TIME_ENTRY_SCHEMA, REGISTER)
			}
			if (!store.objectTypeRegistry?.[TASK_SCHEMA]) {
				store.registerObjectType(TASK_SCHEMA, TASK_SCHEMA, REGISTER)
			}
			return store
		},

		/**
		 * Load all time entries for the current user.
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-6
		 */
		async loadEntries() {
			this.loading = true
			try {
				const objectStore = this._objectStore()
				const uid = getCurrentUser()?.uid || ''

				const entries = await objectStore.fetchCollection(TIME_ENTRY_SCHEMA, {
					user: uid,
				})
				this.entries = entries

				// Fetch task titles for display.
				const taskIds = [...new Set(entries.map((e) => e.task).filter(Boolean))]
				for (const id of taskIds) {
					if (!this.tasks[id]) {
						try {
							const task = await objectStore.fetchObject(TASK_SCHEMA, id)
							if (task) {
								this.$set(this.tasks, id, task)
							}
						} catch {
							// Task may have been deleted.
						}
					}
				}
			} catch (err) {
				console.error('Timesheet loadEntries error:', err)
			} finally {
				this.loading = false
			}
		},

		onRangeChange(option) {
			this.selectedRange = option
			const now = new Date()

			if (option.id === 'this-week') {
				const monday = getMonday(now)
				const sunday = new Date(monday)
				sunday.setDate(sunday.getDate() + 6)
				const range = dateRange(monday, sunday)
				this.rangeStart = range.start
				this.rangeEnd = range.end
			} else if (option.id === 'last-week') {
				const lastMonday = getMonday(now)
				lastMonday.setDate(lastMonday.getDate() - 7)
				const lastSunday = new Date(lastMonday)
				lastSunday.setDate(lastSunday.getDate() + 6)
				const range = dateRange(lastMonday, lastSunday)
				this.rangeStart = range.start
				this.rangeEnd = range.end
			} else if (option.id === 'this-month') {
				const firstDay = new Date(now.getFullYear(), now.getMonth(), 1)
				const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
				const range = dateRange(firstDay, lastDay)
				this.rangeStart = range.start
				this.rangeEnd = range.end
			}
		},

		getTaskTitle(taskId) {
			return this.tasks[taskId]?.title || t('planix', 'Unknown task')
		},

		formatDate(dateStr) {
			try {
				const d = new Date(dateStr + 'T00:00:00')
				return d.toLocaleDateString(undefined, {
					weekday: 'long',
					year: 'numeric',
					month: 'long',
					day: 'numeric',
				})
			} catch {
				return dateStr
			}
		},

		goToTask(entry) {
			if (entry.task) {
				this.$router.push({ name: 'TaskDetail', params: { taskId: entry.task } })
			}
		},
	},
}
</script>

<style scoped>
.timesheet {
	padding: 16px 24px 32px;
	max-width: 900px;
}

.timesheet__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 8px;
}

.timesheet__header h2 {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
}

.timesheet__range-selector {
	min-width: 180px;
}

.timesheet__total {
	font-size: 16px;
	font-weight: 600;
	margin-bottom: 20px;
	color: var(--color-primary-element);
}

.timesheet__loading {
	display: flex;
	justify-content: center;
	padding: 60px;
}

.timesheet__day {
	margin-bottom: 20px;
}

.timesheet__day-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 8px;
}

.timesheet__day-total {
	font-size: 14px;
	font-weight: 600;
	color: var(--color-primary-element);
}

.timesheet__entries {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.timesheet__entry {
	display: flex;
	align-items: center;
	gap: 16px;
	padding: 10px 12px;
	border-radius: var(--border-radius, 4px);
	background: var(--color-background-dark);
	cursor: pointer;
	transition: background 0.15s;
}

.timesheet__entry:hover {
	background: var(--color-background-hover);
}

.timesheet__entry-duration {
	font-weight: 600;
	font-variant-numeric: tabular-nums;
	min-width: 60px;
}

.timesheet__entry-info {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.timesheet__entry-task {
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.timesheet__entry-desc {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
