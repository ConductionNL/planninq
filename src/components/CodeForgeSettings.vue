<template>
	<!-- Code forges (integration-code-forge-links): how to connect GitHub through integriq -->
	<CnSettingsSection
		:name="t('planninq', 'Code forges')"
		:description="t('planninq', 'Link GitHub commits and pull requests to tasks. Integriq receives the events; planninq never calls GitHub.')"
		data-testid="code-forge-settings">
		<ol class="code-forge-settings__steps">
			<li>{{ t('planninq', 'Download the configuration and import it in Integriq. Then fill in a secret on the rule "Check the GitHub signature".') }}</li>
			<li>
				{{ t('planninq', 'In GitHub, add a webhook to the repository with this address and the same secret. Choose content type application/json and the push and pull request events:') }}
				<code class="code-forge-settings__url" data-testid="code-forge-webhook-url">{{ webhookUrl }}</code>
			</li>
			<li>{{ t('planninq', 'Name the task key, such as VC-12, in a commit message, branch name or pull request title. The link then appears on that task.') }}</li>
		</ol>
		<p class="code-forge-settings__hint">
			{{ t('planninq', 'Without Integriq, project members can still paste a link on the task page.') }}
		</p>
		<NcButton variant="secondary" data-testid="code-forge-download" @click="download">
			{{ t('planninq', 'Download the Integriq configuration') }}
		</NcButton>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcButton } from '@nextcloud/vue'
import configuration from '../../lib/Settings/integriq/planninq-code-forge.json'

/**
 * CodeForgeSettings: the admin steps to link GitHub to tasks through
 * integriq, the webhook address, and the configuration file to import.
 *
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-3.2
 */
export default {
	name: 'CodeForgeSettings',

	components: {
		CnSettingsSection,
		NcButton,
	},

	computed: {
		/**
		 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-3.2
		 * @return {string} The absolute address of integriq's GitHub endpoint
		 */
		webhookUrl() {
			return window.location.origin + generateUrl('/apps/integriq/api/endpoint/planninq/code-forge/github')
		},
	},

	methods: {
		t,

		/**
		 * Offer the shipped configuration as a file.
		 *
		 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-3.2
		 */
		download() {
			const blob = new Blob([JSON.stringify(configuration, null, 2)], { type: 'application/json' })
			const link = document.createElement('a')
			link.href = URL.createObjectURL(blob)
			link.download = 'planninq-code-forge.json'
			link.click()
			URL.revokeObjectURL(link.href)
		},
	},
}
</script>

<style scoped>
.code-forge-settings__steps {
	padding-inline-start: calc(var(--default-grid-baseline) * 5);
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}

.code-forge-settings__steps li {
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.code-forge-settings__url {
	display: block;
	margin-top: var(--default-grid-baseline);
	word-break: break-all;
}

.code-forge-settings__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}
</style>
