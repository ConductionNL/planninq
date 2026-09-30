/**
 * Column rules: what happens to a card when it enters a board column
 * (boards-column-automation).
 *
 * A column keeps its rules in `automation`, a list of `{ action, value }`.
 * The server runs them (ColumnAutomationListener) in the same save as the
 * move; these helpers only prepare the rules dialog and describe what a move
 * changed, so the board can say so.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
 */

/** The actions a rule can take, in the column schema's order. */
export const RULE_ACTIONS = ['setPriority', 'assign', 'assignMover', 'unassign', 'addLabel']

/** Actions that need a value: a priority, a person or a label. */
const VALUE_ACTIONS = ['setPriority', 'assign', 'addLabel']

/** The task schema's priority values. */
export const RULE_PRIORITIES = ['low', 'normal', 'high', 'urgent']

/**
 * Whether an action needs a value.
 *
 * @param {string} action The action.
 * @return {boolean}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
 */
export function actionNeedsValue(action) {
	return VALUE_ACTIONS.includes(action)
}

/**
 * A column's rules as stored, or an empty list.
 *
 * @param {object|null} column The column.
 * @return {Array<{action: string, value?: string}>}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.2
 */
export function columnRules(column) {
	return Array.isArray(column?.automation) ? column.automation.filter((rule) => rule && typeof rule.action === 'string') : []
}

/**
 * The rules to save: known actions only, a value only where the action takes one.
 *
 * @param {Array<object>} rules The rules in the dialog.
 * @return {Array<{action: string, value?: string}>}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
 */
export function rulesPayload(rules) {
	return (rules || [])
		.filter((rule) => RULE_ACTIONS.includes(rule?.action))
		.map((rule) => actionNeedsValue(rule.action)
			? { action: rule.action, value: String(rule.value ?? '') }
			: { action: rule.action })
}

/**
 * Whether a rule can be saved: a known action and, where it needs one, a value.
 *
 * @param {object} rule The rule.
 * @return {boolean}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
 */
export function ruleIsComplete(rule) {
	if (!RULE_ACTIONS.includes(rule?.action)) {
		return false
	}
	return !actionNeedsValue(rule.action) || String(rule.value ?? '') !== ''
}

/**
 * What is wrong with a stored rule now, or null: an assignee who left the
 * project, or a label that was deleted. The server skips such a rule.
 *
 * @param {object}        rule     The rule.
 * @param {Array<string>} members  The project's member ids, owner included.
 * @param {Array<string>} labelIds The ids of the existing labels.
 * @return {'formerMember'|'missingLabel'|null}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
 */
export function ruleProblem(rule, members, labelIds) {
	if (rule?.action === 'assign' && rule.value && !(members || []).includes(rule.value)) {
		return 'formerMember'
	}
	if (rule?.action === 'addLabel' && rule.value && !(labelIds || []).includes(rule.value)) {
		return 'missingLabel'
	}
	return null
}

/**
 * What the rules changed on a moved card, comparing what the board sent with
 * what the server stored.
 *
 * @param {object} sent   The task as the board expected it after the move.
 * @param {object} stored The task the server returned.
 * @return {{assignedTo?: string, priority?: string, labelsAdded: Array<string>}}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.2
 */
export function ruleEffects(sent, stored) {
	const effects = { labelsAdded: [] }
	if (!stored) {
		return effects
	}
	if ('assignedTo' in stored && String(stored.assignedTo ?? '') !== String(sent?.assignedTo ?? '')) {
		effects.assignedTo = String(stored.assignedTo ?? '')
	}
	if ('priority' in stored && (stored.priority || 'normal') !== (sent?.priority || 'normal')) {
		effects.priority = stored.priority
	}
	const before = new Set(Array.isArray(sent?.labels) ? sent.labels : [])
	effects.labelsAdded = (Array.isArray(stored.labels) ? stored.labels : []).filter((id) => !before.has(id))
	return effects
}

/**
 * Whether a move changed anything beyond the card's place.
 *
 * @param {object} effects The result of ruleEffects().
 * @return {boolean}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.2
 */
export function hasRuleEffects(effects) {
	return 'assignedTo' in effects || 'priority' in effects || effects.labelsAdded.length > 0
}
