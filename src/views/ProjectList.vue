<template>
	<div class="project-list">
		<!-- Header with actions -->
		<div class="project-list__header">
			<h2 class="project-list__title">
				{{ t('planninq', 'Projects') }}
			</h2>
			<div class="project-list__actions">
				<!-- Status filter chips (NcChip per spec) -->
				<NcChip
					v-for="chip in statusChips"
					:key="String(chip.value)"
					:text="chip.label"
					:variant="activeStatus === chip.value ? 'primary' : 'secondary'"
					:noClose="true"
					:aria-pressed="activeStatus === chip.value"
					@click="setStatusFilter(chip.value)" />
				<!-- New project button — hidden when creation is restricted to admins -->
				<NcButton
					v-if="canCreateProject"
					variant="primary"
					@click="showCreationDialog = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('planninq', 'New project') }}
				</NcButton>
				<!-- Request a project, for those outside the creation policy (projects-lifecycle-policy) -->
				<NcButton
					v-if="!canCreateProject && canRequestProject"
					variant="primary"
					data-testid="project-request-open"
					@click="showRequestDialog = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('planninq', 'Request a project') }}
				</NcButton>
			</div>
		</div>

		<!-- Search bar and portfolio filter -->
		<div class="project-list__search">
			<NcTextField
				:modelValue="listView.searchTerm.value"
				:label="t('planninq', 'Search projects')"
				:placeholder="t('planninq', 'Search by title or description\u2026')"
				@update:modelValue="listView.onSearchInput($event)" />
			<NcSelect
				v-if="portfolios.length"
				v-model="portfolioFilter"
				class="project-list__portfolio-filter"
				:options="portfolioOptions"
				:clearable="false"
				:inputLabel="t('planninq', 'Portfolio')"
				label="label"
				data-testid="portfolio-filter" />
		</div>

		<!-- Loading state -->
		<div v-if="loading" class="project-list__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Error state -->
		<NcEmptyContent
			v-else-if="error"
			:name="t('planninq', 'Could not load projects')"
			:description="error">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="projectsStore.fetchProjects()">
					{{ t('planninq', 'Retry') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Empty state — no projects at all -->
		<NcEmptyContent
			v-else-if="projects.length === 0"
			:name="t('planninq', 'No projects yet')"
			:description="canCreateProject ? t('planninq', 'Create your first project to get started.') : t('planninq', 'No projects are available to you yet. Ask an administrator to create one.')">
			<template #icon>
				<FolderOutline :size="20" />
			</template>
			<template v-if="canCreateProject" #action>
				<NcButton variant="primary" @click="showCreationDialog = true">
					{{ t('planninq', 'Create your first project') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Empty state — search/filter has no results -->
		<NcEmptyContent
			v-else-if="filteredProjects.length === 0"
			:name="t('planninq', 'No projects match your search')"
			:description="t('planninq', 'Try different search terms or clear the filter.')">
			<template #icon>
				<Magnify :size="20" />
			</template>
		</NcEmptyContent>

		<!-- Project list, grouped by portfolio -->
		<PortfolioSections v-else :groups="groupedProjects" :showHeadings="portfolios.length > 0">
			<template #default="{ projects: groupProjects }">
				<ul class="project-list__items" role="listbox">
					<ProjectListItem
						v-for="row in treeRows(groupProjects, folded)"
						:key="row.project.id"
						:project="row.project"
						:depth="row.depth"
						:hasChildren="row.hasChildren"
						:folded="!row.expanded"
						:canRestore="mayRestore(row.project)"
						@click="navigateToProject(row.project)"
						@restore="restore"
						@toggle="toggleSubprojects(row.project)" />
				</ul>
			</template>
		</PortfolioSections>

		<ProjectRequestDialog
			v-if="showRequestDialog"
			@close="showRequestDialog = false"
			@requested="onProjectRequested" />

		<!-- Creation dialog — only mounted when creation is permitted -->
		<ProjectCreationDialog
			v-if="showCreationDialog && canCreateProject"
			:prefill="creationPrefill"
			@close="showCreationDialog = false"
			@created="onProjectCreated" />
	</div>
</template>

<script>
import { useListView } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
/**
 * ProjectList view.
 *
 * Renders the project list with status filters, search, and empty-states.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-11
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-12
 */
// @nextcloud/vue@9 removed the `dist/Components/*.js` layout, so NcChip comes
// from the root barrel like every other component here.
import { NcButton, NcChip, NcEmptyContent, NcLoadingIcon, NcSelect, NcTextField } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import PortfolioSections from '../components/PortfolioSections.vue'
import ProjectListItem from '../components/ProjectListItem.vue'
import ProjectCreationDialog from '../dialogs/ProjectCreationDialog.vue'
import ProjectRequestDialog from '../dialogs/ProjectRequestDialog.vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { useObjectStore } from '../store/objectStore.js'
import { useProjectsStore } from '../store/projects.js'
import { creationPrefill as prefillFromQuery } from '../utils/caseBridge.js'
import { canCreateFrom } from '../utils/creationPolicy.js'
import { filterByPortfolio, groupByPortfolio, NO_PORTFOLIO, sortPortfolios } from '../utils/portfolioGrouping.js'
import { canManageMembers, currentGroupIds, projectRole } from '../utils/projectRole.js'
import { treeRows } from '../utils/projectTree.js'

export default {
	name: 'ProjectList',

	components: {
		NcButton,
		NcChip,
		NcTextField,
		NcLoadingIcon,
		NcEmptyContent,
		NcSelect,
		AlertCircleOutline,
		FolderOutline,
		Magnify,
		PlusIcon,
		PortfolioSections,
		ProjectListItem,
		ProjectCreationDialog,
		ProjectRequestDialog,
	},

	/**
	 * @spec exclude Composable-wiring glue — instantiates useListView for search/filter state.
	 */
	setup() {
		// useListView manages search term and filter state per spec requirement.
		// fetchFn is provided as a no-op because filtering is done client-side;
		// omitting it causes a TypeError in the compiled dist when onSearchInput fires.
		const listView = useListView({ fetchFn: async () => {} })
		return { listView }
	},

	data() {
		return {
			folded: new Set(),
			showCreationDialog: false,
			showRequestDialog: false,
			creationPrefill: {},
			activeStatus: null,
			portfolios: [],
			portfolioFilter: null,
			// Live-updates handle for the or-collection-planninq-project
			// subscription. livePendingType marks an in-flight subscribe so a
			// concurrent call doesn't double-subscribe; liveEpoch invalidates
			// in-flight resolutions after a release (destroy). liveUnwatch
			// tears down the collection→projectsStore bridge watcher.
			liveHandle: null,
			livePendingType: '',
			liveEpoch: 0,
			liveUnwatch: null,
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
		 * @spec exclude Store passthrough — proxies projectsStore.projects.
		 */
		projects() {
			return this.projectsStore.projects
		},

		/**
		 * @spec exclude Store passthrough — proxies projectsStore.loading.
		 */
		loading() {
			return this.projectsStore.loading
		},

		/**
		 * @spec exclude Store passthrough — proxies projectsStore.error.
		 */
		error() {
			return this.projectsStore.error
		},

		/**
		 * Returns true when the current user is allowed to create new projects.
		 * Reads allow_project_creation: 'all' (default) | 'admins'.
		 * Any unrecognised value defaults to allowing all authenticated users.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-12
		 */
		canCreateProject() {
			// The server answers for the current user, groups included (projects-lifecycle-policy).
			const settingsStore = useSettingsStore()
			return canCreateFrom(settingsStore.settings, settingsStore.isAdmin)
		},

		/**
		 * Whether the current user may request a project (the server answers).
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
		 */
		canRequestProject() {
			return useSettingsStore().settings?.canRequestProject === true
		},

		/**
		 * @spec openspec/changes/retrofit-2026-05-26-planix-display-capabilities/tasks.md#task-3
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
		 */
		statusChips() {
			return [
				{ value: null, label: this.t('planninq', 'All') },
				{ value: 'active', label: this.t('planninq', 'Active') },
				{ value: 'archived', label: this.t('planninq', 'Archived') },
				{ value: 'completed', label: this.t('planninq', 'Completed') },
				{ value: 'requested', label: this.t('planninq', 'Requested') },
				{ value: 'template', label: this.t('planninq', 'Templates') },
			]
		},

		/**
		 * Client-side filtered project list — applies the active status filter
		 * and the useListView search term (title/description, case-insensitive).
		 *
		 * @return {Array}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-11
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-3.1
		 */
		// Client-side filter — uses useListView's searchTerm and local activeStatus.
		filteredProjects() {
			let list = this.projects
			if (this.activeStatus === 'template') {
				list = list.filter((p) => p.isTemplate === true)
			} else {
				list = list.filter((p) => p.isTemplate !== true)
				if (this.activeStatus) {
					list = list.filter((p) => p.status === this.activeStatus)
				}
			}
			const term = (this.listView.searchTerm.value || '').trim().toLowerCase()
			if (term) {
				list = list.filter((p) => p.title?.toLowerCase().includes(term)
					|| p.key?.toLowerCase().includes(term)
					|| p.description?.toLowerCase().includes(term))
			}
			return filterByPortfolio(list, this.portfolioFilter?.id || '')
		},

		/**
		 * The filtered projects grouped by portfolio.
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
		 */
		groupedProjects() {
			return groupByPortfolio(this.filteredProjects, this.portfolios)
		},

		/**
		 * The portfolio filter: all, each portfolio in order, and no portfolio.
		 *
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
		 */
		portfolioOptions() {
			const named = sortPortfolios(this.portfolios).map((p) => ({ id: String(p.id), label: String(p.title ?? '') }))
			return [
				{ id: '', label: this.t('planninq', 'All portfolios') },
				...named,
				{ id: NO_PORTFOLIO, label: this.t('planninq', 'No portfolio') },
			]
		},
	},

	/**
	 * @spec exclude list-view lifecycle — loads the project list, then attaches the live collection subscription.
	 */
	async mounted() {
		// A "New project" link from a case or client page (the projects leaf)
		// opens the dialog with the case or client filled in.
		const prefill = prefillFromQuery(this.$route?.query)
		if (prefill !== null) {
			this.creationPrefill = prefill
			this.showCreationDialog = true
		}

		const [portfolios] = await Promise.all([this.projectsStore.fetchPortfolios(), this.projectsStore.fetchProjects()])
		this.portfolios = portfolios
		this.syncLiveSubscription()
	},

	/**
	 * Lifecycle hook: release the live collection subscription on unmount.
	 *
	 * @spec openspec/specs/realtime-updates.md
	 */
	beforeUnmount() {
		this.releaseLiveSubscription()
	},

	methods: {
		treeRows,

		/**
		 * Fold or unfold the subprojects under a project.
		 *
		 * @param {object} project The parent project.
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.3
		 */
		toggleSubprojects(project) {
			const folded = new Set(this.folded)
			const id = String(project.id)
			if (folded.has(id)) {
				folded.delete(id)
			} else {
				folded.add(id)
			}
			this.folded = folded
		},

		/**
		 * Subscribe to live updates for the Planninq project collection
		 * (or-collection-planninq-project). Events are refetch hints only: the
		 * liveUpdatesPlugin re-runs fetchCollection('project') with the
		 * last-used params; the bridge watcher installed here re-applies the
		 * member filter into projectsStore.projects so this view re-renders.
		 * Uses notify_push when available, visibility-gated polling otherwise.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/realtime-updates.md
		 */
		async syncLiveSubscription() {
			const objectStore = useObjectStore()
			if (typeof objectStore.subscribe !== 'function') {
				return
			}
			const type = 'project'
			if (this.liveHandle || this.livePendingType === type) {
				// Already subscribed, or a subscribe is in flight —
				// re-subscribing would leak the first handle.
				return
			}
			try {
				// Ensure the 'project' type is registered (with slug hints).
				this.projectsStore._objectStore()
				const epoch = this.liveEpoch
				this.livePendingType = type
				const handle = await objectStore.subscribe(type)
				this.livePendingType = ''
				if (this.liveEpoch !== epoch) {
					// Released while awaiting (component destroyed) — drop the
					// now-stale subscription instead of leaking it.
					objectStore.unsubscribe(handle)
					return
				}
				this.liveHandle = handle
				// Bridge: event → plugin refetch → collections.project →
				// projectsStore.projects (which this template renders).
				this.liveUnwatch = this.$watch(
					() => objectStore.collections[type],
					(fresh) => {
						if (this.liveHandle) {
							this.projectsStore.applyLiveProjects(fresh)
						}
					},
				)
			} catch (e) {
				this.livePendingType = ''
				this.liveHandle = null
				console.warn('[ProjectList] live subscription failed:', e?.message ?? e)
			}
		},

		/**
		 * Release the live collection subscription and its bridge watcher, and
		 * invalidate any in-flight subscribe (its resolution unsubscribes
		 * itself via the epoch check).
		 *
		 * @spec openspec/specs/realtime-updates.md
		 */
		releaseLiveSubscription() {
			this.liveEpoch += 1
			this.livePendingType = ''
			if (this.liveUnwatch) {
				this.liveUnwatch()
				this.liveUnwatch = null
			}
			const objectStore = useObjectStore()
			if (this.liveHandle && typeof objectStore.unsubscribe === 'function') {
				objectStore.unsubscribe(this.liveHandle)
			}
			this.liveHandle = null
		},

		/**
		 * Filter projects by status.
		 *
		 * @param {string|null} status Status to filter on
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-11
		 */
		setStatusFilter(status) {
			this.activeStatus = status
		},

		/**
		 * After a request is sent: show it under the Requested chip.
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
		 */
		onProjectRequested() {
			this.showRequestDialog = false
			this.activeStatus = 'requested'
			showSuccess(this.t('planninq', 'Your request was sent'))
		},

		/**
		 * Whether the viewer may restore a project: its owner or a manager
		 * (directly or through a group) or an admin, the project's update rule
		 * that the restore transition checks.
		 *
		 * @param {object} project The project.
		 * @return {boolean}
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.2
		 */
		mayRestore(project) {
			const user = getCurrentUser()
			return user?.isAdmin === true || canManageMembers(projectRole(project, user?.uid, currentGroupIds()))
		},

		/**
		 * Restore an archived project from the list.
		 *
		 * @param {object} project The archived project.
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.4
		 */
		async restore(project) {
			const restored = await this.projectsStore.restoreProject(project.id)
			if (restored) {
				showSuccess(this.t('planninq', 'Project restored'))
				return
			}
			showError(this.t('planninq', 'Could not restore the project'))
		},

		/**
		 * Navigate to a project's board.
		 *
		 * @param {object} project Project to navigate to
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-11
		 */
		navigateToProject(project) {
			this.$router.push({ name: 'ProjectBoard', params: { id: project.id } })
		},

		/**
		 * Handle project-created event from the creation dialog.
		 *
		 * @param {object} project Newly created project
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-12
		 */
		async onProjectCreated(project) {
			this.showCreationDialog = false
			this.$router.push({ name: 'ProjectBoard', params: { id: project.id } })
		},
	},
}
</script>

<style scoped>
.project-list {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.project-list__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 16px;
}

.project-list__title {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-list__actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.project-list__search {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 12px;
	margin-bottom: 16px;
}

.project-list__portfolio-filter {
	min-width: 220px;
}

.project-list__loading {
	display: flex;
	justify-content: center;
	padding: 40px;
}

.project-list__items {
	margin: 0;
	padding: 0;
}
</style>
