<template>
	<div class="projects-view">
		<p class="hidden-visually" aria-live="polite" data-testid="view-announcement">
			{{ announcement }}
		</p>

		<div v-if="loading" class="projects-view__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="!view"
			:name="t('planninq', 'This view does not exist or is not shared with you.')">
			<template #icon>
				<ViewDashboardOutline :size="20" />
			</template>
			<template #action>
				<NcButton @click="$router.push({ name: 'Boards' })">
					{{ t('planninq', 'Boards') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<template v-else>
			<header class="projects-view__header">
				<div class="projects-view__heading">
					<span class="projects-view__kind">{{ t('planninq', 'Cross-project view') }}</span>
					<h2 class="projects-view__title" data-testid="view-title">
						{{ view.title }}
					</h2>
				</div>
				<div v-if="manageable" class="projects-view__actions">
					<NcButton data-testid="view-edit" @click="openEditor">
						{{ t('planninq', 'Edit') }}
					</NcButton>
					<NcButton variant="error" data-testid="view-delete" @click="deleting = true">
						{{ t('planninq', 'Delete') }}
					</NcButton>
				</div>
			</header>

			<NcNoteCard v-if="hidden > 0" type="info" data-testid="hidden-projects">
				{{ hiddenText }}
			</NcNoteCard>

			<BoardFilterBar
				:filter="filter"
				:labels="labels"
				:people="people"
				:hideSaved="true"
				:shown="shownCount"
				:total="tasks.length"
				@update:filter="setFilter" />

			<StatusLanes
				:lanes="lanes"
				:projectsById="projectsById"
				:movable="canMove"
				@move="moveTask"
				@open="openTask" />

			<ProjectsViewEditDialog
				v-if="editing"
				:view="view"
				:projects="pickable"
				:uid="uid"
				@close="editing = false"
				@saved="onSaved" />
			<ProjectsViewDeleteDialog
				v-if="deleting"
				:view="view"
				@close="deleting = false"
				@deleted="$router.push({ name: 'Boards' })" />
		</template>
	</div>
</template>

<script>
/**
 * A cross-project view (boards-cross-project-board): the tasks of every
 * project in a saved view, in status lanes, each card naming its project.
 *
 * Every read and write runs with the viewer's own rights, so a project the
 * viewer is not in shows none of its tasks and is only counted. A move goes
 * through the task's own project columns (resolveViewMove), the same write
 * a move on that project's board makes.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
 */
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import BoardFilterBar from '../components/BoardFilterBar.vue'
import StatusLanes from '../components/StatusLanes.vue'
import ProjectsViewDeleteDialog from '../dialogs/ProjectsViewDeleteDialog.vue'
import ProjectsViewEditDialog from '../dialogs/ProjectsViewEditDialog.vue'
import { useProjectsStore } from '../store/projects.js'
import { decodeFilter, matchesFilter, withFilterQuery } from '../utils/boardFilter.js'
import { canManageView, mergeViewResults, pickableProjects, resolveViewMove, viewLanes } from '../utils/projectsView.js'
import { memberOptions } from '../utils/taskPeople.js'

export default {
	name: 'ProjectsView',

	components: { BoardFilterBar, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard, ProjectsViewDeleteDialog, ProjectsViewEditDialog, StatusLanes, ViewDashboardOutline },

	data() {
		return {
			projectsStore: useProjectsStore(),
			loading: true,
			view: null,
			tasks: [],
			projectsById: {},
			hidden: 0,
			labels: [],
			announcement: '',
			editing: false,
			deleting: false,
		}
	},

	computed: {
		/**
		 * @spec exclude Auth passthrough — the viewer's user id.
		 */
		uid() {
			return getCurrentUser()?.uid || ''
		},

		/**
		 * Whether the viewer may edit or delete the view: its owner or an admin.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		manageable() {
			return canManageView(this.view, { uid: this.uid, isAdmin: getCurrentUser()?.isAdmin === true })
		},

		/**
		 * @spec exclude Store passthrough — the projects the editor may add.
		 */
		pickable() {
			return pickableProjects(this.projectsStore.projects, this.uid)
		},

		/**
		 * @return {object} The filter in the page address.
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
		 */
		filter() {
			return decodeFilter(this.$route.query)
		},

		/**
		 * @return {object} The filtered tasks by status.
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
		 */
		lanes() {
			return viewLanes(this.tasks, this.filter, this.uid)
		},

		/**
		 * @spec exclude Display count — cards the filter shows.
		 */
		shownCount() {
			return this.tasks.filter((task) => matchesFilter(task, this.filter, this.uid)).length
		},

		/**
		 * @return {Array<{id: string, label: string}>} Everyone in the view's readable projects.
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
		 */
		people() {
			const byId = {}
			for (const project of Object.values(this.projectsById)) {
				for (const option of memberOptions(project)) {
					byId[option.id] = option
				}
			}
			return Object.values(byId).sort((a, b) => a.label.localeCompare(b.label))
		},

		/**
		 * @return {string} How many projects the viewer cannot see, never which.
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.2
		 */
		hiddenText() {
			return this.hidden === 1
				? this.t('planninq', '1 project in this view is hidden from you.')
				: this.t('planninq', '{count} projects in this view are hidden from you.', { count: this.hidden })
		},
	},

	watch: {
		/**
		 * @spec exclude Route glue — reloads when another view opens.
		 */
		'$route.params.id': function() {
			this.load()
		},
	},

	/**
	 * @spec exclude Lifecycle glue — loads the view.
	 */
	mounted() {
		this.load()
	},

	methods: {
		/**
		 * Read the view, then every listed project and its tasks in parallel,
		 * with the viewer's own rights.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
		 */
		async load() {
			this.loading = true
			this.view = await this.projectsStore.fetchBoardView(this.$route.params.id)
			if (this.view) {
				const [results, labels] = await Promise.all([
					Promise.all((this.view.projects || []).map(async (projectId) => {
						const project = await this.projectsStore.readProject(projectId)
						return { projectId, project, tasks: project ? await this.projectsStore.fetchTasks(projectId) : [] }
					})),
					this.projectsStore.fetchLabels(),
				])
				const merged = mergeViewResults(results)
				this.tasks = merged.tasks
				this.projectsById = merged.projectsById
				this.hidden = merged.hidden
				this.labels = Array.isArray(labels) ? labels : []
			}
			this.loading = false
		},

		/**
		 * @param {object} filter The new filter.
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
		 */
		setFilter(filter) {
			this.$router.replace({ query: withFilterQuery(this.$route.query, filter) })
		},

		/**
		 * Whether the viewer may move this card: they are in its project.
		 *
		 * @param {object} task The card.
		 * @return {boolean}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.4
		 */
		canMove(task) {
			const project = this.projectsById[task.project]
			return !!project && (project.owner === this.uid || (project.members || []).includes(this.uid))
		},

		/**
		 * Move a card to a status lane through its own project's columns, or
		 * say which project has no column for that status.
		 *
		 * @param {object} task   The card.
		 * @param {string} status The target lane.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.4
		 */
		async moveTask(task, status) {
			const project = this.projectsById[task.project]
			const columns = await this.projectsStore.fetchColumns(task.project)
			const result = resolveViewMove(task, status, columns, this.tasks.filter((card) => card.project === task.project))
			if (!result.ok) {
				const message = this.t('planninq', '{project} has no column for {status}.', { project: project?.title || '', status: this.statusTitle(status) })
				this.announcement = message
				showError(message)
				return
			}
			const saved = await this.projectsStore.updateTask(task.id, result.patch)
			if (!saved) {
				showError(this.t('planninq', 'Could not move the task. Please try again.'))
				return
			}
			this.tasks = this.tasks.map((card) => (card.id === task.id ? { ...card, ...result.patch, ...saved } : card))
			this.announcement = this.t('planninq', '"{task}" moved to {status}.', { task: task.title, status: this.statusTitle(status) })
		},

		/**
		 * @param {string} status A task status.
		 * @return {string} Its lane title.
		 * @spec exclude Display helper — the label of a status.
		 */
		statusTitle(status) {
			const titles = {
				open: this.t('planninq', 'Open'),
				in_progress: this.t('planninq', 'In progress'),
				blocked: this.t('planninq', 'Blocked'),
				done: this.t('planninq', 'Done'),
				cancelled: this.t('planninq', 'Cancelled'),
			}
			return titles[status] || status
		},

		/**
		 * Open the editor, reading the user's projects first when the page was
		 * opened directly.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		async openEditor() {
			if (!this.projectsStore.projects.length) {
				await this.projectsStore.fetchProjects({ status: 'active' })
			}
			this.editing = true
		},

		/**
		 * The view is saved: read it again with its projects.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		async onSaved() {
			this.editing = false
			await this.load()
		},

		/**
		 * @param {object} task The card.
		 * @spec exclude Navigation glue — opens the task in its own project.
		 */
		openTask(task) {
			this.$router.push({ name: 'TaskDetail', params: { id: task.project, taskId: task.id } })
		},
	},
}
</script>

<style scoped>
.projects-view {
	padding: 24px;
}

.projects-view__loading {
	display: flex;
	justify-content: center;
	padding: 48px 0;
}

.projects-view__header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 16px;
}

.projects-view__kind {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.projects-view__actions {
	display: flex;
	gap: 8px;
}

.projects-view__title {
	margin: 0;
}
</style>
