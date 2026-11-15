<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<EditDialog
		:name="workflow ? t('planninq', 'Follow {workflow}', { workflow: workflow.title }) : t('planninq', 'Stop following the workflow')"
		:saving="saving"
		:disabled="loading"
		:label="t('planninq', 'Confirm')"
		saveTestid="workflow-move-confirm"
		@close="$emit('close')"
		@save="confirm">
		<div class="workflow-move-dialog__body">
			<NcLoadingIcon v-if="loading" :size="20" />
			<template v-else-if="workflow">
				<p>{{ t('planninq', 'The board gets the columns of this workflow, and keeps them in step with it.') }}</p>
				<p v-if="mapping.moving" data-testid="workflow-move-count">
					{{ t('planninq', 'Tasks that move to {column} because their column is not in this workflow: {count}', { count: mapping.moving, column: mapping.target }) }}
				</p>
				<p v-else data-testid="workflow-move-count">
					{{ t('planninq', 'Every task keeps its column.') }}
				</p>
			</template>
			<p v-else>
				{{ t('planninq', 'The board keeps its columns, and you can edit them again.') }}
			</p>
			<p v-if="submitError" class="workflow-move-dialog__error" role="alert">
				{{ submitError }}
			</p>
		</div>
	</EditDialog>
</template>

<script>
/**
 * WorkflowMoveDialog.
 *
 * Puts a project on a shared workflow, or takes it off one. Before moving
 * onto a workflow it says how many tasks move to the first column because
 * their column is not in the workflow.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.5
 */
import { NcLoadingIcon } from '@nextcloud/vue'
import EditDialog from './EditDialog.vue'
import { useProjectsStore } from '../store/projects.js'
import { workflowMapping } from '../utils/workflows.js'

export default {
	name: 'WorkflowMoveDialog',

	components: {
		EditDialog,
		NcLoadingIcon,
	},

	props: {
		/** The project. */
		project: { type: Object, required: true },

		/** The workflow to follow; none takes the project off its workflow. */
		workflow: { type: Object, default: null },
	},

	emits: ['close', 'moved'],

	data() {
		return {
			loading: !!this.workflow,
			mapping: { moving: 0, target: '' },
			saving: false,
			submitError: '',
		}
	},

	/**
	 * Count the tasks the move would put in the first column.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.5
	 */
	async mounted() {
		if (!this.workflow) {
			return
		}
		const store = useProjectsStore()
		const [columns, tasks] = await Promise.all([store.fetchColumns(this.project.id), store.fetchTasks(this.project.id)])
		this.mapping = workflowMapping(columns || [], tasks || [], this.workflow)
		this.loading = false
	},

	methods: {
		/**
		 * Store the project's workflow; the server rebuilds its columns.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.5
		 */
		async confirm() {
			this.saving = true
			this.submitError = ''
			const saved = await useProjectsStore().patchProject(this.project.id, { workflow: this.workflow?.id || null }).catch(() => null)
			this.saving = false
			if (!saved) {
				this.submitError = this.t('planninq', 'The workflow could not be changed. Please try again.')
				return
			}
			this.$emit('moved', saved)
		},
	},
}
</script>

<style scoped>
.workflow-move-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-width: min(420px, 90vw);
}

.workflow-move-dialog__error {
	color: var(--color-error-text);
}
</style>
