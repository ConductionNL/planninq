<template>
	<div class="project-status">
		<div class="project-status__header">
			<h2>{{ t('planninq', 'Status') }}</h2>
			<NcButton
				v-if="canWrite"
				variant="primary"
				data-testid="status-report-new"
				@click="writing = true">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('planninq', 'New report') }}
			</NcButton>
		</div>

		<ProjectTabs :projectId="projectId" />

		<div v-if="loading" class="project-status__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<template v-else>
			<p v-if="!canWrite" class="project-status__muted">
				{{ t('planninq', 'Only the project owner writes status reports.') }}
			</p>

			<section v-if="latest" class="project-status__latest" data-testid="status-latest">
				<h3>
					{{ t('planninq', 'Latest report, {date}', { date: latest.reportDate }) }}
				</h3>
				<p class="project-status__meta">
					<span data-testid="status-latest-author">{{ authorName(latest) }}</span>
					<span>{{ t('planninq', 'Overall') }}: <HealthStatus :status="overallOf(latest)" data-testid="status-latest-overall" /></span>
				</p>
				<table class="project-status__table">
					<thead>
						<tr>
							<th scope="col">
								{{ t('planninq', 'Aspect') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Status') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Note') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="aspect in aspects" :key="aspect.id" :data-testid="`status-latest-${aspect.id}`">
							<th scope="row">
								{{ aspect.label }}
							</th>
							<td><HealthStatus :status="latest[`status${aspect.key}`]" /></td>
							<td>{{ latest[`note${aspect.key}`] || '' }}</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section v-if="sortedReports.length" class="project-status__history">
				<h3>{{ t('planninq', 'Report history') }}</h3>
				<table class="project-status__table" data-testid="status-history">
					<thead>
						<tr>
							<th scope="col">
								{{ t('planninq', 'Date') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Author') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Overall') }}
							</th>
							<th v-for="aspect in aspects" :key="aspect.id" scope="col">
								{{ aspect.label }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="report in sortedReports"
							:key="report.id"
							data-testid="status-history-row"
							:data-date="report.reportDate">
							<td>{{ report.reportDate }}</td>
							<td>{{ authorName(report) }}</td>
							<td><HealthStatus :status="overallOf(report)" /></td>
							<td v-for="aspect in aspects" :key="aspect.id">
								<HealthStatus :status="report[`status${aspect.key}`]" />
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<NcEmptyContent v-else :name="t('planninq', 'No status reports yet')">
				<template v-if="canWrite" #action>
					<NcButton @click="writing = true">
						{{ t('planninq', 'Write the first report') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</template>

		<StatusReportDialog
			v-if="writing"
			:projectId="projectId"
			:suggestions="suggestions"
			@close="writing = false"
			@saved="onSaved" />
	</div>
</template>

<script>
/**
 * ProjectStatus.
 *
 * The project's status reports: the latest one per aspect with its notes, the
 * history newest first, and a New report button for the owner. The form
 * suggests money, time and risk from the project's own data.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import HealthStatus from '../components/HealthStatus.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import StatusReportDialog from '../dialogs/StatusReportDialog.vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { useProjectsStore } from '../store/projects.js'
import { canSeeProjectMoney, projectMoney } from '../utils/finance.js'
import { entryAuthor } from '../utils/projectOverview.js'
import { parseRiskScale } from '../utils/riskHelpers.js'
import { aspectKey, ASPECTS, sortReports, suggestMoney, suggestRisk, suggestTime, worstStatus } from '../utils/statusReports.js'
import { displayNames } from '../utils/userNames.js'

export default {
	name: 'ProjectStatus',

	components: {
		HealthStatus,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		PlusIcon,
		ProjectTabs,
		StatusReportDialog,
	},

	data() {
		return {
			project: null,
			reports: [],
			tasks: [],
			risks: [],
			cost: null,
			names: {},
			loading: true,
			writing: false,
			settingsStore: useSettingsStore(),
		}
	},

	computed: {
		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/**
		 * @return {Array<{id: string, key: string, label: string}>}
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
			return ASPECTS.map((id) => ({ id, key: aspectKey(id), label: labels[id] }))
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		sortedReports() {
			return sortReports(this.reports)
		},

		/**
		 * @return {object|null}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		latest() {
			return this.sortedReports[0] || null
		},

		/**
		 * The owner and admins write reports; the server enforces the same rule.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		canWrite() {
			const user = getCurrentUser()
			return Boolean(user && (user.isAdmin || (this.project && this.project.owner === user.uid)))
		},

		/**
		 * Suggestions for money, time and risk. The money suggestion compares
		 * the budget with the actual cost, labour included, for those who may
		 * see the money; for anyone else there is no cost and no suggestion.
		 *
		 * @return {object}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		suggestions() {
			return {
				money: suggestMoney({ budget: this.project?.budgetAmount, cost: this.cost }),
				time: suggestTime(this.tasks, this.project),
				risk: suggestRisk(this.risks, parseRiskScale(this.settingsStore.settings?.risk_scale)),
			}
		},
	},

	watch: {
		projectId: {
			immediate: true,
			/**
			 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		/**
		 * The project's actual cost, labour included, or null when the viewer may not see its money.
		 *
		 * @param {object|null} project The project.
		 * @return {Promise<number|null>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		async loadCost(project) {
			if (!canSeeProjectMoney(project, getCurrentUser())) {
				return null
			}
			const store = useProjectsStore()
			const [lines, entries] = await Promise.all([
				store.fetchFinanceLines(this.projectId),
				store.fetchProjectTimeEntries(this.projectId),
			])
			return projectMoney(project, lines, entries).actual
		},

		/**
		 * Load the project, its reports, tasks and risks, and the risk scale.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		async load() {
			this.loading = true
			const store = useProjectsStore()
			try {
				const [project, reports, tasks, risks] = await Promise.all([
					store.fetchProject(this.projectId),
					store.fetchStatusReports(this.projectId),
					store.fetchTasks(this.projectId),
					store.fetchRisks(this.projectId),
					this.settingsStore.settings?.risk_scale ? Promise.resolve() : this.settingsStore.fetchSettings(),
				])
				this.project = project
				this.reports = reports
				this.tasks = Array.isArray(tasks) ? tasks : []
				this.risks = risks
				this.cost = await this.loadCost(project)
				this.names = await displayNames([...new Set(reports.map((report) => entryAuthor(report)).filter(Boolean))])
			} finally {
				this.loading = false
			}
		},

		/**
		 * @param {object} saved The saved report, with the overall the server calculated.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		async onSaved(saved) {
			this.writing = false
			const id = saved?.id ?? saved?.['@self']?.id
			this.reports = [{ ...saved, id }, ...this.reports.filter((report) => report.id !== id)]
			const author = entryAuthor(saved)
			if (author && !this.names[author]) {
				this.names = { ...this.names, ...(await displayNames([author])) }
			}
		},

		/**
		 * @param {object} report The report.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		authorName(report) {
			const uid = entryAuthor(report)
			return this.names[uid] || uid
		},

		/**
		 * The server's overall status, or the worst of the six for a report saved before it was calculated.
		 *
		 * @param {object} report The report.
		 * @return {string|null}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		overallOf(report) {
			return report?.overall || worstStatus(ASPECTS.map((aspect) => report?.[`status${aspectKey(aspect)}`]))
		},
	},
}
</script>

<style scoped>
.project-status {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.project-status__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.project-status__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-status h3 {
	margin: 16px 0 8px;
	font-size: 18px;
	font-weight: 600;
}

.project-status__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.project-status__meta {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
	margin: 0 0 8px;
}

.project-status__muted {
	color: var(--color-text-maxcontrast);
}

.project-status__history {
	overflow-x: auto;
}

.project-status__table {
	width: 100%;
	border-collapse: collapse;
}

.project-status__table th,
.project-status__table td {
	padding: 8px;
	border-bottom: 1px solid var(--color-border);
	text-align: start;
	vertical-align: top;
}
</style>
