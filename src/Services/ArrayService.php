<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class ArrayService
{
    public static function insertAtIndex(array &$array, int $index, $value): void
    {
        $head = array_slice($array, 0, $index, true);
        $tail = array_slice($array, $index, null, true);

        // Décale manuellement les clés
        $tail = array_combine(array_map(fn($k) => $k + 1, array_keys($tail)), array_values($tail));

        $array = $head + [$index => $value] + $tail;
    }
}
