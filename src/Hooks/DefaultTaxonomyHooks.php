<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Services\ClassService;

class DefaultTaxonomyHooks extends AbstractHook
{
    public function init(): void
    {
        add_action('admin_head', [$this, 'handleReadAndEditOnly']);
    }

    public function handleReadAndEditOnly(): void
    {
        $currentTaxonomy = request()->get('taxonomy');

        if (!$currentTaxonomy) {
            return;
        }

        $class = ClassService::getTaxonomyClassBySlug(slug: $currentTaxonomy);

        if (!$class) {
            return;
        }

        $readOnly = $class::$readOnly ?? false;
        $editOnly = $class::$editOnly ?? false;

        if (!$readOnly && !$editOnly) {
            return;
        }

        $screen = get_current_screen();

        switch ($screen->base) {
            case 'edit-tags':
                echo <<<EOF
<style>
#col-container #col-left { display: none;}

#col-container #col-right { width: 100%; }
</style>
EOF;
                break;
            case 'term':
                break;
                echo <<<EOF
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('name')?.setAttribute('disabled', 'disabled');
    document.getElementById('slug')?.setAttribute('disabled', 'disabled');
    document.getElementById('description')?.setAttribute('disabled', 'disabled');
    Array.from(document.querySelectorAll('input[name="acf[field_9baad940]"]')).forEach(function(field){
        if(field) {
            field.setAttribute('disabled', 'disabled');
        }
    })
});
</script>
EOF;
            default:
                break;
        }
    }
}
