<template>
	<div class="task-detail">
		<!-- Loading state -->
		<div v-if="loading" class="task-detail__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Not found / forbidden state -->
		<NcEmptyContent
			v-else-if="!task"
			:name="errorTitle"
			:description="errorDescription">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="goBack">
					{{ t('planninq', 'Back to board') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Task detail + collaboration sidebar -->
		<div v-else class="task-detail__layout">
			<div class="task-detail__main">
				<div class="task-detail__header">
					<NcButton variant="tertiary" @click="goBack">
						<template #icon>
							<ArrowLeft :size="20" />
						</template>
						{{ t('planninq', 'Back to board') }}
					</NcButton>
					<h2 class="task-detail__title">
						<!-- Readable key such as VERG-42 (tasks-readable-keys) -->
						<span v-if="task && task.key" class="task-detail__key" data-testid="task-detail-key">{{ task.key }}</span>
						{{ taskTitle }}
					</h2>
					<div class="task-detail__actions">
						<NcButton variant="secondary" data-testid="task-edit" @click="editing = true">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('planninq', 'Edit') }}
						</NcButton>
						<NcButton
							variant="tertiary"
							:disabled="duplicating"
							data-testid="task-duplicate"
							@click="duplicate">
							<template #icon>
								<ContentCopy :size="20" />
							</template>
							{{ t('planninq', 'Duplicate') }}
						</NcButton>
						<NcButton
							v-if="canDelete"
							variant="tertiary"
							data-testid="task-delete"
							@click="deleting = true">
							<template #icon>
								<DeleteIcon :size="20" />
							</template>
							{{ t('planninq', 'Delete task') }}
						</NcButton>
					</div>
				</div>

				<!-- Description as Markdown; NcRichText escapes raw HTML (tasks-create-edit-delete) -->
				<section class="task-detail__description" aria-labelledby="task-detail-description-heading">
					<h3 id="task-detail-description-heading" class="task-detail__section-title">
						{{ t('planninq', 'Description') }}
					</h3>
					<NcRichText
						v-if="task.description"
						:text="task.description"
						:useMarkdown="true"
						data-testid="task-description" />
					<p v-else class="task-detail__no-description">
						{{ t('planninq', 'No description yet') }}
					</p>
				</section>

				<!-- People, priority and labels (tasks-assignment-priority-labels) -->
				<div class="task-detail__controls">
					<NcSelect
						:modelValue="responsibleOption"
						:options="peopleOptions"
						:inputLabel="t('planninq', 'Responsible')"
						label="label"
						data-testid="task-responsible"
						@update:modelValue="onResponsible" />
					<NcSelect
						:modelValue="sharedOptions"
						:options="sharerOptions"
						:inputLabel="t('planninq', 'Also working on this')"
						:multiple="true"
						label="label"
						data-testid="task-shared-with"
						@update:modelValue="onShared" />
					<NcSelect
						:modelValue="priorityOption"
						:options="priorityOptions"
						:inputLabel="t('planninq', 'Priority')"
						:clearable="false"
						label="label"
						data-testid="task-priority"
						@update:modelValue="onPriority" />
					<NcSelect
						:modelValue="selectedLabels"
						:options="labelOptions"
						:inputLabel="t('planninq', 'Labels')"
						:multiple="true"
						label="label"
						data-testid="task-labels"
						@update:modelValue="onLabels" />
				</div>

				<dl class="task-detail__fields">
					<!-- Vue 3 wants the key on the <template v-for> itself; the
					     Vue 2 spelling put one on each child, which the Vue 3
					     compiler rejects with "<template v-for> key should be
					     placed on the <template> tag". -->
					<template v-for="field in fields" :key="field.key">
						<dt>
							{{ field.label }}
						</dt>
						<dd>
							{{ field.value || '—' }}
						</dd>
					</template>
				</dl>

				<!-- Subtasks, one level deep (tasks-subtasks-checklist) -->
				<section
					v-if="!task.parent"
					class="task-detail__subtasks"
					aria-labelledby="task-detail-subtasks-heading"
					data-testid="task-subtasks">
					<h3 id="task-detail-subtasks-heading" class="task-detail__section-title">
						{{ t('planninq', 'Subtasks') }}
					</h3>
					<p v-if="subtasks.length" data-testid="subtask-progress">
						{{ t('planninq', '{done} of {total} done', subtaskCounts) }}
					</p>
					<ul v-if="subtasks.length" class="task-detail__list">
						<li v-for="sub in subtasks" :key="sub.id">
							<router-link :to="{ name: 'TaskDetail', params: { id: $route.params.id, taskId: sub.id } }">
								{{ sub.title }}
							</router-link>
							<span class="task-detail__muted">{{ statusText(sub.status) }}</span>
						</li>
					</ul>
					<form class="task-detail__add" @submit.prevent="addSubtask">
						<NcTextField
							v-model="newSubtaskTitle"
							:label="t('planninq', 'Add a subtask')"
							:disabled="addingSubtask"
							data-testid="subtask-add" />
					</form>
				</section>

				<!-- Checklist (tasks-subtasks-checklist) -->
				<section class="task-detail__checklist" aria-labelledby="task-detail-checklist-heading" data-testid="task-checklist">
					<h3 id="task-detail-checklist-heading" class="task-detail__section-title">
						{{ t('planninq', 'Checklist') }}
						<span v-if="checklist.length" class="task-detail__muted">{{ checklistDone }}</span>
					</h3>
					<ul v-if="checklist.length" class="task-detail__list">
						<li
							v-for="(item, index) in checklist"
							:key="item.id"
							class="task-detail__check"
							draggable="true"
							@dragstart="dragItem = item.id"
							@dragover.prevent
							@drop="dropItem(index)">
							<NcCheckboxRadioSwitch
								:modelValue="item.done"
								data-testid="checklist-item"
								@update:modelValue="saveChecklist(toggled(item))">
								{{ item.text }}
							</NcCheckboxRadioSwitch>
							<NcActions :aria-label="t('planninq', 'Checklist item actions')">
								<NcActionButton :closeAfterClick="true" @click="saveChecklist(moved(item, -1))">
									<template #icon>
										<ArrowUp :size="20" />
									</template>
									{{ t('planninq', 'Move up') }}
								</NcActionButton>
								<NcActionButton :closeAfterClick="true" @click="saveChecklist(moved(item, 1))">
									<template #icon>
										<ArrowDown :size="20" />
									</template>
									{{ t('planninq', 'Move down') }}
								</NcActionButton>
								<NcActionButton :closeAfterClick="true" @click="saveChecklist(removed(item))">
									<template #icon>
										<DeleteIcon :size="20" />
									</template>
									{{ t('planninq', 'Remove') }}
								</NcActionButton>
							</NcActions>
						</li>
					</ul>
					<form class="task-detail__add" @submit.prevent="addItem">
						<NcTextField
							v-model="newChecklistText"
							:label="t('planninq', 'Add a checklist item')"
							data-testid="checklist-add" />
					</form>
				</section>

				<!-- Time tracking -->
				<section class="task-detail__time" aria-labelledby="task-detail-time-heading">
					<h3 id="task-detail-time-heading" class="task-detail__section-title">
						{{ t('planninq', 'Time tracking') }}
					</h3>

					<!-- Estimate input -->
					<div class="task-detail__estimate">
						<NcTextField
							v-model="estimateInput"
							:label="t('planninq', 'Estimate')"
							:error="!!estimateError"
							:helperText="estimateError || t('planninq', 'e.g. 2h 30m, 90m, 1.5h')"
							data-testid="estimate-input" />
						<NcButton
							variant="secondary"
							:disabled="savingEstimate || !!estimateError || estimateInput.trim() === ''"
							@click="saveEstimate">
							{{ t('planninq', 'Save estimate') }}
						</NcButton>
					</div>

					<!-- Progress: logged vs estimate -->
					<p
						v-if="estimateMinutes > 0"
						class="task-detail__progress"
						:class="{ 'task-detail__progress--over': isOverEstimate }"
						data-testid="time-progress">
						{{ progressText }}
						<span v-if="isOverEstimate" class="task-detail__overage">
							({{ t('planninq', 'over by {amount}', { amount: overageText }) }})
						</span>
					</p>
					<p v-else class="task-detail__progress" data-testid="time-progress">
						{{ t('planninq', 'Logged: {logged}', { logged: loggedText }) }}
					</p>
					<p v-if="subtasks.length" class="task-detail__progress" data-testid="subtask-rollup">
						{{ t('planninq', 'Subtasks: {estimate} estimated, {logged} logged', rollupText) }}
						<br>
						{{ t('planninq', 'Total estimate: {total}', { total: formatMinutes(estimateMinutes + rollup.estimate) }) }}
					</p>

					<!-- Log time -->
					<NcButton variant="primary" data-testid="log-time" @click="openLogDialog()">
						<template #icon>
							<ClockPlusOutline :size="20" />
						</template>
						{{ t('planninq', 'Log time') }}
					</NcButton>

					<!-- Entries -->
					<ul v-if="timeEntries.length" class="task-detail__entries">
						<li v-for="entry in timeEntries" :key="entry.id" class="task-detail__entry">
							<span class="task-detail__entry-duration">{{ formatMinutes(entry.duration) }}</span>
							<span class="task-detail__entry-date">{{ entry.date }}</span>
							<span class="task-detail__entry-desc">{{ entry.description }}</span>
							<span class="task-detail__entry-user">{{ entry.user }}</span>
							<NcActions v-if="canModify(entry)">
								<NcActionButton :closeAfterClick="true" @click="openLogDialog(entry)">
									<template #icon>
										<PencilIcon :size="20" />
									</template>
									{{ t('planninq', 'Edit') }}
								</NcActionButton>
								<NcActionButton :closeAfterClick="true" @click="deleteEntry(entry)">
									<template #icon>
										<DeleteIcon :size="20" />
									</template>
									{{ t('planninq', 'Delete') }}
								</NcActionButton>
							</NcActions>
						</li>
					</ul>
				</section>

				<!-- Links to other tasks of the project (planning-dependencies-on-task-page) -->
				<TaskDependencies :task="task" :projectTasks="projectTasks" />
			</div>

			<!-- Collaboration sidebar: comments (notes), files, audit trail.
			     Legacy hardcoded-tabs mode (use-registry=false) so the three
			     built-in tabs render without requiring the integration registry;
			     generic tags/tasks tabs are hidden. All data comes from
			     OpenRegister per-object endpoints (ADR-022) — no Planninq PHP. -->
			<CnObjectSidebar
				:open="true"
				v-bind="sidebarConfig"
				:title="taskTitle"
				:subtitle="t('planninq', 'Task')"
				:filesLabel="t('planninq', 'Attachments')"
				:notesLabel="t('planninq', 'Comments')"
				:auditTrailLabel="t('planninq', 'Activity')"
				@update:open="onSidebarToggle" />
		</div>

		<!-- Log/edit time dialog -->
		<TimeEntryDialog
			v-if="dialogOpen"
			:taskId="taskId"
			:entry="editingEntry"
			@close="closeDialog"
			@saved="onEntrySaved" />

		<TaskFormDialog
			v-if="editing && task"
			:task="task"
			:project="project"
			@close="editing = false"
			@saved="onTaskSaved" />
		<TaskDeleteDialog
			v-if="deleting && task"
			:task="task"
			:hasTime="timeEntries.length > 0"
			:subtasks="subtasks"
			@close="deleting = false"
			@deleted="onTaskDeleted"
			@cancelled="onTaskSaved" />
	</div>
</template>

<script>
import { CnObjectSidebar } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { NcActionButton, NcActions, NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon, NcRichText, NcSelect, NcTextField } from '@nextcloud/vue'
import { mapState } from 'pinia'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import ClockPlusOutline from 'vue-material-design-icons/ClockPlusOutline.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import TaskDependencies from '../components/TaskDependencies.vue'
import TaskDeleteDialog from '../dialogs/TaskDeleteDialog.vue'
import TaskFormDialog from '../dialogs/TaskFormDialog.vue'
import TimeEntryDialog from '../dialogs/TimeEntryDialog.vue'
import { useDependenciesStore } from '../store/dependencies.js'
import { useSettingsStore } from '../store/modules/settings.js'
import { useObjectStore } from '../store/objectStore.js'
import { useProjectsStore } from '../store/projects.js'
import { useTimeEntriesStore } from '../store/timeEntries.js'
import { formatDuration, parseDuration } from '../utils/durationParser.js'
import { addChecklistItem, checklistCount, moveChecklistItem, newSubtask, removeChecklistItem, subtaskProgress, subtaskRollup, toggleChecklistItem } from '../utils/taskBreakdown.js'
import { canDeleteTask } from '../utils/taskEditing.js'
import { taskCollaborationSidebarConfig } from '../utils/taskHelpers.js'
import { labelsPatch, memberOptions, PRIORITIES, priorityPatch, responsiblePatch, sharedWithPatch } from '../utils/taskPeople.js'
import { displayNames } from '../utils/userNames.js'
import { taskHeading } from '../utils/workItemKeys.js'

/**
 * Task detail view.
 *
 * Renders a single task's fields and mounts the collaboration sidebar
 * (Comments / Attachments / Activity tabs) backed by OpenRegister per-object
 * APIs. Reached from the board via the `?task=<uuid>` deep-link or the
 * `/projects/:id/tasks/:taskId` route.
 *
 * @spec openspec/specs/task-collaboration.md
 */
export default {
	name: 'TaskDetail',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcCheckboxRadioSwitch,
		NcEmptyContent,
		NcLoadingIcon,
		NcRichText,
		NcSelect,
		NcTextField,
		CnObjectSidebar,
		ArrowDown,
		ArrowLeft,
		ArrowUp,
		ContentCopy,
		AlertCircleOutline,
		ClockPlusOutline,
		PencilIcon,
		DeleteIcon,
		TaskDependencies,
		TaskDeleteDialog,
		TaskFormDialog,
		TimeEntryDialog,
	},

	data() {
		return {
			projectsStore: useProjectsStore(),
			timeEntriesStore: useTimeEntriesStore(),
			settingsStore: useSettingsStore(),
			estimateInput: '',
			savingEstimate: false,
			projectTasks: [],
			dialogOpen: false,
			editingEntry: null,
			editing: false,
			deleting: false,
			project: null,
			names: {},
			labels: [],
			projectEntries: [],
			newSubtaskTitle: '',
			addingSubtask: false,
			newChecklistText: '',
			dragItem: null,
			duplicating: false,
			// Live-updates handle for the or-object-{uuid} subscription of the
			// task being viewed. livePendingKey marks an in-flight subscribe so
			// a concurrent same-key call doesn't double-subscribe; liveEpoch
			// invalidates in-flight resolutions after a release (task switch /
			// destroy). liveUnwatch tears down the cache→activeTask bridge.
			liveHandle: null,
			liveKey: '',
			livePendingKey: '',
			liveEpoch: 0,
			liveUnwatch: null,
		}
	},

	computed: {
		...mapState(useProjectsStore, ['activeTask', 'loading', 'error']),

		/**
		 * UUID of the task from the route.
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		taskId() {
			return this.$route.params.taskId
		},

		/**
		 * The loaded task object (or null).
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		task() {
			return this.activeTask
		},

		/**
		 * CnObjectSidebar props (register/schema/objectId/hidden tabs).
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		sidebarConfig() {
			return taskCollaborationSidebarConfig({ id: this.taskId })
		},

		/**
		 * Display title of the task.
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		taskTitle() {
			return this.task?.title || this.t('planninq', 'Untitled task')
		},

		/**
		 * Label/value pairs rendered in the detail body.
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		fields() {
			const t = this.task || {}
			return [
				{ key: 'status', label: this.t('planninq', 'Status'), value: t.status },
				{ key: 'dueDate', label: this.t('planninq', 'Due date'), value: t.dueDate },
			]
		},

		/**
		 * The task's subtasks, from the project's tasks.
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-2.1
		 */
		subtasks() {
			return this.task ? this.projectTasks.filter((other) => other.parent === this.task.id) : []
		},

		/**
		 * @spec exclude Display helper, done and total subtasks.
		 */
		subtaskCounts() {
			return subtaskProgress(this.subtasks)
		},

		/**
		 * The subtasks' summed estimate and logged time, in minutes.
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-4.1
		 */
		rollup() {
			const ids = new Set(this.subtasks.map((sub) => sub.id))
			const entries = {}
			for (const entry of this.projectEntries) {
				const id = entry?.task?.id ?? entry?.task
				if (ids.has(id)) {
					(entries[id] = entries[id] || []).push(entry)
				}
			}
			return subtaskRollup(this.subtasks, entries)
		},

		/**
		 * @spec exclude Display helper, the rollup as text.
		 */
		rollupText() {
			return { estimate: formatDuration(this.rollup.estimate), logged: formatDuration(this.rollup.logged) }
		},

		/**
		 * @spec exclude Display helper, the task's checklist.
		 */
		checklist() {
			return this.task?.checklist || []
		},

		/**
		 * @spec exclude Display helper, the checklist's done count.
		 */
		checklistDone() {
			return checklistCount(this.checklist)
		},

		/**
		 * The project's members as picker options, by display name.
		 *
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
		 */
		peopleOptions() {
			return memberOptions(this.project, this.names)
		},

		/**
		 * @spec exclude Display helper, the members who can share the task.
		 */
		sharerOptions() {
			return this.peopleOptions.filter((option) => option.id !== this.task?.assignedTo)
		},

		/**
		 * @spec exclude Display helper, the labels on the task.
		 */
		selectedLabels() {
			return this.labelOptions.filter((option) => (this.task?.labels || []).includes(option.id))
		},

		/**
		 * @spec exclude Display helper, the selected responsible person.
		 */
		responsibleOption() {
			const uid = this.task?.assignedTo
			return uid ? { id: uid, label: this.names[uid] || uid } : null
		},

		/**
		 * @spec exclude Display helper, the people the task is shared with.
		 */
		sharedOptions() {
			return (this.task?.sharedWith || []).map((uid) => ({ id: uid, label: this.names[uid] || uid }))
		},

		/**
		 * @spec exclude Display helper, the priority choices.
		 */
		priorityOptions() {
			const labels = { urgent: this.t('planninq', 'Urgent'), high: this.t('planninq', 'High'), normal: this.t('planninq', 'Normal'), low: this.t('planninq', 'Low') }
			return PRIORITIES.map((id) => ({ id, label: labels[id] }))
		},

		/**
		 * @spec exclude Display helper, the selected priority.
		 */
		priorityOption() {
			return this.priorityOptions.find((option) => option.id === (this.task?.priority || 'normal'))
		},

		/**
		 * @spec exclude Display helper, every label as a picker option.
		 */
		labelOptions() {
			return this.labels.map((label) => ({ id: label.id ?? label['@self']?.id, label: label.title }))
		},

		/**
		 * Whether "Delete task" shows: the reporter, the project owner or an admin.
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-4.1
		 */
		canDelete() {
			const user = getCurrentUser()
			return canDeleteTask(this.task, this.project, user ? { uid: user.uid, isAdmin: user.isAdmin === true } : null)
		},

		/**
		 * Time entries for this task (all users) from the timeEntries store.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		timeEntries() {
			return this.timeEntriesStore.entries
		},

		/**
		 * The task's estimate in minutes (0 when unset).
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		estimateMinutes() {
			return Number(this.task?.estimatedDuration) || 0
		},

		/**
		 * Total logged minutes across every entry on this task.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		loggedMinutes() {
			return this.timeEntries.reduce((acc, e) => acc + (Number(e.duration) || 0), 0)
		},

		/**
		 * Inline validation error for the estimate input (empty is allowed —
		 * the Save button is simply disabled — but non-empty must parse).
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		estimateError() {
			if (this.estimateInput.trim() === '') {
				return ''
			}
			return parseDuration(this.estimateInput) === null
				? this.t('planninq', 'Enter a valid estimate (e.g. 2h 30m, 90m, 1.5h)')
				: ''
		},

		/**
		 * Human-readable total logged time.
		 *
		 * @spec exclude Display getter — formats loggedMinutes.
		 */
		loggedText() {
			return formatDuration(this.loggedMinutes)
		},

		/**
		 * Progress string "logged / estimate", e.g. "1h 30m / 3h".
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		progressText() {
			return `${formatDuration(this.loggedMinutes)} / ${formatDuration(this.estimateMinutes)}`
		},

		/**
		 * Whether logged time exceeds the estimate (progress turns red).
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		isOverEstimate() {
			return this.estimateMinutes > 0 && this.loggedMinutes > this.estimateMinutes
		},

		/**
		 * Human-readable overage (logged − estimate).
		 *
		 * @spec exclude Display getter — formats the overage amount.
		 */
		overageText() {
			return formatDuration(Math.max(0, this.loggedMinutes - this.estimateMinutes))
		},

		/**
		 * Title shown in the not-found / forbidden empty state.
		 *
		 * @spec exclude Presentational empty-state copy; no observable behaviour.
		 */
		errorTitle() {
			return this.error === 'forbidden'
				? this.t('planninq', 'You do not have access to this task')
				: this.t('planninq', 'Task not found')
		},

		/**
		 * Description shown in the not-found / forbidden empty state.
		 *
		 * @spec exclude Presentational empty-state copy; no observable behaviour.
		 */
		errorDescription() {
			return this.error === 'forbidden'
				? this.t('planninq', 'You are not a member of this task\'s project.')
				: this.t('planninq', 'The task may have been deleted.')
		},
	},

	watch: {
		taskId: {
			immediate: true,
			/**
			 * Load the task whenever the route's task id changes.
			 *
			 * @param {string} id The task UUID from the route.
			 *
			 * @spec openspec/specs/task-collaboration.md
			 */
			async handler(id) {
				if (id) {
					await this.projectsStore.fetchTask(id)
					this.setDocumentTitle()
					this.estimateInput = this.estimateMinutes > 0 ? formatDuration(this.estimateMinutes) : ''
					await this.timeEntriesStore.fetchForTask(id)
					await this.loadLinks()
					this.syncLiveSubscription()
				} else {
					this.releaseLiveSubscription()
				}
			},
		},
	},

	/**
	 * Lifecycle hook: release the live object subscription on unmount.
	 *
	 * @spec openspec/specs/realtime-updates.md
	 */
	beforeUnmount() {
		this.releaseLiveSubscription()
	},

	methods: {
		/**
		 * Put the task's key and title in the browser tab (tasks-readable-keys).
		 *
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-3.1
		 */
		setDocumentTitle() {
			const heading = taskHeading(this.task)
			if (heading) {
				document.title = `${heading} - Planninq`
			}
		},

		/**
		 * Load the project's tasks and the dependency links for the Dependencies section.
		 *
		 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-1.1
		 */
		async loadLinks() {
			const projectId = this.task?.project?.id || this.task?.project
			const [tasks, project] = await Promise.all([
				projectId ? this.projectsStore.fetchTasks(String(projectId)) : Promise.resolve([]),
				projectId ? this.projectsStore._objectStore().fetchObject('project', String(projectId)) : Promise.resolve(null),
				useDependenciesStore().fetchEdges(),
			])
			this.projectTasks = Array.isArray(tasks) ? tasks : []
			this.project = project || null
			this.projectEntries = projectId ? await this.projectsStore.fetchProjectTimeEntries(String(projectId)) : []
			const [names, labels] = await Promise.all([
				displayNames([...new Set([...memberOptions(this.project).map((option) => option.id), ...(this.task?.sharedWith || []), this.task?.assignedTo].filter(Boolean))]),
				this.projectsStore.fetchLabels(),
			])
			this.names = names
			this.labels = labels
		},

		/**
		 * @param {{id: string}|null} option The chosen member, or null for nobody.
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
		 */
		onResponsible(option) {
			return this.saveTask(responsiblePatch(this.task, option ? option.id : ''))
		},

		/**
		 * @param {Array<{id: string}>} options The chosen members.
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
		 */
		onShared(options) {
			return this.saveTask(sharedWithPatch(this.task, (options || []).map((option) => option.id)))
		},

		/**
		 * @param {{id: string}|null} option The chosen level.
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-3.1
		 */
		onPriority(option) {
			return this.saveTask(priorityPatch(this.task, option ? option.id : ''))
		},

		/**
		 * @param {Array<{id: string}>} options The chosen labels.
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-4.1
		 */
		onLabels(options) {
			return this.saveTask(labelsPatch(this.task, (options || []).map((option) => option.id)))
		},

		/**
		 * Add a subtask with the typed title under this task, and keep the field for the next one.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-2.1
		 */
		async addSubtask() {
			if (this.newSubtaskTitle.trim() === '' || this.addingSubtask) {
				return
			}
			this.addingSubtask = true
			const created = await this.projectsStore.createTask(newSubtask(this.newSubtaskTitle, this.task, this.projectTasks))
			this.addingSubtask = false
			if (!created) {
				showError(this.t('planninq', 'Could not create the task. Please try again.'))
				return
			}
			this.newSubtaskTitle = ''
			this.projectTasks = [...this.projectTasks, created]
		},

		/**
		 * Add a checklist item with the typed text.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
		 */
		async addItem() {
			const next = addChecklistItem(this.checklist, this.newChecklistText)
			if (next === this.checklist) {
				return
			}
			this.newChecklistText = ''
			await this.saveChecklist(next)
		},

		/**
		 * Drop the dragged checklist item at a position.
		 *
		 * @param {number} index Where it lands.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
		 */
		async dropItem(index) {
			const from = this.checklist.findIndex((item) => item.id === this.dragItem)
			this.dragItem = null
			if (from === -1 || from === index) {
				return
			}
			await this.saveChecklist(this.reordered(from, index))
		},

		/**
		 * @param {object} item A checklist item.
		 * @return {Array<object>} The checklist with it ticked or unticked.
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
		 */
		toggled(item) {
			return toggleChecklistItem(this.checklist, item.id)
		},

		/**
		 * @param {object} item      A checklist item.
		 * @param {number} direction -1 or +1.
		 * @return {Array<object>} The checklist with it moved a step.
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
		 */
		moved(item, direction) {
			return moveChecklistItem(this.checklist, item.id, direction)
		},

		/**
		 * @param {object} item A checklist item.
		 * @return {Array<object>} The checklist without it.
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
		 */
		removed(item) {
			return removeChecklistItem(this.checklist, item.id)
		},

		/**
		 * @param {number} from The dragged item's position.
		 * @param {number} to   Where it lands.
		 * @return {Array<object>} The reordered checklist.
		 * @spec exclude Display helper, the list after a drop.
		 */
		reordered(from, to) {
			const next = [...this.checklist]
			const [item] = next.splice(from, 1)
			next.splice(to, 0, item)
			return next
		},

		/**
		 * Write the whole checklist (the array is the unit, tasks-subtasks-checklist design).
		 *
		 * @param {Array<object>} checklist The new checklist.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
		 */
		saveChecklist(checklist) {
			return this.saveTask({ checklist })
		},

		/**
		 * Copy this task with its checklist and subtasks, then open the copy.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.1
		 */
		async duplicate() {
			this.duplicating = true
			const copy = await this.projectsStore.duplicateTask(this.task, this.subtasks)
			this.duplicating = false
			const copyId = copy?.id ?? copy?.['@self']?.id
			if (!copyId) {
				showError(this.t('planninq', 'Could not copy the task. Please try again.'))
				return
			}
			this.$router.push({ name: 'TaskDetail', params: { id: this.$route.params.id, taskId: copyId } })
		},

		/**
		 * @param {string} status A task status.
		 * @return {string} Its label.
		 * @spec exclude Display helper, the label of a status.
		 */
		statusText(status) {
			const labels = { open: this.t('planninq', 'Open'), in_progress: this.t('planninq', 'In progress'), blocked: this.t('planninq', 'Blocked'), done: this.t('planninq', 'Done'), cancelled: this.t('planninq', 'Cancelled') }
			return labels[status] || status
		},

		/**
		 * PATCH one change from the people, priority or label controls.
		 *
		 * @param {object} patch Only the changed fields; an empty patch does nothing.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
		 */
		async saveTask(patch) {
			if (!this.task || !Object.keys(patch).length) {
				return
			}
			const updated = await this.projectsStore.updateTask(this.task.id, patch)
			if (!updated) {
				showError(this.t('planninq', 'Could not save the task. Please try again.'))
				return
			}
			this.projectsStore.activeTask = { ...this.task, ...patch, ...updated }
		},

		/**
		 * Show the saved task (an edit, or a cancel from the delete dialog).
		 *
		 * @param {object} saved The task as the server returned it.
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-4.1
		 */
		onTaskSaved(saved) {
			this.editing = false
			this.deleting = false
			this.projectsStore.activeTask = { ...this.task, ...saved }
			this.setDocumentTitle()
		},

		/**
		 * Back to the board once the task is deleted.
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-4.1
		 */
		onTaskDeleted() {
			this.deleting = false
			this.projectsStore.activeTask = null
			this.goBack()
		},

		/**
		 * Subscribe to live updates for the task being viewed
		 * (or-object-{uuid}). Events are refetch hints only: the
		 * liveUpdatesPlugin re-runs fetchObject('task', uuid), which lands in
		 * the object store's objects.task cache; the watcher installed here
		 * bridges that fresh data into projectsStore.activeTask so this view
		 * re-renders. Idempotent per task uuid; releases the previous
		 * subscription when another task is opened.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/realtime-updates.md
		 */
		async syncLiveSubscription() {
			const objectStore = useObjectStore()
			if (typeof objectStore.subscribe !== 'function') {
				return
			}
			const uuid = this.taskId
			if (!uuid) {
				this.releaseLiveSubscription()
				return
			}
			const type = 'task'
			const key = `${type}::${uuid}`
			if (this.liveHandle && this.liveKey === key) {
				return
			}
			if (this.livePendingKey === key) {
				// A subscribe for this exact task is already in flight —
				// re-subscribing here would leak the first handle + watcher.
				return
			}
			this.releaseLiveSubscription()
			try {
				// Ensure the 'task' type is registered (with slug hints).
				this.projectsStore._objectStore()
				const epoch = this.liveEpoch
				this.livePendingKey = key
				this.liveKey = key
				const handle = await objectStore.subscribe(type, uuid)
				if (this.livePendingKey === key) {
					this.livePendingKey = ''
				}
				if (this.liveEpoch !== epoch) {
					// Released while awaiting (another task opened, or the
					// component was destroyed) — drop the stale subscription.
					objectStore.unsubscribe(handle)
					return
				}
				this.liveHandle = handle
				// Bridge: event → plugin refetch → objects.task[uuid] cache →
				// projectsStore.activeTask (which this template renders).
				this.liveUnwatch = this.$watch(
					() => objectStore.getObject(type, uuid),
					(fresh) => {
						if (fresh && this.liveKey === key) {
							this.projectsStore.activeTask = fresh
						}
					},
				)
			} catch (e) {
				if (this.livePendingKey === key) {
					this.livePendingKey = ''
				}
				this.liveHandle = null
				this.liveKey = ''
				console.warn('[TaskDetail] live subscription failed:', e?.message ?? e)
			}
		},

		/**
		 * Release the current live object subscription and its cache watcher,
		 * and invalidate any in-flight subscribe (its resolution unsubscribes
		 * itself via the epoch check).
		 *
		 * @spec openspec/specs/realtime-updates.md
		 */
		releaseLiveSubscription() {
			this.liveEpoch += 1
			this.livePendingKey = ''
			if (this.liveUnwatch) {
				this.liveUnwatch()
				this.liveUnwatch = null
			}
			const objectStore = useObjectStore()
			if (this.liveHandle && typeof objectStore.unsubscribe === 'function') {
				objectStore.unsubscribe(this.liveHandle)
			}
			this.liveHandle = null
			this.liveKey = ''
		},

		/**
		 * Navigate back to the project board.
		 *
		 * @spec exclude Router navigation glue; no observable spec behaviour.
		 */
		goBack() {
			const projectId = this.$route.params.id
			if (projectId) {
				this.$router.push({ name: 'ProjectBoard', params: { id: projectId } })
			} else {
				this.$router.push({ name: 'Projects' })
			}
		},

		/**
		 * Sidebar open/close handler. The sidebar is part of the detail layout,
		 * so closing it returns to the board rather than leaving an empty page.
		 *
		 * @param {boolean} open Whether the sidebar is now open.
		 *
		 * @spec exclude UI affordance (close returns to board); no spec behaviour.
		 */
		onSidebarToggle(open) {
			if (!open) {
				this.goBack()
			}
		},

		/**
		 * Format minutes for display.
		 *
		 * @param {number} minutes Whole minutes.
		 * @return {string} Human-readable duration.
		 *
		 * @spec exclude Display glue — wraps formatDuration for the template.
		 */
		formatMinutes(minutes) {
			return formatDuration(minutes)
		},

		/**
		 * Whether the current user may edit/delete a time entry (owner/admin).
		 *
		 * @param {object} entry The time entry.
		 * @return {boolean}
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		canModify(entry) {
			return this.timeEntriesStore.canModify(entry)
		},

		/**
		 * Persist the task estimate parsed from the estimate input.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		async saveEstimate() {
			const minutes = parseDuration(this.estimateInput)
			if (minutes === null) {
				return
			}
			this.savingEstimate = true
			try {
				await this.projectsStore.updateTask(this.taskId, { estimatedDuration: minutes })
				await this.projectsStore.fetchTask(this.taskId)
				this.estimateInput = formatDuration(this.estimateMinutes)
			} finally {
				this.savingEstimate = false
			}
		},

		/**
		 * Open the log-time dialog (new entry, or editing `entry`).
		 *
		 * @param {object|null} entry The entry to edit, or null to create.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		openLogDialog(entry = null) {
			this.editingEntry = entry
			this.dialogOpen = true
		},

		/**
		 * @spec exclude UI glue — closes the log-time dialog.
		 */
		closeDialog() {
			this.dialogOpen = false
			this.editingEntry = null
		},

		/**
		 * Refresh entries after a create/edit and close the dialog.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		async onEntrySaved() {
			this.closeDialog()
			await this.timeEntriesStore.fetchForTask(this.taskId)
		},

		/**
		 * Delete a time entry and refresh the task's total.
		 *
		 * @param {object} entry The entry to delete.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		async deleteEntry(entry) {
			await this.timeEntriesStore.delete(entry.id)
			await this.timeEntriesStore.fetchForTask(this.taskId)
		},
	},
}
</script>

<style scoped>
.task-detail {
	height: 100%;
}

.task-detail__loading {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
}

.task-detail__layout {
	display: flex;
	height: 100%;
}

.task-detail__main {
	flex: 1 1 auto;
	padding: 24px;
	overflow-y: auto;
}

.task-detail__header {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 24px;
}

.task-detail__title {
	margin: 0;
}

.task-detail__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.task-detail__description {
	margin-bottom: 24px;
}

.task-detail__no-description {
	color: var(--color-text-maxcontrast);
}

.task-detail__key {
	margin-inline-end: 8px;
	font-weight: 400;
	color: var(--color-text-maxcontrast);
}

.task-detail__subtasks,
.task-detail__checklist {
	margin-bottom: 24px;
	max-width: 640px;
}

.task-detail__list {
	margin: 0 0 8px;
	padding: 0;
	list-style: none;
}

.task-detail__list li {
	display: flex;
	align-items: center;
	gap: 8px;
}

.task-detail__muted {
	color: var(--color-text-maxcontrast);
	font-weight: 400;
}

.task-detail__controls {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
	gap: 12px 24px;
	max-width: 640px;
	margin-bottom: 24px;
}

.task-detail__fields {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 8px 24px;
	max-width: 640px;
}

.task-detail__fields dt {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.task-detail__fields dd {
	margin: 0;
}

.task-detail__time {
	margin-top: 32px;
	max-width: 640px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.task-detail__section-title {
	margin: 0;
	font-size: 16px;
}

.task-detail__estimate {
	display: flex;
	align-items: flex-start;
	gap: 8px;
}

.task-detail__estimate > :first-child {
	flex: 1 1 auto;
}

.task-detail__progress {
	margin: 0;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.task-detail__progress--over {
	color: var(--color-error);
}

.task-detail__overage {
	font-weight: normal;
}

.task-detail__entries {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.task-detail__entry {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 6px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.task-detail__entry-duration {
	font-weight: 600;
	min-width: 64px;
}

.task-detail__entry-desc {
	flex: 1 1 auto;
	color: var(--color-text-maxcontrast);
}

.task-detail__entry-user {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}
</style>
