// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
// SPDX-License-Identifier: EUPL-1.2

/**
 * The Markdown side of files attached to a wiki page (projects-wiki, task
 * 2.3): which pasted, dropped or picked items are files, and the link that
 * shows an image or offers any other file for download. No network here; the
 * upload is `uploadWikiFile()` in `src/api/wikiPages.js`.
 *
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-project-member-keeps-wiki-pages-next-to-the-tasks
 */
import { imageMarkdown } from './descriptionImages.js'

/**
 * The files among pasted, dropped or picked items.
 *
 * @param {DataTransfer|HTMLInputElement|null|undefined} source A clipboard's or a drop's data, or a file input.
 * @return {File[]}
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
 */
export function filesIn(source) {
	return Array.from(source?.files ?? []).filter((file) => file && typeof file.name === 'string')
}

/**
 * Whether a file is shown inline as an image.
 *
 * @param {File|{type?: string}} file The file.
 * @return {boolean}
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
 */
export function isImage(file) {
	return typeof file?.type === 'string' && file.type.startsWith('image/')
}

/**
 * The Markdown for an attached file: an image is shown from its preview,
 * any other file is a link to its download. The name is the link text, with
 * the characters that would end it taken out.
 *
 * @param {File|{name?: string, type?: string}} file The file.
 * @param {{preview: string, download: string}} urls Where OpenRegister serves it.
 * @return {string}
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
 */
export function attachmentMarkdown(file, urls) {
	if (isImage(file)) {
		return imageMarkdown(file?.name, urls.preview)
	}
	const text = String(file?.name || 'file').replace(/[[\]\n\r]/g, ' ').trim() || 'file'
	return `[${text}](${urls.download})`
}
