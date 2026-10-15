<template>
	<div class="portfolio-timeline">
		<div class="portfolio-timeline__header">
			<h2>{{ t('planninq', 'Portfolio timeline') }}</h2>
			<div class="portfolio-timeline__controls">
				<NcSelect
					v-if="portfolios.length"
					:modelValue="selectedOption"
					class="portfolio-timeline__picker"
					:options="portfolioOptions"
					:clearable="false"
					:inputLabel="t('planninq', 'Portfolio')"
					label="label"
					data-testid="portfolio-timeline-picker"
					@update:modelValue="pick" />
				<NcSelect
					v-model="zoom"
					:options="zoomOptions"
					:clearable="false"
					:inputLabel="t('planninq', 'Zoom')"
					label="label"
					trackBy="value" />
			</div>
		</div>

		<div v-if="loading" class="portfolio-timeline__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="error"
			:name="t('planninq', 'Could not load the timeline')"
			:description="error" />

		<NcEmptyContent
			v-else-if="!portfolios.length"
			:name="t('planninq', 'No portfolios yet')"
			:description="t('planninq', 'An admin adds portfolios under Projects, Portfolios. Projects in a portfolio are shown here on one timeline.')" />

		<NcEmptyContent
			v-else-if="!layout.rows.length"
			:name="t('planninq', 'No project in this portfolio that you can read.')" />

		<template v-else>
			<p class="portfolio-timeline__hint">
				{{ t('planninq', 'Open a project to see its phases and tasks. The timeline is read-only.') }}
			</p>
			<div class="portfolio-timeline__grid">
				<ul class="portfolio-timeline__labels" :style="{ height: layout.barsHeight + 'px' }">
					<li
						v-for="row in layout.rows"
						:key="`${row.kind}-${row.id}`"
						class="portfolio-timeline__label"
						:class="`portfolio-timeline__label--${row.kind}`"
						:style="{ top: row.top + 'px' }">
						<button
							v-if="row.kind === 'project'"
							type="button"
							class="portfolio-timeline__toggle"
							:aria-expanded="String(openIds.includes(row.id))"
							data-testid="portfolio-timeline-project"
							:data-project="row.id"
							@click="toggle(row.id)">
							<ChevronDown v-if="openIds.includes(row.id)" :size="18" />
							<ChevronRight v-else :size="18" />
							<span>{{ row.title }}</span>
						</button>
						<span v-else :data-testid="`portfolio-timeline-${row.kind}`">
							{{ row.kind === 'phase' ? t('planninq', 'Phase: {title}', { title: row.title }) : row.title }}
						</span>
					</li>
				</ul>
				<div class="portfolio-timeline__scroll">
					<div class="portfolio-timeline__chart" :style="{ width: layout.chartWidth + 'px', height: layout.barsHeight + 'px' }">
						<svg
							class="portfolio-timeline__edges"
							:width="layout.chartWidth"
							:height="layout.barsHeight"
							aria-hidden="true">
							<defs>
								<marker
									id="planninq-portfolio-arrow"
									markerWidth="6"
									markerHeight="6"
									refX="5"
									refY="3"
									orient="auto">
									<path d="M0,0 L6,3 L0,6 Z" fill="var(--color-text-maxcontrast)" />
								</marker>
							</defs>
							<line
								v-for="edge in layout.edgeLines"
								:key="edge.key"
								:x1="edge.x1"
								:y1="edge.y1"
								:x2="edge.x2"
								:y2="edge.y2"
								stroke="var(--color-text-maxcontrast)"
								stroke-width="1.5"
								marker-end="url(#planninq-portfolio-arrow)" />
						</svg>
						<div
							v-for="row in datedRows"
							:key="`bar-${row.kind}-${row.id}`"
							class="portfolio-timeline__bar"
							:class="`portfolio-timeline__bar--${row.kind}`"
							:data-testid="`portfolio-timeline-bar-${row.kind}`"
							:style="{ left: row.left + 'px', width: row.width + 'px', top: row.top + 'px', backgroundColor: row.color || null }"
							:title="row.title" />
					</div>
				</div>
			</div>
			<p v-if="skipped" class="portfolio-timeline__muted">
				{{ t('planninq', 'Left out because you cannot read them: {count} projects', { count: skipped }) }}
			</p>
		</template>
	</div>
</template>

<script>
/**
 * PortfolioTimeline.
 *
 * The projects of one portfolio on one time axis: a summary bar per project,
 * sorted by start date, that opens into its phases and task bars by click or
 * Enter on the project's button. Dependencies between tasks of any two
 * projects are drawn when both ends are shown. Read-only; the data comes from
 * one GET /api/timeline request per fifty projects.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
import { NcEmptyContent, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import { fetchPortfolioTimeline } from '../api/timeline.js'
import { useProjectsStore } from '../store/projects.js'
import { filterByPortfolio, sortPortfolios } from '../utils/portfolioGrouping.js'
import { buildPortfolioLayout } from '../utils/portfolioTimeline.js'
import { PX_PER_DAY } from '../utils/timelineHelpers.js'

export default {
	name: 'PortfolioTimeline',

	components: {
		ChevronDown,
		ChevronRight,
		NcEmptyContent,
		NcLoadingIcon,
		NcSelect,
	},

	data() {
		return {
			loading: true,
			error: null,
			portfolios: [],
			projects: [],
			timeline: { projects: [], dependencies: [], skipped: [] },
			openIds: [],
			zoom: { value: 'week', label: this.t('planninq', 'Week') },
			zoomOptions: [
				{ value: 'day', label: this.t('planninq', 'Day') },
				{ value: 'week', label: this.t('planninq', 'Week') },
				{ value: 'month', label: this.t('planninq', 'Month') },
			],
		}
	},

	computed: {
		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		portfolioOptions() {
			return sortPortfolios(this.portfolios).map((p) => ({ id: String(p.id), label: String(p.title ?? '') }))
		},

		/**
		 * @return {object|null}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		portfolio() {
			const wanted = String(this.$route.query.portfolio || '')
			const sorted = sortPortfolios(this.portfolios)
			return sorted.find((p) => String(p.id) === wanted) || sorted[0] || null
		},

		/**
		 * @return {{id: string, label: string}|null}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		selectedOption() {
			return this.portfolioOptions.find((option) => option.id === String(this.portfolio?.id)) || null
		},

		/**
		 * @return {Array<string>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		projectIds() {
			return this.portfolio ? filterByPortfolio(this.projects, String(this.portfolio.id)).map((p) => p.id) : []
		},

		/**
		 * @return {object}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		layout() {
			return buildPortfolioLayout(this.timeline.projects, this.openIds, this.timeline.dependencies, PX_PER_DAY[this.zoom?.value] || PX_PER_DAY.week)
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		datedRows() {
			return this.layout.rows.filter((row) => row.dated)
		},

		/**
		 * @return {number}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		skipped() {
			return this.timeline.skipped.length
		},
	},

	watch: {
		/**
		 * @spec exclude Data glue: reload the timeline when another portfolio is picked.
		 */
		projectIds() {
			this.load()
		},
	},

	/**
	 * @spec exclude Lifecycle glue: loads the portfolios and projects.
	 */
	async mounted() {
		const store = useProjectsStore()
		try {
			const [portfolios] = await Promise.all([store.fetchPortfolios(), store.fetchProjects()])
			this.portfolios = portfolios
			this.projects = store.projects
		} finally {
			this.loading = false
		}
	},

	methods: {
		/**
		 * @param {{id: string}} option The picked portfolio.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		pick(option) {
			if (option?.id && option.id !== this.$route.query.portfolio) {
				this.openIds = []
				this.$router.replace({ query: { ...this.$route.query, portfolio: option.id } })
			}
		},

		/**
		 * @param {string} id The project id.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		toggle(id) {
			this.openIds = this.openIds.includes(id) ? this.openIds.filter((one) => one !== id) : [...this.openIds, id]
		},

		/**
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
		 */
		async load() {
			this.error = null
			try {
				this.timeline = await fetchPortfolioTimeline(this.projectIds)
			} catch (err) {
				console.error('PortfolioTimeline: could not load the timeline', err)
				this.error = this.t('planninq', 'Please try again later.')
			}
		},
	},
}
</script>

<style scoped>
.portfolio-timeline {
	padding: 8px 4px 24px;
}

.portfolio-timeline__header {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.portfolio-timeline__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.portfolio-timeline__controls {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

.portfolio-timeline__picker {
	min-width: 240px;
}

.portfolio-timeline__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.portfolio-timeline__hint,
.portfolio-timeline__muted {
	color: var(--color-text-maxcontrast);
}

.portfolio-timeline__grid {
	display: flex;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.portfolio-timeline__labels {
	position: relative;
	flex: 0 0 240px;
	margin: 0;
	padding: 0;
	list-style: none;
	border-inline-end: 1px solid var(--color-border);
}

.portfolio-timeline__label {
	position: absolute;
	inset-inline: 8px;
	height: 28px;
	display: flex;
	align-items: center;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}

.portfolio-timeline__label--phase,
.portfolio-timeline__label--task {
	padding-inline-start: 24px;
	color: var(--color-text-maxcontrast);
}

.portfolio-timeline__toggle {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 4px;
	border: none;
	border-radius: var(--border-radius-element);
	background: none;
	color: var(--color-main-text);
	font: inherit;
	font-weight: 600;
	cursor: pointer;
}

.portfolio-timeline__toggle:hover {
	background-color: var(--color-background-hover);
}

.portfolio-timeline__toggle:focus-visible {
	outline: 2px solid var(--color-primary-element);
}

.portfolio-timeline__scroll {
	flex: 1 1 auto;
	overflow-x: auto;
}

.portfolio-timeline__chart {
	position: relative;
}

.portfolio-timeline__edges {
	position: absolute;
	inset: 0;
	pointer-events: none;
}

.portfolio-timeline__bar {
	position: absolute;
	height: 28px;
	border-radius: var(--border-radius-element);
}

.portfolio-timeline__bar--project {
	background-color: var(--color-primary-element);
}

.portfolio-timeline__bar--phase {
	background-color: var(--color-primary-element-light);
	border: 1px solid var(--color-primary-element);
}
</style>
