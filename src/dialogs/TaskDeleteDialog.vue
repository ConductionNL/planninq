<template>
	<NcDialog :name="t('planninq', 'Delete task')" @closing="$emit('close')">
		<template #default>
			<div class="task-delete-dialog__body">
				<p v-if="reason === 'has-time'" data-testid="task-delete-has-time">
					{{ t('planninq', '"{title}" has logged time, so it cannot be deleted. You can cancel it instead: the time entries stay.', { title: task.title }) }}
				</p>
				<p v-else>
					{{ t('planninq', 'Delete "{title}"? Its links to other tasks are removed too. This cannot be undone.', { title: task.title }) }}
				</p>
				<div v-if="submitError" class="task-delete-dialog__error" role="alert">
					{{ submitError }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="busy" @click="$emit('close')">
				{{ t('planninq', 'Keep task') }}
			</NcButton>
			<NcButton
				v-if="reason === 'has-time'"
				variant="primary"
				:disabled="busy"
				data-testid="task-delete-cancel-task"
				@click="cancelTask">
				{{ t('planninq', 'Cancel task') }}
			</NcButton>
			<NcButton
				v-else
				variant="error"
				:disabled="busy"
				data-testid="task-delete-confirm"
				@click="confirm">
				<template v-if="busy" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Delete task') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * TaskDeleteDialog.
 *
 * Confirms deleting one task. A task with logged time is not deleted: the
 * dialog says so and offers "Cancel task", which sets the status to
 * `cancelled` and keeps the time entries.
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-2.2
 */
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'

export default {
	name: 'TaskDeleteDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
	},

	props: {
		/** The task to delete. */
		task: {
			type: Object,
			required: true,
		},

		/** Whether the task has logged time; the dialog then offers to cancel it. */
		hasTime: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'deleted', 'cancelled'],

	data() {
		return {
			reason: this.hasTime ? 'has-time' : '',
			busy: false,
			submitError: '',
		}
	},

	methods: {
		/**
		 * Delete the task, or switch to the logged-time branch when the server says so.
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-2.2
		 */
		async confirm() {
			this.busy = true
			this.submitError = ''
			const result = await useProjectsStore().deleteTask(this.task.id)
			this.busy = false
			if (result.deleted) {
				this.$emit('deleted', this.task)
				return
			}
			if (result.reason === 'has-time') {
				this.reason = 'has-time'
				return
			}
			this.submitError = result.reason === 'not-allowed'
				? this.t('planninq', 'Only the reporter, the project owner or an admin can delete this task.')
				: this.t('planninq', 'Could not delete the task. Please try again.')
		},

		/**
		 * Cancel the task instead of deleting it; its time entries stay.
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-2.2
		 */
		async cancelTask() {
			this.busy = true
			this.submitError = ''
			const updated = await useProjectsStore().updateTaskStatus(this.task.id, 'cancelled')
			this.busy = false
			if (!updated) {
				this.submitError = this.t('planninq', 'Could not cancel the task. Please try again.')
				return
			}
			this.$emit('cancelled', updated)
		},
	},
}
</script>

<style scoped>
.task-delete-dialog__body {
	min-width: min(420px, 90vw);
}

.task-delete-dialog__error {
	color: var(--color-error-text);
}
</style>
