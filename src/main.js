import { registerIcons } from '@conduction/nextcloud-vue'
import { loadTranslations, translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createApp } from 'vue'
import App from './App.vue'
import appIcons from './icons.js'
import pinia from './pinia.js'
import router from './router/index.js'
import { initializeStores } from './store/store.js'

// Library CSS — must be explicit import (webpack tree-shakes side-effect imports from aliased packages)
import '@conduction/nextcloud-vue/css/index.css'
// Global (unscoped) app styles
import './assets/app.css'

// ADR-077 rule 3. The shared components resolve an `icon` by PascalCase name
// through this registry, and an unregistered name renders NO icon rather than a
// fallback — so a missing registerIcons() call is invisible in code review and
// shows up only as silently icon-less navigation.
//
// Called at module scope, before mountApp(), so the registry is populated
// before any component renders.
registerIcons(appIcons)

/**
 * Bootstrap the Planninq SPA.
 *
 * @return {void}
 */
function mountApp() {
	const app = createApp(App)

	// Vue 3 has no global `Vue.mixin` / `Vue.use` — both are per-app instance.
	app.mixin({ methods: { t, n } })
	app.use(pinia)
	app.use(router)

	// ⚠️ The host element is `#planninq-app`, NOT `#content`.
	//
	// Vue 2's `$mount()` REPLACED the matched element; Vue 3's `mount()`
	// renders INSIDE it. The old `<div id="content">` in templates/index.php
	// duplicates Nextcloud's own `layout.user.php` wrapper — under Vue 2 the
	// duplication was invisible because the app replaced core's div, but under
	// Vue 3 the app would render *inside* core's `#content` and the NcContent
	// layout breaks. Renaming the host element sidesteps the question of which
	// div wins entirely.
	app.mount('#planninq-app')

	// Initialize stores after mount.
	initializeStores()
}

// Load translations first so `t()` resolves real strings on first render — but
// mount either way.
//
// `loadTranslations` only invokes its callback when the fetch succeeds; on a
// 404 (any locale for which `l10n/<lang>.json` was never generated) it REJECTS
// and the callback never runs. Mounting inside the callback therefore leaves a
// permanently blank page for those users, with only a network 404 to show for
// it. `.finally()` runs the mount exactly once on both paths.
loadTranslations('planninq')
	.catch((error) => {
		console.warn('[planninq] translations could not be loaded; falling back to source strings', error)
	})
	.finally(mountApp)
