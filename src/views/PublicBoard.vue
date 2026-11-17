<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  SPDX-License-Identifier: EUPL-1.2

  The read-only board an access link opens (projects-public-share task 2.1).
  No forms but the password field; no Nextcloud chrome that needs a session.
-->
<template>
	<main class="planninq-public-board">
		<h1 class="planninq-public-board__title">
			{{ title }}
		</h1>
		<p v-if="state === 'loading'">
			{{ t('planninq', 'Loading the board…') }}
		</p>
		<form v-else-if="state === 'password' || state === 'wrongPassword'"
			class="planninq-public-board__password"
			@submit.prevent="unlock">
			<p>{{ t('planninq', 'This board is closed with a password.') }}</p>
			<p v-if="state === 'wrongPassword'" role="alert">
				{{ t('planninq', 'That password is not right.') }}
			</p>
			<label for="planninq-public-board-password">{{ t('planninq', 'Password') }}</label>
			<input id="planninq-public-board-password"
				v-model="password"
				type="password"
				autocomplete="off"
				required>
			<button type="submit">
				{{ t('planninq', 'Open board') }}
			</button>
		</form>
		<p v-else-if="state === 'gone'" role="alert">
			{{ t('planninq', 'This link does not work any more.') }}
		</p>
		<template v-else>
			<p v-if="board.capped" role="status">
				{{ t('planninq', 'Only the first 200 cards and columns are shown.') }}
			</p>
			<div class="planninq-public-board__columns">
				<section v-for="column in columnsShown"
					:key="column.id"
					class="planninq-public-board__column"
					:aria-label="column.title">
					<h2>{{ column.title }}</h2>
					<ul>
						<li v-for="card in column.cards" :key="card.id" class="planninq-public-board__card">
							<span class="planninq-public-board__card-title">{{ card.title }}</span>
							<span v-if="card.dueDate" class="planninq-public-board__meta">{{ card.dueDate }}</span>
							<span v-if="card.status" class="planninq-public-board__meta">{{ card.status }}</span>
							<span v-for="cardLabel in card.labels" :key="cardLabel" class="planninq-public-board__label">{{ cardLabel }}</span>
						</li>
					</ul>
				</section>
			</div>
		</template>
	</main>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { anchorFromPath, boardFromLink, failureState, linkDataUrl, PASSWORD_HEADER } from '../utils/publicBoard.js'

export default {
	name: 'PublicBoard',
	data() {
		return {
			state: 'loading',
			password: '',
			label: '',
			board: { columns: [], loose: [], capped: false },
		}
	},

	computed: {
		/**
		 * The page heading: the link's label, else "Shared board".
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-public-share/tasks.md#task-2.1
		 */
		title() {
			return this.label || t('planninq', 'Shared board')
		},

		/**
		 * The columns to draw, with cards that name no column in a last "Other cards" column.
		 *
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/projects-public-share/tasks.md#task-2.1
		 */
		columnsShown() {
			if (this.board.loose.length === 0) {
				return this.board.columns
			}
			return [...this.board.columns, { id: '__loose', title: t('planninq', 'Other cards'), cards: this.board.loose }]
		},
	},

	mounted() {
		this.load(null)
	},

	methods: {
		t,
		/**
		 * Read the board again with the password the visitor typed.
		 *
		 * @return {void}
		 *
		 * @spec openspec/changes/projects-public-share/tasks.md#task-2.1
		 */
		unlock() {
			this.load(this.password)
		},

		/**
		 * Read the link's board, sending the password when one is given.
		 *
		 * @param {string} [password] The link password.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-public-share/tasks.md#task-2.1
		 */
		async load(password) {
			const anchor = anchorFromPath(window.location.pathname)
			if (anchor === null) {
				this.state = 'gone'
				return
			}
			const headers = password ? { [PASSWORD_HEADER]: password } : {}
			try {
				const { data } = await axios.get(linkDataUrl(anchor), { headers })
				this.label = String(data?.link?.label ?? '')
				this.board = boardFromLink(data)
				this.state = 'board'
			} catch (error) {
				this.state = failureState(error?.response?.status, Boolean(password))
			}
		},
	},
}
</script>

<style scoped>
.planninq-public-board {
	padding: calc(var(--default-grid-baseline) * 4);
	color: var(--color-main-text);
	background: var(--color-main-background);
}

.planninq-public-board__columns {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 3);
	overflow-x: auto;
}

.planninq-public-board__column {
	min-width: 240px;
	padding: calc(var(--default-grid-baseline) * 2);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
}

.planninq-public-board__column ul {
	list-style: none;
	padding: 0;
	margin: 0;
}

.planninq-public-board__card {
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline);
	margin-block: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 2);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
}

.planninq-public-board__card-title {
	flex-basis: 100%;
	font-weight: bold;
}

.planninq-public-board__meta {
	color: var(--color-text-maxcontrast);
}

.planninq-public-board__label {
	padding-inline: var(--default-grid-baseline);
	border-radius: var(--border-radius-pill);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.planninq-public-board__password {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	max-width: 320px;
}
</style>
