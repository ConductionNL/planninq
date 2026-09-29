<?php

/**
 * Planninq Microsoft Project plan parser
 *
 * Reads a plan saved from Microsoft Project in its XML format (MSPDI) into
 * plain arrays. The file is untrusted: a DOCTYPE or entity declaration is
 * refused before the XML parser sees it, the parser never touches the
 * network, and the size and task caps are checked before anything is mapped.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DOMDocument;
use DOMElement;
use OCA\Planninq\Exception\MsProjectImportException;

/**
 * Parses MSPDI XML.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.1
 */
class MsProjectPlanParser {

	/**
	 * The largest file read, in bytes (10 MB).
	 *
	 * @var int
	 */
	public const MAX_BYTES = 10485760;

	/**
	 * The most tasks a plan may hold.
	 *
	 * @var int
	 */
	public const MAX_TASKS = 2000;

	/**
	 * The ResourceUID Project writes for an unassigned placeholder.
	 *
	 * @var int
	 */
	private const UNASSIGNED = -65535;

	/**
	 * Read a plan.
	 *
	 * @param string $content  The file content.
	 * @param string $fileName The name the file was uploaded with.
	 *
	 * @return array{name:string,saveVersion:string,tasks:list<array<string,mixed>>}
	 *
	 * @throws MsProjectImportException When the file is refused.
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.1
	 */
	public function parse(string $content, string $fileName): array {
		$this->assertAcceptable(content: $content, fileName: $fileName);

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		// LIBXML_NONET: no network access. No LIBXML_NOENT or LIBXML_DTDLOAD, so
		// no entity is ever substituted and no DTD is fetched; the declarations
		// were refused above anyway.
		$loaded = $document->loadXML($content, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$root = $document->documentElement;
		if ($loaded === false || $root === null || $root->localName !== 'Project' || $document->doctype !== null) {
			throw new MsProjectImportException(reason: MsProjectImportException::NOT_A_PLAN, message: 'The file is not a Microsoft Project XML plan.');
		}

		$taskElements = $this->children(parent: $this->child(parent: $root, name: 'Tasks'), name: 'Task');
		if (count($taskElements) > self::MAX_TASKS) {
			throw new MsProjectImportException(reason: MsProjectImportException::TOO_MANY_TASKS, message: 'The plan has more than ' . self::MAX_TASKS . ' tasks.');
		}

		$resourced = $this->resourcedTaskUids(root: $root);
		$tasks     = [];
		foreach ($taskElements as $element) {
			$task = $this->task(element: $element, resourced: $resourced);
			if ($task['level'] >= 1) {
				$tasks[] = $task;
			}
		}

		return [
			'name'        => $this->text(parent: $root, name: 'Name'),
			'saveVersion' => $this->text(parent: $root, name: 'SaveVersion'),
			'tasks'       => $tasks,
		];
	}//end parse()

	/**
	 * Refuse a file by its name, size and declarations, before it is parsed.
	 *
	 * @param string $content  The file content.
	 * @param string $fileName The file name.
	 *
	 * @return void
	 *
	 * @throws MsProjectImportException When the file is refused.
	 */
	private function assertAcceptable(string $content, string $fileName): void {
		if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'mpp' || str_starts_with($content, "\xD0\xCF\x11\xE0") === true) {
			throw new MsProjectImportException(reason: MsProjectImportException::MPP, message: 'Binary .mpp files cannot be read.');
		}

		if (strlen($content) > self::MAX_BYTES) {
			throw new MsProjectImportException(reason: MsProjectImportException::TOO_LARGE, message: 'The file is larger than 10 MB.');
		}

		if (preg_match('/<!(DOCTYPE|ENTITY)/i', $content) === 1) {
			throw new MsProjectImportException(reason: MsProjectImportException::UNSAFE, message: 'The file declares a DOCTYPE or an entity.');
		}
	}//end assertAcceptable()

	/**
	 * One task element as an array.
	 *
	 * @param DOMElement         $element   The Task element.
	 * @param array<string,bool> $resourced The UIDs of tasks with a resource.
	 *
	 * @return array<string,mixed>
	 */
	private function task(DOMElement $element, array $resourced): array {
		$uid          = $this->text(parent: $element, name: 'UID');
		$predecessors = [];
		foreach ($this->children(parent: $element, name: 'PredecessorLink') as $link) {
			$predecessors[] = [
				'uid'  => $this->text(parent: $link, name: 'PredecessorUID'),
				'type' => (int)($this->text(parent: $link, name: 'Type', fallback: '1')),
				'lag'  => (int)$this->text(parent: $link, name: 'LinkLag', fallback: '0'),
			];
		}

		return [
			'uid'          => $uid,
			'name'         => $this->text(parent: $element, name: 'Name'),
			'level'        => (int)$this->text(parent: $element, name: 'OutlineLevel', fallback: '1'),
			'summary'      => ($this->text(parent: $element, name: 'Summary') === '1'),
			'milestone'    => ($this->text(parent: $element, name: 'Milestone') === '1'),
			'start'        => $this->date(value: $this->text(parent: $element, name: 'Start')),
			'finish'       => $this->date(value: $this->text(parent: $element, name: 'Finish')),
			'minutes'      => $this->minutes(duration: $this->text(parent: $element, name: 'Duration')),
			'percent'      => max(0, min(100, (int)$this->text(parent: $element, name: 'PercentComplete', fallback: '0'))),
			'notes'        => $this->text(parent: $element, name: 'Notes'),
			'predecessors' => $predecessors,
			'hasResource'  => isset($resourced[$uid]),
		];
	}//end task()

	/**
	 * The UIDs of tasks that have a real resource assigned.
	 *
	 * @param DOMElement $root The Project element.
	 *
	 * @return array<string,bool>
	 */
	private function resourcedTaskUids(DOMElement $root): array {
		$uids = [];
		foreach ($this->children(parent: $this->child(parent: $root, name: 'Assignments'), name: 'Assignment') as $assignment) {
			$resource = $this->text(parent: $assignment, name: 'ResourceUID');
			if ($resource !== '' && (int)$resource !== self::UNASSIGNED) {
				$uids[$this->text(parent: $assignment, name: 'TaskUID')] = true;
			}
		}

		return $uids;
	}//end resourcedTaskUids()

	/**
	 * An MSPDI date-time as a date, or null.
	 *
	 * @param string $value The value, e.g. 2027-03-01T08:00:00.
	 *
	 * @return string|null
	 */
	private function date(string $value): ?string {
		if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $match) !== 1) {
			return null;
		}

		return $match[0];
	}//end date()

	/**
	 * An ISO 8601 duration (Project writes PT80H0M0S) in whole minutes, or null.
	 *
	 * @param string $duration The duration.
	 *
	 * @return int|null
	 */
	private function minutes(string $duration): ?int {
		if (preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:[\d.]+S)?)?$/', $duration, $parts) !== 1 || $duration === 'P') {
			return null;
		}

		return ((int)($parts[1] ?? 0) * 1440) + ((int)($parts[2] ?? 0) * 60) + (int)($parts[3] ?? 0);
	}//end minutes()

	/**
	 * The first direct child element with this local name, or null.
	 *
	 * @param DOMElement|null $parent The parent.
	 * @param string          $name   The local name.
	 *
	 * @return DOMElement|null
	 */
	private function child(?DOMElement $parent, string $name): ?DOMElement {
		return ($this->children(parent: $parent, name: $name)[0] ?? null);
	}//end child()

	/**
	 * The direct child elements with this local name.
	 *
	 * @param DOMElement|null $parent The parent.
	 * @param string          $name   The local name.
	 *
	 * @return list<DOMElement>
	 */
	private function children(?DOMElement $parent, string $name): array {
		if ($parent === null) {
			return [];
		}

		$found = [];
		foreach ($parent->childNodes as $node) {
			if ($node instanceof DOMElement && $node->localName === $name) {
				$found[] = $node;
			}
		}

		return $found;
	}//end children()

	/**
	 * The trimmed text of a direct child element.
	 *
	 * @param DOMElement $parent   The parent.
	 * @param string     $name     The local name.
	 * @param string     $fallback The value when the element is missing.
	 *
	 * @return string
	 */
	private function text(DOMElement $parent, string $name, string $fallback = ''): string {
		$child = $this->child(parent: $parent, name: $name);
		if ($child === null) {
			return $fallback;
		}

		return trim($child->textContent);
	}//end text()
}//end class
