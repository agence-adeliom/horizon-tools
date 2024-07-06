<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Database;

use LucasVigneron\SageTools\Http\Request\Request;

class QueryBuilder
{
	private const ORDER_BY_META_KEY = 'meta_value';
	private const ORDER_BY_META_KEY_NUM = 'meta_value_num';

	private array $postTypes = [];
	private array $idIn = [];
	private array $idNotIn = [];
	private array $metaQueries = [];
	private array $taxQueries = [];
	private ?int $perPage = null;
	private int $page = 1;
	private string $orderBy = 'date';
	private string $order = 'DESC';
	private ?string $orderMetaKey = null;
	private ?string $status = 'publish';
	private ?\WP_Query $WP_Query = null;

	private function triggerChange(): void
	{
		$this->WP_Query = null;
	}

	public function postType(string|array $postType): self
	{
		$this->triggerChange();

		if (is_string($postType)) {
			$postType = [$postType];
		}

		foreach ($postType as $item) {
			if (!in_array($item, $this->postTypes)) {
				$this->postTypes[] = $item;
			}
		}

		return $this;
	}

	public function whereIdIn(int|array $ids): self
	{
		$this->triggerChange();

		if (is_int($ids)) {
			$ids = [$ids];
		}

		foreach ($ids as $item) {
			if (!in_array($item, $this->idIn)) {
				$this->idIn[] = $item;
			}
		}

		return $this;
	}

	public function removeIdIn(int|array $ids): self
	{
		$this->triggerChange();

		if (is_int($ids)) {
			$ids = [$ids];
		}

		foreach ($ids as $item) {
			if (in_array($item, $this->idIn)) {
				$this->idIn = array_diff($this->idIn, [$item]);
			}
		}

		return $this;
	}

	public function whereIdNotIn(int|array $ids): self
	{
		$this->triggerChange();

		if (is_int($ids)) {
			$ids = [$ids];
		}

		foreach ($ids as $item) {
			if (!in_array($item, $this->idNotIn)) {
				$this->idNotIn[] = $item;
			}
		}

		return $this;
	}

	public function removeIdNotIn(int|array $ids): self
	{
		$this->triggerChange();

		if (is_int($ids)) {
			$ids = [$ids];
		}

		foreach ($ids as $item) {
			if (in_array($item, $this->idNotIn)) {
				$this->idNotIn = array_diff($this->idNotIn, [$item]);
			}
		}

		return $this;
	}

	public function addMetaQuery(MetaQuery $metaQuery): self
	{
		$this->triggerChange();

		if ($metaQuery->getQuery()) {
			$this->metaQueries[] = $metaQuery;
		}

		return $this;
	}

	public function addTaxQuery(TaxQuery $taxQuery): self
	{
		$this->triggerChange();

		if ([] !== $taxQuery->getQuery()) {
			$this->taxQueries[] = $taxQuery;
		}

		return $this;
	}

	public function setPage(int $page): self
	{
		$this->triggerChange();

		$this->page = $page;

		return $this;
	}

	public function setPerPage(?int $perPage): self
	{
		$this->triggerChange();

		$this->perPage = $perPage;

		return $this;
	}

	public function setStatus(string $status): self
	{
		$this->triggerChange();

		if (in_array($status, ['any', 'publish', 'pending', 'draft', 'future', 'auto-draft', 'private', 'inherit', 'trash'])) {
			$this->status = $status;
		}

		return $this;
	}

	public function orderBy(string $order = 'DESC', string $orderBy = 'date', bool $isMeta = false, bool $isMetaNumeric = false): self
	{
		$this->triggerChange();

		$this->order = $order;

		if (!$isMeta) {
			$this->orderBy = $orderBy;
		} else {
			if ($isMetaNumeric) {
				$this->orderBy = self::ORDER_BY_META_KEY_NUM;
			} else {
				$this->orderBy = self::ORDER_BY_META_KEY;
			}

			$this->orderMetaKey = $orderBy;
		}

		return $this;
	}

	public function getQuery(): \WP_Query
	{
		if (null === $this->WP_Query) {
			$args = [];

			if ($this->status) {
				$args['post_status'] = $this->status;
			}

			if ($this->postTypes) {
				$args['post_type'] = $this->postTypes;
			}

			if ($this->idIn) {
				$args['post__in'] = $this->idIn;
			}

			if ($this->idNotIn) {
				$args['post__not_in'] = $this->idNotIn;
			}

			if ($this->page) {
				$args['page'] = $this->page;

				if ($this->perPage) {
					$args['posts_per_page'] = $this->perPage;
					$args['offset'] = ($this->page - 1) * $this->perPage;
				} else {
					$args['offset'] = 0;
				}
			}

			if ([] !== $this->metaQueries) {
				foreach ($this->metaQueries as $metaQuery) {
					if ($metaQuery instanceof MetaQuery) {
						$args['meta_query'][] = $metaQuery->generateMetaQueryArray();
					}
				}
			}

			if ([] !== $this->taxQueries) {
				foreach ($this->taxQueries as $taxQuery) {
					if ($taxQuery instanceof TaxQuery) {
						$args['tax_query'][] = $taxQuery->generateTaxQueryArray();
					}
				}
			}

			$args['orderby'] = $this->orderBy;
			$args['order'] = $this->order;

			if (in_array($this->orderBy, [self::ORDER_BY_META_KEY, self::ORDER_BY_META_KEY_NUM])) {
				$args['meta_key'] = $this->orderMetaKey;
			}

			$this->WP_Query = new \WP_Query($args);
		}


		return $this->WP_Query;
	}

	/**
	 * @return \WP_Post[]
	 */
	public function get(?string $asClass = null, ?callable $callback = null): array
	{
		$results = $this->getQuery()
			->posts;

		if (null !== $asClass && class_exists($asClass)) {
			$results = array_map(function ($post) use ($asClass, $callback) {
				return null !== $callback ? $callback(new $asClass($post)) : new $asClass($post);
			}, $results);
		} else {
			if (null !== $callback) {
				$results = array_map($callback, $results);
			}
		}

		return $results;
	}

	public function getOneOrNull(?string $viewModelClassName = null): mixed
	{
		$query = clone $this->getQuery();

		$query->query['posts_per_page'] = 1;
		$query->set('posts_per_page', 1);

		if ($results = $query->get_posts()) {
			if ($viewModelClassName) {
				return new $viewModelClassName($results[0]);
			}

			return $results[0];
		}

		return null;
	}

	public function getCount(): ?int
	{
		return $this->getQuery()->found_posts;
	}

	public function getPagesCount(): ?int
	{
		if ($pages = $this->getQuery()?->max_num_pages) {
			return intval($pages);
		}

		return null;
	}

	public function getPaginatedData(?string $asClass = null, ?callable $callback = null): array
	{
		$items = $this->get(asClass: $asClass, callback: $callback);

		return [
			'items' => $items,
			'pages' => $this->getPagesCount(),
			'total' => $this->getCount(),
			'current' => $this->page,
		];
	}
}
