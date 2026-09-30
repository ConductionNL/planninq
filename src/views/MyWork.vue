<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="my-work">
		<div class="my-work__header">
			<h2>{{ t('planninq', 'My tasks') }}</h2>
			<RouterLink :to="{ name: 'MyCalendar' }" data-testid="my-work-as-calendar">
				{{ t('planninq', 'Show as calendar') }}
			</RouterLink>
			<p v-if="figure" class="my-work__figure" data-testid="my-work-figure">
				<span>{{ figureLabel }}</span>
				<NcButton variant="tertiary" data-testid="my-work-show-all" @click="showAll">
					{{ t('planninq', 'Show all my tasks') }}
				</NcButton>
			</p>
		</div>

		<div v-if="loading" class="my-work__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent v-else-if="!listed.length"
			:name="t('planninq', 'No tasks assigned to you')"
			data-testid="my-work-empty">
			<template #icon>
				<ClipboardCheckOutlineIcon :size="20" />
			</template>
			<template #action>
				<NcButton variant="primary" data-testid="my-work-browse" @click="$router.push({ name: 'Projects' })">
					{{ t('planninq', 'Browse projects') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<template v-else>
			<section v-for="group in groups"
				:key="group.id"
				class="my-work__group"
				:data-testid="`my-work-group-${group.id}`">
				<h3>{{ groupTitle(group.id) }} <span class="my-work__count">{{ group.tasks.length }}</span></h3>
				<ul class="my-work__list">
					<li v-for="task in group.tasks"
						:key="task.id"
						class="my-work__row"
						data-testid="my-work-row">
						<div class="my-work__main">
							<router-link :to="taskRoute(task)" class="my-work__title" data-testid="my-work-title">
								{{ task.title }}
							</router-link>
							<span class="my-work__meta">
								<span data-testid="my-work-project">{{ projectTitle(task) }}</span>
								<span v-if="task.dueDate">{{ t('planninq', 'Due {date}', { date: formatDate(task.dueDate) }) }}</span>
								<span>{{ priorityLabel(task.priority) }}</span>
							</span>
						</div>
						<NcSelect class="my-work__status"
							:modelValue="statusOption(task.status)"
							:options="statusOptions"
							:clearable="false"
							:disabled="saving === task.id"
							:inputLabel="t('planninq', 'Status')"
							label="label"
							data-testid="my-work-status"
							@update:modelValue="(option) => changeStatus(task, option)" />
					</li>
				</ul>
			</section>
		</template>
	</div>
</template>

<script>
/**
 * My tasks: every task assigned to the user or shared with them, across the
 * projects they can read, grouped as Overdue, Due this week and Later, with
 * the status changed in place. A dashboard figure opens it narrowed through
 * `?group=` to the tasks that figure counts.
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
 */
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcEmptyContent, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import { useProjectsStore } from '../store/projects.js'
import { filterMyTasks, groupMyTasks, MY_WORK_FILTERS } from '../utils/myWork.js'

const STATUSES = ['open', 'in_progress', 'blocked', 'done', 'cancelled']

export default {
	name: 'MyWork',

	components: { ClipboardCheckOutlineIcon, NcButton, NcEmptyContent, NcLoadingIcon, NcSelect },

	data() {
		return {
			projectsStore: useProjectsStore(),
			tasks: [],
			projects: [],
			loading: true,
			saving: '',
		}
	},

	computed: {
		/**
		 * The dashboard figure in the address, or ''.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		figure() {
			const group = String(this.$route.query.group || '')
			return MY_WORK_FILTERS.includes(group) ? group : ''
		},

		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		figureLabel() {
			return {
				open: this.t('planninq', 'Open'),
				overdue: this.t('planninq', 'Overdue'),
				in_progress: this.t('planninq', 'In progress'),
				completed_today: this.t('planninq', 'Completed today'),
			}[this.figure] || ''
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		listed() {
			return filterMyTasks(this.tasks, getCurrentUser()?.uid || '', this.figure)
		},

		/**
		 * The groups with tasks in them; completed tasks form one group.
		 *
		 * @return {Array<{id: string, tasks: Array<object>}>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		groups() {
			if (this.figure === 'completed_today') {
				return [{ id: 'completed', tasks: this.listed }]
			}
			return groupMyTasks(this.listed).filter((group) => group.tasks.length > 0)
		},

		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		statusOptions() {
			const labels = {
				open: this.t('planninq', 'Open'),
				in_progress: this.t('planninq', 'In progress'),
				blocked: this.t('planninq', 'Blocked'),
				done: this.t('planninq', 'Done'),
				cancelled: this.t('planninq', 'Cancelled'),
			}
			return STATUSES.map((id) => ({ id, label: labels[id] }))
		},
	},

	/**
	 * @spec exclude Lifecycle glue: loads the tasks and the project names.
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		/**
		 * Read my tasks and the projects they sit in.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		async load() {
			this.loading = true
			try {
				const [tasks, projects] = await Promise.all([
					this.projectsStore.fetchMyTasks(),
					this.projectsStore.fetchProjects(),
				])
				this.tasks = tasks || []
				this.projects = projects || []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Save a new status and stay on the page.
		 *
		 * @param {object} task The task.
		 * @param {{id: string}|null} option The chosen status.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		async changeStatus(task, option) {
			if (!option || option.id === task.status) {
				return
			}
			this.saving = task.id
			try {
				const saved = await this.projectsStore.updateTaskStatus(task.id, option.id)
				if (!saved) {
					showError(this.t('planninq', 'Could not change the status. Please try again.'))
					return
				}
				this.tasks = this.tasks.map((row) => (row.id === task.id ? { ...row, ...saved, status: saved.status ?? option.id } : row))
			} finally {
				this.saving = ''
			}
		},

		/**
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		showAll() {
			this.$router.push({ name: 'MyWork' })
		},

		/**
		 * @param {string} id A group id.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		groupTitle(id) {
			return {
				overdue: this.t('planninq', 'Overdue'),
				week: this.t('planninq', 'Due this week'),
				later: this.t('planninq', 'Later'),
				completed: this.t('planninq', 'Completed today'),
			}[id] || ''
		},

		/**
		 * @param {string} status A status.
		 * @return {{id: string, label: string}|null}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		statusOption(status) {
			return this.statusOptions.find((option) => option.id === status) || null
		},

		/**
		 * @param {string} priority A priority.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		priorityLabel(priority) {
			return {
				urgent: this.t('planninq', 'Urgent'),
				high: this.t('planninq', 'High'),
				low: this.t('planninq', 'Low'),
			}[priority] || this.t('planninq', 'Normal')
		},

		/**
		 * @param {object} task The task.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		projectTitle(task) {
			const id = typeof task.project === 'object' ? task.project?.id : task.project
			return this.projects.find((project) => String(project.id) === String(id))?.title || ''
		},

		/**
		 * The task page, which returns here with Back.
		 *
		 * @param {object} task The task.
		 * @return {object}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.2
		 */
		taskRoute(task) {
			const id = typeof task.project === 'object' ? task.project?.id : task.project
			return { name: 'TaskDetail', params: { id, taskId: task.id }, query: { from: 'my-tasks' } }
		},

		/**
		 * @param {string} value An ISO date.
		 * @return {string}
		 *
		 * @spec exclude Display helper: a date in the user's locale.
		 */
		formatDate(value) {
			const date = new Date(value)
			return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString()
		},
	},
}
</script>

<style scoped>
.my-work {
	padding: 24px;
	max-width: 1000px;
}

.my-work__header {
	margin-bottom: 16px;
}

.my-work__figure {
	display: flex;
	gap: 8px;
	align-items: center;
	color: var(--color-text-maxcontrast);
}

.my-work__loading {
	display: flex;
	justify-content: center;
	padding: 48px 0;
}

.my-work__group {
	margin-bottom: 24px;
}

.my-work__count {
	color: var(--color-text-maxcontrast);
	font-weight: normal;
}

.my-work__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.my-work__row {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
}

.my-work__main {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.my-work__title {
	font-weight: 600;
	color: var(--color-main-text);
}

.my-work__meta {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	color: var(--color-text-maxcontrast);
}

.my-work__status {
	min-width: 180px;
}
</style>
