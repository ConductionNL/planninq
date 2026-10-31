// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Read a pasted code forge address: what it points at, in which repository,
 * and its number or hash. GitHub, GitLab and Gitea (Forgejo, Codeberg) are
 * recognised by their path shapes, so a self-hosted GitLab or Gitea works
 * too; anything else is a plain link.
 *
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.1
 */

const PATTERNS = [
	// GitLab: /group/sub/repo/-/merge_requests/42, /-/commit/<sha>, /-/issues/7, /-/tree/<branch>
	{ re: /^\/(.+?)\/-\/merge_requests\/(\d+)/, kind: 'mergeRequest', ref: (m) => `!${m[2]}` },
	{ re: /^\/(.+?)\/-\/commits?\/([0-9a-f]{7,40})/i, kind: 'commit', ref: (m) => m[2].slice(0, 7) },
	{ re: /^\/(.+?)\/-\/issues\/(\d+)/, kind: 'issue', ref: (m) => `#${m[2]}` },
	{ re: /^\/(.+?)\/-\/tree\/(.+)$/, kind: 'branch', ref: (m) => decodeURIComponent(m[2]) },
	// GitHub /owner/repo/pull/42 and Gitea /owner/repo/pulls/42
	{ re: /^\/([^/]+\/[^/]+)\/pulls?\/(\d+)/, kind: 'mergeRequest', ref: (m) => `#${m[2]}` },
	{ re: /^\/([^/]+\/[^/]+)\/commits?\/([0-9a-f]{7,40})/i, kind: 'commit', ref: (m) => m[2].slice(0, 7) },
	{ re: /^\/([^/]+\/[^/]+)\/issues\/(\d+)/, kind: 'issue', ref: (m) => `#${m[2]}` },
	// GitHub /owner/repo/tree/<branch>, Gitea /owner/repo/src/branch/<branch>
	{ re: /^\/([^/]+\/[^/]+)\/(?:tree|src\/branch)\/(.+)$/, kind: 'branch', ref: (m) => decodeURIComponent(m[2]) },
]

/**
 * Parse a pasted address.
 *
 * @param {string} value The pasted text
 * @return {{kind: string, url: string, repository: string, reference: string, externalId: string}|null}
 *   null when the text is not an http(s) address
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.1
 */
export function parseForgeUrl(value) {
	let url
	try {
		url = new URL(String(value ?? '').trim())
	} catch {
		return null
	}
	if (url.protocol !== 'https:' && url.protocol !== 'http:') {
		return null
	}
	const path = url.pathname.replace(/\/+$/, '')
	for (const { re, kind, ref } of PATTERNS) {
		const m = path.match(re)
		if (m) {
			const reference = ref(m)
			return {
				kind,
				url: url.toString(),
				repository: m[1],
				reference,
				externalId: `${url.host}:${m[1]}:${kind}:${reference}`,
			}
		}
	}
	return { kind: 'link', url: url.toString(), repository: '', reference: '', externalId: '' }
}

/**
 * Newest first, by `occurredAt`, falling back to the creation date.
 *
 * @param {Array<object>} links The forge links of a task
 * @return {Array<object>} A sorted copy
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.1
 */
export function sortForgeLinks(links) {
	const at = (link) => Date.parse(link?.occurredAt || link?.['@self']?.created || '') || 0
	return [...(links || [])].sort((a, b) => at(b) - at(a))
}

/**
 * Whether the current user may remove a link: an admin always, a project
 * member only a link added by hand (an integration link would come back
 * with the next forge event).
 *
 * @param {object} link The link
 * @param {boolean} isAdmin Whether the viewer is an admin
 * @return {boolean}
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.1
 */
export function mayRemoveForgeLink(link, isAdmin) {
	return isAdmin === true || (link?.source ?? 'manual') === 'manual'
}

/**
 * Read a refused create: `duplicate` when the task already has the link,
 * `no-task` when the key or task does not resolve, else `failed`.
 *
 * @param {object} body The error body OpenRegister answered with
 * @return {string} The refusal
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.1
 */
export function forgeLinkRefusal(body) {
	const code = body?.code ?? body?.errors?.code ?? ''
	if (code === 'planninq-forge-link-duplicate') {
		return 'duplicate'
	}
	if (code === 'planninq-forge-link-no-task') {
		return 'no-task'
	}
	return 'failed'
}
