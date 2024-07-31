<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Hooks;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Facades\Config;

class DefaultWordPressHooks extends AbstractHook
{
	public function init(): void
	{
		add_filter('upload_mimes', [$this, 'allowedMimeTypes']);
		add_filter('wp_handle_upload_prefilter', [$this, 'cleanMedias']);
	}

	public function allowedMimeTypes($mimes): array
	{
		if (is_array($mimes)) {
			if (Config::get('medias.allow.svg')) {
				$mimes['svg'] = 'image/svg+xml';
			}
		}

		return $mimes;
	}

	public function cleanMedias($file): array
	{
		switch ($file['type']) {
			case 'image/svg+xml':
				if (Config::get('medias.sanitize.svg')) {
					$sanitizer = new Sanitizer();
					$cleanSvg = $sanitizer->sanitize(file_get_contents($file['tmp_name']));

					if ($cleanSvg) {
						file_put_contents($file['tmp_name'], $cleanSvg);
					} else {
						$file['error'] = 'Failed to sanitize SVG';
					}
				}
				break;
			default:
				break;
		}

		return $file;
	}
}