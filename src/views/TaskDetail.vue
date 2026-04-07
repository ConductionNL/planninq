<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@spec openspec/changes/time-tracking-mvp/tasks.md#task-5
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
			<span>{{ task ? task.title : t('planix', 'Task') }}</span>
		</nav>

		<!-- Loading -->
		<div v-if="loading" class="task-detail__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Task content -->
		<template v-else-if="task">
			<div class="task-detail__header">
				<h2>{{ task.title }}</h2>
				<div class="task-detail__meta">
					<span v-if="task.status" class="task-detail__status">{{ task.status }}</span>
					<span v-if="task.priority" class="task-detail__priority">{{ task.priority }}</span>
					<span v-if="task.assignedTo">{{ t('planix', 'Assigned to {user}', { user: task.assignedTo }) }}</span>
				</div>
			</div>

			<p v-if="task.description" class="task-detail__description">
				{{ task.description }}
			</p>

			<!-- Time Estimate -->
			<div class="task-detail__section">
				<h3>{{ t('planix', 'Time Estimate') }}</h3>
				<DurationInput
					:value="task.estimatedDuration || null"
					:label="t('planix', 'Estimated duration')"
					@input="updateEstimate" />
			</div>

			<!-- Time Tracking Section -->
			<div class="task-detail__section">
				<div class="task-detail__section-header" @click="timeTrackingOpen = !timeTrackingOpen">
					<h3>{{ t('planix', 'Time Tracking') }}</h3>
					<ChevronDown v-if="timeTrackingOpen" :size="20" />
					<ChevronRight v-else :size="20" />
				</div>

				<template v-if="timeTrackingOpen">
					<!-- Progress bar: logged vs estimated -->
					<div v-if="task.estimatedDuration" class="task-detail__time-progress">
						<div class="task-detail__time-summary">
							<span>{{ formatDuration(loggedDuration) }} / {{ formatDuration(task.estimatedDuration) }}</span>
							<span>{{ progressPercent }}%</span>
						</div>
						<div class="task-detail__progress-bar">
							<div
								class="task-detail__progress-fill"
								:class="{ 'task-detail__progress-fill--over': progressPercent > 100 }"
								:style="{ width: Math.min(progressPercent, 100) + '%' }" />
						</div>
					</div>
					<div v-else class="task-detail__time-summary">
						<span>{{ t('planix', 'Logged: {duration}', { duration: formatDuration(loggedDuration) }) }}</span>
					</div>

					<!-- Log Time button / form -->
					<div class="task-detail__log-time">
						<NcButton
							v-if="!showTimeForm"
							type="secondary"
							@click="showTimeForm = true">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							{{ t('planix', 'Log Time') }}
						</NcButton>
						<TimeEntryForm
							v-else
							:task-id="task.id"
							@saved="onTimeEntrySaved"
							@cancel="showTimeForm = false" />
					</div>

					<!-- Time entries list -->
					<div v-if="timeEntries.length > 0" class="task-detail__entries">
						<div
							v-for="entry in timeEntries"
							:key="entry.id"
							class="task-detail__entry">
							<div class="task-detail__entry-info">
								<span class="task-detail__entry-duration">{{ formatDuration(entry.duration) }}</span>
								<span class="task-detail__entry-date">{{ entry.date }}</span>
								<span v-if="entry.description" class="task-detail__entry-desc">{{ entry.description }}</span>
								<span class="task-detail__entry-user">{{ entry.user }}</span>
							</div>
							<NcButton
								v-if="entry.user === currentUser"
								type="tertiary"
								:aria-label="t('planix', 'Delete time entry')"
								@click="deleteEntry(entry)">
								<template #icon>
									<DeleteOutline :size="20" />
								</template>
							</NcButton>
						</div>
					</div>
					<p v-else class="task-detail__no-entries">
						{{ t('planix', 'No time entries yet.') }}
					</p>
				</template>
			</div>
		</template>

		<!-- Not found -->
		<NcEmptyContent
			v-else
			:name="t('planix', 'Task not found')">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { getCurrentUser } from '@nextcloud/auth'
import { useObjectStore } from '@conduction/nextcloud-vue'
import { showError, showSuccess } from '@nextcloud/dialogs'

import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import DurationInput from '../components/DurationInput.vue'
import TimeEntryForm from '../components/TimeEntryForm.vue'
import { formatDuration } from '../utils/duration.js'
import { useProjectsStore } from '../store/projects.js'

/**
 * Task detail view with time tracking section.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-5
 */
export default {
	name: 'TaskDetail',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		AlertCircleOutline,
		ChevronDown,
		ChevronRight,
		DeleteOutline,
		PlusIcon,
		DurationInput,
		TimeEntryForm,
	},

	data() {
		return {
			task: null,
			timeEntries: [],
			loading: false,
			timeTrackingOpen: true,
			showTimeForm: false,
		}
	},

	computed: {
		currentUser() {
			return getCurrentUser()?.uid || ''
		},
		loggedDuration() {
			return this.timeEntries.reduce((sum, e) => sum + (e.duration || 0), 0)
		},
		progressPercent() {
			if (!this.task?.estimatedDuration) return 0
			return Math.round((this.loggedDuration / this.task.estimatedDuration) * 100)
		},
		projectTitle() {
			const store = useProjectsStore()
			return store.activeProject?.title || this.t('planix', 'Project')
		},
	},

	async mounted() {
		await this.loadTask()
	},

	methods: {
		formatDuration,

		async loadTask() {
			this.loading = true
			try {
				const objectStore = useObjectStore()
				const taskId = this.$route.params.taskId
				this.task = await objectStore.fetchObject('task', taskId)

				if (this.task) {
					await this.loadTimeEntries()
					// Load project for breadcrumb
					if (this.task.project) {
						const projectsStore = useProjectsStore()
						if (!projectsStore.activeProject || projectsStore.activeProject.id !== this.task.project) {
							await projectsStore.fetchProject(this.task.project)
						}
					}
				}
			} catch (err) {
				console.error('Failed to load task:', err)
			} finally {
				this.loading = false
			}
		},

		async loadTimeEntries() {
			try {
				const objectStore = useObjectStore()
				this.timeEntries = await objectStore.fetchCollection('timeEntry', { task: this.task.id })
			} catch (err) {
				console.error('Failed to load time entries:', err)
				this.timeEntries = []
			}
		},

		async updateEstimate(minutes) {
			try {
				const objectStore = useObjectStore()
				const updated = await objectStore.saveObject('task', {
					id: this.task.id,
					estimatedDuration: minutes,
				})
				if (updated) {
					this.task = { ...this.task, estimatedDuration: minutes }
				}
			} catch (err) {
				showError(this.t('planix', 'Failed to update estimate'))
			}
		},

		async onTimeEntrySaved() {
			this.showTimeForm = false
			await this.loadTimeEntries()
		},

		async deleteEntry(entry) {
			try {
				const objectStore = useObjectStore()
				const ok = await objectStore.deleteObject('timeEntry', entry.id)
				if (ok !== false) {
					this.timeEntries = this.timeEntries.filter((e) => e.id !== entry.id)
					showSuccess(this.t('planix', 'Time entry deleted'))
				}
			} catch (err) {
				showError(this.t('planix', 'Failed to delete time entry'))
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
	margin-bottom: 16px;
}

.task-detail__header h2 {
	margin: 0 0 8px;
	font-size: 22px;
	font-weight: 600;
}

.task-detail__meta {
	display: flex;
	gap: 12px;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}

.task-detail__status,
.task-detail__priority {
	padding: 2px 8px;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-dark);
	font-size: 12px;
	text-transform: capitalize;
}

.task-detail__description {
	margin-bottom: 24px;
	color: var(--color-text-lighter);
	line-height: 1.6;
}

.task-detail__section {
	margin-bottom: 24px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.task-detail__section h3 {
	margin: 0 0 12px;
	font-size: 16px;
	font-weight: 600;
}

.task-detail__section-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	cursor: pointer;
}

.task-detail__section-header h3 {
	margin: 0;
}

.task-detail__time-progress {
	margin-bottom: 16px;
}

.task-detail__time-summary {
	display: flex;
	justify-content: space-between;
	margin-bottom: 4px;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
}

.task-detail__progress-bar {
	height: 8px;
	background: var(--color-background-dark);
	border-radius: 4px;
	overflow: hidden;
}

.task-detail__progress-fill {
	height: 100%;
	background: var(--color-primary);
	border-radius: 4px;
	transition: width 0.3s ease;
}

.task-detail__progress-fill--over {
	background: var(--color-warning);
}

.task-detail__log-time {
	margin-bottom: 16px;
}

.task-detail__entries {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.task-detail__entry {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 12px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
}

.task-detail__entry-info {
	display: flex;
	gap: 12px;
	align-items: center;
	font-size: 14px;
}

.task-detail__entry-duration {
	font-weight: 600;
	min-width: 60px;
}

.task-detail__entry-date {
	color: var(--color-text-maxcontrast);
}

.task-detail__entry-desc {
	color: var(--color-text-lighter);
}

.task-detail__entry-user {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.task-detail__no-entries {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
