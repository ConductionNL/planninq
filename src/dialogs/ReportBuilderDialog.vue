<template>
	<NcDialog
		v-model:open="open"
		:name="report.id ? t('planninq', 'Edit report') : t('planninq', 'New report')"
		:noClose="saving"
		size="normal"
		@close="$emit('close')">
		<div class="report-builder">
			<NcTextField
				v-model="draft.title"
				:label="t('planninq', 'Name')"
				data-testid="report-title" />
			<NcSelect
				v-model="draft.projects"
				:options="projectOptions"
				:reduce="(option) => option.id"
				label="label"
				multiple
				:inputLabel="t('planninq', 'Projects')"
				data-testid="report-projects" />
			<fieldset class="report-builder__filters">
				<legend>{{ t('planninq', 'Only tasks where') }}</legend>
				<NcSelect
					v-model="draft.filters.status"
					:options="statusOptions"
					:reduce="(option) => option.id"
					label="label"
					:inputLabel="t('planninq', 'Status')"
					data-testid="report-filter-status" />
				<NcSelect
					v-model="draft.filters.priority"
					:options="priorityOptions"
					:reduce="(option) => option.id"
					label="label"
					:inputLabel="t('planninq', 'Priority')" />
			</fieldset>
			<NcSelect
				v-model="draft.groupBy"
				:options="groupOptions"
				:reduce="(option) => option.id"
				label="label"
				:clearable="false"
				:inputLabel="t('planninq', 'Group by')"
				data-testid="report-group-by" />
			<NcSelect
				v-model="draft.metric"
				:options="metricOptions"
				:reduce="(option) => option.id"
				label="label"
				:clearable="false"
				:inputLabel="t('planninq', 'Measure')" />
			<div class="report-builder__displays" role="radiogroup" :aria-label="t('planninq', 'Display')">
				<NcCheckboxRadioSwitch
					v-for="option in displayOptions"
					:key="option.id"
					v-model="draft.display"
					type="radio"
					:value="option.id"
					name="report-display"
					:data-testid="`report-display-${option.id}`">
					{{ option.label }}
				</NcCheckboxRadioSwitch>
			</div>
			<NcCheckboxRadioSwitch
				:modelValue="draft.shared === 'readers'"
				data-testid="report-shared"
				@update:modelValue="draft.shared = $event ? 'readers' : 'private'">
				{{ t('planninq', 'Share with everyone who can read its projects') }}
			</NcCheckboxRadioSwitch>
			<p v-if="error" class="report-builder__error" role="alert">
				{{ error }}
			</p>
		</div>

		<template #actions>
			<NcButton :disabled="saving" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || !valid"
				data-testid="report-save"
				@click="save">
				{{ t('planninq', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcSelect, NcTextField } from '@nextcloud/vue'
import { saveReport } from '../api/reports.js'
import { equalityFilters, REPORT_DISPLAYS, REPORT_GROUP_BY } from '../utils/reportBuilder.js'

/**
 * Build or edit a report: projects, equality filters, a grouping, a count
 * or a sum, and a display. Only equality filters are offered, the ones
 * OpenRegister's aggregation applies.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
 */
export default {
	name: 'ReportBuilderDialog',
	components: { NcButton, NcCheckboxRadioSwitch, NcDialog, NcSelect, NcTextField },
	props: {
		/** The report to edit; empty for a new one. */
		report: { type: Object, default: () => ({}) },
		/** The projects the user can pick from: id, title. */
		projects: { type: Array, default: () => [] },
	},

	emits: ['close', 'saved'],
	data() {
		return {
			open: true,
			saving: false,
			error: '',
			draft: {
				title: this.report.title || '',
				projects: [...(this.report.projects || [])],
				filters: { status: null, priority: null, ...(this.report.filters || {}) },
				groupBy: this.report.groupBy || 'assignedTo',
				metric: this.report.metric || 'count',
				display: this.report.display || 'bar',
				shared: this.report.shared || 'private',
			},
		}
	},

	computed: {
		/**
		 * @return {Array<object>} Project options.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
		 */
		projectOptions() {
			return this.projects.map((project) => ({ id: project.id, label: project.title }))
		},

		/**
		 * @return {Array<object>} Status options.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
		 */
		statusOptions() {
			return [
				{ id: 'open', label: this.t('planninq', 'Open') },
				{ id: 'in_progress', label: this.t('planninq', 'In progress') },
				{ id: 'blocked', label: this.t('planninq', 'Blocked') },
				{ id: 'done', label: this.t('planninq', 'Done') },
			]
		},

		/**
		 * @return {Array<object>} Priority options.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
		 */
		priorityOptions() {
			return [
				{ id: 'low', label: this.t('planninq', 'Low') },
				{ id: 'normal', label: this.t('planninq', 'Normal') },
				{ id: 'high', label: this.t('planninq', 'High') },
				{ id: 'urgent', label: this.t('planninq', 'Urgent') },
			]
		},

		/**
		 * @return {Array<object>} Grouping options.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
		 */
		groupOptions() {
			const labels = {
				status: this.t('planninq', 'Status'),
				priority: this.t('planninq', 'Priority'),
				assignedTo: this.t('planninq', 'Assignee'),
				labels: this.t('planninq', 'Label'),
				project: this.t('planninq', 'Project'),
				column: this.t('planninq', 'Column'),
			}
			return REPORT_GROUP_BY.map((id) => ({ id, label: labels[id] }))
		},

		/**
		 * @return {Array<object>} Measure options.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
		 */
		metricOptions() {
			return [{ id: 'count', label: this.t('planninq', 'Number of tasks') }]
		},

		/**
		 * @return {Array<object>} Display options.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
		 */
		displayOptions() {
			const labels = { table: this.t('planninq', 'Table'), bar: this.t('planninq', 'Bar'), donut: this.t('planninq', 'Donut') }
			return REPORT_DISPLAYS.map((id) => ({ id, label: labels[id] }))
		},

		/**
		 * @return {boolean} Whether the report can be saved.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
		 */
		valid() {
			return this.draft.title.trim() !== '' && this.draft.projects.length > 0
		},
	},

	methods: {
		/**
		 * Save and hand the saved report back.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
		 */
		async save() {
			this.saving = true
			this.error = ''
			try {
				const filters = equalityFilters(this.draft.filters)
				// No filters is null: an empty object reaches the server as an empty array, which the schema refuses.
				const saved = await saveReport({ ...(this.report.id ? { id: this.report.id } : {}), ...this.draft, title: this.draft.title.trim(), filters: Object.keys(filters).length ? filters : null })
				this.$emit('saved', saved)
			} catch {
				this.error = this.t('planninq', 'The report could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.report-builder {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.report-builder__filters {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.report-builder__error {
	color: var(--color-error-text);
}
</style>
