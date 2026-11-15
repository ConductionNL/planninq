<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<EditDialog
		:name="workflow ? t('planninq', 'Edit workflow') : t('planninq', 'Add workflow')"
		:saving="saving"
		:disabled="!form.title.trim()"
		saveTestid="workflow-save"
		size="normal"
		@close="$emit('close')"
		@save="save">
		<div class="workflow-edit-dialog__body">
			<NcTextField
				v-model="form.title"
				:label="t('planninq', 'Name')"
				data-testid="workflow-title"
				required />
			<NcTextArea
				v-model="form.description"
				:label="t('planninq', 'Description')"
				rows="2" />

			<fieldset class="workflow-edit-dialog__columns">
				<legend>{{ t('planninq', 'Columns') }}</legend>
				<ol>
					<li v-for="(column, index) in form.columns" :key="column.rowId" data-testid="workflow-column">
						<NcTextField
							v-model="column.title"
							:label="t('planninq', 'Column {number}', { number: index + 1 })" />
						<NcCheckboxRadioSwitch
							:modelValue="column.type === 'done'"
							@update:modelValue="column.type = $event ? 'done' : 'active'">
							{{ t('planninq', 'Done column') }}
						</NcCheckboxRadioSwitch>
						<NcButton
							variant="tertiary"
							:disabled="index === 0"
							:aria-label="t('planninq', 'Move {title} up', { title: column.title || index + 1 })"
							@click="moveColumn(index, -1)">
							↑
						</NcButton>
						<NcButton
							variant="tertiary"
							:disabled="index === form.columns.length - 1"
							:aria-label="t('planninq', 'Move {title} down', { title: column.title || index + 1 })"
							@click="moveColumn(index, 1)">
							↓
						</NcButton>
						<NcButton
							variant="tertiary"
							:aria-label="t('planninq', 'Remove {name}', { name: column.title || index + 1 })"
							@click="form.columns.splice(index, 1)">
							{{ t('planninq', 'Remove') }}
						</NcButton>
					</li>
				</ol>
				<NcButton data-testid="workflow-add-column" @click="addColumn">
					{{ t('planninq', 'Add column') }}
				</NcButton>
				<p class="workflow-edit-dialog__hint">
					{{ t('planninq', 'A removed column\'s tasks move to the first column of each project.') }}
				</p>
			</fieldset>

			<NcSelect
				v-model="scale"
				:options="scaleOptions"
				:clearable="false"
				:inputLabel="t('planninq', 'Estimate')"
				label="label"
				data-testid="workflow-estimate-scale" />
			<NcTextField
				v-if="form.estimateScale !== 'none'"
				v-model="form.estimateValues"
				:label="t('planninq', 'Allowed values')"
				:helperText="t('planninq', 'Separate the values with commas, for example 1, 2, 3, 5, 8.')"
				data-testid="workflow-estimate-values" />

			<p v-if="submitError" class="workflow-edit-dialog__error" role="alert">
				{{ submitError }}
			</p>
		</div>
	</EditDialog>
</template>

<script>
/**
 * WorkflowEditDialog.
 *
 * Adds or edits a shared workflow: its name, its ordered columns and the
 * estimate scale its tasks use. Saving reaches every project on the workflow.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
 */
import { NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import EditDialog from './EditDialog.vue'
import { saveWorkflow } from '../api/workflows.js'
import { ESTIMATE_SCALES, workflowBody } from '../utils/workflows.js'

let nextRow = 0

/**
 * A column row of the form, with a key for the list.
 *
 * @param {object} column The stored column, or nothing for a new one.
 * @return {object}
 */
function columnRow(column = {}) {
	nextRow++
	return { rowId: nextRow, key: column.key || '', title: column.title || '', type: column.type === 'done' ? 'done' : 'active' }
}

export default {
	name: 'WorkflowEditDialog',

	components: {
		EditDialog,
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The workflow to edit; none adds one. */
		workflow: { type: Object, default: null },
	},

	emits: ['close', 'saved'],

	data() {
		const workflow = this.workflow || {}
		return {
			form: {
				title: workflow.title || '',
				description: workflow.description || '',
				columns: (workflow.columns || []).map(columnRow),
				estimateScale: workflow.estimateScale || 'none',
				estimateValues: (workflow.estimateValues || []).join(', '),
			},

			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: the estimate scales, labelled.
		 */
		scaleOptions() {
			const labels = {
				none: this.t('planninq', 'No estimate'),
				hours: this.t('planninq', 'Hours'),
				storyPoints: this.t('planninq', 'Story points'),
				tshirt: this.t('planninq', 'T-shirt sizes'),
			}
			return ESTIMATE_SCALES.map((id) => ({ id, label: labels[id] }))
		},

		scale: {
			/**
			 * @spec exclude Trivial getter: the picked scale as a select option.
			 */
			get() {
				return this.scaleOptions.find((option) => option.id === this.form.estimateScale)
			},

			/**
			 * @param {{id: string}} option The picked option.
			 * @spec exclude Trivial setter: stores the picked scale.
			 */
			set(option) {
				this.form.estimateScale = option.id
			},
		},
	},

	methods: {
		/**
		 * @spec exclude Trivial form edit: appends an empty column.
		 */
		addColumn() {
			this.form.columns.push(columnRow())
		},

		/**
		 * Move a column one place up or down.
		 *
		 * @param {number} index The column's place.
		 * @param {number} step -1 for up, 1 for down.
		 *
		 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
		 */
		moveColumn(index, step) {
			const [column] = this.form.columns.splice(index, 1)
			this.form.columns.splice(index + step, 0, column)
		},

		/**
		 * Store the workflow; on success emit it, else show why.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			try {
				this.$emit('saved', await saveWorkflow({ id: this.workflow?.id, ...workflowBody(this.form) }))
			} catch (error) {
				this.submitError = error.message === 'workflow-save-error' ? this.t('planninq', 'The workflow could not be saved. Please try again.') : error.message
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.workflow-edit-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(520px, 90vw);
}

.workflow-edit-dialog__columns {
	border: none;
	padding: 0;
	margin: 0;
}

.workflow-edit-dialog__columns ol {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin: 0 0 8px;
	padding: 0;
	list-style: none;
}

.workflow-edit-dialog__columns li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
}

.workflow-edit-dialog__hint {
	color: var(--color-text-maxcontrast);
}

.workflow-edit-dialog__error {
	color: var(--color-error-text);
}
</style>
