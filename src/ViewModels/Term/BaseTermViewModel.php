<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\ViewModels\Term;

class BaseTermViewModel
{
	public ?int $id = null;
	public ?int $term_id = null;
	public ?string $name = null;
	public ?string $slug = null;
	public ?int $parent = null;
	public ?int $count = null;

	public function __construct(\WP_Term $term)
	{
		$this->id = $term->term_id;
		$this->term_id = $term->term_id;
		$this->name = $term->name;
		$this->slug = $term->slug;
		$this->parent = $term->parent;
		$this->count = $term->count;
	}

	public function toStdClass(): \stdClass
	{
		$array = get_object_vars($this);

		return json_decode(json_encode($array));
	}
}