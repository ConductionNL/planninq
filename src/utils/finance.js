/**
 * A project's money (portfolio-finance): labour cost from booked time, the
 * tables of budget, commitments, actual cost, forecast and what remains per
 * cost category and per phase, who sees the amounts, and the payloads the
 * Finance tab writes.
 *
 * Every amount is in euro, the reporting currency of `project.budgetAmount`.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.1
 */

/** The id of the table row that holds labour from booked time. */
export const LABOUR_ROW = '__labour__'

/** The kinds of amount, in column order. */
export const FINANCE_KINDS = ['budget', 'commitment', 'actual', 'forecast']

/** The billing models a project can have. */
export const BILLING_MODELS = ['none', 'fixedPrice', 'hourly']

/**
 * A number from a stored value or a form field; anything else is zero.
 *
 * @param {number|string|null|undefined} value The value.
 * @return {number}
 */
function amountOf(value) {
	const number = Number(value)
	return Number.isFinite(number) ? number : 0
}

/**
 * Labour cost: booked hours times the entry's rate, else the project's rate.
 *
 * The one place that reads hours and rates, so the move of hours to humaniq
 * (plannedtimeentry-reads-humaniqs-hours) changes only its caller's input.
 * When the owner of the hours is missing the cost is not available, which is
 * not the same as zero.
 *
 * @param {Array<object>} entries The time entries booked on the project (duration in minutes).
 * @param {object} project The project, for its hourly rate.
 * @param {object} [options] Options.
 * @param {boolean} [options.hoursAvailable] False when the hours cannot be read.
 * @return {{available: boolean, hours: number|null, amount: number|null}}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.1
 */
export function laborCost(entries, project, { hoursAvailable = true } = {}) {
	if (!hoursAvailable) {
		return { available: false, hours: null, amount: null }
	}
	const projectRate = amountOf(project?.hourlyRate)
	let minutes = 0
	let amount = 0
	for (const entry of entries || []) {
		const entryMinutes = Math.max(0, amountOf(entry?.duration))
		const rate = amountOf(entry?.hourlyRate) > 0 ? amountOf(entry.hourlyRate) : projectRate
		minutes += entryMinutes
		amount += (entryMinutes / 60) * rate
	}
	return { available: true, hours: Math.round((minutes / 60) * 100) / 100, amount: Math.round(amount * 100) / 100 }
}

/**
 * An empty row of the finance table.
 *
 * @param {string} id The row id.
 * @param {string} label The row label.
 * @return {object}
 */
function emptyRow(id, label) {
	return { id, label, budget: 0, commitment: 0, actual: 0, forecast: 0, remaining: null, over: false }
}

/**
 * Add a line's amount to a row.
 *
 * @param {object} row The row.
 * @param {object} line The finance line.
 */
function addLine(row, line) {
	if (FINANCE_KINDS.includes(line?.kind)) {
		row[line.kind] += amountOf(line.amount)
	}
}

/**
 * Work out what remains of a row: budget minus actual cost minus open commitments.
 *
 * A row without a budget has nothing to remain.
 *
 * @param {object} row The row.
 * @return {object} The row.
 */
function settle(row) {
	if (row.budget > 0) {
		row.remaining = row.budget - row.actual - row.commitment
		row.over = row.remaining < 0
	}
	return row
}

/**
 * The Finance tab's tables: a row per cost category plus labour, a row per
 * phase, the budget no category holds yet, and the total.
 *
 * `project.budgetAmount` stays the agreed total. Budget lines break it down;
 * the difference is shown as not yet assigned, never silently picked (risk 2).
 * Without an agreed total the category budgets are the budget.
 *
 * @param {object} input The input.
 * @param {Array<object>} input.lines The project's finance lines.
 * @param {Array<string>} input.categories The admin's cost categories, in order.
 * @param {object} input.labour The laborCost() result.
 * @param {object} input.project The project.
 * @param {Array<object>} [input.phases] The project's phases, in order.
 * @return {{categoryRows: Array<object>, phaseRows: Array<object>, unassignedBudget: number, total: object}}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 */
export function financeTable({ lines, categories, labour, project, phases = [] }) {
	const byCategory = new Map()
	for (const name of categories || []) {
		byCategory.set(name, emptyRow(name, name))
	}
	for (const line of lines || []) {
		const name = line?.category || ''
		if (!byCategory.has(name)) {
			byCategory.set(name, emptyRow(name, name))
		}
		addLine(byCategory.get(name), line)
	}

	const labourRow = emptyRow(LABOUR_ROW, '')
	labourRow.available = labour?.available !== false
	labourRow.hours = labour?.hours ?? null
	labourRow.actual = labourRow.available ? amountOf(labour?.amount) : 0

	const categoryRows = [...byCategory.values(), labourRow].map(settle)

	const byPhase = new Map()
	for (const phase of phases || []) {
		byPhase.set(phase.id, emptyRow(phase.id, phase.title || ''))
	}
	for (const line of lines || []) {
		const id = line?.phase || ''
		if (!byPhase.has(id)) {
			byPhase.set(id, emptyRow(id, ''))
		}
		addLine(byPhase.get(id), line)
	}
	const noPhase = byPhase.get('')
	byPhase.delete('')
	const phaseRows = [...byPhase.values(), ...(noPhase ? [noPhase] : [])].map(settle)

	const total = emptyRow('', '')
	for (const row of categoryRows) {
		for (const kind of FINANCE_KINDS) {
			total[kind] += row[kind]
		}
	}
	const lineBudget = total.budget
	const agreed = amountOf(project?.budgetAmount)
	total.budget = agreed > 0 ? agreed : lineBudget
	settle(total)

	return {
		categoryRows,
		phaseRows,
		unassignedBudget: agreed > 0 ? Math.max(0, agreed - lineBudget) : 0,
		total,
	}
}

/**
 * Whether a viewer sees the project's amounts: its owner, the managers of its
 * portfolio and admins. Members and viewers do not (design decision 1).
 *
 * @param {object} project The project.
 * @param {{uid: string, isAdmin?: boolean}|null} user The viewer.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
 */
export function canSeeProjectMoney(project, user) {
	if (!user?.uid || !project) {
		return false
	}
	if (user.isAdmin === true || project.owner === user.uid) {
		return true
	}
	return Array.isArray(project.portfolioReaders) && project.portfolioReaders.includes(user.uid)
}

/**
 * Whether a viewer sets the terms and writes manual lines: the owner and admins.
 *
 * @param {object} project The project.
 * @param {{uid: string, isAdmin?: boolean}|null} user The viewer.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
 */
export function canEditTerms(project, user) {
	if (!user?.uid || !project) {
		return false
	}
	return user.isAdmin === true || project.owner === user.uid
}

/**
 * The terms form, filled from a project.
 *
 * @param {object} project The project.
 * @return {object}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
 */
export function termsForm(project) {
	const number = (value) => (amountOf(value) > 0 ? String(value) : '')
	return {
		billable: project?.billable === true,
		billingModel: BILLING_MODELS.includes(project?.billingModel) ? project.billingModel : 'none',
		budgetAmount: number(project?.budgetAmount),
		budgetHours: number(project?.budgetHours),
		hourlyRate: number(project?.hourlyRate),
		startDate: project?.startDate || '',
		endDate: project?.endDate || '',
	}
}

/**
 * The project patch the terms form saves. An empty or negative amount is zero,
 * which means no budget agreed.
 *
 * @param {object} form The terms form.
 * @return {object}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.3
 */
export function termsPatch(form) {
	const positive = (value) => Math.max(0, amountOf(value))
	return {
		billable: form.billable === true,
		billingModel: BILLING_MODELS.includes(form.billingModel) ? form.billingModel : 'none',
		budgetAmount: positive(form.budgetAmount),
		budgetHours: positive(form.budgetHours),
		hourlyRate: positive(form.hourlyRate),
		startDate: form.startDate || null,
		endDate: form.endDate || null,
	}
}

/**
 * The payload of a manual finance line.
 *
 * @param {object} form The line form.
 * @param {string} projectId The project UUID.
 * @return {object}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 */
export function financeLinePayload(form, projectId) {
	return {
		project: projectId,
		kind: FINANCE_KINDS.includes(form.kind) ? form.kind : 'actual',
		category: form.category || '',
		amount: amountOf(form.amount),
		date: form.date || null,
		description: (form.description || '').trim(),
		phase: form.phase || null,
		source: 'manual',
	}
}

/**
 * Format an amount in euro, without cents.
 *
 * @param {number} amount The amount.
 * @return {string}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 */
export function formatEuro(amount) {
	try {
		return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(amount)
	} catch {
		return String(Math.round(amount))
	}
}

/**
 * The cost categories stored in the admin setting, or none when it is unreadable.
 *
 * @param {string} raw The stored JSON list.
 * @return {Array<string>}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.2
 */
export function parseCategories(raw) {
	try {
		const parsed = JSON.parse(raw || '[]')
		return Array.isArray(parsed) ? parsed.filter((name) => typeof name === 'string' && name.trim() !== '') : []
	} catch {
		return []
	}
}

/**
 * The category names typed one per line.
 *
 * @param {string} text The text.
 * @return {Array<string>}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.2
 */
export function categoryLines(text) {
	return String(text || '').split('\n').map((line) => line.trim()).filter((line) => line !== '')
}

/**
 * Whether a list of category names may be saved: at least one, each name once.
 *
 * @param {Array<string>} names The names.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.2
 */
export function categoriesValid(names) {
	const lower = names.map((name) => name.toLowerCase())
	return names.length > 0 && new Set(lower).size === lower.length
}

/**
 * One project's money: the total row of its finance table, labour included.
 *
 * @param {object} project The project.
 * @param {Array<object>} lines The project's finance lines.
 * @param {Array<object>} entries The time entries booked on it.
 * @param {object} [options] Options for laborCost().
 * @return {object} Budget, commitment, actual, forecast, remaining and over.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
 */
export function projectMoney(project, lines, entries, options = {}) {
	return financeTable({ lines, categories: [], labour: laborCost(entries, project, options), project }).total
}

/**
 * Money across a portfolio: a row per project whose money the viewer may see,
 * the totals, how many projects were left out, and whether every line belongs
 * to one of the portfolio's projects (a line from elsewhere means the filter
 * on `portfolio` was not applied, and the page says the totals cannot be
 * trusted instead of showing them as if they could).
 *
 * @param {object} input The input.
 * @param {Array<object>} input.projects The portfolio's projects the viewer can read.
 * @param {Array<object>} input.lines The finance lines read with the filter portfolio = id.
 * @param {object} input.entriesByProject Time entries keyed by project id.
 * @param {{uid: string, isAdmin?: boolean}|null} input.user The viewer.
 * @return {{rows: Array<object>, total: object, hidden: number, consistent: boolean}}
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
 */
export function portfolioFinance({ projects, lines, entriesByProject, user }) {
	const ids = new Set((projects || []).map((project) => project.id))
	const byProject = {}
	let consistent = true
	for (const line of lines || []) {
		const id = typeof line?.project === 'object' ? line?.project?.id : line?.project
		if (!ids.has(id)) {
			consistent = false
			continue
		}
		(byProject[id] ||= []).push(line)
	}

	const visible = (projects || []).filter((project) => canSeeProjectMoney(project, user))
	const rows = visible.map((project) => ({
		project,
		...projectMoney(project, byProject[project.id] || [], entriesByProject?.[project.id] || []),
	}))

	const total = { budget: 0, commitment: 0, actual: 0, forecast: 0, remaining: null, over: false }
	for (const row of rows) {
		for (const kind of FINANCE_KINDS) {
			total[kind] += row[kind]
		}
	}
	settle(total)

	return { rows, total, hidden: (projects || []).length - visible.length, consistent }
}
