<?php

/**
 * Stand-in for the DAV app's CalDAV backend class, so the export's class check passes in a unit run.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\DAV\CalDAV;

/**
 * Empty: the export only checks that the class exists and asks the container for the backend.
 */
class CalDavBackend {
}
