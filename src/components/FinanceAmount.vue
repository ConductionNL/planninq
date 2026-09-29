<template>
	<span v-if="value === null" />
	<span v-else-if="column === 'remaining' && row.over" class="finance-amount finance-amount--over">
		{{ t('planninq', '{amount} over budget', { amount: euro(-value) }) }}
	</span>
	<span v-else class="finance-amount">{{ euro(value) }}</span>
</template>

<script>
/**
 * FinanceAmount.
 *
 * One cell of a finance table. An overrun reads "over budget" in words as
 * well as colour, so it does not rest on colour alone.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 */
import { formatEuro } from '../utils/finance.js'

export default {
	name: 'FinanceAmount',

	props: {
		/** The table row. */
		row: {
			type: Object,
			required: true,
		},

		/** The column: budget, commitment, actual, forecast or remaining. */
		column: {
			type: String,
			required: true,
		},
	},

	computed: {
		/**
		 * The amount, or null when there is nothing to show.
		 *
		 * @return {number|null}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		value() {
			const value = this.row?.[this.column]
			return typeof value === 'number' ? value : null
		},
	},

	methods: {
		/**
		 * @param {number} amount The amount.
		 * @return {string}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		euro(amount) {
			return formatEuro(amount)
		},
	},
}
</script>

<style scoped>
.finance-amount--over {
	color: var(--color-error-text);
	font-weight: 600;
}
</style>
