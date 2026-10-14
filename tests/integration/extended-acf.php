<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Fields\Text\EncryptedText;

require __DIR__ . '/bootstrap.php';

$settings = EncryptedText::make('Clé API Google Maps', 'googleMapsKey')
    ->writeOnly()
    ->helperText('Clé navigateur')
    ->required()
    ->get('group_test');
hz_check($settings['type'] === 'encrypted_text', 'type encrypted_text');
hz_check($settings['name'] === 'googleMapsKey', 'nom conservé');
hz_check($settings['revealable'] === false, 'writeOnly() désactive le réglage affichable');
hz_check($settings['instructions'] === 'Clé navigateur', 'helperText() disponible');
hz_check($settings['required'] === true, 'required() disponible');

$default = EncryptedText::make('Jeton CRM')->get('group_test');
hz_check(!isset($default['revealable']) || $default['revealable'] === true, 'affichable par défaut');
$explicit = EncryptedText::make('Jeton CRM', 'jetonCrmExplicite')->writeOnly(false)->get('group_test');
hz_check($explicit['revealable'] === true, 'writeOnly(false) : affichable');

hz_done();
