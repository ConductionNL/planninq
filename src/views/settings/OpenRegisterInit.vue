<template>
	<CnSettingsSection
		:name="t('planix', 'OpenRegister Initialization')"
		:description="t('planix', 'Status of the Planix register in OpenRegister and initialization controls.')">
		<div class="openregister-init">
			<div class="status-row">
				<template v-if="isAvailable">
					<CheckCircleIcon class="status-icon status-icon--success" :size="20" />
					<span>{{ t('planix', 'OpenRegister is available') }}</span>
				</template>
				<template v-else>
					<AlertCircleIcon class="status-icon status-icon--warning" :size="20" />
					<span>{{ t('planix', 'OpenRegister is not installed or enabled') }}</span>
				</template>
			</div>

			<div v-if="isAvailable && isAdmin" class="init-action">
				<NcButton
					type="secondary"
					:disabled="loading"
					@click="initialize">
					{{ loading ? t('planix', 'Initializing...') : t('planix', 'Initialize register') }}
				</NcButton>
			</div>

			<div v-if="resultMessage" class="feedback" :class="resultSuccess ? 'success-message' : 'error-message'">
				{{ resultMessage }}
			</div>
		</div>
	</CnSettingsSection>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle.vue'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import { generateUrl } from '@nextcloud/router'
import { useSettingsStore } from '../../store/modules/settings.js'

export default {
	name: 'OpenRegisterInit',
	components: {
		NcButton,
		CnSettingsSection,
		CheckCircleIcon,
		AlertCircleIcon,
	},
	data() {
		const settingsStore = useSettingsStore()
		return {
			isAvailable: settingsStore.hasOpenRegisters,
			isAdmin: settingsStore.isAdmin,
			loading: false,
			resultMessage: '',
			resultSuccess: false,
		}
	},
	methods: {
		async initialize() {
			this.loading = true
			this.resultMessage = ''
			try {
				const response = await fetch(generateUrl('/apps/planix/api/settings/load'), {
					method: 'POST',
					headers: { requesttoken: OC.requestToken },
				})
				const data = await response.json()
				this.resultSuccess = !!data?.success
				this.resultMessage = data?.message ?? (this.resultSuccess
					? t('planix', 'Register initialized successfully')
					: t('planix', 'Initialization failed'))
			} catch (error) {
				this.resultSuccess = false
				this.resultMessage = t('planix', 'Initialization request failed')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.openregister-init {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.status-row {
	display: flex;
	align-items: center;
	gap: 8px;
}
.status-icon--success {
	color: var(--color-success);
}
.status-icon--warning {
	color: var(--color-warning);
}
.init-action {
	display: flex;
}
.feedback {
	padding: 4px 0;
}
.success-message {
	color: var(--color-success);
}
.error-message {
	color: var(--color-error);
}
</style>
