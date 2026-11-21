/**
 * Reading a value that should be a list.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md
 */

/**
 * The value when it is an array, otherwise an empty list.
 *
 * @param {Array|object|string|number|null|undefined} value The value.
 * @return {Array}
 *
 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md
 */
export function asList(value) {
	return Array.isArray(value) ? value : []
}
