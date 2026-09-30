<template>
	<div class="board-filter-bar" data-testid="board-filter-bar">
		<div
			v-if="labels.length"
			class="board-filter-bar__labels"
			role="group"
			:aria-label="t('planninq', 'Filter tasks by label')">
			<NcChip
				v-for="chip in labelChips"
				:key="chip.key"
				:text="chip.title"
				:variant="chip.pressed ? 'primary' : 'secondary'"
				:noClose="true"
				class="board-filter-bar__chip"
				data-testid="label-filter-chip"
				role="button"
				tabindex="0"
				:aria-pressed="chip.pressed"
				@click="toggleLabel(chip.value)"
				@keydown.enter="toggleLabel(chip.value)"
				@keydown.space.prevent="toggleLabel(chip.value)">
				<template v-if="chip.color" #icon>
					<span
						class="board-filter-bar__swatch"
						:style="{ backgroundColor: chip.color }"
						aria-hidden="true" />
				</template>
			</NcChip>
			<NcButton
				variant="tertiary"
				:pressed="filter.label.op === 'isNot'"
				data-testid="filter-label-not"
				@update:pressed="setOp('label', $event)">
				{{ t('planninq', 'is not') }}
			</NcButton>
		</div>

		<div class="board-filter-bar__selects">
			<div
				v-for="dimension in selectDimensions"
				:key="dimension.id"
				class="board-filter-bar__dimension">
				<NcSelect
					:modelValue="dimension.options.filter((option) => filter[dimension.id].values.includes(option.id))"
					:options="dimension.options"
					:multiple="true"
					:inputLabel="dimension.label"
					label="label"
					:data-testid="'filter-' + dimension.id"
					@update:modelValue="setValues(dimension.id, $event)" />
				<NcButton
					variant="tertiary"
					:pressed="filter[dimension.id].op === 'isNot'"
					:aria-label="t('planninq', '{dimension} is not', { dimension: dimension.label })"
					:data-testid="'filter-' + dimension.id + '-not'"
					@update:pressed="setOp(dimension.id, $event)">
					{{ t('planninq', 'is not') }}
				</NcButton>
			</div>

			<NcActions
				:aria-label="t('planninq', 'Saved filters')"
				:menuName="t('planninq', 'Saved filters')"
				data-testid="saved-filters">
				<template #icon>
					<FilterVariantIcon :size="20" />
				</template>
				<NcActionButton
					v-for="saved in savedFilters"
					:key="saved.id"
					:closeAfterClick="true"
					data-testid="saved-filter"
					@click="$emit('apply', saved)">
					<template #icon>
						<AccountGroupIcon v-if="saved.shared" :size="20" />
						<AccountIcon v-else :size="20" />
					</template>
					{{ saved.name }}
				</NcActionButton>
				<NcActionCaption v-if="!savedFilters.length" :name="t('planninq', 'No saved filters yet')" />
				<NcActionSeparator />
				<NcActionButton
					:disabled="!active"
					:closeAfterClick="true"
					data-testid="save-filter"
					@click="$emit('save')">
					<template #icon>
						<ContentSaveIcon :size="20" />
					</template>
					{{ t('planninq', 'Save filter') }}
				</NcActionButton>
				<template v-for="saved in manageable" :key="saved.id">
					<NcActionButton
						:closeAfterClick="true"
						data-testid="rename-saved-filter"
						@click="$emit('rename', saved)">
						<template #icon>
							<PencilIcon :size="20" />
						</template>
						{{ t('planninq', 'Rename "{name}"', { name: saved.name }) }}
					</NcActionButton>
					<NcActionButton
						:closeAfterClick="true"
						data-testid="delete-saved-filter"
						@click="$emit('delete', saved)">
						<template #icon>
							<DeleteIcon :size="20" />
						</template>
						{{ t('planninq', 'Delete "{name}"', { name: saved.name }) }}
					</NcActionButton>
				</template>
			</NcActions>

			<NcButton
				v-if="active"
				variant="tertiary"
				data-testid="filter-clear"
				@click="$emit('update:filter', emptyFilter())">
				{{ t('planninq', 'Clear filters') }}
			</NcButton>
		</div>

		<p class="board-filter-bar__count" aria-live="polite" data-testid="filter-count">
			<template v-if="active">
				{{ t('planninq', 'Showing {shown} of {total} tasks', { shown, total }) }}
			</template>
			<template v-if="notice">
				{{ notice }}
			</template>
		</p>
	</div>
</template>

<script>
/**
 * BoardFilterBar: narrow the board by assignee, label, priority and due date,
 * each "is" or "is not", and save or apply a named filter.
 *
 * The label chips of the earlier label filter are the label dimension: a
 * chip toggles its label in the filter, "All labels" clears it. The parent
 * keeps the filter in the page address; this bar only emits the new value.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-2.1
 */
import { NcActionButton, NcActionCaption, NcActions, NcActionSeparator, NcButton, NcChip, NcSelect } from '@nextcloud/vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FilterVariantIcon from 'vue-material-design-icons/FilterVariant.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import { activeDimensions, emptyFilter, ME, UNASSIGNED } from '../utils/boardFilter.js'
import { labelId, sortLabelsByTitle } from '../utils/labelHelpers.js'

export default {
	name: 'BoardFilterBar',

	components: {
		NcActionButton,
		NcActionCaption,
		NcActions,
		NcActionSeparator,
		NcButton,
		NcChip,
		NcSelect,
		AccountIcon,
		AccountGroupIcon,
		ContentSaveIcon,
		DeleteIcon,
		FilterVariantIcon,
		PencilIcon,
	},

	props: {
		/** The active filter (boardFilter.js shape). */
		filter: {
			type: Object,
			required: true,
		},

		/** Every label. */
		labels: {
			type: Array,
			default: () => [],
		},

		/** The project's people as `{ id, label }`. */
		people: {
			type: Array,
			default: () => [],
		},

		/** The saved filters the user may read. */
		savedFilters: {
			type: Array,
			default: () => [],
		},

		/** The saved filters the user may rename or delete. */
		manageable: {
			type: Array,
			default: () => [],
		},

		/** How many cards the filter shows. */
		shown: {
			type: Number,
			default: 0,
		},

		/** How many cards the board holds. */
		total: {
			type: Number,
			default: 0,
		},

		/** A line to announce, such as a saved filter that changed. */
		notice: {
			type: String,
			default: '',
		},
	},

	emits: ['update:filter', 'save', 'apply', 'rename', 'delete'],

	computed: {
		/**
		 * @spec exclude Display flag: whether any dimension narrows the board.
		 */
		active() {
			return activeDimensions(this.filter) > 0
		},

		/**
		 * The label chips: "All labels" first, then every label in title order.
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-2.1
		 */
		labelChips() {
			const chosen = this.filter.label.values
			return [
				{ key: 'all', value: null, title: this.t('planninq', 'All labels'), color: '', pressed: chosen.length === 0 },
				...sortLabelsByTitle(this.labels).map((label) => ({
					key: labelId(label),
					value: labelId(label),
					title: label.title,
					color: label.color,
					pressed: chosen.includes(labelId(label)),
				})),
			]
		},

		/**
		 * The select dimensions: assignee, priority and due date.
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-2.1
		 */
		selectDimensions() {
			return [
				{
					id: 'assignee',
					label: this.t('planninq', 'Assignee'),
					options: [
						{ id: ME, label: this.t('planninq', 'Me') },
						{ id: UNASSIGNED, label: this.t('planninq', 'Unassigned') },
						...this.people,
					],
				},
				{
					id: 'priority',
					label: this.t('planninq', 'Priority'),
					options: [
						{ id: 'urgent', label: this.t('planninq', 'Urgent') },
						{ id: 'high', label: this.t('planninq', 'High') },
						{ id: 'normal', label: this.t('planninq', 'Normal') },
						{ id: 'low', label: this.t('planninq', 'Low') },
					],
				},
				{
					id: 'due',
					label: this.t('planninq', 'Due date'),
					options: [
						{ id: 'overdue', label: this.t('planninq', 'Overdue') },
						{ id: 'thisWeek', label: this.t('planninq', 'Due this week') },
						{ id: 'none', label: this.t('planninq', 'No date') },
					],
				},
			]
		},
	},

	methods: {
		emptyFilter,

		/**
		 * Toggle a label in the filter; "All labels" clears the dimension.
		 *
		 * @param {string|null} id The label id, null for all.
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-2.1
		 */
		toggleLabel(id) {
			const values = this.filter.label.values
			const next = id === null ? [] : (values.includes(id) ? values.filter((value) => value !== id) : [...values, id])
			this.$emit('update:filter', { ...this.filter, label: { ...this.filter.label, values: next } })
		},

		/**
		 * @param {string}        dimension The dimension.
		 * @param {Array<object>} options   The chosen options.
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-2.1
		 */
		setValues(dimension, options) {
			this.$emit('update:filter', { ...this.filter, [dimension]: { ...this.filter[dimension], values: (options || []).map((option) => option.id) } })
		},

		/**
		 * @param {string}  dimension The dimension.
		 * @param {boolean} negated   Whether it reads "is not".
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-2.1
		 */
		setOp(dimension, negated) {
			this.$emit('update:filter', { ...this.filter, [dimension]: { ...this.filter[dimension], op: negated ? 'isNot' : 'is' } })
		},
	},
}
</script>

<style scoped>
.board-filter-bar {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 16px;
}

.board-filter-bar__labels,
.board-filter-bar__selects,
.board-filter-bar__dimension {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 6px;
}

.board-filter-bar__selects {
	gap: 12px;
}

.board-filter-bar__chip {
	cursor: pointer;
}

/* Perceivable keyboard focus (WCAG 2.4.7): the chip is role="button". */
.board-filter-bar__chip:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

/* The label's own colour is data, so it arrives inline on the swatch; the chip
   text carries the name, so colour is never the only signal (WCAG 1.4.1). */
.board-filter-bar__swatch {
	display: block;
	width: 12px;
	height: 12px;
	margin-inline-start: 4px;
	border: 1px solid var(--color-border);
	border-radius: 50%;
	background: var(--color-background-dark);
}

.board-filter-bar__count {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
