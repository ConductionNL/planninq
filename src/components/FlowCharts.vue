<template>
	<div class="flow-charts">
		<section class="flow-charts__section" data-testid="flow-cumulative">
			<div class="flow-charts__heading">
				<h3>{{ t('planninq', 'Cumulative flow') }}</h3>
				<NcButton variant="tertiary" data-testid="flow-table-toggle" @click="asTable = !asTable">
					{{ asTable ? t('planninq', 'Show as chart') : t('planninq', 'Show as table') }}
				</NcButton>
			</div>

			<template v-if="!asTable">
				<svg
					class="flow-charts__area"
					:viewBox="`0 0 ${width} ${height}`"
					preserveAspectRatio="none"
					role="img"
					:aria-label="t('planninq', 'Tasks per column per day')">
					<path
						v-for="(band, index) in bands"
						:key="band.id"
						:d="bandPath(band.points, width, height, max)"
						:style="{ fill: bandColour(index) }" />
				</svg>
				<ul class="flow-charts__legend">
					<li v-for="(column, index) in columns" :key="column.id">
						<span class="flow-charts__swatch" :style="{ background: bandColour(index) }" />
						{{ column.title }}
					</li>
				</ul>
			</template>

			<table v-else class="flow-charts__table" data-testid="flow-table">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Date') }}
						</th>
						<th v-for="column in columns" :key="column.id" scope="col">
							{{ column.title }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in table" :key="row.date">
						<th scope="row">
							{{ row.date }}
						</th>
						<td v-for="(cell, index) in row.cells" :key="index" :data-column="columns[index].title">
							{{ cell }}
						</td>
					</tr>
				</tbody>
			</table>
			<p v-if="withoutHistory" class="flow-charts__note">
				{{ t('planninq', 'Tasks without recorded history in this period: {count}', { count: withoutHistory }) }}
			</p>
		</section>

		<section class="flow-charts__section" data-testid="flow-times">
			<h3>{{ t('planninq', 'Lead and cycle time') }}</h3>
			<p v-if="!summary.finished" class="flow-charts__note">
				{{ t('planninq', 'No tasks were finished in this period.') }}
			</p>
			<template v-else>
				<dl class="flow-charts__stats">
					<div>
						<dt>{{ t('planninq', 'Finished tasks') }}</dt>
						<dd data-testid="flow-finished">
							{{ summary.finished }}
						</dd>
					</div>
					<div>
						<dt>{{ t('planninq', 'Cycle time, average') }}</dt>
						<dd data-testid="flow-cycle-average">
							{{ days(summary.cycle.average) }}
						</dd>
					</div>
					<div>
						<dt>{{ t('planninq', 'Cycle time, 85th percentile') }}</dt>
						<dd data-testid="flow-cycle-p85">
							{{ days(summary.cycle.p85) }}
						</dd>
					</div>
					<div>
						<dt>{{ t('planninq', 'Lead time, average') }}</dt>
						<dd>{{ days(summary.lead.average) }}</dd>
					</div>
				</dl>
				<p v-if="summary.estimated" class="flow-charts__note">
					{{ t('planninq', 'Tasks whose finish time was estimated from their history: {count}', { count: summary.estimated }) }}
				</p>

				<svg
					class="flow-charts__scatter"
					:viewBox="`0 0 ${width} ${height}`"
					role="img"
					:aria-label="t('planninq', 'Cycle time of each finished task, with the average and the 85th percentile')">
					<line
						class="flow-charts__line"
						x1="0"
						:x2="width"
						:y1="scatterY(summary.cycle.average)"
						:y2="scatterY(summary.cycle.average)" />
					<line
						class="flow-charts__line flow-charts__line--p85"
						x1="0"
						:x2="width"
						:y1="scatterY(summary.cycle.p85)"
						:y2="scatterY(summary.cycle.p85)" />
					<circle
						v-for="point in scatter"
						:key="point.id"
						class="flow-charts__dot"
						r="4"
						:cx="point.x"
						:cy="point.y" />
				</svg>

				<h4>{{ t('planninq', 'Slowest tasks') }}</h4>
				<ol class="flow-charts__slowest" data-testid="flow-slowest">
					<li v-for="task in summary.slowest" :key="task.id">
						<router-link :to="{ name: 'TaskDetail', params: { id: task.projectId, taskId: task.id } }">
							{{ task.key ? task.key + ' ' : '' }}{{ task.title }}
						</router-link>
						<span class="flow-charts__times">
							{{ t('planninq', 'cycle {cycle}, lead {lead}', { cycle: days(task.cycleDays), lead: days(task.leadDays) }) }}
						</span>
					</li>
				</ol>
			</template>
		</section>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import { bandPath, flowTable, scatterPoints, stackBands, stackMax } from '../utils/flowChart.js'

/**
 * The cumulative flow diagram (with its table view) and the lead and cycle
 * time of finished tasks, for one project or a portfolio.
 *
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export default {
	name: 'FlowCharts',
	components: { NcButton },
	props: {
		/** Columns in board order: id, title. */
		columns: { type: Array, default: () => [] },
		/** Days from the flow endpoint: date, counts. */
		flowDays: { type: Array, default: () => [] },
		/** Finished tasks: id, title, key, finishedAt, leadDays, cycleDays. */
		finished: { type: Array, default: () => [] },
		/** Summary: finished, estimated, lead, cycle, slowest. */
		summary: { type: Object, default: () => ({ finished: 0, estimated: 0, lead: {}, cycle: {}, slowest: [] }) },
		/** Tasks without recorded history. */
		withoutHistory: { type: Number, default: 0 },
	},

	data() {
		return { asTable: false, width: 600, height: 200 }
	},

	computed: {
		/**
		 * @return {Array<object>} The stacked bands.
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
		 */
		bands() {
			return stackBands(this.columns, this.flowDays)
		},

		/**
		 * @return {number} The highest stack.
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
		 */
		max() {
			return stackMax(this.flowDays)
		},

		/**
		 * @return {Array<object>} The table rows.
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
		 */
		table() {
			return flowTable(this.columns, this.flowDays)
		},

		/**
		 * @return {Array<object>} The finished tasks as scatter points.
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
		 */
		scatter() {
			return scatterPoints(this.finished, this.width, this.height)
		},
	},

	methods: {
		bandPath,
		/**
		 * A band's fill: the column's place in a ramp of the primary colour.
		 *
		 * @param {number} index The column's place
		 * @return {string}
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
		 */
		bandColour(index) {
			const share = this.columns.length > 1 ? 30 + Math.round((index / (this.columns.length - 1)) * 70) : 100
			return `color-mix(in srgb, var(--color-primary-element) ${share}%, var(--color-main-background))`
		},

		/**
		 * The y of a cycle time on the scatter.
		 *
		 * @param {number} value Days
		 * @return {number}
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
		 */
		scatterY(value) {
			const top = Math.max(1, ...this.finished.map((row) => Number(row.cycleDays)))
			return this.height - (Number(value) / top) * this.height
		},

		/**
		 * A number of days as text.
		 *
		 * @param {number} value Days
		 * @return {string}
		 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
		 */
		days(value) {
			return this.t('planninq', '{days} days', { days: Number(value || 0) })
		},
	},
}
</script>
