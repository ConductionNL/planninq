/**
 * Reading the body of an answer that may not be JSON.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/projects/spec.md
 */

/**
 * The JSON body of a response, or an empty object when it is not JSON.
 *
 * @param {Response} response The response.
 * @return {Promise<object|null>}
 *
 * @spec openspec/specs/projects/spec.md
 */
export function responseBody(response) {
	return response.json().catch(() => ({}))
}
