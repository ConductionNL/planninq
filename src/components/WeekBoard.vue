<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="week-board" data-testid="week-board">
		<div class="week-board__columns">
			<section
				v-for="day in days"
				:key="day.date"
				class="week-board__day"
				:class="{ 'week-board__day--drop-target': dropTargetDate === day.date }"
				:data-date="day.date"
				:aria-label="dayAriaLabel(day.date)"
				data-testid="week-day-column"
				@dragover.prevent="onDragOver(day.date)"
				@dragleave="onDragLeave(day.date)"
				@drop="onDrop(day.date)">
				<header class="week-board__day-header">
					<h3 class="week-board__day-title">
						{{ dayLabel(day.date) }}
					</h3>
					<span class="week-board__day-date">
						{{ dayDateLabel(day.date) }}
					</span>
					<span class="week-board__day-count" aria-hidden="true">
						{{ day.tasks.length }}
					</span>
				</header>

				<div class="week-board__day-body">
					<div
						v-for="task in day.tasks"
						:key="task.id"
						class="week-board__card"
						:class="{ 'week-board__card--mine': isCurrentUserTask(task) }"
						data-testid="week-task-card"
						draggable="true"
						@dragstart="onDragStart(task)"
						@dragend="onDragEnd">
						<span
							v-if="isCurrentUserTask(task)"
							class="week-board__mine-mark"
							data-testid="current-user-mark">
							{{ t('planninq', 'Mine') }}
						</span>
						<TaskCard :task="task" :labels="labelsForTask(task)" />
					</div>

					<p v-if="day.tasks.length === 0" class="week-board__empty">
						{{ t('planninq', 'No tasks') }}
					</p>
				</div>
			</section>
		</div>

		<!-- Tasks without a day are shown here rather than dropped from the
		     week view; they can be dragged onto a day column to schedule them. -->
		<section
			class="week-board__unscheduled"
			:aria-label="t('planninq', 'Unscheduled')"
			data-testid="week-unscheduled">
			<header class="week-board__day-header">
				<h3 class="week-board__day-title">
					{{ t('planninq', 'Unscheduled') }}
				</h3>
				<span class="week-board__day-count" aria-hidden="true">
					{{ unscheduled.length }}
				</span>
			</header>

			<div class="week-board__day-body">
				<div
					v-for="task in unscheduled"
					:key="task.id"
					class="week-board__card"
					:class="{ 'week-board__card--mine': isCurrentUserTask(task) }"
					data-testid="week-task-card"
					draggable="true"
					@dragstart="onDragStart(task)"
					@dragend="onDragEnd">
					<span
						v-if="isCurrentUserTask(task)"
						class="week-board__mine-mark"
						data-testid="current-user-mark">
						{{ t('planninq', 'Mine') }}
					</span>
					<TaskCard :task="task" :labels="labelsForTask(task)" />
				</div>

				<p v-if="unscheduled.length === 0" class="week-board__empty">
					{{ t('planninq', 'No tasks') }}
				</p>
			</div>
		</section>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { NcLoadingIcon } from '@nextcloud/vue'
import TaskCard from './TaskCard.vue'
import { useProjectsStore } from '../store/projects.js'
import { resolveTaskLabels } from '../utils/labelHelpers.js'
import { groupTasksByDay } from '../utils/taskHelpers.js'
import { parseDay } from '../utils/timelineHelpers.js'

/**
 * Week view of a project's planning board.
 *
 * Renders one Monday-to-Sunday week as seven day columns plus an
 * "Unscheduled" area, so a planner sees the whole week at once instead of
 * opening each day. Tasks assigned to the current Nextcloud user carry a text
 * mark ("Mine"), so the highlight is never colour alone (WCAG 1.4.1).
 *
 * A card can be dragged from one day column to another (or out of
 * "Unscheduled" onto a day). The drop writes the target day to the task's
 * `dueDate` through the existing `updateTask` store action — the same
 * RBAC-scoped PATCH the kanban status move uses, no new endpoint. The move is
 * optimistic and reverts on a failed write, and the user is told when it
 * fails.
 *
 * The day grouping itself lives in the pure `groupTasksByDay` helper, so the
 * week arithmetic is unit-tested without mounting this component.
 *
 * @spec openspec/changes/add-week-view-to-planning-board/specs/kanban-board/spec.md
 * @spec openspec/specs/kanban-board.md
 */
export default {
	name: 'WeekBoard',

	components: {
		NcLoadingIcon,
		TaskCard,
	},

	props: {
		/**
		 * The project's tasks, already filtered by the board's active label
		 * filter — the week view is a second grouping of the same collection.
		 */
		tasks: {
			type: Array,
			default: () => [],
		},

		/**
		 * Every app-wide label, so cards can render their chips. Resolved by
		 * the board, not per card: one fetch serves every card.
		 */
		labels: {
			type: Array,
			default: () => [],
		},

		/**
		 * Any date inside the week to render. Defaults to today, which is the
		 * week the board opens on.
		 */
		weekStart: {
			type: Date,
			default: () => new Date(),
		},
	},

	data() {
		return {
			/** @type {object|null} The task currently being dragged. */
			draggingTask: null,
			/** @type {string|null} The day column currently hovered during a drag. */
			dropTargetDate: null,
			/** @type {boolean} Whether a day write is in flight. */
			saving: false,
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — returns the projects Pinia store.
		 */
		projectsStore() {
			return useProjectsStore()
		},

		/**
		 * The current Nextcloud user id, or an empty string when unavailable.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/add-week-view-to-planning-board/specs/kanban-board/spec.md
		 */
		currentUid() {
			return getCurrentUser()?.uid || ''
		},

		/**
		 * The seven day buckets of the rendered week, Monday first.
		 *
		 * @return {Array<{date: string, tasks: Array}>}
		 *
		 * @spec openspec/changes/add-week-view-to-planning-board/specs/kanban-board/spec.md
		 */
		days() {
			return groupTasksByDay(this.tasks, this.weekStart).days
		},

		/**
		 * The tasks of the project that carry no (parseable) due date.
		 *
		 * @return {Array}
		 *
		 * @spec openspec/changes/add-week-view-to-planning-board/specs/kanban-board/spec.md
		 */
		unscheduled() {
			return groupTasksByDay(this.tasks, this.weekStart).unscheduled
		},
	},

	methods: {
		/**
		 * Whether a task is assigned to the current user. Deliberately named
		 * apart from the board's `isHighlighted(task)` deep-link predicate,
		 * which answers a different question.
		 *
		 * @param {object} task The task to test.
		 * @return {boolean}
		 *
		 * @spec openspec/changes/add-week-view-to-planning-board/specs/kanban-board/spec.md
		 */
		isCurrentUserTask(task) {
			return !!this.currentUid && task?.assignedTo === this.currentUid
		},

		/**
		 * The label objects a task carries, resolved from its `labels` UUIDs.
		 *
		 * @param {object} task The task whose labels are resolved.
		 * @return {Array} The label objects, in title order.
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		labelsForTask(task) {
			return resolveTaskLabels(task, this.labels)
		},

		/**
		 * The weekday name of a YYYY-MM-DD day key, in the user's locale.
		 *
		 * @param {string} date YYYY-MM-DD day key.
		 * @return {string} The weekday name.
		 *
		 * @spec exclude Display getter — formats a day key for the column header.
		 */
		dayLabel(date) {
			return this.toLocalDate(date).toLocaleDateString(undefined, { weekday: 'long' })
		},

		/**
		 * The short date of a YYYY-MM-DD day key, in the user's locale.
		 *
		 * @param {string} date YYYY-MM-DD day key.
		 * @return {string} The short date.
		 *
		 * @spec exclude Display getter — formats a day key for the column header.
		 */
		dayDateLabel(date) {
			return this.toLocalDate(date).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
		},

		/**
		 * The accessible name of a day column: weekday plus date.
		 *
		 * @param {string} date YYYY-MM-DD day key.
		 * @return {string}
		 *
		 * @spec exclude Display getter — builds the column's aria-label.
		 */
		dayAriaLabel(date) {
			return `${this.dayLabel(date)} ${this.dayDateLabel(date)}`
		},

		/**
		 * Parse a YYYY-MM-DD day key as a LOCAL date. `new Date('2026-06-17')`
		 * is parsed as UTC midnight, which shifts the weekday west of UTC.
		 *
		 * @param {string} date YYYY-MM-DD day key.
		 * @return {Date}
		 *
		 * @spec exclude Pure date parsing — avoids the UTC shift of Date.parse on a bare day key.
		 */
		toLocalDate(date) {
			const [year, month, day] = date.split('-').map(Number)
			return new Date(year, month - 1, day)
		},

		/**
		 * @param {object} task The task whose drag started.
		 * @spec exclude Drag glue — records the dragged task for the drop handler.
		 */
		onDragStart(task) {
			this.draggingTask = task
		},

		/**
		 * @spec exclude Drag glue — clears the drag state when the drag ends.
		 */
		onDragEnd() {
			this.draggingTask = null
			this.dropTargetDate = null
		},

		/**
		 * @param {string} date The day column being hovered.
		 * @spec exclude Drag glue — marks the hovered day column as the drop target.
		 */
		onDragOver(date) {
			this.dropTargetDate = date
		},

		/**
		 * @param {string} date The day column the pointer left.
		 * @spec exclude Drag glue — clears the drop-target mark for that column.
		 */
		onDragLeave(date) {
			if (this.dropTargetDate === date) {
				this.dropTargetDate = null
			}
		},

		/**
		 * Drop the dragged card on a day column: write the day to the task's
		 * `dueDate` through the existing store action, optimistically, and
		 * revert the card when the write fails.
		 *
		 * Dropping a card on the day it already has sends no update.
		 *
		 * @param {string} date The target day key (YYYY-MM-DD).
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/add-week-view-to-planning-board/specs/kanban-board/spec.md
		 */
		async onDrop(date) {
			const task = this.draggingTask
			this.draggingTask = null
			this.dropTargetDate = null

			if (!task || this.saving) {
				return
			}

			// Dropped on the day it already has: nothing to write.
			if (parseDay(task.dueDate) === parseDay(date)) {
				return
			}

			const previousDueDate = task.dueDate
			// Optimistic: the card moves to the target column immediately.
			task.dueDate = date
			this.saving = true
			try {
				await this.projectsStore.updateTask(task.id, { dueDate: date })
			} catch (err) {
				// Revert the card to the day it came from.
				task.dueDate = previousDueDate
				showError(this.t('planninq', 'The task could not be moved to {day}.', { day: this.dayLabel(date) }))
				console.error('week view day move failed:', err)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.week-board {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.week-board__columns {
	display: grid;
	grid-template-columns: repeat(7, minmax(0, 1fr));
	gap: 8px;
}

.week-board__day,
.week-board__unscheduled {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 8px;
	background: var(--color-background-dark);
	border: 1px solid var(--color-border);
	border-radius: 8px;
}

.week-board__day--drop-target {
	border-color: var(--color-primary-element);
}

.week-board__day-header {
	display: flex;
	align-items: baseline;
	gap: 6px;
}

.week-board__day-title {
	margin: 0;
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text);
}

.week-board__day-date {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.week-board__day-count {
	margin-inline-start: auto;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.week-board__day-body {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-height: 48px;
}

.week-board__card {
	position: relative;
	cursor: grab;
}

/* The current user's own tasks. The border is the colour cue; the "Mine"
   text mark inside the card is the non-colour cue (WCAG 1.4.1). */
.week-board__card--mine {
	border-inline-start: 3px solid var(--color-primary-element);
	border-radius: 8px;
}

.week-board__mine-mark {
	display: inline-block;
	margin: 4px 0 0 4px;
	padding: 0 6px;
	font-size: 11px;
	font-weight: 600;
	color: var(--color-primary-element-text, var(--color-main-text));
	background: var(--color-primary-element-light);
	border-radius: 4px;
}

.week-board__empty {
	margin: 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
