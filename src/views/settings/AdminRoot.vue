<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<!-- The shared admin settings shell (adopt-cnapproot-manifest-shell, task
	     3.1): the page title, the version card with the up-to-date check, the
	     re-import of planninq's configuration through the AppHost
	     /api/settings/load route, and the support footer. Planninq's own
	     sections render in its default slot. -->
	<CnAdminSettingsShell
		appId="planninq"
		appName="Planninq">
		<Settings v-if="storesReady" />
	</CnAdminSettingsShell>
</template>

<script>
import { CnAdminSettingsShell } from '@conduction/nextcloud-vue'
/**
 * AdminRoot view.
 *
 * Admin settings root mounted by settings.js; renders planninq's Settings
 * form inside the shared CnAdminSettingsShell.
 *
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-3
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

<style scoped>
.cn-admin-settings-shell {
	max-width: 900px;
}
</style>
