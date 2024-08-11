<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Database;

class QueryBuilder
{
	private const ORDER_BY_META_KEY = 'meta_value';
	private const ORDER_BY_META_KEY_NUM = 'meta_value_num';

	private bool $isPostType = true;
	private bool $isTaxonomy = false;
	private array $postTypes = [];
	private array $taxonomies = [];
	private array $idIn = [];
	private array $idNotIn = [];
	private array $metaQueries = [];
	private array $taxQueries = [];
	private ?string $asClass = null;
	private ?int $perPage = null;
	private int $page = 1;
	private ?string $slug = null;
	private string $orderBy = 'date';
	private string $order = 'DESC';
	private ?string $orderMetaKey = null;
	private ?string $status = 'publish';
	private bool $hideEmpty = false;
	private ?\WP_Query $WP_Query = null;
	private ?\WP_Term_Query $WP_Term_Query = null;

	private function triggerChange(): void
	{
		$this->WP_Query = null;
		$this->WP_Term_Query = null;
	}

	public function postType(string|array $postType): self
	{
		$this->triggerChange();
		$this->isTaxonomy = false;
		$this->isPostType = true;

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

	public function taxonomy(string|array $taxonomy): self
	{
		$this->triggerChange();
		$this->isPostType = false;
		$this->isTaxonomy = true;

		if (is_string($taxonomy)) {
			$taxonomy = [$taxonomy];
		}

		foreach ($taxonomy as $item) {
			if (!in_array($item, $this->taxonomies)) {
				$this->taxonomies[] = $item;
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

	public function whereSlug(?string $slug = null): self
	{
		$this->triggerChange();

		$this->slug = $slug;

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

	public function fetchEmptyTaxonomies(bool $fetchEmpty = true): self
	{
		$this->triggerChange();
		$this->hideEmpty = !$fetchEmpty;

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

	private function getWpQuery(): \WP_Query
	{
		if (null === $this->WP_Query) {
			$args = [];

			if ($this->status) {
				$args['post_status'] = $this->status;
			}

			if ($this->slug) {
				$args['name'] = $this->slug;
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

	private function getWpTaxQuery(): \WP_Term_Query
	{
		if (null === $this->WP_Term_Query) {
			$args = [];

			if ($this->taxonomies) {
				$args['taxonomy'] = $this->taxonomies;
			}

			if ($this->page) {
				$args['offset'] = ($this->page - 1) * $this->perPage;
			}

			if ($this->perPage) {
				$args['number'] = $this->perPage;
			}

			if ($this->idNotIn) {
				$args['exclude'] = $this->idNotIn;
			}

			if ($this->idIn) {
				$args['include'] = $this->idIn;
			}

			if ($this->slug) {
				$args['slug'] = $this->slug;
			}

			$args['hide_empty'] = $this->hideEmpty;

			if ([] !== $this->metaQueries) {
				foreach ($this->metaQueries as $metaQuery) {
					if ($metaQuery instanceof MetaQuery) {
						$args['meta_query'][] = $metaQuery->generateMetaQueryArray();
					}
				}
			}


			$this->WP_Term_Query = new \WP_Term_Query($args);
		}

		return $this->WP_Term_Query;
	}

	public function getQuery(): \WP_Query|\WP_Term_Query
	{
		if ($this->isPostType) {
			return $this->getWpQuery();
		} elseif ($this->isTaxonomy) {
			return $this->getWpTaxQuery();
		}
	}

	public function as(?string $class = null): self
	{
		$this->asClass = $class;

		return $this;
	}

	/**
	 * @return \WP_Post[]
	 */
	public function get(?callable $callback = null): array
	{
		$results = null;

		if ($this->isPostType) {
			$results = $this->getQuery()
				->posts;
		} elseif ($this->isTaxonomy) {
			$results = $this->getQuery()
				->terms;
		}

		if (!empty($this->asClass) && class_exists($this->asClass)) {
			$results = array_map(function ($postOrTerm) use ($callback) {
				return null !== $callback ? $callback(new $this->asClass($postOrTerm)) : new $this->asClass($postOrTerm);
			}, $results);
		} else {
			if (null !== $callback) {
				$results = array_map($callback, $results);
			}
		}

		return null !== $results ? $results : [];
	}

	public function getOneOrNull(): mixed
	{
		$query = clone $this->getQuery();

		$results = null;

		if ($this->isPostType) {
			$query->query['posts_per_page'] = 1;
			$query->set('posts_per_page', 1);

			$results = $query->get_posts();
		} elseif ($this->isTaxonomy) {
			$query->query['number'] = 1;

			$results = $query->get_terms();
		}

		if ($results) {
			if (!empty($this->asClass)) {
				return new $this->asClass($results[0]);
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
		if ($this->isPostType) {
			if ($pages = $this->getQuery()?->max_num_pages) {
				return intval($pages);
			}
		}

		return null;
	}

	public function getPaginatedData(?callable $callback = null): array
	{
		$items = $this->get(callback: $callback);
		$total = 0;
		$pages = 0;

		if ($this->isPostType) {
			$total = $this->getCount();
			$pages = $this->getPagesCount();
		} elseif ($this->isTaxonomy) {
			$clone = clone $this;
			$clone->setPerPage(null);
			$total = $clone->get();

			$total = count($total);
			$pages = intval(ceil($total / $this->perPage));
		}

		return [
			'items' => $items,
			'pages' => $pages,
			'total' => $total,
			'current' => $this->page,
		];
	}
}
