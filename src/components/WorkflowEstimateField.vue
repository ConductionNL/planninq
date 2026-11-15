<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div v-if="scale" class="workflow-estimate-field">
		<NcSelect
			:modelValue="picked"
			:options="choices"
			:inputLabel="t('planninq', 'Estimate')"
			:placeholder="t('planninq', 'No estimate')"
			:disabled="saving"
			label="label"
			data-testid="workflow-estimate"
			@update:modelValue="save" />
		<p class="workflow-estimate-field__hint">
			{{ t('planninq', 'The {workflow} workflow sets this scale.', { workflow: workflow.title }) }}
		</p>
	</div>
	<slot v-else />
</template>

<script>
/**
 * WorkflowEstimateField — the estimate of a task whose project follows a
 * workflow with story points or hours: only the workflow's values are
 * offered. On any other project it shows its default slot, the free estimate.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
 */
import { NcSelect } from '@nextcloud/vue'
import projectWorkflow from '../mixins/projectWorkflow.js'
import { useProjectsStore } from '../store/projects.js'
import { estimateChoices, estimatePatch, storedScale } from '../utils/workflows.js'

export default {
	name: 'WorkflowEstimateField',

	components: {
		NcSelect,
	},

	mixins: [projectWorkflow],

	props: {
		/** The task's project; its workflow sets the scale. */
		project: { type: Object, default: null },

		/** The task. */
		task: { type: Object, required: true },
	},

	data() {
		return {
			saving: false,
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: the workflow's stored scale, or null.
		 */
		scale() {
			return storedScale(this.workflow)
		},

		/**
		 * @spec exclude Trivial getter: the workflow's values as options.
		 */
		choices() {
			return estimateChoices(this.workflow)
		},

		/**
		 * The option of the task's current estimate, or null.
		 *
		 * @return {object|null}
		 *
		 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
		 */
		picked() {
			const field = Object.keys(estimatePatch(this.workflow, 0))[0]
			const value = Number(this.task[field]) || 0
			return this.choices.find((choice) => estimatePatch(this.workflow, choice.id)[field] === value) || null
		},
	},

	methods: {
		/**
		 * Store the picked estimate, or clear it, and read the task again.
		 *
		 * @param {{id: number}|null} option The picked option.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
		 */
		async save(option) {
			this.saving = true
			const store = useProjectsStore()
			try {
				await store.updateTask(this.task.id, estimatePatch(this.workflow, option ? option.id : null))
				await store.fetchTask(this.task.id)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.workflow-estimate-field__hint {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
}
</style>
