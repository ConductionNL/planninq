<template>
	<NcDialog
		:name="isEdit ? t('planninq', 'Edit cost line') : t('planninq', 'Add cost line')"
		@closing="$emit('close')">
		<template #default>
			<div class="finance-line-dialog__body">
				<div class="finance-line-dialog__row">
					<NcSelect
						v-model="kind"
						:options="kindOptions"
						:clearable="false"
						:inputLabel="t('planninq', 'Kind')"
						label="label"
						data-testid="finance-line-kind" />
					<NcSelect
						v-model="category"
						:options="categoryOptions"
						:clearable="false"
						:inputLabel="t('planninq', 'Category')"
						data-testid="finance-line-category" />
				</div>
				<div class="finance-line-dialog__row">
					<NcTextField
						v-model="amount"
						type="number"
						min="0"
						step="0.01"
						:label="t('planninq', 'Amount in euro')"
						data-testid="finance-line-amount"
						required />
					<NcTextField
						v-model="date"
						type="date"
						:label="t('planninq', 'Date')"
						data-testid="finance-line-date" />
				</div>
				<NcSelect
					v-if="phaseOptions.length"
					v-model="phase"
					:options="phaseOptions"
					:inputLabel="t('planninq', 'Phase')"
					label="label"
					data-testid="finance-line-phase" />
				<NcTextField
					v-model="description"
					:label="t('planninq', 'Description')"
					data-testid="finance-line-description" />
				<div v-if="submitError" class="finance-line-dialog__error" role="alert">
					{{ submitError }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton
				v-if="isEdit"
				variant="error"
				:disabled="saving"
				data-testid="finance-line-remove"
				@click="remove">
				{{ t('planninq', 'Remove') }}
			</NcButton>
			<NcButton :disabled="saving" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || !validAmount || !category"
				data-testid="finance-line-save"
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
 * FinanceLineDialog.
 *
 * Adds a manual cost line to a project, or changes or removes one: its kind
 * (budget, commitment, actual cost or forecast), category, amount, date,
 * phase and description. Lines from the finance system never open here.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 */
import { NcButton, NcDialog, NcLoadingIcon, NcSelect, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { financeLinePayload } from '../utils/finance.js'

export default {
	name: 'FinanceLineDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
		NcTextField,
	},

	props: {
		/** The line to change; null to add one. */
		line: {
			type: Object,
			default: null,
		},

		/** The project. */
		projectId: {
			type: String,
			required: true,
		},

		/** The admin's cost categories. */
		categories: {
			type: Array,
			default: () => [],
		},

		/** The project's phases. */
		phases: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'saved', 'removed'],

	data() {
		const kindOptions = [
			{ id: 'budget', label: this.t('planninq', 'Budget') },
			{ id: 'commitment', label: this.t('planninq', 'Commitment') },
			{ id: 'actual', label: this.t('planninq', 'Actual cost') },
			{ id: 'forecast', label: this.t('planninq', 'Forecast') },
		]
		const phaseOptions = this.phases.map((phase) => ({ id: phase.id, label: phase.title || '' }))
		return {
			kindOptions,
			phaseOptions,
			kind: kindOptions.find((option) => option.id === this.line?.kind) || kindOptions[2],
			category: this.line?.category || this.categories[0] || '',
			amount: this.line?.amount !== undefined ? String(this.line.amount) : '',
			date: this.line?.date || '',
			phase: phaseOptions.find((option) => option.id === this.line?.phase) || null,
			description: this.line?.description || '',
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @return {boolean}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		isEdit() {
			return Boolean(this.line?.id)
		},

		/**
		 * The admin's categories, plus the line's own when the admin removed it.
		 *
		 * @return {Array<string>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		categoryOptions() {
			const own = this.line?.category
			return own && !this.categories.includes(own) ? [...this.categories, own] : this.categories
		},

		/**
		 * @return {boolean}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		validAmount() {
			return this.amount !== '' && Number.isFinite(Number(this.amount)) && Number(this.amount) >= 0
		},
	},

	methods: {
		/**
		 * Save the line and hand the saved object to the parent.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			const payload = financeLinePayload({
				kind: this.kind.id,
				category: this.category,
				amount: this.amount,
				date: this.date,
				description: this.description,
				phase: this.phase?.id,
			}, this.projectId)
			const saved = await useProjectsStore().saveFinanceLine(this.isEdit ? { id: this.line.id, ...payload } : payload)
			this.saving = false
			if (!saved) {
				this.submitError = this.t('planninq', 'Could not save the cost line. Only the project owner changes the money of a project.')
				return
			}
			this.$emit('saved', saved)
		},

		/**
		 * Remove the line.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		async remove() {
			this.saving = true
			this.submitError = ''
			const removed = await useProjectsStore().deleteFinanceLine(this.line.id)
			this.saving = false
			if (!removed) {
				this.submitError = this.t('planninq', 'Could not remove the cost line. Please try again.')
				return
			}
			this.$emit('removed', this.line.id)
		},
	},
}
</script>

<style scoped>
.finance-line-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(520px, 90vw);
}

.finance-line-dialog__row {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

.finance-line-dialog__row > * {
	flex: 1 1 200px;
}

.finance-line-dialog__error {
	color: var(--color-error-text);
}
</style>
