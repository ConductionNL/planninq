<?php

/**
 * Planix Settings Section
 *
 * Defines the Planix section in the Nextcloud admin settings.
 *
 * @category Sections
 * @package  OCA\Planix\Sections
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Sections;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Defines the Planix section in the Nextcloud admin settings.
 */
class SettingsSection implements IIconSection
{
    /**
     * Constructor for SettingsSection.
     *
     * @param IL10N         $l            The localization service
     * @param IURLGenerator $urlGenerator The URL generator service
     *
     * @return void
     */
    public function __construct(
        private IL10N $l,
        private IURLGenerator $urlGenerator,
    ) {
    }//end __construct()

    /**
     * Get the section identifier.
     *
     * @return string
     */
    public function getID(): string
    {
        return 'planix';
    }//end getID()

    /**
     * Get the display name of this section.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->l->t('Planix');
    }//end getName()

    /**
     * Get the priority for ordering this section.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 75;
    }//end getPriority()

    /**
     * Get the icon path for this section.
     *
     * @return string
     */
    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath(appName: 'planix', file: 'app-dark.svg');
    }//end getIcon()
}//end class
