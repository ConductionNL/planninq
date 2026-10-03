<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<section class="task-calendar" data-testid="task-calendar">
		<div class="task-calendar__toolbar">
			<div class="task-calendar__nav">
				<NcButton variant="tertiary" data-testid="calendar-previous" @click="move(-1)">
					{{ t('planninq', 'Previous') }}
				</NcButton>
				<NcButton variant="tertiary" data-testid="calendar-today" @click="goToday">
					{{ t('planninq', 'Today') }}
				</NcButton>
				<NcButton variant="tertiary" data-testid="calendar-next" @click="move(1)">
					{{ t('planninq', 'Next') }}
				</NcButton>
			</div>
			<div class="task-calendar__modes" role="group" :aria-label="t('planninq', 'Calendar view')">
				<NcButton v-for="option in modes"
					:key="option.id"
					:variant="mode === option.id ? 'primary' : 'tertiary'"
					:aria-pressed="mode === option.id"
					:data-testid="`calendar-mode-${option.id}`"
					@click="mode = option.id">
					{{ option.label }}
				</NcButton>
			</div>
		</div>

		<table v-if="mode !== 'list'" class="task-calendar__grid" data-testid="calendar-grid">
			<caption class="task-calendar__caption" data-testid="calendar-caption" aria-live="polite">
				{{ caption }}
			</caption>
			<thead>
				<tr>
					<th v-for="name in weekdayNames" :key="name" scope="col">
						{{ name }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(week, index) in visibleWeeks" :key="index">
					<td v-for="day in week"
						:key="day.date"
						:class="{ 'is-outside': !day.inMonth, 'is-today': day.date === today }"
						:data-date="day.date">
						<span class="task-calendar__day">{{ dayNumber(day.date) }}</span>
						<ul v-if="byDay.get(day.date)" class="task-calendar__tasks">
							<li v-for="item in byDay.get(day.date)" :key="item.task.id">
								<RouterLink :to="taskRoute(item.task)" class="task-calendar__task" data-testid="calendar-task">
									<span v-if="item.starts" class="task-calendar__starts">{{ t('planninq', 'Starts:') }}</span>
									{{ item.task.title }}
									<span v-if="showProject && projectTitle(item.task)" class="task-calendar__project">{{ projectTitle(item.task) }}</span>
								</RouterLink>
							</li>
						</ul>
					</td>
				</tr>
			</tbody>
		</table>

		<div v-else class="task-calendar__list" data-testid="calendar-list">
			<h3 class="task-calendar__caption">
				{{ caption }}
			</h3>
			<p v-if="listGroups.length === 0" class="task-calendar__empty">
				{{ t('planninq', 'No tasks with a date in this month.') }}
			</p>
			<section v-for="group in listGroups" :key="group.date" class="task-calendar__list-day">
				<h4>{{ longDate(group.date) }}</h4>
				<ul>
					<li v-for="item in group.items" :key="item.task.id">
						<RouterLink :to="taskRoute(item.task)" data-testid="calendar-list-task">
							<span v-if="item.starts">{{ t('planninq', 'Starts:') }}</span>
							{{ item.task.title }}
						</RouterLink>
						<span v-if="showProject && projectTitle(item.task)" class="task-calendar__project">{{ projectTitle(item.task) }}</span>
					</li>
				</ul>
			</section>
		</div>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
/**
 * TaskCalendar: tasks on their due dates in a month grid, a week row or a
 * dated list. The grid is a table with a caption naming the period; every
 * task is a link to its task page, so Tab reaches it and Enter opens it.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
import { NcButton } from '@nextcloud/vue'
import { dateKey, listByDate, monthWeeks, shiftMonth, tasksByDay, weekDays } from '../utils/calendarHelpers.js'

export default {
	name: 'TaskCalendar',

	components: {
		NcButton,
	},

	props: {
		/** The tasks to place. */
		tasks: {
			type: Array,
			default: () => [],
		},

		/** Project id to title, to name the project on each task (My calendar). */
		projects: {
			type: Object,
			default: () => ({}),
		},

		/** Whether each task names its project. */
		showProject: {
			type: Boolean,
			default: false,
		},

		/** The day the calendar opens on, as YYYY-MM-DD; today when empty. */
		initialDate: {
			type: String,
			default: '',
		},
	},

	data() {
		const start = this.initialDate || dateKey(new Date())
		return {
			mode: 'month',
			focus: start,
			today: dateKey(new Date()),
		}
	},

	computed: {
		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @return {Array<object>} The view choices
		 */
		modes() {
			return [
				{ id: 'month', label: t('planninq', 'Month') },
				{ id: 'week', label: t('planninq', 'Week') },
				{ id: 'list', label: t('planninq', 'List') },
			]
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @return {Map} Tasks per day
		 */
		byDay() {
			return tasksByDay(this.tasks)
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @return {{year: number, month: number}} The focused month
		 */
		month() {
			const [y, m] = this.focus.split('-').map(Number)
			return { year: y, month: m - 1 }
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @return {Array<Array<object>>} The weeks shown
		 */
		visibleWeeks() {
			if (this.mode === 'week') {
				return [weekDays(this.focus).map((date) => ({ date, inMonth: true }))]
			}
			return monthWeeks(this.month.year, this.month.month)
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @return {Array<string>} Monday to Sunday, in the user's language
		 */
		weekdayNames() {
			return weekDays('2026-10-12').map((key) => this.fromKey(key).toLocaleDateString(undefined, { weekday: 'short' }))
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.4
		 * @return {string} The period the grid or list shows
		 */
		caption() {
			if (this.mode === 'week') {
				const days = weekDays(this.focus)
				return t('planninq', 'Week of {start} to {end}', { start: this.longDate(days[0]), end: this.longDate(days[6]) })
			}
			return new Date(this.month.year, this.month.month, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @return {Array<object>} The month's tasks grouped by date
		 */
		listGroups() {
			const first = dateKey(new Date(this.month.year, this.month.month, 1))
			const last = dateKey(new Date(this.month.year, this.month.month + 1, 0))
			return listByDate(this.tasks, first, last)
		},
	},

	methods: {
		t,

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @param {string} key A day key
		 * @return {Date}
		 */
		fromKey(key) {
			const [y, m, d] = key.split('-').map(Number)
			return new Date(y, m - 1, d)
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @param {string} key A day key
		 * @return {number} The day of the month
		 */
		dayNumber(key) {
			return Number(key.slice(8, 10))
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @param {string} key A day key
		 * @return {string} The date in words
		 */
		longDate(key) {
			return this.fromKey(key).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' })
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.4
		 * @param {number} step One period forward, or back when negative
		 */
		move(step) {
			if (this.mode === 'week') {
				const day = this.fromKey(this.focus)
				this.focus = dateKey(new Date(day.getFullYear(), day.getMonth(), day.getDate() + 7 * step))
				return
			}
			const next = shiftMonth(this.month.year, this.month.month, step)
			this.focus = dateKey(new Date(next.year, next.month, 1))
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.4
		 */
		goToday() {
			this.focus = dateKey(new Date())
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
		 * @param {object} task The task
		 * @return {object} The route to its task page
		 */
		taskRoute(task) {
			const project = typeof task.project === 'object' ? task.project?.id : task.project
			return { name: 'TaskDetail', params: { id: project, taskId: task.id } }
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.3
		 * @param {object} task The task
		 * @return {string} Its project's title, when known
		 */
		projectTitle(task) {
			const project = typeof task.project === 'object' ? task.project?.id : task.project
			return this.projects[project] || ''
		},
	},
}
</script>

<style scoped>
.task-calendar__toolbar {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}

.task-calendar__nav,
.task-calendar__modes {
	display: flex;
	gap: var(--default-grid-baseline);
}

.task-calendar__grid {
	width: 100%;
	table-layout: fixed;
	border-collapse: collapse;
}

.task-calendar__caption {
	text-align: start;
	font-weight: bold;
	font-size: 1.2em;
	padding-bottom: calc(var(--default-grid-baseline) * 2);
}

.task-calendar__grid th {
	text-align: start;
	color: var(--color-text-maxcontrast);
	font-weight: normal;
	padding: var(--default-grid-baseline);
}

.task-calendar__grid td {
	vertical-align: top;
	height: 96px;
	padding: var(--default-grid-baseline);
	border: 1px solid var(--color-border);
}

.task-calendar__grid td.is-outside {
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.task-calendar__grid td.is-today .task-calendar__day {
	font-weight: bold;
	text-decoration: underline;
}

.task-calendar__tasks {
	list-style: none;
	margin: var(--default-grid-baseline) 0 0;
	padding: 0;
}

.task-calendar__task {
	display: block;
	overflow-wrap: anywhere;
	padding: 2px var(--default-grid-baseline);
	margin-bottom: 2px;
	border-radius: var(--border-radius);
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.task-calendar__task:focus-visible {
	outline: 2px solid var(--color-primary-element);
}

.task-calendar__starts,
.task-calendar__project {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.task-calendar__list-day h4 {
	margin-top: calc(var(--default-grid-baseline) * 3);
}

.task-calendar__empty {
	color: var(--color-text-maxcontrast);
}
</style>
