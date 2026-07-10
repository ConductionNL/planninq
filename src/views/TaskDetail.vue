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

				<!-- Time tracking -->
				<section class="task-detail__time" aria-labelledby="task-detail-time-heading">
					<h3 id="task-detail-time-heading" class="task-detail__section-title">
						{{ t('planix', 'Time tracking') }}
					</h3>

					<!-- Estimate input -->
					<div class="task-detail__estimate">
						<NcTextField
							:value.sync="estimateInput"
							:label="t('planix', 'Estimate')"
							:error="!!estimateError"
							:helper-text="estimateError || t('planix', 'e.g. 2h 30m, 90m, 1.5h')"
							data-testid="estimate-input" />
						<NcButton
							type="secondary"
							:disabled="savingEstimate || !!estimateError || estimateInput.trim() === ''"
							@click="saveEstimate">
							{{ t('planix', 'Save estimate') }}
						</NcButton>
					</div>

					<!-- Progress: logged vs estimate -->
					<p
						v-if="estimateMinutes > 0"
						class="task-detail__progress"
						:class="{ 'task-detail__progress--over': isOverEstimate }"
						data-testid="time-progress">
						{{ progressText }}
						<span v-if="isOverEstimate" class="task-detail__overage">
							({{ t('planix', 'over by {amount}', { amount: overageText }) }})
						</span>
					</p>
					<p v-else class="task-detail__progress" data-testid="time-progress">
						{{ t('planix', 'Logged: {logged}', { logged: loggedText }) }}
					</p>

					<!-- Log time -->
					<NcButton type="primary" data-testid="log-time" @click="openLogDialog()">
						<template #icon>
							<ClockPlusOutline :size="20" />
						</template>
						{{ t('planix', 'Log time') }}
					</NcButton>

					<!-- Entries -->
					<ul v-if="timeEntries.length" class="task-detail__entries">
						<li v-for="entry in timeEntries" :key="entry.id" class="task-detail__entry">
							<span class="task-detail__entry-duration">{{ formatMinutes(entry.duration) }}</span>
							<span class="task-detail__entry-date">{{ entry.date }}</span>
							<span class="task-detail__entry-desc">{{ entry.description }}</span>
							<span class="task-detail__entry-user">{{ entry.user }}</span>
							<NcActions v-if="canModify(entry)">
								<NcActionButton :close-after-click="true" @click="openLogDialog(entry)">
									<template #icon>
										<PencilIcon :size="20" />
									</template>
									{{ t('planix', 'Edit') }}
								</NcActionButton>
								<NcActionButton :close-after-click="true" @click="deleteEntry(entry)">
									<template #icon>
										<DeleteIcon :size="20" />
									</template>
									{{ t('planix', 'Delete') }}
								</NcActionButton>
							</NcActions>
						</li>
					</ul>
				</section>
			</div>

			<!-- Collaboration sidebar: comments (notes), files, audit trail.
			     Legacy hardcoded-tabs mode (use-registry=false) so the three
			     built-in tabs render without requiring the integration registry;
			     generic tags/tasks tabs are hidden. All data comes from
			     OpenRegister per-object endpoints (ADR-022) — no planix PHP. -->
			<CnObjectSidebar
				:open="true"
				v-bind="sidebarConfig"
				:title="taskTitle"
				:subtitle="t('planix', 'Task')"
				:files-label="t('planix', 'Attachments')"
				:notes-label="t('planix', 'Comments')"
				:audit-trail-label="t('planix', 'Activity')"
				@update:open="onSidebarToggle" />
		</div>

		<!-- Log/edit time dialog -->
		<TimeEntryDialog
			v-if="dialogOpen"
			:task-id="taskId"
			:entry="editingEntry"
			@close="closeDialog"
			@saved="onEntrySaved" />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { NcActions, NcActionButton, NcButton, NcEmptyContent, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import { CnObjectSidebar } from '@conduction/nextcloud-vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import ClockPlusOutline from 'vue-material-design-icons/ClockPlusOutline.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import { useProjectsStore } from '../store/projects.js'
import { useTimeEntriesStore } from '../store/timeEntries.js'
import { useSettingsStore } from '../store/modules/settings.js'
import { taskCollaborationSidebarConfig } from '../utils/taskHelpers.js'
import { parseDuration, formatDuration } from '../utils/durationParser.js'
import TimeEntryDialog from '../components/dialogs/TimeEntryDialog.vue'

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
		NcActions,
		NcActionButton,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcTextField,
		CnObjectSidebar,
		ArrowLeft,
		AlertCircleOutline,
		ClockPlusOutline,
		PencilIcon,
		DeleteIcon,
		TimeEntryDialog,
	},

	data() {
		return {
			projectsStore: useProjectsStore(),
			timeEntriesStore: useTimeEntriesStore(),
			settingsStore: useSettingsStore(),
			estimateInput: '',
			savingEstimate: false,
			dialogOpen: false,
			editingEntry: null,
		}
	},

	computed: {
		...mapState(useProjectsStore, ['activeTask', 'loading', 'error']),

		/**
		 * UUID of the task from the route.
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		taskId() {
			return this.$route.params.taskId
		},

		/**
		 * The loaded task object (or null).
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		task() {
			return this.activeTask
		},

		/**
		 * CnObjectSidebar props (register/schema/objectId/hidden tabs).
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		sidebarConfig() {
			return taskCollaborationSidebarConfig({ id: this.taskId })
		},

		/**
		 * Display title of the task.
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		taskTitle() {
			return this.task?.title || this.t('planix', 'Untitled task')
		},

		/**
		 * Label/value pairs rendered in the detail body.
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
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

		/**
		 * Time entries for this task (all users) from the timeEntries store.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		timeEntries() {
			return this.timeEntriesStore.entries
		},

		/**
		 * The task's estimate in minutes (0 when unset).
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		estimateMinutes() {
			return Number(this.task?.estimatedDuration) || 0
		},

		/**
		 * Total logged minutes across every entry on this task.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		loggedMinutes() {
			return this.timeEntries.reduce((acc, e) => acc + (Number(e.duration) || 0), 0)
		},

		/**
		 * Inline validation error for the estimate input (empty is allowed —
		 * the Save button is simply disabled — but non-empty must parse).
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		estimateError() {
			if (this.estimateInput.trim() === '') {
				return ''
			}
			return parseDuration(this.estimateInput) === null
				? this.t('planix', 'Enter a valid estimate (e.g. 2h 30m, 90m, 1.5h)')
				: ''
		},

		/**
		 * Human-readable total logged time.
		 *
		 * @spec exclude Display getter — formats loggedMinutes.
		 */
		loggedText() {
			return formatDuration(this.loggedMinutes)
		},

		/**
		 * Progress string "logged / estimate", e.g. "1h 30m / 3h".
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		progressText() {
			return `${formatDuration(this.loggedMinutes)} / ${formatDuration(this.estimateMinutes)}`
		},

		/**
		 * Whether logged time exceeds the estimate (progress turns red).
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		isOverEstimate() {
			return this.estimateMinutes > 0 && this.loggedMinutes > this.estimateMinutes
		},

		/**
		 * Human-readable overage (logged − estimate).
		 *
		 * @spec exclude Display getter — formats the overage amount.
		 */
		overageText() {
			return formatDuration(Math.max(0, this.loggedMinutes - this.estimateMinutes))
		},

		/**
		 * Title shown in the not-found / forbidden empty state.
		 *
		 * @spec exclude Presentational empty-state copy; no observable behaviour.
		 */
		errorTitle() {
			return this.error === 'forbidden'
				? this.t('planix', 'You do not have access to this task')
				: this.t('planix', 'Task not found')
		},

		/**
		 * Description shown in the not-found / forbidden empty state.
		 *
		 * @spec exclude Presentational empty-state copy; no observable behaviour.
		 */
		errorDescription() {
			return this.error === 'forbidden'
				? this.t('planix', 'You are not a member of this task\'s project.')
				: this.t('planix', 'The task may have been deleted.')
		},
	},

	watch: {
		taskId: {
			immediate: true,
			/**
			 * Load the task whenever the route's task id changes.
			 *
			 * @param {string} id The task UUID from the route.
			 *
			 * @spec openspec/specs/task-collaboration.md
			 */
			async handler(id) {
				if (id) {
					await this.projectsStore.fetchTask(id)
					this.estimateInput = this.estimateMinutes > 0 ? formatDuration(this.estimateMinutes) : ''
					await this.timeEntriesStore.fetchForTask(id)
				}
			},
		},
	},

	methods: {
		/**
		 * Navigate back to the project board.
		 *
		 * @spec exclude Router navigation glue; no observable spec behaviour.
		 */
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
		 *
		 * @spec exclude UI affordance (close returns to board); no spec behaviour.
		 */
		onSidebarToggle(open) {
			if (!open) {
				this.goBack()
			}
		},

		/**
		 * Format minutes for display.
		 *
		 * @param {number} minutes Whole minutes.
		 * @return {string} Human-readable duration.
		 *
		 * @spec exclude Display glue — wraps formatDuration for the template.
		 */
		formatMinutes(minutes) {
			return formatDuration(minutes)
		},

		/**
		 * Whether the current user may edit/delete a time entry (owner/admin).
		 *
		 * @param {object} entry The time entry.
		 * @return {boolean}
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		canModify(entry) {
			return this.timeEntriesStore.canModify(entry)
		},

		/**
		 * Persist the task estimate parsed from the estimate input.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		async saveEstimate() {
			const minutes = parseDuration(this.estimateInput)
			if (minutes === null) {
				return
			}
			this.savingEstimate = true
			try {
				await this.projectsStore.updateTask(this.taskId, { estimatedDuration: minutes })
				await this.projectsStore.fetchTask(this.taskId)
				this.estimateInput = formatDuration(this.estimateMinutes)
			} finally {
				this.savingEstimate = false
			}
		},

		/**
		 * Open the log-time dialog (new entry, or editing `entry`).
		 *
		 * @param {object|null} entry The entry to edit, or null to create.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		openLogDialog(entry = null) {
			this.editingEntry = entry
			this.dialogOpen = true
		},

		/**
		 * @spec exclude UI glue — closes the log-time dialog.
		 */
		closeDialog() {
			this.dialogOpen = false
			this.editingEntry = null
		},

		/**
		 * Refresh entries after a create/edit and close the dialog.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		async onEntrySaved() {
			this.closeDialog()
			await this.timeEntriesStore.fetchForTask(this.taskId)
		},

		/**
		 * Delete a time entry and refresh the task's total.
		 *
		 * @param {object} entry The entry to delete.
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		async deleteEntry(entry) {
			await this.timeEntriesStore.delete(entry.id)
			await this.timeEntriesStore.fetchForTask(this.taskId)
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

.task-detail__time {
	margin-top: 32px;
	max-width: 640px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.task-detail__section-title {
	margin: 0;
	font-size: 16px;
}

.task-detail__estimate {
	display: flex;
	align-items: flex-start;
	gap: 8px;
}

.task-detail__estimate > :first-child {
	flex: 1 1 auto;
}

.task-detail__progress {
	margin: 0;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.task-detail__progress--over {
	color: var(--color-error);
}

.task-detail__overage {
	font-weight: normal;
}

.task-detail__entries {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.task-detail__entry {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 6px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.task-detail__entry-duration {
	font-weight: 600;
	min-width: 64px;
}

.task-detail__entry-desc {
	flex: 1 1 auto;
	color: var(--color-text-maxcontrast);
}

.task-detail__entry-user {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}
</style>
