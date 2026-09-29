<template>
	<div class="portfolio-status">
		<div class="portfolio-status__header">
			<h2>{{ t('planninq', 'Portfolio status') }}</h2>
			<NcSelect
				v-if="portfolios.length"
				:modelValue="selectedOption"
				class="portfolio-status__picker"
				:options="portfolioOptions"
				:clearable="false"
				:inputLabel="t('planninq', 'Portfolio')"
				label="label"
				data-testid="portfolio-status-picker"
				@update:modelValue="pick" />
		</div>

		<div v-if="loading" class="portfolio-status__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="!portfolios.length"
			:name="t('planninq', 'No portfolios yet')"
			:description="t('planninq', 'An admin adds portfolios under Projects, Portfolios. Projects in a portfolio are listed here with their status.')" />

		<template v-else-if="portfolio">
			<section class="portfolio-status__rollup" data-testid="portfolio-rollup">
				<h3>{{ t('planninq', 'Roll-up') }}</h3>
				<table class="portfolio-status__table">
					<thead>
						<tr>
							<th scope="col">
								{{ t('planninq', 'Aspect') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Projects') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Portfolio') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in rollup" :key="row.aspect" :data-testid="`portfolio-rollup-${row.aspect}`">
							<th scope="row">
								{{ aspectLabels[row.aspect] }}
							</th>
							<td data-testid="portfolio-rollup-counts">
								{{ t('planninq', '{onTrack} on track, {atRisk} at risk, {offTrack} off track, {noReport} no report', row) }}
							</td>
							<td>
								<HealthStatus :status="row.state || ''" data-testid="portfolio-rollup-state" />
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="portfolio-status__projects">
				<h3>{{ t('planninq', 'Projects') }}</h3>
				<p v-if="!rows.length" class="portfolio-status__muted">
					{{ t('planninq', 'No project in this portfolio that you can read.') }}
				</p>
				<div v-else class="portfolio-status__scroll">
					<table class="portfolio-status__table" data-testid="portfolio-projects">
						<thead>
							<tr>
								<th scope="col">
									{{ t('planninq', 'Project') }}
								</th>
								<th scope="col">
									{{ t('planninq', 'Status') }}
								</th>
								<th scope="col">
									{{ t('planninq', 'Progress') }}
								</th>
								<th scope="col">
									{{ t('planninq', 'Start') }}
								</th>
								<th scope="col">
									{{ t('planninq', 'End') }}
								</th>
								<th v-if="showMoney" scope="col">
									{{ t('planninq', 'Budget') }}
								</th>
								<th v-if="showMoney" scope="col">
									{{ t('planninq', 'Actual cost') }}
								</th>
								<th v-for="aspect in aspects" :key="aspect" scope="col">
									{{ aspectLabels[aspect] }}
								</th>
								<th scope="col">
									{{ t('planninq', 'Last report') }}
								</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="row in rows"
								:key="row.project.id"
								data-testid="portfolio-project-row"
								:data-project="row.project.id">
								<th scope="row">
									<router-link :to="{ name: 'ProjectStatus', params: { id: row.project.id } }">
										{{ row.project.title }}
									</router-link>
								</th>
								<td>{{ lifecycleLabel(row.project.status) }}</td>
								<td>{{ progressText(row.progress) }}</td>
								<td>{{ row.project.startDate || '' }}</td>
								<td>{{ row.project.endDate || '' }}</td>
								<td v-if="showMoney" data-testid="portfolio-project-budget">
									{{ row.money ? budgetText(row.project) : '' }}
								</td>
								<td v-if="showMoney" data-testid="portfolio-project-actual">
									{{ row.money && actualCost[row.project.id] !== undefined ? euro(actualCost[row.project.id]) : '' }}
								</td>
								<td v-for="aspect in aspects" :key="aspect">
									<HealthStatus :status="row.project.healthDate ? (row.project[`health${aspectKey(aspect)}`] || '') : ''" />
								</td>
								<td data-testid="portfolio-project-report-date">
									<template v-if="row.project.healthDate">
										{{ row.project.healthDate.slice(0, 10) }}
										<span v-if="row.outOfDate" class="portfolio-status__stale" data-testid="portfolio-out-of-date">
											{{ t('planninq', 'Out of date') }}
										</span>
									</template>
									<template v-else>
										{{ t('planninq', 'No report') }}
									</template>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</template>
	</div>
</template>

<script>
/**
 * PortfolioStatus.
 *
 * One portfolio's projects with the facts a portfolio office asks for: the
 * lifecycle status, progress, planned dates, the budget for the people who
 * answer for money, the six aspect statuses copied from each project's
 * latest report and its date, marked out of date past the admin's reporting
 * period. Above the list, each aspect rolls up to the worst state of the
 * portfolio's projects, with projects without a report counted apart.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.2
 */
import { getCurrentUser } from '@nextcloud/auth'
import { NcEmptyContent, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import HealthStatus from '../components/HealthStatus.vue'
import { fetchPortfolioTimeline } from '../api/timeline.js'
import { useSettingsStore } from '../store/modules/settings.js'
import { useProjectsStore } from '../store/projects.js'
import { canSeeProjectMoney, formatEuro, portfolioFinance } from '../utils/finance.js'
import { filterByPortfolio, sortPortfolios } from '../utils/portfolioGrouping.js'
import { canSeeMoney, isOutOfDate, portfolioRollup } from '../utils/portfolioStatus.js'
import { projectProgress } from '../utils/projectOverview.js'
import { aspectKey, ASPECTS, localToday } from '../utils/statusReports.js'

export default {
	name: 'PortfolioStatus',

	components: {
		HealthStatus,
		NcEmptyContent,
		NcLoadingIcon,
		NcSelect,
	},

	data() {
		return {
			loading: true,
			portfolios: [],
			projects: [],
			tasksByProject: {},
			moneyLines: [],
			entriesByProject: {},
			aspects: ASPECTS,
			settingsStore: useSettingsStore(),
		}
	},

	computed: {
		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		portfolioOptions() {
			return sortPortfolios(this.portfolios).map((p) => ({ id: String(p.id), label: String(p.title ?? '') }))
		},

		/**
		 * The portfolio in the address (?portfolio=), else the first one.
		 *
		 * @return {object|null}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		portfolio() {
			const wanted = String(this.$route.query.portfolio || '')
			const sorted = sortPortfolios(this.portfolios)
			return sorted.find((p) => String(p.id) === wanted) || sorted[0] || null
		},

		/**
		 * @return {{id: string, label: string}|null}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		selectedOption() {
			return this.portfolioOptions.find((option) => option.id === String(this.portfolio?.id)) || null
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		portfolioProjects() {
			return this.portfolio ? filterByPortfolio(this.projects, String(this.portfolio.id)) : []
		},

		/**
		 * The actual cost per project, labour included, for the projects whose money the viewer may see.
		 *
		 * @return {object}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		actualCost() {
			const finance = portfolioFinance({
				projects: this.portfolioProjects,
				lines: this.moneyLines,
				entriesByProject: this.entriesByProject,
				user: getCurrentUser(),
			})
			return Object.fromEntries(finance.rows.map((row) => [row.project.id, row.actual]))
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.2
		 */
		rollup() {
			return portfolioRollup(this.portfolioProjects)
		},

		/**
		 * One row per project, by title.
		 *
		 * @return {Array<{project: object, progress: object, money: boolean, outOfDate: boolean}>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		rows() {
			const today = localToday()
			const period = this.settingsStore.settings?.status_report_period_days
			const user = getCurrentUser()
			return [...this.portfolioProjects]
				.sort((a, b) => String(a.title ?? '').localeCompare(String(b.title ?? '')))
				.map((project) => ({
					project,
					progress: projectProgress(this.tasksByProject[project.id] || []),
					money: canSeeMoney(project, this.portfolio, user),
					outOfDate: isOutOfDate(project.healthDate, today, period),
				}))
		},

		/**
		 * @return {boolean}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		showMoney() {
			return this.rows.some((row) => row.money)
		},

		/**
		 * @return {object}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		aspectLabels() {
			return {
				money: this.t('planninq', 'Money'),
				organisation: this.t('planninq', 'Organisation'),
				time: this.t('planninq', 'Time'),
				information: this.t('planninq', 'Information'),
				quality: this.t('planninq', 'Quality'),
				risk: this.t('planninq', 'Risk'),
			}
		},
	},

	watch: {
		/**
		 * @spec exclude Data glue: reload the task counts when another portfolio is picked.
		 */
		portfolioProjects() {
			this.loadTasks()
			this.loadMoney()
		},
	},

	/**
	 * @spec exclude Lifecycle glue: loads portfolios, projects and settings.
	 */
	async mounted() {
		const store = useProjectsStore()
		const [portfolios] = await Promise.all([
			store.fetchPortfolios(),
			store.fetchProjects(),
			this.settingsStore.settings?.status_report_period_days ? Promise.resolve() : this.settingsStore.fetchSettings(),
		])
		this.portfolios = portfolios
		this.projects = store.projects
		this.loading = false
	},

	methods: {
		aspectKey,

		/**
		 * @param {{id: string}} option The picked portfolio.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		pick(option) {
			if (option?.id && option.id !== this.$route.query.portfolio) {
				this.$router.replace({ query: { ...this.$route.query, portfolio: option.id } })
			}
		},

		/**
		 * Load the tasks of the shown projects through one timeline request.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		async loadTasks() {
			const ids = this.portfolioProjects.map((project) => project.id)
			if (!ids.length) {
				this.tasksByProject = {}
				return
			}
			try {
				const answer = await fetchPortfolioTimeline(ids)
				this.tasksByProject = Object.fromEntries(answer.projects.map((p) => [p.id, [...(p.tasks || []), ...(p.unscheduled || [])]]))
			} catch (err) {
				console.error('PortfolioStatus: could not load the tasks', err)
				this.tasksByProject = {}
			}
		},

		/**
		 * Load the finance lines and booked time behind the actual cost column.
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		async loadMoney() {
			const user = getCurrentUser()
			const visible = this.portfolioProjects.filter((project) => canSeeProjectMoney(project, user))
			if (!this.portfolio || !visible.length) {
				this.moneyLines = []
				this.entriesByProject = {}
				return
			}
			const money = await useProjectsStore().fetchPortfolioMoney(String(this.portfolio.id), visible)
			this.moneyLines = money.lines
			this.entriesByProject = money.entriesByProject
		},

		/**
		 * @param {number} amount The amount.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		euro(amount) {
			return formatEuro(amount)
		},

		/**
		 * @param {{done: number, total: number}} progress The progress.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		progressText(progress) {
			return progress.total
				? this.t('planninq', '{done} of {total} tasks done', progress)
				: this.t('planninq', 'No tasks yet')
		},

		/**
		 * @param {string} status The lifecycle status.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		lifecycleLabel(status) {
			return {
				active: this.t('planninq', 'Active'),
				archived: this.t('planninq', 'Archived'),
				completed: this.t('planninq', 'Completed'),
				cancelled: this.t('planninq', 'Cancelled'),
			}[status] || status || this.t('planninq', 'Active')
		},

		/**
		 * @param {object} project The project.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		budgetText(project) {
			const amount = Number(project.budgetAmount)
			return project.budgetAmount === undefined || project.budgetAmount === null || project.budgetAmount === '' || Number.isNaN(amount)
				? ''
				: amount.toLocaleString(undefined, { style: 'currency', currency: 'EUR' })
		},
	},
}
</script>

<style scoped>
.portfolio-status {
	padding: 8px 4px 24px;
	max-width: 1400px;
}

.portfolio-status__header {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.portfolio-status__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.portfolio-status h3 {
	margin: 16px 0 8px;
	font-size: 18px;
	font-weight: 600;
}

.portfolio-status__picker {
	min-width: 240px;
}

.portfolio-status__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.portfolio-status__scroll {
	overflow-x: auto;
}

.portfolio-status__table {
	border-collapse: collapse;
	width: 100%;
}

.portfolio-status__table th,
.portfolio-status__table td {
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
	text-align: start;
	vertical-align: top;
}

.portfolio-status__table thead th {
	color: var(--color-text-maxcontrast);
	font-weight: 600;
	white-space: nowrap;
}

.portfolio-status__stale {
	display: block;
	color: var(--color-warning-text);
	font-weight: 600;
}

.portfolio-status__muted {
	color: var(--color-text-maxcontrast);
}
</style>
