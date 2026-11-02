/**
 * A dialog that works inside one project: the project's UUID as a required
 * prop.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec exclude Shared prop of the project dialogs; each dialog's spec covers what it does.
 */
export default {
	props: {
		/** The project UUID. */
		projectId: {
			type: String,
			required: true,
		},
	},
}
