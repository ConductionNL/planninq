<template>
	<div class="task-card">
		<!-- Task title -->
		<h3 class="task-card__title">
			{{ task.title }}
		</h3>

		<!-- Task description (optional) -->
		<p v-if="task.description" class="task-card__description">
			{{ task.description }}
		</p>

		<!-- Task metadata -->
		<div class="task-card__meta">
			<!-- Due date badge -->
			<NcChip
				v-if="dueDateBadgeStatus"
				:text="dueDateBadgeText"
				:variant="dueDateBadgeVariant"
				:no-close="true"
				class="task-card__due-date-badge" />

			<!-- Status -->
			<NcChip
				:text="statusLabel"
				:variant="statusVariant"
				:no-close="true"
				class="task-card__status-badge" />

			<!-- Priority -->
			<NcChip
				v-if="task.priority"
				:text="priorityLabel"
				:variant="priorityVariant"
				:no-close="true"
				class="task-card__priority-badge" />

			<!-- Estimate (time-tracking) -->
			<NcChip
				v-if="estimateLabel"
				:text="estimateLabel"
				:no-close="true"
				class="task-card__estimate-badge" />
		</div>

		<!-- Assignee (optional) -->
		<div v-if="task.assignedTo" class="task-card__assignee">
			{{ t('planix', 'Assigned to: {user}', { user: task.assignedTo }) }}
		</div>
	</div>
</template>

<script>
// @nextcloud/vue@9 removed the `dist/Components/*.js` layout; the package now
// publishes only an `exports` map (root barrel + `./components/<Name>`).
import { NcChip } from '@nextcloud/vue'
import { dueDateStatus } from '../utils/taskHelpers.js'
import { formatDuration } from '../utils/durationParser.js'

/**
 * Kanban board task card.
 *
 * Renders a single task as a draggable card inside a board column: title,
 * optional description, a due-date warning badge (yellow "Due soon" /
 * red "Overdue"), the status + priority chips, and the assignee. The badge is
 * driven by the pure `dueDateStatus` helper (date-only comparison) so colour is
 * never the sole signal — a text label is always present (WCAG 1.4.1).
 *
 * @spec openspec/specs/kanban-board.md
 */
export default {
	name: 'TaskCard',
	components: { NcChip },

	props: {
		task: {
			type: Object,
			required: true,
		},
	},

	computed: {
		/**
		 * @spec openspec/specs/kanban-board.md
		 */
		dueDateBadgeStatus() {
			return dueDateStatus(this.task)
		},

		/**
		 * @spec openspec/specs/time-tracking.md
		 */
		estimateLabel() {
			const minutes = Number(this.task.estimatedDuration) || 0
			return minutes > 0 ? formatDuration(minutes) : ''
		},

		/**
		 * @spec exclude Display getter — maps the due-date status to its translated chip label.
		 */
		dueDateBadgeText() {
			const map = {
				approaching: this.t('planix', 'Due soon'),
				overdue: this.t('planix', 'Overdue'),
			}
			return map[this.dueDateBadgeStatus] || ''
		},

		/**
		 * @spec exclude Display getter — maps the due-date status to a chip colour variant.
		 *
		 * `secondary` is NcChip's own default variant. The pre-migration code
		 * returned 'default', which was never a valid NcChip value in either
		 * major — it only tripped the prop validator and fell through to the
		 * base styling.
		 */
		dueDateBadgeVariant() {
			const map = {
				approaching: 'warning',
				overdue: 'error',
			}
			return map[this.dueDateBadgeStatus] || 'secondary'
		},

		/**
		 * @spec exclude Display getter — translated label for the task status chip.
		 */
		statusLabel() {
			const map = {
				open: this.t('planix', 'Open'),
				in_progress: this.t('planix', 'In Progress'),
				blocked: this.t('planix', 'Blocked'),
				done: this.t('planix', 'Done'),
				cancelled: this.t('planix', 'Cancelled'),
			}
			return map[this.task.status] || this.task.status || this.t('planix', 'Open')
		},

		/**
		 * @spec exclude Display getter — maps the task status to a chip colour variant.
		 */
		statusVariant() {
			const map = {
				open: 'secondary',
				in_progress: 'primary',
				blocked: 'error',
				done: 'success',
				cancelled: 'secondary',
			}
			return map[this.task.status] || 'secondary'
		},

		/**
		 * @spec exclude Display getter — translated label for the priority chip.
		 */
		priorityLabel() {
			const map = {
				low: this.t('planix', 'Low'),
				normal: this.t('planix', 'Normal'),
				high: this.t('planix', 'High'),
				urgent: this.t('planix', 'Urgent'),
			}
			return map[this.task.priority] || this.task.priority || ''
		},

		/**
		 * @spec exclude Display getter — maps the priority to a chip colour variant.
		 */
		priorityVariant() {
			const map = {
				low: 'secondary',
				normal: 'secondary',
				high: 'warning',
				urgent: 'error',
			}
			return map[this.task.priority] || 'secondary'
		},
	},
}
</script>

<style scoped>
.task-card {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px;
	background: var(--color-surface);
	border: 1px solid var(--color-border);
	border-radius: 8px;
}

.task-card__title {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	line-height: 1.4;
	color: var(--color-text);
}

.task-card__description {
	margin: 0;
	font-size: 12px;
	line-height: 1.4;
	color: var(--color-text-maxcontrast);
	word-wrap: break-word;
}

.task-card__meta {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.task-card__due-date-badge {
	flex-shrink: 0;
}

.task-card__status-badge {
	flex-shrink: 0;
}

.task-card__priority-badge {
	flex-shrink: 0;
}

.task-card__assignee {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
