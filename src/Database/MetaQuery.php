<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Database;

class MetaQuery
{
	private string $relation = 'AND';

	private array $query = [];

	public function __construct()
	{
	}

	public function add(string|self $nameOrMetaQuery, mixed $value = null, string $comparator = '=', string $type = 'CHAR'): self
	{
		if (is_string($nameOrMetaQuery)) {
			$comparator = strtoupper($comparator);
			$type = strtoupper($type);

			$data = [
				'key' => $nameOrMetaQuery,
				'value' => $value,
			];

			if (in_array($comparator, ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS'])) {
				$data['compare'] = $comparator;
			}

			if (in_array($type, ['NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'])) {
				$data['type'] = $type;
			}

			$this->query[] = $data;
		} else {
			$this->query[] = $nameOrMetaQuery;
		}

		return $this;
	}

	public function getQuery(): array
	{
		return $this->query;
	}

	public function setRelation(string $relation): self
	{
		if (in_array($relation, ['AND', 'OR'])) {
			$this->relation = $relation;
		}

		return $this;
	}

	public function getRelation(): string
	{
		return $this->relation;
	}

	public function generateMetaQueryArray(): array
	{
		$elements = array_map(function ($query) {
			if (is_array($query)) {
				return $query;
			} else if ($query instanceof self) {
				return $query->generateMetaQueryArray();
			}

			return null;
		}, $this->getQuery());

		return [
			'relation' => $this->getRelation(),
			...$elements,
		];
	}
}
