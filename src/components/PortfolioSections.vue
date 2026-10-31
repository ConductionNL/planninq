<template>
	<div class="portfolio-sections">
		<template v-if="showHeadings">
			<section
				v-for="group in groups"
				:key="group.id"
				class="portfolio-sections__section"
				data-testid="portfolio-section"
				:data-portfolio="group.id">
				<h3 class="portfolio-sections__heading">
					<button
						type="button"
						class="portfolio-sections__toggle"
						:aria-expanded="String(!folded[group.id])"
						:aria-controls="`portfolio-section-${group.id}`"
						data-testid="portfolio-section-toggle"
						@click="toggle(group.id)">
						<ChevronDown v-if="!folded[group.id]" :size="20" />
						<ChevronRight v-else :size="20" />
						<span
							v-if="group.color"
							class="portfolio-sections__swatch"
							:style="{ backgroundColor: group.color }"
							aria-hidden="true" />
						<span>{{ group.title || t('planninq', 'No portfolio') }}</span>
						<span class="portfolio-sections__count">({{ group.projects.length }})</span>
					</button>
				</h3>
				<div v-show="!folded[group.id]" :id="`portfolio-section-${group.id}`">
					<slot :projects="group.projects" />
				</div>
			</section>
		</template>
		<slot v-else :projects="allProjects" />
	</div>
</template>

<script>
/**
 * PortfolioSections.
 *
 * Project lists grouped by portfolio: one section per portfolio with a
 * heading that is a real button, folds its section and announces whether it
 * is open (aria-expanded). Without any portfolio the list renders flat.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
 */
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'

export default {
	name: 'PortfolioSections',

	components: {
		ChevronDown,
		ChevronRight,
	},

	props: {
		/** Groups from groupByPortfolio(). */
		groups: {
			type: Array,
			required: true,
		},

		/** Whether any portfolio exists; without one the list renders flat. */
		showHeadings: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			folded: {},
		}
	},

	computed: {
		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
		 */
		allProjects() {
			return this.groups.flatMap((group) => group.projects)
		},
	},

	methods: {
		/**
		 * @param {string} id The portfolio id.
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
		 */
		toggle(id) {
			this.folded = { ...this.folded, [id]: !this.folded[id] }
		},
	},
}
</script>

<style scoped>
.portfolio-sections__section + .portfolio-sections__section {
	margin-top: 12px;
}

.portfolio-sections__heading {
	margin: 0 0 4px;
	font-size: 16px;
	font-weight: 600;
}

.portfolio-sections__toggle {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 4px 8px;
	border: none;
	border-radius: var(--border-radius-element);
	background: none;
	color: var(--color-main-text);
	font: inherit;
	cursor: pointer;
}

.portfolio-sections__toggle:hover {
	background-color: var(--color-background-hover);
}

.portfolio-sections__toggle:focus-visible {
	outline: 2px solid var(--color-primary-element);
}

.portfolio-sections__swatch {
	display: inline-block;
	width: 12px;
	height: 12px;
	border-radius: 50%;
}

.portfolio-sections__count {
	color: var(--color-text-maxcontrast);
	font-weight: normal;
}
</style>
