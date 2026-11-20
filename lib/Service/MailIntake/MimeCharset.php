<?php

/**
 * Planninq MIME Charset
 *
 * Converts text in a MIME-declared charset to valid UTF-8.
 *
 * @category Service
 * @package  OCA\Planninq\Service\MailIntake
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Service\MailIntake;

/**
 * Text in the charset a header or body part names, as valid UTF-8: a charset
 * mbstring knows is converted, and invalid bytes are scrubbed.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */
final class MimeCharset {

	/**
	 * Text in a charset, as valid UTF-8.
	 *
	 * @param string $text    The text.
	 * @param string $charset Its charset.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function utf8(string $text, string $charset): string {
		$charset = strtolower(trim($charset, " \t\"'"));
		if ($charset !== '' && in_array($charset, ['utf-8', 'utf8', 'us-ascii', 'ascii'], true) === false) {
			if (isset(self::knownCharsets()[$charset]) === true) {
				$text = (string)mb_convert_encoding($text, 'UTF-8', $charset);
			}
		}

		if (mb_check_encoding($text, 'UTF-8') === true) {
			return $text;
		}

		return mb_scrub($text, 'UTF-8');
	}//end utf8()

	/**
	 * The character sets mbstring converts from, by lower-cased name and alias.
	 *
	 * @return array<string,true>
	 */
	private static function knownCharsets(): array {
		static $known = null;
		if ($known === null) {
			$known = [];
			// Transfer encodings and entity forms are not character sets.
			$notCharsets = ['base64', 'uuencode', 'html-entities', 'quoted-printable', '7bit', '8bit', 'pass', 'wchar'];
			foreach (mb_list_encodings() as $encoding) {
				if (in_array(strtolower($encoding), $notCharsets, true) === true) {
					continue;
				}

				$known[strtolower($encoding)] = true;
				foreach (mb_encoding_aliases($encoding) as $alias) {
					$known[strtolower($alias)] = true;
				}
			}
		}

		return $known;
	}//end knownCharsets()
}//end class
