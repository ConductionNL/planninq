import { createApp } from 'vue'
import { translate as t, translatePlural as n, loadTranslations } from '@nextcloud/l10n'
import pinia from './pinia.js'
import AdminRoot from './views/settings/AdminRoot.vue'

/**
 * Bootstrap the Planix admin-settings panel.
 *
 * @return {void}
 */
function mountAdminSettings() {
	const app = createApp(AdminRoot)

	// Vue 3 has no global `Vue.mixin` / `Vue.use` — both are per-app instance.
	app.mixin({ methods: { t, n } })
	app.use(pinia)

	// `#planix-settings` is this panel's own host element (templates/settings/
	// admin.php), not a Nextcloud-owned wrapper, so `mount()` rendering INSIDE
	// it rather than replacing it is correct here.
	app.mount('#planix-settings')
}

// See src/main.js: `loadTranslations` never invokes its callback when the
// locale bundle 404s, so mounting inside the callback renders a blank admin
// panel for exactly those users. Mount on both paths.
loadTranslations('planix')
	.catch((error) => {
		console.warn('[planix] translations could not be loaded; falling back to source strings', error)
	})
	.finally(mountAdminSettings)
