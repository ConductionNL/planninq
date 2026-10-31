<template>
	<NcDialog
		:name="isEdit ? t('planninq', 'Edit risk') : t('planninq', 'Add risk')"
		@closing="$emit('close')">
		<template #default>
			<div class="risk-edit-dialog__body">
				<NcTextField
					v-model="title"
					:label="t('planninq', 'What could go wrong')"
					data-testid="risk-title"
					required />
				<div class="risk-edit-dialog__row">
					<NcSelect
						v-model="likelihood"
						:options="likelihoodOptions"
						:clearable="false"
						:inputLabel="t('planninq', 'Likelihood')"
						label="label"
						data-testid="risk-likelihood" />
					<NcSelect
						v-model="impact"
						:options="impactOptions"
						:clearable="false"
						:inputLabel="t('planninq', 'Impact')"
						label="label"
						data-testid="risk-impact" />
				</div>
				<p class="risk-edit-dialog__score" data-testid="risk-score-preview">
					{{ t('planninq', 'Score {score}', { score: likelihood.id * impact.id }) }}
				</p>
				<div class="risk-edit-dialog__row">
					<NcSelect
						v-model="response"
						:options="responseOptions"
						:clearable="false"
						:inputLabel="t('planninq', 'Response')"
						label="label"
						data-testid="risk-response" />
					<NcSelect
						v-model="status"
						:options="statusOptions"
						:clearable="false"
						:inputLabel="t('planninq', 'Status')"
						label="label"
						data-testid="risk-status" />
				</div>
				<NcSelect
					v-model="owner"
					:options="people"
					:inputLabel="t('planninq', 'Owner')"
					label="label"
					data-testid="risk-owner" />
				<NcTextArea
					v-model="countermeasures"
					:label="t('planninq', 'Countermeasures')"
					resize="vertical"
					data-testid="risk-countermeasures" />
				<NcTextField
					v-model="reviewDate"
					type="date"
					:label="t('planninq', 'Review date')"
					data-testid="risk-review-date" />
				<div v-if="submitError" class="risk-edit-dialog__error" role="alert">
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
				data-testid="risk-save"
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
 * RiskEditDialog.
 *
 * Adds a risk to a project or edits one: likelihood and impact on the admin's
 * scale, response, status, owner, countermeasures and a review date. The
 * server calculates the score.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
import { NcButton, NcDialog, NcLoadingIcon, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { DEFAULT_RISK_SCALE, riskPayload } from '../utils/riskHelpers.js'

export default {
	name: 'RiskEditDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The risk to edit; null to add one. */
		risk: {
			type: Object,
			default: null,
		},

		/** The project. */
		projectId: {
			type: String,
			required: true,
		},

		/** The risk scale. */
		scale: {
			type: Object,
			default: () => DEFAULT_RISK_SCALE,
		},

		/** The people on the project, as `{ id, label }`. */
		people: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'saved'],

	data() {
		const levels = (labels) => labels.map((label, i) => ({ id: i + 1, label: `${i + 1}. ${this.t('planninq', label)}` }))
		const likelihoodOptions = levels(this.scale.likelihood)
		const impactOptions = levels(this.scale.impact)
		const responseOptions = [
			{ id: 'avoid', label: this.t('planninq', 'Avoid') },
			{ id: 'reduce', label: this.t('planninq', 'Reduce') },
			{ id: 'transfer', label: this.t('planninq', 'Transfer') },
			{ id: 'accept', label: this.t('planninq', 'Accept') },
		]
		const statusOptions = [
			{ id: 'open', label: this.t('planninq', 'Open') },
			{ id: 'mitigating', label: this.t('planninq', 'Mitigating') },
			{ id: 'closed', label: this.t('planninq', 'Closed') },
			{ id: 'occurred', label: this.t('planninq', 'Occurred') },
		]
		const pick = (options, id, fallback) => options.find((option) => option.id === id) || options[fallback]
		return {
			likelihoodOptions,
			impactOptions,
			responseOptions,
			statusOptions,
			title: this.risk?.title || '',
			likelihood: pick(likelihoodOptions, Number(this.risk?.likelihood), 0),
			impact: pick(impactOptions, Number(this.risk?.impact), 0),
			response: pick(responseOptions, this.risk?.response, 1),
			status: pick(statusOptions, this.risk?.status, 0),
			owner: this.people.find((person) => person.id === this.risk?.owner) || null,
			countermeasures: this.risk?.countermeasures || '',
			reviewDate: this.risk?.reviewDate || '',
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/** @return {boolean} */
		isEdit() {
			return Boolean(this.risk?.id)
		},
	},

	methods: {
		/**
		 * Save the risk and hand the saved object to the parent.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			const payload = riskPayload({
				title: this.title,
				likelihood: this.likelihood.id,
				impact: this.impact.id,
				response: this.response.id,
				status: this.status.id,
				owner: this.owner?.id,
				countermeasures: this.countermeasures,
				reviewDate: this.reviewDate,
				description: this.risk?.description,
				category: this.risk?.category,
			}, this.projectId)
			const saved = await useProjectsStore().saveRisk(this.isEdit ? { id: this.risk.id, ...payload } : payload)
			this.saving = false
			if (!saved) {
				this.submitError = this.t('planninq', 'Could not save the risk. Please try again.')
				return
			}
			this.$emit('saved', saved)
		},
	},
}
</script>

<style scoped>
.risk-edit-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(520px, 90vw);
}

.risk-edit-dialog__row {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

.risk-edit-dialog__row > * {
	flex: 1 1 200px;
}

.risk-edit-dialog__score {
	margin: 0;
	font-weight: 600;
}

.risk-edit-dialog__error {
	color: var(--color-error-text);
}
</style>
