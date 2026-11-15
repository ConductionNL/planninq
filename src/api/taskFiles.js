/**
 * Images in a task description, stored as files of the task through
 * OpenRegister's per-object files API (task-description-images). Nothing is
 * stored by planninq itself.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/task-description-images/specs/task-editing/spec.md
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { imageMarkdown } from '../utils/descriptionImages.js'

/**
 * The address an image of a task is shown from: OpenRegister's preview of the
 * file, which checks that the reader may read the task.
 *
 * @param {string} taskId The task.
 * @param {number|string} fileId The stored file.
 * @return {string}
 *
 * @spec openspec/changes/task-description-images/tasks.md#1.1
 */
export function taskImageUrl(taskId, fileId) {
	return generateUrl(`/apps/openregister/api/objects/planninq/task/${taskId}/files/${fileId}/preview?width=1024&height=1024`)
}

/**
 * Store one image as a file of the task and answer the Markdown that shows it.
 *
 * @param {string} taskId The task.
 * @param {File} file The pasted or dropped image.
 * @return {Promise<string>} The Markdown image link.
 * @throws {Error} When OpenRegister stored nothing.
 *
 * @spec openspec/changes/task-description-images/specs/task-editing/spec.md
 */
export async function uploadTaskImage(taskId, file) {
	const form = new FormData()
	form.append('files[]', file)
	const response = await axios.post(generateUrl(`/apps/openregister/api/objects/planninq/task/${taskId}/filesMultipart`), form)
	const data = response?.data
	const stored = Array.isArray(data) ? data : (Array.isArray(data?.results) ? data.results : [])
	const fileId = stored[0]?.id
	if (fileId === undefined || fileId === null) {
		throw new Error('The image was not stored')
	}
	return imageMarkdown(file.name, taskImageUrl(taskId, fileId))
}
