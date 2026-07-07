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
						:aria-label="t('planix', 'View timeline')"
						type="tertiary"
						@click="$router.push({ name: 'ProjectTimeline', params: { id: project.id } })">
						{{ t('planix', 'Timeline') }}
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

			<!-- Board loading overlay (tasks fetch) -->
			<div v-if="tasksLoading" class="project-board__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<!-- Kanban columns -->
			<div v-else class="project-board__columns" data-cy="kanban-board">
				<section
					v-for="column in columns"
					:key="column.status"
					class="kanban-column"
					:data-status="column.status"
					:aria-label="column.label"
					:class="{ 'kanban-column--drop-target': dropTargetStatus === column.status }"
					@dragover.prevent="onDragOver(column.status)"
					@dragleave="onDragLeave(column.status)"
					@drop="onDrop(column.status)">
					<header class="kanban-column__header">
						<h3 class="kanban-column__title">
							{{ column.label }}
						</h3>
						<span class="kanban-column__count" aria-hidden="true">
							{{ tasksByStatus[column.status].length }}
						</span>
					</header>

					<div class="kanban-column__body">
						<!-- Task cards -->
						<div
							v-for="task in tasksByStatus[column.status]"
							:key="task.id"
							class="kanban-column__card"
							:class="{ 'kanban-column__card--highlight': isHighlighted(task) }"
							draggable="true"
							@dragstart="onDragStart(task)"
							@dragend="onDragEnd">
							<TaskCard :task="task" />
						</div>

						<!-- Empty column placeholder -->
						<p v-if="tasksByStatus[column.status].length === 0" class="kanban-column__empty">
							{{ t('planix', 'No tasks') }}
						</p>
					</div>
				</section>
			</div>
		</template>

		<!-- Settings sidebar (rendered via App.vue outlet, passed via provide) -->
	</div>
</template>

<script>
/**
 * ProjectBoard view — the Kanban board.
 *
 * Renders the project's tasks as cards grouped into columns by task `status`
 * (the task schema's status enum: open / in_progress / blocked / done /
 * cancelled). Cards are dragged between columns to change a task's status,
 * persisted to OpenRegister via the projects store (`updateTaskStatus`, a
 * RBAC-scoped PATCH — ADR-005/ADR-022). The move is optimistic and reverts on
 * a failed write. Each card is a {@link TaskCard}, which surfaces the due-date
 * warning badge. Empty columns render a graceful placeholder.
 *
 * @spec openspec/specs/kanban-board.md
 */
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import LockOutline from 'vue-material-design-icons/LockOutline.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { useProjectsStore } from '../store/projects.js'
import { groupTasksByStatus, BOARD_STATUSES } from '../utils/taskHelpers.js'
import ProjectSettingsSidebar from '../components/ProjectSettingsSidebar.vue'
import TaskCard from '../components/TaskCard.vue'

export default {
	name: 'ProjectBoard',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		CogIcon,
		LockOutline,
		TaskCard,
	},

	inject: {
		setSidebar: { default: null },
		closeSidebar: { default: null },
	},

	data() {
		return {
			/**
			 * UUID of the task to highlight/scroll to from the ?task= deep-link query param.
			 */
			highlightTaskId: null,
			/** @type {Array} Tasks of the active project. */
			tasks: [],
			/** @type {boolean} Whether the task collection is being fetched. */
			tasksLoading: false,
			/** @type {object|null} The task currently being dragged. */
			draggingTask: null,
			/** @type {string|null} The status column currently hovered during a drag. */
			dropTargetStatus: null,
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — returns the projects Pinia store.
		 */
		projectsStore() {
			return useProjectsStore()
		},
		/**
		 * @spec exclude Store passthrough — proxies projectsStore.activeProject.
		 */
		project() {
			return this.projectsStore.activeProject
		},
		/**
		 * @spec exclude Store passthrough — proxies projectsStore.loading.
		 */
		loading() {
			return this.projectsStore.loading
		},
		/**
		 * The board's columns, in display order. One column per task status —
		 * the status enum is the single source of truth for the board lanes.
		 *
		 * @return {Array<{status: string, label: string}>}
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		columns() {
			const labels = {
				open: this.t('planix', 'Open'),
				in_progress: this.t('planix', 'In Progress'),
				blocked: this.t('planix', 'Blocked'),
				done: this.t('planix', 'Done'),
				cancelled: this.t('planix', 'Cancelled'),
			}
			return BOARD_STATUSES.map((status) => ({ status, label: labels[status] }))
		},
		/**
		 * Tasks grouped by their status. Every column key is always present so
		 * empty columns render gracefully; a task with an unknown status falls
		 * back to the "open" lane.
		 *
		 * @return {Object<string, Array>}
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		tasksByStatus() {
			return groupTasksByStatus(this.tasks, BOARD_STATUSES)
		},
		/**
		 * Whether the current user is denied access to the project — true on a
		 * stored 403 (`forbidden`) or when the loaded project's members array
		 * does not include the current user's UID.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		accessDenied() {
			const store = this.projectsStore
			if (store.error === 'forbidden') return true
			if (!store.loading && store.activeProject) {
				const uid = getCurrentUser()?.uid
				return !!uid && !store.activeProject.members?.includes(uid)
			}
			return false
		},
	},

	/**
	 * @spec exclude Lifecycle glue — fetches the route's project + tasks on mount.
	 */
	async mounted() {
		const id = this.$route.params.id
		await this.projectsStore.fetchProject(id)

		// Deep-link support: when the route contains ?task=<uuid>, highlight the
		// matching card once the board has rendered.
		const taskId = this.$route.query.task
		if (taskId) {
			this.highlightTaskId = taskId
		}

		await this.loadTasks(id)
	},

	beforeDestroy() {
		this.closeSidebar?.()
	},

	methods: {
		/**
		 * Load the project's tasks into the board.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		async loadTasks(projectId) {
			if (!projectId || this.accessDenied) return
			this.tasksLoading = true
			try {
				this.tasks = await this.projectsStore.fetchTasks(projectId)
			} finally {
				this.tasksLoading = false
			}
		},

		/**
		 * @param {object} task The task to test.
		 * @return {boolean}
		 * @spec exclude Display predicate — whether a task is the deep-link highlighted card.
		 */
		isHighlighted(task) {
			return !!this.highlightTaskId && task.id === this.highlightTaskId
		},

		/**
		 * @param {object} task The task being dragged.
		 * @spec exclude Drag glue — records the task being dragged.
		 */
		onDragStart(task) {
			this.draggingTask = task
		},

		/**
		 * @spec exclude Drag glue — clears drag state when the drag ends.
		 */
		onDragEnd() {
			this.draggingTask = null
			this.dropTargetStatus = null
		},

		/**
		 * @param {string} status The hovered column's status.
		 * @spec exclude Drag glue — marks the hovered column as the drop target.
		 */
		onDragOver(status) {
			this.dropTargetStatus = status
		},

		/**
		 * @param {string} status The column being left.
		 * @spec exclude Drag glue — clears the drop-target highlight on leave.
		 */
		onDragLeave(status) {
			if (this.dropTargetStatus === status) {
				this.dropTargetStatus = null
			}
		},

		/**
		 * Drop a dragged task into a column, changing its status.
		 *
		 * Applies the move optimistically (the card jumps to the new column
		 * immediately) and persists it via the RBAC-scoped store action; on a
		 * failed write the task reverts to its original status and an error toast
		 * is shown.
		 *
		 * @param {string} newStatus The target column's status
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		async onDrop(newStatus) {
			const task = this.draggingTask
			this.dropTargetStatus = null
			this.draggingTask = null
			if (!task || task.status === newStatus) return

			const previousStatus = task.status

			// Optimistic update.
			this.tasks = this.tasks.map((existing) =>
				existing.id === task.id ? { ...existing, status: newStatus } : existing,
			)

			const updated = await this.projectsStore.updateTaskStatus(task.id, newStatus)
			if (!updated) {
				// Revert on failure.
				this.tasks = this.tasks.map((existing) =>
					existing.id === task.id ? { ...existing, status: previousStatus } : existing,
				)
				showError(this.t('planix', 'Could not move the task. Please try again.'))
			}
		},

		/**
		 * Open the project settings sidebar via the App.vue outlet.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
		 */
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

.project-board__loading {
	display: flex;
	justify-content: center;
	padding: 60px;
}

.project-board__columns {
	display: flex;
	gap: 16px;
	align-items: flex-start;
	overflow-x: auto;
	padding-bottom: 8px;
}

.kanban-column {
	flex: 1 0 240px;
	min-width: 240px;
	max-width: 320px;
	display: flex;
	flex-direction: column;
	background: var(--color-background-dark);
	border: 1px solid var(--color-border);
	border-radius: 8px;
	padding: 8px;
}

.kanban-column--drop-target {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
}

.kanban-column__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 4px 8px 8px;
}

.kanban-column__title {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.kanban-column__count {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-hover);
	border-radius: 12px;
	padding: 1px 8px;
}

.kanban-column__body {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-height: 40px;
}

.kanban-column__card {
	cursor: grab;
}

.kanban-column__card:active {
	cursor: grabbing;
}

.kanban-column__card--highlight {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
	border-radius: 8px;
}

.kanban-column__empty {
	margin: 0;
	padding: 12px 8px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}
</style>
