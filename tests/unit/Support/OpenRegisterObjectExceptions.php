<?php

/**
 * OpenRegister's validation and provider exceptions, declared for unit tests that run without openregister.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Support
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Exception;

if (class_exists(ValidationException::class) === false) {
	/**
	 * Thrown by OpenRegister when an object fails its schema.
	 */
	class ValidationException extends \Exception {
	}
}

if (class_exists(CustomValidationException::class) === false) {
	/**
	 * Thrown by OpenRegister when an object fails a custom validation rule.
	 */
	class CustomValidationException extends \Exception {
	}
}

if (class_exists(ProviderUnavailableException::class) === false) {
	/**
	 * Thrown by OpenRegister when an upstream data provider cannot be reached.
	 */
	class ProviderUnavailableException extends \Exception {
	}
}
