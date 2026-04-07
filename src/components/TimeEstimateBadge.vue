<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@spec openspec/changes/time-tracking-mvp/tasks.md#task-3
-->
<template>
	<span
		v-if="estimatedDuration"
		class="time-estimate-badge"
		:title="t('planix', 'Estimated: {duration}', { duration: formatted })">
		<TimerOutline :size="14" />
		{{ formatted }}
	</span>
</template>

<script>
import TimerOutline from 'vue-material-design-icons/TimerOutline.vue'
import { formatDuration } from '../utils/duration.js'

/**
 * Badge showing estimated duration on kanban cards.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-3
 */
export default {
	name: 'TimeEstimateBadge',

	components: { TimerOutline },

	props: {
		estimatedDuration: {
			type: Number,
			default: null,
		},
	},

	computed: {
		formatted() {
			return formatDuration(this.estimatedDuration)
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
	border-radius: var(--border-radius-pill);
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	white-space: nowrap;
}
</style>
