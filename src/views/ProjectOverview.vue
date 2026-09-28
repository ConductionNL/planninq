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

				<section class="project-overview__block" aria-labelledby="overview-people">
					<h3 id="overview-people">
						{{ t('planninq', 'People') }}
					</h3>
					<ul class="project-overview__people" data-testid="overview-people">
						<li v-for="uid in people" :key="uid" class="project-overview__person">
							<NcAvatar :user="uid" :size="28" :displayName="names[uid] || uid" :hideStatus="true" />
							<span>{{ names[uid] || uid }}</span>
							<span v-if="uid === project.owner" class="project-overview__muted">
								{{ t('planninq', 'Owner') }}
							</span>
						</li>
					</ul>
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
 * task that is not cancelled) and the latest log entries, each block linking
 * to its tab.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
import { NcAvatar, NcEmptyContent, NcLoadingIcon, NcProgressBar } from '@nextcloud/vue'
import LockOutline from 'vue-material-design-icons/LockOutline.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import { useProjectsStore } from '../store/projects.js'
import { latestLogEntries, projectPeople, projectProgress } from '../utils/projectOverview.js'
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
			entries: [],
			names: {},
			loading: true,
			accessDenied: false,
		}
	},

	computed: {
		/** @return {string} */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/** @return {{done: number, total: number}} */
		progress() {
			return projectProgress(this.tasks)
		},

		/** @return {number} */
		progressPercent() {
			return this.progress.total ? Math.round((this.progress.done / this.progress.total) * 100) : 0
		},

		/** @return {string} */
		progressText() {
			return this.t('planninq', '{done} of {total} tasks done', { done: this.progress.done, total: this.progress.total })
		},

		/** @return {Array<string>} */
		people() {
			return projectPeople(this.project)
		},

		/** @return {Array<object>} */
		latestEntries() {
			return latestLogEntries(this.entries, 5)
		},

		/** @return {string} */
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
				const [tasks, entries] = await Promise.all([
					store.fetchTasks(this.projectId),
					store.fetchLogEntries(this.projectId),
				])
				this.tasks = tasks
				this.entries = entries
				this.names = await displayNames(this.people)
			} finally {
				this.loading = false
			}
		},

		/**
		 * The label of a log entry type.
		 *
		 * @param {string} type The type.
		 * @return {string}
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
