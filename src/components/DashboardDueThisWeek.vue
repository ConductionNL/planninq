<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 DashboardDueThisWeek — lists tasks due within the next 7 days.

 @spec openspec/changes/dashboard-my-work/tasks.md#task-5
-->
<template>
	<div class="dashboard-due-this-week">
		<h3 class="dashboard-due-this-week__header">
			{{ t('planix', 'Due This Week') }}
		</h3>

		<template v-if="sortedTasks.length > 0">
			<div
				v-for="task in sortedTasks"
				:key="task.id"
				class="dashboard-due-this-week__item"
				role="link"
				tabindex="0"
				@click="navigateToTask(task.id)"
				@keyup.enter="navigateToTask(task.id)">
				<div class="dashboard-due-this-week__title">
					{{ task.title }}
				</div>
				<div class="dashboard-due-this-week__meta">
					<span
						v-if="task.projectTitle"
						class="dashboard-due-this-week__project">
						{{ task.projectTitle }}
					</span>
					<span
						class="dashboard-due-this-week__chip"
						:class="dueDateClass(task.dueDate)">
						{{ dueDateLabel(task.dueDate) }}
					</span>
				</div>
			</div>
		</template>

		<NcEmptyContent
			v-else
			:name="t('planix', 'No tasks due this week')">
			<template #icon>
				<CalendarCheck :size="64" aria-hidden="true" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcEmptyContent } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import CalendarCheck from 'vue-material-design-icons/CalendarCheck.vue'

/**
 * @spec openspec/changes/dashboard-my-work/tasks.md#task-5
 */
export default {
	name: 'DashboardDueThisWeek',
	components: { NcEmptyContent, CalendarCheck },
	props: {
		tasks: { type: Array, default: () => [] },
	},
	computed: {
		sortedTasks() {
			return [...this.tasks].sort((a, b) => {
				const da = a.dueDate ? new Date(a.dueDate) : new Date('9999-12-31')
				const db = b.dueDate ? new Date(b.dueDate) : new Date('9999-12-31')
				return da - db
			})
		},
	},
	methods: {
		t,
		navigateToTask(id) {
			this.$router.push({ name: 'ProjectBoard', params: { id } })
		},
		dueDateClass(dueDate) {
			if (!dueDate) return ''
			const now = new Date()
			const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
			const due = new Date(dueDate)
			const dueDay = new Date(due.getFullYear(), due.getMonth(), due.getDate())

			if (dueDay < today) return 'dashboard-due-this-week__chip--overdue'
			if (dueDay.getTime() === today.getTime()) return 'dashboard-due-this-week__chip--today'
			return ''
		},
		dueDateLabel(dueDate) {
			if (!dueDate) return t('planix', 'No due date')

			const now = new Date()
			const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
			const due = new Date(dueDate)
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
	},
}
</script>

<style scoped>
.dashboard-due-this-week__header {
	font-size: 16px;
	font-weight: 600;
	margin: 0 0 12px;
}

.dashboard-due-this-week__item {
	padding: 10px 12px;
	border-radius: var(--border-radius, 4px);
	cursor: pointer;
	transition: background-color 0.15s ease;
}

.dashboard-due-this-week__item:hover {
	background-color: var(--color-background-hover);
}

.dashboard-due-this-week__item:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.dashboard-due-this-week__title {
	font-weight: 500;
	margin-bottom: 4px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dashboard-due-this-week__meta {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.dashboard-due-this-week__project {
	background: var(--color-background-dark);
	padding: 1px 6px;
	border-radius: 8px;
}

.dashboard-due-this-week__chip {
	padding: 1px 6px;
	border-radius: 8px;
}

.dashboard-due-this-week__chip--today {
	background: var(--color-warning);
	color: var(--color-warning-text, #fff);
}

.dashboard-due-this-week__chip--overdue {
	background: var(--color-error);
	color: var(--color-error-text, #fff);
}
</style>
