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
