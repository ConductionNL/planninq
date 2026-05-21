<template>
	<div class="project-board">
		<!-- Access denied state (403 or non-member) -->
		<NcEmptyContent
			v-if="accessDenied"
			:name="t('planix', 'You do not have access to this project')"
			:description="t('planix', 'You are not a member of this project.')">
			<template #icon>
				<LockOutline :size="20" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$router.push({ name: 'Projects' })">
					{{ t('planix', 'Back to projects') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Loading state -->
		<div v-else-if="loading" class="project-board__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Board content -->
		<template v-else-if="project">
			<!-- Page header -->
			<div class="project-board__header">
				<!-- Color accent bar -->
				<span
					v-if="project.color"
					class="project-board__color-accent"
					:style="{ backgroundColor: project.color }"
					aria-hidden="true" />

				<span class="project-board__icon" aria-hidden="true">
					{{ project.icon || '📁' }}
				</span>

				<h2 class="project-board__title">
					{{ project.title }}
				</h2>

				<div class="project-board__header-actions">
					<NcButton
						:aria-label="t('planix', 'View backlog')"
						type="tertiary"
						@click="$router.push({ name: 'ProjectBacklog', params: { id: project.id } })">
						{{ t('planix', 'Backlog') }}
					</NcButton>
					<NcButton
						:aria-label="t('planix', 'Project settings')"
						type="tertiary"
						@click="openSettings">
						<template #icon>
							<CogIcon :size="20" />
						</template>
					</NcButton>
				</div>
			</div>

			<!-- Columns loading indicator -->
			<div v-if="columnsLoading" class="project-board__columns-loading">
				<NcLoadingIcon :size="32" />
			</div>

			<!-- No columns state -->
			<NcEmptyContent
				v-else-if="!columnsLoading && columns.length === 0"
				:name="t('planix', 'No columns yet')"
				:description="t('planix', 'Add columns in project settings to set up your board.')">
				<template #icon>
					<ViewColumnOutline :size="20" />
				</template>
			</NcEmptyContent>

			<!-- Column list -->
			<div v-else class="project-board__columns">
				<div
					v-for="column in columns"
					:key="column.id"
					class="project-board__column">
					<div class="project-board__column-header">
						<span class="project-board__column-title">{{ column.title }}</span>
					</div>

					<div class="project-board__column-body">
						<TaskCard
							v-for="task in columnTasks[column.id] || []"
							:key="task.id"
							:task="task" />
						<NcEmptyContent
							v-if="!columnTasksLoading && !(columnTasks[column.id] || []).length"
							:name="t('planix', 'No tasks')"
							:description="t('planix', 'Use the button below to add your first task.')" />
					</div>

					<div class="project-board__column-footer">
						<QuickAddTask
							:column-id="column.id"
							:project-id="project.id"
							@task-created="onTaskCreated(column.id, $event.task)" />
					</div>
				</div>
			</div>
		</template>

		<!-- Settings sidebar (rendered via App.vue outlet, passed via provide) -->
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import LockOutline from 'vue-material-design-icons/LockOutline.vue'
import ViewColumnOutline from 'vue-material-design-icons/ViewColumnOutline.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { useProjectsStore } from '../store/projects.js'
import { useObjectStore } from '../store/modules/object.js'
import ProjectSettingsSidebar from '../components/ProjectSettingsSidebar.vue'
import QuickAddTask from '../components/QuickAddTask.vue'
import TaskCard from '../components/TaskCard.vue'

/**
 * ProjectBoard — renders a project's kanban columns with inline quick-add.
 *
 * @spec openspec/changes/task-quick-add/tasks.md#task-1
 * @spec openspec/changes/task-quick-add/tasks.md#task-7
 */
export default {
	name: 'ProjectBoard',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		CogIcon,
		LockOutline,
		ViewColumnOutline,
		QuickAddTask,
		TaskCard,
	},

	inject: {
		setSidebar: { default: null },
		closeSidebar: { default: null },
	},

	data() {
		return {
			columnTasks: {},
			columnTasksLoading: false,
		}
	},

	computed: {
		projectsStore() {
			return useProjectsStore()
		},
		objectStore() {
			return useObjectStore()
		},
		project() {
			return this.projectsStore.activeProject
		},
		loading() {
			return this.projectsStore.loading
		},
		accessDenied() {
			const store = this.projectsStore
			if (store.error === 'forbidden') return true
			if (!store.loading && store.activeProject) {
				const uid = getCurrentUser()?.uid
				return !!uid && !store.activeProject.members?.includes(uid)
			}
			return false
		},
		columns() {
			const all = this.objectStore.objects['column'] || []
			return all
				.filter(c => c.project === this.project?.id)
				.slice()
				.sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
		},
		columnsLoading() {
			return !!this.objectStore.loading['column']
		},
	},

	async mounted() {
		const id = this.$route.params.id
		await this.projectsStore.fetchProject(id)

		if (this.project) {
			await this.loadColumns()
		}
	},

	beforeDestroy() {
		this.closeSidebar?.()
	},

	methods: {
		/**
		 * Fetch columns for the current project, then fetch their tasks.
		 *
		 * @spec openspec/changes/task-quick-add/tasks.md#task-1
		 */
		async loadColumns() {
			await this.objectStore.fetchObjects('column', {
				'object.project': this.project.id,
			})
			await this.loadColumnTasks()
		},

		/**
		 * Fetch all tasks for the current project and group them by column.
		 *
		 * @spec openspec/changes/task-quick-add/tasks.md#task-1
		 */
		async loadColumnTasks() {
			this.columnTasksLoading = true
			try {
				await this.objectStore.fetchObjects('task', {
					'object.project': this.project.id,
				})
				const tasks = this.objectStore.objects['task'] || []
				const grouped = {}
				tasks.forEach(task => {
					const col = task.column || task['object.column']
					if (!col) return
					if (!grouped[col]) grouped[col] = []
					grouped[col].push(task)
				})
				this.columnTasks = grouped
			} finally {
				this.columnTasksLoading = false
			}
		},

		/**
		 * Prepend the newly created task into the correct column bucket.
		 *
		 * @param {string} columnId
		 * @param {object} task
		 * @spec openspec/changes/task-quick-add/tasks.md#task-2
		 */
		onTaskCreated(columnId, task) {
			if (!this.columnTasks[columnId]) {
				this.$set(this.columnTasks, columnId, [])
			}
			this.columnTasks[columnId] = [task, ...this.columnTasks[columnId]]
		},

		openSettings() {
			if (!this.setSidebar) return
			this.setSidebar({
				...ProjectSettingsSidebar,
				propsData: { project: this.project },
				on: {
					close: () => this.closeSidebar?.(),
					archived: () => this.$router.push({ name: 'Projects' }),
					deleted: () => this.$router.push({ name: 'Projects' }),
				},
			})
		},
	},
}
</script>

<style scoped>
.project-board {
	padding: 8px 4px 24px;
	max-width: 1400px;
}

.project-board__header {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 24px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.project-board__color-accent {
	flex-shrink: 0;
	width: 6px;
	height: 32px;
	border-radius: 3px;
}

.project-board__icon {
	font-size: 24px;
	line-height: 1;
}

.project-board__title {
	flex: 1;
	margin: 0;
	font-size: 20px;
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.project-board__header-actions {
	display: flex;
	gap: 4px;
}

.project-board__loading,
.project-board__columns-loading {
	display: flex;
	justify-content: center;
	padding: 60px;
}

.project-board__columns {
	display: flex;
	gap: 12px;
	overflow-x: auto;
	padding-bottom: 16px;
	align-items: flex-start;
}

.project-board__column {
	flex: 0 0 260px;
	min-width: 200px;
	display: flex;
	flex-direction: column;
	border-radius: var(--border-radius-large);
	border: 1px solid var(--color-border);
	background: var(--color-background-dark);
}

.project-board__column-header {
	padding: 10px 12px;
	border-bottom: 1px solid var(--color-border);
}

.project-board__column-title {
	font-size: 14px;
	font-weight: 600;
	color: var(--color-main-text);
}

.project-board__column-body {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 8px;
	min-height: 60px;
}

.project-board__column-footer {
	padding: 6px 8px;
	border-top: 1px solid var(--color-border);
}
</style>
