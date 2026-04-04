<template>
	<div class="planix-dashboard">
		<header class="planix-dashboard__header">
			<h2>{{ t('planix', 'Dashboard') }}</h2>
		</header>

		<CnKpiGrid :columns="4">
			<div class="planix-dashboard__kpi-card" @click="goToMyWork('open')">
				<CnStatsBlock
					:title="t('planix', 'Open')"
					:count="kpi.open"
					:icon="FolderOutline"
					variant="primary"
					horizontal />
			</div>
			<div class="planix-dashboard__kpi-card" @click="goToMyWork('overdue')">
				<CnStatsBlock
					:title="t('planix', 'Overdue')"
					:count="kpi.overdue"
					:icon="AlertCircleOutline"
					variant="error"
					horizontal />
			</div>
			<div class="planix-dashboard__kpi-card" @click="goToMyWork('in_progress')">
				<CnStatsBlock
					:title="t('planix', 'In Progress')"
					:count="kpi.inProgress"
					:icon="ProgressClock"
					variant="warning"
					horizontal />
			</div>
			<div class="planix-dashboard__kpi-card" @click="goToMyWork('completed_today')">
				<CnStatsBlock
					:title="t('planix', 'Completed Today')"
					:count="kpi.completedToday"
					:icon="CheckCircleOutline"
					variant="success"
					horizontal />
			</div>
		</CnKpiGrid>

		<div class="planix-dashboard__columns">
			<CnConfigurationCard :title="t('planix', 'Recent projects')">
				<NcLoadingIcon v-if="loading" :size="24" />
				<ul v-else-if="recentProjects.length" class="planix-dashboard__project-list">
					<li
						v-for="project in recentProjects"
						:key="project.id"
						class="planix-dashboard__project-item"
						@click="goToProject(project.id)">
						<div class="planix-dashboard__project-row">
							<span class="planix-dashboard__project-icon" aria-hidden="true">
								{{ project.icon || '📁' }}
							</span>
							<span class="planix-dashboard__project-title">{{ project.title }}</span>
							<span class="planix-dashboard__project-count">
								{{ projectTaskStats(project.id).done }}/{{ projectTaskStats(project.id).total }}
							</span>
						</div>
						<div class="planix-dashboard__progress-track">
							<div
								class="planix-dashboard__progress-bar"
								:style="{ width: projectProgress(project.id) + '%' }" />
						</div>
					</li>
				</ul>
				<p v-else class="planix-dashboard__empty">
					{{ t('planix', 'No projects yet.') }}
				</p>
			</CnConfigurationCard>

			<CnConfigurationCard :title="t('planix', 'Due this week')">
				<NcLoadingIcon v-if="loading" :size="24" />
				<ul v-else-if="tasksDueThisWeek.length" class="planix-dashboard__task-list">
					<li
						v-for="task in tasksDueThisWeek"
						:key="task.id"
						class="planix-dashboard__task-item"
						:class="{
							'planix-dashboard__task-item--today': isDueToday(task),
							'planix-dashboard__task-item--tomorrow': isDueTomorrow(task),
						}">
						<span class="planix-dashboard__task-title">{{ task.title }}</span>
						<span class="planix-dashboard__task-project">{{ projectTitle(task.project) }}</span>
						<span class="planix-dashboard__task-due">{{ formatDueDate(task.dueDate) }}</span>
					</li>
				</ul>
				<p v-else class="planix-dashboard__empty">
					{{ t('planix', 'No tasks due this week.') }}
				</p>
			</CnConfigurationCard>
		</div>
	</div>
</template>

<script>
import { CnConfigurationCard, CnKpiGrid, CnStatsBlock, useObjectStore } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { NcLoadingIcon } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import ProgressClock from 'vue-material-design-icons/ProgressClock.vue'

const TASK_SCHEMA = 'task'
const PROJECT_SCHEMA = 'project'
const REGISTER = 'planix'

export default {
	name: 'Dashboard',
	components: {
		CnConfigurationCard,
		CnKpiGrid,
		CnStatsBlock,
		NcLoadingIcon,
	},
	data() {
		return {
			AlertCircleOutline,
			CheckCircleOutline,
			FolderOutline,
			ProgressClock,
			tasks: [],
			projects: [],
			loading: false,
		}
	},
	computed: {
		todayMidnight() {
			const d = new Date()
			d.setHours(0, 0, 0, 0)
			return d
		},
		kpi() {
			const today = this.todayMidnight
			return {
				open: this.tasks.filter((task) => ['open', 'in_progress'].includes(task.status)).length,
				overdue: this.tasks.filter(
					(task) => task.dueDate && new Date(task.dueDate) < today && task.status !== 'done',
				).length,
				inProgress: this.tasks.filter((task) => task.status === 'in_progress').length,
				completedToday: this.tasks.filter((task) => {
					if (!task.completedAt) return false
					const completed = new Date(task.completedAt)
					completed.setHours(0, 0, 0, 0)
					return completed.getTime() === today.getTime()
				}).length,
			}
		},
		recentProjects() {
			return [...this.projects]
				.sort((a, b) => new Date(b.updatedAt || 0) - new Date(a.updatedAt || 0))
				.slice(0, 5)
		},
		tasksDueThisWeek() {
			const today = this.todayMidnight
			const weekEnd = new Date(today)
			weekEnd.setDate(weekEnd.getDate() + 7)
			return this.tasks
				.filter((task) => task.dueDate && task.status !== 'done')
				.filter((task) => {
					const due = new Date(task.dueDate)
					return due >= today && due < weekEnd
				})
				.sort((a, b) => new Date(a.dueDate) - new Date(b.dueDate))
		},
	},
	mounted() {
		this.fetchData()
	},
	methods: {
		async fetchData() {
			this.loading = true
			try {
				const objectStore = useObjectStore()
				const uid = getCurrentUser()?.uid || ''

				if (!objectStore.objectTypeRegistry?.[TASK_SCHEMA]) {
					objectStore.registerObjectType(TASK_SCHEMA, TASK_SCHEMA, REGISTER)
				}
				if (!objectStore.objectTypeRegistry?.[PROJECT_SCHEMA]) {
					objectStore.registerObjectType(PROJECT_SCHEMA, PROJECT_SCHEMA, REGISTER)
				}

				const [tasks, projects] = await Promise.all([
					objectStore.fetchCollection(TASK_SCHEMA, { assignedTo: uid }),
					objectStore.fetchCollection(PROJECT_SCHEMA, { members: uid }),
				])

				this.tasks = tasks || []
				this.projects = projects || []
			} catch (error) {
				console.error('planix: fetchData failed', error)
				showError(t('planix', 'Failed to load your tasks. Please try again.'))
			} finally {
				this.loading = false
			}
		},
		projectTaskStats(projectId) {
			const projectTasks = this.tasks.filter((task) => task.project === projectId)
			return {
				total: projectTasks.length,
				done: projectTasks.filter((task) => task.status === 'done').length,
			}
		},
		projectProgress(projectId) {
			const stats = this.projectTaskStats(projectId)
			if (stats.total === 0) return 0
			return Math.round((stats.done / stats.total) * 100)
		},
		projectTitle(projectId) {
			const project = this.projects.find((p) => p.id === projectId)
			return project?.title || ''
		},
		isDueToday(task) {
			if (!task.dueDate) return false
			const due = new Date(task.dueDate)
			due.setHours(0, 0, 0, 0)
			return due.getTime() === this.todayMidnight.getTime()
		},
		isDueTomorrow(task) {
			if (!task.dueDate) return false
			const tomorrow = new Date(this.todayMidnight)
			tomorrow.setDate(tomorrow.getDate() + 1)
			const due = new Date(task.dueDate)
			due.setHours(0, 0, 0, 0)
			return due.getTime() === tomorrow.getTime()
		},
		formatDueDate(dueDate) {
			if (!dueDate) return ''
			const due = new Date(dueDate)
			due.setHours(0, 0, 0, 0)
			const today = this.todayMidnight
			const tomorrow = new Date(today)
			tomorrow.setDate(tomorrow.getDate() + 1)
			if (due.getTime() === today.getTime()) return t('planix', 'Today')
			if (due.getTime() === tomorrow.getTime()) return t('planix', 'Tomorrow')
			return due.toLocaleDateString()
		},
		goToMyWork(filter) {
			this.$router.push({ name: 'MyWork', query: { filter } })
		},
		goToProject(id) {
			this.$router.push({ name: 'ProjectBoard', params: { id } })
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
	margin: 0 0 8px;
	font-size: 22px;
	font-weight: 600;
}

.planix-dashboard__kpi-card {
	cursor: pointer;
}

.planix-dashboard__kpi-card:hover {
	opacity: 0.85;
}

.planix-dashboard__columns {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 16px;
	margin-top: 16px;
}

@media (max-width: 900px) {
	.planix-dashboard__columns {
		grid-template-columns: 1fr;
	}
}

.planix-dashboard__project-list,
.planix-dashboard__task-list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.planix-dashboard__project-item {
	padding: 8px 4px;
	border-bottom: 1px solid var(--color-border);
	cursor: pointer;
}

.planix-dashboard__project-item:last-child {
	border-bottom: none;
}

.planix-dashboard__project-item:hover {
	background-color: var(--color-background-hover);
}

.planix-dashboard__project-row {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 6px;
}

.planix-dashboard__project-title {
	flex: 1;
	font-weight: 500;
}

.planix-dashboard__project-count {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.planix-dashboard__progress-track {
	height: 4px;
	background: var(--color-border);
	border-radius: 2px;
	overflow: hidden;
}

.planix-dashboard__progress-bar {
	height: 100%;
	background: var(--color-primary);
	border-radius: 2px;
	transition: width 0.3s ease;
}

.planix-dashboard__task-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 4px;
	border-bottom: 1px solid var(--color-border);
}

.planix-dashboard__task-item:last-child {
	border-bottom: none;
}

.planix-dashboard__task-title {
	flex: 1;
}

.planix-dashboard__task-project {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
	padding: 2px 6px;
	border-radius: 10px;
	white-space: nowrap;
}

.planix-dashboard__task-due {
	font-size: 12px;
	white-space: nowrap;
}

.planix-dashboard__task-item--today .planix-dashboard__task-due,
.planix-dashboard__task-item--tomorrow .planix-dashboard__task-due {
	color: var(--color-warning);
	font-weight: 600;
}

.planix-dashboard__empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
