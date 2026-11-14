<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="copy ? t('planninq', 'Copy column to project') : t('planninq', 'Move column to project')" @closing="$emit('close')">
		<template #default>
			<div class="column-move-dialog__body">
				<p>
					{{ copy
						? t('planninq', 'Copy "{title}" and its tasks to another project. The copies are open and have no assignees.', { title: column.title })
						: t('planninq', 'Move "{title}" and its tasks to another project. It becomes the last column there.', { title: column.title }) }}
				</p>
				<NcLoadingIcon v-if="loading" :size="20" />
				<p v-else-if="!targets.length" data-testid="column-move-no-targets">
					{{ t('planninq', 'You do not own another project.') }}
				</p>
				<NcSelect
					v-else
					v-model="target"
					:options="targetOptions"
					:inputLabel="t('planninq', 'Project')"
					label="label"
					data-testid="column-move-target" />

				<div v-if="submitError" class="column-move-dialog__error" role="alert">
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
				data-testid="column-move-confirm"
				@click="confirm">
				<template v-if="busy" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ copy ? t('planninq', 'Copy column') : t('planninq', 'Move column') }}
			</NcButton>
		</template>
	</NcDialog>
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
import { NcButton, NcDialog, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { currentGroupIds } from '../utils/projectRole.js'
import { columnTargets, idOf, lastColumnOrder } from '../utils/taskMove.js'

export default {
	name: 'ColumnMoveDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
	},

	props: {
		/** The column to move or copy. */
		column: {
			type: Object,
			required: true,
		},

		/** The board's tasks; the column's own move or are copied with it. */
		tasks: {
			type: Array,
			default: () => [],
		},

		/** Copy instead of move. */
		copy: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'done'],

	data() {
		return {
			loading: true,
			targets: [],
			target: null,
			busy: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * The target projects as select options.
		 *
		 * @return {Array<{id: string, label: string}>}
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
		 */
		targetOptions() {
			return this.targets.map((project) => ({ id: idOf(project), label: project.title || idOf(project) }))
		},

		/**
		 * The picked target project.
		 *
		 * @return {object|null}
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
		 */
		targetProject() {
			return this.targets.find((project) => idOf(project) === this.target?.id) || null
		},
	},

	/**
	 * Load the projects the column may go to.
	 *
	 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
	 */
	async mounted() {
		const projects = await useProjectsStore().fetchProjects()
		this.targets = columnTargets(projects, this.column.project, getCurrentUser()?.uid, currentGroupIds())
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
			if (!this.targetProject) {
				return
			}
			this.busy = true
			this.submitError = ''
			const store = useProjectsStore()
			const order = lastColumnOrder(await store.fetchColumns(idOf(this.targetProject)))
			const result = this.copy
				? await store.copyColumnToProject(this.column, this.targetProject, this.tasks, order)
				: await store.moveColumnToProject(this.column, this.targetProject, this.tasks, order)
			this.busy = false
			if (!result.ok) {
				this.submitError = this.copy
					? this.t('planninq', 'Could not copy the column. Please try again.')
					: this.t('planninq', 'Could not move the column. Please try again.')
				return
			}
			if (result.failed.length) {
				showError(this.t('planninq', 'Tasks that could not be moved or copied: {count}', { count: result.failed.length }))
			}
			this.$emit('done', { target: this.targetProject, copy: this.copy, failed: result.failed })
		},
	},
}
</script>

<style scoped>
.column-move-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(420px, 90vw);
}

.column-move-dialog__error {
	color: var(--color-error-text);
}
</style>
