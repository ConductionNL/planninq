<template>
	<NcDialog :name="t('planninq', 'Remove column')" @closing="$emit('close')">
		<template #default>
			<div class="column-remove-dialog__body">
				<p v-if="!removable" role="alert">
					{{ t('planninq', 'The last done column cannot be removed.') }}
				</p>
				<template v-else>
					<p><strong>{{ column.title }}</strong></p>
					<NcSelect
						v-if="cards.length"
						v-model="target"
						:options="targetOptions"
						:clearable="false"
						:inputLabel="t('planninq', 'Move its cards to')"
						label="label"
						data-testid="column-remove-target" />
				</template>
				<div v-if="submitError" class="column-remove-dialog__error" role="alert">
					{{ submitError }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="working" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				v-if="removable"
				variant="error"
				:disabled="working"
				data-testid="column-remove-confirm"
				@click="remove">
				<template v-if="working" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Remove column') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ColumnRemoveDialog.
 *
 * Removes a board column. A column that holds cards first moves them to a
 * column the owner picks, or to the backlog; only then is the column deleted,
 * so no card is lost. The last done column is never removable.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
 */
import { NcButton, NcDialog, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { buildMovePatch, canRemoveColumn } from '../utils/columnHelpers.js'

export default {
	name: 'ColumnRemoveDialog',

	components: { NcButton, NcDialog, NcLoadingIcon, NcSelect },

	props: {
		/** The column to remove. */
		column: {
			type: Object,
			required: true,
		},

		/** Every column of the project. */
		columns: {
			type: Array,
			required: true,
		},

		/** The cards in the column, in order. */
		cards: {
			type: Array,
			default: () => [],
		},

		/** The cards per column id, to put moved cards at the bottom of the target. */
		lanes: {
			type: Object,
			default: () => ({}),
		},
	},

	emits: ['close', 'removed'],

	data() {
		const targetOptions = [
			...this.columns
				.filter((other) => other.id !== this.column.id)
				.map((other) => ({ id: other.id, label: other.title, column: other })),
			{ id: '', label: this.t('planninq', 'Backlog'), column: null },
		]
		return {
			targetOptions,
			target: targetOptions[0],
			working: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
		 */
		removable() {
			return canRemoveColumn(this.columns, this.column)
		},
	},

	methods: {
		/**
		 * Move the cards, then delete the column.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
		 */
		async remove() {
			this.working = true
			this.submitError = ''
			const store = useProjectsStore()
			const lane = [...(this.lanes[this.target?.id] || [])]
			for (const card of this.cards) {
				const patch = this.target?.column
					? buildMovePatch(this.target.column, lane)
					: { column: null }
				const moved = await store.updateTask(card.id, patch)
				if (!moved) {
					this.fail()
					return
				}
				lane.push({ ...card, ...patch })
			}
			if (!(await store.deleteColumn(this.column.id))) {
				this.fail()
				return
			}
			this.working = false
			this.$emit('removed', this.column)
		},

		/**
		 * @spec exclude Error display glue.
		 */
		fail() {
			this.working = false
			this.submitError = this.t('planninq', 'Could not remove the column. Please try again.')
		},
	},
}
</script>

<style scoped>
.column-remove-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-bottom: 8px;
}

.column-remove-dialog__error {
	color: var(--color-error-text);
}
</style>
