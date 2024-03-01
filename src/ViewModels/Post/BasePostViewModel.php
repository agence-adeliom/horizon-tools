<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\ViewModels\Post;

class BasePostViewModel
{
	public ?int $id = null;
	public ?string $title = null;
	public ?string $slug= null;

	public function __construct(\WP_Post $post)
	{
		$this->id = $post->ID;
		$this->title = $post->post_title;
		$this->slug = $post->post_name;
	}
}