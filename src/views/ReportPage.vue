<template>
	<div class="report-page">
		<div class="report-page__header">
			<h2>{{ report.title || t('planninq', 'Report') }}</h2>
			<NcButton v-if="isOwner" data-testid="report-edit" @click="editing = true">
				{{ t('planninq', 'Edit report') }}
			</NcButton>
		</div>
		<p v-if="report.description" class="report-page__description">
			{{ report.description }}
		</p>

		<div v-if="loading" class="report-page__loading">
			<NcLoadingIcon :size="32" />
		</div>
		<NcNoteCard v-else-if="error" type="error">
			{{ error }}
		</NcNoteCard>
		<template v-else>
			<NcNoteCard v-if="visibility.hidden" type="info" data-testid="report-hidden">
				{{ t('planninq', '{hidden} of {total} projects in this report are not visible to you', { hidden: visibility.hidden, total: visibility.total }) }}
			</NcNoteCard>
			<p v-if="!buckets.length" class="report-page__empty">
				{{ t('planninq', 'No tasks match this report.') }}
			</p>
			<table v-else-if="report.display === 'table'" class="report-page__table" data-testid="report-table">
				<thead>
					<tr>
						<th scope="col">
							{{ groupLabel }}
						</th>
						<th scope="col">
							{{ report.metric === 'sum' ? t('planninq', 'Total') : t('planninq', 'Tasks') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="bucket in buckets" :key="bucket.key">
						<th scope="row">
							{{ bucket.key || t('planninq', 'None') }}
						</th>
						<td>{{ bucket.value }}</td>
					</tr>
				</tbody>
			</table>
			<div v-else-if="report.display === 'donut'" class="report-page__donut" data-testid="report-donut">
				<svg viewBox="0 0 42 42" role="img" :aria-label="report.title">
					<circle
						v-for="(segment, index) in segments"
						:key="segment.key"
						cx="21"
						cy="21"
						r="15.9155"
						fill="transparent"
						stroke-width="6"
						:stroke="shade(index)"
						:stroke-dasharray="segment.dash"
						:stroke-dashoffset="segment.offset" />
				</svg>
				<ul class="report-page__legend">
					<li v-for="(segment, index) in segments" :key="segment.key" data-testid="report-bar">
						<span class="report-page__swatch" :style="{ background: shade(index) }" />
						{{ segment.key || t('planninq', 'None') }}: {{ segment.value }}
					</li>
				</ul>
			</div>
			<ul v-else class="report-page__bars" data-testid="report-bars">
				<li v-for="bucket in buckets" :key="bucket.key" class="report-page__bar-row" data-testid="report-bar">
					<span class="report-page__bar-label">{{ bucket.key || t('planninq', 'None') }}</span>
					<span class="report-page__bar" :style="{ width: barWidth(bucket.value) }" />
					<span class="report-page__bar-value">{{ bucket.value }}</span>
				</li>
			</ul>
		</template>

		<ReportBuilderDialog
			v-if="editing"
			:report="report"
			:projects="projects"
			@close="editing = false"
			@saved="onSaved" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import ReportBuilderDialog from '../dialogs/ReportBuilderDialog.vue'
import { fetchReport, runReport } from '../api/reports.js'
import { useProjectsStore } from '../store/projects.js'
import { donutSegments, visibleProjects } from '../utils/reportBuilder.js'

/**
 * One saved report, run with the viewer's own rights: only the projects the
 * viewer can read are counted, and the page says how many are left out.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export default {
	name: 'ReportPage',
	components: { NcButton, NcLoadingIcon, NcNoteCard, ReportBuilderDialog },
	data() {
		return { report: {}, projects: [], buckets: [], loading: true, error: '', editing: false }
	},
	computed: {
		/**
		 * @return {{hidden: number, total: number, visible: string[]}} The report's projects split by visibility.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		visibility() {
			return visibleProjects(this.report.projects || [], this.projects.map((project) => project.id))
		},
		/**
		 * @return {boolean} Whether the viewer owns the report.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		isOwner() {
			return !!this.report.owner && this.report.owner === getCurrentUser()?.uid
		},
		/**
		 * @return {Array<object>} The donut's segments.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		segments() {
			return donutSegments(this.buckets)
		},
		/**
		 * @return {string} The grouping's column header.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		groupLabel() {
			const labels = {
				status: this.t('planninq', 'Status'),
				priority: this.t('planninq', 'Priority'),
				assignedTo: this.t('planninq', 'Assignee'),
				labels: this.t('planninq', 'Label'),
				project: this.t('planninq', 'Project'),
				column: this.t('planninq', 'Column'),
			}
			return labels[this.report.groupBy] || ''
		},
	},
	watch: {
		/**
		 * Reload on another report.
		 *
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		'$route.params.id'() {
			this.load()
		},
	},
	/**
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
	 */
	mounted() {
		this.load()
	},
	methods: {
		/**
		 * Read the report and the viewer's projects, then run it.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		async load() {
			this.loading = true
			this.error = ''
			try {
				const store = useProjectsStore()
				const [report] = await Promise.all([fetchReport(this.$route.params.id), store.fetchProjects()])
				this.report = report
				this.projects = (store.projects || []).map((project) => ({ id: project.id ?? project['@self']?.id, title: project.title }))
				this.buckets = await runReport(this.report, this.visibility.visible)
			} catch (e) {
				this.error = this.t('planninq', 'Could not load this report.')
			} finally {
				this.loading = false
			}
		},
		/**
		 * @param {object} saved The saved report
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		onSaved(saved) {
			this.editing = false
			this.report = { ...this.report, ...saved }
			this.load()
		},
		/**
		 * @param {number} index A segment's place
		 * @return {string} Its colour, a ramp of the primary colour
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		shade(index) {
			const share = this.segments.length > 1 ? 30 + Math.round((index / (this.segments.length - 1)) * 70) : 100
			return `color-mix(in srgb, var(--color-primary-element) ${share}%, var(--color-main-background))`
		},
		/**
		 * @param {number} value A bucket's value
		 * @return {string} Its bar's width
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		barWidth(value) {
			const top = Math.max(1, ...this.buckets.map((bucket) => bucket.value))
			return `${Math.round((value / top) * 100)}%`
		},
	},
}
</script>

<style scoped>
.report-page {
	padding: 16px 24px;
}

.report-page__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.report-page__bars {
	list-style: none;
	padding: 0;
	max-width: 720px;
}

.report-page__bar-row {
	display: grid;
	grid-template-columns: minmax(120px, 1fr) 3fr auto;
	align-items: center;
	gap: 8px;
	margin-bottom: 6px;
}

.report-page__bar {
	display: block;
	height: 20px;
	border-radius: var(--border-radius);
	background: var(--color-primary-element);
}

.report-page__donut {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 24px;
}

.report-page__donut svg {
	width: 200px;
	height: 200px;
}

.report-page__legend {
	list-style: none;
	padding: 0;
}

.report-page__swatch {
	display: inline-block;
	width: 12px;
	height: 12px;
	margin-inline-end: 6px;
	border-radius: 2px;
}

.report-page__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}
</style>
