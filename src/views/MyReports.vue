<template>
	<div class="my-reports">
		<div class="my-reports__header">
			<h2>{{ t('planninq', 'Your reports') }}</h2>
			<NcButton variant="primary" data-testid="report-new" @click="building = true">
				{{ t('planninq', 'New report') }}
			</NcButton>
		</div>

		<div v-if="loading" class="my-reports__loading">
			<NcLoadingIcon :size="32" />
		</div>
		<template v-else>
			<section v-for="group in groups" :key="group.id" :data-testid="`reports-${group.id}`">
				<h3>{{ group.label }}</h3>
				<p v-if="!group.reports.length" class="my-reports__empty">
					{{ group.empty }}
				</p>
				<ul v-else class="my-reports__list">
					<li v-for="report in group.reports" :key="report.id">
						<router-link :to="{ name: 'ReportPage', params: { id: report.id } }">
							{{ report.title }}
						</router-link>
						<span v-if="report.description" class="my-reports__description">{{ report.description }}</span>
					</li>
				</ul>
			</section>
		</template>

		<ReportBuilderDialog
			v-if="building"
			:projects="projects"
			@close="building = false"
			@saved="onSaved" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import ReportBuilderDialog from '../dialogs/ReportBuilderDialog.vue'
import { fetchReports } from '../api/reports.js'
import { useProjectsStore } from '../store/projects.js'
import { splitReports } from '../utils/reportBuilder.js'

/**
 * The reports a user built ("My reports") and the ones shared with them
 * ("Shared reports"), and the button that builds a new one.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export default {
	name: 'MyReports',
	components: { NcButton, NcLoadingIcon, ReportBuilderDialog },
	data() {
		return { reports: [], projects: [], loading: true, building: false }
	},

	computed: {
		/**
		 * @return {Array<object>} My reports, then shared reports.
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		groups() {
			const { mine, shared } = splitReports(this.reports, getCurrentUser()?.uid)
			return [
				{ id: 'mine', label: this.t('planninq', 'My reports'), empty: this.t('planninq', 'You have not built a report yet.'), reports: mine },
				{ id: 'shared', label: this.t('planninq', 'Shared reports'), empty: this.t('planninq', 'Nobody has shared a report with you yet.'), reports: shared },
			]
		},
	},

	/**
	 * @spec exclude Lifecycle glue: loads the reports and the projects to pick from.
	 */
	async mounted() {
		const store = useProjectsStore()
		try {
			const [reports] = await Promise.all([fetchReports(), store.fetchProjects()])
			this.reports = reports
			this.projects = (store.projects || []).map((project) => ({ id: project.id ?? project['@self']?.id, title: project.title }))
		} finally {
			this.loading = false
		}
	},

	methods: {
		/**
		 * Open the report just saved.
		 *
		 * @param {object} saved The saved report
		 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
		 */
		onSaved(saved) {
			this.building = false
			const id = saved.id ?? saved['@self']?.id
			if (id) {
				this.$router.push({ name: 'ReportPage', params: { id } })
			}
		},
	},
}
</script>

<style scoped>
.my-reports {
	padding: 16px 24px;
}

.my-reports__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.my-reports__list li {
	display: flex;
	flex-direction: column;
	padding: 8px 0;
}

.my-reports__description,
.my-reports__empty {
	color: var(--color-text-maxcontrast);
}

.my-reports__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}
</style>
