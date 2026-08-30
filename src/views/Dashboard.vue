<template>
	<div class="planninq-dashboard">
		<header class="planninq-dashboard__header">
			<h2>{{ t('planninq', 'Dashboard') }}</h2>
		</header>

		<CnKpiGrid :columns="3">
			<CnStatsBlock
				:title="t('planninq', 'Active projects')"
				:count="activeProjectCount"
				:icon="FolderOutline"
				variant="primary"
				horizontal />
			<CnStatsBlock
				:title="t('planninq', 'Projects I am in')"
				:count="memberProjectCount"
				:icon="AccountGroupOutline"
				variant="success"
				horizontal />
			<CnStatsBlock
				:title="t('planninq', 'Archived projects')"
				:count="archivedProjectCount"
				:icon="ArchiveOutline"
				variant="default"
				horizontal />
		</CnKpiGrid>

		<div class="planninq-dashboard__columns">
			<CnConfigurationCard :title="t('planninq', 'My projects')">
				<NcLoadingIcon v-if="loading" :size="24" />
				<ul v-else-if="recentProjects.length > 0" class="planninq-dashboard__project-list">
					<!-- A bare @click on an <li> is mouse-only: the element is
					     not focusable and fires nothing on Enter or Space, so
					     this list was unreachable by keyboard entirely. -->
					<li
						v-for="project in recentProjects"
						:key="project.id"
						class="planninq-dashboard__project-item"
						role="button"
						tabindex="0"
						@click="navigateToProject(project)"
						@keydown.enter="navigateToProject(project)"
						@keydown.space.prevent="navigateToProject(project)">
						<span
							v-if="project.icon"
							class="planninq-dashboard__project-icon">{{ project.icon }}</span>
						<span>{{ project.title }}</span>
					</li>
				</ul>
				<p v-else class="planninq-dashboard__hint">
					{{ t('planninq', 'You are not a member of any projects yet.') }}
				</p>
			</CnConfigurationCard>

			<CnConfigurationCard :title="t('planninq', 'Quick actions')">
				<p class="planninq-dashboard__hint">
					{{ t('planninq', 'Use the Projects page to create and manage your projects and tasks.') }}
				</p>
				<NcButton variant="primary" @click="$router.push({ name: 'Projects' })">
					{{ t('planninq', 'Go to projects') }}
				</NcButton>
			</CnConfigurationCard>
		</div>
	</div>
</template>

<script>
/**
 * Dashboard view.
 *
 * Shows real KPI counts derived from the projects store instead of
 * hardcoded placeholder values.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-11
 */
import { CnConfigurationCard, CnKpiGrid, CnStatsBlock } from '@conduction/nextcloud-vue'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import ArchiveOutline from 'vue-material-design-icons/ArchiveOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'

import { useProjectsStore } from '../store/projects.js'

export default {
	name: 'Dashboard',
	components: {
		CnConfigurationCard,
		CnKpiGrid,
		CnStatsBlock,
		NcButton,
		NcLoadingIcon,
	},
	data() {
		return {
			FolderOutline,
			AccountGroupOutline,
			ArchiveOutline,
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — returns the projects Pinia store.
		 */
		projectsStore() {
			return useProjectsStore()
		},
		/**
		 * @spec exclude Store passthrough — proxies projectsStore.loading.
		 */
		loading() {
			return this.projectsStore.loading
		},
		/**
		 * Number of active projects the user is a member of.
		 *
		 * @return {number}
		 */
		activeProjectCount() {
			return this.projectsStore.projects.filter((p) => p.status === 'active').length
		},
		/**
		 * Number of archived projects the user is a member of.
		 *
		 * @return {number}
		 */
		archivedProjectCount() {
			return this.projectsStore.projects.filter((p) => p.status === 'archived').length
		},
		/**
		 * Total number of projects the user is a member of (all statuses).
		 *
		 * @return {number}
		 */
		memberProjectCount() {
			return this.projectsStore.projects.length
		},
		/**
		 * Up to 5 most recent active projects, sorted by title for now.
		 *
		 * @return {Array}
		 */
		recentProjects() {
			return this.projectsStore.projects
				.filter((p) => p.status === 'active')
				.slice(0, 5)
		},
	},

	async mounted() {
		await this.projectsStore.fetchProjects()
	},

	methods: {
		/**
		 * Navigate to a project's board.
		 *
		 * @param {object} project Project to navigate to
		 */
		navigateToProject(project) {
			this.$router.push({ name: 'ProjectBoard', params: { id: project.id } })
		},
	},
}
</script>

<style scoped>
.planninq-dashboard {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.planninq-dashboard__header {
	margin-bottom: 20px;
}

.planninq-dashboard__header h2 {
	margin: 0 0 8px;
	font-size: 22px;
	font-weight: 600;
}

.planninq-dashboard__columns {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 16px;
	margin-top: 16px;
}

@media (max-width: 900px) {
	.planninq-dashboard__columns {
		grid-template-columns: 1fr;
	}
}

.planninq-dashboard__project-list {
	margin: 0;
	padding: 0;
	list-style: none;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.planninq-dashboard__project-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	cursor: pointer;
	font-size: 14px;
}

.planninq-dashboard__project-item:hover {
	background: var(--color-background-hover);
}

.planninq-dashboard__project-icon {
	font-size: 16px;
}

.planninq-dashboard__hint {
	margin: 0 0 12px;
	line-height: 1.5;
	color: var(--color-text-maxcontrast);
}
</style>
