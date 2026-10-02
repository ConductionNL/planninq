<template>
	<div class="owner-group" data-testid="owner-group">
		<p class="owner-group__current" data-testid="owner-group-current">
			<template v-if="ownerGroup">
				{{ t('planninq', 'Owned by group: {name}', { name: groupName || ownerGroup }) }}
			</template>
			<template v-else>
				{{ t('planninq', 'No group owns this project.') }}
			</template>
		</p>
		<div v-if="canChange" class="owner-group__controls">
			<NcSelect
				:modelValue="null"
				:options="results"
				:inputLabel="t('planninq', 'Owned by group')"
				:placeholder="t('planninq', 'Search for a group…')"
				label="displayName"
				:filterable="false"
				:loading="loading"
				:clearSearchOnSelect="true"
				data-testid="owner-group-search"
				@search="onInput"
				@update:modelValue="select">
				<template #no-options>
					<span v-if="query.trim().length >= minLength && searched && !loading">
						{{ t('planninq', 'No group found. Your admin\'s sharing settings decide which groups you can find.') }}
					</span>
					<span v-else>
						{{ t('planninq', 'Type at least two characters') }}
					</span>
				</template>
			</NcSelect>
			<NcButton
				v-if="ownerGroup"
				variant="tertiary"
				data-testid="owner-group-clear"
				@click="change(null)">
				{{ t('planninq', 'No owning group') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
/**
 * OwnerGroupPicker component.
 *
 * Shows which Nextcloud group owns the project and lets the owner, anyone in
 * the owning group or an admin hand the project to a group, or take it back.
 * Every member of the owning group holds owner rights, so the project does not
 * depend on one account.
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
 */
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcSelect } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { MIN_SEARCH_LENGTH, SHARE_TYPE_GROUP, searchMembers } from '../utils/memberSearch.js'

export default {
	name: 'OwnerGroupPicker',

	components: { NcButton, NcSelect },

	props: {
		projectId: {
			type: String,
			required: true,
		},

		ownerGroup: {
			type: String,
			default: null,
		},

		groupName: {
			type: String,
			default: '',
		},

		canChange: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['changed'],

	data() {
		return {
			query: '',
			results: [],
			loading: false,
			searched: false,
			minLength: MIN_SEARCH_LENGTH,
			debounceTimer: null,
			abortController: null,
		}
	},

	beforeUnmount() {
		clearTimeout(this.debounceTimer)
		this.abortController?.abort()
	},

	methods: {
		/**
		 * Search for groups after a short pause in typing.
		 *
		 * @param {string} value The search text.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
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
		 * Ask the core autocomplete for groups only.
		 *
		 * @param {string} term The search text.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
		 */
		async search(term) {
			this.abortController = new AbortController()
			this.loading = true
			try {
				this.results = await searchMembers(term, {
					groups: this.ownerGroup ? [this.ownerGroup] : [],
					shareTypes: [SHARE_TYPE_GROUP],
					signal: this.abortController.signal,
				})
			} catch (err) {
				if (err.name === 'AbortError') {
					return
				}
				console.error('Group search failed:', err)
				showError(this.t('planninq', 'Could not search for groups. Please try again.'))
				this.results = []
			} finally {
				this.loading = false
				this.searched = true
			}
		},

		/**
		 * Hand the project to the picked group.
		 *
		 * @param {object|null} option The picked group.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
		 */
		select(option) {
			if (option) {
				this.change(option.id, option.displayName)
			}
		},

		/**
		 * Write the owning group, or clear it with null.
		 *
		 * @param {string|null} gid The group id.
		 * @param {string} name The group name, for the Members tab.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
		 */
		async change(gid, name = '') {
			try {
				await useProjectsStore().setOwnerGroup(this.projectId, gid)
				this.query = ''
				this.results = []
				this.$emit('changed', { id: gid, name })
			} catch {
				showError(this.t('planninq', 'Could not change the owning group'))
			}
		},
	},
}
</script>

<style scoped>
.owner-group {
	margin-block: 12px;
}

.owner-group__current {
	font-weight: bold;
}

.owner-group__controls {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 8px;
}
</style>
