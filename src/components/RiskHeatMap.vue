<template>
	<table class="risk-heat-map" data-testid="risk-heat-map">
		<caption>{{ t('planninq', 'Risks by likelihood and impact') }}</caption>
		<thead>
			<tr>
				<th scope="col">
					{{ t('planninq', 'Impact') }} / {{ t('planninq', 'Likelihood') }}
				</th>
				<th v-for="cell in rows[0].cells" :key="cell.likelihood" scope="col">
					{{ cell.likelihood }}. {{ t('planninq', cell.label) }}
				</th>
			</tr>
		</thead>
		<tbody>
			<tr v-for="row in rows" :key="row.impact">
				<th scope="row">
					{{ row.impact }}. {{ t('planninq', row.label) }}
				</th>
				<td
					v-for="cell in row.cells"
					:key="cell.likelihood"
					:class="`risk-heat-map__cell risk-heat-map__cell--${cell.band}`"
					:data-testid="`risk-cell-${cell.likelihood}-${row.impact}`">
					<span class="risk-heat-map__count">{{ cell.count }}</span>
					<span class="risk-heat-map__band">{{ bandLabel(cell.band) }}</span>
				</td>
			</tr>
		</tbody>
	</table>
</template>

<script>
/**
 * RiskHeatMap.
 *
 * Likelihood against impact on the admin's scale. Each cell carries its
 * count and its band as text, and its colour from NL Design tokens, so
 * colour is never the only signal (WCAG 1.4.1).
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
import { DEFAULT_RISK_SCALE, heatMapRows } from '../utils/riskHelpers.js'

export default {
	name: 'RiskHeatMap',

	props: {
		/** The risks to count. */
		risks: {
			type: Array,
			default: () => [],
		},

		/** The risk scale. */
		scale: {
			type: Object,
			default: () => DEFAULT_RISK_SCALE,
		},
	},

	computed: {
		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		rows() {
			return heatMapRows(this.risks, this.scale)
		},
	},

	methods: {
		/**
		 * @param {string} band low, medium or high
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		bandLabel(band) {
			return {
				low: this.t('planninq', 'Low'),
				medium: this.t('planninq', 'Medium'),
				high: this.t('planninq', 'High'),
			}[band]
		},
	},
}
</script>

<style scoped>
.risk-heat-map {
	border-collapse: separate;
	border-spacing: 4px;
	margin-bottom: 16px;
}

.risk-heat-map caption {
	text-align: start;
	font-weight: 600;
	margin-bottom: 4px;
}

.risk-heat-map th {
	padding: 4px 8px;
	font-weight: 400;
	color: var(--color-text-maxcontrast);
	text-align: start;
}

.risk-heat-map__cell {
	min-width: 72px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	border: 1px solid var(--color-border);
	color: var(--color-main-text);
	text-align: center;
}

.risk-heat-map__cell--low {
	background-color: var(--nldesign-color-feedback-success-background, var(--color-success-hover, var(--color-background-dark)));
}

.risk-heat-map__cell--medium {
	background-color: var(--nldesign-color-feedback-warning-background, var(--color-warning-hover, var(--color-background-dark)));
}

.risk-heat-map__cell--high {
	background-color: var(--nldesign-color-feedback-error-background, var(--color-error-hover, var(--color-background-dark)));
}

.risk-heat-map__count {
	display: block;
	font-size: 18px;
	font-weight: 600;
}

.risk-heat-map__band {
	display: block;
	font-size: 12px;
}
</style>
