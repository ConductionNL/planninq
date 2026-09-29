/**
 * Vitest unit tests for the case bridge (integration-case-bridge): the
 * projects leaf scoped to a case, the "New project" link from a case, the
 * New project dialog opened and prefilled from that link, and when the owner
 * is offered "Hand over to case".
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { guardRows, isCaseHost, scopeParams } from '../../src/integrations/projectScope.js'
import { creationPrefill, handoverOffered, newProjectQuery } from '../../src/utils/caseBridge.js'

const CASE_HOST = { register: 'dossiq', schema: 'case' }

describe('the projects leaf on a case (scenario: the case page lists its projects)', () => {
	it('recognises Dossiq\'s case as the host', () => {
		expect(isCaseHost(CASE_HOST)).toBe(true)
		expect(isCaseHost({ register: 'pipelinq', schema: 'client' })).toBe(false)
		expect(isCaseHost(undefined)).toBe(false)
	})

	it('asks for the projects linked to the case, and keeps only those', () => {
		expect(scopeParams('detail-page', 'case-1', CASE_HOST)).toEqual({ caseReference: 'case-1' })
		const rows = [
			{ id: 'a', caseReference: 'case-1', client: 'case-1' },
			{ id: 'b', caseReference: 'case-2' },
			{ id: 'c', client: 'case-1' },
		]
		expect(guardRows(rows, 'detail-page', 'case-1', CASE_HOST).map((row) => row.id)).toEqual(['a'])
	})

	it('keeps the client scope for any other host', () => {
		expect(scopeParams('detail-page', 'client-7', { schema: 'client' })).toEqual({ client: 'client-7' })
		expect(scopeParams('detail-page', 'client-7')).toEqual({ client: 'client-7' })
		expect(guardRows([{ id: 'c', client: 'client-7' }], 'detail-page', 'client-7').map((row) => row.id)).toEqual(['c'])
	})
})

describe('newProjectQuery (scenario: a case handler starts a project from the case)', () => {
	it('links the case and its title from a case page', () => {
		expect(newProjectQuery('case-1', CASE_HOST, 'Zaak 12')).toEqual({ new: '1', case: 'case-1', title: 'Zaak 12' })
		expect(newProjectQuery('case-1', CASE_HOST, '')).toEqual({ new: '1', case: 'case-1' })
	})

	it('links the client from any other host', () => {
		expect(newProjectQuery('client-7', { schema: 'client' }, '')).toEqual({ new: '1', client: 'client-7' })
	})
})

describe('creationPrefill', () => {
	it('opens the dialog prefilled from ?new=1', () => {
		expect(creationPrefill({ new: '1', case: 'case-1', title: 'Zaak 12' })).toEqual({ caseReference: 'case-1', title: 'Zaak 12' })
		expect(creationPrefill({ new: '1', client: 'client-7' })).toEqual({ client: 'client-7' })
		expect(creationPrefill({ new: '1' })).toEqual({})
	})

	it('does nothing without new=1', () => {
		expect(creationPrefill({ case: 'case-1' })).toBeNull()
		expect(creationPrefill(undefined)).toBeNull()
	})
})

describe('handoverOffered (scenario: the owner hands the project over)', () => {
	const project = { owner: 'olga', caseReference: 'case-1' }

	it('is offered to the owner of a linked project when the case app is there', () => {
		expect(handoverOffered(project, { uid: 'olga' }, true)).toBe(true)
		expect(handoverOffered(project, { uid: 'root', isAdmin: true }, true)).toBe(true)
	})

	it('is hidden without the case app, without a case link, or for anyone else', () => {
		expect(handoverOffered(project, { uid: 'olga' }, false)).toBe(false)
		expect(handoverOffered({ owner: 'olga' }, { uid: 'olga' }, true)).toBe(false)
		expect(handoverOffered(project, { uid: 'mo' }, true)).toBe(false)
		expect(handoverOffered(project, null, true)).toBe(false)
	})
})
