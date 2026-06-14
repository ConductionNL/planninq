<template>
	<div class="task-detail">
		<!-- Loading state -->
		<div v-if="loading" class="task-detail__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Not found / forbidden state -->
		<NcEmptyContent
			v-else-if="!task"
			:name="errorTitle"
			:description="errorDescription">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>
			<template #action>
				<NcButton type="primary" @click="goBack">
					{{ t('planix', 'Back to board') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Task detail + collaboration sidebar -->
		<div v-else class="task-detail__layout">
			<div class="task-detail__main">
				<div class="task-detail__header">
					<NcButton type="tertiary" @click="goBack">
						<template #icon>
							<ArrowLeft :size="20" />
						</template>
						{{ t('planix', 'Back to board') }}
					</NcButton>
					<h2 class="task-detail__title">
						{{ taskTitle }}
					</h2>
				</div>

				<dl class="task-detail__fields">
					<template v-for="field in fields">
						<dt :key="field.key + '-dt'">
							{{ field.label }}
						</dt>
						<dd :key="field.key + '-dd'">
							{{ field.value || '—' }}
						</dd>
					</template>
				</dl>
			</div>

			<!-- Collaboration sidebar: comments (notes), files, audit trail.
			     Legacy hardcoded-tabs mode (use-registry=false) so the three
			     built-in tabs render without requiring the integration registry;
			     generic tags/tasks tabs are hidden. All data comes from
			     OpenRegister per-object endpoints (ADR-022) — no planix PHP. -->
			<CnObjectSidebar
				:open="true"
				:use-registry="false"
				:object-id="taskId"
				object-type="planix-task"
				:register="register"
				schema="task"
				:title="taskTitle"
				:subtitle="t('planix', 'Task')"
				:hidden-tabs="['tags', 'tasks']"
				:files-label="t('planix', 'Attachments')"
				:notes-label="t('planix', 'Comments')"
				:audit-trail-label="t('planix', 'Activity')"
				@update:open="onSidebarToggle" />
		</div>
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { CnObjectSidebar } from '@conduction/nextcloud-vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import { useProjectsStore } from '../store/projects.js'
import { useSettingsStore } from '../store/modules/settings.js'

/**
 * Task detail view.
 *
 * Renders a single task's fields and mounts the collaboration sidebar
 * (Comments / Attachments / Activity tabs) backed by OpenRegister per-object
 * APIs. Reached from the board via the `?task=<uuid>` deep-link or the
 * `/projects/:id/tasks/:taskId` route.
 *
 * @spec openspec/specs/task-collaboration.md
 */
export default {
	name: 'TaskDetail',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		CnObjectSidebar,
		ArrowLeft,
		AlertCircleOutline,
	},

	data() {
		return {
			projectsStore: useProjectsStore(),
			settingsStore: useSettingsStore(),
			/** OpenRegister register slug the task lives in. */
			register: 'planix',
		}
	},

	computed: {
		...mapState(useProjectsStore, ['activeTask', 'loading', 'error']),

		/** UUID of the task from the route. */
		taskId() {
			return this.$route.params.taskId
		},

		/** The loaded task object (or null). */
		task() {
			return this.activeTask
		},

		/** Display title of the task. */
		taskTitle() {
			return this.task?.title || this.t('planix', 'Untitled task')
		},

		/** Label/value pairs rendered in the detail body. */
		fields() {
			const t = this.task || {}
			return [
				{ key: 'status', label: this.t('planix', 'Status'), value: t.status },
				{ key: 'priority', label: this.t('planix', 'Priority'), value: t.priority },
				{ key: 'assignedTo', label: this.t('planix', 'Assigned to'), value: t.assignedTo },
				{ key: 'dueDate', label: this.t('planix', 'Due date'), value: t.dueDate },
				{ key: 'description', label: this.t('planix', 'Description'), value: t.description },
			]
		},

		/** Title shown in the not-found / forbidden empty state. */
		errorTitle() {
			return this.error === 'forbidden'
				? this.t('planix', 'You do not have access to this task')
				: this.t('planix', 'Task not found')
		},

		/** Description shown in the not-found / forbidden empty state. */
		errorDescription() {
			return this.error === 'forbidden'
				? this.t('planix', 'You are not a member of this task\'s project.')
				: this.t('planix', 'The task may have been deleted.')
		},
	},

	watch: {
		taskId: {
			immediate: true,
			handler(id) {
				if (id) {
					this.projectsStore.fetchTask(id)
				}
			},
		},
	},

	methods: {
		/** Navigate back to the project board. */
		goBack() {
			const projectId = this.$route.params.id
			if (projectId) {
				this.$router.push({ name: 'ProjectBoard', params: { id: projectId } })
			} else {
				this.$router.push({ name: 'Projects' })
			}
		},

		/**
		 * Sidebar open/close handler. The sidebar is part of the detail layout,
		 * so closing it returns to the board rather than leaving an empty page.
		 *
		 * @param {boolean} open Whether the sidebar is now open.
		 */
		onSidebarToggle(open) {
			if (!open) {
				this.goBack()
			}
		},
	},
}
</script>

<style scoped>
.task-detail {
	height: 100%;
}

.task-detail__loading {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
}

.task-detail__layout {
	display: flex;
	height: 100%;
}

.task-detail__main {
	flex: 1 1 auto;
	padding: 24px;
	overflow-y: auto;
}

.task-detail__header {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 24px;
}

.task-detail__title {
	margin: 0;
}

.task-detail__fields {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 8px 24px;
	max-width: 640px;
}

.task-detail__fields dt {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.task-detail__fields dd {
	margin: 0;
}
</style>
