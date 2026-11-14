<?php

/**
 * Test stub of OpenRegister's FileUpload, with the signatures planninq calls.
 *
 * @category Test
 * @package  OCA\OpenRegister\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service\File;

final readonly class FileUpload {
	public function __construct(
		public string $fileName,
		public mixed $content,
		public bool $share,
		public array $tags = [],
	) {
	}
}
