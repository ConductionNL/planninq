<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div
		class="backlog-bulk-bar"
		role="region"
		:aria-label="t('planninq', 'Actions')"
		data-testid="backlog-bulk-bar">
		<span class="backlog-bulk-bar__count" aria-live="polite">
			{{ t('planninq', 'Selected: {count}', { count }) }}
		</span>
		<NcSelect
			v-model="action"
			class="backlog-bulk-bar__field"
			:options="actionOptions"
			:clearable="false"
			:inputLabel="t('planninq', 'Actions')"
			label="label"
			:reduce="(option) => option.id"
			data-testid="bulk-action"
			@update:modelValue="value = null" />
		<NcSelect
			v-model="value"
			class="backlog-bulk-bar__field"
			:options="valueOptions"
			:clearable="false"
			:inputLabel="valueLabel"
			label="label"
			:reduce="(option) => option.id"
			data-testid="bulk-value" />
		<NcButton
			variant="primary"
			:disabled="busy || value === null"
			data-testid="bulk-apply"
			@click="$emit('apply', { action, value })">
			{{ busy ? t('planninq', 'Saving…') : t('planninq', 'Apply') }}
		</NcButton>
		<NcButton variant="tertiary"
			:disabled="busy"
			data-testid="bulk-clear"
			@click="$emit('clear')">
			{{ t('planninq', 'Clear selection') }}
		</NcButton>
	</div>
</template>

<script>
/**
 * The backlog's bulk bar (tasks-search-and-bulk, design D2 and D3): change the
 * status, the assignee, the priority, or add or remove one label on every
 * selected task. The bar only says what to change; the backlog applies it and
 * reports the result.
 *
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.3
 */
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcSelect } from '@nextcloud/vue'
import { BULK_STATUSES } from '../utils/bulkHelpers.js'

export default {
	name: 'BacklogBulkBar',
	components: { NcButton, NcSelect },
	props: {
		/** How many tasks are selected. */
		count: {
			type: Number,
			required: true,
		},

		/** The project's people, `{ id, label }`. */
		people: {
			type: Array,
			default: () => [],
		},

		/** Every label, `{ id, title }`. */
		labels: {
			type: Array,
			default: () => [],
		},

		/** Whether a bulk change is running. */
		busy: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['apply', 'clear'],
	data() {
		return {
			action: 'status',
			value: null,
		}
	},

	computed: {
		/**
		 * @return {Array<{id: string, label: string}>} The bulk actions.
		 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.3
		 */
		actionOptions() {
			return [
				{ id: 'status', label: t('planninq', 'Status') },
				{ id: 'assign', label: t('planninq', 'Assign to') },
				{ id: 'priority', label: t('planninq', 'Priority') },
				{ id: 'labelAdd', label: t('planninq', 'Add label') },
				{ id: 'labelRemove', label: t('planninq', 'Remove label') },
			]
		},

		/**
		 * @return {Array<{id: string, label: string}>} The values the chosen action takes.
		 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.3
		 */
		valueOptions() {
			if (this.action === 'status') {
				const names = {
					open: t('planninq', 'Open'),
					in_progress: t('planninq', 'In progress'),
					blocked: t('planninq', 'Blocked'),
					done: t('planninq', 'Done'),
					cancelled: t('planninq', 'Cancelled'),
				}
				return BULK_STATUSES.map((id) => ({ id, label: names[id] }))
			}
			if (this.action === 'assign') {
				return [{ id: '', label: t('planninq', 'Unassigned') }, ...this.people]
			}
			if (this.action === 'priority') {
				return [
					{ id: 'urgent', label: t('planninq', 'Urgent') },
					{ id: 'high', label: t('planninq', 'High') },
					{ id: 'normal', label: t('planninq', 'Normal') },
					{ id: 'low', label: t('planninq', 'Low') },
				]
			}
			return this.labels.map((label) => ({ id: label.id, label: label.title }))
		},

		/**
		 * @return {string} The value field's label.
		 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.3
		 */
		valueLabel() {
			return (this.actionOptions.find((option) => option.id === this.action) || {}).label || ''
		},
	},

	methods: { t },
}
</script>

<style scoped>
.backlog-bulk-bar {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 2);
	margin-block: calc(var(--default-grid-baseline) * 2);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.backlog-bulk-bar__count {
	font-weight: bold;
	align-self: center;
}

.backlog-bulk-bar__field {
	min-width: 180px;
}
</style>
