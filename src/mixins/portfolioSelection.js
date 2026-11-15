/**
 * The portfolio a portfolio page shows: the one in the address
 * (?portfolio=), else the first one, and the picker that changes it.
 *
 * The component provides `portfolios` and `projects` as data.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
 */
import { filterByPortfolio, sortPortfolios } from '../utils/portfolioGrouping.js'

export default {
	computed: {
		/**
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		portfolioOptions() {
			return sortPortfolios(this.portfolios).map((p) => ({ id: String(p.id), label: String(p.title ?? '') }))
		},

		/**
		 * The portfolio in the address (?portfolio=), else the first one.
		 *
		 * @return {object|null}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		portfolio() {
			const wanted = String(this.$route.query.portfolio || '')
			const sorted = sortPortfolios(this.portfolios)
			return sorted.find((p) => String(p.id) === wanted) || sorted[0] || null
		},

		/**
		 * @return {{id: string, label: string}|null}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		selectedOption() {
			return this.portfolioOptions.find((option) => option.id === String(this.portfolio?.id)) || null
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		portfolioProjects() {
			return this.portfolio ? filterByPortfolio(this.projects, String(this.portfolio.id)) : []
		},
	},

	methods: {
		/**
		 * @param {{id: string}} option The picked portfolio.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
		 */
		pick(option) {
			if (option?.id && option.id !== this.$route.query.portfolio) {
				this.$router.replace({ query: { ...this.$route.query, portfolio: option.id } })
			}
		},
	},
}
