<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class CssService
{
    /**
     * Strip @layer utilities { … } wrapper from compiled CSS so Tailwind utilities
     * become unlayered and beat WP admin's unlayered element rules via specificity.
     */
    public static function unlayerUtilities(string $css): string
    {
        return self::stripCssLayer($css, 'utilities');
    }

    /**
     * Auto-generate .block-editor__container img.{utility} overrides for every
     * single-class rule in $css that touches an image sizing property.
     *
     * WP admin common.min.css has an unlayered .block-editor__container img rule
     * (specificity 0,1,1) that beats unlayered Tailwind utilities (0,1,0). The
     * generated overrides have specificity 0,2,1, guaranteeing they win regardless
     * of source order. Call this after unlayerUtilities() when injecting app.css
     * into admin or editor contexts.
     */
    public static function addEditorImageOverrides(string $css): string
    {
        $imageProps = ['height', 'width', 'object-fit', 'aspect-ratio', 'object-position'];
        $overrides = [];
        $seen = [];

        // Match single-class rules only (no spaces, combinators, or commas in selector;
        // no nested braces in declarations). Handles both formatted and minified CSS.
        preg_match_all('/(\.[^{\s,>~+]+)\s*\{([^{}]+)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $selector = trim($m[1]);
            $declarations = trim($m[2]);

            if (isset($seen[$selector])) {
                continue;
            }

            foreach ($imageProps as $prop) {
                if (preg_match('/(?:^|;)\s*' . preg_quote($prop, '/') . '\s*:/', $declarations)) {
                    $seen[$selector] = true;
                    $overrides[] = ".block-editor__container img{$selector}{{$declarations}}";
                    break;
                }
            }
        }

        return empty($overrides) ? $css : $css . "\n" . implode('', $overrides);
    }

    /**
     * Convenience wrapper: unlayer Tailwind utilities then add editor image overrides.
     * Call this whenever injecting app.css into a WP admin or block editor context.
     */
    public static function prepareForAdmin(string $css): string
    {
        return self::addEditorImageOverrides(self::unlayerUtilities($css));
    }

    /**
     * Remove @layer <name> { … } wrappers from compiled CSS, keeping inner rules
     * as unlayered declarations. Handles arbitrary nesting depth.
     */
    private static function stripCssLayer(string $css, string $layerName): string
    {
        $out = '';
        $i = 0;
        $len = strlen($css);
        $pattern = '/@layer\s+' . preg_quote($layerName, '/') . '\s*\{/';

        while ($i < $len) {
            if (!preg_match($pattern, $css, $m, PREG_OFFSET_CAPTURE, $i)) {
                $out .= substr($css, $i);
                break;
            }

            $matchPos = $m[0][1];
            $bracePos = $matchPos + strlen($m[0][0]) - 1;

            $out .= substr($css, $i, $matchPos - $i);

            $depth = 1;
            $j = $bracePos + 1;
            while ($j < $len && $depth > 0) {
                if ($css[$j] === '{') {
                    $depth++;
                } elseif ($css[$j] === '}') {
                    $depth--;
                }
                $j++;
            }

            $out .= substr($css, $bracePos + 1, $j - 1 - $bracePos - 1);
            $i = $j;
        }

        return $out;
    }
}
