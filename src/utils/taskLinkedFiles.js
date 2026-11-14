// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Files from Files on a task (task-attachments-from-files): a task points at
 * files that already live in Nextcloud Files by their path. Nothing here
 * copies a file; the picker only yields paths and the task stores them.
 *
 * @spec openspec/changes/task-attachments-from-files/specs/task-collaboration/spec.md#requirement-a-member-attaches-a-file-that-is-already-in-files
 */

/**
 * The task's linked files as clean references: one per path, with a name.
 *
 * @param {Array<object|string>} files What the task or the picker holds.
 *
 * @return {Array<{path: string, name: string}>} The references.
 *
 * @spec openspec/changes/task-attachments-from-files/specs/task-collaboration/spec.md#requirement-a-member-attaches-a-file-that-is-already-in-files
 */
export function linkedFileRefs(files) {
	const out = []
	const seen = new Set()
	for (const file of Array.isArray(files) ? files : []) {
		const path = String((typeof file === 'string' ? file : file?.path) || '').trim()
		if (path === '' || seen.has(path)) {
			continue
		}
		seen.add(path)
		const name = String((typeof file === 'object' && file?.name) || '').trim() || path.split('/').filter(Boolean).pop() || path
		out.push({ path, name })
	}
	return out
}

/**
 * The patch that stores the picked list on the task, or an empty patch when
 * nothing changed, so an unchanged list writes nothing.
 *
 * @param {Array<object>} current The task's linked files now.
 * @param {Array<object>} next The list after the pick or the removal.
 *
 * @return {object} `{ linkedFiles }`, or `{}`.
 *
 * @spec openspec/changes/task-attachments-from-files/specs/task-collaboration/spec.md#requirement-a-member-attaches-a-file-that-is-already-in-files
 */
export function linkedFilesPatch(current, next) {
	const before = linkedFileRefs(current)
	const after = linkedFileRefs(next)
	if (JSON.stringify(before) === JSON.stringify(after)) {
		return {}
	}
	return { linkedFiles: after }
}
