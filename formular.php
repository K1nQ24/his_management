<?php
/**
 * HIS Objektmanagement – Versand von Anfrage- und Kontaktformular per E-Mail.
 * Läuft auf dem IONOS-Webhosting (PHP mail(), ab PHP 7.4). Speichert keine Formularinhalte auf dem Server.
 *
 * Absender ist immer die eigene Adresse (IONOS verlangt eine Absenderadresse der eigenen Domain);
 * die Adresse des Besuchers steht im Reply-To, damit „Antworten“ direkt an ihn geht.
 *
 * Schutzmaßnahmen: Honeypot-Feld, Mindest-Ausfüllzeit, nur Absendungen von der eigenen Domain,
 * Begrenzung der Sendungen pro Absender und insgesamt, serverseitige Prüfung aller Felder.
 */

declare(strict_types=1);

// Keine PHP-Fehlermeldungen an Besucher ausgeben (verrät sonst Serverpfade); Fehler nur ins Log
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header_remove('X-Powered-By');

const EMPFAENGER = 'kontakt@hisobjekt.de';
const ABSENDER   = 'kontakt@hisobjekt.de';

// Begrenzung: höchstens 3 Sendungen pro Absender in 10 Minuten und 30 Sendungen insgesamt pro Stunde
const LIMIT_PRO_ABSENDER = 3;
const FENSTER_ABSENDER   = 600;
const LIMIT_GESAMT       = 30;
const FENSTER_GESAMT     = 3600;
// Menschen brauchen zum Ausfüllen mehr als 3 Sekunden; schnellere Absendungen stammen von Bots
const MINDESTDAUER       = 3;

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
function zurueck(string $seite, string $status): void
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

/** Stammt die Absendung von der eigenen Seite? (Schutz vor Absendungen über fremde Websites) */
function eigene_herkunft(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return true; // ältere Browser senden keinen Origin – dann greifen die übrigen Schutzmaßnahmen
    }
    $host = parse_url($origin, PHP_URL_HOST);
    return is_string($host) && strcasecmp($host, (string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) === 0;
}

/**
 * Sendungen begrenzen. Gespeichert wird nur ein Hashwert der IP-Adresse (mit täglich wechselndem Salt)
 * und der Zeitpunkt – höchstens für FENSTER_GESAMT Sekunden.
 * Hinweis: Hinter einem CDN wie Cloudflare ist REMOTE_ADDR die Adresse des CDN; die Gesamtgrenze greift trotzdem.
 */
function sendung_erlaubt(): bool
{
    $datei = sys_get_temp_dir() . '/his_formular_' . md5(__DIR__) . '.json';
    $fh = @fopen($datei, 'c+');
    if ($fh === false) {
        return true; // Speicher nicht verfügbar: Formular nicht blockieren
    }
    flock($fh, LOCK_EX);
    $inhalt = stream_get_contents($fh);
    $eintraege = json_decode($inhalt !== false && $inhalt !== '' ? $inhalt : '[]', true);
    $eintraege = is_array($eintraege) ? $eintraege : [];

    $jetzt = time();
    $absender = hash('sha256', date('Y-m-d') . __DIR__ . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $eintraege = array_values(array_filter($eintraege, function ($e) use ($jetzt) {
        return is_array($e) && isset($e[1]) && $jetzt - (int) $e[1] < FENSTER_GESAMT;
    }));
    $vomAbsender = count(array_filter($eintraege, function ($e) use ($jetzt, $absender) {
        return $e[0] === $absender && $jetzt - (int) $e[1] < FENSTER_ABSENDER;
    }));

    $erlaubt = $vomAbsender < LIMIT_PRO_ABSENDER && count($eintraege) < LIMIT_GESAMT;
    if ($erlaubt) {
        $eintraege[] = [$absender, $jetzt];
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string) json_encode($eintraege));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $erlaubt;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.html', true, 303);
    exit;
}

$typ   = feld('formular') === 'kontakt' ? 'kontakt' : 'anfrage';
$seite = $typ === 'kontakt' ? 'kontakt.html' : 'anfrage.html';

// Absendungen über fremde Websites ablehnen
if (!eigene_herkunft()) {
    zurueck($seite, 'fehler');
}

// Honeypot ausgefüllt oder in unter 3 Sekunden abgeschickt: Bot – still „erfolgreich“ beenden
$dauer = feld('dauer', 10);
if (feld('website') !== '' || ($dauer !== '' && ctype_digit($dauer) && (int) $dauer < MINDESTDAUER)) {
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
$gewaehlt = array_values(array_unique($gewaehlt));

// Serverseitige Prüfung (zusätzlich zur Prüfung im Browser)
$gueltig = $name !== ''
    && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
    && $datenschutz
    && ($typ === 'kontakt' ? $nachricht !== '' : ($ort !== '' && $gewaehlt !== []));

if (!$gueltig) {
    zurueck($seite, 'fehler');
}

// Erst nach erfolgreicher Prüfung zählen, damit Tippfehler nicht zur Sperre führen
if (!sendung_erlaubt()) {
    zurueck($seite, 'limit');
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

// $email ist durch FILTER_VALIDATE_EMAIL geprüft und enthält keine Zeilenumbrüche.
// Quoted-Printable bricht lange Zeilen normgerecht um (max. 76 Zeichen), Umlaute bleiben erhalten.
$kopfzeilen = implode("\r\n", [
    'From: HIS Objektmanagement Website <' . ABSENDER . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: quoted-printable',
]);

$gesendet = mail(
    EMPFAENGER,
    mb_encode_mimeheader(mb_substr($betreff, 0, 150), 'UTF-8', 'Q'),
    quoted_printable_encode(str_replace("\n", "\r\n", implode("\n", $zeilen))),
    $kopfzeilen,
    '-f' . ABSENDER
);

zurueck($seite, $gesendet ? 'ok' : 'fehler');
