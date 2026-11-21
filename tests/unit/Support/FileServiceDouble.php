<?php

/**
 * In-memory double of OpenRegister's FileService.
 *
 * It declares the real method names and parameter names of
 * OCA\OpenRegister\Service\FileService (getFiles, getFile, addFile) and keeps
 * each object's files in memory as OCP\Files\File nodes, so a service that
 * calls the real API with named arguments runs against it unchanged.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Support
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

namespace OCA\Planninq\Tests\Unit\Support;

use OCA\OpenRegister\Db\ObjectEntity;
use OCP\Files\File;

/**
 * Files per object uuid, in memory.
 */
class FileServiceDouble {

	/**
	 * Stored files: object uuid => name => content.
	 *
	 * @var array<string, array<string,string>>
	 */
	public array $files = [];

	/**
	 * Names whose written copy reads back different bytes (a corrupted write).
	 *
	 * @var array<int,string>
	 */
	public array $corruptOnWrite = [];

	/**
	 * Every addFile() call.
	 *
	 * @var array<int, array{object:string,name:string}>
	 */
	public array $added = [];

	/**
	 * Build a file node.
	 *
	 * @var \Closure(string,string):File
	 */
	private \Closure $node;

	/**
	 * Constructor.
	 *
	 * @param \Closure(string,string):File $node Makes a File node from a name and content.
	 */
	public function __construct(\Closure $node) {
		$this->node = $node;
	}//end __construct()

	/**
	 * The uuid of an object or object id.
	 *
	 * @param ObjectEntity|string $object The object.
	 *
	 * @return string
	 */
	private function uuid(ObjectEntity|string $object): string {
		if ($object instanceof ObjectEntity) {
			return (string)$object->getUuid();
		}

		return $object;
	}//end uuid()

	/**
	 * The files of an object, as OpenRegister returns them.
	 *
	 * @param ObjectEntity|string $object          The object.
	 * @param bool|null           $sharedFilesOnly Ignored.
	 *
	 * @return array<int,File>
	 */
	public function getFiles(ObjectEntity|string $object, ?bool $sharedFilesOnly = false): array {
		$nodes = [];
		foreach (($this->files[$this->uuid(object: $object)] ?? []) as $name => $content) {
			$nodes[] = ($this->node)($name, $content);
		}

		return $nodes;
	}//end getFiles()

	/**
	 * One file of an object by name, or null.
	 *
	 * @param ObjectEntity|string|null $object The object.
	 * @param string|int               $file   The name.
	 *
	 * @return File|null
	 */
	public function getFile(ObjectEntity|string|null $object = null, string|int $file = ''): ?File {
		$content = ($this->files[$this->uuid(object: ($object ?? ''))][(string)$file] ?? null);
		if ($content === null) {
			return null;
		}

		return ($this->node)((string)$file, $content);
	}//end getFile()

	/**
	 * Add a file to an object.
	 *
	 * @param ObjectEntity|string $objectEntity The object.
	 * @param string              $fileName     The name.
	 * @param mixed               $content      The bytes.
	 * @param bool                $share        Ignored.
	 * @param array<int,string>   $tags         Ignored.
	 *
	 * @return File
	 */
	public function addFile(ObjectEntity|string $objectEntity, string $fileName, mixed $content, bool $share = false, array $tags = []): File {
		$uuid = $this->uuid(object: $objectEntity);
		if (isset($this->files[$uuid][$fileName]) === true) {
			throw new \RuntimeException('File already exists: ' . $fileName);
		}

		if (in_array($fileName, $this->corruptOnWrite, true) === true) {
			$content .= 'x';
		}

		$this->files[$uuid][$fileName] = (string)$content;
		$this->added[] = ['object' => $uuid, 'name' => $fileName];

		return ($this->node)($fileName, (string)$content);
	}//end addFile()
}//end class
