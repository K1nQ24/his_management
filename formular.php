<?php
/**
 * HIS Objektmanagement – Versand von Anfrage- und Kontaktformular per E-Mail.
 * Läuft auf dem IONOS-Webhosting (PHP mail()). Speichert keine Daten auf dem Server.
 *
 * Absender ist immer die eigene Adresse (IONOS verlangt eine Absenderadresse der eigenen Domain);
 * die Adresse des Besuchers steht im Reply-To, damit „Antworten“ direkt an ihn geht.
 */

declare(strict_types=1);

const EMPFAENGER = 'kontakt@hisobjekt.de';
const ABSENDER   = 'kontakt@hisobjekt.de';

const LEISTUNGEN = [
    'objektmanagement'       => 'Objektmanagement & Hausmeisterservice',
    'entruempelung'          => 'Entrümpelung & Räumung',
    'reinigung'              => 'Gebäude- & Glasreinigung',
    'garten'                 => 'Garten- & Grundstückspflege',
    'landwirtschaft'         => 'Landwirtschaftliche Flächen & Großflächen',
    'winterdienst'           => 'Winterdienst & Kehrwochendienst',
    'transport'              => 'Transport- & Umzugsservice',
    'montage'                => 'Einfache Montagearbeiten',
    'immobilienvorbereitung' => 'Immobilienvorbereitung',
    'aussenflaechen'         => 'Außenflächenpflege',
];

/** Zurück zur Formularseite mit Statusmeldung. */
function zurueck(string $seite, string $status): never
{
    header('Location: ' . $seite . '?status=' . $status . '#formular', true, 303);
    exit;
}

/** Text aus dem Formular holen: getrimmt, Längenbegrenzung, Steuerzeichen entfernt. */
function feld(string $name, int $max = 200, bool $mehrzeilig = false): string
{
    $wert = isset($_POST[$name]) && is_string($_POST[$name]) ? trim($_POST[$name]) : '';
    $wert = $mehrzeilig
        ? preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $wert))
        : preg_replace('/\p{C}/u', ' ', $wert);
    return mb_substr((string) $wert, 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.html', true, 303);
    exit;
}

$typ   = feld('formular') === 'kontakt' ? 'kontakt' : 'anfrage';
$seite = $typ === 'kontakt' ? 'kontakt.html' : 'anfrage.html';

// Honeypot: Bots füllen das unsichtbare Feld aus – still „erfolgreich“ beenden
if (feld('website') !== '') {
    zurueck($seite, 'ok');
}

$name      = feld('name', 120);
$email     = feld('email', 200);
$telefon   = feld('telefon', 60);
$ort       = feld('ort', 120);
$zeitraum  = feld('zeitraum', 200);
$nachricht = feld('nachricht', 5000, true);
$datenschutz = isset($_POST['datenschutz']);

$gewaehlt = [];
if (isset($_POST['leistung']) && is_array($_POST['leistung'])) {
    foreach ($_POST['leistung'] as $id) {
        if (is_string($id) && isset(LEISTUNGEN[$id])) {
            $gewaehlt[] = LEISTUNGEN[$id];
        }
    }
}

// Serverseitige Prüfung (zusätzlich zur Prüfung im Browser)
$gueltig = $name !== ''
    && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
    && $datenschutz
    && ($typ === 'kontakt' ? $nachricht !== '' : ($ort !== '' && $gewaehlt !== []));

if (!$gueltig) {
    zurueck($seite, 'fehler');
}

$zeilen = [
    $typ === 'kontakt' ? 'Neue Kontaktanfrage über die Website' : 'Neue Anfrage über die Website',
    str_repeat('-', 44),
    'Name:            ' . $name,
    'E-Mail:          ' . $email,
    'Telefon:         ' . ($telefon !== '' ? $telefon : '–'),
];
if ($typ === 'anfrage') {
    $zeilen[] = 'Objektort / PLZ: ' . $ort;
    $zeilen[] = 'Leistung(en):    ' . implode(', ', $gewaehlt);
    $zeilen[] = 'Zeitraum:        ' . ($zeitraum !== '' ? $zeitraum : '–');
}
$zeilen[] = '';
$zeilen[] = 'Nachricht:';
$zeilen[] = $nachricht !== '' ? $nachricht : '–';
$zeilen[] = '';
$zeilen[] = str_repeat('-', 44);
$zeilen[] = 'Datenschutzhinweis bestätigt: ja';

$betreff = ($typ === 'kontakt' ? 'Kontaktanfrage' : 'Anfrage') . ' von ' . $name
    . ($gewaehlt !== [] ? ' – ' . implode(', ', $gewaehlt) : '');

// $email ist durch FILTER_VALIDATE_EMAIL geprüft und enthält keine Zeilenumbrüche
$kopfzeilen = implode("\r\n", [
    'From: HIS Objektmanagement Website <' . ABSENDER . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);

$gesendet = mail(
    EMPFAENGER,
    mb_encode_mimeheader(mb_substr($betreff, 0, 150), 'UTF-8', 'Q'),
    implode("\n", $zeilen),
    $kopfzeilen,
    '-f' . ABSENDER
);

zurueck($seite, $gesendet ? 'ok' : 'fehler');
