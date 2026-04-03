<template>
	<div class="project-list">
		<!-- Header with actions -->
		<div class="project-list__header">
			<h2 class="project-list__title">
				{{ t('planix', 'Projects') }}
			</h2>
			<div class="project-list__actions">
				<!-- Status filter chips (NcChip per spec) -->
				<NcChip
					v-for="chip in statusChips"
					:key="String(chip.value)"
					:text="chip.label"
					:type="activeStatus === chip.value ? 'primary' : 'secondary'"
					:no-close="true"
					:aria-pressed="activeStatus === chip.value"
					@click="setStatusFilter(chip.value)" />
				<!-- New project button -->
				<NcButton
					type="primary"
					@click="showCreationDialog = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('planix', 'New project') }}
				</NcButton>
			</div>
		</div>

		<!-- Search bar -->
		<div class="project-list__search">
			<NcTextField
				:value="listView.searchTerm.value"
				:label="t('planix', 'Search projects')"
				:placeholder="t('planix', 'Search by title or description\u2026')"
				@update:value="listView.onSearchInput($event)" />
		</div>

		<!-- Loading state -->
		<div v-if="loading" class="project-list__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Error state -->
		<NcEmptyContent
			v-else-if="error"
			:name="t('planix', 'Could not load projects')"
			:description="error">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>
			<template #action>
				<NcButton type="primary" @click="projectsStore.fetchProjects()">
					{{ t('planix', 'Retry') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Empty state — no projects at all -->
		<NcEmptyContent
			v-else-if="projects.length === 0"
			:name="t('planix', 'No projects yet')"
			:description="t('planix', 'Create your first project to get started.')">
			<template #icon>
				<FolderOutline :size="20" />
			</template>
			<template #action>
				<NcButton type="primary" @click="showCreationDialog = true">
					{{ t('planix', 'Create your first project') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Empty state — search/filter has no results -->
		<NcEmptyContent
			v-else-if="filteredProjects.length === 0"
			:name="t('planix', 'No projects match your search')"
			:description="t('planix', 'Try different search terms or clear the filter.')">
			<template #icon>
				<Magnify :size="20" />
			</template>
		</NcEmptyContent>

		<!-- Project list -->
		<ul v-else class="project-list__items" role="listbox">
			<ProjectListItem
				v-for="project in filteredProjects"
				:key="project.id"
				:project="project"
				@click="navigateToProject(project)" />
		</ul>

		<!-- Creation dialog -->
		<ProjectCreationDialog
			v-if="showCreationDialog"
			@close="showCreationDialog = false"
			@created="onProjectCreated" />
	</div>
</template>

<script>
import { NcButton, NcTextField, NcLoadingIcon, NcEmptyContent } from '@nextcloud/vue'
import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'
import { useListView } from '@conduction/nextcloud-vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import { useProjectsStore } from '../store/projects.js'
import ProjectListItem from '../components/ProjectListItem.vue'
import ProjectCreationDialog from '../components/dialogs/ProjectCreationDialog.vue'

export default {
	name: 'ProjectList',

	components: {
		NcButton,
		NcChip,
		NcTextField,
		NcLoadingIcon,
		NcEmptyContent,
		AlertCircleOutline,
		FolderOutline,
		Magnify,
		PlusIcon,
		ProjectListItem,
		ProjectCreationDialog,
	},

	setup() {
		// useListView manages search term and filter state per spec requirement.
		// fetchFn is provided as a no-op because filtering is done client-side;
		// omitting it causes a TypeError in the compiled dist when onSearchInput fires.
		const listView = useListView({ fetchFn: async () => {} })
		return { listView }
	},

	data() {
		return {
			showCreationDialog: false,
			activeStatus: null,
		}
	},

	computed: {
		projectsStore() {
			return useProjectsStore()
		},
		projects() {
			return this.projectsStore.projects
		},
		loading() {
			return this.projectsStore.loading
		},
		error() {
			return this.projectsStore.error
		},

		statusChips() {
			return [
				{ value: null, label: this.t('planix', 'All') },
				{ value: 'active', label: this.t('planix', 'Active') },
				{ value: 'archived', label: this.t('planix', 'Archived') },
				{ value: 'completed', label: this.t('planix', 'Completed') },
			]
		},

		// Client-side filter — uses useListView's searchTerm and local activeStatus.
		filteredProjects() {
			let list = this.projects
			if (this.activeStatus) {
				list = list.filter((p) => p.status === this.activeStatus)
			}
			const term = (this.listView.searchTerm.value || '').trim().toLowerCase()
			if (term) {
				list = list.filter(
					(p) =>
						p.title?.toLowerCase().includes(term)
						|| p.description?.toLowerCase().includes(term),
				)
			}
			return list
		},
	},

	async mounted() {
		await this.projectsStore.fetchProjects()
	},

	methods: {
		setStatusFilter(status) {
			this.activeStatus = status
		},

		navigateToProject(project) {
			this.$router.push({ name: 'ProjectBoard', params: { id: project.id } })
		},

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
	margin-bottom: 16px;
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
