/**
 * Readable keys (tasks-readable-keys): a project key such as VERG and task
 * keys such as VERG-42. The format rule matches the server's
 * (WorkItemKeyService::isValidFormat): 2 to 10 letters and digits, starting
 * with a letter, stored uppercase.
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.1
 */

const KEY_FORMAT = /^[A-Z][A-Z0-9]{1,9}$/

/**
 * A key as the server stores it: trimmed and uppercase.
 *
 * @param {string} key The key as typed.
 * @return {string}
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.1
 */
export function normaliseProjectKey(key) {
	return String(key ?? '').trim().toUpperCase()
}

/**
 * Whether a normalised key has the project key format.
 *
 * @param {string} key The normalised key.
 * @return {boolean}
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.1
 */
export function isValidProjectKey(key) {
	return KEY_FORMAT.test(String(key ?? ''))
}

/**
 * The key the dialog suggests for a title: the initials of several words, or
 * the first four characters of one word; empty when that would not be valid.
 *
 * @param {string} title The project title.
 * @return {string}
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.1
 */
export function suggestProjectKey(title) {
	const words = String(title ?? '')
		.normalize('NFD')
		.replace(/[̀-ͯ]/g, '')
		.toUpperCase()
		.split(/[^A-Z0-9]+/)
		.filter(Boolean)
	const key = (words.length > 1 ? words.map((word) => word[0]).join('') : (words[0] || '').slice(0, 4)).slice(0, 10)
	return isValidProjectKey(key) ? key : ''
}

/**
 * Whether the key may still change: until a task carries it.
 *
 * @param {object} project The project.
 * @return {boolean}
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.3
 */
export function keyEditable(project) {
	return !(Number(project?.nextTaskNumber) > 1)
}

/**
 * Which key rule a server error names: 'used', 'format', 'fixed' or ''.
 *
 * @param {string} error The server error code or message.
 * @return {string}
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
 */
export function keyRefusal(error) {
	const text = String(error || '')
	if (text.includes('planninq-project-key-used') || text.includes('already used by another project')) {
		return 'used'
	}
	if (text.includes('planninq-project-key-format') || text.includes('starts with a letter')) {
		return 'format'
	}
	if (text.includes('planninq-project-key-fixed') || text.includes('once tasks carry it')) {
		return 'fixed'
	}
	return ''
}

/**
 * A task's key and title, as the page title shows them.
 *
 * @param {object} task The task.
 * @return {string}
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-3.1
 */
export function taskHeading(task) {
	return [task?.key, task?.title].filter(Boolean).join(' ')
}
