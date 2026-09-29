<template>
	<div class="project-board">
		<!-- Access denied state (403 or non-member) -->
		<NcEmptyContent
			v-if="accessDenied"
			:name="t('planninq', 'You do not have access to this project')"
			:description="t('planninq', 'You are not a member of this project.')">
			<template #icon>
				<LockOutline :size="20" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="$router.push({ name: 'Projects' })">
					{{ t('planninq', 'Back to projects') }}
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
					<div
						class="project-board__view-switch"
						role="group"
						:aria-label="t('planninq', 'Board or list')">
						<NcButton
							:variant="view === 'board' ? 'primary' : 'tertiary'"
							:aria-pressed="view === 'board'"
							data-testid="view-board"
							@click="setView('board')">
							{{ t('planninq', 'Board') }}
						</NcButton>
						<NcButton
							:variant="view === 'list' ? 'primary' : 'tertiary'"
							:aria-pressed="view === 'list'"
							data-testid="view-list"
							@click="setView('list')">
							{{ t('planninq', 'List') }}
						</NcButton>
					</div>
					<NcButton
						v-if="!readOnly && !requestBanner && columns.length"
						variant="primary"
						data-testid="new-task"
						@click="creatingTask = true">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('planninq', 'New task') }}
					</NcButton>
					<NcButton
						:aria-label="t('planninq', 'Project settings')"
						variant="tertiary"
						@click="openSettings">
						<template #icon>
							<CogIcon :size="20" />
						</template>
					</NcButton>
				</div>
			</div>

			<ProjectTabs :projectId="project.id" />

			<!-- A requested or rejected project shows its review, not a board (projects-lifecycle-policy) -->
			<ProjectRequestBanner v-if="requestBanner" :project="project" @reviewed="onRequestReviewed" />
			<template v-else>
				<p v-if="readOnly" class="project-board__read-only" data-testid="board-read-only">
					{{ t('planninq', 'You read this project as a manager of its portfolio. Only its members change it.') }}
				</p>

				<!-- Label filter chips. Same idiom as the project list's status
			     filter: one chip per value, the active one primary, pressed
			     state exposed through aria-pressed. -->
				<div
					v-if="labels.length"
					class="project-board__filters"
					role="group"
					:aria-label="t('planninq', 'Filter tasks by label')">
					<NcChip
						v-for="chip in labelFilterChips"
						:key="chip.key"
						:text="chip.title"
						:variant="activeLabelId === chip.value ? 'primary' : 'secondary'"
						:noClose="true"
						class="project-board__filter-chip"
						data-testid="label-filter-chip"
						role="button"
						tabindex="0"
						:aria-pressed="activeLabelId === chip.value"
						@click="setLabelFilter(chip.value)"
						@keydown.enter="setLabelFilter(chip.value)"
						@keydown.space.prevent="setLabelFilter(chip.value)">
						<template v-if="chip.color" #icon>
							<span
								class="project-board__filter-swatch"
								:style="{ backgroundColor: chip.color }"
								aria-hidden="true" />
						</template>
					</NcChip>
				</div>

				<!-- Board loading overlay (tasks fetch) -->
				<div v-if="tasksLoading" class="project-board__loading">
					<NcLoadingIcon :size="32" />
				</div>

				<!-- List view: the same cards the board shows, in lane order and
			     then card order (boards-list-toggle). -->
				<table v-else-if="view === 'list'" class="project-board__list" data-testid="board-list">
					<thead>
						<tr>
							<th scope="col">
								{{ t('planninq', 'Title') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Column') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Priority') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Due date') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="row in listRows"
							:key="row.task.id"
							data-testid="board-list-row"
							:data-title="row.task.title">
							<td>
								<NcButton variant="tertiary-no-background" @click="navigateToTask(row.task)">
									{{ row.task.title }}
								</NcButton>
							</td>
							<td>{{ row.column.title }}</td>
							<td>{{ priorityLabel(row.task.priority) }}</td>
							<td>{{ row.task.dueDate || '' }}</td>
						</tr>
					</tbody>
				</table>

				<!-- Kanban columns: one lane per column object of the project,
			     in `order`. A task sits in the lane its `column` references;
			     a task without one is in the backlog. -->
				<div v-else class="project-board__columns" data-cy="kanban-board">
					<section
						v-for="(column, index) in columns"
						:key="column.id"
						class="kanban-column"
						:data-column="column.title"
						:data-column-id="column.id"
						:aria-label="column.title"
						:class="{ 'kanban-column--drop-target': dropTargetId === column.id }"
						@dragover.prevent="onDragOver(column.id)"
						@dragleave="onDragLeave(column.id)"
						@drop="onDrop(column)">
						<header
							class="kanban-column__header"
							:class="{ 'kanban-column__header--over': wipFor(column).over }"
							:style="column.color ? { borderTopColor: column.color } : null">
							<h3 class="kanban-column__title">
								{{ column.title }}
							</h3>
							<span class="kanban-column__count" data-testid="column-count">
								{{ wipFor(column).text }}
								<template v-if="wipFor(column).over">
									{{ t('planninq', 'over limit') }}
								</template>
							</span>
							<ColumnActions
								v-if="isOwner"
								:first="index === 0"
								:last="index === columns.length - 1"
								@edit="editingColumn = column"
								@move="(direction) => moveColumn(column, direction)"
								@remove="removingColumn = column" />
						</header>

						<div class="kanban-column__body">
							<!-- Task cards -->
							<div
								v-for="task in tasksByColumn[column.id]"
								:key="task.id"
								class="kanban-column__card"
								:class="{ 'kanban-column__card--highlight': isHighlighted(task) }"
								role="button"
								tabindex="0"
								:aria-label="task.title"
								data-testid="task-card"
								:draggable="readOnly ? 'false' : 'true'"
								@click="navigateToTask(task)"
								@keydown.enter="navigateToTask(task)"
								@keydown.space.prevent="navigateToTask(task)"
								@dragstart="onDragStart(task)"
								@dragend="onDragEnd"
								@drop.stop="onDrop(column, task)">
								<TaskCard
									:task="task"
									:labels="labelsForTask(task)"
									:blocked="blockedIds.has(task.id)"
									:openBlockerCount="openBlockerIds(task.id, dependenciesStore.edges, statusById).length" />

								<!-- Keyboard-operable move: the accessible equivalent
							     of drag-and-drop, to another lane or a step up or
							     down in this one. Not itself draggable, and stops
							     click propagation so it never opens the task. -->
								<div
									v-if="!readOnly"
									class="kanban-column__card-actions"
									draggable="false"
									@click.stop
									@keydown.enter.stop
									@keydown.space.stop
									@dragstart.stop>
									<NcActions
										:aria-label="t('planninq', 'Move task to another column')"
										:forceMenu="true">
										<NcActionButton
											:closeAfterClick="true"
											@click="stepCard(task, column, -1)">
											<template #icon>
												<ArrowUpIcon :size="20" />
											</template>
											{{ t('planninq', 'Move up') }}
										</NcActionButton>
										<NcActionButton
											:closeAfterClick="true"
											@click="stepCard(task, column, 1)">
											<template #icon>
												<ArrowDownIcon :size="20" />
											</template>
											{{ t('planninq', 'Move down') }}
										</NcActionButton>
										<NcActionButton
											:closeAfterClick="true"
											data-testid="move-to-backlog"
											@click="moveToBacklog(task)">
											<template #icon>
												<FormatListBulleted :size="20" />
											</template>
											{{ t('planninq', 'Move to backlog') }}
										</NcActionButton>
										<NcActionButton
											v-for="target in otherColumns(column)"
											:key="target.id"
											:closeAfterClick="true"
											@click="moveTask(task, target)">
											<template #icon>
												<ArrowRightIcon :size="20" />
											</template>
											{{ target.title }}
										</NcActionButton>
										<!-- Priority from the card (tasks-assignment-priority-labels) -->
										<NcActionSeparator />
										<NcActionCaption :name="t('planninq', 'Priority')" />
										<NcActionButton
											v-for="level in priorityLevels"
											:key="level.id"
											:closeAfterClick="true"
											:data-testid="'set-priority-' + level.id"
											:aria-pressed="(task.priority || 'normal') === level.id"
											@click="setPriority(task, level.id)">
											<template #icon>
												<FlagOutline :size="20" />
											</template>
											{{ level.label }}
										</NcActionButton>
									</NcActions>
								</div>
							</div>

							<!-- Empty column placeholder -->
							<p v-if="tasksByColumn[column.id].length === 0" class="kanban-column__empty">
								{{ t('planninq', 'No tasks') }}
							</p>
						</div>

						<!-- Quick add: Enter creates the task at the bottom of this lane
						     and keeps focus for the next one (tasks-create-edit-delete). -->
						<form
							v-if="!readOnly"
							class="kanban-column__quick-add"
							@submit.prevent="quickAdd(column)">
							<NcTextField
								v-model="quickAddTitles[column.id]"
								:label="t('planninq', 'Add a task')"
								:disabled="quickAdding === column.id"
								data-testid="quick-add" />
						</form>
					</section>

					<div v-if="isOwner" class="project-board__add-column">
						<NcButton variant="secondary" data-testid="add-column" @click="editingColumn = {}">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							{{ t('planninq', 'Add column') }}
						</NcButton>
					</div>
				</div>

				<TaskFormDialog
					v-if="creatingTask"
					:projectId="project.id"
					:column="columns[0] || null"
					:laneTasks="columns.length ? tasksByColumn[columns[0].id] : []"
					:project="project"
					@close="creatingTask = false"
					@saved="onTaskCreated" />
				<ColumnEditDialog
					v-if="editingColumn"
					:column="editingColumn.id ? editingColumn : null"
					:projectId="project.id"
					:nextOrder="nextColumnOrder"
					@close="editingColumn = null"
					@saved="onColumnsChanged" />
				<ColumnRemoveDialog
					v-if="removingColumn"
					:column="removingColumn"
					:columns="columns"
					:cards="tasksOfColumn(removingColumn)"
					:lanes="tasksByColumn"
					@close="removingColumn = null"
					@removed="onColumnsChanged" />
			</template>
		</template>

		<!-- Settings sidebar (rendered via App.vue outlet, passed via provide) -->
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
/**
 * ProjectBoard view — the Kanban board.
 *
 * Renders the project's tasks as cards grouped into columns by task `status`
 * (the task schema's status enum: open / in_progress / blocked / done /
 * cancelled). Cards are dragged between columns to change a task's status,
 * persisted to OpenRegister via the projects store (`updateTaskStatus`, a
 * RBAC-scoped PATCH — ADR-005/ADR-022). The move is optimistic and reverts on
 * a failed write. Each card is a {@link TaskCard}, which surfaces the due-date
 * warning badge and one chip per label the task carries. A chip row above the
 * columns filters the board down to a single label. Empty columns render a
 * graceful placeholder.
 *
 * @spec openspec/specs/kanban-board.md
 * @spec openspec/specs/admin-user-settings.md
 */
import { NcActionButton, NcActionCaption, NcActions, NcActionSeparator, NcButton, NcChip, NcEmptyContent, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import ArrowDownIcon from 'vue-material-design-icons/ArrowDown.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import ArrowUpIcon from 'vue-material-design-icons/ArrowUp.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import FlagOutline from 'vue-material-design-icons/FlagOutline.vue'
import FormatListBulleted from 'vue-material-design-icons/FormatListBulleted.vue'
import LockOutline from 'vue-material-design-icons/LockOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ColumnActions from '../components/ColumnActions.vue'
import ProjectRequestBanner from '../components/ProjectRequestBanner.vue'
import ProjectSettingsSidebar from '../components/ProjectSettingsSidebar.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import TaskCard from '../components/TaskCard.vue'
import ColumnEditDialog from '../dialogs/ColumnEditDialog.vue'
import ColumnRemoveDialog from '../dialogs/ColumnRemoveDialog.vue'
import TaskFormDialog from '../dialogs/TaskFormDialog.vue'
import { useDependenciesStore } from '../store/dependencies.js'
import { useProjectsStore } from '../store/projects.js'
import { backlogTasks, moveToBacklogPatch } from '../utils/backlogHelpers.js'
import {
	boardListRows,
	buildMovePatch,
	groupTasksByColumn,
	orderPatchesForStep,
	sortColumns,
	swapColumnPatches,
	wipState,
} from '../utils/columnHelpers.js'
import { filterTasksByLabel, labelId, resolveTaskLabels, sortLabelsByTitle } from '../utils/labelHelpers.js'
import { isReadOnlyFor } from '../utils/portfolioGrouping.js'
import { requestBanner } from '../utils/projectRequests.js'
import { newLaneTask } from '../utils/taskEditing.js'
import { deriveBlockedTaskIds, openBlockerIds, statusMapFromTasks } from '../utils/taskHelpers.js'
import { PRIORITIES, priorityPatch } from '../utils/taskPeople.js'

export default {
	name: 'ProjectBoard',

	components: {
		NcActions,
		NcActionButton,
		NcActionCaption,
		NcActionSeparator,
		FlagOutline,
		NcButton,
		NcChip,
		NcEmptyContent,
		NcLoadingIcon,
		ArrowDownIcon,
		ArrowRightIcon,
		ArrowUpIcon,
		CogIcon,
		ColumnActions,
		FormatListBulleted,
		ColumnEditDialog,
		ColumnRemoveDialog,
		LockOutline,
		NcTextField,
		PlusIcon,
		ProjectRequestBanner,
		ProjectTabs,
		TaskCard,
		TaskFormDialog,
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
			/** @type {string|null} Id of the lane currently hovered during a drag. */
			dropTargetId: null,
			/** @type {Array} The project's column objects. */
			boardColumns: [],
			/** @type {object|null} The column being edited; `{}` while adding one. */
			editingColumn: null,
			/** @type {object|null} The column being removed. */
			removingColumn: null,
			/** @type {Array} Every app-wide label, for the card chips and the filter. */
			labels: [],
			/** @type {string|null} Id of the label the board is filtered by, null for all. */
			activeLabelId: null,
			/** @type {boolean} Whether the New task dialog is open. */
			creatingTask: false,
			/** @type {object} Column id to the title typed in that lane's quick add. */
			quickAddTitles: {},
			/** @type {string} Id of the lane whose quick add is saving. */
			quickAdding: '',
		}
	},

	computed: {
		/**
		 * The banner of a requested or rejected project, which replaces the board.
		 *
		 * @return {{kind: string, note: string}|null}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
		 */
		requestBanner() {
			return requestBanner(this.project)
		},

		/**
		 * The priority levels for the card menu, most urgent first.
		 *
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-3.1
		 */
		priorityLevels() {
			return PRIORITIES.map((id) => ({ id, label: this.priorityLabel(id) }))
		},

		/**
		 * @spec exclude Store passthrough — the dependencies store the badges read.
		 */
		dependenciesStore() {
			return useDependenciesStore()
		},

		/**
		 * Task id to status, for the blocked derivation.
		 *
		 * @return {object}
		 *
		 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-2.1
		 */
		statusById() {
			return statusMapFromTasks(this.tasks)
		},

		/**
		 * The tasks an unfinished task blocks, derived once per render.
		 *
		 * @return {Set<string>}
		 *
		 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-2.1
		 */
		blockedIds() {
			return new Set(deriveBlockedTaskIds(this.dependenciesStore.edges, this.statusById))
		},

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
		 * The board's lanes: the project's column objects in `order`.
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.1
		 */
		columns() {
			return sortColumns(this.boardColumns)
		},

		/**
		 * Tasks grouped by the column they reference, in `columnOrder`. Every
		 * lane is a key; a column-less task is in the backlog, not here.
		 *
		 * @return {{[columnId: string]: Array}}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.1
		 */
		tasksByColumn() {
			return groupTasksByColumn(this.visibleTasks, this.columns)
		},

		/**
		 * The page's view from the query string: `list`, or the board.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/boards-list-toggle/tasks.md#task-1.2
		 */
		view() {
			return this.$route.query.view === 'list' ? 'list' : 'board'
		},

		/**
		 * The list view's rows: the board's cards in lane then card order.
		 *
		 * @return {Array<{task: object, column: object}>}
		 *
		 * @spec openspec/changes/boards-list-toggle/tasks.md#task-1.2
		 */
		listRows() {
			return boardListRows(this.visibleTasks, this.columns)
		},

		/**
		 * Whether the current user may manage the columns: the project owner or
		 * an admin. The server enforces the same rule (ColumnOwnerGuardListener).
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
		 */
		isOwner() {
			const user = getCurrentUser()
			return !!user && (user.isAdmin === true || this.project?.owner === user.uid)
		},

		/**
		 * Whether the board is read-only for the current user: a manager of the
		 * project's portfolio who is not on the project. The server refuses
		 * their writes the same way.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.6
		 */
		readOnly() {
			return isReadOnlyFor(this.project, getCurrentUser())
		},

		/**
		 * @spec exclude Display helper: the `order` a new column gets.
		 */
		nextColumnOrder() {
			return this.columns.reduce((max, column) => Math.max(max, Number(column.order) || 0), -1) + 1
		},

		/**
		 * The tasks the board currently shows: every task when no label filter
		 * is active, otherwise only the tasks carrying the selected label.
		 *
		 * Filtering is client-side on the collection already fetched, so it costs
		 * no request and the column counts follow it — the point of the filter is
		 * to narrow what is on screen, and a count that ignored it would say the
		 * opposite of what the board shows.
		 *
		 * @return {Array}
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		visibleTasks() {
			return filterTasksByLabel(this.tasks, this.activeLabelId)
		},

		/**
		 * The label filter's chips: an "All labels" reset first, then one chip
		 * per label in title order, each carrying its own colour.
		 *
		 * Every label is offered, not only the ones this board's tasks happen to
		 * use, so a freshly created label is selectable here straight away.
		 *
		 * @return {Array<{key: string, value: string|null, title: string, color: string}>}
		 *
		 * @spec openspec/specs/admin-user-settings.md
		 */
		labelFilterChips() {
			return [
				{ key: 'all', value: null, title: this.t('planninq', 'All labels'), color: '' },
				...sortLabelsByTitle(this.labels).map((label) => ({
					key: labelId(label),
					value: labelId(label),
					title: label.title,
					color: label.color,
				})),
			]
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
			if (store.error === 'forbidden') {
				return true
			}
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

		await this.loadColumns(id)
		await this.loadTasks(id)
		await this.loadLabels()
	},

	beforeUnmount() {
		this.closeSidebar?.()
	},

	methods: {
		openBlockerIds,

		/**
		 * After a review, reload the project, and the board of an approved one.
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
		 */
		async onRequestReviewed() {
			const id = this.project.id
			await this.projectsStore.fetchProject(id)
			if (!this.requestBanner) {
				await this.loadColumns(id)
				await this.loadTasks(id)
			}
		},

		/**
		 * Quick add: create a task with the typed title at the bottom of this
		 * lane, clear the field and keep focus in it for the next one.
		 *
		 * @param {object} column The lane.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-3.1
		 */
		async quickAdd(column) {
			const title = String(this.quickAddTitles[column.id] ?? '').trim()
			if (title === '' || this.quickAdding) {
				return
			}
			this.quickAdding = column.id
			const lane = groupTasksByColumn(this.tasks, this.columns)[column.id] || []
			const created = await this.projectsStore.createTask(newLaneTask({ title }, this.project.id, column, lane))
			this.quickAdding = ''
			if (!created) {
				showError(this.t('planninq', 'Could not create the task. Please try again.'))
				return
			}
			this.quickAddTitles = { ...this.quickAddTitles, [column.id]: '' }
			this.tasks = [...this.tasks, created]
			this.$nextTick(() => {
				this.$el.querySelector(`section[data-column-id="${column.id}"] [data-testid="quick-add"] input`)?.focus()
			})
		},

		/**
		 * Put a task made in the New task dialog on the board.
		 *
		 * @param {object} created The task as the server returned it.
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-3.1
		 */
		onTaskCreated(created) {
			this.creatingTask = false
			this.tasks = [...this.tasks, created]
		},

		/**
		 * Load the project's tasks into the board.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		async loadTasks(projectId) {
			if (!projectId || this.accessDenied) {
				return
			}
			this.tasksLoading = true
			try {
				const [tasks] = await Promise.all([
					this.projectsStore.fetchTasks(projectId),
					this.dependenciesStore.fetchEdges(),
				])
				this.tasks = tasks
			} finally {
				this.tasksLoading = false
			}
		},

		/**
		 * Load the project's columns, the board's lanes.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.1
		 */
		async loadColumns(projectId) {
			if (!projectId || this.accessDenied) {
				return
			}
			this.boardColumns = await this.projectsStore.fetchColumns(projectId)
		},

		/**
		 * Load every app-wide label, so cards can render their chips and the
		 * filter can offer them.
		 *
		 * A board with no labels simply renders no filter row: the labels are a
		 * decoration on the tasks, so a failed or empty read must never keep the
		 * board itself from rendering.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		async loadLabels() {
			if (this.accessDenied) {
				return
			}
			this.labels = await this.projectsStore.fetchLabels()
		},

		/**
		 * The label objects a task carries, resolved from its `labels` UUIDs.
		 *
		 * @param {object} task The task whose labels are resolved.
		 * @return {Array} The label objects, in title order.
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		labelsForTask(task) {
			return resolveTaskLabels(task, this.labels)
		},

		/**
		 * Set the board's label filter, or clear it when the active chip is
		 * pressed again.
		 *
		 * @param {string|null} id The label id to filter by, null for all labels.
		 *
		 * @spec openspec/specs/admin-user-settings.md
		 */
		setLabelFilter(id) {
			this.activeLabelId = (id !== null && id === this.activeLabelId) ? null : id
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
			this.draggingTask = this.readOnly ? null : task
		},

		/**
		 * @spec exclude Drag glue — clears drag state when the drag ends.
		 */
		onDragEnd() {
			this.draggingTask = null
			this.dropTargetId = null
		},

		/**
		 * @param {string} columnId The hovered lane's column id.
		 * @spec exclude Drag glue — marks the hovered lane as the drop target.
		 */
		onDragOver(columnId) {
			this.dropTargetId = columnId
		},

		/**
		 * @param {string} columnId The lane being left.
		 * @spec exclude Drag glue — clears the drop-target highlight on leave.
		 */
		onDragLeave(columnId) {
			if (this.dropTargetId === columnId) {
				this.dropTargetId = null
			}
		},

		/**
		 * Drop the dragged card into a lane: at the bottom, or in front of the
		 * card it was dropped on. The drop is never refused, over a WIP limit
		 * too.
		 *
		 * @param {object}      column       The target column.
		 * @param {object|null} [beforeTask] The card it was dropped on.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
		 */
		async onDrop(column, beforeTask = null) {
			const task = this.draggingTask
			this.dropTargetId = null
			this.draggingTask = null
			if (!task || (beforeTask && beforeTask.id === task.id)) {
				return
			}
			await this.applyMove(task, column, beforeTask)
		},

		/**
		 * Navigate to a task's detail page. The click/keyboard-activated
		 * equivalent of the (URL-only) TaskDetail route — mirrors
		 * `ProjectList.navigateToProject`.
		 *
		 * @param {object} task The task to open
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		navigateToTask(task) {
			if (!task) {
				return
			}
			this.$router.push({
				name: 'TaskDetail',
				params: { id: this.project?.id ?? this.$route.params.id, taskId: task.id },
			})
		},

		/**
		 * The lanes other than `column`: the keyboard "Move to" targets.
		 *
		 * @param {object} column The card's current column.
		 * @return {Array<object>}
		 *
		 * @spec exclude Display helper — filters the lane list for the move menu.
		 */
		otherColumns(column) {
			return this.columns.filter((other) => other.id !== column.id)
		},

		/**
		 * @param {object} column A lane.
		 * @return {Array<object>} Its cards, in order.
		 * @spec exclude Display helper — the cards of one lane.
		 */
		tasksOfColumn(column) {
			return this.tasksByColumn[column.id] || []
		},

		/**
		 * @param {object} column A lane.
		 * @return {{text: string, over: boolean}}
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.3
		 */
		wipFor(column) {
			return wipState(this.tasksOfColumn(column).length, column.wipLimit)
		},

		/**
		 * Keyboard move to another lane: the same path as a drop.
		 *
		 * @param {object} task   The task to move.
		 * @param {object} column The target column.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
		 */
		async moveTask(task, column) {
			await this.applyMove(task, column, null)
		},

		/**
		 * Optimistically put a task in a lane and PATCH its `column`,
		 * `columnOrder` and the lane's mapped status; revert and toast when the
		 * write fails. The server stamps `completedAt` when the status becomes
		 * done (TaskCompletionListener).
		 *
		 * @param {object}      task       The task to move.
		 * @param {object}      column     The target column.
		 * @param {object|null} beforeTask The card to land in front of.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
		 */
		async applyMove(task, column, beforeTask) {
			const lane = this.tasksOfColumn(column).filter((card) => card.id !== task.id)
			const patch = buildMovePatch(column, lane, beforeTask)
			await this.patchTasks([{ id: task.id, ...patch }])
		},

		/**
		 * Switch between the board and the list, keeping the rest of the query.
		 *
		 * @param {string} view `board` or `list`.
		 *
		 * @spec openspec/changes/boards-list-toggle/tasks.md#task-1.2
		 */
		setView(view) {
			const query = { ...this.$route.query }
			if (view === 'list') {
				query.view = 'list'
			} else {
				delete query.view
			}
			this.$router.replace({ query })
		},

		/**
		 * @param {string} priority A task priority.
		 * @return {string} Its label.
		 * @spec exclude Display helper — the label of a priority value.
		 */
		priorityLabel(priority) {
			const labels = {
				low: this.t('planninq', 'Low'),
				normal: this.t('planninq', 'Normal'),
				high: this.t('planninq', 'High'),
				urgent: this.t('planninq', 'Urgent'),
			}
			return labels[priority || 'normal'] || ''
		},

		/**
		 * Set a card's priority from its action menu; the chip changes at once
		 * and reverts when the save fails.
		 *
		 * @param {object} task     The card.
		 * @param {string} priority The level.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-3.1
		 */
		async setPriority(task, priority) {
			const patch = priorityPatch(task, priority)
			if (!Object.keys(patch).length) {
				return
			}
			const previous = this.tasks
			this.tasks = this.tasks.map((other) => other.id === task.id ? { ...other, ...patch } : other)
			if (!await this.projectsStore.updateTask(task.id, patch)) {
				this.tasks = previous
				showError(this.t('planninq', 'Could not change the priority. Please try again.'))
			}
		},

		/**
		 * Take a card off the board: no column, last in the backlog.
		 *
		 * @param {object} task The card.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-3.1
		 */
		async moveToBacklog(task) {
			await this.patchTasks([{ id: task.id, ...moveToBacklogPatch(backlogTasks(this.tasks)) }])
		},

		/**
		 * Move a card one step up (-1) or down (+1) in its lane.
		 *
		 * @param {object} task      The card.
		 * @param {object} column    Its lane.
		 * @param {number} direction -1 or +1.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.4
		 */
		async stepCard(task, column, direction) {
			await this.patchTasks(orderPatchesForStep(this.tasksOfColumn(column), task, direction))
		},

		/**
		 * Apply task patches optimistically and persist them; revert all on a failure.
		 *
		 * @param {Array<object>} patches Each `{ id, ...fields }`.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
		 */
		async patchTasks(patches) {
			if (!patches.length) {
				return
			}
			const previous = this.tasks
			const byId = new Map(patches.map((patch) => [patch.id, patch]))
			this.tasks = this.tasks.map((task) => byId.has(task.id) ? { ...task, ...byId.get(task.id) } : task)
			for (const { id, ...fields } of patches) {
				const updated = await this.projectsStore.updateTask(id, fields)
				if (!updated) {
					this.tasks = previous
					showError(this.t('planninq', 'Could not move the task. Please try again.'))
					return
				}
			}
		},

		/**
		 * Move a column one place left or right.
		 *
		 * @param {object} column    The column.
		 * @param {number} direction -1 or +1.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
		 */
		async moveColumn(column, direction) {
			for (const patch of swapColumnPatches(this.columns, column, direction)) {
				if (!(await this.projectsStore.saveColumn(patch))) {
					showError(this.t('planninq', 'Could not save the column. Please try again.'))
					break
				}
			}
			await this.loadColumns(this.project.id)
		},

		/**
		 * Reload lanes and cards after a column was added, edited or removed.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
		 */
		async onColumnsChanged() {
			this.editingColumn = null
			this.removingColumn = null
			await this.loadColumns(this.project.id)
			await this.loadTasks(this.project.id)
		},

		/**
		 * Open the project settings sidebar via the App.vue outlet.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
		 */
		openSettings() {
			if (!this.setSidebar) {
				return
			}
			this.setSidebar({
				...ProjectSettingsSidebar,
				propsData: { project: this.project },
				on: {
					close: () => this.closeSidebar?.(),
					archived: () => this.$router.push({ name: 'Projects' }),
					columnsChanged: () => this.onColumnsChanged(),
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

.project-board__filters {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px;
	margin-bottom: 16px;
}

.project-board__filter-chip {
	cursor: pointer;
}

/* Perceivable keyboard focus (WCAG 2.4.7) — the chip is role="button". */
.project-board__filter-chip:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

/* The label's own colour is DATA, so it arrives inline on the swatch, exactly
   like the project accent bar above. The surrounding chip stays on the theme
   tokens, and the chip text carries the name so colour is never the sole
   signal (WCAG 1.4.1). */
.project-board__filter-swatch {
	display: block;
	width: 12px;
	height: 12px;
	margin-inline-start: 4px;
	border-radius: 50%;
	border: 1px solid var(--color-border);
	background: var(--color-background-dark);
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
	gap: 8px;
	padding: 4px 8px 8px;
	border-top: 3px solid transparent;
}

.kanban-column__title {
	flex: 1;
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

/* Over the WIP limit: warning colour AND the words "over limit", so colour
   is not the only signal (WCAG 1.4.1). The drop is never refused. */
.kanban-column__header--over .kanban-column__count {
	color: var(--color-warning-text);
	background: var(--color-warning-hover, var(--color-background-hover));
	border: 1px solid var(--color-warning);
}

.project-board__view-switch {
	display: flex;
	gap: 4px;
}

.project-board__list {
	width: 100%;
	border-collapse: collapse;
}

.project-board__list th,
.project-board__list td {
	padding: 4px 8px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.project-board__list th {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.project-board__add-column {
	flex: 0 0 auto;
	padding-top: 4px;
}

.kanban-column__body {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-height: 40px;
}

.kanban-column__card {
	position: relative;
	cursor: grab;
	border-radius: 8px;
}

.kanban-column__card:active {
	cursor: grabbing;
}

/* Perceivable keyboard focus (WCAG 2.4.7) — the card is role="button". */
.kanban-column__card:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

/* Keyboard "Move to…" affordance, top-right of the card. Revealed on
   hover/focus-within so it does not clutter the card at rest, but is always
   reachable by keyboard. */
.kanban-column__card-actions {
	position: absolute;
	top: 6px;
	inset-inline-end: 6px;
	opacity: 0;
	transition: opacity 0.1s ease-in-out;
}

.kanban-column__card:hover .kanban-column__card-actions,
.kanban-column__card:focus-within .kanban-column__card-actions {
	opacity: 1;
}

/* Honour a reduced-motion preference: the fade is decorative, so drop the
   transition rather than the visibility change — the actions must still
   appear on hover and focus. */
@media (prefers-reduced-motion: reduce) {
	.kanban-column__card-actions {
		transition: none;
	}
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

.kanban-column__quick-add {
	padding: 8px;
	border-top: 1px solid var(--color-border);
}

.project-board__read-only {
	margin: 0 0 12px;
	color: var(--color-text-maxcontrast);
}
</style>
