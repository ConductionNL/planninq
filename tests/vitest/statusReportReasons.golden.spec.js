/**
 * Golden test for the suggestion sentences of src/dialogs/StatusReportDialog.vue:
 * suggestionText() and reasonText() over every suggestion code, recorded with
 * the arguments of every t() call, plus fingerprints of the compiled template
 * and the styles. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { loadSfc } from './support/sfc.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'statusReportReasons.golden.json')
const CODES = ['late', 'endPassed', 'onSchedule', 'highestRisk', 'noOpenRisks', 'overBudget', 'nearBudget', 'withinBudget', 'noBudget', 'noCosts', 'unknown', '', undefined, 'toString', '__proto__']
const STATUSES = [undefined, '', 'onTrack', 'atRisk', 'offTrack']

/**
 * A component instance stand-in whose t() records what it was passed.
 *
 * @param {object} methods - the component's methods
 * @param {object} suggestions - the suggestions per aspect
 * @return {{vm: object, calls: Array}} the instance and its t() calls
 */
function instance(methods, suggestions) {
	const calls = []
	const vm = {
		suggestions,
		t: (...args) => {
			calls.push(args)
			return `[${args[1]}]${args[2] ? JSON.stringify(args[2]) : ''}`
		},
	}
	for (const [name, method] of Object.entries(methods)) {
		vm[name] = method.bind(vm)
	}
	return { vm, calls }
}

describe('StatusReportDialog suggestion sentences golden', () => {
	it('matches the recorded fixture', () => {
		const { options, templateHash, styleHash } = loadSfc(new URL('../../src/dialogs/StatusReportDialog.vue', import.meta.url))
		const cases = []
		for (const code of CODES) {
			for (const count of [1, 0, 3, undefined]) {
				for (const status of STATUSES) {
					const suggestion = { code, count, status, title: 'Supplier', score: 12, percent: 87 }
					const { vm, calls } = instance(options.methods, { money: suggestion })
					cases.push({ code, count, status, reason: vm.reasonText(suggestion), text: vm.suggestionText('money'), calls })
				}
			}
		}
		const { vm } = instance(options.methods, {})
		cases.push({ missing: vm.suggestionText('time') })
		const actual = JSON.parse(JSON.stringify({ templateHash, styleHash, cases }))
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual) + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	})
})
