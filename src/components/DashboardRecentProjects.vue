<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 DashboardRecentProjects — shows the 5 most recently active projects
 with progress bars.

 @spec openspec/changes/dashboard-my-work/tasks.md#task-4
-->
<template>
	<div class="dashboard-recent-projects">
		<h3 class="dashboard-recent-projects__header">
			{{ t('planix', 'Recent Projects') }}
		</h3>

		<template v-if="sortedProjects.length > 0">
			<div
				v-for="project in sortedProjects"
				:key="project.id"
				class="dashboard-recent-projects__item"
				role="link"
				tabindex="0"
				@click="navigateToProject(project.id)"
				@keyup.enter="navigateToProject(project.id)">
				<div class="dashboard-recent-projects__info">
					<span
						class="dashboard-recent-projects__icon"
						:style="{ backgroundColor: project.color || 'var(--color-primary-element)' }"
						aria-hidden="true">
						{{ project.icon || '' }}
					</span>
					<span class="dashboard-recent-projects__title">{{ project.title }}</span>
				</div>
				<div class="dashboard-recent-projects__progress">
					<div class="dashboard-recent-projects__bar">
						<div
							class="dashboard-recent-projects__bar-fill"
							:style="{ width: progressPercent(project) + '%' }" />
					</div>
					<span class="dashboard-recent-projects__count">
						{{ t('planix', '{done} of {total} tasks done', progressCounts(project)) }}
					</span>
				</div>
			</div>
		</template>

		<NcEmptyContent
			v-else
			:name="t('planix', 'No projects yet')"
			:description="t('planix', 'Create your first project to get started')">
			<template #icon>
				<FolderOutline :size="64" aria-hidden="true" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$router.push('/projects/new')">
					{{ t('planix', 'Create project') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'

/**
 * @spec openspec/changes/dashboard-my-work/tasks.md#task-4
 */
export default {
	name: 'DashboardRecentProjects',
	components: { NcButton, NcEmptyContent, FolderOutline },
	props: {
		projects: { type: Array, default: () => [] },
		tasks: { type: Array, default: () => [] },
	},
	computed: {
		sortedProjects() {
			return [...this.projects]
				.sort((a, b) => {
					const da = a.updatedAt ? new Date(a.updatedAt) : new Date(0)
					const db = b.updatedAt ? new Date(b.updatedAt) : new Date(0)
					return db - da
				})
				.slice(0, 5)
		},
	},
	methods: {
		t,
		progressCounts(project) {
			const projectTasks = this.tasks.filter((task) => task.project === project.id)
			const done = projectTasks.filter((task) => task.status === 'done').length
			return { done, total: projectTasks.length }
		},
		progressPercent(project) {
			const { done, total } = this.progressCounts(project)
			return total > 0 ? Math.round((done / total) * 100) : 0
		},
		navigateToProject(id) {
			this.$router.push({ name: 'ProjectBoard', params: { id } })
		},
	},
}
</script>

<style scoped>
.dashboard-recent-projects__header {
	font-size: 16px;
	font-weight: 600;
	margin: 0 0 12px;
}

.dashboard-recent-projects__item {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 10px 12px;
	border-radius: var(--border-radius, 4px);
	cursor: pointer;
	transition: background-color 0.15s ease;
}

.dashboard-recent-projects__item:hover {
	background-color: var(--color-background-hover);
}

.dashboard-recent-projects__item:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.dashboard-recent-projects__info {
	display: flex;
	align-items: center;
	gap: 8px;
}

.dashboard-recent-projects__icon {
	width: 24px;
	height: 24px;
	border-radius: 4px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 14px;
	flex-shrink: 0;
}

.dashboard-recent-projects__title {
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dashboard-recent-projects__progress {
	display: flex;
	align-items: center;
	gap: 8px;
	padding-left: 32px;
}

.dashboard-recent-projects__bar {
	flex: 1;
	height: 6px;
	background: var(--color-background-dark);
	border-radius: 3px;
	overflow: hidden;
}

.dashboard-recent-projects__bar-fill {
	height: 100%;
	background: var(--color-success);
	border-radius: 3px;
	transition: width 0.3s ease;
}

.dashboard-recent-projects__count {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}
</style>
