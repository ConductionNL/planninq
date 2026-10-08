<template>
	<div class="project-log">
		<div class="project-log__header">
			<h2>{{ t('planninq', 'Log') }}</h2>
			<NcButton variant="primary" data-testid="log-add" @click="openEditor(null)">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('planninq', 'Add entry') }}
			</NcButton>
		</div>

		<ProjectTabs :projectId="projectId" />

		<div class="project-log__filters" role="group" :aria-label="t('planninq', 'Show')">
			<NcButton
				v-for="option in filterOptions"
				:key="option.id"
				:variant="filter === option.id ? 'primary' : 'tertiary'"
				:aria-pressed="filter === option.id"
				:data-testid="`log-filter-${option.id}`"
				@click="setFilter(option.id)">
				{{ option.label }}
			</NcButton>
		</div>

		<div v-if="loading" class="project-log__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Actions: the project's tasks that came out of a log entry -->
		<template v-else-if="filter === 'actions'">
			<ul v-if="actions.length" class="project-log__list" data-testid="log-actions">
				<li v-for="task in actions"
					:key="task.id"
					class="project-log__action"
					:data-title="task.title">
					<RouterLink :to="{ name: 'TaskDetail', params: { id: projectId, taskId: task.id } }">
						{{ task.title }}
					</RouterLink>
					<span class="project-log__chip">{{ statusLabel(task.status) }}</span>
					<span v-if="task.assignedTo" class="project-log__muted">{{ names[task.assignedTo] || task.assignedTo }}</span>
					<span v-if="task.dueDate" class="project-log__muted">{{ task.dueDate }}</span>
				</li>
			</ul>
			<NcEmptyContent v-else :name="t('planninq', 'No actions yet')" />
		</template>

		<ol v-else-if="visibleEntries.length" class="project-log__list" data-testid="log-list">
			<li
				v-for="entry in visibleEntries"
				:key="entry.id"
				class="project-log__entry"
				data-testid="log-entry"
				:data-title="entry.title">
				<div class="project-log__entry-head">
					<span class="project-log__chip" data-testid="log-entry-type-label">{{ typeLabel(entry.type) }}</span>
					<strong>{{ entry.title }}</strong>
					<span v-if="entry.type === 'issue'" class="project-log__chip">
						{{ entry.status === 'closed' ? t('planninq', 'Closed') : t('planninq', 'Open') }}
					</span>
					<NcActions :aria-label="t('planninq', 'Entry actions')" :forceMenu="true">
						<NcActionButton :closeAfterClick="true" @click="openEditor(entry)">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('planninq', 'Edit') }}
						</NcActionButton>
						<NcActionButton
							v-if="entry.type !== 'lesson'"
							:closeAfterClick="true"
							data-testid="log-entry-add-action"
							@click="actionFor = entry">
							<template #icon>
								<CheckboxMarkedOutline :size="20" />
							</template>
							{{ t('planninq', 'Add action') }}
						</NcActionButton>
					</NcActions>
				</div>
				<p class="project-log__meta" data-testid="log-entry-meta">
					{{ entry.date }}
					<template v-if="authorOf(entry)">
						· {{ t('planninq', 'by {author}', { author: names[authorOf(entry)] || authorOf(entry) }) }}
					</template>
					<template v-if="savedAt(entry)">
						· {{ t('planninq', 'saved {time}', { time: savedAt(entry) }) }}
					</template>
				</p>
				<p v-if="entry.attendees && entry.attendees.length" class="project-log__meta">
					{{ t('planninq', 'Attendees: {names}', { names: entry.attendees.map((uid) => names[uid] || uid).join(', ') }) }}
				</p>
				<p v-if="entry.body" class="project-log__body">
					{{ entry.body }}
				</p>
				<ul v-if="linkedActions(entry).length" class="project-log__linked" data-testid="log-entry-actions">
					<li v-for="task in linkedActions(entry)" :key="task.id">
						<RouterLink :to="{ name: 'TaskDetail', params: { id: projectId, taskId: task.id } }">
							{{ task.title }}
						</RouterLink>
						<span class="project-log__chip">{{ statusLabel(task.status) }}</span>
					</li>
				</ul>
			</li>
		</ol>

		<NcEmptyContent v-else :name="t('planninq', 'Nothing logged yet')">
			<template #action>
				<NcButton @click="openEditor(null)">
					{{ t('planninq', 'Add the first entry') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<LogEntryEditDialog
			v-if="editing"
			:entry="editing.entry"
			:projectId="projectId"
			:people="peopleOptions"
			@close="editing = null"
			@saved="onEntrySaved" />

		<LogActionDialog
			v-if="actionFor"
			:entry="actionFor"
			:projectId="projectId"
			:people="peopleOptions"
			:columns="columns"
			:tasks="tasks"
			@close="actionFor = null"
			@saved="onActionSaved" />
	</div>
</template>

<script>
/**
 * ProjectLog.
 *
 * The project's log: issues, lessons learned, meetings and decisions, newest
 * first, with a filter per type and an Actions filter over the project's
 * tasks with `issueType: 'action'`. "Add action" on an entry creates such a
 * task and links it. The filter lives in the query string.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
 */
import { NcActionButton, NcActions, NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import CheckboxMarkedOutline from 'vue-material-design-icons/CheckboxMarkedOutline.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import LogActionDialog from '../dialogs/LogActionDialog.vue'
import LogEntryEditDialog from '../dialogs/LogEntryEditDialog.vue'
import { useProjectsStore } from '../store/projects.js'
import { actionTasks, entryAuthor, entryCreated, filterLog, LOG_TYPES, projectPeople } from '../utils/projectOverview.js'
import { displayNames } from '../utils/userNames.js'

export default {
	name: 'ProjectLog',

	components: {
		CheckboxMarkedOutline,
		LogActionDialog,
		LogEntryEditDialog,
		NcActionButton,
		NcActions,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		PencilIcon,
		PlusIcon,
		ProjectTabs,
	},

	data() {
		return {
			project: null,
			entries: [],
			tasks: [],
			columns: [],
			names: {},
			loading: true,
			editing: null,
			actionFor: null,
		}
	},

	computed: {
		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		filter() {
			const value = String(this.$route.query.type || 'all')
			return [...LOG_TYPES, 'actions'].includes(value) ? value : 'all'
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		filterOptions() {
			return [
				{ id: 'all', label: this.t('planninq', 'All') },
				{ id: 'issue', label: this.t('planninq', 'Issues') },
				{ id: 'lesson', label: this.t('planninq', 'Lessons learned') },
				{ id: 'meeting', label: this.t('planninq', 'Meetings') },
				{ id: 'decision', label: this.t('planninq', 'Decisions') },
				{ id: 'actions', label: this.t('planninq', 'Actions') },
			]
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		visibleEntries() {
			return filterLog(this.entries, this.filter)
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		actions() {
			return actionTasks(this.tasks)
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		peopleOptions() {
			return projectPeople(this.project).map((uid) => ({ id: uid, label: this.names[uid] || uid }))
		},
	},

	watch: {
		projectId: {
			immediate: true,
			/**
			 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		/**
		 * Load the project, its log, its tasks and its columns.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		async load() {
			this.loading = true
			const store = useProjectsStore()
			try {
				this.project = await store.fetchProject(this.projectId)
				const [entries, tasks, columns] = await Promise.all([
					store.fetchLogEntries(this.projectId),
					store.fetchTasks(this.projectId),
					store.fetchColumns(this.projectId),
				])
				this.entries = entries
				this.tasks = tasks
				this.columns = columns
				await this.resolveNames()
			} finally {
				this.loading = false
			}
			if (this.$route.query.add === '1') {
				this.openEditor(null)
			}
		},

		/**
		 * Resolve the names of the people on the project and of every author.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		async resolveNames() {
			const uids = new Set(projectPeople(this.project))
			for (const entry of this.entries) {
				if (entryAuthor(entry)) {
					uids.add(entryAuthor(entry))
				}
			}
			this.names = await displayNames([...uids])
		},

		/**
		 * @param {string} id The filter.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		setFilter(id) {
			const query = { ...this.$route.query }
			delete query.type
			delete query.add
			this.$router.replace({ query: id === 'all' ? query : { ...query, type: id } })
		},

		/**
		 * @param {object|null} entry The entry to edit, or null for a new one.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		openEditor(entry) {
			this.editing = { entry }
		},

		/**
		 * @param {object} saved The saved entry.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		async onEntrySaved(saved) {
			this.editing = null
			this.replaceEntry(saved)
			await this.resolveNames()
		},

		/**
		 * @param {{task: object, entry: object}} result The new task and the updated entry.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		onActionSaved({ task, entry }) {
			this.actionFor = null
			this.tasks = [...this.tasks, task]
			this.replaceEntry(entry)
		},

		/**
		 * @param {object} saved The saved entry.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		replaceEntry(saved) {
			const id = saved?.id ?? saved?.['@self']?.id
			const rest = this.entries.filter((entry) => entry.id !== id)
			this.entries = [{ ...saved, id }, ...rest]
		},

		/**
		 * @param {object} entry The entry.
		 * @return {Array<object>} The tasks it links, in link order.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		linkedActions(entry) {
			return (entry.actions || []).map((id) => this.tasks.find((task) => task.id === id)).filter(Boolean)
		},

		/**
		 * @param {object} entry The entry.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		authorOf(entry) {
			return entryAuthor(entry)
		},

		/**
		 * @param {object} entry The entry.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		savedAt(entry) {
			const created = entryCreated(entry)
			const date = created ? new Date(created) : null
			return date && !Number.isNaN(date.getTime()) ? date.toLocaleString() : ''
		},

		/**
		 * @param {string} type The entry type.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		typeLabel(type) {
			const labels = {
				issue: this.t('planninq', 'Issue'),
				lesson: this.t('planninq', 'Lesson learned'),
				meeting: this.t('planninq', 'Meeting'),
				decision: this.t('planninq', 'Decision'),
			}
			return labels[type] || type
		},

		/**
		 * @param {string} status The task status.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		statusLabel(status) {
			const labels = {
				open: this.t('planninq', 'Open'),
				in_progress: this.t('planninq', 'In Progress'),
				blocked: this.t('planninq', 'Blocked'),
				done: this.t('planninq', 'Done'),
				cancelled: this.t('planninq', 'Cancelled'),
			}
			return labels[status] || status
		},
	},
}
</script>

<style scoped>
.project-log {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.project-log__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.project-log__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-log__filters {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin-bottom: 16px;
}

.project-log__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.project-log__list,
.project-log__linked {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.project-log__entry {
	padding: 12px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.project-log__entry-head,
.project-log__action,
.project-log__linked li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.project-log__entry-head strong {
	flex: 1;
}

.project-log__chip {
	padding: 2px 8px;
	border-radius: var(--border-radius-pill);
	background-color: var(--color-background-dark);
	font-size: 13px;
}

.project-log__meta,
.project-log__muted {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.project-log__body {
	margin: 8px 0 0;
	white-space: pre-wrap;
}

.project-log__linked {
	gap: 4px;
	margin-top: 8px;
}

.project-log a {
	color: var(--color-primary-element);
	text-decoration: underline;
}
</style>
