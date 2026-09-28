<template>
	<div class="project-backlog">
		<!-- Breadcrumb -->
		<nav class="project-backlog__breadcrumb" aria-label="breadcrumb">
			<NcButton variant="tertiary-no-background" @click="$router.push({ name: 'Projects' })">
				{{ t('planninq', 'Projects') }}
			</NcButton>
			<span aria-hidden="true">&rsaquo;</span>
			<NcButton
				variant="tertiary-no-background"
				@click="$router.push({ name: 'ProjectBoard', params: { id: $route.params.id } })">
				{{ projectTitle }}
			</NcButton>
			<span aria-hidden="true">&rsaquo;</span>
			<span>{{ t('planninq', 'Backlog') }}</span>
		</nav>

		<!-- Page header with the create row -->
		<div class="project-backlog__header">
			<h2>{{ t('planninq', 'Backlog') }}</h2>
			<form class="project-backlog__create" @submit.prevent="createTask">
				<NcTextField
					v-model="newTitle"
					:label="t('planninq', 'New task')"
					data-testid="backlog-new-task" />
				<NcButton
					type="submit"
					variant="primary"
					:disabled="creating || newTitle.trim() === ''"
					data-testid="backlog-add">
					{{ t('planninq', 'Add') }}
				</NcButton>
			</form>
		</div>

		<!-- Sort and filter; both live in the query string -->
		<div class="project-backlog__toolbar">
			<NcSelect
				:modelValue="sortOption"
				:options="sortOptions"
				:clearable="false"
				:inputLabel="t('planninq', 'Sort by')"
				label="label"
				data-testid="backlog-sort"
				@update:modelValue="(option) => setQuery({ sort: option.id === 'rank' ? undefined : option.id })" />
			<NcSelect
				:modelValue="priorityOption"
				:options="priorityOptions"
				:clearable="false"
				:inputLabel="t('planninq', 'Priority')"
				label="label"
				data-testid="backlog-priority"
				@update:modelValue="(option) => setQuery({ priority: option.id || undefined })" />
			<NcCheckboxRadioSwitch
				:modelValue="showCancelled"
				data-testid="backlog-cancelled"
				@update:modelValue="(value) => setQuery({ cancelled: value ? '1' : undefined })">
				{{ t('planninq', 'Cancelled') }}
			</NcCheckboxRadioSwitch>
		</div>
		<p v-if="sort !== 'rank'" class="project-backlog__hint">
			{{ t('planninq', 'Sort by rank to reorder') }}
		</p>

		<div v-if="loading" class="project-backlog__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="rows.length === 0"
			:name="t('planninq', 'Nothing in the backlog')">
			<template #icon>
				<FormatListBulleted :size="20" />
			</template>
		</NcEmptyContent>

		<ol v-else class="project-backlog__list" data-testid="backlog-list">
			<li
				v-for="task in rows"
				:key="task.id"
				class="project-backlog__row"
				:class="{ 'project-backlog__row--drop-target': dropTargetId === task.id }"
				data-testid="backlog-row"
				:data-title="task.title"
				:draggable="sort === 'rank'"
				@dragstart="draggingTask = task"
				@dragend="draggingTask = null; dropTargetId = null"
				@dragover.prevent="dropTargetId = task.id"
				@drop.prevent="onDrop(task)">
				<DragIcon
					v-if="sort === 'rank'"
					class="project-backlog__handle"
					:size="20"
					aria-hidden="true" />
				<NcButton
					variant="tertiary-no-background"
					class="project-backlog__title"
					@click="$router.push({ name: 'TaskDetail', params: { id: $route.params.id, taskId: task.id } })">
					{{ task.title }}
				</NcButton>
				<span class="project-backlog__meta">{{ priorityLabel(task.priority) }}</span>
				<span v-if="task.dueDate" class="project-backlog__meta">{{ task.dueDate }}</span>
				<NcActions :aria-label="t('planninq', 'Task actions')" :forceMenu="true">
					<template v-if="sort === 'rank'">
						<NcActionButton :closeAfterClick="true" @click="step(task, -1)">
							<template #icon>
								<ArrowUpIcon :size="20" />
							</template>
							{{ t('planninq', 'Move up') }}
						</NcActionButton>
						<NcActionButton :closeAfterClick="true" @click="step(task, 1)">
							<template #icon>
								<ArrowDownIcon :size="20" />
							</template>
							{{ t('planninq', 'Move down') }}
						</NcActionButton>
					</template>
					<NcActionCaption :name="t('planninq', 'Move to board')" />
					<NcActionButton
						v-for="column in columns"
						:key="column.id"
						:closeAfterClick="true"
						@click="moveToBoard(task, column)">
						<template #icon>
							<ArrowRightIcon :size="20" />
						</template>
						{{ column.title }}
					</NcActionButton>
				</NcActions>
			</li>
		</ol>
	</div>
</template>

<script>
/**
 * ProjectBacklog view.
 *
 * The project's backlog: every task in no board column, in rank order. A
 * member adds a task straight into it, ranks it by dragging a row or with
 * "Move up" and "Move down", sorts it by rank, priority, due date or creation
 * date, filters it by priority or to the cancelled tasks, and moves a task
 * onto the board with a column choice. Sort and filters live in the query
 * string. Hydrates the project on a deep link so the breadcrumb shows its
 * title.
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-1.1
 */
import { showError } from '@nextcloud/dialogs'
import {
	NcActionButton,
	NcActionCaption,
	NcActions,
	NcButton,
	NcCheckboxRadioSwitch,
	NcEmptyContent,
	NcLoadingIcon,
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'
import ArrowDownIcon from 'vue-material-design-icons/ArrowDown.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import ArrowUpIcon from 'vue-material-design-icons/ArrowUp.vue'
import DragIcon from 'vue-material-design-icons/Drag.vue'
import FormatListBulleted from 'vue-material-design-icons/FormatListBulleted.vue'
import { useProjectsStore } from '../store/projects.js'
import {
	BACKLOG_SORTS,
	backlogTasks,
	filterBacklog,
	newBacklogTask,
	sortBacklog,
} from '../utils/backlogHelpers.js'
import {
	buildMovePatch,
	groupTasksByColumn,
	orderFor,
	orderPatchesForStep,
	sortColumns,
} from '../utils/columnHelpers.js'

export default {
	name: 'ProjectBacklog',

	components: {
		NcActionButton,
		NcActionCaption,
		NcActions,
		NcButton,
		NcCheckboxRadioSwitch,
		NcEmptyContent,
		NcLoadingIcon,
		NcSelect,
		NcTextField,
		ArrowDownIcon,
		ArrowRightIcon,
		ArrowUpIcon,
		DragIcon,
		FormatListBulleted,
	},

	data() {
		return {
			/** @type {Array} Every task of the project. */
			tasks: [],
			/** @type {Array} The project's board columns. */
			boardColumns: [],
			loading: false,
			newTitle: '',
			creating: false,
			/** @type {object|null} The row being dragged. */
			draggingTask: null,
			/** @type {string|null} Id of the row hovered during a drag. */
			dropTargetId: null,
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
		 * @spec exclude Trivial display getter — active project title with UUID fallback.
		 */
		projectTitle() {
			return this.projectsStore.activeProject?.title || this.$route.params.id
		},

		/**
		 * @spec exclude Display helper — the board columns in lane order.
		 */
		columns() {
			return sortColumns(this.boardColumns)
		},

		/**
		 * The sort from the query string, rank when absent or unknown.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-2.2
		 */
		sort() {
			const sort = this.$route.query.sort
			return BACKLOG_SORTS.includes(sort) ? sort : 'rank'
		},

		/**
		 * @spec exclude Display helper — whether the Cancelled filter is on.
		 */
		showCancelled() {
			return this.$route.query.cancelled === '1'
		},

		/**
		 * The backlog as shown: filtered, then in the chosen order.
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-2.3
		 */
		rows() {
			const backlog = backlogTasks(this.tasks, { cancelled: this.showCancelled })
			return sortBacklog(filterBacklog(backlog, { priority: this.$route.query.priority || '' }), this.sort)
		},

		/**
		 * @spec exclude Display helper — the sort choices.
		 */
		sortOptions() {
			return [
				{ id: 'rank', label: this.t('planninq', 'Rank') },
				{ id: 'priority', label: this.t('planninq', 'Priority') },
				{ id: 'due', label: this.t('planninq', 'Due date') },
				{ id: 'created', label: this.t('planninq', 'Created') },
			]
		},

		/**
		 * @spec exclude Display helper — the selected sort choice.
		 */
		sortOption() {
			return this.sortOptions.find((option) => option.id === this.sort)
		},

		/**
		 * @spec exclude Display helper — the priority filter choices.
		 */
		priorityOptions() {
			return [
				{ id: '', label: this.t('planninq', 'All priorities') },
				{ id: 'urgent', label: this.t('planninq', 'Urgent') },
				{ id: 'high', label: this.t('planninq', 'High') },
				{ id: 'normal', label: this.t('planninq', 'Normal') },
				{ id: 'low', label: this.t('planninq', 'Low') },
			]
		},

		/**
		 * @spec exclude Display helper — the selected priority filter.
		 */
		priorityOption() {
			return this.priorityOptions.find((option) => option.id === (this.$route.query.priority || '')) || this.priorityOptions[0]
		},
	},

	/**
	 * Hydrate the project on a deep link, then load the tasks and columns.
	 *
	 * @spec openspec/changes/backlog-list/tasks.md#task-1.1
	 */
	async mounted() {
		const id = this.$route.params.id
		if (!this.projectsStore.activeProject || this.projectsStore.activeProject.id !== id) {
			await this.projectsStore.fetchProject(id)
		}
		await this.load()
	},

	methods: {
		/**
		 * Load the project's tasks and board columns.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-1.1
		 */
		async load() {
			const id = this.$route.params.id
			this.loading = true
			try {
				this.boardColumns = await this.projectsStore.fetchColumns(id)
				this.tasks = await this.projectsStore.fetchTasks(id)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Change the sort or a filter, keeping the rest of the query.
		 *
		 * @param {object} change Query keys to set; undefined removes a key.
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-2.2
		 */
		setQuery(change) {
			const query = { ...this.$route.query, ...change }
			for (const key of Object.keys(query)) {
				if (query[key] === undefined) {
					delete query[key]
				}
			}
			this.$router.replace({ query })
		},

		/**
		 * @param {string} priority A task priority.
		 * @return {string} Its label.
		 * @spec exclude Display helper — the label of a priority value.
		 */
		priorityLabel(priority) {
			return (this.priorityOptions.find((option) => option.id === (priority || 'normal')) || {}).label
		},

		/**
		 * Create a task straight into the backlog, last in rank.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-1.2
		 */
		async createTask() {
			if (this.newTitle.trim() === '' || this.creating) {
				return
			}
			this.creating = true
			const payload = newBacklogTask(this.newTitle, this.$route.params.id, backlogTasks(this.tasks))
			const created = await this.projectsStore.createTask(payload)
			this.creating = false
			if (!created) {
				showError(this.t('planninq', 'Could not create the task. Please try again.'))
				return
			}
			this.newTitle = ''
			this.tasks = [...this.tasks, created]
		},

		/**
		 * Apply task patches optimistically and persist them; revert on failure.
		 *
		 * @param {Array<object>} patches Each `{ id, ...fields }`.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-2.1
		 */
		async patchTasks(patches) {
			if (!patches.length) {
				return
			}
			const previous = this.tasks
			const byId = new Map(patches.map((patch) => [patch.id, patch]))
			this.tasks = this.tasks.map((task) => byId.has(task.id) ? { ...task, ...byId.get(task.id) } : task)
			for (const { id, ...fields } of patches) {
				if (!(await this.projectsStore.updateTask(id, fields))) {
					this.tasks = previous
					showError(this.t('planninq', 'Could not move the task. Please try again.'))
					return
				}
			}
		},

		/**
		 * Move a row one step up (-1) or down (+1) in rank.
		 *
		 * @param {object} task      The task.
		 * @param {number} direction -1 or +1.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-2.1
		 */
		async step(task, direction) {
			await this.patchTasks(orderPatchesForStep(this.rows, task, direction))
		},

		/**
		 * Drop the dragged row in front of another; only when sorted by rank.
		 *
		 * @param {object} beforeTask The row it was dropped on.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-2.1
		 */
		async onDrop(beforeTask) {
			const task = this.draggingTask
			this.draggingTask = null
			this.dropTargetId = null
			if (!task || this.sort !== 'rank' || task.id === beforeTask.id) {
				return
			}
			const list = this.rows.filter((row) => row.id !== task.id)
			await this.patchTasks([{ id: task.id, columnOrder: orderFor(list, beforeTask) }])
		},

		/**
		 * Plan a task: put it at the bottom of a board lane with that lane's status.
		 *
		 * @param {object} task   The backlog task.
		 * @param {object} column The chosen column.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-3.1
		 */
		async moveToBoard(task, column) {
			const lane = groupTasksByColumn(this.tasks, this.columns)[column.id] || []
			await this.patchTasks([{ id: task.id, ...buildMovePatch(column, lane) }])
		},
	},
}
</script>

<style scoped>
.project-backlog {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.project-backlog__breadcrumb {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-bottom: 16px;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
}

.project-backlog__header {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 16px;
}

.project-backlog__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-backlog__create,
.project-backlog__toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
}

.project-backlog__toolbar {
	margin-bottom: 8px;
}

.project-backlog__hint {
	margin: 0 0 8px;
	color: var(--color-text-maxcontrast);
}

.project-backlog__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.project-backlog__row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 8px;
	border-bottom: 1px solid var(--color-border);
}

.project-backlog__row--drop-target {
	box-shadow: inset 0 2px 0 var(--color-primary-element);
}

.project-backlog__handle {
	cursor: grab;
	color: var(--color-text-maxcontrast);
}

.project-backlog__title {
	flex: 1;
	justify-content: flex-start;
}

.project-backlog__meta {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.project-backlog__loading {
	display: flex;
	justify-content: center;
	padding: 24px;
}
</style>
