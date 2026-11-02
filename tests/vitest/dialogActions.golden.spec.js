/**
 * Golden test for the Cancel and Save actions of the edit dialogs.
 *
 * Renders each dialog on the server with its package components stubbed, in
 * four states (idle, saving, ready to save, and saving while ready), and compares the HTML, what
 * clicking each button does, and the hash of the dialog's styles with the
 * fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { loadSfc } from './support/sfc.js'
import { renderScene } from './support/ssr.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'dialogActions.golden.json')

const PROJECT = { id: 'p1', title: 'Project', members: [], columns: [] }
const COLUMN = { id: 'c1', title: 'Doing', order: 1, automation: [] }

/**
 * Per dialog: the props it renders with, the handlers to record, and the
 * data and computed values that make it ready to save.
 */
const DIALOGS = {
	BoardFilterSaveDialog: { props: { filter: {}, projectId: 'p1' }, spy: ['save'], ready: { computed: { nameError: '' } } },
	ColumnEditDialog: { props: { projectId: 'p1' }, spy: ['save'], ready: { computed: { isValid: true } } },
	ColumnRulesDialog: { props: { column: COLUMN, project: PROJECT }, spy: ['save'], ready: { computed: { isValid: true } } },
	FinanceLineDialog: {
		props: { projectId: 'p1', line: { id: 'f1', kind: 'cost', amount: 10 } },
		spy: ['save', 'remove'],
		ready: { computed: { validAmount: true }, data: { category: { id: 'travel', label: 'Travel' } } },
	},
	LabelEditDialog: { props: {}, spy: ['save'], ready: { computed: { isValid: true } } },
	LogEntryEditDialog: { props: { projectId: 'p1' }, spy: ['save'], ready: { computed: { isValid: true } } },
	PhaseCloseDialog: { props: { phase: { id: 'ph1', title: 'Build' } }, spy: ['close'], ready: { data: { uploading: false } } },
	ProjectsViewEditDialog: { props: { projects: [] }, spy: ['save'], ready: {} },
	ReleaseEditDialog: { props: { projectId: 'p1' }, spy: ['save'], ready: { data: { title: 'v1' } } },
	ReportBuilderDialog: { props: {}, spy: ['save'], ready: { computed: { valid: true } } },
	RiskEditDialog: { props: { projectId: 'p1' }, spy: ['save'], ready: { data: { title: 'Risk' } } },
	TaskFormDialog: { props: { projectId: 'p1', project: PROJECT, column: COLUMN }, spy: ['save'], ready: { data: { draft: { title: 'Task' } } } },
	TimetableWishDialog: { props: { show: true, confirm: '<function>', close: '<function>' }, spy: ['save'], ready: {} },
}

/**
 * Render one dialog in its four states.
 *
 * @param {string} name - the dialog's file name without .vue
 * @return {Promise<object>} the style hash, and per state the HTML and click effects
 */
async function recordDialog(name) {
	const { props, spy, ready } = DIALOGS[name]
	const url = new URL(`../../src/dialogs/${name}.vue`, import.meta.url)
	return {
		styleHash: loadSfc(url).styleHash,
		idle: await renderScene(url, { props, spy, data: { saving: false } }),
		saving: await renderScene(url, { props, spy, data: { saving: true } }),
		ready: await renderScene(url, { props, spy, computed: ready.computed, data: { ...ready.data, saving: false } }),
		savingReady: await renderScene(url, { props, spy, computed: ready.computed, data: { ...ready.data, saving: true } }),
	}
}

describe('dialog actions golden', () => {
	it('matches the recording', async () => {
		const recorded = {}
		for (const name of Object.keys(DIALOGS)) {
			recorded[name] = await recordDialog(name)
		}
		const actual = JSON.stringify(recorded, null, 2) + '\n'
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.mkdirSync(path.dirname(FIXTURE), { recursive: true })
			fs.writeFileSync(FIXTURE, actual)
		}
		expect(actual).toBe(fs.readFileSync(FIXTURE, 'utf8'))
	}, 60000)
})
