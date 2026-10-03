import Ajv from 'ajv'
import addFormats from 'ajv-formats'
/**
 * Vitest tests for column rules (boards-column-automation).
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.2
 */
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	actionNeedsValue,
	columnRules,
	hasRuleEffects,
	RULE_ACTIONS,
	RULE_PRIORITIES,
	ruleEffects,
	ruleIsComplete,
	ruleProblem,
	rulesPayload,
} from '../../src/utils/columnAutomation.js'

const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
const automation = register.components.schemas.column.properties.automation
const ajv = new Ajv({ strict: false })
addFormats(ajv)
const validate = ajv.compile(automation)

describe('rulesPayload (scenario: the owner adds an assign rule to Review)', () => {
	it('keeps a value only where the action takes one, and the result passes the column schema', () => {
		const payload = rulesPayload([
			{ action: 'assignMover', value: 'stale' },
			{ action: 'setPriority', value: 'high' },
			{ action: 'assign', value: 'anna' },
			{ action: 'unassign' },
			{ action: 'addLabel', value: '9f1c7d2e-3b4a-4c5d-8e6f-7a8b9c0d1e2f' },
			{ action: 'closeTask' },
		])
		expect(payload).toEqual([
			{ action: 'assignMover' },
			{ action: 'setPriority', value: 'high' },
			{ action: 'assign', value: 'anna' },
			{ action: 'unassign' },
			{ action: 'addLabel', value: '9f1c7d2e-3b4a-4c5d-8e6f-7a8b9c0d1e2f' },
		])
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('control: the schema refuses an action outside the list', () => {
		expect(validate([{ action: 'closeTask' }])).toBe(false)
	})

	it('offers the same actions and priorities as the register', () => {
		expect(RULE_ACTIONS).toEqual(automation.items.properties.action.enum)
		expect(RULE_PRIORITIES).toEqual(register.components.schemas.task.properties.priority.enum)
	})
})

describe('ruleIsComplete and actionNeedsValue', () => {
	it('needs a value for priority, person and label', () => {
		expect(['setPriority', 'assign', 'addLabel'].every(actionNeedsValue)).toBe(true)
		expect(actionNeedsValue('assignMover')).toBe(false)
		expect(ruleIsComplete({ action: 'assign', value: '' })).toBe(false)
		expect(ruleIsComplete({ action: 'assign', value: 'ben' })).toBe(true)
		expect(ruleIsComplete({ action: 'unassign' })).toBe(true)
		expect(ruleIsComplete({ action: '' })).toBe(false)
	})
})

describe('ruleProblem (scenario: a rule for a former member is skipped)', () => {
	it('marks an assignee who left the project and a deleted label', () => {
		expect(ruleProblem({ action: 'assign', value: 'carl' }, ['anna', 'ben'], [])).toBe('formerMember')
		expect(ruleProblem({ action: 'assign', value: 'ben' }, ['anna', 'ben'], [])).toBeNull()
		expect(ruleProblem({ action: 'addLabel', value: 'l-gone' }, [], ['l-1'])).toBe('missingLabel')
		expect(ruleProblem({ action: 'assignMover' }, [], [])).toBeNull()
	})
})

describe('columnRules', () => {
	it('reads the stored rules, or none', () => {
		expect(columnRules({ automation: [{ action: 'unassign' }, null, { value: 'x' }] })).toEqual([{ action: 'unassign' }])
		expect(columnRules({})).toEqual([])
		expect(columnRules(null)).toEqual([])
	})
})

describe('ruleEffects (scenario: moving a card into Review assigns the mover)', () => {
	it('names the new assignee, priority and added labels', () => {
		const sent = { id: 't1', assignedTo: 'anna', priority: 'normal', labels: ['l-1'] }
		const effects = ruleEffects(sent, { ...sent, assignedTo: 'ben', priority: 'high', labels: ['l-1', 'l-2'] })
		expect(effects).toEqual({ assignedTo: 'ben', priority: 'high', labelsAdded: ['l-2'] })
		expect(hasRuleEffects(effects)).toBe(true)
	})

	it('reports nothing when the server stored what the board sent', () => {
		const sent = { id: 't1', assignedTo: 'anna', labels: [] }
		const effects = ruleEffects(sent, { ...sent, priority: 'normal' })
		expect(hasRuleEffects(effects)).toBe(false)
		expect(hasRuleEffects(ruleEffects(sent, null))).toBe(false)
	})

	it('reports an unassign as an empty assignee', () => {
		expect(ruleEffects({ assignedTo: 'anna' }, { assignedTo: '' })).toEqual({ assignedTo: '', labelsAdded: [] })
	})
})
