<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Hooks;

use Adeliom\SageTools\Services\BudService;

class DefaultGutenbergHooks extends AbstractHook
{
	public function init(): void
	{
		add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
	}

	public function enqueueBlockEditorAssets(): void
	{
		$style = BudService::getUrl('app.css');

		if (!$style) {
			$style = BudService::getUrlByRegex('/app.[0-9-a-z-A-Z]+.css/');
		}

		if ($style) {
			wp_enqueue_style('gutenberg-tailwind', $style, ['common']);
		}
	}
}