<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@see openspec/changes/time-tracking-mvp/tasks.md#task-5
-->
<template>
	<div class="task-detail">
		<!-- Loading -->
		<div v-if="loading" class="task-detail__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Not found -->
		<NcEmptyContent
			v-else-if="!task"
			:name="t('planix', 'Task not found')"
			:description="t('planix', 'The requested task could not be loaded.')">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$router.back()">
					{{ t('planix', 'Go back') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Task content -->
		<template v-else>
			<!-- Header -->
			<div class="task-detail__header">
				<NcButton type="tertiary" @click="$router.back()">
					<template #icon>
						<ArrowLeft :size="20" />
					</template>
				</NcButton>
				<div class="task-detail__header-info">
					<h2 class="task-detail__title">
						{{ task.title }}
					</h2>
					<div class="task-detail__meta">
						<NcChip v-if="task.status" :text="task.status" no-close />
						<NcChip v-if="task.priority" :text="task.priority" no-close />
						<span v-if="task.assignedTo" class="task-detail__assignee">
							{{ task.assignedTo }}
						</span>
					</div>
				</div>
			</div>

			<!-- Description -->
			<div v-if="task.description" class="task-detail__description">
				<p>{{ task.description }}</p>
			</div>

			<!-- Time Estimate -->
			<div class="task-detail__section">
				<h3>{{ t('planix', 'Time Estimate') }}</h3>
				<DurationInput
					:value="task.estimatedDuration"
					:label="t('planix', 'Estimated duration')"
					@input="updateEstimate" />
			</div>

			<!-- Time Tracking Section -->
			<div class="task-detail__section">
				<div
					class="task-detail__section-header"
					@click="timeTrackingOpen = !timeTrackingOpen">
					<h3>{{ t('planix', 'Time Tracking') }}</h3>
					<ChevronDown
						:size="20"
						:class="{ 'task-detail__chevron--open': timeTrackingOpen }" />
				</div>

				<template v-if="timeTrackingOpen">
					<!-- Progress bar: logged vs estimated -->
					<div v-if="task.estimatedDuration" class="task-detail__time-progress">
						<div class="task-detail__time-progress-labels">
							<span>{{ formattedLogged }} / {{ formattedEstimate }}</span>
							<span>{{ rawProgressPercent }}%</span>
						</div>
						<NcProgressBar
							:value="progressPercent"
							:error="rawProgressPercent > 100" />
					</div>
					<div v-else class="task-detail__time-total">
						{{ t('planix', 'Total logged: {total}', { total: formattedLogged }) }}
					</div>

					<!-- Log time button / form -->
					<NcButton
						v-if="!showEntryForm"
						type="secondary"
						@click="openEntryForm(null)">
						<template #icon>
							<Plus :size="20" />
						</template>
						{{ t('planix', 'Log time') }}
					</NcButton>

					<TimeEntryForm
						v-if="showEntryForm"
						:task-id="task.id"
						:edit-entry="editingEntry"
						@saved="onEntrySaved"
						@cancel="closeEntryForm" />

					<!-- Time entries list -->
					<div v-if="timeEntries.length > 0" class="task-detail__entries">
						<div
							v-for="entry in timeEntries"
							:key="entry.id"
							class="task-detail__entry">
							<div class="task-detail__entry-info">
								<strong>{{ formatDuration(entry.duration) }}</strong>
								<span class="task-detail__entry-date">{{ entry.date }}</span>
								<span v-if="entry.description" class="task-detail__entry-desc">
									{{ entry.description }}
								</span>
							</div>
							<div class="task-detail__entry-meta">
								<span class="task-detail__entry-user">{{ entry.user }}</span>
								<NcButton
									v-if="isOwnEntry(entry)"
									type="tertiary"
									:aria-label="t('planix', 'Edit')"
									@click="openEntryForm(entry)">
									<template #icon>
										<Pencil :size="16" />
									</template>
								</NcButton>
								<NcButton
									v-if="isOwnEntry(entry)"
									type="tertiary"
									:aria-label="t('planix', 'Delete')"
									@click="deleteEntry(entry)">
									<template #icon>
										<Delete :size="16" />
									</template>
								</NcButton>
							</div>
						</div>
					</div>
					<p v-else class="task-detail__no-entries">
						{{ t('planix', 'No time entries yet.') }}
					</p>
				</template>
			</div>
		</template>
	</div>
</template>

<script>
/**
 * Task detail view with time tracking section.
 *
 * @see openspec/changes/time-tracking-mvp/tasks.md#task-5
 */
import { NcButton, NcChip, NcEmptyContent, NcLoadingIcon, NcProgressBar } from '@nextcloud/vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { useObjectStore } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import DurationInput, { formatDuration } from '../components/DurationInput.vue'
import TimeEntryForm from '../components/TimeEntryForm.vue'

const REGISTER = 'planix'
const TASK_SCHEMA = 'task'
const TIME_ENTRY_SCHEMA = 'timeEntry'

export default {
	name: 'TaskDetail',

	components: {
		NcButton,
		NcChip,
		NcEmptyContent,
		NcLoadingIcon,
		NcProgressBar,
		AlertCircleOutline,
		ArrowLeft,
		ChevronDown,
		Delete,
		Pencil,
		Plus,
		DurationInput,
		TimeEntryForm,
	},

	data() {
		return {
			task: null,
			timeEntries: [],
			loading: true,
			timeTrackingOpen: true,
			showEntryForm: false,
			editingEntry: null,
			objectStore: null,
		}
	},

	computed: {
		/**
		 * Sum of all time entry durations.
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-5
		 * @return {number}
		 */
		loggedDuration() {
			return this.timeEntries.reduce((sum, e) => sum + (e.duration || 0), 0)
		},
		formattedLogged() {
			return formatDuration(this.loggedDuration)
		},
		formattedEstimate() {
			return formatDuration(this.task?.estimatedDuration)
		},
		rawProgressPercent() {
			if (!this.task?.estimatedDuration) return 0
			return Math.round((this.loggedDuration / this.task.estimatedDuration) * 100)
		},
		progressPercent() {
			return Math.min(this.rawProgressPercent, 100)
		},
	},

	created() {
		const store = useObjectStore()
		if (!store.objectTypeRegistry?.[TASK_SCHEMA]) {
			store.registerObjectType(TASK_SCHEMA, TASK_SCHEMA, REGISTER)
		}
		if (!store.objectTypeRegistry?.[TIME_ENTRY_SCHEMA]) {
			store.registerObjectType(TIME_ENTRY_SCHEMA, TIME_ENTRY_SCHEMA, REGISTER)
		}
		this.objectStore = store
	},

	async mounted() {
		await this.loadTask()
	},

	methods: {
		t,
		formatDuration,

		/**
		 * Load task and its time entries.
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-5
		 */
		async loadTask() {
			this.loading = true
			try {
				const taskId = this.$route.params.taskId
				this.task = await this.objectStore.fetchObject(TASK_SCHEMA, taskId)
				if (this.task) {
					await this.loadTimeEntries()
				}
			} catch (err) {
				console.error('TaskDetail loadTask error:', err)
			} finally {
				this.loading = false
			}
		},

		async loadTimeEntries() {
			try {
				const entries = await this.objectStore.fetchCollection(TIME_ENTRY_SCHEMA, {
					task: this.task.id,
				})
				this.timeEntries = entries.sort((a, b) => (b.date || '').localeCompare(a.date || ''))
			} catch (err) {
				console.error('TaskDetail loadTimeEntries error:', err)
				this.timeEntries = []
			}
		},

		/**
		 * Update the estimated duration on the task.
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-3
		 * @param {number|null} minutes New estimate
		 */
		async updateEstimate(minutes) {
			try {
				const updated = await this.objectStore.saveObject(TASK_SCHEMA, {
					id: this.task.id,
					estimatedDuration: minutes,
				})
				if (updated) {
					this.task = { ...this.task, estimatedDuration: minutes }
				}
			} catch (err) {
				console.error('updateEstimate error:', err)
				showError(t('planix', 'Failed to update estimate'))
			}
		},

		// TODO(SEC-003): This check is UI-only. The underlying OpenRegister DELETE/PATCH
		// endpoints are callable directly by any authenticated user regardless of ownership.
		// Server-side enforcement is tracked in: https://github.com/ConductionNL/planix/issues/146
		isOwnEntry(entry) {
			return entry.user === (getCurrentUser()?.uid || '')
		},

		openEntryForm(entry) {
			this.editingEntry = entry
			this.showEntryForm = true
		},

		closeEntryForm() {
			this.showEntryForm = false
			this.editingEntry = null
		},

		async onEntrySaved() {
			this.closeEntryForm()
			await this.loadTimeEntries()
		},

		/**
		 * Delete a time entry.
		 *
		 * @see openspec/changes/time-tracking-mvp/tasks.md#task-5
		 * @param {object} entry The time entry to delete
		 */
		async deleteEntry(entry) {
			try {
				const ok = await this.objectStore.deleteObject(TIME_ENTRY_SCHEMA, entry.id)
				if (ok !== false) {
					this.timeEntries = this.timeEntries.filter((e) => e.id !== entry.id)
					showSuccess(t('planix', 'Time entry deleted'))
				} else {
					showError(t('planix', 'Failed to delete time entry'))
				}
			} catch (err) {
				console.error('deleteEntry error:', err)
				showError(t('planix', 'Failed to delete time entry'))
			}
		},
	},
}
</script>

<style scoped>
.task-detail {
	padding: 16px 24px 32px;
	max-width: 800px;
}

.task-detail__loading {
	display: flex;
	justify-content: center;
	padding: 60px;
}

.task-detail__header {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	margin-bottom: 20px;
}

.task-detail__header-info {
	flex: 1;
}

.task-detail__title {
	margin: 0 0 8px;
	font-size: 20px;
	font-weight: 600;
}

.task-detail__meta {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.task-detail__assignee {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.task-detail__description {
	margin-bottom: 24px;
	color: var(--color-text-light);
}

.task-detail__description p {
	margin: 0;
}

.task-detail__section {
	margin-bottom: 24px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
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
	user-select: none;
}

.task-detail__section-header h3 {
	margin-bottom: 0;
}

.task-detail__chevron--open {
	transform: rotate(180deg);
}

.task-detail__time-progress {
	margin-bottom: 16px;
}

.task-detail__time-progress-labels {
	display: flex;
	justify-content: space-between;
	font-size: 13px;
	margin-bottom: 4px;
	color: var(--color-text-maxcontrast);
}

.task-detail__time-total {
	margin-bottom: 16px;
	font-size: 14px;
	font-weight: 500;
}

.task-detail__entries {
	margin-top: 16px;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.task-detail__entry {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 12px;
	border-radius: var(--border-radius, 4px);
	background: var(--color-background-dark);
}

.task-detail__entry-info {
	display: flex;
	align-items: center;
	gap: 12px;
	flex: 1;
	min-width: 0;
}

.task-detail__entry-date {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.task-detail__entry-desc {
	font-size: 13px;
	color: var(--color-text-light);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.task-detail__entry-meta {
	display: flex;
	align-items: center;
	gap: 4px;
	flex-shrink: 0;
}

.task-detail__entry-user {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.task-detail__no-entries {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>
