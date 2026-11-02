/**
 * Render a single-file component to HTML on the server, with every package
 * component replaced by a stub that prints its attributes and slots, and
 * record what clicking each button does.
 *
 * Relative .vue imports render for real, so a template that moves markup into
 * a child component renders the same HTML as before. Comments (fragment and
 * v-if markers) are dropped, and scoped-style attributes are not rendered.
 * Each render gets a fresh Pinia, so stores start empty.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { compile } from '@vue/compiler-dom'
import { createPinia } from 'pinia'
import * as Vue from 'vue'
import { renderToString } from 'vue/server-renderer'
import { loadSfcWithImports } from './sfc.js'

const REGISTRY = Symbol('stub registry')
const FUNCTION = '<function>'

/**
 * Load a component file as a renderable component: its options with a render
 * function compiled from its template, and every component it could not
 * import replaced by a stub.
 *
 * @param {URL} url - the .vue file
 * @return {Promise<object>} the component options
 */
export async function loadComponent(url) {
	const { options, template } = await loadSfcWithImports(url, { vue: loadComponent })
	const { code } = compile(template, { mode: 'function', prefixIdentifiers: true })
	const components = Object.fromEntries(Object.entries(options.components || {})
		.map(([name, component]) => [name, component || stub(name)]))
	return { ...options, components, render: new Function('Vue', code)(Vue) }
}

/**
 * The attributes a stub prints: listeners as one data-on list, every other
 * value as text (JSON for anything but a string) under a ':' prefix, so a
 * false boolean does not print as a bare HTML boolean attribute. In name order.
 *
 * @param {object} attrs - the stub's attributes
 * @return {object} the element attributes
 */
function printedAttrs(attrs) {
	const printed = {}
	const listeners = []
	for (const key of Object.keys(attrs).sort()) {
		if (typeof attrs[key] === 'function') {
			listeners.push(key)
		} else {
			printed[`:${key}`] = typeof attrs[key] === 'string' ? attrs[key] : JSON.stringify(attrs[key])
		}
	}
	if (listeners.length > 0) {
		printed['data-on'] = listeners.join(' ')
	}
	return printed
}

/**
 * A component that renders an element named after it, printing its
 * attributes and each slot in a stub-slot element, and registers its
 * attributes so the scene can fire its listeners afterwards.
 *
 * @param {string} name - the component's registered name
 * @return {object} the stub component
 */
function stub(name) {
	const tag = name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase()
	return {
		name,
		inheritAttrs: false,
		setup(_, { attrs, slots }) {
			const registry = Vue.inject(REGISTRY)
			return () => {
				registry.push({ name, attrs: { ...attrs } })
				const children = Object.keys(slots).sort().map((slot) => {
					let content
					try {
						content = slots[slot]({})
					} catch (error) {
						content = `[slot error: ${error.message}]`
					}
					return Vue.h('stub-slot', { name: slot }, content)
				})
				return Vue.h(tag, printedAttrs(attrs), children)
			}
		},
	}
}

/**
 * The component options for one scene: data and computed values overridden, and the named methods replaced by recorders.
 *
 * @param {object} options - the loaded component
 * @param {{scene: object, log: Array}} run - the scene and the log its recorders write to
 * @return {object} the options to render
 */
function sceneOptions(options, run) {
	const { scene, log } = run
	const computed = { ...(options.computed || {}) }
	for (const [name, value] of Object.entries(scene.computed || {})) {
		computed[name] = () => value
	}
	const methods = { ...(options.methods || {}) }
	for (const name of scene.spy || []) {
		methods[name] = (...args) => log.push(`${name}(${args.map((arg) => JSON.stringify(arg)).join(', ')})`)
	}
	const data = options.data
	return {
		...options,
		computed,
		methods,
		data() {
			return { ...(data ? data.call(this, this) : {}), ...(scene.data || {}) }
		},
	}
}

/**
 * The root props for one scene: the scene's props, with each '<function>'
 * value and each emit declared by the component or its mixins turned into a
 * recorder.
 *
 * @param {object} options - the loaded component
 * @param {{scene: object, log: Array}} run - the scene and the log its recorders write to
 * @return {object} the props
 */
function sceneProps(options, run) {
	const { scene, log } = run
	const props = {}
	for (const [name, value] of Object.entries(scene.props || {})) {
		props[name] = value === FUNCTION ? (...args) => log.push(`prop ${name}(${args.length})`) : value
	}
	const emits = [...(options.mixins || []).flatMap((mixin) => mixin.emits || []), ...(options.emits || [])]
	for (const event of new Set(emits)) {
		props[`on${event[0].toUpperCase()}${event.slice(1)}`] = (...args) => log.push(`emit ${event}(${JSON.stringify(args)})`)
	}
	return props
}

/**
 * Click every rendered button stub in order and note what each click did.
 *
 * @param {Array} registry - the rendered stubs
 * @param {Array} log - the recorders' log
 * @return {Array<string>} one line per button: its label attributes and the effects
 */
function clickButtons(registry, log) {
	return registry.filter((entry) => entry.name === 'NcButton').map((entry, index) => {
		const start = log.length
		const onClick = entry.attrs.onClick
		const handlers = Array.isArray(onClick) ? onClick : [onClick].filter(Boolean)
		for (const handler of handlers) {
			const result = handler('click-event')
			if (result && typeof result.catch === 'function') {
				result.catch(() => {})
			}
		}
		return `button ${index}: ${log.slice(start).join('; ') || 'nothing'}`
	})
}

/**
 * Close every rendered dialog stub the way NcDialog does, and note what each
 * close did.
 *
 * @param {Array} registry - the rendered stubs
 * @param {Array} log - the recorders' log
 * @return {Array<string>} one line per dialog: the effects of its closing event
 */
function closeDialogs(registry, log) {
	return registry.filter((entry) => entry.name === 'NcDialog').map((entry, index) => {
		const start = log.length
		const onClosing = entry.attrs.onClosing
		for (const handler of Array.isArray(onClosing) ? onClosing : [onClosing].filter(Boolean)) {
			handler()
		}
		return `dialog ${index}: ${log.slice(start).join('; ') || 'nothing'}`
	})
}

/**
 * Render a component file in one scene, click each of its buttons, then close each of its dialogs.
 *
 * A scene has props (a '<function>' value becomes a recorder), data and
 * computed overrides, and spy: method names replaced by recorders.
 *
 * @param {URL} url - the .vue file
 * @param {object} scene - props, data, computed and spy
 * @return {Promise<{html: string, clicks: Array<string>, closing?: Array<string>}>} the rendered HTML, the click effects and the closing effects
 */
export async function renderScene(url, scene) {
	const options = await loadComponent(url)
	const log = []
	const registry = []
	const app = Vue.createSSRApp(sceneOptions(options, { scene, log }), sceneProps(options, { scene, log }))
	app.mixin({ methods: { t: (_app, text) => text, n: (_app, one, many, count) => (count === 1 ? one : many) } })
	app.use(createPinia())
	app.provide(REGISTRY, registry)
	const html = (await renderToString(app)).replace(/<!--[\s\S]*?-->/g, '')
	const clicks = clickButtons(registry, log)
	const closing = closeDialogs(registry, log)
	return closing.length > 0 ? { html, clicks, closing } : { html, clicks }
}
