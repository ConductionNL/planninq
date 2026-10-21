<template>
	<NcDialog :name="t('planninq', 'Add action')" @closing="$emit('close')">
		<template #default>
			<div class="log-action-dialog__body">
				<p class="log-action-dialog__source">
					{{ t('planninq', 'From: {title}', { title: entry.title }) }}
				</p>
				<NcTextField
					v-model="title"
					:label="t('planninq', 'What needs to happen')"
					data-testid="log-action-title"
					required />
				<NcSelect
					v-model="assignee"
					:options="people"
					:inputLabel="t('planninq', 'Assigned to')"
					label="label"
					data-testid="log-action-assignee" />
				<NcTextField
					v-model="dueDate"
					type="date"
					:label="t('planninq', 'Due date')"
					data-testid="log-action-due" />
				<div v-if="submitError" class="log-action-dialog__error" role="alert">
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
				:disabled="saving || title.trim() === ''"
				data-testid="log-action-save"
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
 * LogActionDialog.
 *
 * Turns a log entry's outcome into an action: an ordinary task in the same
 * project with `issueType: 'action'`, placed on the board's first column,
 * and linked from the entry's `actions`.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.3
 */
import { NcButton, NcDialog, NcLoadingIcon, NcSelect, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { newActionTask } from '../utils/projectOverview.js'

export default {
	name: 'LogActionDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
		NcTextField,
	},

	props: {
		/** The log entry the action comes from. */
		entry: {
			type: Object,
			required: true,
		},

		/** The project. */
		projectId: {
			type: String,
			required: true,
		},

		/** The people on the project, as `{ id, label }`. */
		people: {
			type: Array,
			default: () => [],
		},

		/** The project's board columns. */
		columns: {
			type: Array,
			default: () => [],
		},

		/** The project's tasks, to place the action at the bottom of its lane. */
		tasks: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'saved'],

	data() {
		return {
			title: '',
			assignee: null,
			dueDate: '',
			saving: false,
			submitError: '',
		}
	},

	methods: {
		/**
		 * Create the task, then link it from the entry.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.3
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			const store = useProjectsStore()
			const task = await store.createTask(newActionTask({
				title: this.title,
				assignedTo: this.assignee?.id,
				dueDate: this.dueDate,
			}, this.projectId, this.columns, this.tasks))
			const taskId = task?.id ?? task?.uuid ?? task?.['@self']?.id
			if (!taskId) {
				this.saving = false
				this.submitError = this.t('planninq', 'Could not create the action. Please try again.')
				return
			}
			const entryId = this.entry.id ?? this.entry['@self']?.id
			const entry = await store.saveLogEntry({ id: entryId, actions: [...(this.entry.actions || []), taskId] })
			this.saving = false
			if (!entry) {
				this.submitError = this.t('planninq', 'The action was created but could not be linked to the entry.')
				return
			}
			this.$emit('saved', { task, entry })
		},
	},
}
</script>

<style scoped>
.log-action-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(420px, 90vw);
}

.log-action-dialog__source {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.log-action-dialog__error {
	color: var(--color-error-text);
}
</style>
