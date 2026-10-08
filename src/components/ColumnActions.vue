<template>
	<NcActions :aria-label="t('planninq', 'Column actions')" :forceMenu="true" data-testid="column-actions">
		<NcActionButton :closeAfterClick="true" @click="$emit('edit')">
			<template #icon>
				<PencilIcon :size="20" />
			</template>
			{{ t('planninq', 'Edit column') }}
		</NcActionButton>
		<NcActionButton
			v-if="rules"
			:closeAfterClick="true"
			data-testid="column-rules"
			@click="$emit('rules')">
			<template #icon>
				<LightningBoltIcon :size="20" />
			</template>
			{{ t('planninq', 'Rules') }}
		</NcActionButton>
		<NcActionButton v-if="!first" :closeAfterClick="true" @click="$emit('move', -1)">
			<template #icon>
				<ArrowLeftIcon :size="20" />
			</template>
			{{ t('planninq', 'Move left') }}
		</NcActionButton>
		<NcActionButton v-if="!last" :closeAfterClick="true" @click="$emit('move', 1)">
			<template #icon>
				<ArrowRightIcon :size="20" />
			</template>
			{{ t('planninq', 'Move right') }}
		</NcActionButton>
		<NcActionButton
			v-if="transfer"
			:closeAfterClick="true"
			data-testid="column-move-project"
			@click="$emit('moveProject')">
			<template #icon>
				<FolderMoveIcon :size="20" />
			</template>
			{{ t('planninq', 'Move column to project') }}
		</NcActionButton>
		<NcActionButton
			v-if="transfer"
			:closeAfterClick="true"
			data-testid="column-copy-project"
			@click="$emit('copyProject')">
			<template #icon>
				<ContentCopyIcon :size="20" />
			</template>
			{{ t('planninq', 'Copy column to project') }}
		</NcActionButton>
		<NcActionButton :closeAfterClick="true" @click="$emit('remove')">
			<template #icon>
				<DeleteIcon :size="20" />
			</template>
			{{ t('planninq', 'Remove column') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
/**
 * ColumnActions: the owner's menu for one board column, used in the lane
 * header and in the settings sidebar's Columns tab.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
 */
import { NcActionButton, NcActions } from '@nextcloud/vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FolderMoveIcon from 'vue-material-design-icons/FolderMove.vue'
import LightningBoltIcon from 'vue-material-design-icons/LightningBolt.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'

export default {
	name: 'ColumnActions',

	components: { NcActionButton, NcActions, ArrowLeftIcon, ArrowRightIcon, ContentCopyIcon, DeleteIcon, FolderMoveIcon, LightningBoltIcon, PencilIcon },

	props: {
		/** Whether the column is the leftmost one. */
		first: {
			type: Boolean,
			default: false,
		},

		/** Whether the column is the rightmost one. */
		last: {
			type: Boolean,
			default: false,
		},

		/** Whether to offer the column's rules (the board header; boards-column-automation). */
		rules: {
			type: Boolean,
			default: false,
		},

		/** Whether to offer moving and copying the column to another project (the board header). */
		transfer: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['edit', 'move', 'remove', 'rules', 'moveProject', 'copyProject'],
}
</script>
