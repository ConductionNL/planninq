/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Golden test for the project a project page shows (ProjectCalendar,
 * ProjectFinance, ProjectLog, ProjectOverview, ProjectRisks, ProjectStatus):
 * the project id read from the address, the watchers on it and the loads they
 * start, the names of every computed property, method and watcher, plus
 * fingerprints of each compiled template and its styles.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec exclude Golden test of the shared project route frame; each page's spec covers what it loads.
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'projectRoute.golden.json')
const VIEWS = ['ProjectCalendar', 'ProjectFinance', 'ProjectLog', 'ProjectOverview', 'ProjectRisks', 'ProjectStatus']
const PARAMS = [{}, { id: '' }, { id: 'p1' }, { id: 42 }, { id: 0 }, { id: null }, { id: ' p 2 ' }, { id: ['a', 'b'] }]

/**
 * The component's options of one kind, mixins first and its own entries last.
 *
 * @param {object} options - the component options
 * @param {string} kind - 'computed', 'methods' or 'watch'
 * @return {Array<object>} one entry map per source
 */
function sources(options, kind) {
	return [...(options.mixins || []).map((mixin) => mixin[kind] || {}), options[kind] || {}]
}

/**
 * Every watcher of one property, in the order the component applies them.
 *
 * @param {object} options - the component options
 * @param {string} name - the watched property
 * @return {Array<object>} the watcher definitions
 */
function watchers(options, name) {
	return sources(options, 'watch').flatMap((map) => (name in map ? [].concat(map[name]) : []))
}

/**
 * Record what one page reads from the address and what its watchers do.
 *
 * @param {object} options - the component options
 * @return {object} the recorded page
 */
function record(options) {
	const computed = Object.assign({}, ...sources(options, 'computed'))
	const ids = PARAMS.map((params) => computed.projectId.call({ $route: { params } }))
	const watched = watchers(options, 'projectId').map((watcher) => {
		const loads = []
		const vm = { load: (...args) => loads.push(args) }
		const handler = typeof watcher === 'function' ? watcher : watcher.handler
		handler.call(vm, 'new', 'old')
		return { immediate: watcher.immediate === true, deep: watcher.deep === true, loads }
	})
	const names = (kind) => [...new Set(sources(options, kind).flatMap(Object.keys))].sort()
	return { ids, watched, computed: names('computed'), methods: names('methods'), watch: names('watch') }
}

describe('project route golden', () => {
	it('matches the recorded fixture', async () => {
		const actual = {}
		for (const view of VIEWS) {
			const { options, templateHash, styleHash } = await loadSfcWithImports(new URL(`../../src/views/${view}.vue`, import.meta.url))
			actual[view] = JSON.parse(JSON.stringify({ templateHash, styleHash, ...record(options) }))
		}
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	}, 60000)
})
