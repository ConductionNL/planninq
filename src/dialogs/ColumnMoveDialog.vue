<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<ProjectTargetDialog
		v-model="target"
		:name="copy ? t('planninq', 'Copy column to project') : t('planninq', 'Move column to project')"
		:loading="loading"
		:options="targetOptions"
		:emptyText="t('planninq', 'You do not own another project.')"
		:busy="busy"
		:error="submitError"
		:confirmLabel="copy ? t('planninq', 'Copy column') : t('planninq', 'Move column')"
		testid="column-move"
		@close="$emit('close')"
		@confirm="confirm">
		<p>
			{{ copy
				? t('planninq', 'Copy "{title}" and its tasks to another project. The copies are open and have no assignees.', { title: column.title })
				: t('planninq', 'Move "{title}" and its tasks to another project. It becomes the last column there.', { title: column.title }) }}
		</p>
	</ProjectTargetDialog>
</template>

<script>
/**
 * ColumnMoveDialog.
 *
 * Moves a board column with its tasks to another project the person owns, or
 * copies it with open, unassigned copies of its tasks. Either way the column
 * is appended as the last column of the target board.
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import ProjectTargetDialog from '../components/ProjectTargetDialog.vue'
import projectTarget from '../mixins/projectTarget.js'
import { useProjectsStore } from '../store/projects.js'
import { currentGroupIds } from '../utils/projectRole.js'
import { columnTargets, idOf, lastColumnOrder } from '../utils/taskMove.js'

export default {
	name: 'ColumnMoveDialog',

	components: {
		ProjectTargetDialog,
	},

	mixins: [projectTarget],

	props: {
		/** The column to move or copy. */
		column: { type: Object, required: true },

		/** The board's tasks; the column's own move or are copied with it. */
		tasks: { type: Array, default: () => [] },

		/** Copy instead of move. */
		copy: { type: Boolean, default: false },
	},

	emits: ['close', 'done'],

	/**
	 * Load the projects the column may go to.
	 *
	 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
	 */
	async mounted() {
		const projects = await useProjectsStore().fetchProjects()
		this.targets = columnTargets(projects, { current: this.column.project, uid: getCurrentUser()?.uid, groupIds: currentGroupIds() })
		this.loading = false
	},

	methods: {
		/**
		 * Move or copy the column to the picked project, appended last on its board.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
		 */
		async confirm() {
			const result = await this.runMove((target) => this.writeTo(target), this.failureText())
			if (!result) {
				return
			}
			if (result.failed.length) {
				showError(this.t('planninq', 'Tasks that could not be moved or copied: {count}', { count: result.failed.length }))
			}
			this.$emit('done', { target: this.targetProject, copy: this.copy, failed: result.failed })
		},

		/**
		 * Move or copy the column to a project, appended last on its board.
		 *
		 * @param {object} target The target project.
		 * @return {Promise<{ok: boolean, failed: Array<string>}>}
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
		 */
		async writeTo(target) {
			const store = useProjectsStore()
			const order = lastColumnOrder(await store.fetchColumns(idOf(target)))
			const move = { target, tasks: this.tasks, order }
			return this.copy ? store.copyColumnToProject(this.column, move) : store.moveColumnToProject(this.column, move)
		},

		/**
		 * The message for a move or copy the server refused.
		 *
		 * @return {string}
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
		 */
		failureText() {
			return this.copy
				? this.t('planninq', 'Could not copy the column. Please try again.')
				: this.t('planninq', 'Could not move the column. Please try again.')
		},
	},
}
</script>
