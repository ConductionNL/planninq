<template>
	<div class="project-overview">
		<NcEmptyContent
			v-if="accessDenied"
			:name="t('planninq', 'You do not have access to this project')"
			:description="t('planninq', 'You are not a member of this project.')">
			<template #icon>
				<LockOutline :size="20" />
			</template>
		</NcEmptyContent>

		<div v-else-if="loading" class="project-overview__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<template v-else-if="project">
			<div class="project-overview__header">
				<h2 class="project-overview__title">
					{{ project.title }}
				</h2>
				<span v-if="project.status" class="project-overview__status" data-testid="overview-status">
					{{ statusLabel }}
				</span>
			</div>

			<ProjectTabs :projectId="projectId" />

			<div class="project-overview__grid">
				<section class="project-overview__block" aria-labelledby="overview-about">
					<h3 id="overview-about">
						{{ t('planninq', 'About this project') }}
					</h3>
					<p v-if="project.description" class="project-overview__description" data-testid="overview-description">
						{{ project.description }}
					</p>
					<p v-else class="project-overview__muted">
						{{ t('planninq', 'No description yet') }}
					</p>
					<dl class="project-overview__dates">
						<dt>{{ t('planninq', 'Start date') }}</dt>
						<dd data-testid="overview-start">
							{{ project.startDate || t('planninq', 'Not set') }}
						</dd>
						<dt>{{ t('planninq', 'End date') }}</dt>
						<dd data-testid="overview-end">
							{{ project.endDate || t('planninq', 'Not set') }}
						</dd>
					</dl>
				</section>

				<section class="project-overview__block" aria-labelledby="overview-progress">
					<h3 id="overview-progress">
						{{ t('planninq', 'Progress') }}
					</h3>
					<template v-if="progress.total > 0">
						<NcProgressBar
							:value="progressPercent"
							size="medium"
							:aria-label="progressText" />
						<p data-testid="overview-progress">
							{{ progressText }}
						</p>
					</template>
					<p v-else data-testid="overview-progress">
						{{ t('planninq', 'No tasks yet') }}
					</p>
					<RouterLink :to="{ name: 'ProjectBoard', params: { id: projectId } }">
						{{ t('planninq', 'Open the board') }}
					</RouterLink>
				</section>

				<section v-if="subprojects.length" class="project-overview__block" aria-labelledby="overview-subprojects">
					<h3 id="overview-subprojects">
						{{ t('planninq', 'Subprojects') }}
					</h3>
					<ul class="project-overview__entries" data-testid="overview-subprojects">
						<li v-for="child in subprojects" :key="child.project.id" data-testid="overview-subproject">
							<RouterLink :to="{ name: 'ProjectOverview', params: { id: child.project.id } }">
								{{ child.project.title }}
							</RouterLink>
							<span class="project-overview__muted">
								{{ child.progress.total ? t('planninq', '{done} of {total}', child.progress) : t('planninq', 'No tasks yet') }}
							</span>
						</li>
					</ul>
				</section>

				<section class="project-overview__block" aria-labelledby="overview-people">
					<h3 id="overview-people">
						{{ t('planninq', 'People') }}
					</h3>
					<ul class="project-overview__people" data-testid="overview-people">
						<li v-for="uid in people" :key="uid" class="project-overview__person">
							<NcAvatar :user="uid"
								:size="28"
								:displayName="names[uid] || uid"
								:hideStatus="true" />
							<span>{{ names[uid] || uid }}</span>
							<span v-if="uid === project.owner" class="project-overview__muted">
								{{ t('planninq', 'Owner') }}
							</span>
						</li>
					</ul>
				</section>

				<section class="project-overview__block" aria-labelledby="overview-risks">
					<h3 id="overview-risks">
						{{ t('planninq', 'Highest open risks') }}
					</h3>
					<ul v-if="topRisks.length" class="project-overview__entries" data-testid="overview-risks">
						<li v-for="risk in topRisks" :key="risk.id">
							<span class="project-overview__chip">{{ risk.score }}</span>
							<span>{{ risk.title }}</span>
						</li>
					</ul>
					<p v-else class="project-overview__muted">
						{{ t('planninq', 'No open risks') }}
					</p>
					<RouterLink :to="{ name: 'ProjectRisks', params: { id: projectId } }" data-testid="overview-risks-link">
						{{ risks.length ? t('planninq', 'Open the risk register') : t('planninq', 'Add the first risk') }}
					</RouterLink>
				</section>

				<section class="project-overview__block" aria-labelledby="overview-log">
					<h3 id="overview-log">
						{{ t('planninq', 'Latest log entries') }}
					</h3>
					<ul v-if="latestEntries.length" class="project-overview__entries" data-testid="overview-log">
						<li v-for="entry in latestEntries" :key="entry.id">
							<span class="project-overview__chip">{{ typeLabel(entry.type) }}</span>
							<span>{{ entry.title }}</span>
							<span class="project-overview__muted">{{ entry.date }}</span>
						</li>
					</ul>
					<p v-else class="project-overview__muted">
						{{ t('planninq', 'Nothing logged yet') }}
					</p>
					<RouterLink
						:to="{ name: 'ProjectLog', params: { id: projectId }, query: latestEntries.length ? {} : { add: '1' } }"
						data-testid="overview-log-link">
						{{ latestEntries.length ? t('planninq', 'Open the log') : t('planninq', 'Add the first entry') }}
					</RouterLink>
				</section>
			</div>
		</template>
	</div>
</template>

<script>
/**
 * ProjectOverview.
 *
 * One screen that answers "where does this project stand": description,
 * planned dates, the people on it by name, progress (done tasks against every
 * task that is not cancelled), the three highest open risks and the five
 * latest log entries, each block linking to its tab.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
import { NcAvatar, NcEmptyContent, NcLoadingIcon, NcProgressBar } from '@nextcloud/vue'
import LockOutline from 'vue-material-design-icons/LockOutline.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import { useProjectsStore } from '../store/projects.js'
import { latestLogEntries, projectPeople, projectProgress } from '../utils/projectOverview.js'
import { descendantsOf, rollupProgress, subprojectsOf } from '../utils/projectTree.js'
import { topOpenRisks } from '../utils/riskHelpers.js'
import { displayNames } from '../utils/userNames.js'

export default {
	name: 'ProjectOverview',

	components: {
		LockOutline,
		NcAvatar,
		NcEmptyContent,
		NcLoadingIcon,
		NcProgressBar,
		ProjectTabs,
	},

	data() {
		return {
			project: null,
			tasks: [],
			childProgress: [],
			entries: [],
			risks: [],
			names: {},
			loading: true,
			accessDenied: false,
		}
	},

	computed: {
		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/**
		 * @return {{done: number, total: number}}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		progress() {
			return rollupProgress(projectProgress(this.tasks), this.childProgress.map((child) => child.subtree))
		},

		/**
		 * The direct subprojects with their own progress, their subprojects included.
		 *
		 * @return {Array<{project: object, progress: object}>}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.2
		 */
		subprojects() {
			return this.childProgress.filter((child) => child.direct).map((child) => ({ project: child.project, progress: child.subtree }))
		},

		/**
		 * @return {number}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		progressPercent() {
			return this.progress.total ? Math.round((this.progress.done / this.progress.total) * 100) : 0
		},

		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		progressText() {
			const figures = { done: this.progress.done, total: this.progress.total }
			return this.childProgress.length
				? this.t('planninq', '{done} of {total} tasks done, including subprojects', figures)
				: this.t('planninq', '{done} of {total} tasks done', figures)
		},

		/**
		 * @return {Array<string>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		people() {
			return projectPeople(this.project)
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		topRisks() {
			return topOpenRisks(this.risks, 3)
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		latestEntries() {
			return latestLogEntries(this.entries, 5)
		},

		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		statusLabel() {
			const labels = {
				active: this.t('planninq', 'Active'),
				archived: this.t('planninq', 'Archived'),
				completed: this.t('planninq', 'Completed'),
				cancelled: this.t('planninq', 'Cancelled'),
			}
			return labels[this.project?.status] || this.project?.status || ''
		},
	},

	watch: {
		projectId: {
			immediate: true,
			/**
			 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		/**
		 * Load the project, its tasks and its log.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		async load() {
			this.loading = true
			this.accessDenied = false
			const store = useProjectsStore()
			try {
				const project = await store.fetchProject(this.projectId)
				if (!project) {
					this.accessDenied = store.error === 'forbidden'
					this.project = null
					return
				}
				this.project = project
				const [tasks, entries, risks] = await Promise.all([
					store.fetchTasks(this.projectId),
					store.fetchLogEntries(this.projectId),
					store.fetchRisks(this.projectId),
				])
				this.tasks = tasks
				this.childProgress = await this.loadSubprojects(store)
				this.entries = entries
				this.risks = risks
				this.names = await displayNames(this.people)
			} finally {
				this.loading = false
			}
		},

		/**
		 * The subprojects the user can read, each with the progress of its own subtree.
		 * Only the direct ones are listed; the deeper ones count through their parent.
		 *
		 * @param {object} store The projects store.
		 * @return {Promise<Array<{project: object, direct: boolean, subtree: object}>>}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.2
		 */
		async loadSubprojects(store) {
			const projects = await store.fetchProjects()
			const below = descendantsOf(projects, this.projectId)
			if (!below.length) {
				return []
			}
			const tasks = await Promise.all(below.map((child) => store.fetchTasks(child.id)))
			const own = Object.fromEntries(below.map((child, i) => [String(child.id), projectProgress(tasks[i])]))
			const subtree = (id) => rollupProgress(own[id], subprojectsOf(below, id).map((child) => subtree(String(child.id))))
			return subprojectsOf(below, this.projectId).map((child) => ({ project: child, direct: true, subtree: subtree(String(child.id)) }))
		},

		/**
		 * The label of a log entry type.
		 *
		 * @param {string} type The type.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
		 */
		typeLabel(type) {
			const labels = {
				issue: this.t('planninq', 'Issue'),
				lesson: this.t('planninq', 'Lesson learned'),
				meeting: this.t('planninq', 'Meeting'),
				decision: this.t('planninq', 'Decision'),
			}
			return labels[type] || type
		},
	},
}
</script>

<style scoped>
.project-overview {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.project-overview__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.project-overview__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 12px;
	margin-bottom: 12px;
}

.project-overview__title {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-overview__status,
.project-overview__chip {
	padding: 2px 8px;
	border-radius: var(--border-radius-pill);
	background-color: var(--color-background-dark);
	font-size: 13px;
}

.project-overview__grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 16px;
}

.project-overview__block {
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.project-overview__block h3 {
	margin: 0 0 8px;
	font-size: 16px;
	font-weight: 600;
}

.project-overview__block a {
	color: var(--color-primary-element);
	text-decoration: underline;
}

.project-overview__description {
	white-space: pre-wrap;
}

.project-overview__muted {
	color: var(--color-text-maxcontrast);
}

.project-overview__dates {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 4px 12px;
	margin: 12px 0 0;
}

.project-overview__dates dd {
	margin: 0;
}

.project-overview__people,
.project-overview__entries {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin: 0 0 12px;
	padding: 0;
	list-style: none;
}

.project-overview__person,
.project-overview__entries li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}
</style>
