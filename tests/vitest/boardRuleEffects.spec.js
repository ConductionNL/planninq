/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for announcing what a column's rules did to a moved card
 * (ProjectBoard showRuleEffects): which fields are copied onto the card and
 * what the live announcement says.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const lookups = []
const { options } = await loadSfcWithImports(new URL('../../src/views/ProjectBoard.vue', import.meta.url), {
	modules: {
		'../utils/userNames.js': {
			displayNames: async (uids) => {
				lookups.push(uids)
				return { anna: 'Anna de Vries' }
			},
		},
	},
	vue: async () => ({}),
})

/**
 * A fake board with the component's methods and one sent card.
 *
 * @param {object} sent The card as the board sent it.
 * @return {object}
 */
function board(sent) {
	const vm = {
		tasks: [sent, { id: 'other', title: 'Other' }],
		rulesAnnouncement: 'before',
		labelsById: new Map([['l1', { title: 'Urgent' }]]),
		t: (app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (match, key) => String(vars[key])),
	}
	for (const [name, method] of Object.entries(options.methods)) {
		vm[name] = method.bind(vm)
	}
	return vm
}

const sent = { id: 't1', title: 'Card', assignedTo: '', sharedWith: [], priority: 'normal', labels: [] }

describe('ProjectBoard showRuleEffects', () => {
	it('copies the rule fields and announces assignee, priority and labels', async () => {
		lookups.length = 0
		const vm = board(sent)
		await vm.showRuleEffects('t1', { ...sent, assignedTo: 'anna', priority: 'high', labels: ['l1', 'gone'], sharedWith: ['bob'], title: 'Server title' })

		expect(vm.tasks[0]).toEqual({ ...sent, assignedTo: 'anna', priority: 'high', labels: ['l1', 'gone'], sharedWith: ['bob'] })
		expect(vm.tasks[1]).toEqual({ id: 'other', title: 'Other' })
		expect(lookups).toEqual([['anna']])
		expect(vm.rulesAnnouncement).toBe('Rules applied: assigned to Anna de Vries, priority High, label Urgent, label gone')
	})

	it('falls back to the user id when no name is known, and says when the assignee is removed', async () => {
		const unknown = board(sent)
		await unknown.showRuleEffects('t1', { ...sent, assignedTo: 'zed' })
		const removed = board({ ...sent, assignedTo: 'anna' })
		lookups.length = 0
		await removed.showRuleEffects('t1', { ...sent, assignedTo: '' })

		expect(unknown.rulesAnnouncement).toBe('Rules applied: assigned to zed')
		expect(removed.rulesAnnouncement).toBe('Rules applied: assignee removed')
		expect(lookups).toEqual([])
	})

	it('copies only the rule fields the server returned', async () => {
		const vm = board(sent)
		await vm.showRuleEffects('t1', { id: 't1', priority: 'low' })

		expect(vm.tasks[0]).toEqual({ ...sent, priority: 'low' })
		expect(vm.rulesAnnouncement).toBe('Rules applied: priority Low')
	})

	it('changes nothing when the rules did nothing or the card is not on the board', async () => {
		const quiet = board(sent)
		await quiet.showRuleEffects('t1', { ...sent })
		const missing = board(sent)
		await missing.showRuleEffects('nope', { ...sent, priority: 'high' })

		expect([quiet.tasks[0], quiet.rulesAnnouncement]).toEqual([sent, 'before'])
		expect([missing.tasks[0], missing.rulesAnnouncement]).toEqual([sent, 'before'])
	})
})
