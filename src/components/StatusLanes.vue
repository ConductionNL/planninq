<template>
	<div class="status-lanes" data-testid="status-lanes">
		<section
			v-for="status in statuses"
			:key="status"
			class="status-lanes__lane"
			:class="{ 'status-lanes__lane--drop-target': dropTarget === status }"
			:data-status="status"
			:aria-label="laneTitle(status)"
			@dragover.prevent="dropTarget = status"
			@dragleave="onDragLeave(status)"
			@drop="onDrop(status)">
			<header class="status-lanes__header">
				<h3 class="status-lanes__title">
					{{ laneTitle(status) }}
				</h3>
				<span class="status-lanes__count" data-testid="lane-count">{{ (lanes[status] || []).length }}</span>
			</header>

			<div class="status-lanes__body">
				<p v-if="!(lanes[status] || []).length" class="status-lanes__empty">
					{{ t('planninq', 'No tasks') }}
				</p>
				<div
					v-for="task in lanes[status] || []"
					:key="task.id"
					class="status-lanes__card"
					role="button"
					tabindex="0"
					:aria-label="task.title"
					data-testid="task-card"
					:draggable="canMove(task) ? 'true' : 'false'"
					@click="$emit('open', task)"
					@keydown.enter="$emit('open', task)"
					@keydown.space.prevent="$emit('open', task)"
					@dragstart="dragged = task"
					@dragend="onDragEnd">
					<TaskCard :task="task" :project="projectsById[task.project] || null" />

					<!-- Keyboard-operable move: the accessible equivalent of
					     dragging the card to another lane. It stops click
					     propagation so it never opens the task. -->
					<div
						v-if="canMove(task)"
						class="status-lanes__card-actions"
						draggable="false"
						@click.stop
						@keydown.enter.stop
						@keydown.space.stop
						@dragstart.stop>
						<NcActions
							:aria-label="t('planninq', 'Move task to another column')"
							:forceMenu="true">
							<NcActionButton
								v-for="target in otherStatuses(status)"
								:key="target"
								:closeAfterClick="true"
								:data-testid="'move-to-' + target"
								@click="$emit('move', task, target)">
								<template #icon>
									<ArrowRightIcon :size="20" />
								</template>
								{{ laneTitle(target) }}
							</NcActionButton>
						</NcActions>
					</div>
				</div>
			</div>
		</section>
	</div>
</template>

<script>
/**
 * Status lanes for a cross-project view (boards-cross-project-board).
 *
 * One lane per task status, the vocabulary every project shares. A card is
 * dragged to another lane or moved with the keyboard menu; either way the
 * component only emits `move(task, status)`: the page resolves the move
 * through the task's own project columns. `ProjectBoard` does not use this:
 * its lanes are the project's own columns.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.1
 */
import { NcActionButton, NcActions } from '@nextcloud/vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import TaskCard from './TaskCard.vue'
import { BOARD_STATUSES } from '../utils/taskHelpers.js'

export default {
	name: 'StatusLanes',

	components: { ArrowRightIcon, NcActionButton, NcActions, TaskCard },

	props: {
		/** Tasks by status, from viewLanes(). */
		lanes: {
			type: Object,
			required: true,
		},

		/** The view's readable projects by id, for the card chip. */
		projectsById: {
			type: Object,
			default: () => ({}),
		},

		/** Whether the viewer may move a task (their project rights decide the write). */
		movable: {
			type: Function,
			default: () => true,
		},
	},

	emits: ['move', 'open'],

	data() {
		return {
			statuses: BOARD_STATUSES,
			dragged: null,
			dropTarget: null,
		}
	},

	methods: {
		/**
		 * @param {string} status A task status.
		 * @return {string} The lane's title.
		 * @spec exclude Display helper — the label of a status lane.
		 */
		laneTitle(status) {
			const titles = {
				open: this.t('planninq', 'Open'),
				in_progress: this.t('planninq', 'In progress'),
				blocked: this.t('planninq', 'Blocked'),
				done: this.t('planninq', 'Done'),
				cancelled: this.t('planninq', 'Cancelled'),
			}
			return titles[status] || status
		},

		/**
		 * @param {string} status The card's lane.
		 * @return {string[]} The lanes it can move to.
		 * @spec exclude Display helper — the move menu's targets.
		 */
		otherStatuses(status) {
			return this.statuses.filter((candidate) => candidate !== status)
		},

		/**
		 * @param {object} task A card.
		 * @return {boolean}
		 * @spec exclude Prop passthrough — whether the card offers a move.
		 */
		canMove(task) {
			return this.movable(task) === true
		},

		/**
		 * Drop the dragged card on a lane: a move when the lane differs.
		 *
		 * @param {string} status The lane dropped on.
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.4
		 */
		onDrop(status) {
			const task = this.dragged
			this.dragged = null
			this.dropTarget = null
			if (task && task.status !== status && this.canMove(task)) {
				this.$emit('move', task, status)
			}
		},

		/**
		 * @param {string} status The lane left.
		 * @spec exclude Drag glue — clears the drop highlight.
		 */
		onDragLeave(status) {
			if (this.dropTarget === status) {
				this.dropTarget = null
			}
		},

		/**
		 * @spec exclude Drag glue — resets the drag state.
		 */
		onDragEnd() {
			this.dragged = null
			this.dropTarget = null
		},
	},
}
</script>

<style scoped>
.status-lanes {
	display: flex;
	gap: 12px;
	overflow-x: auto;
	align-items: flex-start;
	padding-bottom: 8px;
}

.status-lanes__lane {
	flex: 0 0 280px;
	display: flex;
	flex-direction: column;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	border: 2px solid transparent;
}

.status-lanes__lane--drop-target {
	border-color: var(--color-primary-element);
}

.status-lanes__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px;
}

.status-lanes__title {
	margin: 0;
	font-size: 15px;
}

.status-lanes__count {
	color: var(--color-text-maxcontrast);
}

.status-lanes__body {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 0 8px 8px;
	min-height: 48px;
}

.status-lanes__empty {
	margin: 0;
	padding: 8px;
	color: var(--color-text-maxcontrast);
}

.status-lanes__card {
	position: relative;
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	cursor: pointer;
}

.status-lanes__card:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.status-lanes__card-actions {
	position: absolute;
	inset-block-start: 4px;
	inset-inline-end: 4px;
}
</style>
