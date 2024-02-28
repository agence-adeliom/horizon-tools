<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Database;

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

	public function postType(string|array $postType): self
	{
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
		if ($metaQuery->getQuery()) {
			$this->metaQueries[] = $metaQuery;
		}

		return $this;
	}

	public function addTaxQuery(TaxQuery $taxQuery): self
	{
		if ([] !== $taxQuery->getQuery()) {
			$this->taxQueries[] = $taxQuery;
		}

		return $this;
	}

	public function setPage(int $page): self
	{
		$this->page = $page;

		return $this;
	}

	public function setPerPage(?int $perPage): self
	{
		$this->perPage = $perPage;

		return $this;
	}

	public function getQuery(): \WP_Query
	{
		$args = [];

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

		return new \WP_Query($args);
	}

	/**
	 * @return \WP_Post[]
	 */
	public function get(): array
	{
		$results = $this->getQuery()
			->posts;

		return $results;
	}

	public function getOneOrNull(): ?\WP_Post
	{
		$query = $this->getQuery();

		$query->query['posts_per_page'] = 1;
		$query->set('posts_per_page', 1);

		if ($results = $query->get_posts()) {
			return $results[0];
		}

		return null;
	}

	public function getCount(): ?int
	{
		return $this->getQuery()->found_posts;
	}

	public function orderBy(string $order = 'DESC', string $orderBy = 'date', bool $isMeta = false, bool $isMetaNumeric = false): self
	{
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
}
