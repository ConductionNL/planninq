<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="t('planninq', 'Dates of {title}', { title: task.title })" @closing="$emit('close')">
		<template #default>
			<div class="task-dates-dialog__body">
				<label class="task-dates-dialog__field">
					<span>{{ t('planninq', 'Start date') }}</span>
					<input v-model="startDate" type="date" data-testid="task-dates-start">
				</label>
				<label class="task-dates-dialog__field">
					<span>{{ t('planninq', 'Due date') }}</span>
					<input v-model="dueDate" type="date" data-testid="task-dates-due">
				</label>
				<p v-if="invalid" class="task-dates-dialog__error" role="alert">
					{{ t('planninq', 'The due date cannot be before the start date.') }}
				</p>
			</div>
		</template>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary"
				:disabled="invalid"
				data-testid="task-dates-save"
				@click="save">
				{{ t('planninq', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcDialog } from '@nextcloud/vue'

/**
 * TaskDatesDialog: the keyboard and touch path to a task's dates on the
 * timeline. It edits nothing itself: it emits the chosen dates and the
 * timeline writes them.
 *
 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
 */
export default {
	name: 'TaskDatesDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		/** The task, with title, startDate and dueDate. */
		task: {
			type: Object,
			required: true,
		},
	},

	emits: ['close', 'save'],

	data() {
		return {
			startDate: String(this.task.startDate || '').slice(0, 10),
			dueDate: String(this.task.dueDate || '').slice(0, 10),
		}
	},

	computed: {
		/**
		 * @return {boolean} Whether the due date is before the start date
		 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
		 */
		invalid() {
			return !this.startDate || !this.dueDate || this.dueDate < this.startDate
		},
	},

	methods: {
		t,

		/**
		 * @spec openspec/changes/planning-timeline-editing/tasks.md#task-2.2
		 */
		save() {
			this.$emit('save', { startDate: this.startDate, dueDate: this.dueDate })
		},
	},
}
</script>

<style scoped>
.task-dates-dialog__body {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
}

.task-dates-dialog__field {
	display: flex;
	flex-direction: column;
}

.task-dates-dialog__error {
	color: var(--color-error-text);
}
</style>
