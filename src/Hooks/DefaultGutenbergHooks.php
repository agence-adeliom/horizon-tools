<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Services\BudService;
use Illuminate\Support\Facades\Config;

class DefaultGutenbergHooks extends AbstractHook
{
	public function init(): void
	{
		add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
	}

	public function enqueueBlockEditorAssets(): void
	{
		$toEnqueue = [
			'app.css',
		];

		if ($files = Config::get('gutenberg.assets.enqueue')) {
			$toEnqueue = $files;
		}

		foreach ($toEnqueue as $file) {
			$fileUrl = BudService::getUrl($file);

			if (!$fileUrl) {
				// Get file extension
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				$fileName = pathinfo($file, PATHINFO_FILENAME);

				$fileUrl = BudService::getUrlByRegex(sprintf('/%s.[0-9-a-z-A-Z]+.%s/', $fileName, $ext));
			}

			if ($fileUrl) {
				wp_enqueue_style('gutenberg-' . $file, $fileUrl, ['common']);
			}
		}
	}
}