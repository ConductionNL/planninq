<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
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
import memberSearchInput from '../mixins/memberSearchInput.js'
import { useProjectsStore } from '../store/projects.js'

export default {
	name: 'MemberSearch',

	components: { AccountGroupOutline, NcAvatar, NcSelect },

	mixins: [memberSearchInput],

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

	methods: {
		/**
		 * The search asks for people and groups, leaving out who is already
		 * on the project.
		 *
		 * @return {object} the searchMembers() options
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
		 */
		searchFilter() {
			return {
				members: this.existingMembers,
				groups: this.existingGroups,
				groupSubname: this.t('planninq', 'Everyone in this group'),
			}
		},

		/**
		 * Report a failed search.
		 *
		 * @param {Error} err The error.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
		 */
		onSearchError(err) {
			console.error('Member search failed:', err)
			showError(this.t('planninq', 'Could not search for people. Please try again.'))
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
