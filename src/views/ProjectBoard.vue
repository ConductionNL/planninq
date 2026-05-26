<template>
	<div class="project-board">
		<!-- Access denied state (403 or non-member) -->
		<NcEmptyContent
			v-if="accessDenied"
			:name="t('planix', 'You do not have access to this project')"
			:description="t('planix', 'You are not a member of this project.')">
			<template #icon>
				<LockOutline :size="20" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$router.push({ name: 'Projects' })">
					{{ t('planix', 'Back to projects') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Loading state -->
		<div v-else-if="loading" class="project-board__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Board content -->
		<template v-else-if="project">
			<!-- Page header -->
			<div class="project-board__header">
				<!-- Color accent bar -->
				<span
					v-if="project.color"
					class="project-board__color-accent"
					:style="{ backgroundColor: project.color }"
					aria-hidden="true" />

				<span class="project-board__icon" aria-hidden="true">
					{{ project.icon || '📁' }}
				</span>

				<h2 class="project-board__title">
					{{ project.title }}
				</h2>

				<div class="project-board__header-actions">
					<NcButton
						:aria-label="t('planix', 'View backlog')"
						type="tertiary"
						@click="$router.push({ name: 'ProjectBacklog', params: { id: project.id } })">
						{{ t('planix', 'Backlog') }}
					</NcButton>
					<NcButton
						:aria-label="t('planix', 'Project settings')"
						type="tertiary"
						@click="openSettings">
						<template #icon>
							<CogIcon :size="20" />
						</template>
					</NcButton>
				</div>
			</div>

			<!-- Board placeholder -->
			<NcEmptyContent
				:name="t('planix', 'Board view coming soon')"
				:description="t('planix', 'The Kanban board is being built. Use the Backlog view in the meantime.')">
				<template #icon>
					<ViewColumnOutline :size="20" />
				</template>
				<template #action>
					<NcButton
						type="secondary"
						@click="$router.push({ name: 'ProjectBacklog', params: { id: project.id } })">
						{{ t('planix', 'View Backlog') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</template>

		<!-- Settings sidebar (rendered via App.vue outlet, passed via provide) -->
	</div>
</template>

<script>
/**
 * ProjectBoard view.
 *
 * Project board header + settings cog; the kanban board itself is a placeholder.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
 */
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import LockOutline from 'vue-material-design-icons/LockOutline.vue'
import ViewColumnOutline from 'vue-material-design-icons/ViewColumnOutline.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { useProjectsStore } from '../store/projects.js'
import ProjectSettingsSidebar from '../components/ProjectSettingsSidebar.vue'

export default {
	name: 'ProjectBoard',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		CogIcon,
		LockOutline,
		ViewColumnOutline,
	},

	inject: {
		setSidebar: { default: null },
		closeSidebar: { default: null },
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — returns the projects Pinia store.
		 */
		projectsStore() {
			return useProjectsStore()
		},
		/**
		 * @spec exclude Store passthrough — proxies projectsStore.activeProject.
		 */
		project() {
			return this.projectsStore.activeProject
		},
		/**
		 * @spec exclude Store passthrough — proxies projectsStore.loading.
		 */
		loading() {
			return this.projectsStore.loading
		},
		/**
		 * Whether the current user is denied access to the project — true on a
		 * stored 403 (`forbidden`) or when the loaded project's members array
		 * does not include the current user's UID.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		accessDenied() {
			const store = this.projectsStore
			if (store.error === 'forbidden') return true
			if (!store.loading && store.activeProject) {
				const uid = getCurrentUser()?.uid
				return !!uid && !store.activeProject.members?.includes(uid)
			}
			return false
		},
	},

	/**
	 * @spec exclude Lifecycle glue — fetches the route's project on mount; fetch behavior is spec'd in projects#REQ-Project-Lifecycle.
	 */
	async mounted() {
		const id = this.$route.params.id
		await this.projectsStore.fetchProject(id)
	},

	beforeDestroy() {
		this.closeSidebar?.()
	},

	methods: {
		/**
		 * Open the project settings sidebar via the App.vue outlet.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
		 */
		openSettings() {
			if (!this.setSidebar) return
			this.setSidebar({
				...ProjectSettingsSidebar,
				propsData: { project: this.project },
				on: {
					close: () => this.closeSidebar?.(),
					archived: () => this.$router.push({ name: 'Projects' }),
					deleted: () => this.$router.push({ name: 'Projects' }),
				},
			})
		},
	},
}
</script>

<style scoped>
.project-board {
	padding: 8px 4px 24px;
	max-width: 1400px;
}

.project-board__header {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 24px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.project-board__color-accent {
	flex-shrink: 0;
	width: 6px;
	height: 32px;
	border-radius: 3px;
}

.project-board__icon {
	font-size: 24px;
	line-height: 1;
}

.project-board__title {
	flex: 1;
	margin: 0;
	font-size: 20px;
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.project-board__header-actions {
	display: flex;
	gap: 4px;
}

.project-board__loading {
	display: flex;
	justify-content: center;
	padding: 60px;
}
</style>
