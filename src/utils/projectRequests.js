/**
 * Project requests (projects-lifecycle-policy, section 3): someone outside
 * the creation policy asks for a project; a reviewer approves or rejects it
 * through the project lifecycle.
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
 */

/**
 * Whether a step of the two-step request form may go on.
 *
 * @param {number} step 1 (what the project is) or 2 (why it is needed).
 * @param {object} form The form.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
 */
export function requestStepValid(step, form) {
	if (step === 1) {
		return !!String(form?.title || '').trim()
	}
	return !!String(form?.requestReason || '').trim()
}

/**
 * The create body of a request; empty optional fields are left out.
 *
 * @param {object} form The form.
 * @return {object}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
 */
export function requestPayload(form) {
	const payload = {}
	for (const key of ['title', 'description', 'requestReason', 'startDate']) {
		const value = String(form?.[key] || '').trim()
		if (value) {
			payload[key] = value
		}
	}
	return payload
}

/**
 * The banner a requested or rejected project shows instead of its board.
 *
 * @param {object} project The project.
 * @return {{kind: string, note: string}|null}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
 */
export function requestBanner(project) {
	if (project?.status === 'requested') {
		return { kind: 'waiting', note: '' }
	}
	if (project?.status === 'rejected') {
		return { kind: 'rejected', note: project.reviewNote || '' }
	}
	return null
}

/**
 * Which review buttons show, from the actions OpenRegister offers.
 *
 * @param {string[]|null} actions The available actions.
 * @return {{approve: boolean, reject: boolean}}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
 */
export function reviewButtons(actions) {
	const list = Array.isArray(actions) ? actions : []
	return { approve: list.includes('approve'), reject: list.includes('reject') }
}
