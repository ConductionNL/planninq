<?php

/**
 * Planninq BoardExportMapper
 *
 * Turns a board exported from another tool into one plan of a project, its
 * columns and its tasks, driven entirely by a mapping configuration in
 * `lib/Settings/board-import/`. The mapper knows two source shapes, a JSON
 * document read through JSON pointers and a CSV file read by column name; what
 * a list, a status, a card or an issue is in a particular tool is the
 * configuration's business, not this class's.
 *
 * It writes nothing and resolves no accounts: user fields come back as names
 * for the import to match against Nextcloud, and everything that could not be
 * carried over is listed under `losses`.
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
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Maps a JSON or CSV board export onto a project, columns and tasks.
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
 */
class BoardExportMapper {

	/**
	 * Date layouts tried, in order, before the free-form parser.
	 *
	 * @var array<int,string>
	 */
	private const DATE_FORMATS = ['!Y-m-d', 'Y-m-d\TH:i:s.v\Z', 'Y-m-d\TH:i:sP', 'd/M/y h:i A', '!d/M/y', '!d-m-Y'];

	/**
	 * Map one export.
	 *
	 * @param array<string,mixed> $config   The mapping configuration.
	 * @param string              $content  The exported file's content.
	 * @param string              $fileName The file name, the project title's fallback.
	 *
	 * @return array{
	 *   project: array<string,string>,
	 *   columns: array<int,array{key: string, title: string}>,
	 *   tasks: array<int,array<string,mixed>>,
	 *   losses: array<int,string>
	 * }
	 *
	 * @throws InvalidArgumentException When the file cannot be read in the configured format.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function map(array $config, string $content, string $fileName): array {
		$format = (string)($config['format'] ?? '');
		if ($format === 'json') {
			return $this->mapJson(config: $config, document: $this->decodeJson(content: $content), fileName: $fileName);
		}

		if ($format === 'csv') {
			return $this->mapCsv(config: $config, rows: $this->readCsv(content: $content), fileName: $fileName);
		}

		throw new InvalidArgumentException('Unknown export format "' . $format . '".');
	}//end map()

	/**
	 * Map a JSON document.
	 *
	 * @param array<string,mixed> $config   The mapping configuration.
	 * @param array<mixed>        $document The decoded export.
	 * @param string              $fileName The file name.
	 *
	 * @return array<string,mixed> The plan.
	 */
	private function mapJson(array $config, array $document, string $fileName): array {
		$losses  = [];
		$project = $this->mapFields(
			fields: (array)($config['project'] ?? []),
			row: $document,
			fileName: $fileName,
			lookups: [],
			losses: $losses,
			label: 'project'
		);

		$columns = [];
		$spec    = (array)($config['columns'] ?? []);
		foreach ($this->listAt(document: $document, pointer: (string)($spec['source'] ?? '')) as $list) {
			if ($this->skipped(row: $list, skip: (array)($spec['skip'] ?? [])) === true) {
				continue;
			}

			$title = trim((string)self::pointer(document: $list, pointer: (string)($spec['title'] ?? '')));
			$key   = (string)self::pointer(document: $list, pointer: (string)($spec['key'] ?? ''));
			if ($title !== '' && $key !== '') {
				$columns[] = ['key' => $key, 'title' => $title];
			}
		}

		$lookups = $this->lookups(document: $document, specs: (array)($config['lookups'] ?? []));
		$tasks   = $this->mapTasks(
			spec: (array)($config['tasks'] ?? []),
			rows: $this->listAt(document: $document, pointer: (string)($config['tasks']['source'] ?? '')),
			lookups: $lookups,
			columnKeys: array_column($columns, 'key'),
			losses: $losses
		);

		return ['project' => $project['data'], 'columns' => $columns, 'tasks' => $tasks, 'losses' => $losses];
	}//end mapJson()

	/**
	 * Map CSV rows, the columns being the distinct values of one task column.
	 *
	 * @param array<string,mixed>              $config   The mapping configuration.
	 * @param array<int,array<string,string>> $rows     The rows by header.
	 * @param string                           $fileName The file name.
	 *
	 * @return array<string,mixed> The plan.
	 */
	private function mapCsv(array $config, array $rows, string $fileName): array {
		$losses  = [];
		$project = $this->mapFields(
			fields: (array)($config['project'] ?? []),
			row: ($rows[0] ?? []),
			fileName: $fileName,
			lookups: [],
			losses: $losses,
			label: 'project'
		);

		$field = (string)($config['columns']['fromTaskColumn'] ?? '');
		$seen  = [];
		foreach ($rows as $row) {
			$value = trim((string)($row[$field] ?? ''));
			if ($value !== '') {
				$seen[$value] = true;
			}
		}

		$preferred = array_values(array_filter((array)($config['columns']['order'] ?? []), static fn (mixed $title): bool => isset($seen[(string)$title])));
		$titles    = array_values(array_unique(array_merge(array_map('strval', $preferred), array_map('strval', array_keys($seen)))));
		$columns   = array_map(static fn (string $title): array => ['key' => $title, 'title' => $title], $titles);

		$tasks = $this->mapTasks(spec: (array)($config['tasks'] ?? []), rows: $rows, lookups: [], columnKeys: $titles, losses: $losses);

		return ['project' => $project['data'], 'columns' => $columns, 'tasks' => $tasks, 'losses' => $losses];
	}//end mapCsv()

	/**
	 * Map the task rows.
	 *
	 * @param array<string,mixed>                 $spec       The `tasks` configuration.
	 * @param array<int,mixed>                    $rows       The source rows.
	 * @param array<string,array<string,string>> $lookups    Named lookup tables.
	 * @param array<int,string>                   $columnKeys The keys of the mapped columns.
	 * @param array<int,string>                   $losses     Collects what was not carried over.
	 *
	 * @return array<int,array{column: string, sourceId: string, data: array<string,mixed>, users: array<string,string>}>
	 */
	private function mapTasks(array $spec, array $rows, array $lookups, array $columnKeys, array &$losses): array {
		$known = array_flip($columnKeys);
		$tasks = [];
		foreach (array_values($rows) as $index => $row) {
			if (is_array($row) === false || $this->skipped(row: $row, skip: (array)($spec['skip'] ?? [])) === true) {
				continue;
			}

			$label  = 'row ' . ($index + 1);
			$mapped = $this->mapFields(fields: (array)($spec['fields'] ?? []), row: $row, fileName: '', lookups: $lookups, losses: $losses, label: $label);
			if ($mapped['complete'] === false) {
				continue;
			}

			$column = trim((string)self::read(row: $row, source: (string)($spec['column'] ?? '')));
			if (isset($known[$column]) === false) {
				$losses[] = $label . ': its list or status "' . $column . '" is not imported, so it was left out.';
				continue;
			}

			$tasks[] = [
				'column'   => $column,
				'sourceId' => (string)self::read(row: $row, source: (string)($spec['sourceId'] ?? '')),
				'data'     => $mapped['data'],
				'users'    => $mapped['users'],
			];
		}//end foreach

		return $tasks;
	}//end mapTasks()

	/**
	 * Map one row's fields.
	 *
	 * @param array<string,mixed>                 $fields   Target field => mapping.
	 * @param array<mixed>                        $row      The source row.
	 * @param string                              $fileName The file name, for a `fileName` fallback.
	 * @param array<string,array<string,string>> $lookups  Named lookup tables.
	 * @param array<int,string>                   $losses   Collects what was not carried over.
	 * @param string                              $label    Names the row in a loss.
	 *
	 * @return array{data: array<string,mixed>, users: array<string,string>, complete: bool}
	 */
	private function mapFields(array $fields, array $row, string $fileName, array $lookups, array &$losses, string $label): array {
		$data  = [];
		$users = [];
		foreach ($fields as $target => $mapping) {
			$value = $this->value(mapping: (array)$mapping, row: $row, fileName: $fileName, lookups: $lookups, losses: $losses, label: $label . ' ' . $target);
			if ($value === null) {
				if (($mapping['required'] ?? false) === true) {
					$losses[] = $label . ': no ' . $target . ', so it was left out.';
					return ['data' => [], 'users' => [], 'complete' => false];
				}

				continue;
			}

			if (($mapping['user'] ?? false) === true) {
				$users[(string)$target] = $value;
				continue;
			}

			$data[(string)$target] = $value;
		}

		return ['data' => $data, 'users' => $users, 'complete' => true];
	}//end mapFields()

	/**
	 * One mapped value, or null when there is none.
	 *
	 * @param array<string,mixed>                 $mapping  The field mapping.
	 * @param array<mixed>                        $row      The source row.
	 * @param string                              $fileName The file name.
	 * @param array<string,array<string,string>> $lookups  Named lookup tables.
	 * @param array<int,string>                   $losses   Collects what was not carried over.
	 * @param string                              $label    Names the field in a loss.
	 *
	 * @return string|null The value.
	 */
	private function value(array $mapping, array $row, string $fileName, array $lookups, array &$losses, string $label): ?string {
		$raw = trim((string)self::read(row: $row, source: (string)($mapping['source'] ?? '')));
		if ($raw === '' && ($mapping['fallback'] ?? '') === 'fileName') {
			$raw = trim((string)preg_replace('/\.[A-Za-z0-9]+$/', '', basename($fileName)));
		}

		if ($raw === '') {
			return null;
		}

		$transform = (string)($mapping['transform'] ?? '');
		if ($transform === 'date') {
			return $this->date(value: $raw, losses: $losses, label: $label);
		}

		if ($transform === 'map' || $transform === 'lookup') {
			$table = (array)($mapping['map'] ?? ($lookups[(string)($mapping['lookup'] ?? '')] ?? []));
			if (array_key_exists($raw, $table) === false) {
				$losses[] = $label . ': "' . $raw . '" has no counterpart, so it was left empty.';
				return null;
			}

			return (string)$table[$raw];
		}

		return $raw;
	}//end value()

	/**
	 * A date as Y-m-d.
	 *
	 * @param string            $value  The source value.
	 * @param array<int,string> $losses Collects what was not carried over.
	 * @param string            $label  Names the field in a loss.
	 *
	 * @return string|null The date, or null when it cannot be read.
	 */
	private function date(string $value, array &$losses, string $label): ?string {
		foreach (self::DATE_FORMATS as $format) {
			$date = DateTimeImmutable::createFromFormat($format, $value);
			if ($date !== false && DateTimeImmutable::getLastErrors() === false) {
				return $date->format('Y-m-d');
			}
		}

		$losses[] = $label . ': "' . $value . '" is not a date this import reads, so it was left empty.';
		return null;
	}//end date()

	/**
	 * Lookup tables built from lists in the document.
	 *
	 * @param array<mixed>        $document The decoded export.
	 * @param array<string,mixed> $specs    Name => {source, key, value}.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function lookups(array $document, array $specs): array {
		$tables = [];
		foreach ($specs as $name => $spec) {
			$tables[(string)$name] = [];
			foreach ($this->listAt(document: $document, pointer: (string)($spec['source'] ?? '')) as $entry) {
				$key = (string)self::pointer(document: $entry, pointer: (string)($spec['key'] ?? ''));
				if ($key !== '') {
					$tables[(string)$name][$key] = (string)self::pointer(document: $entry, pointer: (string)($spec['value'] ?? ''));
				}
			}
		}

		return $tables;
	}//end lookups()

	/**
	 * Whether a row matches its `skip` conditions (pointer => value, all must hold).
	 *
	 * @param array<mixed>        $row  The row.
	 * @param array<string,mixed> $skip The conditions.
	 *
	 * @return bool
	 */
	private function skipped(array $row, array $skip): bool {
		if ($skip === []) {
			return false;
		}

		foreach ($skip as $source => $expected) {
			if (self::read(row: $row, source: (string)$source) !== $expected) {
				return false;
			}
		}

		return true;
	}//end skipped()

	/**
	 * The list at a pointer, or an empty list.
	 *
	 * @param array<mixed> $document The document.
	 * @param string       $pointer  The JSON pointer.
	 *
	 * @return array<int,array<mixed>>
	 */
	private function listAt(array $document, string $pointer): array {
		$list = self::pointer(document: $document, pointer: $pointer);
		if (is_array($list) === false) {
			return [];
		}

		return array_values(array_filter($list, 'is_array'));
	}//end listAt()

	/**
	 * Read a source: a JSON pointer when it starts with "/", else a CSV column name.
	 *
	 * @param array<mixed> $row    The row.
	 * @param string       $source The source.
	 *
	 * @return mixed The value, or null.
	 */
	private static function read(array $row, string $source): mixed {
		if ($source === '') {
			return null;
		}

		if (str_starts_with($source, '/') === true) {
			return self::pointer(document: $row, pointer: $source);
		}

		return ($row[$source] ?? null);
	}//end read()

	/**
	 * Resolve a JSON pointer (RFC 6901).
	 *
	 * @param array<mixed> $document The document.
	 * @param string       $pointer  The pointer; "" is the document itself.
	 *
	 * @return mixed The value, or null when it does not resolve.
	 */
	private static function pointer(array $document, string $pointer): mixed {
		if ($pointer === '') {
			return $document;
		}

		$value = $document;
		foreach (explode('/', substr($pointer, 1)) as $part) {
			$part = str_replace(['~1', '~0'], ['/', '~'], $part);
			if (is_array($value) === false || array_key_exists($part, $value) === false) {
				return null;
			}

			$value = $value[$part];
		}

		return $value;
	}//end pointer()

	/**
	 * Decode a JSON export.
	 *
	 * @param string $content The file content.
	 *
	 * @return array<mixed> The document.
	 *
	 * @throws InvalidArgumentException When it is not a JSON object.
	 */
	private function decodeJson(string $content): array {
		try {
			$document = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
		} catch (Throwable $e) {
			throw new InvalidArgumentException('The file is not valid JSON: ' . $e->getMessage(), 0, $e);
		}

		if (is_array($document) === false) {
			throw new InvalidArgumentException('The file holds no board.');
		}

		return $document;
	}//end decodeJson()

	/**
	 * Read a CSV export into rows keyed by header; a repeated header keeps its first column.
	 *
	 * @param string $content The file content.
	 *
	 * @return array<int,array<string,string>> The rows.
	 *
	 * @throws InvalidArgumentException When there is no header row.
	 */
	private function readCsv(string $content): array {
		$content = (string)preg_replace('/^\xEF\xBB\xBF/', '', $content);
		$stream  = fopen('php://temp', 'r+');
		fwrite($stream, $content);
		rewind($stream);

		$header = fgetcsv($stream, null, ',', '"', '');
		if ($header === false || $header === [null]) {
			fclose($stream);
			throw new InvalidArgumentException('The file has no header row.');
		}

		$columns = [];
		foreach ($header as $position => $name) {
			$name = trim((string)$name);
			if ($name !== '' && in_array($name, $columns, true) === false) {
				$columns[$position] = $name;
			}
		}

		$rows = [];
		while (($line = fgetcsv($stream, null, ',', '"', '')) !== false) {
			if ($line === [null]) {
				continue;
			}

			$row = [];
			foreach ($columns as $position => $name) {
				$row[$name] = (string)($line[$position] ?? '');
			}

			$rows[] = $row;
		}

		fclose($stream);

		return $rows;
	}//end readCsv()

}//end class
