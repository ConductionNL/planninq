<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="t('planninq', 'Move the tasks that wait for {title}?', { title: task.title })" @closing="$emit('cancel')">
		<template #default>
			<p>{{ t('planninq', 'These tasks wait for it and would start after its new due date:') }}</p>
			<p v-if="moves.length > limit" data-testid="reschedule-count">
				{{ t('planninq', '{count} tasks would move; the first {limit} are listed.', { count: moves.length, limit }) }}
			</p>
			<table class="reschedule-dialog__table" data-testid="reschedule-preview">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Task') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Now') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'After the move') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="move in shown" :key="move.id" data-testid="reschedule-row">
						<th scope="row">
							{{ move.title }}
						</th>
						<td>{{ range(move.from) }}</td>
						<td>{{ range(move.to) }}</td>
					</tr>
				</tbody>
			</table>
		</template>

		<template #actions>
			<NcButton data-testid="reschedule-cancel" @click="$emit('cancel')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton data-testid="reschedule-only" @click="$emit('only')">
				{{ t('planninq', 'Only this task') }}
			</NcButton>
			<NcButton variant="primary" data-testid="reschedule-all" @click="$emit('all')">
				{{ t('planninq', 'Move all') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcDialog } from '@nextcloud/vue'

/**
 * RescheduleDialog: the preview of the tasks a slip would push, with Move
 * all, Only this task and Cancel. It writes nothing; the timeline does.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.3
 */
export default {
	name: 'RescheduleDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		/** The task that was moved. */
		task: {
			type: Object,
			required: true,
		},

		/** The tasks it would push, from cascade(). */
		moves: {
			type: Array,
			required: true,
		},
	},

	emits: ['all', 'only', 'cancel'],

	data() {
		return { limit: 50 }
	},

	computed: {
		/**
		 * @return {Array<object>} The first 50 moves
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.3
		 */
		shown() {
			return this.moves.slice(0, this.limit)
		},
	},

	methods: {
		t,

		/**
		 * @param {{startDate: string, dueDate: string}} dates The dates
		 * @return {string} The range in words
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.3
		 */
		range(dates) {
			const word = (date) => new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, { day: 'numeric', month: 'long', timeZone: 'UTC' })
			return t('planninq', '{start} to {end}', { start: word(dates.startDate), end: word(dates.dueDate) })
		},
	},
}
</script>

<style scoped>
.reschedule-dialog__table {
	width: 100%;
	border-collapse: collapse;
}

.reschedule-dialog__table th,
.reschedule-dialog__table td {
	text-align: start;
	padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2);
	border-bottom: 1px solid var(--color-border);
}
</style>
