<template>
	<div class="planix-dashboard">
		<header class="planix-dashboard__header">
			<h2>{{ t('planix', 'Dashboard') }}</h2>
			<p class="planix-dashboard__lead">
				{{ t('planix', 'Starter overview with sample KPIs and activity placeholders. Replace this view with your own data.') }}
			</p>
		</header>

		<CnKpiGrid :columns="4">
			<CnStatsBlock
				:title="t('planix', 'Open items')"
				:count="12"
				:count-label="t('planix', 'sample')"
				:icon="FolderOutline"
				variant="primary"
				horizontal />
			<CnStatsBlock
				:title="t('planix', 'Due this week')"
				:count="5"
				:count-label="t('planix', 'sample')"
				:icon="CalendarClock"
				variant="warning"
				horizontal />
			<CnStatsBlock
				:title="t('planix', 'Completed')"
				:count="48"
				:count-label="t('planix', 'sample')"
				:icon="CheckCircleOutline"
				variant="success"
				horizontal />
			<CnStatsBlock
				:title="t('planix', 'Team members')"
				:count="7"
				:count-label="t('planix', 'sample')"
				:icon="AccountGroupOutline"
				variant="default"
				horizontal />
		</CnKpiGrid>

		<div class="planix-dashboard__columns">
			<CnConfigurationCard :title="t('planix', 'Recent activity')">
				<ul class="planix-dashboard__placeholder-list">
					<li>{{ t('planix', 'Placeholder: user opened a record') }}</li>
					<li>{{ t('planix', 'Placeholder: status changed to Review') }}</li>
					<li>{{ t('planix', 'Placeholder: comment added') }}</li>
				</ul>
			</CnConfigurationCard>

			<CnConfigurationCard :title="t('planix', 'Quick actions')">
				<p class="planix-dashboard__hint">
					{{ t('planix', 'Wire buttons here to create records, open lists, or deep links. Use the sidebar for Settings and Documentation.') }}
				</p>
			</CnConfigurationCard>
		</div>
	</div>
</template>

<script>
import { CnConfigurationCard, CnKpiGrid, CnStatsBlock } from '@conduction/nextcloud-vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'

export default {
	name: 'Dashboard',
	components: {
		CnConfigurationCard,
		CnKpiGrid,
		CnStatsBlock,
	},
	data() {
		return {
			FolderOutline,
			CalendarClock,
			CheckCircleOutline,
			AccountGroupOutline,
		}
	},
}
</script>

<style scoped>
.planix-dashboard {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.planix-dashboard__header {
	margin-bottom: 20px;
}

.planix-dashboard__header h2 {
	margin: 0 0 8px;
	font-size: 22px;
	font-weight: 600;
}

.planix-dashboard__lead {
	margin: 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.planix-dashboard__columns {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 16px;
}

@media (max-width: 900px) {
	.planix-dashboard__columns {
		grid-template-columns: 1fr;
	}
}

.planix-dashboard__placeholder-list {
	margin: 0;
	padding-left: 1.2em;
	line-height: 1.6;
}

.planix-dashboard__hint {
	margin: 0;
	line-height: 1.5;
	color: var(--color-text-maxcontrast);
}
</style>
