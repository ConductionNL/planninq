<template>
	<span v-if="blocked" class="planix-blocked-badge" :title="title">
		<LockOutline :size="14" />
		<span class="planix-blocked-badge__label">{{ t('planix', 'Blocked') }}</span>
	</span>
</template>

<script>
/**
 * BlockedBadge — a compact "Blocked" indicator chip for kanban cards and
 * list-view rows.
 *
 * Presentation-only: the parent computes the derived blocked state with the
 * pure `isBlocked` helper and passes it in. The badge never writes status and
 * never blocks interaction (soft signal, consistent with the WIP-limit
 * philosophy). Styling matches the card's other status chips.
 *
 * @spec openspec/changes/task-dependencies/specs/kanban-board/spec.md
 */
import { translate as t } from '@nextcloud/l10n'
import LockOutline from 'vue-material-design-icons/LockOutline.vue'

export default {
	name: 'BlockedBadge',

	components: {
		LockOutline,
	},

	props: {
		/**
		 * Whether the task is blocked (derived by the parent via isBlocked()).
		 */
		blocked: {
			type: Boolean,
			default: false,
		},

		/**
		 * Number of open blockers, used to build an informative tooltip.
		 */
		openBlockerCount: {
			type: Number,
			default: 0,
		},
	},

	computed: {
		/**
		 * Tooltip text naming how many open blockers cause the state.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/task-dependencies/specs/kanban-board/spec.md
		 */
		title() {
			if (this.openBlockerCount > 0) {
				return t('planix', 'Blocked by {count} open task(s)', { count: this.openBlockerCount })
			}
			return t('planix', 'Blocked')
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.planix-blocked-badge {
	display: inline-flex;
	align-items: center;
	gap: 2px;
	padding: 1px 6px;
	border-radius: var(--border-radius-pill, 100px);
	background-color: var(--color-error, #e9322d);
	color: var(--color-primary-text, #fff);
	font-size: 11px;
	font-weight: 600;
	line-height: 1.4;
	white-space: nowrap;
}

.planix-blocked-badge__label {
	letter-spacing: 0.02em;
}
</style>
