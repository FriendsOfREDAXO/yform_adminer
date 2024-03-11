<?php

/**
 * Das Addon ist über die Rechtevergabe auf Nutzung durch Admins
 * bzw. User mit dem Recht "yform_adminer[]" beschränkt.
 *
 * Über diverse EPs werden zusätzliche Buttons an verschiedenen Stellen
 * eingebaut, mit denen Daten im Adminer direkt angezeigt werden:
 *
 *  - Adminer an sich
 *  - Daten der aktuellen Tabelle (Liste)
 *  - Daten des aktuellen Datensatzes (Edit)
 *  - Tabelle rex_yform_table (Liste)
 *  - Aus rex_yform_table der Satz der aktuelle Tabbelle (einzeilige Liste)
 *  - Tabelle rex_yform_field (Liste)
 *  - Aus rex_yform_field die Felder der aktuellen Tabelle (Liste)
 *  - Aus rex_yform_field der Satz des aktuelle Feldes (einzeilige Liste)
 *
 * Welche Button verfügbar sind, hängt von Kontext ab.
 */

namespace FriendsOfRedaxo\YFormAdminer;

use rex;
use rex_addon;
use rex_perm;

// nicht im live-Mode ausführen
if (true === rex::getProperty('live_mode', false)) {
    return;
}

rex_perm::register('yform_adminer[]');

if (rex::isBackend()) {
    $user = rex::getUser();
    if (null !== $user && $user->hasPerm('yform_adminer[]') && rex_addon::get('yform')->isAvailable() && rex_addon::get('adminer')->isAvailable()) {
        YFormAdminer::init();
    }
}
