<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 DashboardView — landing page with KPI cards, recent projects,
 and due-this-week tasks.

 @spec openspec/changes/dashboard-my-work/tasks.md#task-3
-->
<template>
	<div class="planix-dashboard">
		<header class="planix-dashboard__header">
			<h2>{{ t('planix', 'Dashboard') }}</h2>
		</header>

		<!-- Error banner -->
		<div v-if="fetchError" class="planix-dashboard__error">
			<span>{{ t('planix', 'Failed to load dashboard data') }}</span>
			<NcButton type="secondary" @click="loadData">
				{{ t('planix', 'Retry') }}
			</NcButton>
		</div>

		<!-- KPI Cards -->
		<CnKpiGrid :columns="4">
			<KpiCard
				:label="t('planix', 'Open Tasks')"
				:count="openTasksCount"
				:icon="FolderOpenOutline"
				color="--color-primary-element"
				filter-value="open"
				:loading="loading"
				@click="navigateToMyWork" />
			<KpiCard
				:label="t('planix', 'Overdue')"
				:count="overdueCount"
				:icon="AlertCircleOutline"
				color="--color-error"
				filter-value="overdue"
				:loading="loading"
				@click="navigateToMyWork" />
			<KpiCard
				:label="t('planix', 'In Progress')"
				:count="inProgressCount"
				:icon="ProgressClock"
				color="--color-warning"
				filter-value="in_progress"
				:loading="loading"
				@click="navigateToMyWork" />
			<KpiCard
				:label="t('planix', 'Completed Today')"
				:count="completedTodayCount"
				:icon="CheckCircleOutline"
				color="--color-success"
				filter-value="completed_today"
				:loading="loading"
				@click="navigateToMyWork" />
		</CnKpiGrid>

		<!-- Two-column layout: Recent Projects + Due This Week -->
		<div class="planix-dashboard__columns">
			<DashboardRecentProjects
				:projects="projects"
				:tasks="allProjectTasks" />
			<DashboardDueThisWeek
				:tasks="dueThisWeekTasks" />
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import { CnKpiGrid } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { useTasksStore } from '../store/tasks.js'
import { useProjectsStore } from '../store/projects.js'
import KpiCard from '../components/KpiCard.vue'
import DashboardRecentProjects from '../components/DashboardRecentProjects.vue'
import DashboardDueThisWeek from '../components/DashboardDueThisWeek.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import FolderOpenOutline from 'vue-material-design-icons/FolderOpenOutline.vue'
import ProgressClock from 'vue-material-design-icons/ProgressClock.vue'

/**
 * @spec openspec/changes/dashboard-my-work/tasks.md#task-3
 */
export default {
	name: 'DashboardView',
	components: {
		NcButton,
		CnKpiGrid,
		KpiCard,
		DashboardRecentProjects,
		DashboardDueThisWeek,
	},
	data() {
		return {
			loading: true,
			fetchError: false,
			tasks: [],
			projects: [],
			allProjectTasks: [],
			FolderOpenOutline,
			AlertCircleOutline,
			ProgressClock,
			CheckCircleOutline,
		}
	},
	computed: {
		/** @spec openspec/changes/dashboard-my-work/tasks.md#task-3 */
		openTasksCount() {
			return this.tasks.filter(
				(t) => t.status === 'open' || t.status === 'in_progress',
			).length
		},
		overdueCount() {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			return this.tasks.filter(
				(t) => t.dueDate && new Date(t.dueDate) < today
					&& t.status !== 'done' && t.status !== 'cancelled',
			).length
		},
		inProgressCount() {
			return this.tasks.filter((t) => t.status === 'in_progress').length
		},
		completedTodayCount() {
			return this.tasks.filter((t) => {
				if (!t.completedAt) return false
				const completed = new Date(t.completedAt)
				const now = new Date()
				return completed.getFullYear() === now.getFullYear()
					&& completed.getMonth() === now.getMonth()
					&& completed.getDate() === now.getDate()
			}).length
		},
		dueThisWeekTasks() {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			const weekFromNow = new Date(today)
			weekFromNow.setDate(weekFromNow.getDate() + 7)

			return this.tasks
				.filter(
					(t) => t.dueDate
						&& new Date(t.dueDate) <= weekFromNow
						&& t.status !== 'done'
						&& t.status !== 'cancelled',
				)
				.map((task) => {
					const project = this.projects.find((p) => p.id === task.project)
					return { ...task, projectTitle: project?.title || '' }
				})
		},
	},
	async mounted() {
		await this.loadData()
	},
	methods: {
		t,
		async loadData() {
			this.loading = true
			this.fetchError = false
			const uid = getCurrentUser()?.uid || ''

			const tasksStore = useTasksStore()
			const projectsStore = useProjectsStore()

			try {
				const [taskResult, projectResult] = await Promise.allSettled([
					tasksStore.fetchTasks({ assignedTo: uid }),
					projectsStore.fetchProjects(),
				])

				if (taskResult.status === 'fulfilled') {
					this.tasks = taskResult.value || []
				} else {
					this.fetchError = true
				}

				if (projectResult.status === 'fulfilled') {
					this.projects = projectResult.value || []
				} else {
					this.fetchError = true
				}

				// Fetch all tasks for project progress bars (not just assigned to user).
				if (this.projects.length > 0) {
					try {
						const objectStore = projectsStore._objectStore()
						const allTasks = await objectStore.fetchCollection('task', {})
						this.allProjectTasks = allTasks || []
					} catch {
						this.allProjectTasks = this.tasks
					}
				}
			} catch {
				this.fetchError = true
			} finally {
				this.loading = false
			}
		},
		navigateToMyWork(filterValue) {
			this.$router.push({ name: 'MyWork', query: { filter: filterValue } })
		},
	},
}
</script>

<style scoped>
.planix-dashboard {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.planix-dashboard__header {
	margin-bottom: 20px;
}

.planix-dashboard__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.planix-dashboard__error {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	margin-bottom: 16px;
	background: var(--color-error);
	color: var(--color-error-text, #fff);
	border-radius: var(--border-radius, 4px);
}

.planix-dashboard__columns {
	display: grid;
	grid-template-columns: 3fr 2fr;
	gap: 16px;
	margin-top: 20px;
}

@media (max-width: 1023px) {
	.planix-dashboard__columns {
		grid-template-columns: 1fr;
	}
}
</style>
