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
	 * The file name of the export being mapped, for a `fileName` fallback; '' while tasks are mapped.
	 *
	 * @var string
	 */
	private string $fileName = '';

	/**
	 * Named lookup tables of the export being mapped.
	 *
	 * @var array<string,array<string,string>>
	 */
	private array $lookups = [];

	/**
	 * What the export being mapped did not carry over.
	 *
	 * @var array<int,string>
	 */
	private array $losses = [];

	/**
	 * Names the row being mapped in a loss: `project` or `row N`.
	 *
	 * @var string
	 */
	private string $rowLabel = '';

	/**
	 * Names the field being mapped in a loss: the row label and the target field.
	 *
	 * @var string
	 */
	private string $fieldLabel = '';

	/**
	 * The keys of the mapped columns, as keys, while tasks are mapped.
	 *
	 * @var array<string,int>
	 */
	private array $knownColumns = [];

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
		$this->fileName = $fileName;
		$this->lookups  = [];
		$this->losses   = [];
		$this->knownColumns = [];

		$format = (string)($config['format'] ?? '');
		if ($format === 'json') {
			return $this->mapJson(config: $config, document: $this->decodeJson(content: $content));
		}

		if ($format === 'csv') {
			return $this->mapCsv(config: $config, rows: $this->readCsv(content: $content));
		}

		throw new InvalidArgumentException('Unknown export format "' . $format . '".');
	}//end map()

	/**
	 * Map a JSON document.
	 *
	 * @param array<string,mixed> $config   The mapping configuration.
	 * @param array<mixed>        $document The decoded export.
	 *
	 * @return array<string,mixed> The plan.
	 */
	private function mapJson(array $config, array $document): array {
		$this->rowLabel = 'project';
		$project        = $this->mapFields(fields: (array)($config['project'] ?? []), row: $document);

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

		$this->lookups      = $this->lookups(document: $document, specs: (array)($config['lookups'] ?? []));
		$this->knownColumns = array_flip(array_column($columns, 'key'));
		$tasks = $this->mapTasks(
			spec: (array)($config['tasks'] ?? []),
			rows: $this->listAt(document: $document, pointer: (string)($config['tasks']['source'] ?? ''))
		);

		return ['project' => $project['data'], 'columns' => $columns, 'tasks' => $tasks, 'losses' => $this->losses];
	}//end mapJson()

	/**
	 * Map CSV rows, the columns being the distinct values of one task column.
	 *
	 * @param array<string,mixed>              $config   The mapping configuration.
	 * @param array<int,array<string,string>> $rows     The rows by header.
	 *
	 * @return array<string,mixed> The plan.
	 */
	private function mapCsv(array $config, array $rows): array {
		$this->rowLabel = 'project';
		$project        = $this->mapFields(fields: (array)($config['project'] ?? []), row: ($rows[0] ?? []));

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

		$this->knownColumns = array_flip($titles);
		$tasks = $this->mapTasks(spec: (array)($config['tasks'] ?? []), rows: $rows);

		return ['project' => $project['data'], 'columns' => $columns, 'tasks' => $tasks, 'losses' => $this->losses];
	}//end mapCsv()

	/**
	 * Map the task rows onto the columns in knownColumns.
	 *
	 * @param array<string,mixed> $spec The `tasks` configuration.
	 * @param array<int,mixed>    $rows The source rows.
	 *
	 * @return array<int,array{column: string, sourceId: string, data: array<string,mixed>, users: array<string,string>}>
	 */
	private function mapTasks(array $spec, array $rows): array {
		// A task row has no file name to fall back on.
		$this->fileName = '';
		$tasks          = [];
		foreach (array_values($rows) as $index => $row) {
			if (is_array($row) === false || $this->skipped(row: $row, skip: (array)($spec['skip'] ?? [])) === true) {
				continue;
			}

			$this->rowLabel = 'row ' . ($index + 1);
			$mapped         = $this->mapFields(fields: (array)($spec['fields'] ?? []), row: $row);
			if ($mapped['complete'] === false) {
				continue;
			}

			$column = trim((string)self::read(row: $row, source: (string)($spec['column'] ?? '')));
			if (isset($this->knownColumns[$column]) === false) {
				$this->losses[] = $this->rowLabel . ': its list or status "' . $column . '" is not imported, so it was left out.';
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
	 * Map one row's fields; rowLabel names the row in a loss.
	 *
	 * @param array<string,mixed> $fields Target field => mapping.
	 * @param array<mixed>        $row    The source row.
	 *
	 * @return array{data: array<string,mixed>, users: array<string,string>, complete: bool}
	 */
	private function mapFields(array $fields, array $row): array {
		$data  = [];
		$users = [];
		foreach ($fields as $target => $mapping) {
			$this->fieldLabel = $this->rowLabel . ' ' . $target;
			$value            = $this->value(mapping: (array)$mapping, row: $row);
			if ($value === null) {
				if (($mapping['required'] ?? false) === true) {
					$this->losses[] = $this->rowLabel . ': no ' . $target . ', so it was left out.';
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
	 * One mapped value, or null when there is none; fieldLabel names the field in a loss.
	 *
	 * @param array<string,mixed> $mapping The field mapping.
	 * @param array<mixed>        $row     The source row.
	 *
	 * @return string|null The value.
	 */
	private function value(array $mapping, array $row): ?string {
		$raw = trim((string)self::read(row: $row, source: (string)($mapping['source'] ?? '')));
		if ($raw === '' && ($mapping['fallback'] ?? '') === 'fileName') {
			$raw = trim((string)preg_replace('/\.[A-Za-z0-9]+$/', '', basename($this->fileName)));
		}

		if ($raw === '') {
			return null;
		}

		return $this->transform(mapping: $mapping, raw: $raw);
	}//end value()

	/**
	 * A raw value through the mapping's transform: a date, a table lookup, or as is.
	 *
	 * @param array<string,mixed> $mapping The field mapping.
	 * @param string              $raw     The non-empty source value.
	 *
	 * @return string|null The value, or null when it cannot be carried over.
	 */
	private function transform(array $mapping, string $raw): ?string {
		$transform = (string)($mapping['transform'] ?? '');
		if ($transform === 'date') {
			return $this->date(value: $raw);
		}

		if ($transform !== 'map' && $transform !== 'lookup') {
			return $raw;
		}

		$table = (array)($mapping['map'] ?? ($this->lookups[(string)($mapping['lookup'] ?? '')] ?? []));
		if (array_key_exists($raw, $table) === false) {
			$this->losses[] = $this->fieldLabel . ': "' . $raw . '" has no counterpart, so it was left empty.';
			return null;
		}

		return (string)$table[$raw];
	}//end transform()

	/**
	 * A date as Y-m-d; fieldLabel names the field in a loss.
	 *
	 * @param string $value The source value.
	 *
	 * @return string|null The date, or null when it cannot be read.
	 */
	private function date(string $value): ?string {
		foreach (self::DATE_FORMATS as $format) {
			$date = DateTimeImmutable::createFromFormat($format, $value);
			if ($date !== false && DateTimeImmutable::getLastErrors() === false) {
				return $date->format('Y-m-d');
			}
		}

		$this->losses[] = $this->fieldLabel . ': "' . $value . '" is not a date this import reads, so it was left empty.';
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

		$columns = self::csvColumns(header: $header);
		$rows    = [];
		while (($line = fgetcsv($stream, null, ',', '"', '')) !== false) {
			if ($line !== [null]) {
				$rows[] = self::csvRow(line: $line, columns: $columns);
			}
		}

		fclose($stream);

		return $rows;
	}//end readCsv()

	/**
	 * The named header columns by position; a repeated or empty header is left out.
	 *
	 * @param array<int,string|null> $header The header row.
	 *
	 * @return array<int,string> Position => name.
	 */
	private static function csvColumns(array $header): array {
		$columns = [];
		foreach ($header as $position => $name) {
			$name = trim((string)$name);
			if ($name !== '' && in_array($name, $columns, true) === false) {
				$columns[$position] = $name;
			}
		}

		return $columns;
	}//end csvColumns()

	/**
	 * One CSV line keyed by header.
	 *
	 * @param array<int,string|null> $line    The line.
	 * @param array<int,string>      $columns Position => name.
	 *
	 * @return array<string,string> The row.
	 */
	private static function csvRow(array $line, array $columns): array {
		$row = [];
		foreach ($columns as $position => $name) {
			$row[$name] = (string)($line[$position] ?? '');
		}

		return $row;
	}//end csvRow()

}//end class
