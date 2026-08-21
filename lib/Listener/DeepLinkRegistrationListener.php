<?php

/**
 * Planix Deep-Link Registration Listener
 *
 * One-line AppHost leaf class. `IRegistrationContext::registerEventListener()`
 * is typed `class-string<IEventListener<Event>>`, a contract only a real class
 * can satisfy — so unlike the other AppHost leaf names (which stay plain DI
 * service-id strings in Application::registerAppHost()), this one physically
 * exists.
 *
 * It carries no behaviour of its own: answering OpenRegister's
 * DeepLinkRegistrationEvent from the app's src/manifest.json `deepLinks` block
 * lives entirely in the engine base class.
 * Application::registerAppHostDeepLinks() constructs it inside a lazy factory
 * closure, so the OpenRegister parent is still never autoloaded at Nextcloud
 * bootstrap — the lazy-by-construction invariant of ADR-040. The event it
 * listens for is only ever dispatched by OpenRegister itself, so with
 * OpenRegister absent this class is never resolved at all.
 *
 * @category Listener
 * @package  OCA\Planix\Listener
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

namespace OCA\Planix\Listener;

use OCA\OpenRegister\AppHost\Listener\GenericDeepLinkRegistrationListener;

/**
 * AppHost-backed deep-link registration listener for Planix (ADR-040).
 */
class DeepLinkRegistrationListener extends GenericDeepLinkRegistrationListener {
}//end class
