<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<!-- Shared workflows (projects-templates-shared-workflow): columns and an estimate scale many projects follow -->
	<CnSettingsSection
		:name="t('planninq', 'Workflows')"
		:description="t('planninq', 'A workflow gives every project that follows it the same columns and estimate. A change here reaches all of them.')"
		data-testid="workflow-settings">
		<NcLoadingIcon v-if="loading" :size="20" />
		<p v-else-if="!workflows.length" class="workflow-settings__hint">
			{{ t('planninq', 'No workflows yet.') }}
		</p>
		<ul v-else class="workflow-settings__list">
			<li v-for="workflow in workflows" :key="workflow.id" data-testid="workflow-row">
				<span class="workflow-settings__title">{{ workflow.title }}</span>
				<span class="workflow-settings__columns">{{ columnTitles(workflow) }}</span>
				<NcButton variant="tertiary" @click="editing = workflow">
					{{ t('planninq', 'Edit') }}
				</NcButton>
				<NcButton
					variant="tertiary"
					:aria-label="t('planninq', 'Delete {title}', { title: workflow.title })"
					@click="remove(workflow)">
					{{ t('planninq', 'Delete') }}
				</NcButton>
			</li>
		</ul>
		<p v-if="error" class="workflow-settings__error" role="alert">
			{{ error }}
		</p>
		<NcButton variant="secondary" data-testid="workflow-add" @click="editing = {}">
			{{ t('planninq', 'Add workflow') }}
		</NcButton>

		<WorkflowEditDialog
			v-if="editing"
			:workflow="editing.id ? editing : null"
			@close="editing = null"
			@saved="onSaved" />
	</CnSettingsSection>
</template>

<script>
/**
 * WorkflowSettings — the workflows section of the planninq admin page: lists
 * the shared workflows, adds and edits them, and deletes one no project follows.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
 */
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import WorkflowEditDialog from '../dialogs/WorkflowEditDialog.vue'
import { deleteWorkflow, listWorkflows } from '../api/workflows.js'

export default {
	name: 'WorkflowSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcLoadingIcon,
		WorkflowEditDialog,
	},

	data() {
		return {
			workflows: [],
			loading: true,
			editing: null,
			error: '',
		}
	},

	/**
	 * @spec exclude Lifecycle glue: reads the workflows.
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		/**
		 * Read the workflows; none when they cannot be read.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
		 */
		async load() {
			this.loading = true
			try {
				this.workflows = await listWorkflows()
			} catch {
				this.workflows = []
				this.error = this.t('planninq', 'The workflows could not be loaded.')
			}
			this.loading = false
		},

		/**
		 * @param {object} workflow The workflow.
		 * @return {string}
		 * @spec exclude Trivial formatter: the workflow's column titles in order.
		 */
		columnTitles(workflow) {
			return (workflow.columns || []).map((column) => column.title).join(' → ')
		},

		/**
		 * Close the dialog and read the workflows again.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
		 */
		async onSaved() {
			this.editing = null
			this.error = ''
			await this.load()
		},

		/**
		 * Delete a workflow, or show why it was refused (projects still follow it).
		 *
		 * @param {object} workflow The workflow.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
		 */
		async remove(workflow) {
			this.error = ''
			try {
				await deleteWorkflow(workflow.id)
				this.workflows = this.workflows.filter((other) => other.id !== workflow.id)
			} catch (error) {
				this.error = error.message === 'workflow-delete-error' ? this.t('planninq', 'The workflow could not be deleted. Please try again.') : error.message
			}
		},
	},
}
</script>

<style scoped>
.workflow-settings__list {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin: 0 0 12px;
	padding: 0;
	list-style: none;
}

.workflow-settings__list li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.workflow-settings__title {
	font-weight: 600;
}

.workflow-settings__columns,
.workflow-settings__hint {
	color: var(--color-text-maxcontrast);
}

.workflow-settings__error {
	color: var(--color-error-text);
}
</style>
