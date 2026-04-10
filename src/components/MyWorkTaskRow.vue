<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 MyWorkTaskRow — a single task row in the My Work view.

 Shows priority dot, task title, project badge, due date chip,
 and inline status dropdown.

 @spec openspec/changes/dashboard-my-work/tasks.md#task-7
-->
<template>
	<div
		class="my-work-task-row"
		:class="{ 'my-work-task-row--highlighted': highlighted }">
		<!-- Priority dot -->
		<span
			class="my-work-task-row__priority"
			:style="{ backgroundColor: priorityColor }"
			:title="t('planix', task.priority || 'normal')"
			aria-hidden="true" />

		<!-- Task title -->
		<span
			class="my-work-task-row__title"
			role="link"
			tabindex="0"
			@click="navigateToTask"
			@keyup.enter="navigateToTask">
			{{ task.title }}
		</span>

		<!-- Project badge -->
		<span
			v-if="projectName"
			class="my-work-task-row__project"
			:style="projectBadgeStyle">
			{{ projectName }}
		</span>

		<!-- Due date chip -->
		<span
			class="my-work-task-row__due"
			:class="dueDateClass">
			{{ dueDateLabel }}
		</span>

		<!-- Status dropdown -->
		<div class="my-work-task-row__status">
			<select
				v-if="!readOnly"
				:value="task.status"
				:disabled="updating"
				class="my-work-task-row__select"
				:aria-label="t('planix', 'Task status')"
				@change="onStatusChange($event.target.value)">
				<option
					v-for="opt in statusOptions"
					:key="opt.value"
					:value="opt.value">
					{{ opt.label }}
				</option>
			</select>
			<TaskStatusBadge v-else :status="task.status" />
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showSuccess } from '@nextcloud/dialogs'
import { useTasksStore } from '../store/tasks.js'
import { useProjectsStore } from '../store/projects.js'
import TaskStatusBadge from './TaskStatusBadge.vue'

const PRIORITY_COLORS = {
	urgent: 'var(--color-error)',
	high: 'var(--color-warning)',
	normal: 'transparent',
	low: 'var(--color-info, var(--color-primary-element))',
}

/**
 * @spec openspec/changes/dashboard-my-work/tasks.md#task-7
 */
export default {
	name: 'MyWorkTaskRow',
	components: { TaskStatusBadge },
	props: {
		task: { type: Object, required: true },
		readOnly: { type: Boolean, default: false },
		highlighted: { type: Boolean, default: false },
	},
	data() {
		return {
			updating: false,
		}
	},
	computed: {
		priorityColor() {
			return PRIORITY_COLORS[this.task.priority] || PRIORITY_COLORS.normal
		},
		projectName() {
			const projectsStore = useProjectsStore()
			const project = projectsStore.projects.find((p) => p.id === this.task.project)
			return project?.title || ''
		},
		projectBadgeStyle() {
			const projectsStore = useProjectsStore()
			const project = projectsStore.projects.find((p) => p.id === this.task.project)
			if (project?.color) {
				return { backgroundColor: project.color, color: '#fff' }
			}
			return {}
		},
		dueDateClass() {
			if (!this.task.dueDate) return ''
			const now = new Date()
			const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
			const due = new Date(this.task.dueDate)
			const dueDay = new Date(due.getFullYear(), due.getMonth(), due.getDate())

			if (dueDay < today) return 'my-work-task-row__due--overdue'
			if (dueDay.getTime() === today.getTime()) return 'my-work-task-row__due--today'
			return ''
		},
		dueDateLabel() {
			if (!this.task.dueDate) return t('planix', 'No due date')

			const now = new Date()
			const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
			const due = new Date(this.task.dueDate)
			const dueDay = new Date(due.getFullYear(), due.getMonth(), due.getDate())
			const diffMs = dueDay.getTime() - today.getTime()
			const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24))

			if (diffDays < 0) {
				return t('planix', 'Overdue: {date}', { date: dueDay.toLocaleDateString() })
			}
			if (diffDays === 0) return t('planix', 'Due today')
			if (diffDays === 1) return t('planix', 'Due tomorrow')
			return t('planix', 'Due {date}', { date: dueDay.toLocaleDateString() })
		},
		statusOptions() {
			return [
				{ value: 'open', label: t('planix', 'Open') },
				{ value: 'in_progress', label: t('planix', 'In Progress') },
				{ value: 'blocked', label: t('planix', 'Blocked') },
				{ value: 'done', label: t('planix', 'Done') },
				{ value: 'cancelled', label: t('planix', 'Cancelled') },
			]
		},
	},
	methods: {
		t,
		navigateToTask() {
			this.$router.push({ path: `/tasks/${this.task.id}` })
		},
		/**
		 * @param {string} newStatus New status value
		 * @spec openspec/changes/dashboard-my-work/tasks.md#task-8
		 */
		async onStatusChange(newStatus) {
			this.updating = true
			try {
				const tasksStore = useTasksStore()
				await tasksStore.updateStatus(this.task.id, newStatus)
				showSuccess(t('planix', 'Task status updated'))
			} catch {
				// Store already shows error toast and does not update state on failure.
			} finally {
				this.updating = false
			}
		},
	},
}
</script>

<style scoped>
.my-work-task-row {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 8px 12px;
	border-radius: var(--border-radius, 4px);
	transition: background-color 0.15s ease;
}

.my-work-task-row:hover {
	background-color: var(--color-background-hover);
}

.my-work-task-row--highlighted {
	background-color: var(--color-primary-element-light, rgba(0, 130, 201, 0.08));
}

.my-work-task-row__priority {
	width: 12px;
	height: 12px;
	border-radius: 50%;
	flex-shrink: 0;
	border: 1px solid var(--color-border);
}

.my-work-task-row__title {
	flex: 1;
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	cursor: pointer;
	color: var(--color-main-text);
}

.my-work-task-row__title:hover {
	text-decoration: underline;
}

.my-work-task-row__title:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
	border-radius: 2px;
}

.my-work-task-row__project {
	font-size: 11px;
	padding: 1px 8px;
	border-radius: 10px;
	background: var(--color-background-dark);
	white-space: nowrap;
	flex-shrink: 0;
}

.my-work-task-row__due {
	font-size: 12px;
	padding: 1px 6px;
	border-radius: 8px;
	white-space: nowrap;
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

.my-work-task-row__due--today {
	background: var(--color-warning);
	color: var(--color-warning-text, #fff);
}

.my-work-task-row__due--overdue {
	background: var(--color-error);
	color: var(--color-error-text, #fff);
}

.my-work-task-row__status {
	flex-shrink: 0;
}

.my-work-task-row__select {
	font-size: 12px;
	padding: 2px 6px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius, 4px);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
}

.my-work-task-row__select:disabled {
	opacity: 0.5;
	cursor: wait;
}
</style>
