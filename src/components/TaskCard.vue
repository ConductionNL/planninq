<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->
<!-- Task card component for displaying a task with due date warning badge. -->
<!-- @spec openspec/changes/task-due-date-warning/tasks.md#task-2 -->

<template>
	<div class="task-card" role="article">
		<!-- Task header with title -->
		<div class="task-card__header">
			<h3 class="task-card__title">
				{{ task.title }}
			</h3>
		</div>

		<!-- Task body with description -->
		<div v-if="task.description" class="task-card__body">
			<p class="task-card__description">
				{{ task.description }}
			</p>
		</div>

		<!-- Task footer with due date badge -->
		<div class="task-card__footer">
			<div class="task-card__badges">
				<!-- Due date warning badge -->
				<NcChip
					v-if="dueDateStatusValue"
					:text="dueDateBadgeText"
					:type="dueDateBadgeType"
					:aria-label="dueDateBadgeAriaLabel"
					:no-close="true"
					class="task-card__due-date-badge" />
			</div>
		</div>
	</div>
</template>

<script>
import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'
import { dueDateStatus } from '../utils/taskHelpers.js'

export default {
	name: 'TaskCard',

	components: {
		NcChip,
	},

	props: {
		task: {
			type: Object,
			required: true,
		},
	},

	computed: {
		/**
		 * Get the due date status for this task.
		 *
		 * @return {string|null} `null`, `"approaching"`, or `"overdue"`
		 * @spec openspec/changes/task-due-date-warning/tasks.md#task-2
		 */
		dueDateStatusValue() {
			return dueDateStatus(this.task)
		},

		/**
		 * Get the display text for the due date badge.
		 *
		 * @return {string}
		 * @spec openspec/changes/task-due-date-warning/tasks.md#task-2
		 */
		dueDateBadgeText() {
			return this.dueDateStatusValue === 'approaching'
				? this.t('planix', 'Due soon')
				: this.dueDateStatusValue === 'overdue'
					? this.t('planix', 'Overdue')
					: ''
		},

		/**
		 * Get the NcChip type (color) for the due date badge.
		 *
		 * @return {string} `"warning"` for approaching, `"error"` for overdue
		 * @spec openspec/changes/task-due-date-warning/tasks.md#task-2
		 */
		dueDateBadgeType() {
			return this.dueDateStatusValue === 'approaching'
				? 'warning'
				: this.dueDateStatusValue === 'overdue'
					? 'error'
					: 'default'
		},

		/**
		 * Get the aria-label for the due date badge.
		 *
		 * @return {string}
		 * @spec openspec/changes/task-due-date-warning/tasks.md#task-2
		 */
		dueDateBadgeAriaLabel() {
			return this.dueDateStatusValue === 'approaching'
				? this.t('planix', 'Task due soon: {dueDate}', { dueDate: this.task.dueDate })
				: this.dueDateStatusValue === 'overdue'
					? this.t('planix', 'Task is overdue: {dueDate}', { dueDate: this.task.dueDate })
					: ''
		},
	},
}
</script>

<style scoped>
.task-card {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background-color: var(--color-main-background);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
	transition: box-shadow 0.2s;
}

.task-card:hover {
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.16);
}

.task-card__header {
	display: flex;
	align-items: flex-start;
	gap: 8px;
}

.task-card__title {
	margin: 0;
	font-size: 14px;
	font-weight: 500;
	line-height: 1.4;
	color: var(--color-main-text);
}

.task-card__body {
	flex: 1;
}

.task-card__description {
	margin: 0;
	font-size: 13px;
	line-height: 1.4;
	color: var(--color-text-maxcontrast);
	word-break: break-word;
}

.task-card__footer {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: 8px;
	margin-top: 4px;
	min-height: 24px;
}

.task-card__badges {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}

.task-card__due-date-badge {
	flex-shrink: 0;
}

.task-card__due-date-badge :deep(.nc-chip) {
	height: auto;
	padding: 2px 8px;
	font-size: 12px;
	font-weight: 500;
}
</style>
