<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Services\SeoService;
use Illuminate\Support\Facades\Blade;
use Roots\Acorn\Sage\SageServiceProvider;

class SeoServiceProvider extends SageServiceProvider
{
    public function boot(): void
    {
        Blade::directive('obfuscateHrefScript', function () {
            $attribute = SeoService::OBFUSCATE_ATTRIBUTE;

            return <<<EOF
<script>
	document.addEventListener('DOMContentLoaded', function() {
        var toHandleObfuscation = Array.from(document.querySelectorAll('[$attribute]'));
        
        toHandleObfuscation.forEach(function(elementToHandle) {
			elementToHandle.addEventListener('click', function(event) {
                var base64Url = elementToHandle.getAttribute('$attribute');
                var realUrl = atob(base64Url);
                
                var target = elementToHandle.getAttribute('data-target') ?? elementToHandle.getAttribute('target') ?? '_self';
                
                window.open(realUrl, target);
			});
        });
	});
</script>
EOF;
        });
    }
}
