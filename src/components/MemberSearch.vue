<template>
	<div class="member-search" data-testid="member-search">
		<NcSelect
			:modelValue="null"
			:options="results"
			:inputLabel="t('planninq', 'Add member')"
			:placeholder="t('planninq', 'Search for a person or a group…')"
			label="displayName"
			:filterable="false"
			:loading="loading"
			:clearSearchOnSelect="true"
			@search="onInput"
			@update:modelValue="select">
			<template #option="option">
				<span class="member-search__option" :data-testid="`member-option-${option.key}`">
					<NcAvatar
						v-if="option.type === 'user'"
						:user="option.id"
						:displayName="option.displayName"
						:size="24"
						:hideStatus="true" />
					<AccountGroupOutline v-else :size="24" />
					<span class="member-search__option-text">
						<span>{{ option.displayName }}</span>
						<span v-if="option.subname" class="member-search__subname">{{ option.subname }}</span>
					</span>
				</span>
			</template>
			<template #no-options>
				<span v-if="query.trim().length >= minLength && searched && !loading" data-testid="member-search-empty">
					{{ t('planninq', 'No one found. Your admin\'s sharing settings decide who you can find.') }}
				</span>
				<span v-else>
					{{ t('planninq', 'Type at least two characters') }}
				</span>
			</template>
		</NcSelect>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
/**
 * MemberSearch component.
 *
 * Finds people and groups through Nextcloud's core autocomplete endpoint,
 * which every signed-in owner may call and which applies the admin's
 * sharing settings, and adds the one picked to the project.
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
 */
import { NcAvatar, NcSelect } from '@nextcloud/vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import { useProjectsStore } from '../store/projects.js'
import { MIN_SEARCH_LENGTH, searchMembers } from '../utils/memberSearch.js'

export default {
	name: 'MemberSearch',

	components: { AccountGroupOutline, NcAvatar, NcSelect },

	props: {
		projectId: {
			type: String,
			required: true,
		},

		existingMembers: {
			type: Array,
			default: () => [],
		},

		existingGroups: {
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
			minLength: MIN_SEARCH_LENGTH,
			debounceTimer: null,
			/** @type {AbortController|null} */
			abortController: null,
		}
	},

	/**
	 * @spec exclude Teardown glue: cancels the pending debounce timer and
	 *   aborts the in-flight request so neither resolves against a destroyed
	 *   component. It adds no behaviour of its own for a scenario to describe.
	 */
	beforeUnmount() {
		clearTimeout(this.debounceTimer)
		this.abortController?.abort()
	},

	methods: {
		/**
		 * Debounce what the person types and search after 300 ms.
		 *
		 * @param {string} value The current search text.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
		 */
		onInput(value) {
			this.query = value || ''
			this.searched = false
			clearTimeout(this.debounceTimer)
			this.abortController?.abort()
			if (this.query.trim().length < MIN_SEARCH_LENGTH) {
				this.results = []
				return
			}
			this.debounceTimer = setTimeout(() => this.search(this.query), 300)
		},

		/**
		 * Ask core autocomplete for people and groups matching the text,
		 * leaving out who is already on the project.
		 *
		 * @param {string} term The search text.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
		 */
		async search(term) {
			this.abortController = new AbortController()
			this.loading = true
			try {
				this.results = await searchMembers(term, {
					members: this.existingMembers,
					groups: this.existingGroups,
					groupSubname: this.t('planninq', 'Everyone in this group'),
					signal: this.abortController.signal,
				})
			} catch (err) {
				if (err.name === 'AbortError') {
					return
				}
				console.error('Member search failed:', err)
				showError(this.t('planninq', 'Could not search for people. Please try again.'))
				this.results = []
			} finally {
				this.loading = false
				this.searched = true
			}
		},

		/**
		 * Add the picked person or group to the project.
		 *
		 * @param {object|null} option The picked option.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
		 */
		async select(option) {
			if (!option) {
				return
			}
			try {
				const store = useProjectsStore()
				if (option.type === 'group') {
					await store.addMemberGroup(this.projectId, option.id)
				} else {
					await store.addMember(this.projectId, option.id)
				}
				this.query = ''
				this.results = []
				this.$emit('added', option)
			} catch {
				showError(this.t('planninq', 'Could not add member'))
			}
		},
	},
}
</script>

<style scoped>
.member-search__option {
	display: flex;
	align-items: center;
	gap: 8px;
}

.member-search__option-text {
	display: flex;
	flex-direction: column;
}

.member-search__subname {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>
