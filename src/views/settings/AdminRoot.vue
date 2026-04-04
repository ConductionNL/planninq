<template>
	<div class="planix-admin">
		<CnVersionInfoCard
			:app-name="'Planix'"
			:app-version="appVersion"
			:is-up-to-date="true"
			:show-update-button="true"
			:title="t('planix', 'Version Information')"
			:description="t('planix', 'Information about the current Planix installation')">
			<template #footer>
				<div class="cn-support-info">
					<h4>{{ t('planix', 'Support') }}</h4>
					<p>{{ t('planix', 'For support, contact us at') }} <a href="mailto:support@conduction.nl">support@conduction.nl</a></p>
				</div>
			</template>
		</CnVersionInfoCard>

		<template v-if="storesReady">
			<Settings />
			<DefaultColumns />
			<OpenRegisterInit />
		</template>
	</div>
</template>

<script>
import { CnVersionInfoCard } from '@conduction/nextcloud-vue'
import Settings from './Settings.vue'
import DefaultColumns from './DefaultColumns.vue'
import OpenRegisterInit from './OpenRegisterInit.vue'
import { initializeStores } from '../../store/store.js'

export default {
	name: 'AdminRoot',
	components: {
		CnVersionInfoCard,
		Settings,
		DefaultColumns,
		OpenRegisterInit,
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
