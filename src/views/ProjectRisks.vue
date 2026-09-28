<template>
	<div class="project-risks">
		<div class="project-risks__header">
			<h2>{{ t('planninq', 'Risks') }}</h2>
			<NcButton variant="primary" data-testid="risk-add" @click="editing = { risk: null }">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('planninq', 'Add risk') }}
			</NcButton>
		</div>

		<ProjectTabs :projectId="projectId" />

		<div v-if="loading" class="project-risks__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<template v-else>
			<RiskHeatMap :risks="risks" :scale="scale" />

			<table v-if="sortedRisks.length" class="project-risks__list" data-testid="risk-list">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Risk') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Score') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Status') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Response') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Owner') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Review date') }}
						</th>
						<th scope="col">
							<span class="hidden-visually">{{ t('planninq', 'Actions') }}</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="risk in sortedRisks"
						:key="risk.id"
						data-testid="risk-row"
						:data-title="risk.title">
						<td>
							<strong>{{ risk.title }}</strong>
							<p v-if="risk.countermeasures" class="project-risks__muted">
								{{ risk.countermeasures }}
							</p>
						</td>
						<td data-testid="risk-score">
							{{ risk.score }}
							<span class="project-risks__muted">{{ bandLabel(risk) }}</span>
						</td>
						<td>{{ statusLabel(risk.status) }}</td>
						<td>{{ responseLabel(risk.response) }}</td>
						<td>
							<template v-if="risk.owner">
								{{ names[risk.owner] || risk.owner }}
								<span v-if="!people.includes(risk.owner)" class="project-risks__warning">
									{{ t('planninq', 'no longer on the project') }}
								</span>
							</template>
						</td>
						<td>{{ risk.reviewDate || '' }}</td>
						<td>
							<NcButton
								variant="tertiary"
								:aria-label="t('planninq', 'Edit {title}', { title: risk.title })"
								@click="editing = { risk }">
								<template #icon>
									<PencilIcon :size="20" />
								</template>
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<NcEmptyContent v-else :name="t('planninq', 'No risks recorded yet')">
				<template #action>
					<NcButton @click="editing = { risk: null }">
						{{ t('planninq', 'Add the first risk') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</template>

		<RiskEditDialog
			v-if="editing"
			:risk="editing.risk"
			:projectId="projectId"
			:scale="scale"
			:people="peopleOptions"
			@close="editing = null"
			@saved="onSaved" />
	</div>
</template>

<script>
/**
 * ProjectRisks.
 *
 * The project's risk register: a heat map of likelihood against impact on the
 * admin's scale and a list sorted by the server-calculated score. An owner who
 * is no longer on the project is marked, so a manager can reassign the risk.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import RiskHeatMap from '../components/RiskHeatMap.vue'
import RiskEditDialog from '../dialogs/RiskEditDialog.vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { useProjectsStore } from '../store/projects.js'
import { projectPeople } from '../utils/projectOverview.js'
import { parseRiskScale, riskBand, sortRisks } from '../utils/riskHelpers.js'
import { displayNames } from '../utils/userNames.js'

export default {
	name: 'ProjectRisks',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		PencilIcon,
		PlusIcon,
		ProjectTabs,
		RiskEditDialog,
		RiskHeatMap,
	},

	data() {
		return {
			project: null,
			risks: [],
			names: {},
			loading: true,
			editing: null,
			settingsStore: useSettingsStore(),
		}
	},

	computed: {
		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/**
		 * @return {object}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		scale() {
			return parseRiskScale(this.settingsStore.settings?.risk_scale)
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		sortedRisks() {
			return sortRisks(this.risks)
		},

		/**
		 * @return {Array<string>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		people() {
			return projectPeople(this.project)
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		peopleOptions() {
			return this.people.map((uid) => ({ id: uid, label: this.names[uid] || uid }))
		},
	},

	watch: {
		projectId: {
			immediate: true,
			/**
			 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		/**
		 * Load the project, its risks and the risk scale.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		async load() {
			this.loading = true
			const store = useProjectsStore()
			try {
				const [project, risks] = await Promise.all([
					store.fetchProject(this.projectId),
					store.fetchRisks(this.projectId),
					this.settingsStore.settings?.risk_scale ? Promise.resolve() : this.settingsStore.fetchSettings(),
				])
				this.project = project
				this.risks = risks
				const uids = new Set(this.people)
				for (const risk of risks) {
					if (risk.owner) {
						uids.add(risk.owner)
					}
				}
				this.names = await displayNames([...uids])
			} finally {
				this.loading = false
			}
		},

		/**
		 * @param {object} saved The saved risk, with the score the server calculated.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		onSaved(saved) {
			this.editing = null
			const id = saved?.id ?? saved?.['@self']?.id
			this.risks = [{ ...saved, id }, ...this.risks.filter((risk) => risk.id !== id)]
		},

		/**
		 * @param {object} risk The risk.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		bandLabel(risk) {
			return {
				low: this.t('planninq', 'Low'),
				medium: this.t('planninq', 'Medium'),
				high: this.t('planninq', 'High'),
			}[riskBand(risk.score, this.scale)]
		},

		/**
		 * @param {string} status The status.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		statusLabel(status) {
			return {
				open: this.t('planninq', 'Open'),
				mitigating: this.t('planninq', 'Mitigating'),
				closed: this.t('planninq', 'Closed'),
				occurred: this.t('planninq', 'Occurred'),
			}[status] || status || ''
		},

		/**
		 * @param {string} response The response.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		responseLabel(response) {
			return {
				avoid: this.t('planninq', 'Avoid'),
				reduce: this.t('planninq', 'Reduce'),
				transfer: this.t('planninq', 'Transfer'),
				accept: this.t('planninq', 'Accept'),
			}[response] || response || ''
		},
	},
}
</script>

<style scoped>
.project-risks {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.project-risks__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.project-risks__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-risks__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.project-risks__list {
	width: 100%;
	border-collapse: collapse;
}

.project-risks__list th,
.project-risks__list td {
	padding: 8px;
	border-bottom: 1px solid var(--color-border);
	text-align: start;
	vertical-align: top;
}

.project-risks__muted {
	margin: 2px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.project-risks__warning {
	display: block;
	color: var(--color-warning-text);
	font-size: 13px;
}
</style>
