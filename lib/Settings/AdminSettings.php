<?php

/**
 * Planix Admin Settings
 *
 * One-line AppHost leaf class. Nextcloud instantiates the admin-settings panel
 * by the class name in info.xml `<settings><admin>`, and the
 * `#[AuthorizedAdminSetting(settings: AdminSettings::class)]` attribute on
 * LabelController is typed `class-string<IDelegatedSettings>` — a contract only
 * a real class can satisfy. So unlike the other AppHost leaf names (which stay
 * plain DI service-id strings in Application::registerAppHost()), this one
 * physically exists.
 *
 * It carries no behaviour of its own: form rendering, the version initial-state
 * and the IDelegatedSettings (#299) fail-closed admin gating all live in the
 * engine base class. Application::registerAppHostSettings() constructs it inside
 * a lazy factory closure, so the OpenRegister parent is still never autoloaded
 * at Nextcloud bootstrap — the lazy-by-construction invariant of ADR-040.
 *
 * @category Settings
 * @package  OCA\Planix\Settings
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

namespace OCA\Planix\Settings;

use OCA\OpenRegister\AppHost\Settings\GenericAdminSettings;

/**
 * AppHost-backed admin settings panel for Planix (ADR-040).
 *
 * @spec openspec/specs/admin-user-settings.md
 */
class AdminSettings extends GenericAdminSettings {
}//end class
