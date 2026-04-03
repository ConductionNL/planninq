<template>
	<div class="project-backlog">
		<!-- Breadcrumb -->
		<nav class="project-backlog__breadcrumb" aria-label="breadcrumb">
			<NcButton type="tertiary-no-background" @click="$router.push({ name: 'Projects' })">
				{{ t('planix', 'Projects') }}
			</NcButton>
			<span aria-hidden="true">&rsaquo;</span>
			<NcButton
				type="tertiary-no-background"
				@click="$router.push({ name: 'ProjectBoard', params: { id: $route.params.id } })">
				{{ projectTitle }}
			</NcButton>
			<span aria-hidden="true">&rsaquo;</span>
			<span>{{ t('planix', 'Backlog') }}</span>
		</nav>

		<!-- Page header -->
		<div class="project-backlog__header">
			<h2>{{ t('planix', 'Backlog') }}</h2>
		</div>

		<!-- Placeholder -->
		<NcEmptyContent
			:name="t('planix', 'Backlog view coming soon')"
			:description="t('planix', 'Task management will be available in a future update.')">
			<template #icon>
				<FormatListBulleted :size="20" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import FormatListBulleted from 'vue-material-design-icons/FormatListBulleted.vue'
import { useProjectsStore } from '../store/projects.js'

export default {
	name: 'ProjectBacklog',

	components: {
		NcButton,
		NcEmptyContent,
		FormatListBulleted,
	},

	computed: {
		projectsStore() {
			return useProjectsStore()
		},
		projectTitle() {
			return this.projectsStore.activeProject?.title || this.$route.params.id
		},
	},

	async mounted() {
		if (!this.projectsStore.activeProject || this.projectsStore.activeProject.id !== this.$route.params.id) {
			await this.projectsStore.fetchProject(this.$route.params.id)
		}
	},
}
</script>

<style scoped>
.project-backlog {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.project-backlog__breadcrumb {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-bottom: 16px;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
}

.project-backlog__header {
	margin-bottom: 16px;
}

.project-backlog__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}
</style>
