<template>
	<div class="boards">
		<div class="boards__header">
			<h2 class="boards__title">
				{{ t('planninq', 'Boards') }}
			</h2>
		</div>

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

		<ul v-else class="boards__grid">
			<li v-for="project in projects" :key="project.id" class="boards__card-wrap">
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
import { NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import { useProjectsStore } from '../store/projects.js'

export default {
	name: 'Boards',

	components: { NcEmptyContent, NcLoadingIcon, ViewDashboardOutline },

	data() {
		return {
			projectsStore: useProjectsStore(),
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
	},

	/**
	 * @spec exclude Lifecycle glue — loads the user's member projects.
	 */
	async mounted() {
		await this.projectsStore.fetchProjects({ status: 'active' })
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
