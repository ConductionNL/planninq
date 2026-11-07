<template>
	<span
		class="health-status"
		:class="`health-status--${status || 'none'}`"
		data-testid="health-status"
		:data-status="status || 'none'">
		<component :is="icon" :size="18" class="health-status__icon" />
		<span>{{ label }}</span>
	</span>
</template>

<script>
/**
 * HealthStatus.
 *
 * One aspect status as an icon and a word, with a colour token as the third
 * signal, so colour is never the only one (portfolio-status-overview,
 * design decision 3).
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import MinusCircleOutline from 'vue-material-design-icons/MinusCircleOutline.vue'

export default {
	name: 'HealthStatus',

	props: {
		/** onTrack, atRisk, offTrack, or empty for no report. */
		status: {
			type: String,
			default: '',
		},
	},

	computed: {
		/**
		 * @return {object}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		icon() {
			return { onTrack: CheckCircleOutline, atRisk: AlertCircleOutline, offTrack: CloseCircleOutline }[this.status] || MinusCircleOutline
		},

		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		label() {
			return {
				onTrack: this.t('planninq', 'On track'),
				atRisk: this.t('planninq', 'At risk'),
				offTrack: this.t('planninq', 'Off track'),
			}[this.status] || this.t('planninq', 'No report')
		},
	},
}
</script>

<style scoped>
.health-status {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	white-space: nowrap;
}

.health-status--onTrack .health-status__icon {
	color: var(--color-success-text);
}

.health-status--atRisk .health-status__icon {
	color: var(--color-warning-text);
}

.health-status--offTrack .health-status__icon {
	color: var(--color-error-text);
}

.health-status--none {
	color: var(--color-text-maxcontrast);
}
</style>
