<template>
	<div class="member-search">
		<NcTextField
			:value="query"
			:label="t('planix', 'Add member')"
			:placeholder="t('planix', 'Search for a user…')"
			:disabled="loading"
			@update:value="onInput" />

		<!-- Dropdown results -->
		<ul
			v-if="results.length > 0"
			class="member-search__dropdown"
			role="listbox"
			:aria-label="t('planix', 'User search results')">
			<li
				v-for="user in results"
				:key="user.id"
				class="member-search__result"
				role="option"
				:aria-selected="false"
				@click="selectUser(user)"
				@keydown.enter="selectUser(user)">
				<NcAvatar :user="user.id" :size="24" :aria-label="user.displayName || user.id" />
				<span>{{ user.displayName || user.id }}</span>
			</li>
		</ul>

		<!-- Empty results notice -->
		<p v-else-if="query.length >= 2 && !loading && searched" class="member-search__empty">
			{{ t('planix', 'No users found for "{query}"', { query }) }}
		</p>
	</div>
</template>

<script>
/**
 * MemberSearch component.
 *
 * Debounced OCS /cloud/users search for adding project members.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
 */
import { NcAvatar, NcTextField } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { useProjectsStore } from '../store/projects.js'

export default {
	name: 'MemberSearch',

	components: { NcAvatar, NcTextField },

	props: {
		projectId: {
			type: String,
			required: true,
		},
		existingMembers: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['added'],

	data() {
		return {
			query: '',
			results: [],
			loading: false,
			searched: false,
			debounceTimer: null,
		}
	},

	beforeDestroy() {
		clearTimeout(this.debounceTimer)
	},

	methods: {
		/**
		 * @spec exclude Event-wiring glue — debounces input and delegates to searchUsers (covered by task-10).
		 * @param {string} value The current search input value.
		 */
		onInput(value) {
			this.query = value
			this.searched = false
			clearTimeout(this.debounceTimer)
			if (value.length < 2) {
				this.results = []
				return
			}
			this.debounceTimer = setTimeout(() => this.searchUsers(value), 300)
		},

		/**
		 * Search Nextcloud users via OCS endpoint with 300ms debounce.
		 *
		 * @param {string} term Search term
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async searchUsers(term) {
			this.loading = true
			try {
				const url = generateUrl('/ocs/v2.php/cloud/users')
				const resp = await fetch(`${url}?search=${encodeURIComponent(term)}&limit=10`, {
					headers: {
						requesttoken: OC.requestToken,
						'OCS-APIRequest': 'true',
					},
				})
				if (!resp.ok) throw new Error(resp.statusText)
				const data = await resp.json()
				const users = data.ocs?.data?.users || data.ocs?.data || []
				// Normalise to { id, displayName }
				this.results = (Array.isArray(users) ? users : Object.keys(users)).map((u) =>
					typeof u === 'string' ? { id: u, displayName: u } : u,
				).filter((u) => !this.existingMembers.includes(u.id))
			} catch (err) {
				console.error('User search failed:', err)
			} finally {
				this.loading = false
				this.searched = true
			}
		},

		/**
		 * Add the selected user as a project member.
		 *
		 * @param {object} user User object with id and displayName
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async selectUser(user) {
			if (this.existingMembers.includes(user.id)) return
			try {
				const store = useProjectsStore()
				await store.addMember(this.projectId, user.id)
				this.query = ''
				this.results = []
				this.$emit('added', user)
			} catch {
				showError(this.t('planix', 'Could not add member'))
			}
		},
	},
}
</script>

<style scoped>
.member-search {
	position: relative;
}

.member-search__dropdown {
	position: absolute;
	z-index: 100;
	top: 100%;
	left: 0;
	right: 0;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
	list-style: none;
	margin: 2px 0 0;
	padding: 4px;
	max-height: 200px;
	overflow-y: auto;
}

.member-search__result {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px;
	border-radius: var(--border-radius);
	cursor: pointer;
}

.member-search__result:hover {
	background: var(--color-background-hover);
}

.member-search__empty {
	margin: 4px 0 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>
