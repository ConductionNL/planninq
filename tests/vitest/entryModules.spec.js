/**
 * The bundle entry modules: the SPA bootstrap (main.js), the admin settings
 * bootstrap (settings.js), the leaves-only bundle (leaves.js) and the shared
 * pinia instance. Vue, vue-router, the component library and the Nextcloud
 * helpers are faked; the manifest is the real bundled one.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md
 */
import { readFileSync } from 'node:fs'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const fake = vi.hoisted(() => {
	const state = {}
	state.reset = () => {
		state.calls = []
		state.translations = Promise.resolve()
		state.registerTranslationsFails = false
		state.apps = []
		state.routerOptions = null
	}
	state.reset()
	return state
})

vi.mock('@conduction/nextcloud-vue', () => ({
	buildManifest: (base, fragments, layout) => {
		fake.calls.push(['buildManifest', fragments, layout])
		return { ...base, built: true }
	},
	CnPageRenderer: { name: 'CnPageRenderer' },
	defaultPageTypes: { index: 'IndexPage' },
	registerBuiltinDashboardWidgets: () => fake.calls.push(['registerBuiltinDashboardWidgets']),
	registerDashboardWidget: (type, entry) => fake.calls.push(['registerDashboardWidget', type, entry]),
	registerIcons: (icons) => fake.calls.push(['registerIcons', icons]),
	registerTranslations: () => {
		fake.calls.push(['registerTranslations'])
		if (fake.registerTranslationsFails) {
			throw new Error('no catalogue')
		}
	},
}))
vi.mock('@nextcloud/l10n', () => ({
	loadTranslations: (app) => {
		fake.calls.push(['loadTranslations', app])
		return fake.translations
	},
	translate: (app, text) => text,
	translatePlural: (app, one, more, count) => (count === 1 ? one : more),
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('vue', () => ({
	createApp: (component, props) => {
		const app = {
			component,
			props,
			mixins: [],
			plugins: [],
			mixin(mixin) {
				this.mixins.push(mixin)
				return this
			},
			use(plugin) {
				this.plugins.push(plugin)
				return this
			},
			mount(selector) {
				fake.calls.push(['mount', selector])
			},
		}
		fake.apps.push(app)
		return app
	},
}))
vi.mock('vue-router', () => ({
	createWebHistory: (base) => ({ base }),
	createRouter: (options) => {
		fake.routerOptions = options
		return { router: true }
	},
}))
vi.mock('pinia', () => ({ createPinia: () => ({ pinia: true }) }))
vi.mock('../../src/App.vue', () => ({ default: { name: 'App' } }))
vi.mock('../../src/views/settings/AdminRoot.vue', () => ({ default: { name: 'AdminRoot' } }))
vi.mock('../../src/components/DashboardPanels.vue', () => ({ default: { name: 'DashboardPanels' } }))
vi.mock('../../src/icons.js', () => ({ default: { Folder: 'FolderIcon' } }))
vi.mock('../../src/registry.js', () => ({ default: { Boards: { kind: 'page', component: 'Boards' } } }))
vi.mock('../../src/integrations/registerProjectsLeaf.js', () => ({ registerProjectsLeaf: () => fake.calls.push(['registerProjectsLeaf']) }))
vi.mock('../../src/store/store.js', () => ({ initializeStores: () => fake.calls.push(['initializeStores']) }))
vi.mock('@conduction/nextcloud-vue/css/index.css', () => ({}))
vi.mock('gridstack/dist/gridstack.min.css', () => ({}))
vi.mock('../../src/assets/app.css', () => ({}))

const MANIFEST = JSON.parse(readFileSync(new URL('../../src/manifest.json', import.meta.url), 'utf8'))

/**
 * Let the translations promise chain settle.
 *
 * @return {Promise<void>}
 */
async function settle() {
	for (let i = 0; i < 5; i++) {
		await Promise.resolve()
	}
}

/**
 * The names of the recorded calls, in order.
 *
 * @return {Array<string>}
 */
function callNames() {
	return fake.calls.map((call) => call[0])
}

describe('bundle entry modules', () => {
	beforeEach(() => {
		vi.resetModules()
		fake.reset()
		globalThis.window = { location: { pathname: '/apps/planninq/projects' } }
		vi.spyOn(console, 'warn').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.window
	})

	describe('main.js', () => {
		it('registers icons, widgets, the leaf and translations before mounting the app', async () => {
			await import('../../src/main.js')
			await settle()

			expect(callNames()).toEqual([
				'registerIcons',
				'registerBuiltinDashboardWidgets',
				'registerProjectsLeaf',
				'registerDashboardWidget',
				'registerTranslations',
				'buildManifest',
				'loadTranslations',
				'mount',
				'initializeStores',
			])
			expect(fake.calls[0][1]).toEqual({ Folder: 'FolderIcon' })
			expect(fake.calls[3]).toEqual(['registerDashboardWidget', 'project-panels', {
				renderer: { name: 'DashboardPanels' },
				defaultContent: {},
				displayName: 'Project panels',
				icon: 'FolderOutline',
				card: true,
			}])
			expect(fake.calls[5]).toEqual(['buildManifest', [], {}])
			expect(fake.calls[6]).toEqual(['loadTranslations', 'planninq'])
			expect(fake.calls[7]).toEqual(['mount', '#planninq-app'])
		})

		it('builds one route per manifest page in manifest order, plus the catch-all', async () => {
			await import('../../src/main.js')

			const routes = fake.routerOptions.routes
			expect(routes).toHaveLength(MANIFEST.pages.length + 1)
			MANIFEST.pages.forEach((page, index) => {
				expect(routes[index].name).toBe(page.id)
				expect(routes[index].path).toBe(page.route)
				expect(routes[index].props).toBe(page.route.includes(':'))
				expect(routes[index].component).toEqual({ name: 'CnPageRenderer' })
			})
			expect(routes.some((route) => route.props === true)).toBe(true)
			const { CnPageRenderer } = await import('@conduction/nextcloud-vue')
			expect(routes[0].component).not.toBe(CnPageRenderer)
			expect(new Set(routes.slice(0, -1).map((route) => route.component)).size).toBe(1)
			expect(routes.at(-1)).toEqual({ path: '/:pathMatch(.*)*', redirect: '/' })
		})

		it('takes the router base from the URL the visitor arrived on', async () => {
			globalThis.window.location.pathname = '/index.php/apps/planninq/projects/p1'
			await import('../../src/main.js')
			expect(fake.routerOptions.history).toEqual({ base: '/index.php/apps/planninq' })

			vi.resetModules()
			globalThis.window.location.pathname = '/apps/planninq'
			await import('../../src/main.js')
			expect(fake.routerOptions.history).toEqual({ base: '/apps/planninq' })
		})

		it('falls back to the configured app URL off the app\'s own path', async () => {
			globalThis.window.location.pathname = '/apps/planninqx/elsewhere'
			await import('../../src/main.js')
			expect(fake.routerOptions.history).toEqual({ base: '/index.php/apps/planninq' })
		})

		it('mounts the app with the built manifest, copies of the registries, the helpers, pinia and the router', async () => {
			await import('../../src/main.js')
			await settle()

			expect(fake.apps).toHaveLength(1)
			const [app] = fake.apps
			expect(app.component).toEqual({ name: 'App' })
			expect(app.props.manifest).toMatchObject({ built: true, pages: MANIFEST.pages })
			expect(app.props.registry).toEqual({ Boards: { kind: 'page', component: 'Boards' } })
			expect(app.props.pageTypes).toEqual({ index: 'IndexPage' })
			const { default: registry } = await import('../../src/registry.js')
			expect(app.props.registry).not.toBe(registry)
			expect(app.mixins[0].methods.t('planninq', 'Projects')).toBe('Projects')
			expect(app.mixins[0].methods.n('planninq', 'task', 'tasks', 2)).toBe('tasks')
			expect(app.plugins).toEqual([{ pinia: true }, { router: true }])
		})

		it('still mounts, once, when translations or the library catalogue fail', async () => {
			fake.translations = Promise.reject(new Error('404'))
			fake.registerTranslationsFails = true
			await import('../../src/main.js')
			await settle()

			expect(callNames().filter((name) => name === 'mount')).toHaveLength(1)
			expect(callNames().at(-1)).toBe('initializeStores')
			expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('registerTranslations failed'), expect.any(Error))
			expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('translations could not be loaded'), expect.any(Error))
		})
	})

	describe('settings.js', () => {
		it('mounts the admin panel on its own host element after the translations', async () => {
			await import('../../src/settings.js')
			await settle()

			expect(callNames()).toEqual(['loadTranslations', 'mount'])
			expect(fake.calls[1]).toEqual(['mount', '#planninq-settings'])
			const [app] = fake.apps
			expect(app.component).toEqual({ name: 'AdminRoot' })
			expect(app.props).toBeUndefined()
			expect(app.plugins).toEqual([{ pinia: true }])
			expect(app.mixins[0].methods.n('planninq', 'label', 'labels', 1)).toBe('label')
		})

		it('still mounts when the translations fail to load', async () => {
			fake.translations = Promise.reject(new Error('404'))
			await import('../../src/settings.js')
			await settle()

			expect(callNames()).toEqual(['loadTranslations', 'mount'])
			expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('translations could not be loaded'), expect.any(Error))
		})
	})

	describe('leaves.js', () => {
		it('registers the projects leaf before asking for translations', async () => {
			await import('../../src/leaves.js')
			await settle()

			expect(fake.calls).toEqual([['registerProjectsLeaf'], ['loadTranslations', 'planninq']])
			expect(fake.apps).toEqual([])
		})

		it('swallows a missing catalogue without a warning', async () => {
			const unhandled = vi.fn()
			process.on('unhandledRejection', unhandled)
			fake.translations = Promise.reject(new Error('404'))
			await import('../../src/leaves.js')
			await settle()
			await new Promise((resolve) => setTimeout(resolve, 0))
			process.off('unhandledRejection', unhandled)

			expect(unhandled).not.toHaveBeenCalled()
			expect(console.warn).not.toHaveBeenCalled()
			expect(callNames()).toEqual(['registerProjectsLeaf', 'loadTranslations'])
		})
	})

	it('pinia.js shares one pinia instance', async () => {
		const first = (await import('../../src/pinia.js')).default
		const again = (await import('../../src/pinia.js')).default
		expect(first).toEqual({ pinia: true })
		expect(again).toBe(first)
	})
})
