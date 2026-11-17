<?php

/**
 * Planninq MIME Message Parser
 *
 * Turns a raw RFC 5322 message into the parts the intake reads.
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
 * Reads a raw message: the headers the intake needs (decoded, addresses
 * lower-cased), the first plain-text and the first HTML body (decoded and in
 * UTF-8) and every other part as an attachment with its content.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */
final class MimeMessageParser {

	/**
	 * Nested multiparts deeper than this are not read.
	 */
	private const MAX_DEPTH = 10;

	/**
	 * Parse a message.
	 *
	 * @param string $uid The message's id in its folder.
	 * @param string $raw The message as the server sent it, or only its header block.
	 *
	 * @return IncomingMail
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function parse(string $uid, string $raw): IncomingMail {
		[$headerBlock, $body] = self::splitHeaders(raw: $raw);
		$headers = self::parseHeaders(block: $headerBlock);

		$parts = ['text' => null, 'html' => null, 'attachments' => []];
		$this->walk(headers: $headers, body: $body, parts: $parts, depth: 0);

		return new IncomingMail(
			uid: $uid,
			message: [
				'messageId' => trim((string)(self::first(headers: $headers, name: 'message-id') ?? '')),
				'from' => (self::addresses(value: (string)(self::first(headers: $headers, name: 'from') ?? ''))[0] ?? ''),
				'to' => self::addresses(value: implode(',', ($headers['to'] ?? []))),
				'cc' => self::addresses(value: implode(',', ($headers['cc'] ?? []))),
				'subject' => self::decodeHeader(value: (string)(self::first(headers: $headers, name: 'subject') ?? '')),
				'textBody' => ($parts['text'] ?? ''),
				'htmlBody' => ($parts['html'] ?? ''),
				'authenticationResults' => self::first(headers: $headers, name: 'authentication-results'),
				'attachments' => $parts['attachments'],
			]
		);
	}//end parse()

	/**
	 * The addresses in an address header, lower-cased, without display names.
	 *
	 * @param string $value The header value, e.g. `"Anna, VC" <Anna@Gemeente.nl>, jan@gemeente.nl`.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public static function addresses(string $value): array {
		// Quoted display names and comments may hold commas and @ signs.
		$value = (string)preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '', $value);
		$value = (string)preg_replace('/\((?:[^()\\\\]|\\\\.)*\)/', '', $value);

		$found = [];
		foreach (explode(',', $value) as $entry) {
			if (preg_match('/<([^<>\s]+@[^<>\s]+)>/', $entry, $match) === 1) {
				$found[] = $match[1];
				continue;
			}

			if (preg_match('/([^\s<>;:]+@[^\s<>;:]+)/', $entry, $match) === 1) {
				$found[] = $match[1];
			}
		}

		return array_values(array_unique(array_map(static fn (string $address): string => strtolower(trim($address)), $found)));
	}//end addresses()

	/**
	 * Decode a header that may hold RFC 2047 encoded words, into UTF-8.
	 *
	 * @param string $value The raw header value.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public static function decodeHeader(string $value): string {
		$value = trim($value);
		if (str_contains($value, '=?') === false) {
			return self::utf8(text: $value, charset: 'utf-8');
		}

		// Whitespace between two encoded words is not part of the text (RFC 2047 section 6.2).
		$value   = (string)preg_replace('/(\?=)\s+(=\?)/', '$1$2', $value);
		$decoded = (string)preg_replace_callback(
			'/=\?([^?]+)\?([bBqQ])\?([^?]*)\?=/',
			static function (array $word): string {
				$charset = (string)preg_replace('/\*.*$/', '', $word[1]);
				if (strtoupper($word[2]) === 'B') {
					return self::utf8(text: (string)base64_decode($word[3], false), charset: $charset);
				}

				return self::utf8(text: quoted_printable_decode(str_replace('_', ' ', $word[3])), charset: $charset);
			},
			$value
		);

		return $decoded;
	}//end decodeHeader()

	/**
	 * Walk one part: a multipart is read part by part, a leaf becomes a body or an attachment.
	 *
	 * @param array<string,array<int,string>>                     $headers The part's headers.
	 * @param string                                              $body    The part's body.
	 * @param array{text:?string,html:?string,attachments:array} $parts   What was found so far.
	 * @param int                                                 $depth   How deep in the tree.
	 *
	 * @return void
	 */
	private function walk(array $headers, string $body, array &$parts, int $depth): void {
		[$type, $params] = self::contentType(headers: $headers);

		if (str_starts_with($type, 'multipart/') === true) {
			$boundary = ($params['boundary'] ?? '');
			if ($boundary === '' || $depth >= self::MAX_DEPTH) {
				return;
			}

			foreach (self::splitMultipart(body: $body, boundary: $boundary) as $part) {
				[$partHeaders, $partBody] = self::splitHeaders(raw: $part);
				$this->walk(headers: self::parseHeaders(block: $partHeaders), body: $partBody, parts: $parts, depth: ($depth + 1));
			}

			return;
		}

		$encoding = strtolower(trim((string)(self::first(headers: $headers, name: 'content-transfer-encoding') ?? '')));
		$content  = self::decodeBody(body: $body, encoding: $encoding);
		[$disposition, $dispositionParams] = self::parameterised(value: (string)(self::first(headers: $headers, name: 'content-disposition') ?? ''));
		$name = self::decodeHeader(value: ($dispositionParams['filename'] ?? ($params['name'] ?? '')));

		$isText = ($type === 'text/plain' || $type === 'text/html');
		if ($isText === true && $disposition !== 'attachment' && $name === '') {
			$key = 'html';
			if ($type === 'text/plain') {
				$key = 'text';
			}

			if ($parts[$key] === null) {
				$parts[$key] = str_replace("\r\n", "\n", self::utf8(text: $content, charset: ($params['charset'] ?? 'us-ascii')));
				return;
			}
		}

		if ($name === '') {
			$name = 'attachment-'.(count($parts['attachments']) + 1);
			if ($type === 'message/rfc822') {
				$name = 'message.eml';
			}
		}

		$parts['attachments'][] = ['name' => basename(str_replace('\\', '/', $name)), 'mimeType' => $type, 'content' => $content];
	}//end walk()

	/**
	 * Split a message or part at its first empty line.
	 *
	 * @param string $raw The message or part.
	 *
	 * @return array{0:string,1:string} The header block and the body.
	 */
	private static function splitHeaders(string $raw): array {
		if (preg_match('/\r?\n\r?\n/', $raw, $match, PREG_OFFSET_CAPTURE) !== 1) {
			return [$raw, ''];
		}

		$at = (int)$match[0][1];
		return [substr($raw, 0, $at), substr($raw, ($at + strlen($match[0][0])))];
	}//end splitHeaders()

	/**
	 * The headers of a block, by lower-cased name, in their order, unfolded.
	 *
	 * @param string $block The header block.
	 *
	 * @return array<string,array<int,string>>
	 */
	private static function parseHeaders(string $block): array {
		$unfolded = (string)preg_replace('/\r?\n[ \t]+/', ' ', $block);
		$headers  = [];
		foreach (self::lines(text: $unfolded) as $line) {
			$colon = strpos($line, ':');
			if ($colon === false || $colon === 0) {
				continue;
			}

			$headers[strtolower(trim(substr($line, 0, $colon)))][] = trim(substr($line, ($colon + 1)));
		}

		return $headers;
	}//end parseHeaders()

	/**
	 * The first value of a header, or null.
	 *
	 * @param array<string,array<int,string>> $headers The headers.
	 * @param string                          $name    The lower-cased name.
	 *
	 * @return string|null
	 */
	private static function first(array $headers, string $name): ?string {
		return ($headers[$name][0] ?? null);
	}//end first()

	/**
	 * The media type of a part, lower-cased, and its parameters; text/plain when absent.
	 *
	 * @param array<string,array<int,string>> $headers The part's headers.
	 *
	 * @return array{0:string,1:array<string,string>}
	 */
	private static function contentType(array $headers): array {
		[$type, $params] = self::parameterised(value: (string)(self::first(headers: $headers, name: 'content-type') ?? ''));
		if (preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $type) !== 1) {
			$type = 'text/plain';
		}

		return [$type, $params];
	}//end contentType()

	/**
	 * A value with `; name=value` parameters, such as Content-Type or Content-Disposition.
	 * RFC 2231 extended and continued parameters are joined and decoded.
	 *
	 * @param string $value The header value.
	 *
	 * @return array{0:string,1:array<string,string>} The lower-cased value and the parameters by lower-cased name.
	 */
	private static function parameterised(string $value): array {
		$pieces = self::splitParameters(value: $value);
		$main   = strtolower(trim((string)array_shift($pieces)));

		$plain    = [];
		$extended = [];
		foreach ($pieces as $piece) {
			$equals = strpos($piece, '=');
			if ($equals === false) {
				continue;
			}

			$name = strtolower(trim(substr($piece, 0, $equals)));
			$raw  = trim(substr($piece, ($equals + 1)));
			if (strlen($raw) >= 2 && $raw[0] === '"' && substr($raw, -1) === '"') {
				$raw = (string)preg_replace('/\\\\(.)/s', '$1', substr($raw, 1, -1));
			}

			if (preg_match('/^([^*]+)\*(?:(\d+)\*?|)$/', $name, $match) === 1 && str_contains($name, '*') === true) {
				$index = 0;
				if (isset($match[2]) === true) {
					$index = (int)$match[2];
				}

				$extended[$match[1]][$index] = ['value' => $raw, 'encoded' => str_ends_with($name, '*')];
				continue;
			}

			$plain[$name] = $raw;
		}

		foreach ($extended as $name => $sections) {
			ksort($sections);
			$plain[$name] = self::rfc2231(sections: $sections);
		}

		return [$main, $plain];
	}//end parameterised()

	/**
	 * Split a header value at the semicolons outside quotes.
	 *
	 * @param string $value The header value.
	 *
	 * @return array<int,string>
	 */
	private static function splitParameters(string $value): array {
		$pieces  = [];
		$current = '';
		$quoted  = false;
		$length  = strlen($value);
		for ($i = 0; $i < $length; $i++) {
			$char = $value[$i];
			if ($char === '\\' && $quoted === true && $i + 1 < $length) {
				$current .= $char.$value[++$i];
				continue;
			}

			if ($char === '"') {
				$quoted = !$quoted;
			}

			if ($char === ';' && $quoted === false) {
				$pieces[] = $current;
				$current  = '';
				continue;
			}

			$current .= $char;
		}

		$pieces[] = $current;
		return $pieces;
	}//end splitParameters()

	/**
	 * Join RFC 2231 sections into one UTF-8 value.
	 *
	 * @param array<int,array{value:string,encoded:bool}> $sections The sections in order.
	 *
	 * @return string
	 */
	private static function rfc2231(array $sections): string {
		$charset = 'us-ascii';
		$text    = '';
		$first   = true;
		foreach ($sections as $section) {
			$value = $section['value'];
			if ($section['encoded'] === true) {
				if ($first === true && preg_match("/^([^']*)'[^']*'(.*)$/s", $value, $match) === 1) {
					if ($match[1] !== '') {
						$charset = $match[1];
					}

					$value   = $match[2];
				}

				$value = rawurldecode($value);
			}

			$text .= $value;
			$first = false;
		}

		return self::utf8(text: $text, charset: $charset);
	}//end rfc2231()

	/**
	 * The parts of a multipart body, without preamble and epilogue.
	 *
	 * @param string $body     The multipart body.
	 * @param string $boundary The boundary.
	 *
	 * @return array<int,string>
	 */
	private static function splitMultipart(string $body, string $boundary): array {
		$parts   = [];
		$current = null;
		foreach (self::lines(text: $body) as $line) {
			$trimmed = rtrim($line, " \t");
			if ($trimmed === '--'.$boundary.'--') {
				break;
			}

			if ($trimmed === '--'.$boundary) {
				if ($current !== null) {
					$parts[] = implode("\r\n", $current);
				}

				$current = [];
				continue;
			}

			if ($current !== null) {
				$current[] = $line;
			}
		}

		if ($current !== null) {
			$parts[] = implode("\r\n", $current);
		}

		return $parts;
	}//end splitMultipart()

	/**
	 * Undo a Content-Transfer-Encoding.
	 *
	 * @param string $body     The encoded body.
	 * @param string $encoding The lower-cased encoding.
	 *
	 * @return string
	 */
	private static function decodeBody(string $body, string $encoding): string {
		if ($encoding === 'base64') {
			return (string)base64_decode((string)preg_replace('/[^A-Za-z0-9+\/=]/', '', $body), false);
		}

		if ($encoding === 'quoted-printable') {
			return quoted_printable_decode($body);
		}

		return $body;
	}//end decodeBody()

	/**
	 * Text in a charset, as valid UTF-8.
	 *
	 * @param string $text    The text.
	 * @param string $charset Its charset.
	 *
	 * @return string
	 */
	private static function utf8(string $text, string $charset): string {
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
	 * The lines of a text, without their line endings.
	 *
	 * @param string $text The text.
	 *
	 * @return array<int,string>
	 */
	private static function lines(string $text): array {
		$lines = preg_split('/\r?\n/', $text);
		if ($lines === false) {
			return [];
		}

		return $lines;
	}//end lines()

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
