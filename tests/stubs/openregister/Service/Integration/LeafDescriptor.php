<?php

/**
 * Test-only stub for OpenRegister's LeafDescriptor.
 *
 * TEST-ONLY — see tests/stubs/openregister/Db/ObjectEntity.php for the full
 * rationale. Reachable only via the PSR-4 prefix appended by
 * tests/bootstrap-unit.php, never from composer.json's autoload map.
 *
 * Mirrored from openregister/lib/Service/Integration/LeafDescriptor.php: the
 * constructor's named parameters and the getters the listener test reads back.
 * The real class also validates its kinds, surfaces and render mode; this stub
 * deliberately does NOT, because a stub that reimplements validation tests the
 * stub rather than the listener.
 *
 * @category Test
 * @package  OCA\OpenRegister\Service\Integration
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

namespace OCA\OpenRegister\Service\Integration;

/**
 * Test-only stub of a leaf descriptor.
 */
class LeafDescriptor {
	/**
	 * A tab/widget rendered alongside an object.
	 *
	 * @var string
	 */
	public const KIND_RENDER_SURFACE = 'render-surface';

	/**
	 * A provider serving the app's own data for an object.
	 *
	 * @var string
	 */
	public const KIND_DATA_PROVIDER = 'data-provider';

	/**
	 * The host renders the leaf's component itself.
	 *
	 * @var string
	 */
	/**
	 * Load strategies, mirroring openregister#3956.
	 */
	public const LOADS_VIA_SHARED_ENTRY = 'shared-entry';

	public const LOADS_VIA_OWN_SCRIPT = 'own-script';

	public const LOADS_ALREADY_PRESENT = 'already-present';

	public const RENDER_MODE_COMPONENT = 'component';

	/**
	 * The host hands over a bare element and the leaf mounts into it.
	 *
	 * @var string
	 */
	public const RENDER_MODE_MOUNT = 'mount';

	private string $id;

	private string $label;

	private string $icon;

	private ?string $requiredApp;

	private ?string $group;

	private array $surfaces;

	private ?string $referenceType;

	private ?string $requiresPermission;

	private string $renderMode;

	private ?string $loadStrategy;

	/**
	 * Constructor.
	 *
	 * @param LeafIdentity          $identity     Id, label, icon and group.
	 * @param array                 $kinds        The leaf kinds.
	 * @param LeafAvailability|null $availability Required app and permission.
	 * @param LeafRendering|null    $rendering    Surfaces, reference type, render mode, load strategy.
	 */
	public function __construct(
		LeafIdentity $identity,
		private array $kinds,
		?LeafAvailability $availability = null,
		?LeafRendering $rendering = null,
	) {
		$availability = $availability ?? new LeafAvailability();
		$rendering = $rendering ?? new LeafRendering();
		$this->id = $identity->id;
		$this->label = $identity->label;
		$this->icon = $identity->icon;
		$this->requiredApp = $availability->requiredApp;
		$this->group = $identity->group;
		$this->surfaces = $rendering->surfaces;
		$this->referenceType = $rendering->referenceType;
		$this->requiresPermission = $availability->requiresPermission;
		$this->renderMode = $rendering->renderMode;
		$this->loadStrategy = $rendering->loadStrategy;
	}//end __construct()

	/**
	 * The leaf id.
	 *
	 * @return string The id.
	 */
	public function getId(): string {
		return $this->id;
	}//end getId()

	/**
	 * The label.
	 *
	 * @return string The label.
	 */
	public function getLabel(): string {
		return $this->label;
	}//end getLabel()

	/**
	 * The icon name.
	 *
	 * @return string The icon.
	 */
	public function getIcon(): string {
		return $this->icon;
	}//end getIcon()

	/**
	 * The declared kinds.
	 *
	 * @return array The kinds.
	 */
	public function getKinds(): array {
		return $this->kinds;
	}//end getKinds()

	/**
	 * The app that must be installed.
	 *
	 * @return string|null The app id.
	 */
	public function getRequiredApp(): ?string {
		return $this->requiredApp;
	}//end getRequiredApp()

	/**
	 * The admin-UI group.
	 *
	 * @return string|null The group.
	 */
	public function getGroup(): ?string {
		return $this->group;
	}//end getGroup()

	/**
	 * The render surfaces.
	 *
	 * @return array The surfaces.
	 */
	public function getSurfaces(): array {
		return $this->surfaces;
	}//end getSurfaces()

	/**
	 * The AD-18 reference-type marker.
	 *
	 * @return string|null The reference type.
	 */
	public function getReferenceType(): ?string {
		return $this->referenceType;
	}//end getReferenceType()

	/**
	 * The permission gate. openregister main names this requiresPermission();
	 * the old getRequiresPermission() name would pass the suite and fatal at
	 * runtime.
	 *
	 * @return string|null The permission.
	 */
	public function requiresPermission(): ?string {
		return $this->requiresPermission;
	}//end requiresPermission()

	/**
	 * The render mode.
	 *
	 * @return string The render mode.
	 */
	public function getRenderMode(): string {
		return $this->renderMode;
	}//end getRenderMode()

	/**
	 * How the render bundle reaches the page, or null when the leaf has not said.
	 *
	 * @return string|null The load strategy.
	 */
	public function getLoadStrategy(): ?string {
		return $this->loadStrategy;
	}//end getLoadStrategy()

	/**
	 * Whether the descriptor claims the shared render entry.
	 *
	 * @return bool True when the load strategy is the shared entry.
	 */
	public function claimsSharedEntry(): bool {
		return ($this->loadStrategy === self::LOADS_VIA_SHARED_ENTRY);
	}//end claimsSharedEntry()

	/**
	 * Whether the descriptor declares the given kind.
	 *
	 * @param string $kind One of the KIND_* constants.
	 *
	 * @return bool True when the kind is declared.
	 */
	public function hasKind(string $kind): bool {
		return in_array($kind, $this->kinds, true);
	}//end hasKind()
}//end class
