<template>
	<NcDialog
		:name="t('planninq', 'Delete view')"
		@closing="$emit('close')">
		<template #default>
			<p>{{ t('planninq', 'Delete "{title}"? The projects and their tasks stay as they are.', { title: view.title }) }}</p>
			<div v-if="submitError" class="projects-view-delete-dialog__error" role="alert">
				{{ submitError }}
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="deleting" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="error"
				:disabled="deleting"
				data-testid="view-delete-confirm"
				@click="remove">
				{{ t('planninq', 'Delete') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ProjectsViewDeleteDialog: the owner deletes a cross-project view. Only the
 * view goes; no project or task changes.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
 */
import { NcButton, NcDialog } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'

export default {
	name: 'ProjectsViewDeleteDialog',

	components: { NcButton, NcDialog },

	props: {
		/** The view to delete. */
		view: {
			type: Object,
			required: true,
		},
	},

	emits: ['close', 'deleted'],

	data() {
		return {
			deleting: false,
			submitError: '',
		}
	},

	methods: {
		/**
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		async remove() {
			this.deleting = true
			const gone = await useProjectsStore().deleteBoardView(this.view.id)
			this.deleting = false
			if (!gone) {
				this.submitError = this.t('planninq', 'Could not delete the view. Please try again.')
				return
			}
			this.$emit('deleted')
		},
	},
}
</script>

<style scoped>
.projects-view-delete-dialog__error {
	color: var(--color-error-text);
}
</style>
