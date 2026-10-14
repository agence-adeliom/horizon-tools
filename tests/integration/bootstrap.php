<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

/*
 * Helpers des contrôles d'intégration, exécutés via `wp eval-file` dans un projet
 * Horizon (WordPress + Acorn chargés). Pas de PHPUnit : ces contrôles ont besoin
 * d'ACF, de la base et de la configuration Acorn du projet.
 */

$GLOBALS['hz_failures'] = 0;

function hz_check(bool $condition, string $label): void
{
    echo ($condition ? '  OK    ' : '  ÉCHEC ') . $label . PHP_EOL;

    if (!$condition) {
        $GLOBALS['hz_failures']++;
    }
}

function hz_done(): void
{
    $failures = $GLOBALS['hz_failures'];
    echo PHP_EOL . ($failures === 0 ? 'Tous les contrôles passent.' : sprintf('%d contrôle(s) en échec.', $failures)) . PHP_EOL;

    if ($failures > 0) {
        exit(1);
    }
}
