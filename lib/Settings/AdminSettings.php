<?php

/**
 * Planix Admin Settings
 *
 * Provides the admin settings form for the Planix application.
 *
 * @category Settings
 * @package  OCA\Planix\Settings
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
 */

declare(strict_types=1);

namespace OCA\Planix\Settings;

use OCA\Planix\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\IDelegatedSettings;

/**
 * Provides the admin settings form for the Planix application.
 *
 * Implements IDelegatedSettings (a superset of ISettings) so the admin-only
 * label-management API endpoints can reference this class via
 * #[AuthorizedAdminSetting(self::class)] for a declarative, middleware-enforced
 * admin gate (matching the controller body's isCurrentUserAdmin() check).
 */
class AdminSettings implements IDelegatedSettings
{
    /**
     * Constructor.
     *
     * @param IAppManager   $appManager   The app manager.
     * @param IInitialState $initialState The initial state service.
     */
    public function __construct(
        private IAppManager $appManager,
        private IInitialState $initialState,
    ) {
    }//end __construct()

    /**
     * Get the settings form template.
     *
     * @return TemplateResponse
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
     */
    public function getForm(): TemplateResponse
    {
        $version = $this->appManager->getAppVersion(appId: Application::APP_ID);

        $this->initialState->provideInitialState('version', $version);

        return new TemplateResponse(
            Application::APP_ID,
            'settings/admin',
            []
        );
    }//end getForm()

    /**
     * Get the section ID this settings page belongs to.
     *
     * @return string
     */
    public function getSection(): string
    {
        return 'planix';
    }//end getSection()

    /**
     * Get the priority for ordering within the section.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 10;
    }//end getPriority()

    /**
     * Human-readable name of this delegated settings page.
     *
     * @return string|null
     *
     * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
     */
    public function getName(): ?string
    {
        return null;
    }//end getName()

    /**
     * App-config keys an admin delegated to this section may manage.
     *
     * Planix does not delegate granular app-config keys to non-admin delegates;
     * the section remains full-admin only.
     *
     * @return array<string,string>
     *
     * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
     */
    public function getAuthorizedAppConfig(): array
    {
        return [];
    }//end getAuthorizedAppConfig()
}//end class
