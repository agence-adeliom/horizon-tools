<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Hooks;

use Adeliom\SageTools\PostTypes\AbstractPostType;
use Adeliom\SageTools\Services\ClassService;
use Adeliom\SageTools\Services\SeoService;

class RankMathHooks extends AbstractHook
{
	public function init(): void
	{
		if (SeoService::isRankMathActive()) {
			add_filter('rank_math/frontend/breadcrumb/items', [$this, 'filterCrumbs'], accepted_args: 2);
		}
	}

	public function filterCrumbs(array $crumbs)
	{
		if (count($crumbs) === 2) {
			foreach (ClassService::getAllCustomPostTypeClasses() as $postTypeClass) {
				$postTypeInstance = new $postTypeClass();

				if ($postTypeInstance instanceof AbstractPostType) {
					$config = $postTypeInstance->getConfig();

					if (isset($config['args']['rewrite']['slug'])) {
						$options = get_fields('option');

						if (isset($options['pages'])) {
							foreach ($options['pages'] as $class => $page) {
								if ($page instanceof \WP_Post) {
									if (class_exists($class)) {
										$slug = $page->post_name;

										$url = sprintf('%s/%s/', home_url(), $slug);

										if (isset($crumbs[1][1])) {
											$test = $crumbs[1][1];
											if ($test !== $url && str_contains($crumbs[1][1], $url)) {
												$newCrumbs = [];

												$newCrumbs[0] = $crumbs[0];
												$newCrumbs[1] = [
													0 => $page->post_title,
													1 => $url,
													'hide_in_schema' => false,
												];
												$newCrumbs[2] = $crumbs[1];

												return $newCrumbs;
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		return $crumbs;
	}
}