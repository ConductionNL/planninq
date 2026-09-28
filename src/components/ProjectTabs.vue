<template>
	<nav class="project-tabs" :aria-label="t('planninq', 'Project pages')">
		<ul class="project-tabs__list">
			<li v-for="tab in tabs" :key="tab.id">
				<RouterLink
					class="project-tabs__tab"
					:to="{ name: tab.route, params: { id: projectId } }"
					:data-testid="`project-tab-${tab.id}`">
					{{ tab.label }}
				</RouterLink>
			</li>
		</ul>
	</nav>
</template>

<script>
/**
 * ProjectTabs.
 *
 * The one row of tabs every project page shares. Each tab is a link, so Tab
 * reaches it and Enter opens it, and the router marks the open page with
 * aria-current="page".
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.1
 */
import { PROJECT_TABS } from '../utils/projectOverview.js'

export default {
	name: 'ProjectTabs',

	props: {
		/** The project whose pages the tabs open. */
		projectId: {
			type: String,
			required: true,
		},
	},

	computed: {
		/**
		 * The tabs with their labels.
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.1
		 */
		tabs() {
			const labels = {
				overview: this.t('planninq', 'Overview'),
				board: this.t('planninq', 'Board'),
				backlog: this.t('planninq', 'Backlog'),
				timeline: this.t('planninq', 'Timeline'),
				risks: this.t('planninq', 'Risks'),
				status: this.t('planninq', 'Status'),
				log: this.t('planninq', 'Log'),
			}
			return PROJECT_TABS.map((tab) => ({ ...tab, label: labels[tab.id] }))
		},
	},
}
</script>

<style scoped>
.project-tabs {
	margin-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.project-tabs__list {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.project-tabs__tab {
	display: inline-block;
	padding: 8px 14px;
	border-bottom: 3px solid transparent;
	color: var(--color-main-text);
	text-decoration: none;
}

.project-tabs__tab:hover {
	background-color: var(--color-background-hover);
}

.project-tabs__tab:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.project-tabs__tab[aria-current='page'] {
	border-bottom-color: var(--color-primary-element);
	font-weight: 600;
}
</style>
