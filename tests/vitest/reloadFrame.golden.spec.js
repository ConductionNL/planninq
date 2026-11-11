/**
 * Golden test for the load frame of ProjectRoadmap, TaskForgeLinks,
 * ProjectFlow, ProjectsView and ReportPage: what runs when the component is
 * mounted and when the watched id changes, the names of every computed
 * property, method and watcher, plus fingerprints of each compiled template
 * and its styles. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec exclude Golden test of the shared load frame; each component's spec covers what it loads.
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'reloadFrame.golden.json')
const COMPONENTS = {
	'components/ProjectRoadmap': 'projectId',
	'components/TaskForgeLinks': 'task.id',
	'views/ProjectFlow': 'projectId',
	'views/ProjectsView': '$route.params.id',
	'views/ReportPage': '$route.params.id',
}

/**
 * The component's options of one kind, mixins first and its own entries last.
 *
 * @param {object} options - the component options
 * @param {string} kind - 'computed', 'methods', 'watch' or a lifecycle hook
 * @return {Array<object>} one entry per source
 */
function sources(options, kind) {
	return [...(options.mixins || []).map((mixin) => mixin[kind]), options[kind]].filter(Boolean)
}

/**
 * A stand-in component instance that records the calls a hook makes.
 *
 * @return {{vm: object, calls: Array<string>}} the instance and its calls
 */
function instance() {
	const calls = []
	const vm = new Proxy({}, {
		get: (target, name) => (typeof name === 'string' ? (...args) => calls.push(`${name}(${JSON.stringify(args)})`) : undefined),
	})
	return { vm, calls }
}

/**
 * Record the mounted hooks and the watchers of one key.
 *
 * @param {object} options - the component options
 * @param {string} key - the watched key
 * @return {object} the recorded frame
 */
function record(options, key) {
	const mounted = sources(options, 'mounted').map((hook) => {
		const { vm, calls } = instance()
		hook.call(vm)
		return calls
	})
	const watched = sources(options, 'watch').flatMap((map) => (key in map ? [].concat(map[key]) : [])).map((watcher) => {
		const { vm, calls } = instance()
		const handler = typeof watcher === 'function' ? watcher : watcher.handler
		handler.call(vm, 'new', 'old')
		return { immediate: watcher.immediate === true, deep: watcher.deep === true, calls }
	})
	const names = (kind) => [...new Set(sources(options, kind).flatMap(Object.keys))].sort()
	return { mounted, watched, computed: names('computed'), methods: names('methods'), watch: names('watch') }
}

describe('reload frame golden', () => {
	it('matches the recorded fixture', async () => {
		const actual = {}
		for (const [file, key] of Object.entries(COMPONENTS)) {
			const { options, templateHash, styleHash } = await loadSfcWithImports(new URL(`../../src/${file}.vue`, import.meta.url))
			actual[file] = JSON.parse(JSON.stringify({ templateHash, styleHash, ...record(options, key) }))
		}
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	}, 60000)
})
