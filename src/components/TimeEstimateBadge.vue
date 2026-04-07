<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@spec openspec/changes/time-tracking-mvp/tasks.md#task-3
-->
<template>
	<span v-if="estimatedDuration" class="time-estimate-badge" :title="tooltipText">
		<TimerOutline :size="14" />
		{{ formattedEstimate }}
	</span>
</template>

<script>
/**
 * Badge displaying estimated duration on kanban cards.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-3
 */
import TimerOutline from 'vue-material-design-icons/TimerOutline.vue'
import { formatDuration } from './DurationInput.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TimeEstimateBadge',

	components: { TimerOutline },

	props: {
		/** Estimated duration in minutes */
		estimatedDuration: {
			type: Number,
			default: null,
		},
		/** Logged duration in minutes (computed sum of time entries) */
		loggedDuration: {
			type: Number,
			default: 0,
		},
	},

	computed: {
		formattedEstimate() {
			return formatDuration(this.estimatedDuration)
		},
		tooltipText() {
			if (this.loggedDuration > 0) {
				return t('planix', '{logged} / {estimated} logged', {
					logged: formatDuration(this.loggedDuration),
					estimated: formatDuration(this.estimatedDuration),
				})
			}
			return t('planix', 'Estimate: {estimate}', {
				estimate: formatDuration(this.estimatedDuration),
			})
		},
	},
}
</script>

<style scoped>
.time-estimate-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 8px;
	border-radius: var(--border-radius-pill, 12px);
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1;
	white-space: nowrap;
}
</style>
