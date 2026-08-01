<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem
				:name="t('planix', 'Dashboard')"
				:to="{ name: 'Dashboard' }"
				:exact="true">
				<template #icon>
					<HomeIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('planix', 'Boards')"
				:to="{ name: 'Boards' }">
				<template #icon>
					<ViewDashboardOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('planix', 'Projects')"
				:to="{ name: 'Projects' }">
				<template #icon>
					<FolderOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('planix', 'Portfolio')"
				:to="{ name: 'Portfolio' }">
				<template #icon>
					<ChartBarIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('planix', 'Timesheet')"
				:to="{ name: 'Timesheet' }">
				<template #icon>
					<ClockOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('planix', 'Documentation')"
				@click="openLink('https://planix.conduction.nl', '_blank')">
				<template #icon>
					<BookOpenVariantOutline :size="20" />
				</template>
			</NcAppNavigationItem>
		</template>
		<template #footer>
			<NcAppNavigationSettings>
				<NcAppNavigationItem
					:name="t('planix', 'Settings')"
					@click="$emit('open-settings')">
					<template #icon>
						<CogIcon :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationItem, NcAppNavigationSettings } from '@nextcloud/vue'
import BookOpenVariantOutline from 'vue-material-design-icons/BookOpenVariantOutline.vue'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'

export default {
	name: 'MainMenu',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationSettings,
		BookOpenVariantOutline,
		ChartBarIcon,
		ClockOutline,
		CogIcon,
		FolderOutline,
		HomeIcon,
		ViewDashboardOutline,
	},

	// Vue 3 warns about "extraneous non-emits event listeners" for any event a
	// component emits without declaring it, and lets undeclared listeners fall
	// through onto the root element as native handlers.
	emits: ['open-settings'],

	methods: {
		/**
		 * Opens an external URL in a new tab with noopener and noreferrer
		 * to prevent the opened page from accessing window.opener.
		 *
		 * @spec exclude Trivial helper — opens an external URL safely via window.open.
		 * @param {string} url The URL to open.
		 * @param {string} [target] The window target (defaults to _blank).
		 */
		openLink(url, target = '_blank') {
			window.open(url, target, 'noopener,noreferrer')
		},
	},
}
</script>
