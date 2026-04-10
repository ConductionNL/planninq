<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 TaskStatusBadge — displays a status label with color-coded styling.

 @spec openspec/changes/dashboard-my-work/tasks.md#task-7
-->
<template>
	<span
		class="task-status-badge"
		:class="`task-status-badge--${status}`"
		:title="statusLabel">
		<span class="task-status-badge__dot" />
		{{ statusLabel }}
	</span>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

const STATUS_LABELS = {
	open: 'Open',
	in_progress: 'In Progress',
	blocked: 'Blocked',
	done: 'Done',
	cancelled: 'Cancelled',
}

export default {
	name: 'TaskStatusBadge',
	props: {
		status: {
			type: String,
			required: true,
			validator: (v) => Object.keys(STATUS_LABELS).includes(v),
		},
	},
	computed: {
		statusLabel() {
			return t('planix', STATUS_LABELS[this.status] || this.status)
		},
	},
}
</script>

<style scoped>
.task-status-badge {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 500;
	white-space: nowrap;
}

.task-status-badge__dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	flex-shrink: 0;
}

.task-status-badge--open .task-status-badge__dot {
	background-color: var(--color-primary-element);
}

.task-status-badge--in_progress .task-status-badge__dot {
	background-color: var(--color-warning);
}

.task-status-badge--blocked .task-status-badge__dot {
	background-color: var(--color-error);
}

.task-status-badge--done .task-status-badge__dot {
	background-color: var(--color-success);
}

.task-status-badge--cancelled .task-status-badge__dot {
	background-color: var(--color-text-maxcontrast);
}
</style>
