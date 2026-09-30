<template>
	<section class="scenario-compare" data-testid="scenario-compare">
		<h3>{{ t('planninq', 'Compare scenarios') }}</h3>
		<NcSelect
			v-model="chosen"
			:options="options"
			:multiple="true"
			:selectable="() => chosen.length < maxCompared"
			keepOpen
			label="title"
			:inputLabel="t('planninq', 'Scenarios to compare')"
			data-testid="scenario-compare-select" />
		<p v-if="chosen.length < 2">
			{{ t('planninq', 'Choose two or three scenarios to compare them.') }}
		</p>
		<template v-else>
			<table data-testid="scenario-compare-metrics">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Measure') }}
						</th>
						<th v-for="scenario in chosen" :key="scenario.id" scope="col">
							{{ scenario.title }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in rows" :key="row.key">
						<th scope="row">
							{{ lineLabel(row.key) }}
						</th>
						<td
							v-for="(value, index) in row.values"
							:key="index"
							:class="{ 'scenario-compare__best': row.best.includes(index) }"
							:data-testid="'compare-' + row.key + '-' + index">
							{{ value === null ? '' : value }}
							<span v-if="row.best.includes(index)" class="hidden-visually">{{ t('planninq', 'Best') }}</span>
						</td>
					</tr>
				</tbody>
			</table>

			<h4>{{ t('planninq', 'Lessons that differ') }}</h4>
			<p v-if="diff.length === 0">
				{{ t('planninq', 'No lesson differs.') }}
			</p>
			<table v-else data-testid="scenario-compare-diff">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Lesson') }}
						</th>
						<th v-for="scenario in chosen" :key="scenario.id" scope="col">
							{{ scenario.title }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in diff" :key="row.lesson">
						<th scope="row">
							{{ row.lesson }}
						</th>
						<td v-for="(cell, index) in row.cells" :key="index">
							{{ cell === null ? t('planninq', 'Not placed') : t('planninq', '{period} in {room}', { period: cell.period, room: cell.room }) }}
						</td>
					</tr>
				</tbody>
			</table>
		</template>
	</section>
</template>

<script>
/**
 * TimetableScenarioCompare.
 *
 * Mounted above the scenario list (CnIndexPage's below-header slot): pick two
 * or three scenarios, generated or imported, and read their stored measures
 * side by side with the best value per line marked, then the lessons whose
 * period or room differs.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-7.1
 */
import { buildHeaders } from '@conduction/nextcloud-vue'
import { generateUrl } from '@nextcloud/router'
import { NcSelect } from '@nextcloud/vue'
import { compareRows, lessonDiff, MAX_COMPARED } from '../utils/scenarioCompare.js'

export default {
	name: 'TimetableScenarioCompare',

	components: {
		NcSelect,
	},

	data() {
		return {
			options: [],
			chosen: [],
			maxCompared: MAX_COMPARED,
		}
	},

	computed: {
		/**
		 * The metric rows.
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-7.1
		 */
		rows() {
			return compareRows(this.chosen)
		},

		/**
		 * The lessons that differ.
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-7.1
		 */
		diff() {
			return lessonDiff(this.chosen)
		},
	},

	/**
	 * Read the scenarios that have a result.
	 *
	 * @return {Promise<void>}
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-7.1
	 */
	async mounted() {
		const response = await fetch(generateUrl('/apps/openregister/api/objects/planninq/timetableScenario?_limit=100'), { headers: buildHeaders() }).catch(() => null)
		const body = response?.ok ? await response.json() : { results: [] }
		this.options = (body.results ?? []).filter((scenario) => ['done', 'published'].includes(scenario.status))
	},

	methods: {
		/**
		 * The label of a metric line.
		 *
		 * @param {string} key The metric.
		 * @return {string}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-7.1
		 */
		lineLabel(key) {
			return {
				placed: this.t('planninq', 'Lessons placed'),
				unplaced: this.t('planninq', 'Lessons without a place'),
				clashes: this.t('planninq', 'Clashes'),
				hardWishesBroken: this.t('planninq', 'Hard wishes broken'),
				softWishesBroken: this.t('planninq', 'Soft wishes broken'),
				softPenalty: this.t('planninq', 'Soft wishes broken, weighted'),
				teacherGaps: this.t('planninq', 'Free periods between lessons, all teachers'),
				teacherGapsWorst: this.t('planninq', 'Most free periods of one teacher'),
				lessonsPerDayWorst: this.t('planninq', 'Most lessons of one group on a day'),
				roomUse: this.t('planninq', 'Room use'),
			}[key] ?? key
		},
	},
}
</script>

<style scoped>
.scenario-compare {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-block-end: 16px;
}

.scenario-compare table {
	border-collapse: collapse;
	width: 100%;
}

.scenario-compare th,
.scenario-compare td {
	border-bottom: 1px solid var(--color-border);
	padding: 4px 8px;
	text-align: start;
}

.scenario-compare__best {
	font-weight: bold;
	color: var(--color-success-text, var(--color-main-text));
}
</style>
