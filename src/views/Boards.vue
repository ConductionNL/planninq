<template>
	<div class="boards">
		<div class="boards__header">
			<h2 class="boards__title">
				{{ t('planninq', 'Boards') }}
			</h2>
		</div>

		<section class="boards__views" data-testid="cross-project-views">
			<div class="boards__views-header">
				<h3 class="boards__views-title">
					{{ t('planninq', 'Cross-project views') }}
				</h3>
				<NcButton data-testid="new-view" @click="editing = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('planninq', 'New view') }}
				</NcButton>
			</div>
			<p v-if="!views.length" class="boards__views-empty">
				{{ t('planninq', 'No views yet. A view shows the tasks of several of your projects together.') }}
			</p>
			<ul v-else class="boards__grid">
				<li v-for="view in views" :key="view.id" class="boards__card-wrap">
					<button
						type="button"
						class="boards__card"
						data-testid="view-card"
						@click="openView(view)">
						<span class="boards__card-body">
							<span class="boards__card-title">{{ view.title }}</span>
							<span class="boards__card-meta">
								{{ t('planninq', 'Projects in view: {count}', { count: (view.projects || []).length }) }}
							</span>
						</span>
					</button>
				</li>
			</ul>
		</section>

		<ProjectsViewEditDialog
			v-if="editing"
			:projects="pickable"
			:uid="uid"
			@close="editing = false"
			@saved="onViewSaved" />

		<div v-if="loading" class="boards__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="projects.length === 0"
			:name="t('planninq', 'No boards yet')"
			:description="t('planninq', 'Create a project to get a board.')">
			<template #icon>
				<ViewDashboardOutline :size="20" />
			</template>
		</NcEmptyContent>

		<PortfolioSections v-else :groups="groups" :showHeadings="portfolios.length > 0">
			<template #default="{ projects: groupProjects }">
				<ul class="boards__grid">
					<li v-for="project in groupProjects" :key="project.id" class="boards__card-wrap">
						<button
							type="button"
							class="boards__card"
							@click="openBoard(project)">
							<span
								class="boards__card-accent"
								:style="{ backgroundColor: project.color || 'var(--color-primary-element)' }"
								aria-hidden="true" />
							<span class="boards__card-body">
								<span class="boards__card-title">{{ project.icon }} {{ project.title }}</span>
								<span class="boards__card-meta">
									{{ t('planninq', '{count} members', { count: memberCount(project) }) }}
								</span>
							</span>
						</button>
					</li>
				</ul>
			</template>
		</PortfolioSections>
	</div>
</template>

<script>
/**
 * Boards view — the "Borden" index (ADR-001 IA).
 *
 * Renders one card per project the current user is a member of, each linking
 * to that project's existing kanban board (`ProjectBoard`, unchanged). This
 * surfaces ADR-001's "kanban-board: menu → Borden" placement without
 * duplicating the per-project board component.
 *
 * @spec openspec/specs/portfolio-dashboard-pmo.md
 */
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import PortfolioSections from '../components/PortfolioSections.vue'
import ProjectsViewEditDialog from '../dialogs/ProjectsViewEditDialog.vue'
import { useProjectsStore } from '../store/projects.js'
import { groupByPortfolio } from '../utils/portfolioGrouping.js'
import { pickableProjects } from '../utils/projectsView.js'

export default {
	name: 'Boards',

	components: { NcButton, NcEmptyContent, NcLoadingIcon, PlusIcon, PortfolioSections, ProjectsViewEditDialog, ViewDashboardOutline },

	data() {
		return {
			projectsStore: useProjectsStore(),
			portfolios: [],
			/** @type {Array<object>} Cross-project views the user owns or that are shared with them. */
			views: [],
			editing: false,
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — member projects.
		 */
		projects() {
			return this.projectsStore.projects
		},

		/**
		 * @spec exclude Store passthrough — loading flag.
		 */
		loading() {
			return this.projectsStore.loading
		},

		/**
		 * The boards grouped by portfolio, like the project list.
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
		 */
		groups() {
			return groupByPortfolio(this.projects, this.portfolios)
		},

		/**
		 * @spec exclude Auth passthrough — the user's id.
		 */
		uid() {
			return getCurrentUser()?.uid || ''
		},

		/**
		 * The projects a new view may show: the ones the user is in.
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		pickable() {
			return pickableProjects(this.projects, this.uid)
		},
	},

	/**
	 * @spec exclude Lifecycle glue — loads the user's member projects.
	 */
	async mounted() {
		const [portfolios, views] = await Promise.all([this.projectsStore.fetchPortfolios(), this.projectsStore.fetchBoardViews(), this.projectsStore.fetchProjects({ status: 'active' })])
		this.portfolios = portfolios
		this.views = [...views].sort((a, b) => String(a.title).localeCompare(String(b.title)))
	},

	methods: {
		/**
		 * @param {object} project The project.
		 * @return {number} Member count.
		 * @spec exclude Display helper — member count.
		 */
		memberCount(project) {
			return Array.isArray(project.members) ? project.members.length : 0
		},

		/**
		 * Open a project's kanban board.
		 *
		 * @param {object} project The project to open.
		 *
		 * @spec openspec/specs/portfolio-dashboard-pmo.md
		 */
		openBoard(project) {
			this.$router.push({ name: 'ProjectBoard', params: { id: project.id } })
		},

		/**
		 * Open a cross-project view.
		 *
		 * @param {object} view The view.
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		openView(view) {
			this.$router.push({ name: 'ProjectsView', params: { id: view.id } })
		},

		/**
		 * A new view is saved: open it.
		 *
		 * @param {object} view The saved view.
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		onViewSaved(view) {
			this.editing = false
			this.openView(view)
		},
	},
}
</script>

<style scoped>
.boards {
	padding: 24px;
	max-width: 1200px;
}

.boards__header {
	margin-bottom: 24px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.boards__title {
	margin: 0;
}

.boards__views {
	margin-bottom: 32px;
}

.boards__views-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.boards__views-title {
	margin: 0;
}

.boards__views-empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.boards__loading {
	display: flex;
	justify-content: center;
	padding: 48px 0;
}

.boards__grid {
	list-style: none;
	margin: 0;
	padding: 0;
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
	gap: 16px;
}

.boards__card {
	display: flex;
	align-items: stretch;
	gap: 12px;
	width: 100%;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	cursor: pointer;
	overflow: hidden;
	text-align: start;
}

.boards__card:hover {
	background: var(--color-background-hover);
}

.boards__card-accent {
	flex: 0 0 6px;
}

.boards__card-body {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 16px;
}

.boards__card-title {
	font-weight: 600;
	color: var(--color-main-text);
}

.boards__card-meta {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
