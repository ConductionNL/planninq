<template>
	<div class="portfolio-flow">
		<div class="portfolio-flow__header">
			<h2>{{ t('planninq', 'Portfolio flow') }}</h2>
			<div class="portfolio-flow__controls">
				<NcSelect
					v-if="portfolios.length"
					:modelValue="selectedOption"
					:options="portfolioOptions"
					:clearable="false"
					:inputLabel="t('planninq', 'Portfolio')"
					label="label"
					data-testid="portfolio-flow-picker"
					@update:modelValue="pick" />
				<NcSelect
					:modelValue="period"
					:options="periods"
					:clearable="false"
					:inputLabel="t('planninq', 'Period')"
					label="label"
					@update:modelValue="pickPeriod" />
			</div>
		</div>

		<div v-if="loading" class="portfolio-flow__loading">
			<NcLoadingIcon :size="32" />
		</div>
		<NcEmptyContent
			v-else-if="!portfolios.length"
			:name="t('planninq', 'No portfolios yet')"
			:description="t('planninq', 'An admin adds portfolios under Projects, Portfolios.')" />
		<NcNoteCard v-else-if="error" type="error">
			{{ error }}
		</NcNoteCard>
		<template v-else-if="flow">
			<fieldset class="portfolio-flow__projects" data-testid="portfolio-flow-projects">
				<legend>{{ t('planninq', 'Projects') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="project in flow.projects"
					:key="project.projectId"
					:modelValue="!excluded.includes(project.projectId)"
					@update:modelValue="toggle(project.projectId)">
					{{ project.title }}
				</NcCheckboxRadioSwitch>
				<p v-if="flow.skipped" class="portfolio-flow__note">
					{{ t('planninq', 'Projects not shown, because at most 50 are shown at a time: {count}', { count: flow.skipped }) }}
				</p>
			</fieldset>
			<FlowCharts
				:columns="combined.columns"
				:flowDays="combined.days"
				:finished="combined.finished"
				:summary="combined.summary"
				:withoutHistory="combined.withoutHistory" />
		</template>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import FlowCharts from '../components/FlowCharts.vue'
import { fetchPortfolioFlow } from '../api/flow.js'
import { useProjectsStore } from '../store/projects.js'
import { combineProjectFlows, flowPeriods, periodWindow } from '../utils/flowChart.js'

/**
 * Flow across a portfolio: every project the viewer can read, with a filter
 * per project that recalculates the charts and times without a reload.
 *
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
 */
export default {
	name: 'PortfolioFlow',
	components: { FlowCharts, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon, NcNoteCard, NcSelect },
	data() {
		const periods = flowPeriods(this.t)
		return { periods, period: periods[1], portfolios: [], loading: true, error: '', flow: null, excluded: [] }
	},

	computed: {
		/**
		 * @return {Array<{id: string, label: string}>} The portfolios to pick from.
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
		 */
		portfolioOptions() {
			return this.portfolios.map((portfolio) => ({ id: portfolio.id ?? portfolio['@self']?.id, label: portfolio.title || portfolio.name || '' }))
		},

		/**
		 * @return {object|null} The picked portfolio's option.
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
		 */
		selectedOption() {
			return this.portfolioOptions.find((option) => option.id === this.$route.query.portfolio) || null
		},

		/**
		 * @return {object} The ticked projects' flows added up.
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
		 */
		combined() {
			return combineProjectFlows((this.flow?.projects || []).filter((project) => !this.excluded.includes(project.projectId)))
		},
	},

	watch: {
		/**
		 * Reload on another portfolio.
		 *
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
		 */
		'$route.query.portfolio': function() {
			this.load()
		},
	},

	/**
	 * @spec exclude Lifecycle glue: loads the portfolios, then the flow.
	 */
	async mounted() {
		try {
			this.portfolios = await useProjectsStore().fetchPortfolios()
		} finally {
			this.loading = false
		}
		if (!this.$route.query.portfolio && this.portfolioOptions.length) {
			this.pick(this.portfolioOptions[0])
		} else {
			this.load()
		}
	},

	methods: {
		/**
		 * @param {{id: string}} option The picked portfolio
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
		 */
		pick(option) {
			if (option?.id && option.id !== this.$route.query.portfolio) {
				this.$router.replace({ query: { ...this.$route.query, portfolio: option.id } })
			}
		},

		/**
		 * @param {{days: number}} option The picked period
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
		 */
		pickPeriod(option) {
			if (option) {
				this.period = option
				this.load()
			}
		},

		/**
		 * @param {string} id A project to leave out or take back in
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
		 */
		toggle(id) {
			this.excluded = this.excluded.includes(id) ? this.excluded.filter((one) => one !== id) : [...this.excluded, id]
		},

		/**
		 * @return {Promise<void>}
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
		 */
		async load() {
			const portfolioId = this.$route.query.portfolio
			if (!portfolioId) {
				return
			}
			this.error = ''
			this.excluded = []
			const { from, to } = periodWindow(this.period.days, new Date())
			try {
				this.flow = await fetchPortfolioFlow(portfolioId, from, to)
			} catch {
				this.error = this.t('planninq', 'Could not load the flow of this portfolio.')
			}
		},
	},
}
</script>

<style scoped>
.portfolio-flow {
	padding: 8px 4px 24px;
}

.portfolio-flow__header,
.portfolio-flow__controls {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.portfolio-flow__projects {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 16px;
	margin: 12px 0;
}

.portfolio-flow__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.portfolio-flow__note {
	flex: 1 1 100%;
	color: var(--color-text-maxcontrast);
}
</style>
