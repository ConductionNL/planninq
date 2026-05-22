<!-- SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->

<template>
	<div
		class="task-card"
		tabindex="0"
		role="article"
		:aria-label="task.title">
		<!-- Priority dot -->
		<span
			class="task-card__priority"
			:class="`task-card__priority--${priorityLevel}`"
			:aria-label="t('planix', 'Priority: {priority}', { priority: priorityLabel })" />

		<!-- Title -->
		<span class="task-card__title">{{ task.title }}</span>

		<!-- Due date -->
		<span
			v-if="task.dueDate"
			class="task-card__due-date"
			:class="{ 'task-card__due-date--overdue': isOverdue }">
			{{ formattedDueDate }}
		</span>

		<!-- Assignee -->
		<span
			v-if="task.assignee"
			class="task-card__assignee"
			:aria-label="t('planix', 'Assigned to {assignee}', { assignee: assigneeLabel })">
			{{ assigneeLabel }}
		</span>
	</div>
</template>

<script>
/**
 * TaskCard — read-only card displaying core task fields in a kanban column.
 *
 * @spec openspec/changes/task-quick-add/tasks.md#task-7
 */
export default {
	name: 'TaskCard',

	props: {
		task: {
			type: Object,
			required: true,
		},
	},

	computed: {
		priorityLevel() {
			const map = { urgent: 'urgent', high: 'high', normal: 'normal', low: 'low' }
			return map[this.task.priority] || 'normal'
		},

		priorityLabel() {
			const labels = {
				urgent: t('planix', 'Urgent'),
				high: t('planix', 'High'),
				normal: t('planix', 'Normal'),
				low: t('planix', 'Low'),
			}
			return labels[this.task.priority] || t('planix', 'Normal')
		},

		isOverdue() {
			if (!this.task.dueDate) return false
			return new Date(this.task.dueDate) < new Date()
		},

		formattedDueDate() {
			if (!this.task.dueDate) return ''
			return new Date(this.task.dueDate).toLocaleDateString()
		},

		assigneeLabel() {
			if (!this.task.assignee) return ''
			const uid = String(this.task.assignee)
			return uid.length > 8 ? uid.slice(-8) : uid
		},
	},
}
</script>

<style scoped>
.task-card {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 8px 10px;
	border-radius: var(--border-radius);
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	cursor: default;
}

.task-card:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.task-card__priority {
	display: inline-block;
	width: 8px;
	height: 8px;
	border-radius: 50%;
	flex-shrink: 0;
}

.task-card__priority--urgent { background: var(--color-error); }
.task-card__priority--high   { background: var(--color-warning); }
.task-card__priority--normal { background: var(--color-primary-element); }
.task-card__priority--low    { background: var(--color-text-maxcontrast); }

.task-card__title {
	font-size: 13px;
	font-weight: 500;
	color: var(--color-main-text);
	word-break: break-word;
}

.task-card__due-date {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

.task-card__due-date--overdue {
	color: var(--color-error);
}

.task-card__assignee {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	font-family: var(--font-face-monospace, monospace);
}
</style>
