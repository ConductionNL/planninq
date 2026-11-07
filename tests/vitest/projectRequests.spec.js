/**
 * Vitest unit tests for project requests (projects-lifecycle-policy, section 3):
 * the two steps of the request form, the payload it sends (validated against
 * the real project schema), the banner a requested or rejected project shows,
 * and the review buttons.
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { requestBanner, requestPayload, requestStepValid, reviewButtons } from '../../src/utils/projectRequests.js'

const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))

describe('the request form (scenario: a requester fills in the guided form)', () => {
	const form = { title: ' Portaal ', description: 'A portal for residents', requestReason: ' Residents ask for it ', startDate: '2027-01-04' }

	it('asks for a title first and a reason second', () => {
		expect(requestStepValid(1, { title: '' })).toBe(false)
		expect(requestStepValid(1, form)).toBe(true)
		expect(requestStepValid(2, { ...form, requestReason: ' ' })).toBe(false)
		expect(requestStepValid(2, form)).toBe(true)
	})

	it('sends what the project schema accepts', () => {
		const payload = requestPayload(form)
		expect(payload).toEqual({ title: 'Portaal', description: 'A portal for residents', requestReason: 'Residents ask for it', startDate: '2027-01-04' })
		expect(requestPayload({ title: 'X', requestReason: 'Y', description: '', startDate: '' })).toEqual({ title: 'X', requestReason: 'Y' })

		const ajv = new Ajv({ strict: false })
		addFormats(ajv)
		const { title, description, requestReason, startDate } = register.components.schemas.project.properties
		const validate = ajv.compile({ type: 'object', properties: { title, description, requestReason, startDate } })
		expect(validate(payload)).toBe(true)
	})
})

describe('requestBanner', () => {
	it('shows a waiting or rejected banner instead of the board', () => {
		expect(requestBanner({ status: 'requested' })).toEqual({ kind: 'waiting', note: '' })
		expect(requestBanner({ status: 'rejected', reviewNote: 'Fits in the existing portal project' })).toEqual({ kind: 'rejected', note: 'Fits in the existing portal project' })
		expect(requestBanner({ status: 'active' })).toBeNull()
	})
})

describe('reviewButtons (scenario: a reviewer approves a request)', () => {
	it('follows the actions OpenRegister offers', () => {
		expect(reviewButtons(['approve', 'reject'])).toEqual({ approve: true, reject: true })
		expect(reviewButtons([])).toEqual({ approve: false, reject: false })
		expect(reviewButtons(null)).toEqual({ approve: false, reject: false })
	})

	it('names actions the lifecycle declares', () => {
		const transitions = register.components.schemas.project['x-openregister-lifecycle'].transitions
		expect(transitions.approve.from).toEqual(['requested'])
		expect(transitions.reject.inputs).toEqual([{ field: 'reviewNote', required: true }])
	})
})
