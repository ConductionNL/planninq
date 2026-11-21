<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<ProjectTargetDialog
		v-model="target"
		:name="t('planninq', 'Move to project')"
		:loading="loading"
		:options="targetOptions"
		:emptyText="t('planninq', 'You are not a member of another project.')"
		:busy="busy"
		:error="submitError"
		:confirmLabel="t('planninq', 'Move task')"
		testid="task-move"
		@close="$emit('close')"
		@confirm="confirm">
		<p>
			{{ t('planninq', 'Move "{title}" to another project. It lands in that project\'s backlog.', { title: task.title }) }}
		</p>
		<template #details>
			<p v-if="subtasks.length" data-testid="task-move-subtasks">
				{{ t('planninq', 'Subtasks that move with it: {count}', { count: subtasks.length }) }}
			</p>

			<div v-if="links.length" class="task-move-dialog__warning" data-testid="task-move-links">
				<p>{{ t('planninq', 'These links to other tasks are removed:') }}</p>
				<ul>
					<li v-for="link in linkLabels" :key="link.id">
						{{ link.label }}
					</li>
				</ul>
			</div>

			<div v-if="target && cleared.length" class="task-move-dialog__warning" data-testid="task-move-people">
				<p>{{ t('planninq', 'These people are not members of {project} and are taken off the task:', { project: target.label }) }}</p>
				<ul>
					<li v-for="uid in cleared" :key="uid">
						{{ names[uid] || uid }}
					</li>
				</ul>
			</div>
		</template>
	</ProjectTargetDialog>
</template>

<script>
/**
 * TaskMoveDialog.
 *
 * Moves a task and its subtasks to another project the person works on. Before
 * the move it names the dependency links that are removed and the people who
 * are taken off because they are not members of the target.
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
 */
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import ProjectTargetDialog from './ProjectTargetDialog.vue'
import projectTarget from '../mixins/projectTarget.js'
import { useDependenciesStore } from '../store/dependencies.js'
import { useProjectsStore } from '../store/projects.js'
import { currentGroupIds } from '../utils/projectRole.js'
import { idOf, linksToRemove, moveTargets, peopleToClear } from '../utils/taskMove.js'
import { displayNames } from '../utils/userNames.js'

export default {
	name: 'TaskMoveDialog',

	components: {
		ProjectTargetDialog,
	},

	mixins: [projectTarget],

	props: {
		/** The task to move. */
		task: { type: Object, required: true },

		/** The task's subtasks; they move with it. */
		subtasks: { type: Array, default: () => [] },

		/** The tasks of the task's project, to name the linked tasks. */
		projectTasks: { type: Array, default: () => [] },
	},

	emits: ['close', 'moved'],

	data() {
		return {
			links: [],
			names: {},
		}
	},

	computed: {
		/**
		 * The people on the task, or a subtask, who are not members of the target.
		 *
		 * @return {Array<string>}
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
		 */
		cleared() {
			if (!this.targetProject) {
				return []
			}
			return [...new Set([this.task, ...this.subtasks].flatMap((one) => peopleToClear(one, this.targetProject)))]
		},

		/**
		 * The links the move removes, named by the task on the other end.
		 *
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
		 */
		linkLabels() {
			const titles = Object.fromEntries(this.projectTasks.map((other) => [idOf(other), other.title]))
			const id = idOf(this.task)
			return this.links.map((link) => {
				const blocks = link.blocker === id
				const other = blocks ? link.blocked : link.blocker
				const title = titles[other] || other
				return {
					id: link.id || `${link.blocker}-${link.blocked}`,
					label: blocks ? this.t('planninq', 'Blocks "{title}"', { title }) : this.t('planninq', 'Blocked by "{title}"', { title }),
				}
			})
		},
	},

	watch: {
		/**
		 * @param {Array<string>} uids The people the chosen target clears.
		 * @spec exclude Lifecycle glue: names the people the chosen target clears.
		 */
		async cleared(uids) {
			const missing = uids.filter((uid) => !(uid in this.names))
			if (missing.length) {
				this.names = { ...this.names, ...(await displayNames(missing)) }
			}
		},
	},

	/**
	 * Read the projects the task may move to and the links it has.
	 *
	 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
	 */
	async mounted() {
		const [projects, edges] = await Promise.all([
			useProjectsStore().fetchProjects(),
			useDependenciesStore().fetchEdges(),
		])
		this.targets = moveTargets(projects, { current: this.task.project, uid: getCurrentUser()?.uid, groupIds: currentGroupIds() })
		this.links = linksToRemove(this.task, edges)
		this.loading = false
	},

	methods: {
		/**
		 * Move the task and its subtasks; report subtasks that could not move.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
		 */
		async confirm() {
			const result = await this.runMove(
				(target) => useProjectsStore().moveTaskToProject(this.task, { target, subtasks: this.subtasks }),
				this.t('planninq', 'The task could not be moved. Please try again.'),
			)
			if (!result) {
				return
			}
			if (result.failed.length) {
				showError(this.t('planninq', 'The task moved, but some subtasks did not: {count}. Move them from their own pages.', { count: result.failed.length }))
			}
			this.$emit('moved', { task: result.task, target: this.targetProject, failed: result.failed })
		},
	},
}
</script>

<style scoped>
.task-move-dialog__warning {
	padding: 8px 12px;
	border-inline-start: 4px solid var(--color-warning);
	background-color: var(--color-background-hover);
	border-radius: var(--border-radius);
}

.task-move-dialog__warning ul {
	margin: 4px 0 0 20px;
	list-style: disc;
}
</style>
