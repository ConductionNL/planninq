<?php

/**
 * Planix Activity Filter.
 *
 * Exposes the "Planix" filter in the Nextcloud Activity app so members can
 * narrow the stream to planix task events only.
 *
 * @category Activity
 * @package  OCA\Planix\Activity
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/task-collaboration.md
 */

declare(strict_types=1);

namespace OCA\Planix\Activity;

use OCA\Planix\AppInfo\Application;
use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Activity filter for planix task events.
 */
class Filter implements IFilter
{
    /**
     * The single activity type planix publishes under.
     *
     * @var string
     */
    public const ACTIVITY_TYPE = 'planix_task';

    /**
     * Constructor.
     *
     * @param IL10N         $l            The localization service.
     * @param IURLGenerator $urlGenerator The URL generator.
     */
    public function __construct(
        private IL10N $l,
        private IURLGenerator $urlGenerator,
    ) {
    }//end __construct()

    /**
     * Get the unique identifier of the filter.
     *
     * @return string The filter identifier.
     */
    public function getIdentifier(): string
    {
        return Application::APP_ID;
    }//end getIdentifier()

    /**
     * Get the human-readable name of the filter.
     *
     * @return string The filter name.
     */
    public function getName(): string
    {
        return $this->l->t('Planix');
    }//end getName()

    /**
     * Get the priority of the filter.
     *
     * @return int The filter priority (0-99; lower sorts earlier).
     */
    public function getPriority(): int
    {
        return 50;
    }//end getPriority()

    /**
     * Get the icon URL for the filter.
     *
     * @return string The absolute icon URL.
     */
    public function getIcon(): string
    {
        return $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath(appName: Application::APP_ID, file: 'app.svg')
        );
    }//end getIcon()

    /**
     * Restrict the visible activity types to planix task events.
     *
     * @param array $types The available types.
     *
     * @return array<array-key, string> The filtered types.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $types required by IFilter interface
     */
    public function filterTypes(array $types): array
    {
        return [self::ACTIVITY_TYPE];
    }//end filterTypes()

    /**
     * Get the allowed apps for this filter.
     *
     * @return array<array-key, string> The allowed app IDs.
     */
    public function allowedApps(): array
    {
        return [Application::APP_ID];
    }//end allowedApps()
}//end class
