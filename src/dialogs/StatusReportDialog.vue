<template>
	<NcDialog :name="t('planninq', 'New report')" size="large" @closing="$emit('close')">
		<template #default>
			<div class="status-report-dialog__body">
				<NcTextField
					v-model="reportDate"
					type="date"
					:label="t('planninq', 'Report date')"
					data-testid="status-report-date"
					required />

				<fieldset
					v-for="aspect in aspects"
					:key="aspect.id"
					class="status-report-dialog__aspect"
					:data-testid="`status-aspect-${aspect.id}`">
					<legend>{{ aspect.label }}</legend>
					<div class="status-report-dialog__choices">
						<NcCheckboxRadioSwitch
							v-for="option in statusOptions"
							:key="option.id"
							type="radio"
							:name="`status-${aspect.id}`"
							:value="option.id"
							:modelValue="fields[aspect.id].status"
							:data-testid="`status-${aspect.id}-${option.id}`"
							@update:modelValue="fields[aspect.id].status = $event">
							{{ option.label }}
						</NcCheckboxRadioSwitch>
					</div>
					<p
						v-if="suggestionText(aspect.id)"
						class="status-report-dialog__suggestion"
						:data-testid="`status-suggestion-${aspect.id}`">
						{{ suggestionText(aspect.id) }}
					</p>
					<NcTextField
						v-model="fields[aspect.id].note"
						:label="t('planninq', 'Note')"
						:data-testid="`status-note-${aspect.id}`" />
				</fieldset>

				<p v-if="missing.length" class="status-report-dialog__hint">
					{{ t('planninq', 'Choose a status for every aspect to save the report.') }}
				</p>
				<div v-if="submitError" class="status-report-dialog__error" role="alert">
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
				:disabled="saving || missing.length > 0 || !reportDate"
				data-testid="status-report-save"
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
 * StatusReportDialog.
 *
 * Writes a status report: on track, at risk or off track for each of the six
 * aspects, with a note. Money, time and risk show a suggestion with its
 * reason; nothing is pre-selected, so the project leader always decides
 * (design decision 2). The server calculates the overall status.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { ASPECTS, localToday, reportPayload } from '../utils/statusReports.js'

export default {
	name: 'StatusReportDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcLoadingIcon,
		NcTextField,
	},

	props: {
		/** The project the report is about. */
		projectId: {
			type: String,
			required: true,
		},

		/** Suggestions keyed by aspect, from suggestMoney, suggestTime and suggestRisk. */
		suggestions: {
			type: Object,
			default: () => ({}),
		},
	},

	emits: ['close', 'saved'],

	data() {
		const fields = {}
		for (const aspect of ASPECTS) {
			fields[aspect] = { status: '', note: '' }
		}
		return {
			reportDate: localToday(),
			fields,
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		aspects() {
			const labels = {
				money: this.t('planninq', 'Money'),
				organisation: this.t('planninq', 'Organisation'),
				time: this.t('planninq', 'Time'),
				information: this.t('planninq', 'Information'),
				quality: this.t('planninq', 'Quality'),
				risk: this.t('planninq', 'Risk'),
			}
			return ASPECTS.map((id) => ({ id, label: labels[id] }))
		},

		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		statusOptions() {
			return [
				{ id: 'onTrack', label: this.t('planninq', 'On track') },
				{ id: 'atRisk', label: this.t('planninq', 'At risk') },
				{ id: 'offTrack', label: this.t('planninq', 'Off track') },
			]
		},

		/**
		 * The aspects that still have no status.
		 *
		 * @return {Array<string>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		missing() {
			return ASPECTS.filter((aspect) => !this.fields[aspect].status)
		},
	},

	methods: {
		/**
		 * The suggestion for one aspect as a sentence, or '' when there is none to show.
		 *
		 * @param {string} aspect The aspect.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		suggestionText(aspect) {
			const suggestion = this.suggestions?.[aspect]
			if (!suggestion) {
				return ''
			}
			const reason = this.reasonText(suggestion)
			if (!suggestion.status) {
				return reason
			}
			const status = {
				onTrack: this.t('planninq', 'Suggested: on track.'),
				atRisk: this.t('planninq', 'Suggested: at risk.'),
				offTrack: this.t('planninq', 'Suggested: off track.'),
			}[suggestion.status]
			return reason ? `${status} ${reason}` : status
		},

		/**
		 * The reason of a suggestion.
		 *
		 * @param {object} suggestion The suggestion.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		reasonText(suggestion) {
			switch (suggestion.code) {
				case 'late':
					return suggestion.count === 1
						? this.t('planninq', '1 task is past its due date.')
						: this.t('planninq', '{count} tasks are past their due date.', { count: suggestion.count })
				case 'endPassed':
					return suggestion.count === 1
						? this.t('planninq', 'The end date has passed with 1 open task.')
						: this.t('planninq', 'The end date has passed with {count} open tasks.', { count: suggestion.count })
				case 'onSchedule':
					return this.t('planninq', 'No open task is past its due date.')
				case 'highestRisk':
					return this.t('planninq', 'The highest open risk, "{title}", scores {score}.', { title: suggestion.title, score: suggestion.score })
				case 'noOpenRisks':
					return this.t('planninq', 'There are no open risks.')
				case 'overBudget':
				case 'nearBudget':
				case 'withinBudget':
					return this.t('planninq', 'Costs are at {percent}% of the budget.', { percent: suggestion.percent })
				case 'noBudget':
					return this.t('planninq', 'No suggestion: the project has no budget.')
				case 'noCosts':
					return this.t('planninq', 'No suggestion: no costs are recorded for this project yet.')
				default:
					return ''
			}
		},

		/**
		 * Save the report and hand the saved object to the parent.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			const saved = await useProjectsStore().saveStatusReport(reportPayload({ reportDate: this.reportDate, ...this.fields }, this.projectId))
			this.saving = false
			if (!saved) {
				this.submitError = this.t('planninq', 'The report was not saved. Only the project owner can write status reports.')
				return
			}
			this.$emit('saved', saved)
		},
	},
}
</script>

<style scoped>
.status-report-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.status-report-dialog__aspect {
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.status-report-dialog__aspect legend {
	padding: 0 4px;
	font-weight: 600;
}

.status-report-dialog__choices {
	display: flex;
	flex-wrap: wrap;
	gap: 8px 16px;
}

.status-report-dialog__suggestion,
.status-report-dialog__hint {
	margin: 4px 0;
	color: var(--color-text-maxcontrast);
}

.status-report-dialog__error {
	color: var(--color-error-text);
}
</style>
