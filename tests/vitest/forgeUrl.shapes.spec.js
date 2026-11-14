/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * The forge address shapes forgeUrl.spec.js does not pin: a GitLab commit and
 * branch, a GitHub issue, and addresses that are not http(s).
 *
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.1
 */
import { describe, expect, it } from 'vitest'
import { parseForgeUrl } from '../../src/utils/forgeUrl.js'

describe('parseForgeUrl shapes', () => {
	it('a GitLab commit keeps the short hash', () => {
		expect(parseForgeUrl('https://gitlab.example.org/team/portal/-/commit/0123456789abcdef0123')).toEqual({
			kind: 'commit',
			url: 'https://gitlab.example.org/team/portal/-/commit/0123456789abcdef0123',
			repository: 'team/portal',
			reference: '0123456',
			externalId: 'gitlab.example.org:team/portal:commit:0123456',
		})
	})

	it('a GitLab branch is decoded and a trailing slash dropped', () => {
		expect(parseForgeUrl('https://gitlab.example.org/team/portal/-/tree/feature%2Flogin/')).toMatchObject({
			kind: 'branch',
			repository: 'team/portal',
			reference: 'feature/login',
			externalId: 'gitlab.example.org:team/portal:branch:feature/login',
		})
	})

	it('a GitHub issue reads as #number', () => {
		expect(parseForgeUrl('https://github.com/acme/portal/issues/9')).toMatchObject({
			kind: 'issue',
			repository: 'acme/portal',
			reference: '#9',
			externalId: 'github.com:acme/portal:issue:#9',
		})
	})

	it('text that is not an http(s) address is no link at all', () => {
		expect(parseForgeUrl('not a url')).toBeNull()
		expect(parseForgeUrl('ftp://github.com/acme/portal/issues/9')).toBeNull()
		expect(parseForgeUrl(null)).toBeNull()
	})
})
