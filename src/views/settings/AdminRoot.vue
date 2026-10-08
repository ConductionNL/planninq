<template>
	<!-- The fleet's admin settings chrome: page title, version card (version and up-to-date state come from the
	     AppHost admin settings initial state), support footer. The re-import button stays in the Settings form below,
	     so it is not shown twice. -->
	<CnAdminSettingsShell
		appId="planninq"
		appName="Planninq"
		docUrl="https://github.com/ConductionNL/planninq"
		:showReimport="false">
		<Settings v-if="storesReady" />
	</CnAdminSettingsShell>
</template>

<script>
import { CnAdminSettingsShell } from '@conduction/nextcloud-vue'
/**
 * AdminRoot view.
 *
 * Admin settings root mounted by settings.js bootstrap; a thin wrapper that
 * puts the Settings form inside CnAdminSettingsShell.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
 */
import Settings from './Settings.vue'
import { initializeStores } from '../../store/store.js'

export default {
	name: 'AdminRoot',
	components: {
		CnAdminSettingsShell,
		Settings,
	},

	data() {
		return {
			storesReady: false,
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
