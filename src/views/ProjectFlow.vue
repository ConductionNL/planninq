<template>
	<div class="project-flow">
		<div class="project-flow__header">
			<h2>{{ t('planninq', 'Flow') }}</h2>
			<NcSelect
				:modelValue="period"
				class="project-flow__period"
				:options="periods"
				:clearable="false"
				:inputLabel="t('planninq', 'Period')"
				label="label"
				data-testid="flow-period"
				@update:modelValue="pickPeriod" />
		</div>

		<ProjectTabs :projectId="projectId" />

		<div v-if="loading" class="project-flow__loading">
			<NcLoadingIcon :size="32" />
		</div>
		<NcNoteCard v-else-if="error" type="error">
			{{ error }}
		</NcNoteCard>
		<FlowCharts
			v-else
			:columns="flow.columns"
			:flowDays="flow.days"
			:finished="flow.finished"
			:summary="flow.summary"
			:withoutHistory="flow.withoutHistory" />
	</div>
</template>

<script>
import { NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import FlowCharts from '../components/FlowCharts.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import { fetchProjectFlow } from '../api/flow.js'
import { flowPeriods, periodWindow } from '../utils/flowChart.js'

/**
 * The Flow tab of a project: the cumulative flow diagram and the lead and
 * cycle time of its finished tasks over a chosen period.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-2.1
 */
export default {
	name: 'ProjectFlow',
	components: { FlowCharts, NcLoadingIcon, NcNoteCard, NcSelect, ProjectTabs },
	data() {
		const periods = flowPeriods(this.t)
		return {
			periods,
			period: periods[1],
			loading: true,
			error: '',
			flow: { columns: [], days: [], finished: [], summary: { finished: 0, estimated: 0, lead: {}, cycle: {}, slowest: [] }, withoutHistory: 0 },
		}
	},

	computed: {
		/**
		 * @return {string} The project's UUID from the route.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-2.1
		 */
		projectId() {
			return this.$route.params.id
		},
	},

	watch: {
		/**
		 * Reload on another project.
		 *
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-2.1
		 */
		projectId() {
			this.load()
		},
	},

	mounted() {
		this.load()
	},

	methods: {
		/**
		 * Switch to another period and reload.
		 *
		 * @param {{days: number}} option The picked period
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-2.1
		 */
		pickPeriod(option) {
			if (option) {
				this.period = option
				this.load()
			}
		},

		/**
		 * Read the flow for the chosen period.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-2.1
		 */
		async load() {
			this.loading = true
			this.error = ''
			const { from, to } = periodWindow(this.period.days, new Date())
			try {
				this.flow = { ...this.flow, ...(await fetchProjectFlow(this.projectId, from, to)) }
			} catch {
				this.error = this.t('planninq', 'Could not load the flow of this project.')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.project-flow {
	padding: 16px 24px;
}

.project-flow__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.project-flow__period {
	min-width: 200px;
}

.project-flow__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}
</style>
