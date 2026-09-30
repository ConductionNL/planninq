<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="my-calendar">
		<div class="my-calendar__header">
			<h2>{{ t('planninq', 'My calendar') }}</h2>
			<RouterLink :to="{ name: 'MyWork' }" data-testid="my-calendar-as-list">
				{{ t('planninq', 'Show as list') }}
			</RouterLink>
		</div>

		<div v-if="loading" class="my-calendar__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<TaskCalendar v-else
			:tasks="tasks"
			:projects="projectTitles"
			:showProject="true"
			:initialDate="initialDate" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcLoadingIcon } from '@nextcloud/vue'
import TaskCalendar from '../components/TaskCalendar.vue'
import { useProjectsStore } from '../store/projects.js'

/**
 * MyCalendar: the tasks assigned to or shared with the user, across their
 * projects, on their due dates with the project named (planning-calendar).
 * The same query as My tasks; `?date=YYYY-MM-DD` opens it on that day.
 *
 * @spec openspec/changes/planning-calendar/tasks.md#task-1.3
 */
export default {
	name: 'MyCalendar',

	components: {
		NcLoadingIcon,
		TaskCalendar,
	},

	data() {
		return {
			tasks: [],
			projectTitles: {},
			loading: true,
		}
	},

	computed: {
		/**
		 * @spec openspec/changes/planning-calendar/tasks.md#task-1.3
		 * @return {string} The day to open on, from ?date=
		 */
		initialDate() {
			const value = String(this.$route.query.date || '')
			return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : ''
		},
	},

	mounted() {
		this.load()
	},

	methods: {
		t,

		/**
		 * @spec openspec/changes/planning-calendar/tasks.md#task-1.3
		 */
		async load() {
			this.loading = true
			const store = useProjectsStore()
			const [tasks, projects] = await Promise.all([store.fetchMyTasks(), store.fetchProjects()])
			this.tasks = tasks
			this.projectTitles = Object.fromEntries((projects || []).map((project) => [project.id, project.title]))
			this.loading = false
		},
	},
}
</script>

<style scoped>
.my-calendar {
	padding: calc(var(--default-grid-baseline) * 5);
}

.my-calendar__header {
	display: flex;
	align-items: baseline;
	gap: calc(var(--default-grid-baseline) * 4);
}

.my-calendar__loading {
	display: flex;
	justify-content: center;
	padding: calc(var(--default-grid-baseline) * 10);
}
</style>
