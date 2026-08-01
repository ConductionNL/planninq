<template>
	<NcContent app-name="planix">
		<template v-if="storesReady && !hasOpenRegisters">
			<NcAppContent class="open-register-missing">
				<NcEmptyContent
					:name="t('planix', 'OpenRegister is required')"
					:description="t('planix', 'This app needs OpenRegister to store and manage data. Please install OpenRegister from the app store to get started.')">
					<template #icon>
						<img :src="appIcon"
							alt=""
							width="64"
							height="64">
					</template>
					<template #action>
						<NcButton
							v-if="isAdmin"
							variant="primary"
							:href="appStoreUrl">
							{{ t('planix', 'Install OpenRegister') }}
						</NcButton>
					</template>
				</NcEmptyContent>
			</NcAppContent>
		</template>
		<template v-else-if="storesReady && hasOpenRegisters">
			<MainMenu @open-settings="settingsOpen = true" />
			<UserSettings :open="settingsOpen" @update:open="settingsOpen = $event" />
			<NcAppContent>
				<router-view />
			</NcAppContent>
			<!-- Sidebar outlet: views inject their sidebar component here -->
			<component
				:is="activeSidebar"
				v-if="activeSidebar"
				v-bind="activeSidebar.propsData || {}"
				v-on="activeSidebar.on || {}"
				@close="activeSidebar = null" />
		</template>
		<NcAppContent v-else>
			<div style="display: flex; justify-content: center; align-items: center; height: 100%;">
				<NcLoadingIcon :size="64" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
/**
 * App root component.
 *
 * Renders the OpenRegister-required gate when OR is missing (admin sees an
 * install link); otherwise mounts the main app shell.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-5
 */
import { markRaw } from 'vue'
import { NcButton, NcContent, NcAppContent, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { generateUrl, imagePath } from '@nextcloud/router'
import { initializeStores } from './store/store.js'
import { useSettingsStore } from './store/modules/settings.js'
import MainMenu from './navigation/MainMenu.vue'
import UserSettings from './views/settings/UserSettings.vue'

export default {
	name: 'App',
	components: {
		NcButton,
		NcContent,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		MainMenu,
		UserSettings,
	},

	/**
	 * @spec exclude Framework glue — provides setSidebar/closeSidebar inject callbacks; no observable behavior.
	 */
	provide() {
		return {
			// Views can call this.setSidebar(componentDefinition) to render a sidebar.
			//
			// `markRaw` is required under Vue 3: assigning a component
			// definition into reactive `data` makes Vue deep-proxy the whole
			// options object, which it warns about ("Vue received a Component
			// that was made a reactive object") and which needlessly proxies
			// every option on every render.
			setSidebar: (component) => {
				this.activeSidebar = component ? markRaw(component) : null
			},
			closeSidebar: () => {
				this.activeSidebar = null
			},
		}
	},

	data() {
		return {
			storesReady: false,
			settingsOpen: false,
			/** @type {object|null} Active sidebar component definition */
			activeSidebar: null,
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — proxies settingsStore.hasOpenRegisters.
		 */
		hasOpenRegisters() {
			const settingsStore = useSettingsStore()
			return settingsStore.hasOpenRegisters
		},
		/**
		 * @spec exclude Store passthrough — proxies settingsStore.getIsAdmin.
		 */
		isAdmin() {
			const settingsStore = useSettingsStore()
			return settingsStore.getIsAdmin
		},
		/**
		 * @spec exclude Trivial asset-path getter — resolves the app-dark.svg image path.
		 */
		appIcon() {
			return imagePath('planix', 'app-dark.svg')
		},
		/**
		 * @spec exclude Trivial URL getter — builds the OpenRegister app-store link.
		 */
		appStoreUrl() {
			return generateUrl('/settings/apps/integration/openregister')
		},
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
