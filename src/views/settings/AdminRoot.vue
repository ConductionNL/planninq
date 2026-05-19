<template>
	<div class="planix-admin">
		<CnVersionInfoCard
			:app-name="'Planix'"
			:app-version="appVersion"
			:is-up-to-date="true"
			:show-update-button="true"
			:title="t('planix', 'Version information')"
			:description="t('planix', 'Information about the current Planix installation')">
			<template #footer>
				<div class="cn-support-info">
					<h4>{{ t('planix', 'Support') }}</h4>
					<p>{{ t('planix', 'For support, contact us at') }} <a href="mailto:support@conduction.nl">support@conduction.nl</a></p>
				</div>
			</template>
		</CnVersionInfoCard>

		<Settings v-if="storesReady" />
	</div>
</template>

<script>
import { CnVersionInfoCard } from '@conduction/nextcloud-vue'
import Settings from './Settings.vue'
import { initializeStores } from '../../store/store.js'

export default {
	name: 'AdminRoot',
	components: {
		CnVersionInfoCard,
		Settings,
	},
	data() {
		return {
			storesReady: false,
			appVersion: document.getElementById('planix-settings')?.dataset?.version || 'Unknown',
		}
	},
	async created() {
		await initializeStores()
		this.storesReady = true
	},
}
</script>

<style scoped>
.planix-admin {
	max-width: 900px;
}
</style>
