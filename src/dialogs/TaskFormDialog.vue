<template>
	<NcDialog :name="task ? t('planninq', 'Edit task') : t('planninq', 'New task')" @closing="$emit('close')">
		<template #default>
			<div class="task-form-dialog__body">
				<NcTextField
					v-model="draft.title"
					:label="t('planninq', 'Title')"
					data-testid="task-form-title"
					required />

				<div class="task-form-dialog__description">
					<div class="task-form-dialog__mode" role="group" :aria-label="t('planninq', 'Description')">
						<NcButton
							:variant="preview ? 'tertiary' : 'secondary'"
							:aria-pressed="!preview"
							@click="preview = false">
							{{ t('planninq', 'Write') }}
						</NcButton>
						<NcButton
							:variant="preview ? 'secondary' : 'tertiary'"
							:aria-pressed="preview"
							data-testid="task-form-preview"
							@click="preview = true">
							{{ t('planninq', 'Preview') }}
						</NcButton>
					</div>
					<NcTextArea
						v-if="!preview"
						v-model="draft.description"
						:label="t('planninq', 'Description')"
						:helperText="t('planninq', 'Markdown works here: headings, lists and links.')"
						data-testid="task-form-description"
						rows="6" />
					<div v-else class="task-form-dialog__preview" data-testid="task-form-preview-body">
						<NcRichText v-if="draft.description" :text="draft.description" :useMarkdown="true" />
						<p v-else class="task-form-dialog__empty">
							{{ t('planninq', 'No description yet') }}
						</p>
					</div>
				</div>

				<NcSelect
					v-if="task"
					v-model="statusOption"
					:options="statusOptions"
					:inputLabel="t('planninq', 'Status')"
					:clearable="false"
					label="label"
					data-testid="task-form-status" />
				<NcSelect
					v-model="priorityOption"
					:options="priorityOptions"
					:inputLabel="t('planninq', 'Priority')"
					:clearable="false"
					label="label"
					data-testid="task-form-priority" />

				<div class="task-form-dialog__dates">
					<NcDateTimePickerNative
						v-model="startDateValue"
						type="date"
						:label="t('planninq', 'Start date')"
						data-testid="task-form-start-date" />
					<NcDateTimePickerNative
						v-model="dueDateValue"
						type="date"
						:label="t('planninq', 'Due date')"
						data-testid="task-form-due-date" />
				</div>
				<p v-if="dateError"
					class="task-form-dialog__error"
					role="alert"
					data-testid="task-form-date-error">
					{{ dateError }}
				</p>

				<template v-if="project">
					<NcSelect
						v-model="responsibleOption"
						:options="peopleOptions"
						:inputLabel="t('planninq', 'Responsible')"
						label="label"
						data-testid="task-form-responsible" />
					<NcSelect
						v-model="sharedOptions"
						:options="peopleOptions.filter((option) => option.id !== draft.assignedTo)"
						:inputLabel="t('planninq', 'Also working on this')"
						:multiple="true"
						label="label"
						data-testid="task-form-shared-with" />
				</template>

				<div v-if="submitError" class="task-form-dialog__error" role="alert">
					{{ submitError }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="saving" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || draft.title.trim() === '' || dateError !== ''"
				data-testid="task-form-save"
				@click="save">
				<template v-if="saving" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * TaskFormDialog.
 *
 * Creates a task, or edits one when `task` is given. A new task lands at the
 * bottom of `column` (the board's first lane when opened from the header),
 * or in the backlog without one. An edit PATCHes only the changed fields
 * through the projects store, never a PUT that would null the rest.
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-2.1
 */
import { NcButton, NcDateTimePickerNative, NcDialog, NcLoadingIcon, NcRichText, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { fromPickerDate, toPickerDate, validateTaskDates } from '../utils/taskDates.js'
import { editPatch, newLaneTask } from '../utils/taskEditing.js'
import { memberOptions } from '../utils/taskPeople.js'
import { displayNames } from '../utils/userNames.js'

export default {
	name: 'TaskFormDialog',

	components: {
		NcButton,
		NcDateTimePickerNative,
		NcDialog,
		NcLoadingIcon,
		NcRichText,
		NcSelect,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The task to edit; leave out to create one. */
		task: {
			type: Object,
			default: null,
		},

		/** The project a new task goes into. */
		projectId: {
			type: String,
			default: '',
		},

		/** The lane a new task goes into, or null for the backlog. */
		column: {
			type: Object,
			default: null,
		},

		/** The cards already in that lane. */
		laneTasks: {
			type: Array,
			default: () => [],
		},

		/** The task's project; when given, the dialog offers its members as people. */
		project: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'saved'],

	data() {
		const draft = {
			title: this.task?.title ?? '',
			description: this.task?.description ?? '',
			status: this.task?.status ?? 'open',
			priority: this.task?.priority ?? 'normal',
			startDate: this.task?.startDate ?? '',
			dueDate: this.task?.dueDate ?? '',
		}
		if (this.project) {
			draft.assignedTo = this.task?.assignedTo ?? ''
			draft.sharedWith = [...(this.task?.sharedWith ?? [])]
		}
		return {
			draft,
			names: {},

			preview: false,
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * The inline message when the start date is after the due date.
		 *
		 * @spec openspec/changes/tasks-dates/tasks.md#task-3.1
		 */
		dateError() {
			return validateTaskDates(this.draft.startDate, this.draft.dueDate) === 'startAfterDue'
				? this.t('planninq', 'The start date is after the due date.')
				: ''
		},

		startDateValue: {
			/**
			 * @spec exclude Display helper, the start date as a picker value.
			 */
			get() {
				return toPickerDate(this.draft.startDate)
			},

			/**
			 * @param {Date|null} value The picked date.
			 * @spec exclude Display helper, stores the picked day.
			 */
			set(value) {
				this.draft.startDate = fromPickerDate(value)
			},
		},

		dueDateValue: {
			/**
			 * @spec exclude Display helper, the due date as a picker value.
			 */
			get() {
				return toPickerDate(this.draft.dueDate)
			},

			/**
			 * @param {Date|null} value The picked date.
			 * @spec exclude Display helper, stores the picked day.
			 */
			set(value) {
				this.draft.dueDate = fromPickerDate(value)
			},
		},

		/**
		 * The project's members as picker options.
		 *
		 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
		 */
		peopleOptions() {
			return memberOptions(this.project, this.names)
		},

		responsibleOption: {
			/**
			 * @spec exclude Display helper, the selected responsible person.
			 */
			get() {
				const uid = this.draft.assignedTo
				return uid ? { id: uid, label: this.names[uid] || uid } : null
			},

			/**
			 * @param {{id: string}|null} option The chosen member.
			 * @spec exclude Display helper, stores the responsible person.
			 */
			set(option) {
				this.draft.assignedTo = option?.id || ''
				this.draft.sharedWith = this.draft.sharedWith.filter((uid) => uid !== this.draft.assignedTo)
			},
		},

		sharedOptions: {
			/**
			 * @spec exclude Display helper, the people the task is shared with.
			 */
			get() {
				return this.draft.sharedWith.map((uid) => ({ id: uid, label: this.names[uid] || uid }))
			},

			/**
			 * @param {Array<{id: string}>} options The chosen members.
			 * @spec exclude Display helper, stores the people.
			 */
			set(options) {
				this.draft.sharedWith = (options || []).map((option) => option.id)
			},
		},

		/**
		 * @spec exclude Display helper, the status choices.
		 */
		statusOptions() {
			return [
				{ id: 'open', label: this.t('planninq', 'Open') },
				{ id: 'in_progress', label: this.t('planninq', 'In progress') },
				{ id: 'blocked', label: this.t('planninq', 'Blocked') },
				{ id: 'done', label: this.t('planninq', 'Done') },
				{ id: 'cancelled', label: this.t('planninq', 'Cancelled') },
			]
		},

		/**
		 * @spec exclude Display helper, the priority choices.
		 */
		priorityOptions() {
			return [
				{ id: 'urgent', label: this.t('planninq', 'Urgent') },
				{ id: 'high', label: this.t('planninq', 'High') },
				{ id: 'normal', label: this.t('planninq', 'Normal') },
				{ id: 'low', label: this.t('planninq', 'Low') },
			]
		},

		statusOption: {
			/**
			 * @spec exclude Display helper, the selected status.
			 */
			get() {
				return this.statusOptions.find((option) => option.id === this.draft.status) || this.statusOptions[0]
			},

			/**
			 * @param {{id: string}} option The chosen option.
			 * @spec exclude Display helper, stores the chosen status.
			 */
			set(option) {
				this.draft.status = option?.id || 'open'
			},
		},

		priorityOption: {
			/**
			 * @spec exclude Display helper, the selected priority.
			 */
			get() {
				return this.priorityOptions.find((option) => option.id === this.draft.priority) || this.priorityOptions[2]
			},

			/**
			 * @param {{id: string}} option The chosen option.
			 * @spec exclude Display helper, stores the chosen priority.
			 */
			set(option) {
				this.draft.priority = option?.id || 'normal'
			},
		},
	},

	/**
	 * Look up the display names of the project's members for the pickers.
	 *
	 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
	 */
	async mounted() {
		if (this.project) {
			this.names = await displayNames(memberOptions(this.project).map((option) => option.id))
		}
	},

	methods: {
		/**
		 * Create the task, or PATCH the changed fields of the edited one.
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-2.1
		 */
		async save() {
			if (this.dateError) {
				return
			}
			this.saving = true
			this.submitError = ''
			const store = useProjectsStore()
			let saved
			if (this.task) {
				const patch = editPatch(this.task, this.draft)
				saved = Object.keys(patch).length ? await store.updateTask(this.task.id, patch) : this.task
			} else {
				saved = await store.createTask(newLaneTask(this.draft, this.projectId, this.column, this.laneTasks))
			}
			this.saving = false
			if (!saved) {
				this.submitError = this.task
					? this.t('planninq', 'Could not save the task. Please try again.')
					: this.t('planninq', 'Could not create the task. Please try again.')
				return
			}
			this.$emit('saved', saved)
		},
	},
}
</script>

<style scoped>
.task-form-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(520px, 90vw);
}

.task-form-dialog__mode {
	display: flex;
	gap: 4px;
	margin-bottom: 4px;
}

.task-form-dialog__preview {
	min-height: 120px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.task-form-dialog__empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.task-form-dialog__dates {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

.task-form-dialog__error {
	color: var(--color-error-text);
}
</style>
