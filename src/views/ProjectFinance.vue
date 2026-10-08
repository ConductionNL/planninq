<template>
	<div class="project-finance">
		<div class="project-finance__header">
			<h2>{{ t('planninq', 'Finance') }}</h2>
			<NcButton
				v-if="canEdit && seesMoney"
				variant="primary"
				data-testid="finance-line-add"
				@click="editing = { line: null }">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('planninq', 'Add cost line') }}
			</NcButton>
		</div>

		<ProjectTabs :projectId="projectId" />

		<div v-if="loading" class="project-finance__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<template v-else-if="project">
			<section class="project-finance__terms" data-testid="finance-terms">
				<h3>{{ t('planninq', 'Delivery terms') }}</h3>

				<form v-if="canEdit"
					novalidate
					data-testid="finance-terms-form"
					@submit.prevent="saveTerms">
					<NcCheckboxRadioSwitch v-model="form.billable" data-testid="finance-billable">
						{{ t('planninq', 'Billable') }}
					</NcCheckboxRadioSwitch>
					<div class="project-finance__fields">
						<NcSelect
							v-model="billingModel"
							:options="billingOptions"
							:clearable="false"
							:inputLabel="t('planninq', 'Billing model')"
							label="label"
							data-testid="finance-billing-model" />
						<NcTextField
							v-model="form.budgetAmount"
							type="number"
							min="0"
							:label="t('planninq', 'Budget in euro')"
							data-testid="finance-budget-amount" />
						<NcTextField
							v-model="form.budgetHours"
							type="number"
							min="0"
							:label="t('planninq', 'Budget in hours')"
							data-testid="finance-budget-hours-input" />
						<NcTextField
							v-model="form.hourlyRate"
							type="number"
							min="0"
							:label="t('planninq', 'Hourly rate in euro')"
							data-testid="finance-hourly-rate" />
						<NcTextField
							v-model="form.startDate"
							type="date"
							:label="t('planninq', 'Planned start')"
							data-testid="finance-start-date" />
						<NcTextField
							v-model="form.endDate"
							type="date"
							:label="t('planninq', 'Planned end')"
							data-testid="finance-end-date" />
					</div>
					<p v-if="termsMessage" :class="termsOk ? 'project-finance__muted' : 'project-finance__error'" role="status">
						{{ termsMessage }}
					</p>
					<NcButton variant="primary"
						type="submit"
						:disabled="savingTerms"
						data-testid="finance-terms-save">
						{{ savingTerms ? t('planninq', 'Saving…') : t('planninq', 'Save') }}
					</NcButton>
				</form>

				<dl class="project-finance__summary">
					<div>
						<dt>{{ t('planninq', 'Billing model') }}</dt>
						<dd data-testid="finance-billing-model-value">
							{{ billingLabel(project.billingModel) }}
						</dd>
					</div>
					<div>
						<dt>{{ t('planninq', 'Planned dates') }}</dt>
						<dd data-testid="finance-planned-dates">
							{{ plannedDates }}
						</dd>
					</div>
					<template v-if="seesMoney">
						<div>
							<dt>{{ t('planninq', 'Budget') }}</dt>
							<dd data-testid="finance-budget">
								{{ project.budgetAmount > 0 ? t('planninq', 'Budget {amount}', { amount: euro(project.budgetAmount) }) : t('planninq', 'No budget agreed') }}
							</dd>
						</div>
						<div v-if="project.budgetHours > 0">
							<dt>{{ t('planninq', 'Hours') }}</dt>
							<dd data-testid="finance-budget-hours">
								{{ t('planninq', '{hours} hours', { hours: project.budgetHours }) }}
							</dd>
						</div>
					</template>
				</dl>
				<p v-if="!seesMoney" class="project-finance__muted" data-testid="finance-no-money">
					{{ t('planninq', 'Only the project owner and the portfolio managers see the amounts.') }}
				</p>
			</section>

			<template v-if="seesMoney">
				<section class="project-finance__section">
					<h3>{{ t('planninq', 'Budget against cost per category') }}</h3>
					<table class="project-finance__table" data-testid="finance-category-table">
						<thead>
							<tr>
								<th scope="col">
									{{ t('planninq', 'Category') }}
								</th>
								<th v-for="column in columns" :key="column.id" scope="col">
									{{ column.label }}
								</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="row in table.categoryRows"
								:key="row.id"
								data-testid="finance-category-row"
								:data-category="row.id === labourRow ? 'labour' : row.id">
								<th scope="row">
									{{ row.id === labourRow ? t('planninq', 'Labour (booked time)') : (row.label || t('planninq', 'No category')) }}
								</th>
								<template v-if="row.id === labourRow && row.available === false">
									<td :colspan="columns.length" class="project-finance__muted">
										{{ t('planninq', 'Hours are not available') }}
									</td>
								</template>
								<template v-else>
									<td v-for="column in columns" :key="column.id" :data-testid="`finance-cell-${column.id}`">
										<FinanceAmount :row="row" :column="column.id" />
									</td>
								</template>
							</tr>
							<tr v-if="table.unassignedBudget > 0" data-testid="finance-unassigned">
								<th scope="row">
									{{ t('planninq', 'Not yet assigned to a category') }}
								</th>
								<td>{{ euro(table.unassignedBudget) }}</td>
								<td :colspan="columns.length - 1" />
							</tr>
						</tbody>
						<tfoot>
							<tr data-testid="finance-total">
								<th scope="row">
									{{ t('planninq', 'Total') }}
								</th>
								<td v-for="column in columns" :key="column.id" :data-testid="`finance-total-${column.id}`">
									<FinanceAmount :row="table.total" :column="column.id" />
								</td>
							</tr>
						</tfoot>
					</table>
					<p class="project-finance__muted" data-testid="finance-spent-summary">
						{{ t('planninq', '{spent} spent, {remaining} remaining', { spent: euro(table.total.actual), remaining: euro(table.total.remaining ?? 0) }) }}
					</p>
				</section>

				<section v-if="table.phaseRows.length" class="project-finance__section">
					<h3>{{ t('planninq', 'Budget against cost per phase') }}</h3>
					<table class="project-finance__table" data-testid="finance-phase-table">
						<thead>
							<tr>
								<th scope="col">
									{{ t('planninq', 'Phase') }}
								</th>
								<th v-for="column in columns" :key="column.id" scope="col">
									{{ column.label }}
								</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="row in table.phaseRows" :key="row.id || 'none'">
								<th scope="row">
									{{ row.id ? row.label : t('planninq', 'No phase') }}
								</th>
								<td v-for="column in columns" :key="column.id">
									<FinanceAmount :row="row" :column="column.id" />
								</td>
							</tr>
						</tbody>
					</table>
				</section>

				<section class="project-finance__section">
					<h3>{{ t('planninq', 'Cost lines') }}</h3>
					<ul v-if="sortedLines.length" class="project-finance__lines" data-testid="finance-lines">
						<li
							v-for="line in sortedLines"
							:key="line.id"
							class="project-finance__line"
							data-testid="finance-line"
							:data-source="line.source || 'manual'">
							<span class="project-finance__line-main">
								<strong>{{ euro(line.amount) }}</strong>
								{{ kindLabel(line.kind) }}, {{ line.category || t('planninq', 'No category') }}
								<span v-if="line.date" class="project-finance__muted">{{ line.date }}</span>
								<span v-if="line.description" class="project-finance__muted">{{ line.description }}</span>
							</span>
							<span v-if="line.source === 'import'" class="project-finance__badge" data-testid="finance-line-imported">
								{{ t('planninq', 'From the finance system') }}
							</span>
							<NcButton
								v-else-if="canEdit"
								variant="tertiary"
								:aria-label="t('planninq', 'Edit cost line {amount}', { amount: euro(line.amount) })"
								data-testid="finance-line-edit"
								@click="editing = { line }">
								<template #icon>
									<PencilIcon :size="20" />
								</template>
							</NcButton>
						</li>
					</ul>
					<NcEmptyContent v-else :name="t('planninq', 'No cost lines yet')" />
				</section>
			</template>
		</template>

		<FinanceLineDialog
			v-if="editing"
			:line="editing.line"
			:projectId="projectId"
			:categories="categories"
			:phases="phases"
			@close="editing = null"
			@saved="onSaved"
			@removed="onRemoved" />
	</div>
</template>

<script>
/**
 * ProjectFinance.
 *
 * The project's Finance tab: the delivery terms (billable, billing model,
 * budget in money and hours, hourly rate and planned dates), and for the
 * owner, the portfolio managers and admins the budget against commitments,
 * actual cost and forecast per cost category and per phase, with booked time
 * counted as labour. Members see the billing model and the dates, never an
 * amount; OpenRegister does not even return them the lines.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 */
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon, NcSelect, NcTextField } from '@nextcloud/vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FinanceAmount from '../components/FinanceAmount.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import FinanceLineDialog from '../dialogs/FinanceLineDialog.vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { useProjectsStore } from '../store/projects.js'
import {
	canEditTerms,
	canSeeProjectMoney,
	financeTable,
	formatEuro,
	laborCost,
	LABOUR_ROW,
	parseCategories,
	termsForm,
	termsPatch,
} from '../utils/finance.js'

export default {
	name: 'ProjectFinance',

	components: {
		FinanceAmount,
		FinanceLineDialog,
		NcButton,
		NcCheckboxRadioSwitch,
		NcEmptyContent,
		NcLoadingIcon,
		NcSelect,
		NcTextField,
		PencilIcon,
		PlusIcon,
		ProjectTabs,
	},

	data() {
		return {
			project: null,
			lines: [],
			entries: [],
			phases: [],
			form: termsForm({}),
			loading: true,
			savingTerms: false,
			termsMessage: '',
			termsOk: true,
			editing: null,
			labourRow: LABOUR_ROW,
			settingsStore: useSettingsStore(),
		}
	},

	computed: {
		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/**
		 * @return {object|null}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		user() {
			return getCurrentUser()
		},

		/**
		 * @return {boolean}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		seesMoney() {
			return canSeeProjectMoney(this.project, this.user)
		},

		/**
		 * @return {boolean}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		canEdit() {
			return canEditTerms(this.project, this.user)
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		billingOptions() {
			return ['none', 'fixedPrice', 'hourly'].map((id) => ({ id, label: this.billingLabel(id) }))
		},

		billingModel: {
			/**
			 * @return {object}
			 *
			 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
			 */
			get() {
				return this.billingOptions.find((option) => option.id === this.form.billingModel) || this.billingOptions[0]
			},

			/**
			 * @param {object} option The chosen model.
			 *
			 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
			 */
			set(option) {
				this.form.billingModel = option?.id || 'none'
			},
		},

		/**
		 * @return {Array<string>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.2
		 */
		categories() {
			return parseCategories(this.settingsStore.settings?.finance_categories)
		},

		/**
		 * @return {object}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		table() {
			return financeTable({
				lines: this.lines,
				categories: this.categories,
				labour: laborCost(this.entries, this.project),
				project: this.project,
				phases: this.phases,
			})
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		columns() {
			return [
				{ id: 'budget', label: this.t('planninq', 'Budget') },
				{ id: 'commitment', label: this.t('planninq', 'Commitments') },
				{ id: 'actual', label: this.t('planninq', 'Actual cost') },
				{ id: 'forecast', label: this.t('planninq', 'Forecast') },
				{ id: 'remaining', label: this.t('planninq', 'Remaining') },
			]
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		sortedLines() {
			return [...this.lines].sort((a, b) => String(b.date || '').localeCompare(String(a.date || '')))
		},

		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		plannedDates() {
			const start = this.project?.startDate
			const end = this.project?.endDate
			if (!start && !end) {
				return this.t('planninq', 'Not planned yet')
			}
			return this.t('planninq', '{start} to {end}', { start: start || '?', end: end || '?' })
		},
	},

	watch: {
		projectId: {
			immediate: true,
			/**
			 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		/**
		 * Load the project, and for those who see money its lines, booked time and phases.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		async load() {
			this.loading = true
			const store = useProjectsStore()
			try {
				const [project] = await Promise.all([
					store.fetchProject(this.projectId),
					this.settingsStore.settings?.finance_categories ? Promise.resolve() : this.settingsStore.fetchSettings(),
				])
				this.project = project
				this.form = termsForm(project || {})
				if (canSeeProjectMoney(project, this.user)) {
					const [lines, entries, phases] = await Promise.all([
						store.fetchFinanceLines(this.projectId),
						store.fetchProjectTimeEntries(this.projectId),
						store.fetchPhases(this.projectId),
					])
					this.lines = lines
					this.entries = entries
					this.phases = [...phases].sort((a, b) => Number(a.order ?? 0) - Number(b.order ?? 0))
				}
			} finally {
				this.loading = false
			}
		},

		/**
		 * Save the delivery terms onto the project.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		async saveTerms() {
			this.savingTerms = true
			this.termsMessage = ''
			const updated = await useProjectsStore().patchProject(this.projectId, termsPatch(this.form))
			this.savingTerms = false
			this.termsOk = !!updated
			if (!updated) {
				this.termsMessage = this.t('planninq', 'The terms were not saved. Please try again.')
				return
			}
			this.project = { ...this.project, ...updated }
			this.form = termsForm(this.project)
			this.termsMessage = this.t('planninq', 'Terms saved')
		},

		/**
		 * @param {object} saved The saved line.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		onSaved(saved) {
			this.editing = null
			const id = saved?.id ?? saved?.['@self']?.id
			this.lines = [{ ...saved, id }, ...this.lines.filter((line) => line.id !== id)]
		},

		/**
		 * @param {string} id The removed line.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		onRemoved(id) {
			this.editing = null
			this.lines = this.lines.filter((line) => line.id !== id)
		},

		/**
		 * @param {string} model The billing model.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
		 */
		billingLabel(model) {
			return {
				fixedPrice: this.t('planninq', 'Fixed price'),
				hourly: this.t('planninq', 'Hourly'),
			}[model] || this.t('planninq', 'Not billed')
		},

		/**
		 * @param {string} kind The kind of amount.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		kindLabel(kind) {
			return {
				budget: this.t('planninq', 'Budget'),
				commitment: this.t('planninq', 'Commitment'),
				actual: this.t('planninq', 'Actual cost'),
				forecast: this.t('planninq', 'Forecast'),
			}[kind] || kind || ''
		},

		/**
		 * @param {number} amount The amount.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		euro(amount) {
			return formatEuro(Number(amount) || 0)
		},
	},
}
</script>

<style scoped>
.project-finance {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.project-finance__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.project-finance__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-finance__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.project-finance__fields {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: 12px;
	margin: 8px 0 12px;
}

.project-finance__summary {
	display: flex;
	flex-wrap: wrap;
	gap: 24px;
	margin: 16px 0 0;
}

.project-finance__summary dt {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.project-finance__summary dd {
	margin: 0;
	font-weight: 600;
}

.project-finance__section {
	margin-top: 24px;
}

.project-finance__table {
	width: 100%;
	border-collapse: collapse;
}

.project-finance__table th,
.project-finance__table td {
	padding: 8px;
	border-bottom: 1px solid var(--color-border);
	text-align: start;
}

.project-finance__table tfoot th,
.project-finance__table tfoot td {
	font-weight: 600;
}

.project-finance__lines {
	margin: 0;
	padding: 0;
	list-style: none;
}

.project-finance__line {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
}

.project-finance__line-main {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.project-finance__badge {
	padding: 2px 8px;
	border-radius: var(--border-radius-pill);
	background-color: var(--color-background-dark);
	font-size: 13px;
}

.project-finance__muted {
	color: var(--color-text-maxcontrast);
}

.project-finance__error {
	color: var(--color-error-text);
}
</style>
