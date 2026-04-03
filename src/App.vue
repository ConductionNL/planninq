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
							type="primary"
							:href="appStoreUrl">
							{{ t('planix', 'Install OpenRegister') }}
						</NcButton>
					</template>
				</NcEmptyContent>
			</NcAppContent>
		</template>
		<template v-else-if="storesReady && hasOpenRegisters">
			<MainMenu />
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
import { NcButton, NcContent, NcAppContent, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { generateUrl, imagePath } from '@nextcloud/router'
import { initializeStores } from './store/store.js'
import { useSettingsStore } from './store/modules/settings.js'
import MainMenu from './navigation/MainMenu.vue'

export default {
	name: 'App',
	components: {
		NcButton,
		NcContent,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		MainMenu,
	},

	provide() {
		return {
			// Views can call this.setSidebar(componentDefinition) to render a sidebar.
			setSidebar: (component) => {
				this.activeSidebar = component
			},
			closeSidebar: () => {
				this.activeSidebar = null
			},
		}
	},

	data() {
		return {
			storesReady: false,
			/** @type {object|null} Active sidebar component definition */
			activeSidebar: null,
		}
	},

	computed: {
		hasOpenRegisters() {
			const settingsStore = useSettingsStore()
			return settingsStore.hasOpenRegisters
		},
		isAdmin() {
			const settingsStore = useSettingsStore()
			return settingsStore.getIsAdmin
		},
		appIcon() {
			return imagePath('planix', 'app-dark.svg')
		},
		appStoreUrl() {
			return generateUrl('/settings/apps/integration/openregister')
		},
	},

	async created() {
		await initializeStores()
		this.storesReady = true
	},
}
</script>
