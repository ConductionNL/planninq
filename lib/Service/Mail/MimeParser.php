<?php

/**
 * Planninq MimeParser
 *
 * Reads a raw RFC 822 message into an IncomingMail: decoded headers, the
 * plain-text body (HTML converted to text) and the attached files.
 *
 * @category Service
 * @package  OCA\Planninq\Service\Mail
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
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service\Mail;

/**
 * Parses a raw message.
 */
class MimeParser {

	/**
	 * Parse a raw message.
	 *
	 * @param string $uid The mailbox id to carry on the result.
	 * @param string $raw The raw message.
	 *
	 * @return IncomingMail
	 */
	public function parse(string $uid, string $raw): IncomingMail {
		[$headers, $body] = $this->split(raw: $raw);
		$text  = '';
		$files = [];
		$this->walk(headers: $headers, body: $body, text: $text, files: $files);

		$recipients = array_merge(
			$this->addresses(value: ($headers['to'] ?? '')),
			$this->addresses(value: ($headers['cc'] ?? ''))
		);
		$from = ($this->addresses(value: ($headers['from'] ?? ''))[0] ?? '');

		return new IncomingMail(
			uid: $uid,
			messageId: trim((string)($headers['message-id'] ?? ''), " <>\t"),
			from: $from,
			recipients: $recipients,
			subject: $this->decodeHeader(value: ($headers['subject'] ?? '')),
			body: trim($text),
			authResults: ($headers['authentication-results'] ?? ''),
			attachments: $files,
		);
	}//end parse()

	/**
	 * Split a message or part into unfolded lower-case headers and its body.
	 *
	 * @param string $raw The raw text.
	 *
	 * @return array{0:array<string,string>,1:string}
	 */
	private function split(string $raw): array {
		$raw = str_replace("\r\n", "\n", $raw);
		$cut = strpos($raw, "\n\n");
		$head = $raw;
		$body = '';
		if ($cut !== false) {
			$head = substr($raw, 0, $cut);
			$body = substr($raw, ($cut + 2));
		}

		$headers = [];
		$name    = '';
		foreach (explode("\n", $head) as $line) {
			if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $name !== '') {
				$headers[$name] .= ' ' . trim($line);
				continue;
			}

			$colon = strpos($line, ':');
			if ($colon === false) {
				continue;
			}

			$name = strtolower(substr($line, 0, $colon));
			// The first occurrence wins: Authentication-Results is added by the receiving server on top.
			if (isset($headers[$name]) === true) {
				$name = $name . '#' . count($headers);
			}

			$headers[$name] = trim(substr($line, ($colon + 1)));
		}

		return [$headers, $body];
	}//end split()

	/**
	 * Collect the text and the files of one part, recursing into multiparts.
	 *
	 * @param array<string,string>                                          $headers The part's headers.
	 * @param string                                                        $body    The part's body.
	 * @param string                                                        $text    The text collected so far.
	 * @param array<int,array{name:string,size:int,content:string}>         $files   The files collected so far.
	 *
	 * @return void
	 */
	private function walk(array $headers, string $body, string &$text, array &$files): void {
		$type = strtolower((string)($headers['content-type'] ?? 'text/plain'));
		if (preg_match('/^multipart\/[a-z-]+.*boundary="?([^";]+)"?/is', $type . ' ' . ($headers['content-type'] ?? ''), $found) === 1) {
			foreach ($this->parts(body: $body, boundary: $found[1]) as $part) {
				[$partHeaders, $partBody] = $this->split(raw: $part);
				$this->walk(headers: $partHeaders, body: $partBody, text: $text, files: $files);
			}

			return;
		}

		$content     = $this->decodeBody(body: $body, encoding: strtolower((string)($headers['content-transfer-encoding'] ?? '')));
		$disposition = strtolower((string)($headers['content-disposition'] ?? ''));
		$name        = $this->partName(headers: $headers);
		if (str_starts_with($disposition, 'attachment') === true || ($name !== '' && str_starts_with($type, 'text/') === false)) {
			$files[] = ['name' => $name, 'size' => strlen($content), 'content' => $content];
			return;
		}

		$content = $this->toUtf8(content: $content, contentType: ($headers['content-type'] ?? ''));
		if (str_starts_with($type, 'text/html') === true) {
			// Plain text wins when the message carries both.
			if ($text === '') {
				$text = $this->htmlToText(html: $content);
			}

			return;
		}

		if (str_starts_with($type, 'text/plain') === true) {
			$text = $content;
		}
	}//end walk()

	/**
	 * The parts between the boundaries of a multipart body.
	 *
	 * @param string $body     The multipart body.
	 * @param string $boundary The boundary.
	 *
	 * @return array<int,string>
	 */
	private function parts(string $body, string $boundary): array {
		$pieces = explode('--' . $boundary, $body);
		array_shift($pieces);
		$parts = [];
		foreach ($pieces as $piece) {
			if (str_starts_with($piece, '--') === true) {
				break;
			}

			$parts[] = ltrim($piece, "\n");
		}

		return $parts;
	}//end parts()

	/**
	 * The file name of a part, or ''.
	 *
	 * @param array<string,string> $headers The part's headers.
	 *
	 * @return string
	 */
	private function partName(array $headers): string {
		foreach (['content-disposition', 'content-type'] as $header) {
			if (preg_match('/(?:file)?name="?([^";]+)"?/i', (string)($headers[$header] ?? ''), $found) === 1) {
				return $this->decodeHeader(value: trim($found[1]));
			}
		}

		return '';
	}//end partName()

	/**
	 * Undo the content transfer encoding.
	 *
	 * @param string $body     The encoded body.
	 * @param string $encoding The Content-Transfer-Encoding value.
	 *
	 * @return string
	 */
	private function decodeBody(string $body, string $encoding): string {
		return match ($encoding) {
			'base64' => (string)base64_decode($body),
			'quoted-printable' => quoted_printable_decode($body),
			default => $body,
		};
	}//end decodeBody()

	/**
	 * Convert text to UTF-8 using the charset the part declares.
	 *
	 * @param string $content     The decoded bytes.
	 * @param string $contentType The Content-Type header.
	 *
	 * @return string
	 */
	private function toUtf8(string $content, string $contentType): string {
		$charset = 'UTF-8';
		if (preg_match('/charset="?([^";\s]+)"?/i', $contentType, $found) === 1) {
			$charset = $found[1];
		}

		if (strtoupper($charset) === 'UTF-8') {
			return $content;
		}

		$converted = @mb_convert_encoding($content, 'UTF-8', $charset);
		if (is_string($converted) === true) {
			return $converted;
		}

		return $content;
	}//end toUtf8()

	/**
	 * Plain text from HTML: block ends become line breaks, tags go, entities decode.
	 *
	 * @param string $html The HTML.
	 *
	 * @return string
	 */
	private function htmlToText(string $html): string {
		$html = (string)preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html);
		$html = (string)preg_replace('/<\s*(br|\/p|\/div|\/li|\/tr|\/h[1-6])\b[^>]*>/i', "\n", $html);

		return trim(html_entity_decode(strip_tags($html), (ENT_QUOTES | ENT_HTML5), 'UTF-8'));
	}//end htmlToText()

	/**
	 * Decode RFC 2047 encoded words in a header value.
	 *
	 * @param string $value The raw header.
	 *
	 * @return string
	 */
	private function decodeHeader(string $value): string {
		$decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
		if (is_string($decoded) === true) {
			return $decoded;
		}

		return $value;
	}//end decodeHeader()

	/**
	 * The lower-case email addresses in an address header.
	 *
	 * @param string $value The header value.
	 *
	 * @return array<int,string>
	 */
	private function addresses(string $value): array {
		if (preg_match_all('/[A-Za-z0-9._%+\-\']+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $value, $found) === false) {
			return [];
		}

		return array_map('strtolower', $found[0]);
	}//end addresses()
}//end class
