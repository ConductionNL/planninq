/**
 * The Markdown side of images in a task description (task-description-images):
 * which pasted or dropped files are images, the link that shows one, and
 * where it goes in the text. No network here; the upload is in
 * `src/api/taskFiles.js`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/task-description-images/specs/task-editing/spec.md
 */

/**
 * The images among pasted or dropped items: a clipboard's or a drop's files
 * whose type is an image.
 *
 * @param {DataTransfer|null|undefined} transfer The clipboard data or the drop's data.
 * @return {File[]}
 *
 * @spec openspec/changes/task-description-images/tasks.md#1.1
 */
export function imagesIn(transfer) {
	const files = Array.from(transfer?.files ?? [])
	return files.filter((file) => typeof file?.type === 'string' && file.type.startsWith('image/'))
}

/**
 * The Markdown that shows an image, its name as the alternative text with
 * the characters that would end it taken out.
 *
 * @param {string} name The file name.
 * @param {string} url The image address.
 * @return {string}
 *
 * @spec openspec/changes/task-description-images/tasks.md#1.1
 */
export function imageMarkdown(name, url) {
	const alt = String(name || 'image').replace(/[[\]\n\r]/g, ' ').trim()
	return `![${alt}](${url})`
}

/**
 * The description with Markdown inserted at a position, on a line of its own.
 *
 * @param {string} text The description.
 * @param {number|null|undefined} at Where the cursor was; the end when unknown.
 * @param {string} markdown What to insert.
 * @return {string}
 *
 * @spec openspec/changes/task-description-images/tasks.md#1.1
 */
export function insertAt(text, at, markdown) {
	const source = String(text ?? '')
	const position = Number.isInteger(at) && at >= 0 && at <= source.length ? at : source.length
	const before = source.slice(0, position)
	const after = source.slice(position)
	const lead = before === '' || before.endsWith('\n') ? '' : '\n'
	const trail = after === '' || after.startsWith('\n') ? '' : '\n'
	return `${before}${lead}${markdown}${trail}${after}`
}
