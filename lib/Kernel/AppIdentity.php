<?php

/**
 * Planninq AppIdentity
 *
 * The app id, readable without loading the application class.
 *
 * @category Kernel
 * @package  OCA\Planninq\Kernel
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural constant, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Kernel;

/**
 * The app id every component reads, so none of them depends on the
 * application bootstrap class for it.
 *
 * @category Kernel
 * @package  OCA\Planninq\Kernel
 * @author   Conduction Development Team <dev@conductio.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural constant, no functional requirement.
 */
final class AppIdentity {

	/**
	 * The app id.
	 *
	 * @var string
	 */
	public const APP_ID = 'planninq';
}//end class
