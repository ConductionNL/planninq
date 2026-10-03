<template>
	<div class="portfolio">
		<div class="portfolio__header">
			<h2 class="portfolio__title">
				{{ t('planninq', 'Portfolio') }}
			</h2>
			<p class="portfolio__subtitle">
				{{ t('planninq', 'Open work per person and per project.') }}
			</p>
		</div>

		<div v-if="loading" class="portfolio__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="projects.length === 0"
			:name="t('planninq', 'No projects yet')"
			:description="t('planninq', 'Create a project to see capacity here.')">
			<template #icon>
				<ChartBarIcon :size="20" />
			</template>
		</NcEmptyContent>

		<div v-else class="portfolio__content">
			<div class="portfolio__controls">
				<div class="portfolio__toggle"
					role="group"
					:aria-label="t('planninq', 'View')"
					data-testid="capacity-view-toggle">
					<NcButton :variant="view === 'person' ? 'primary' : 'secondary'"
						:aria-pressed="view === 'person' ? 'true' : 'false'"
						data-testid="capacity-view-person"
						@click="view = 'person'">
						{{ t('planninq', 'By person') }}
					</NcButton>
					<NcButton :variant="view === 'project' ? 'primary' : 'secondary'"
						:aria-pressed="view === 'project' ? 'true' : 'false'"
						data-testid="capacity-view-project"
						@click="view = 'project'">
						{{ t('planninq', 'By project') }}
					</NcButton>
				</div>
				<NcSelect v-if="portfolios.length"
					:modelValue="portfolioOption"
					class="portfolio__picker"
					:options="portfolioOptions"
					:clearable="false"
					:inputLabel="t('planninq', 'Portfolio')"
					label="label"
					data-testid="capacity-portfolio-picker"
					@update:modelValue="pickPortfolio" />
				<NcSelect :modelValue="projectFilterOptions"
					class="portfolio__picker"
					:options="projectOptions"
					:multiple="true"
					:placeholder="t('planninq', 'All projects')"
					:inputLabel="t('planninq', 'Projects')"
					label="label"
					data-testid="capacity-project-filter"
					@update:modelValue="pickProjects" />
			</div>

			<div v-if="reading" class="portfolio__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<template v-else-if="view === 'person'">
				<p class="portfolio__note">
					{{ t('planninq', 'Showing work in the projects you can see. Hours count for the person responsible; shared tasks are counted without hours.') }}
				</p>
				<p v-if="people.length === 0" class="portfolio__note" data-testid="capacity-no-work">
					{{ t('planninq', 'No open work in these projects.') }}
				</p>
				<table v-else class="portfolio__table" data-testid="capacity-people">
					<thead>
						<tr>
							<th scope="col">
								{{ t('planninq', 'Person') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Open') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Overdue') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Hours left') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Tasks without an estimate') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Due in 14 days') }}
							</th>
							<th scope="col">
								{{ t('planninq', 'Shared') }}
							</th>
						</tr>
					</thead>
					<tbody v-for="person in people" :key="person.uid || 'unassigned'">
						<tr :data-testid="'capacity-person-' + (person.uid || 'unassigned')">
							<th scope="row" class="portfolio__project-cell">
								<button type="button"
									class="portfolio__disclosure"
									:aria-expanded="isOpen(person.uid) ? 'true' : 'false'"
									data-testid="capacity-person-toggle"
									@click="toggle(person.uid)">
									<ChevronDownIcon v-if="isOpen(person.uid)" :size="20" />
									<ChevronRightIcon v-else :size="20" />
									<NcAvatar v-if="person.uid"
										:user="person.uid"
										:size="24"
										:displayName="nameOf(person.uid)"
										:hideStatus="true"
										:disableMenu="true"
										:disableTooltip="true" />
									<span data-testid="capacity-person-name">{{ nameOf(person.uid) }}</span>
								</button>
							</th>
							<td data-testid="capacity-open">
								{{ person.open }}
							</td>
							<td :class="{ portfolio__overdue: person.overdue > 0 }" data-testid="capacity-overdue">
								{{ person.overdue }}
							</td>
							<td data-testid="capacity-hours">
								{{ hours(person.minutes) }}
							</td>
							<td data-testid="capacity-without-estimate">
								{{ person.withoutEstimate }}
							</td>
							<td data-testid="capacity-due-soon">
								{{ person.dueSoon }}
							</td>
							<td data-testid="capacity-shared">
								{{ person.shared }}
							</td>
						</tr>
						<template v-if="isOpen(person.uid)">
							<tr v-for="part in person.projects"
								:key="part.id"
								class="portfolio__breakdown"
								data-testid="capacity-breakdown-row">
								<th scope="row" class="portfolio__breakdown-title">
									{{ part.title }}
								</th>
								<td>{{ part.open }}</td>
								<td>{{ part.overdue }}</td>
								<td>{{ hours(part.minutes) }}</td>
								<td />
								<td />
								<td>{{ part.shared }}</td>
							</tr>
						</template>
					</tbody>
				</table>
			</template>

			<!-- By project: the per-project summary of capacity-planning-resource.md,
			     CSS bars on NC design tokens (ADR-036). -->
			<table v-else class="portfolio__table" data-testid="capacity-projects">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Project') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Members') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Open') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Overdue') }}
						</th>
						<th scope="col" class="portfolio__bar-col">
							{{ t('planninq', 'Open work') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in rows" :key="row.id" data-testid="capacity-project-row">
						<th scope="row" class="portfolio__project-cell">
							{{ row.icon }} {{ row.title }}
						</th>
						<td>{{ row.members }}</td>
						<td>{{ row.open }}</td>
						<td :class="{ portfolio__overdue: row.overdue > 0 }">
							{{ row.overdue }}
						</td>
						<td class="portfolio__bar-col">
							<div class="portfolio__bar-track">
								<div
									class="portfolio__bar"
									:style="{ width: barWidth(row.open) }"
									:aria-label="t('planninq', '{count} open tasks', { count: row.open })" />
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script>
/**
 * Capacity report: open work per person (the default) and per project across
 * the active projects the viewer can read, as a member or as a manager of the
 * project's portfolio. Tasks are read once per chosen project and grouped in
 * the browser (ADR-022: no bespoke aggregation service). Read-only.
 *
 * @spec openspec/specs/capacity-planning-resource.md
 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.1
 */
import { NcAvatar, NcButton, NcEmptyContent, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import { useProjectsStore } from '../store/projects.js'
import { filterByPortfolio, sortPortfolios } from '../utils/portfolioGrouping.js'
import { summariseByAssignee, summariseProjectTasks, UNASSIGNED } from '../utils/portfolioHelpers.js'
import { displayNames } from '../utils/userNames.js'

export default {
	name: 'Portfolio',

	components: { NcAvatar, NcButton, NcEmptyContent, NcLoadingIcon, NcSelect, ChartBarIcon, ChevronDownIcon, ChevronRightIcon },

	data() {
		return {
			projectsStore: useProjectsStore(),
			projects: [],
			portfolios: [],
			tasksByProject: {},
			names: {},
			view: 'person',
			portfolioId: '',
			projectIds: [],
			open: [],
			loading: true,
			reading: false,
		}
	},

	computed: {
		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		portfolioOptions() {
			return [{ id: '', label: this.t('planninq', 'All portfolios') }]
				.concat(sortPortfolios(this.portfolios).map((p) => ({ id: String(p.id), label: String(p.title ?? '') })))
		},

		/**
		 * @return {{id: string, label: string}}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		portfolioOption() {
			return this.portfolioOptions.find((option) => option.id === this.portfolioId) || this.portfolioOptions[0]
		},

		/**
		 * The projects of the chosen portfolio (all when none is chosen).
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		portfolioProjects() {
			return filterByPortfolio(this.projects, this.portfolioId)
		},

		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		projectOptions() {
			return this.portfolioProjects.map((p) => ({ id: String(p.id), label: String(p.title ?? '') }))
		},

		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		projectFilterOptions() {
			return this.projectOptions.filter((option) => this.projectIds.includes(option.id))
		},

		/**
		 * The projects the report reads: the chosen ones, else every project of the portfolio.
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		chosenProjects() {
			const chosen = this.portfolioProjects.filter((p) => this.projectIds.includes(String(p.id)))
			return chosen.length ? chosen : this.portfolioProjects
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.1
		 */
		people() {
			return summariseByAssignee(this.chosenProjects.map((project) => ({ project, tasks: this.tasksByProject[project.id] || [] })))
		},

		/**
		 * One row per chosen project (the per-project view).
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/specs/capacity-planning-resource.md
		 */
		rows() {
			return this.chosenProjects.map((project) => {
				const stats = summariseProjectTasks(this.tasksByProject[project.id] || [])
				return {
					id: project.id,
					title: project.title,
					icon: project.icon || '',
					members: Array.isArray(project.members) ? project.members.length : 0,
					open: stats.open,
					overdue: stats.overdue,
					total: stats.total,
				}
			})
		},

		/**
		 * The largest open-task count across projects (bar-chart scale).
		 *
		 * @spec exclude Display helper — chart scaling.
		 */
		maxOpen() {
			return this.rows.reduce((max, r) => Math.max(max, r.open), 0)
		},
	},

	watch: {
		/**
		 * Read the tasks of projects chosen later.
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		chosenProjects() {
			this.readTasks()
		},
	},

	/**
	 * @spec exclude Lifecycle glue — loads projects + per-project task counts.
	 */
	async mounted() {
		await this.loadCapacity()
	},

	methods: {
		/**
		 * Load the viewer's active projects and the portfolios, then the tasks of the chosen projects.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.1
		 */
		async loadCapacity() {
			this.loading = true
			try {
				const [projects, portfolios] = await Promise.all([
					this.projectsStore.fetchProjects({ status: 'active' }),
					this.projectsStore.fetchPortfolios(),
				])
				this.projects = projects || []
				this.portfolios = portfolios || []
			} finally {
				this.loading = false
			}
			await this.readTasks()
		},

		/**
		 * Read the tasks of every chosen project not read yet, one read per project.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		async readTasks() {
			const missing = this.chosenProjects.filter((project) => !(project.id in this.tasksByProject))
			if (!missing.length) {
				return
			}
			this.reading = true
			try {
				const read = {}
				for (const project of missing) {
					read[project.id] = await this.projectsStore.fetchTasks(project.id) || []
				}
				this.tasksByProject = { ...this.tasksByProject, ...read }
				const uids = [...new Set(Object.values(this.tasksByProject).flat()
					.flatMap((task) => [task?.assignedTo, ...(Array.isArray(task?.sharedWith) ? task.sharedWith : [])])
					.filter((uid) => typeof uid === 'string' && uid !== '' && !(uid in this.names)))]
				if (uids.length) {
					this.names = { ...this.names, ...await displayNames(uids) }
				}
			} finally {
				this.reading = false
			}
		},

		/**
		 * @param {{id: string}|null} option The picked portfolio.
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		pickPortfolio(option) {
			this.portfolioId = option?.id || ''
			this.projectIds = []
		},

		/**
		 * @param {Array<{id: string}>} options The picked projects.
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.2
		 */
		pickProjects(options) {
			this.projectIds = (options || []).map((option) => option.id)
		},

		/**
		 * @param {string} uid A user id, or UNASSIGNED.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.1
		 */
		nameOf(uid) {
			return uid === UNASSIGNED ? this.t('planninq', 'Unassigned') : (this.names[uid] || uid)
		},

		/**
		 * @param {string} uid A user id, or UNASSIGNED.
		 * @return {boolean}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.1
		 */
		isOpen(uid) {
			return this.open.includes(uid)
		},

		/**
		 * @param {string} uid A user id, or UNASSIGNED.
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.1
		 */
		toggle(uid) {
			this.open = this.isOpen(uid) ? this.open.filter((u) => u !== uid) : [...this.open, uid]
		},

		/**
		 * Minutes as whole hours with the unit.
		 *
		 * @param {number} minutes The minutes.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-2.1
		 */
		hours(minutes) {
			return this.t('planninq', '{hours} h', { hours: Math.round((Number(minutes) || 0) / 60) })
		},

		/**
		 * The CSS width for a project's open-work bar (relative to the busiest
		 * project).
		 *
		 * @param {number} open Open task count.
		 * @return {string} A CSS width percentage.
		 *
		 * @spec exclude Display helper — bar width.
		 */
		barWidth(open) {
			if (this.maxOpen <= 0) {
				return '0%'
			}
			return `${Math.round((open / this.maxOpen) * 100)}%`
		},
	},
}
</script>

<style scoped>
.portfolio {
	padding: 24px;
	max-width: 1000px;
}

.portfolio__header {
	margin-bottom: 24px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.portfolio__title {
	margin: 0;
}

.portfolio__subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
}

.portfolio__loading {
	display: flex;
	justify-content: center;
	padding: 48px 0;
}

.portfolio__controls {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: flex-end;
	margin-bottom: 16px;
}

.portfolio__toggle {
	display: flex;
	gap: 4px;
}

.portfolio__picker {
	min-width: 220px;
}

.portfolio__note {
	color: var(--color-text-maxcontrast);
	margin: 0 0 12px;
}

.portfolio__disclosure {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	background: none;
	border: none;
	padding: 0;
	font: inherit;
	font-weight: 600;
	color: var(--color-main-text);
	cursor: pointer;
}

.portfolio__breakdown-title {
	padding-inline-start: 40px;
	font-weight: normal;
}

.portfolio__table {
	width: 100%;
	border-collapse: collapse;
}

.portfolio__table th,
.portfolio__table td {
	padding: 8px 12px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.portfolio__project-cell {
	font-weight: 600;
}

.portfolio__overdue {
	color: var(--color-error);
	font-weight: 600;
}

.portfolio__bar-col {
	width: 30%;
}

.portfolio__bar-track {
	width: 100%;
	height: 12px;
	background: var(--color-background-dark);
	border-radius: 6px;
	overflow: hidden;
}

.portfolio__bar {
	height: 100%;
	min-width: 2px;
	background: var(--color-primary-element);
	border-radius: 6px;
}
</style>
