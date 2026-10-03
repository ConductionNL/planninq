<template>
	<NcActions
		:aria-label="t('planninq', 'Board view options')"
		:forceMenu="true"
		:forceName="true"
		:menuName="t('planninq', 'View')"
		data-testid="board-view-menu">
		<template #icon>
			<EyeOutline :size="20" />
		</template>
		<NcActionCaption :name="t('planninq', 'Colour cards')" />
		<NcActionRadio
			v-for="option in colourOptions"
			:key="'colour-' + option.value"
			name="planninq-board-colour"
			:value="option.value"
			:modelValue="colour"
			:data-testid="'board-colour-' + option.value"
			@update:modelValue="$emit('update:colour', option.value)">
			{{ option.label }}
		</NcActionRadio>
		<NcActionSeparator />
		<NcActionCaption :name="t('planninq', 'Swimlanes')" />
		<NcActionRadio
			v-for="option in groupOptions"
			:key="'group-' + option.value"
			name="planninq-board-group"
			:value="option.value"
			:modelValue="group"
			:data-testid="'board-group-' + option.value"
			@update:modelValue="$emit('update:group', option.value)">
			{{ option.label }}
		</NcActionRadio>
	</NcActions>
</template>

<script>
/**
 * The board's View menu: colour cards by label or priority, and split the
 * board into swimlanes by assignee, priority or epic (boards-card-display).
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
 */
import { NcActionCaption, NcActionRadio, NcActions, NcActionSeparator } from '@nextcloud/vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'

export default {
	name: 'BoardViewMenu',

	components: {
		EyeOutline,
		NcActionCaption,
		NcActionRadio,
		NcActions,
		NcActionSeparator,
	},

	props: {
		/** The colour mode: none, label or priority. */
		colour: {
			type: String,
			default: 'none',
		},

		/** The swimlane field: none, assignee, priority or epic. */
		group: {
			type: String,
			default: 'none',
		},
	},

	emits: ['update:colour', 'update:group'],

	computed: {
		/**
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
		 */
		colourOptions() {
			return [
				{ value: 'none', label: this.t('planninq', 'No colour') },
				{ value: 'label', label: this.t('planninq', 'By label') },
				{ value: 'priority', label: this.t('planninq', 'By priority') },
			]
		},

		/**
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
		 */
		groupOptions() {
			return [
				{ value: 'none', label: this.t('planninq', 'No swimlanes') },
				{ value: 'assignee', label: this.t('planninq', 'By assignee') },
				{ value: 'priority', label: this.t('planninq', 'By priority') },
				{ value: 'epic', label: this.t('planninq', 'By epic') },
			]
		},
	},
}
</script>
