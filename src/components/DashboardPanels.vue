<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!--
 The dashboard's two non-KPI panels: "My projects" and "Quick actions".

 Extracted verbatim from the former src/views/Dashboard.vue when the dashboard
 became a manifest `type:"dashboard"` page. The three KPI tiles that used to sit
 above these panels are now declarative `stat` widgets in the manifest, counted
 by OpenRegister instead of by filtering an already-fetched list in the browser.

 This component deliberately renders NO CnDashboardPage: it is mounted as a
 widget on a dashboard page, and a dashboard inside a dashboard is the
 dashboard-in-dashboard antipattern hydra gate-15 rejects.
-->
<template>
	<div class="planninq-dashboard__columns">
		<CnConfigurationCard :title="t('planninq', 'My projects')">
			<NcLoadingIcon v-if="loading" :size="24" />
			<ul v-else-if="recentProjects.length > 0" class="planninq-dashboard__project-list" data-testid="dashboard-my-projects">
				<li
					v-for="project in recentProjects"
					:key="project.id"
					class="planninq-dashboard__project-item"
					data-testid="dashboard-project">
					<button type="button"
						class="planninq-dashboard__project-link"
						@click="navigateToProject(project)">
						<!-- `icon` holds either an emoji or a PascalCase MDI name
						     (the seed writes names). A name goes through CnIcon,
						     anything else stays text. -->
						<CnIcon
							v-if="isIconName(project.icon)"
							class="planninq-dashboard__project-icon"
							:name="project.icon"
							:size="16" />
						<span
							v-else-if="project.icon"
							class="planninq-dashboard__project-icon">{{ project.icon }}</span>
						<span data-testid="dashboard-project-title">{{ project.title }}</span>
					</button>
					<PinIcon v-if="isPinned(project)"
						:size="16"
						:title="t('planninq', 'Pinned')"
						data-testid="dashboard-project-pinned" />
					<NcActions :aria-label="t('planninq', 'Actions for {title}', { title: project.title })" data-testid="dashboard-project-actions">
						<NcActionButton :closeAfterClick="true" data-testid="dashboard-project-pin" @click="pin(project)">
							<template #icon>
								<PinOffIcon v-if="isPinned(project)" :size="20" />
								<PinIcon v-else :size="20" />
							</template>
							{{ isPinned(project) ? t('planninq', 'Unpin') : t('planninq', 'Pin to the top') }}
						</NcActionButton>
						<NcActionButton v-if="isPinned(project) && order.indexOf(String(project.id)) > 0"
							:closeAfterClick="true"
							data-testid="dashboard-project-up"
							@click="move(project, -1)">
							<template #icon>
								<ArrowUpIcon :size="20" />
							</template>
							{{ t('planninq', 'Move up') }}
						</NcActionButton>
						<NcActionButton v-if="isPinned(project) && order.indexOf(String(project.id)) < order.length - 1"
							:closeAfterClick="true"
							data-testid="dashboard-project-down"
							@click="move(project, 1)">
							<template #icon>
								<ArrowDownIcon :size="20" />
							</template>
							{{ t('planninq', 'Move down') }}
						</NcActionButton>
					</NcActions>
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
			<NcButton variant="secondary" data-testid="dashboard-open-my-calendar" @click="$router.push({ name: 'MyCalendar' })">
				{{ t('planninq', 'Open my calendar') }}
			</NcButton>
		</CnConfigurationCard>
	</div>
</template>

<script>
/**
 * Dashboard side panels.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-11
 */
import { CnConfigurationCard, CnIcon } from '@conduction/nextcloud-vue'
import { showError } from '@nextcloud/dialogs'
import { NcActionButton, NcActions, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import ArrowDownIcon from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUpIcon from 'vue-material-design-icons/ArrowUp.vue'
import PinIcon from 'vue-material-design-icons/Pin.vue'
import PinOffIcon from 'vue-material-design-icons/PinOff.vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { useProjectsStore } from '../store/projects.js'
import { movePinned, orderProjects, togglePin } from '../utils/myWork.js'

export default {
	name: 'DashboardPanels',

	components: {
		ArrowDownIcon,
		ArrowUpIcon,
		CnConfigurationCard,
		CnIcon,
		NcActionButton,
		NcActions,
		NcButton,
		NcLoadingIcon,
		PinIcon,
		PinOffIcon,
	},

	data() {
		return {
			order: [],
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
		 * Active projects with the user's pinned ones first, in their order;
		 * every pinned project and up to five in all.
		 *
		 * @return {Array}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
		 */
		recentProjects() {
			const active = this.projectsStore.projects.filter((p) => p.status === 'active')
			return orderProjects(active, this.order).slice(0, Math.max(5, this.order.length))
		},
	},

	/**
	 * @spec exclude Lifecycle bootstrap — fetches the project list and the user's project order.
	 */
	async mounted() {
		const settingsStore = useSettingsStore()
		const [, settings] = await Promise.all([
			this.projectsStore.fetchProjects(),
			settingsStore.settings?.dashboard_project_order ? settingsStore.settings : settingsStore.fetchSettings(),
		])
		this.order = Array.isArray(settings?.dashboard_project_order) ? settings.dashboard_project_order.map(String) : []
	},

	methods: {
		/**
		 * Whether a project's `icon` is a PascalCase MDI name (rendered through
		 * CnIcon) rather than an emoji or other literal text.
		 *
		 * @param {string} icon The stored icon value.
		 * @return {boolean} True when it should render as an icon component.
		 */
		isIconName(icon) {
			return typeof icon === 'string' && /^[A-Z][A-Za-z0-9]+$/.test(icon)
		},

		/**
		 * @param {object} project A project.
		 * @return {boolean}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
		 */
		isPinned(project) {
			return this.order.includes(String(project.id))
		},

		/**
		 * Pin or unpin a project and keep the order for this user.
		 *
		 * @param {object} project A project.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
		 */
		async pin(project) {
			await this.saveOrder(togglePin(this.order, String(project.id)))
		},

		/**
		 * Move a pinned project up (-1) or down (1).
		 *
		 * @param {object} project A project.
		 * @param {number} step -1 or 1.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
		 */
		async move(project, step) {
			await this.saveOrder(movePinned(this.order, String(project.id), step))
		},

		/**
		 * Show the new order at once and store it in the user's settings; put the old one back when that fails.
		 *
		 * @param {Array<string>} next The new order.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
		 */
		async saveOrder(next) {
			const previous = this.order
			this.order = next
			const saved = await useSettingsStore().saveUserSettings({ dashboard_project_order: next })
			if (!saved) {
				this.order = previous
				showError(this.t('planninq', 'Could not save your project order. Please try again.'))
			}
		},

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
.planninq-dashboard__columns {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 16px;
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
	padding: 2px 8px;
	border-radius: var(--border-radius);
	font-size: 14px;
}

.planninq-dashboard__project-link {
	display: flex;
	flex: 1;
	align-items: center;
	gap: 8px;
	min-width: 0;
	padding: 4px 0;
	background: none;
	border: none;
	font: inherit;
	color: var(--color-main-text);
	text-align: start;
	cursor: pointer;
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
