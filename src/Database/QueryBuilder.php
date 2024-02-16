<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Database;

class QueryBuilder
{
	private array $postTypes = [];
	private array $idIn = [];
	private array $idNotIn = [];
	private array $metaQuery = [];
	private ?int $perPage = null;
	private ?int $page = null;

	public function addPostType(string|array $postType): self
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

	public function addIdIn(int|array $ids): self
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

	public function addIdNotIn(int|array $ids): self
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

	public function addMeta()
	{

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

		return new \WP_Query($args);
	}

	/**
	 * @return \WP_Post[]
	 */
	public function get(): array
	{
		$results = $this->getQuery()
			->get_posts();

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
