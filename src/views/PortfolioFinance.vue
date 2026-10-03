<template>
	<div class="portfolio-finance">
		<div class="portfolio-finance__header">
			<h2>{{ t('planninq', 'Portfolio finance') }}</h2>
			<NcSelect
				v-if="portfolios.length"
				:modelValue="selectedOption"
				class="portfolio-finance__picker"
				:options="portfolioOptions"
				:clearable="false"
				:inputLabel="t('planninq', 'Portfolio')"
				label="label"
				data-testid="portfolio-finance-picker"
				@update:modelValue="pick" />
		</div>

		<div v-if="loading" class="portfolio-finance__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="!portfolios.length"
			:name="t('planninq', 'No portfolios yet')"
			:description="t('planninq', 'An admin adds portfolios under Projects, Portfolios. Projects in a portfolio are listed here with their status.')" />

		<template v-else-if="portfolio">
			<p v-if="!finance.rows.length" class="portfolio-finance__muted" data-testid="portfolio-finance-none">
				{{ t('planninq', 'You cannot see the money of any project in this portfolio') }}
			</p>

			<template v-else>
				<p v-if="!finance.consistent"
					class="portfolio-finance__error"
					role="alert"
					data-testid="portfolio-finance-inconsistent">
					{{ t('planninq', 'The server returned cost lines of projects outside this portfolio, so these totals may be wrong.') }}
				</p>
				<table class="portfolio-finance__table" data-testid="portfolio-finance-table">
					<thead>
						<tr>
							<th scope="col">
								{{ t('planninq', 'Project') }}
							</th>
							<th v-for="column in columns" :key="column.id" scope="col">
								{{ column.label }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="row in finance.rows"
							:key="row.project.id"
							data-testid="portfolio-finance-row"
							:data-project="row.project.title">
							<th scope="row">
								<RouterLink :to="{ name: 'ProjectFinance', params: { id: row.project.id } }">
									{{ row.project.title }}
								</RouterLink>
							</th>
							<td v-for="column in columns" :key="column.id" :data-testid="`portfolio-finance-${column.id}`">
								<FinanceAmount :row="row" :column="column.id" />
							</td>
						</tr>
					</tbody>
					<tfoot>
						<tr data-testid="portfolio-finance-total">
							<th scope="row">
								{{ t('planninq', 'Total') }}
							</th>
							<td v-for="column in columns" :key="column.id" :data-testid="`portfolio-finance-total-${column.id}`">
								<FinanceAmount :row="finance.total" :column="column.id" />
							</td>
						</tr>
					</tfoot>
				</table>
				<p v-if="finance.hidden" class="portfolio-finance__muted" data-testid="portfolio-finance-hidden">
					{{ t('planninq', 'Projects left out because you cannot see their money: {count}', { count: finance.hidden }) }}
				</p>
			</template>
		</template>
	</div>
</template>

<script>
/**
 * PortfolioFinance.
 *
 * Money across one portfolio: a row per project with budget, commitments,
 * actual cost including labour, forecast and what remains, and the totals.
 * Only the projects whose money the viewer may see are listed and totalled.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
 */
import { getCurrentUser } from '@nextcloud/auth'
import { NcEmptyContent, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import FinanceAmount from '../components/FinanceAmount.vue'
import { useProjectsStore } from '../store/projects.js'
import { canSeeProjectMoney, portfolioFinance } from '../utils/finance.js'
import { filterByPortfolio, sortPortfolios } from '../utils/portfolioGrouping.js'

export default {
	name: 'PortfolioFinance',

	components: {
		FinanceAmount,
		NcEmptyContent,
		NcLoadingIcon,
		NcSelect,
	},

	data() {
		return {
			loading: true,
			portfolios: [],
			projects: [],
			lines: [],
			entriesByProject: {},
		}
	},

	computed: {
		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		portfolioOptions() {
			return sortPortfolios(this.portfolios).map((p) => ({ id: String(p.id), label: String(p.title ?? '') }))
		},

		/**
		 * The portfolio in the address (?portfolio=), else the first one.
		 *
		 * @return {object|null}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		portfolio() {
			const wanted = String(this.$route.query.portfolio || '')
			const sorted = sortPortfolios(this.portfolios)
			return sorted.find((p) => String(p.id) === wanted) || sorted[0] || null
		},

		/**
		 * @return {{id: string, label: string}|null}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		selectedOption() {
			return this.portfolioOptions.find((option) => option.id === String(this.portfolio?.id)) || null
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		portfolioProjects() {
			return this.portfolio ? filterByPortfolio(this.projects, String(this.portfolio.id)) : []
		},

		/**
		 * @return {object}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		finance() {
			return portfolioFinance({
				projects: this.portfolioProjects,
				lines: this.lines,
				entriesByProject: this.entriesByProject,
				user: getCurrentUser(),
			})
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
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
	},

	watch: {
		/**
		 * @spec exclude Data glue: reload the money when another portfolio is picked.
		 */
		portfolioProjects() {
			this.loadMoney()
		},
	},

	/**
	 * @spec exclude Lifecycle glue: loads portfolios and projects.
	 */
	async mounted() {
		const store = useProjectsStore()
		const [portfolios] = await Promise.all([store.fetchPortfolios(), store.fetchProjects()])
		this.portfolios = portfolios
		this.projects = store.projects
		this.loading = false
	},

	methods: {
		/**
		 * @param {{id: string}} option The picked portfolio.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		pick(option) {
			if (option?.id && option.id !== this.$route.query.portfolio) {
				this.$router.replace({ query: { ...this.$route.query, portfolio: option.id } })
			}
		},

		/**
		 * Load the portfolio's lines and the time booked on the projects whose money the viewer may see.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		async loadMoney() {
			const user = getCurrentUser()
			const visible = this.portfolioProjects.filter((project) => canSeeProjectMoney(project, user))
			if (!this.portfolio || !visible.length) {
				this.lines = []
				this.entriesByProject = {}
				return
			}
			const money = await useProjectsStore().fetchPortfolioMoney(String(this.portfolio.id), visible)
			this.lines = money.lines
			this.entriesByProject = money.entriesByProject
		},
	},
}
</script>

<style scoped>
.portfolio-finance {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.portfolio-finance__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.portfolio-finance__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.portfolio-finance__picker {
	min-width: 240px;
}

.portfolio-finance__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.portfolio-finance__table {
	width: 100%;
	border-collapse: collapse;
}

.portfolio-finance__table th,
.portfolio-finance__table td {
	padding: 8px;
	border-bottom: 1px solid var(--color-border);
	text-align: start;
}

.portfolio-finance__table tfoot th,
.portfolio-finance__table tfoot td {
	font-weight: 600;
}

.portfolio-finance__muted {
	color: var(--color-text-maxcontrast);
}

.portfolio-finance__error {
	color: var(--color-error-text);
}
</style>
