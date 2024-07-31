<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Hooks;

use Illuminate\Support\Facades\Config;

class PostHooks extends AbstractHook
{
	private ?string $prefix = null;

	public function init(): void
	{
		if (Config::get('posts.prefix')) {
			$this->prefix = Config::get('posts.prefix');
			add_action('generate_rewrite_rules', [$this, 'add_rewrite_rules']);
			add_filter('post_link', [$this, 'change_blog_links'], 1, 3);
		}
	}

	public function add_rewrite_rules($wp_rewrite)
	{
		$new_rules = array(
			$this->prefix . '/(.+?)/?$' => 'index.php?post_type=post&name=' . $wp_rewrite->preg_index(1),
		);

		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}

	public function change_blog_links($post_link, $id = 0)
	{
		$post = get_post($id);

		if (is_object($post) && $post->post_type == 'post') {
			return home_url('/' . $this->prefix . '/' . $post->post_name . '/');
		}

		return $post_link;
	}
}