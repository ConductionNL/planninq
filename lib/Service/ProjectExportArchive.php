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
		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new RuntimeException('The export zip could not be opened for writing.');
		}

		$files   = [];
		$skipped = (array)($content['skipped'] ?? []);

		$this->addJson(zip: $zip, name: 'project.json', value: $content['project'] ?? []);
		$files['project.json'] = ['valueMode' => 'stored', 'rows' => 1];

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

		$temporary = [];
		foreach ((array)($content['files'] ?? []) as $entry) {
			$name = $this->freeName(zip: $zip, path: (string)$entry['path']);
			$copy = $this->copyOf(file: $entry['file']);
			if ($copy === null) {
				$skipped[] = ['item' => $name, 'reason' => 'no read access'];
				continue;
			}

			$temporary[] = $copy;
			$zip->addFile($copy, $name);
			$files[$name] = ['valueMode' => 'file', 'rows' => 1];
		}

		$manifest = [
			'project'    => [
				'id'    => (string)(($content['project']['@self']['id'] ?? null) ?? ($content['project']['id'] ?? '')),
				'key'   => (string)($content['key'] ?? ''),
				'title' => (string)($content['title'] ?? ''),
			],
			'profile'    => ProjectExportService::PROFILE,
			'startedAt'  => $started->format(\DateTimeInterface::ATOM),
			'versions'   => [
				'planninq'     => $this->appManager->getAppVersion('planninq'),
				'openregister' => $this->appManager->getAppVersion('openregister'),
			],
			'registerId' => ($content['registerId'] ?? null),
			'files'      => $files,
			'skipped'    => array_values($skipped),
		];
		$this->addJson(zip: $zip, name: 'manifest.json', value: $manifest);

		$closed = $zip->close();
		foreach ($temporary as $copy) {
			@unlink($copy);
		}

		if ($closed !== true) {
			throw new RuntimeException('The export zip could not be written.');
		}

		return $manifest;
	}//end write()

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
