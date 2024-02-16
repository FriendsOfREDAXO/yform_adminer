<?php

namespace FriendsOfRedaxo\YFormAdminer;

use rex;
use rex_addon;
use rex_path;
use rex_scss_compiler;
use ScssPhp\ScssPhp\Formatter\Expanded;

/** @var rex_addon $this */

/**
 * Erstellt eine CSS-Datei basierend auf den Backend-Styles aus dem Addon be_style (falls aktiv).
 * rex_scss_compiler ist verfügbar wenn be_style installiert ist.
 * Klartext-Ausgabe falls man für Tests "lesbares" CSS erzeugen möchte
 */
if (class_exists(rex_scss_compiler::class)) {
    $compiler = new rex_scss_compiler();

    if (rex::isDebugMode() || false === $this->getProperty('compress_assets', true)) {
        $compiler->setFormatter(Expanded::class);
    }

    $compiler->setRootDir(__DIR__ . '/scss');
    $compiler->setScssFile([
        rex_path::plugin('be_style', 'redaxo', 'scss/_variables.scss'),
        rex_path::plugin('be_style', 'redaxo', 'scss/_variables-dark.scss'),
        rex_path::addon('be_style', 'vendor/font-awesome/scss/_variables.scss'),
        __DIR__ . '/scss/be.scss',
    ]);

    $compiler->setCssFile(__DIR__ . '/assets/be.min.css');
    $compiler->compile();
}
