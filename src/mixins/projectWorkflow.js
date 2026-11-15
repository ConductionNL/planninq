/**
 * The shared workflow of the component's `project`, read when the project
 * names one (projects-templates-shared-workflow); null otherwise.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
 */
import { getWorkflow } from '../api/workflows.js'

export default {
	data() {
		return {
			workflow: null,
		}
	},

	watch: {
		'project.workflow': {
			immediate: true,
			/**
			 * @param {string|null} id The project's workflow.
			 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
			 */
			async handler(id) {
				this.workflow = id ? await getWorkflow(id) : null
			},
		},
	},
}
