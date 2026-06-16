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
/**
 * AdminRoot view.
 *
 * Admin settings root mounted by settings.js bootstrap; renders the
 * version info card and the Settings form.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
 */
import { loadState } from '@nextcloud/initial-state'
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
			appVersion: loadState('planix', 'version', 'Unknown'),
		}
	},
	/**
	 * @spec exclude Lifecycle bootstrap — awaits initializeStores() then flips storesReady; store wiring is spec'd in app-shell-and-data-store.
	 */
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
