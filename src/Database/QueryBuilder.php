<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Database;

class QueryBuilder
{
	private array $postTypes = [];
	private array $idIn = [];
	private array $idNotIn = [];
	private array $metaQueries = [];
	private array $taxQueries = [];
	private ?int $perPage = null;
	private ?int $page = null;

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

	public function setPage(?int $page): self
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

		if ($this->perPage && $this->page) {
			$args['posts_per_page'] = $this->perPage;
			$args['offset'] = ($this->page - 1) * $this->perPage;
			$args['page'] = $this->page;
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
}
