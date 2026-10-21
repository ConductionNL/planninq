<template>
	<div class="column-settings-list">
		<NcLoadingIcon v-if="loading" :size="24" />
		<ol v-else class="column-settings-list__items" data-testid="column-settings-list">
			<li v-for="(column, index) in columns" :key="column.id" class="column-settings-list__item">
				<span class="column-settings-list__title">{{ column.title }}</span>
				<span v-if="column.type === 'done'" class="column-settings-list__done">
					{{ t('planninq', 'Done column') }}
				</span>
				<ColumnActions
					v-if="canManage"
					:first="index === 0"
					:last="index === columns.length - 1"
					@edit="editing = column"
					@move="(direction) => move(column, direction)"
					@remove="removing = column" />
			</li>
		</ol>
		<NcButton v-if="canManage && !loading" variant="secondary" @click="editing = {}">
			{{ t('planninq', 'Add column') }}
		</NcButton>

		<ColumnEditDialog
			v-if="editing"
			:column="editing.id ? editing : null"
			:projectId="projectId"
			:nextOrder="columns.length"
			@close="editing = null"
			@saved="changed" />
		<ColumnRemoveDialog
			v-if="removing"
			:column="removing"
			:columns="columns"
			:cards="lanes[removing.id] || []"
			:lanes="lanes"
			@close="removing = null"
			@removed="changed" />
	</div>
</template>

<script>
/**
 * ColumnSettingsList: the Columns tab of the project settings sidebar. The
 * same column actions as the lane header menu, as a plain ordered list, so a
 * keyboard or screen reader user manages columns without the board.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
 */
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import ColumnEditDialog from '../dialogs/ColumnEditDialog.vue'
import ColumnRemoveDialog from '../dialogs/ColumnRemoveDialog.vue'
import ColumnActions from './ColumnActions.vue'
import { useProjectsStore } from '../store/projects.js'
import { groupTasksByColumn, sortColumns, swapColumnPatches } from '../utils/columnHelpers.js'

export default {
	name: 'ColumnSettingsList',

	components: { ColumnActions, ColumnEditDialog, ColumnRemoveDialog, NcButton, NcLoadingIcon },

	props: {
		/** The project whose columns are listed. */
		projectId: {
			type: String,
			required: true,
		},

		/** Whether the current user may manage the columns. */
		canManage: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['changed'],

	data() {
		return {
			columns: [],
			lanes: {},
			loading: true,
			editing: null,
			removing: null,
		}
	},

	/**
	 * @spec exclude Lifecycle glue: loads the list on mount.
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		/**
		 * Load the columns and their cards.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
		 */
		async load() {
			const store = useProjectsStore()
			this.loading = true
			this.columns = sortColumns(await store.fetchColumns(this.projectId))
			this.lanes = groupTasksByColumn(await store.fetchTasks(this.projectId), this.columns)
			this.loading = false
		},

		/**
		 * Move a column one place left or right.
		 *
		 * @param {object} column    The column.
		 * @param {number} direction -1 or +1.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
		 */
		async move(column, direction) {
			const store = useProjectsStore()
			for (const patch of swapColumnPatches(this.columns, column, direction)) {
				if (!(await store.saveColumn(patch))) {
					showError(this.t('planninq', 'Could not save the column. Please try again.'))
					break
				}
			}
			await this.changed()
		},

		/**
		 * @spec exclude Event glue: reload and tell the board.
		 */
		async changed() {
			this.editing = null
			this.removing = null
			await this.load()
			this.$emit('changed')
		},
	},
}
</script>

<style scoped>
.column-settings-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 8px 0;
}

.column-settings-list__items {
	margin: 0;
	padding: 0;
	list-style: none;
}

.column-settings-list__item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
	border-bottom: 1px solid var(--color-border);
}

.column-settings-list__title {
	flex: 1;
}

.column-settings-list__done {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
