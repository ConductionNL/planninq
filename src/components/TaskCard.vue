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
				:type="dueDateBadgeType"
				:no-close="true"
				class="task-card__due-date-badge" />

			<!-- Status -->
			<NcChip
				:text="statusLabel"
				:type="statusType"
				:no-close="true"
				class="task-card__status-badge" />

			<!-- Priority -->
			<NcChip
				v-if="task.priority"
				:text="priorityLabel"
				:type="priorityType"
				:no-close="true"
				class="task-card__priority-badge" />
		</div>

		<!-- Assignee (optional) -->
		<div v-if="task.assignedTo" class="task-card__assignee">
			{{ t('planix', 'Assigned to: {user}', { user: task.assignedTo }) }}
		</div>
	</div>
</template>

<script>
import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'
import { dueDateStatus } from '../utils/taskHelpers.js'

// openspec/changes/task-due-date-warning/tasks.md#task-2
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
		dueDateBadgeStatus() {
			return dueDateStatus(this.task)
		},

		dueDateBadgeText() {
			const map = {
				approaching: this.t('planix', 'Due soon'),
				overdue: this.t('planix', 'Overdue'),
			}
			return map[this.dueDateBadgeStatus] || ''
		},

		dueDateBadgeType() {
			const map = {
				approaching: 'warning',
				overdue: 'error',
			}
			return map[this.dueDateBadgeStatus] || 'default'
		},

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

		statusType() {
			const map = {
				open: 'default',
				in_progress: 'primary',
				blocked: 'error',
				done: 'success',
				cancelled: 'default',
			}
			return map[this.task.status] || 'default'
		},

		priorityLabel() {
			const map = {
				low: this.t('planix', 'Low'),
				normal: this.t('planix', 'Normal'),
				high: this.t('planix', 'High'),
				urgent: this.t('planix', 'Urgent'),
			}
			return map[this.task.priority] || this.task.priority || ''
		},

		priorityType() {
			const map = {
				low: 'default',
				normal: 'default',
				high: 'warning',
				urgent: 'error',
			}
			return map[this.task.priority] || 'default'
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
