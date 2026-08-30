/**
 * Pure helpers for app-wide label management.
 *
 * Kept side-effect free (no DOM, no store, no fetch) so they can be unit tested
 * under the node vitest environment and reused by the LabelEditDialog. The hex
 * pattern mirrors the OpenRegister `label` schema's authoritative
 * `^#[0-9A-Fa-f]{6}$` colour constraint.
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */

/** Default label colour, matching the schema default. */
export const DEFAULT_LABEL_COLOR = '#4376FC'

/** Six-digit hex colour pattern (schema contract). */
export const HEX_COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/

/**
 * Whether a value is a valid 6-digit hex colour (e.g. #4376FC).
 *
 * @param {string} color The candidate colour string.
 * @return {boolean} True when the value matches the schema hex pattern.
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */
export function isValidHexColor(color) {
	return typeof color === 'string' && HEX_COLOR_PATTERN.test(color)
}

/**
 * Whether a label title is present (non-empty after trimming).
 *
 * @param {string} title The candidate title.
 * @return {boolean} True when the title is non-empty.
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */
export function isValidLabelTitle(title) {
	return typeof title === 'string' && title.trim() !== ''
}

/**
 * Validate a label draft, returning a map of field → error key (empty when valid).
 *
 * @param {object} draft The label draft ({ title, color }).
 * @return {{title?: string, color?: string}} Validation errors keyed by field.
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */
export function validateLabelDraft(draft) {
	const errors = {}
	if (!isValidLabelTitle(draft?.title)) {
		errors.title = 'Title is required'
	}
	if (!isValidHexColor(draft?.color)) {
		errors.color = 'Color must be a 6-digit hex code (e.g. #4376FC)'
	}
	return errors
}

/**
 * Normalise a label draft into the payload sent to OpenRegister: title and
 * description trimmed, colour defaulted when blank.
 *
 * @param {object} draft The raw form draft ({ title, color, description }).
 * @return {{title: string, color: string, description: string}} The normalised payload.
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */
export function normaliseLabelPayload(draft) {
	return {
		title: (draft?.title || '').trim(),
		color: draft?.color || DEFAULT_LABEL_COLOR,
		description: (draft?.description || '').trim(),
	}
}
