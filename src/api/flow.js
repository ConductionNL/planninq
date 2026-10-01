/**
 * Flow API: stateless reads for the Flow tab and the portfolio flow page.
 * Both endpoints replay OpenRegister's audit trail server-side and are
 * RBAC-scoped there; nothing here writes.
 *
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * A window's query parameters, leaving out empty bounds.
 *
 * @param {string|null} from First day, YYYY-MM-DD
 * @param {string|null} to Last day, YYYY-MM-DD
 * @return {object}
 */
function windowParams(from, to) {
	const params = {}
	if (from) {
		params.from = from
	}
	if (to) {
		params.to = to
	}
	return params
}

/**
 * The flow of one project.
 *
 * @param {string} projectId The project's UUID
 * @param {string|null} [from] First day
 * @param {string|null} [to] Last day
 * @return {Promise<object>} columns, days, finished, summary, withoutHistory
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export async function fetchProjectFlow(projectId, from = null, to = null) {
	const url = generateUrl(`/apps/planninq/api/projects/${projectId}/flow`)
	const response = await axios.get(url, { params: windowParams(from, to) })
	return response.data || {}
}

/**
 * The flow of every readable project in a portfolio.
 *
 * @param {string} portfolioId The portfolio's UUID
 * @param {string|null} [from] First day
 * @param {string|null} [to] Last day
 * @return {Promise<object>} projects, skipped, summary
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
 */
export async function fetchPortfolioFlow(portfolioId, from = null, to = null) {
	const url = generateUrl(`/apps/planninq/api/portfolios/${portfolioId}/flow`)
	const response = await axios.get(url, { params: windowParams(from, to) })
	return response.data || {}
}
