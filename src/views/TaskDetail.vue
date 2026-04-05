<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.
-->

<template>
	<div class="task-detail">
		<!-- Breadcrumb -->
		<nav class="task-detail__breadcrumb" aria-label="breadcrumb">
			<NcButton type="tertiary-no-background" @click="$router.push({ name: 'Projects' })">
				{{ t('planix', 'Projects') }}
			</NcButton>
			<span aria-hidden="true">&rsaquo;</span>
			<NcButton
				v-if="task && task.project"
				type="tertiary-no-background"
				@click="$router.push({ name: 'ProjectBoard', params: { id: task.project } })">
				{{ projectTitle }}
			</NcButton>
			<span aria-hidden="true">&rsaquo;</span>
			<span>{{ task ? task.title : $route.params.taskId }}</span>
		</nav>

		<!-- Loading state -->
		<div v-if="loading" class="task-detail__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Not found -->
		<NcEmptyContent
			v-else-if="!task"
			:name="t('planix', 'Task not found')"
			:description="t('planix', 'This task could not be loaded.')">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>
		</NcEmptyContent>

		<!-- Task content -->
		<template v-else>
			<div class="task-detail__header">
				<h2 class="task-detail__title">
					{{ task.title }}
				</h2>
				<span v-if="task.status" class="task-detail__status">
					{{ task.status }}
				</span>
			</div>

			<p v-if="task.description" class="task-detail__description">
				{{ task.description }}
			</p>

			<div class="task-detail__meta">
				<span v-if="task.assignedTo">
					{{ t('planix', 'Assigned to:') }} {{ task.assignedTo }}
				</span>
				<span v-if="task.dueDate">
					{{ t('planix', 'Due:') }} {{ task.dueDate }}
				</span>
			</div>

			<!-- Time Log section -->
			<TimeLog :task-id="$route.params.taskId" />
		</template>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import { useObjectStore } from '@conduction/nextcloud-vue'
import { useProjectsStore } from '../store/projects.js'
import TimeLog from '../components/TimeLog.vue'

const REGISTER = 'planix'
const TASK_SCHEMA = 'task'

export default {
	name: 'TaskDetail',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		AlertCircleOutline,
		TimeLog,
	},

	data() {
		return {
			task: null,
			loading: false,
		}
	},

	computed: {
		projectsStore() {
			return useProjectsStore()
		},
		projectTitle() {
			return this.projectsStore.activeProject?.title || this.task?.project || ''
		},
	},

	async mounted() {
		await this.fetchTask()
	},

	methods: {
		async fetchTask() {
			this.loading = true
			try {
				const objectStore = useObjectStore()
				if (!objectStore.objectTypeRegistry?.[TASK_SCHEMA]) {
					objectStore.registerObjectType(TASK_SCHEMA, TASK_SCHEMA, REGISTER)
				}
				this.task = await objectStore.fetchObject(TASK_SCHEMA, this.$route.params.taskId)

				// Load project info for breadcrumb.
				if (this.task?.project && !this.projectsStore.activeProject) {
					await this.projectsStore.fetchProject(this.task.project)
				}
			} catch (err) {
				console.error('fetchTask error:', err)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.task-detail {
	padding: 8px 4px 24px;
	max-width: 900px;
}

.task-detail__breadcrumb {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-bottom: 16px;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
}

.task-detail__loading {
	display: flex;
	justify-content: center;
	padding: 60px;
}

.task-detail__header {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 16px;
}

.task-detail__title {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
	flex: 1;
}

.task-detail__status {
	font-size: 13px;
	padding: 2px 10px;
	border-radius: 12px;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	text-transform: capitalize;
}

.task-detail__description {
	margin: 0 0 16px;
	color: var(--color-text-lighter);
	line-height: 1.5;
}

.task-detail__meta {
	display: flex;
	gap: 24px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>
