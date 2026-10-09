<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="heading" @closing="$emit('close')">
		<template #default>
			<div class="task-move-dialog__body">
				<p v-if="!options.length" data-testid="task-move-none">
					{{ t('planninq', 'You are not a member of any other project.') }}
				</p>
				<template v-else>
					<NcSelect
						v-model="chosen"
						:options="options"
						:inputLabel="t('planninq', 'Target project')"
						:clearable="false"
						label="label"
						data-testid="task-move-target" />
					<p v-if="links.length" data-testid="task-move-links">
						{{ t('planninq', 'These links to other tasks are removed:') }}
					</p>
					<ul v-if="links.length" class="task-move-dialog__list">
						<li v-for="link in links" :key="link.edgeId">
							{{ link.label }}
						</li>
					</ul>
					<p v-if="cleared.length" data-testid="task-move-people">
						{{ t('planninq', 'These people are not on the target project and are cleared: {names}', { names: cleared.join(', ') }) }}
					</p>
				</template>
			</div>
		</template>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="!chosen || busy"
				data-testid="task-move-confirm"
				@click="$emit('confirm', chosen)">
				{{ confirmLabel }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * TaskMoveDialog: pick another project to move a task, or move or copy a
 * column, to. It names the dependency links that will go and the people who
 * will be cleared, and emits `confirm` with the chosen project option.
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
 */
import { NcButton, NcDialog, NcSelect } from '@nextcloud/vue'

export default {
	name: 'TaskMoveDialog',

	components: { NcButton, NcDialog, NcSelect },

	props: {
		/** Dialog heading. */
		heading: {
			type: String,
			default: '',
		},

		/** Label of the confirm button. */
		confirmLabel: {
			type: String,
			default: '',
		},

		/** Projects to pick from, as `{ id, label }`. */
		options: {
			type: Array,
			default: () => [],
		},

		/** Dependency links the move removes, as `{ edgeId, label }`. */
		links: {
			type: Array,
			default: () => [],
		},

		/** The picked project's id, to look up who is cleared. */
		clearedFor: {
			type: Function,
			default: () => [],
		},

		/** Whether the move is being written. */
		busy: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'confirm'],

	data() {
		return { chosen: this.options[0] ?? null }
	},

	computed: {
		/**
		 * @spec exclude Display helper, the people the move clears.
		 */
		cleared() {
			return this.chosen ? this.clearedFor(this.chosen) : []
		},
	},
}
</script>

<style scoped>
.task-move-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.task-move-dialog__list {
	margin: 0;
	padding-left: 20px;
}
</style>
