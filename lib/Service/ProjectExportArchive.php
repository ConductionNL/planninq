<?php

/**
 * Planninq ProjectExportArchive
 *
 * Writes the zip of a project export to a local file: `project.json`, one
 * `<schema>.json` per schema in stored values, `tasks.csv` in rendered
 * values, `comments.json`, the task and phase files, and `manifest.json`
 * naming the project, the start time, the versions, the value mode and row
 * count per file and every skipped schema or file with its reason. Files are
 * copied to temporary files one by one, never held in memory whole.
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
 * @spec openspec/changes/project-data-export/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\ITempManager;
use RuntimeException;
use ZipArchive;

/**
 * Writes the zip of a project export.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-1.2
 */
class ProjectExportArchive {

	/**
	 * Constructor
	 *
	 * @param ITempManager $tempManager Hands out the temporary files of copied attachments.
	 * @param IAppManager  $appManager  Reads the app versions for the manifest.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.2
	 */
	public function __construct(
		private readonly ITempManager $tempManager,
		private readonly IAppManager $appManager,
	) {
	}//end __construct()

	/**
	 * Write the export to a zip at a local path.
	 *
	 * @param array<string,mixed> $content The export from ProjectExportService::collect().
	 * @param string              $path    The local zip path; an existing file there is replaced.
	 * @param \DateTimeInterface  $started When the export started.
	 *
	 * @return array<string,mixed> The manifest written into the zip.
	 *
	 * @throws RuntimeException When the zip cannot be written.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.2
	 */
	public function write(array $content, string $path, \DateTimeInterface $started): array {
		$zip     = $this->open(path: $path);
		$skipped = (array)($content['skipped'] ?? []);
		$files   = $this->addRecords(zip: $zip, content: $content);
		[$documents, $temporary] = $this->addDocuments(zip: $zip, entries: (array)($content['files'] ?? []), skipped: $skipped);

		$manifest = $this->manifest(content: $content, started: $started) + [
			'files'   => $files + $documents,
			'skipped' => array_values($skipped),
		];
		$this->addJson(zip: $zip, name: 'manifest.json', value: $manifest);
		$this->finish(zip: $zip, temporary: $temporary);

		return $manifest;
	}//end write()

	/**
	 * The zip at a path, opened for writing.
	 *
	 * @param string $path The local zip.
	 *
	 * @return ZipArchive
	 *
	 * @throws RuntimeException When it cannot be opened.
	 */
	private function open(string $path): ZipArchive {
		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new RuntimeException('The export zip could not be opened for writing.');
		}

		return $zip;
	}//end open()

	/**
	 * The project, its schemas, the task CSV and the comments, into the zip.
	 *
	 * @param ZipArchive           $zip     The zip.
	 * @param array<string, mixed> $content ProjectExportService::collect().
	 *
	 * @return array<string, array{valueMode: string, rows: int}> The manifest's entries for them.
	 */
	private function addRecords(ZipArchive $zip, array $content): array {
		$this->addJson(zip: $zip, name: 'project.json', value: $content['project'] ?? []);
		$files = ['project.json' => ['valueMode' => 'stored', 'rows' => 1]];

		foreach ((array)($content['schemas'] ?? []) as $schema => $rows) {
			$this->addJson(zip: $zip, name: $schema . '.json', value: array_values((array)$rows));
			$files[$schema . '.json'] = ['valueMode' => 'stored', 'rows' => count((array)$rows)];
		}

		$csv = (array)($content['csv'] ?? []);
		$zip->addFromString('tasks.csv', $this->csv(rows: $csv));
		$files['tasks.csv'] = ['valueMode' => 'rendered', 'rows' => max(0, count($csv) - 1)];

		$comments = (array)($content['comments'] ?? []);
		$this->addJson(zip: $zip, name: 'comments.json', value: (object)$comments);
		$files['comments.json'] = ['valueMode' => 'stored', 'rows' => array_sum(array_map('count', $comments))];

		return $files;
	}//end addRecords()

	/**
	 * The project's documents into the zip, each through a local copy; one
	 * the user cannot read is listed as skipped.
	 *
	 * @param ZipArchive                                      $zip     The zip.
	 * @param array<int, array{path: string, file: mixed}>    $entries The documents.
	 * @param array<int, array{item: string, reason: string}> $skipped Receives the documents left out.
	 *
	 * @return array{0: array<string, array{valueMode: string, rows: int}>, 1: array<int, string>} The manifest's entries and the local copies to remove.
	 */
	private function addDocuments(ZipArchive $zip, array $entries, array &$skipped): array {
		$files     = [];
		$temporary = [];
		foreach ($entries as $entry) {
			[$name, $copy] = $this->addDocument(zip: $zip, entry: $entry);
			if ($copy === null) {
				$skipped[] = ['item' => $name, 'reason' => 'no read access'];
				continue;
			}

			$temporary[] = $copy;
			$files[$name] = ['valueMode' => 'file', 'rows' => 1];
		}

		return [$files, $temporary];
	}//end addDocuments()

	/**
	 * One document into the zip through a local copy, under a name not used yet.
	 *
	 * @param ZipArchive                       $zip   The zip.
	 * @param array{path: string, file: mixed} $entry The document.
	 *
	 * @return array{0: string, 1: string|null} The name in the zip, and the local copy, or null when the user cannot read it.
	 */
	private function addDocument(ZipArchive $zip, array $entry): array {
		$name = $this->freeName(zip: $zip, path: (string)$entry['path']);
		$copy = $this->copyOf(file: $entry['file']);
		if ($copy !== null) {
			$zip->addFile($copy, $name);
		}

		return [$name, $copy];
	}//end addDocument()

	/**
	 * The manifest's head: which project, which profile, when, and with which versions.
	 *
	 * @param array<string, mixed> $content ProjectExportService::collect().
	 * @param \DateTimeInterface   $started When the export started.
	 *
	 * @return array<string, mixed>
	 */
	private function manifest(array $content, \DateTimeInterface $started): array {
		return [
			'project'    => [
				'id'    => (string)(($content['project']['@self']['id'] ?? null) ?? ($content['project']['id'] ?? '')),
				'key'   => (string)($content['key'] ?? ''),
				'title' => (string)($content['title'] ?? ''),
			],
			'profile'    => ProjectExportService::PROFILE,
			'startedAt'  => $started->format(\DateTimeInterface::ATOM),
			'versions'   => $this->versions(),
			'registerId' => ($content['registerId'] ?? null),
		];
	}//end manifest()

	/**
	 * The versions of Planninq and OpenRegister the export was made with.
	 *
	 * @return array{planninq: string, openregister: string}
	 */
	private function versions(): array {
		return [
			'planninq'     => $this->appManager->getAppVersion('planninq'),
			'openregister' => $this->appManager->getAppVersion('openregister'),
		];
	}//end versions()

	/**
	 * Close the zip, then remove the local copies.
	 *
	 * @param ZipArchive         $zip       The zip.
	 * @param array<int, string> $temporary The local copies.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the zip could not be written.
	 *
	 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	private function finish(ZipArchive $zip, array $temporary): void {
		$closed = $zip->close();
		foreach ($temporary as $copy) {
			if (file_exists($copy) === true) {
				unlink($copy);
			}
		}

		if ($closed !== true) {
			throw new RuntimeException('The export zip could not be written.');
		}
	}//end finish()

	/**
	 * The total number of objects in an export: the project and every schema row.
	 *
	 * @param array<string,mixed> $content The export from ProjectExportService::collect().
	 *
	 * @return int
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	public function objectCount(array $content): int {
		return 1 + array_sum(array_map(static fn ($rows): int => count((array)$rows), (array)($content['schemas'] ?? [])));
	}//end objectCount()

	/**
	 * Add one pretty-printed JSON entry.
	 *
	 * @param ZipArchive $zip   The open zip.
	 * @param string     $name  The entry name.
	 * @param mixed      $value The value.
	 *
	 * @return void
	 */
	private function addJson(ZipArchive $zip, string $name, mixed $value): void {
		$zip->addFromString($name, (string)json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
	}//end addJson()

	/**
	 * CSV text of rows, RFC 4180 quoting.
	 *
	 * @param array<int,array<int,string>> $rows The rows.
	 *
	 * @return string
	 */
	private function csv(array $rows): string {
		$stream = fopen('php://temp', 'w+');
		foreach ($rows as $row) {
			fputcsv($stream, array_map('strval', (array)$row), ',', '"', '');
		}

		rewind($stream);
		$text = (string)stream_get_contents($stream);
		fclose($stream);

		return $text;
	}//end csv()

	/**
	 * A path not yet in the zip: "plan.pdf", else "plan (1).pdf", and so on.
	 *
	 * @param ZipArchive $zip  The open zip.
	 * @param string     $path The path wanted.
	 *
	 * @return string
	 */
	private function freeName(ZipArchive $zip, string $path): string {
		$dot  = strrpos($path, '.');
		$stem = $path;
		$ext  = '';
		if ($dot !== false && $dot > (int)strrpos($path, '/') + 1) {
			$stem = substr($path, 0, $dot);
			$ext  = substr($path, $dot);
		}

		$candidate = $path;
		for ($i = 1; $zip->locateName($candidate) !== false; $i++) {
			$candidate = $stem . ' (' . $i . ')' . $ext;
		}

		return $candidate;
	}//end freeName()

	/**
	 * Stream a file into a temporary local file, or null when it cannot be read.
	 *
	 * @param File $file The file.
	 *
	 * @return string|null The temporary path.
	 */
	private function copyOf(File $file): ?string {
		try {
			$source = $file->fopen('r');
		} catch (\Throwable $e) {
			return null;
		}

		if (is_resource($source) === false) {
			return null;
		}

		$copy   = (string)$this->tempManager->getTemporaryFile();
		$target = false;
		if ($copy !== '') {
			$target = fopen($copy, 'w');
		}

		if ($target === false) {
			fclose($source);
			throw new RuntimeException('No temporary file for an export attachment.');
		}

		stream_copy_to_stream($source, $target);
		fclose($source);
		fclose($target);

		return $copy;
	}//end copyOf()
}//end class
