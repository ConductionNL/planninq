/**
 * Reading a pasted code forge address (integration-code-forge-links task 2.1).
 *
 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.1
 */
import { describe, expect, it } from 'vitest'
import { forgeLinkRefusal, mayRemoveForgeLink, parseForgeUrl, sortForgeLinks } from '../../src/utils/forgeUrl.js'

describe('parseForgeUrl', () => {
	it('GitHub pull request', () => {
		expect(parseForgeUrl('https://github.com/acme/portal/pull/42')).toMatchObject({
			kind: 'mergeRequest',
			repository: 'acme/portal',
			reference: '#42',
		})
	})

	it('GitLab merge request', () => {
		expect(parseForgeUrl(' https://gitlab.example.org/acme/portal/-/merge_requests/42 ')).toMatchObject({
			kind: 'mergeRequest',
			repository: 'acme/portal',
			reference: '!42',
			url: 'https://gitlab.example.org/acme/portal/-/merge_requests/42',
		})
		expect(parseForgeUrl('https://gitlab.example.org/team/sub/portal/-/issues/7')).toMatchObject({ kind: 'issue', repository: 'team/sub/portal', reference: '#7' })
	})

	it('Gitea commit', () => {
		expect(parseForgeUrl('https://codeberg.org/acme/portal/commit/3f2a9c1d8e7b6a5f4c3d2e1f0a9b8c7d6e5f4a3b')).toMatchObject({
			kind: 'commit',
			repository: 'acme/portal',
			reference: '3f2a9c1',
		})
		expect(parseForgeUrl('https://codeberg.org/acme/portal/pulls/9')).toMatchObject({ kind: 'mergeRequest', reference: '#9' })
		expect(parseForgeUrl('https://codeberg.org/acme/portal/src/branch/VC-12-printer')).toMatchObject({ kind: 'branch', reference: 'VC-12-printer' })
	})

	it('unknown host is a plain link', () => {
		expect(parseForgeUrl('https://example.org/notes/printer')).toMatchObject({ kind: 'link', repository: '', externalId: '' })
		expect(parseForgeUrl('not a link')).toBeNull()
		expect(parseForgeUrl('javascript:alert(1)')).toBeNull()
	})

	it('the same item pasted twice gets the same forge id', () => {
		const a = parseForgeUrl('https://github.com/acme/portal/pull/42')
		const b = parseForgeUrl('https://github.com/acme/portal/pull/42/files')
		expect(a.externalId).toBe(b.externalId)
		expect(a.externalId).not.toBe('')
	})
})

describe('forge link list', () => {
	it('newest first', () => {
		const links = [{ id: 'a', occurredAt: '2026-09-01T10:00:00Z' }, { id: 'b', occurredAt: '2026-09-30T10:00:00Z' }, { id: 'c' }]
		expect(sortForgeLinks(links).map((l) => l.id)).toEqual(['b', 'a', 'c'])
	})

	it('a member removes only a link added by hand, an admin any', () => {
		expect(mayRemoveForgeLink({ source: 'manual' }, false)).toBe(true)
		expect(mayRemoveForgeLink({ source: 'integriq' }, false)).toBe(false)
		expect(mayRemoveForgeLink({ source: 'integriq' }, true)).toBe(true)
	})

	it('reads the server refusal', () => {
		expect(forgeLinkRefusal({ code: 'planninq-forge-link-duplicate' })).toBe('duplicate')
		expect(forgeLinkRefusal({ errors: { code: 'planninq-forge-link-no-task' } })).toBe('no-task')
		expect(forgeLinkRefusal({})).toBe('failed')
	})
})
