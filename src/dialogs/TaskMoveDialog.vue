<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="t('planninq', 'Move to project')" @closing="$emit('close')">
		<template #default>
			<div class="task-move-dialog__body">
				<p>
					{{ t('planninq', 'Move "{title}" to another project. It lands in that project\'s backlog.', { title: task.title }) }}
				</p>
				<NcLoadingIcon v-if="loading" :size="20" />
				<p v-else-if="!targets.length" data-testid="task-move-no-targets">
					{{ t('planninq', 'You are not a member of another project.') }}
				</p>
				<NcSelect
					v-else
					v-model="target"
					:options="targetOptions"
					:inputLabel="t('planninq', 'Project')"
					label="label"
					data-testid="task-move-target" />

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

				<div v-if="submitError" class="task-move-dialog__error" role="alert">
					{{ submitError }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="busy" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="busy || !target"
				data-testid="task-move-confirm"
				@click="confirm">
				<template v-if="busy" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Move task') }}
			</NcButton>
		</template>
	</NcDialog>
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
import { NcButton, NcDialog, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import { useDependenciesStore } from '../store/dependencies.js'
import { useProjectsStore } from '../store/projects.js'
import { currentGroupIds } from '../utils/projectRole.js'
import { idOf, linksToRemove, moveTargets, peopleToClear } from '../utils/taskMove.js'
import { displayNames } from '../utils/userNames.js'

export default {
	name: 'TaskMoveDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
	},

	props: {
		/** The task to move. */
		task: {
			type: Object,
			required: true,
		},

		/** The task's subtasks; they move with it. */
		subtasks: {
			type: Array,
			default: () => [],
		},

		/** The tasks of the task's project, to name the linked tasks. */
		projectTasks: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'moved'],

	data() {
		return {
			loading: true,
			targets: [],
			target: null,
			links: [],
			names: {},
			busy: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: the target projects as select options.
		 */
		targetOptions() {
			return this.targets.map((project) => ({ id: idOf(project), label: project.title || idOf(project) }))
		},

		/**
		 * @spec exclude Trivial getter: the chosen target project.
		 */
		targetProject() {
			return this.targets.find((project) => idOf(project) === this.target?.id) || null
		},

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
	 * @spec exclude Lifecycle glue: reads the target projects and the task's links.
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		/**
		 * Read the projects the task may move to and the links it has.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
		 */
		async load() {
			this.loading = true
			const [projects, edges] = await Promise.all([
				useProjectsStore().fetchProjects(),
				useDependenciesStore().fetchEdges(),
			])
			this.targets = moveTargets(projects, this.task.project, getCurrentUser()?.uid, currentGroupIds())
			this.links = linksToRemove(this.task, edges)
			this.loading = false
		},

		/**
		 * Move the task and its subtasks; report subtasks that could not move.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
		 */
		async confirm() {
			if (!this.targetProject) {
				return
			}
			this.busy = true
			this.submitError = ''
			const result = await useProjectsStore().moveTaskToProject(this.task, this.targetProject, this.subtasks)
			this.busy = false
			if (!result.ok) {
				this.submitError = this.t('planninq', 'The task could not be moved. Please try again.')
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
.task-move-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(420px, 90vw);
}

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

.task-move-dialog__error {
	color: var(--color-error-text);
}
</style>
