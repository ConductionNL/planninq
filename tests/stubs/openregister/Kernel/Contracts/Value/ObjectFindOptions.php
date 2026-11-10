<?php

/**
 * Test stub of OpenRegister's ObjectFindOptions (openregister #9116).
 *
 * Same constructor as lib/Kernel/Contracts/Value/ObjectFindOptions.php, so
 * planninq's find() calls can be tested without OpenRegister. The real
 * register and schema parameters also accept RegisterInterface and
 * SchemaInterface; planninq passes slugs and ids only, so the stub widens
 * those to object.
 *
 * @category Test
 * @package  OCA\OpenRegister\Kernel\Contracts\Value
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Kernel\Contracts\Value;

/**
 * What a find() reads: register, schema, extend, files, render and audit.
 */
final class ObjectFindOptions {
	/**
	 * Constructor.
	 *
	 * @param object|string|int|null $register The register (slug, id or entity).
	 * @param object|string|int|null $schema   The schema (slug, id or entity).
	 * @param array<mixed>|null      $extend   Properties to extend.
	 * @param bool                   $files    Whether to include files.
	 * @param bool                   $render   Whether to render the object.
	 * @param bool                   $audit    Whether to write a read audit entry.
	 */
	public function __construct(
		public readonly object|string|int|null $register=null,
		public readonly object|string|int|null $schema=null,
		public readonly ?array $extend=[],
		public readonly bool $files=false,
		public readonly bool $render=true,
		public readonly bool $audit=true,
	) {
	}//end __construct()
}//end class
