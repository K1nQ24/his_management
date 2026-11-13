<?php
/**
 * HIS Objektmanagement – Versand von Anfrage- und Kontaktformular per E-Mail.
 * Versand über den Mailserver von IONOS (SMTP mit Anmeldung), sobald die Zugangsdatei his-mail-config.php
 * vorhanden ist (Vorlage: his-mail-config.beispiel.php); sonst Fallback auf PHP mail(). Ab PHP 7.4.
 * Speichert keine Formularinhalte auf dem Server.
 *
 * Absender und Empfänger ist kontakt@hisobjekt.de (Postfach bei IONOS); die Adresse des Besuchers steht
 * im Reply-To, damit „Antworten“ direkt an ihn geht.
 * Beim Fallback mail() muss der SPF-Eintrag der Domain den Webserver als Absender erlauben.
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

/**
 * Ordner, in denen Zugangsdatei und Protokoll gesucht bzw. angelegt werden – in dieser Reihenfolge:
 * Home-Verzeichnis des Kontos (bei Hetzner z. B. /usr/home/<konto>, der oberste Ordner im FTP-Programm),
 * der Ordner über dem Webordner und zuletzt der Webordner selbst (dort per .htaccess gesperrt).
 */
function suchordner(): array
{
    $ordner = [getenv('HOME') ?: ''];
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $ordner[] = posix_getpwuid(posix_geteuid())['dir'] ?? '';
    }
    if (function_exists('get_current_user')) {
        $ordner[] = '/usr/home/' . get_current_user();
    }
    $ordner[] = dirname(__DIR__);
    $ordner[] = __DIR__;
    return array_values(array_unique(array_filter($ordner, function ($o) {
        return $o !== '' && @is_dir($o);
    })));
}

/**
 * Technisches Protokoll (ohne Passwort und ohne Kundendaten) in his-formular.log,
 * im ersten beschreibbaren Suchordner (siehe suchordner()).
 */
function protokoll(string $text): void
{
    $ordner = __DIR__;
    foreach (suchordner() as $o) {
        if (@is_writable($o)) {
            $ordner = $o;
            break;
        }
    }
    $zeile = date('Y-m-d H:i:s') . '  ' . $text . "\n";
    if (@file_put_contents($ordner . '/his-formular.log', $zeile, FILE_APPEND | LOCK_EX) === false) {
        error_log('HIS Formular: ' . $text);
    }
}

/**
 * Zugangsdaten für den Versand über IONOS laden (Suchordner siehe suchordner(); im Webordner
 * zusätzlich per .htaccess gesperrt, die Datei gibt beim Aufruf nichts aus).
 */
function smtp_konfiguration(): ?array
{
    foreach (array_map(function ($o) { return $o . '/his-mail-config.php'; }, suchordner()) as $datei) {
        if (@is_file($datei) && @is_readable($datei)) {
            $c = include $datei;
            if (is_array($c) && !empty($c['server']) && !empty($c['benutzer']) && !empty($c['passwort'])) {
                return $c + ['port' => 587, 'sicherheit' => 'tls'];
            }
            protokoll('Zugangsdatei ' . $datei . ' gefunden, aber unvollständig (Server, Benutzer oder Passwort fehlt)');
        }
    }
    // Nachvollziehbar machen, warum nicht über IONOS versendet wird (z. B. open_basedir verhindert Zugriff)
    protokoll('Keine lesbare his-mail-config.php gefunden – Versand per mail(). open_basedir: '
        . (ini_get('open_basedir') ?: 'nicht gesetzt'));
    return null;
}

/** Mail per SMTP mit Anmeldung (AUTH LOGIN) verschicken; STARTTLS (Port 587) oder SSL (Port 465). */
function smtp_senden(array $c, string $von, string $an, string $nachricht): bool
{
    $ssl = $c['sicherheit'] === 'ssl';
    $kontext = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $c['server']]]);
    $fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $c['server'] . ':' . (int) $c['port'], $nr, $fehler, 15, STREAM_CLIENT_CONNECT, $kontext);
    if ($fp === false) {
        protokoll('SMTP: Verbindung zu ' . $c['server'] . ':' . (int) $c['port'] . ' fehlgeschlagen: ' . $fehler . ' (' . $nr . ')');
        return false;
    }
    stream_set_timeout($fp, 15);
    $verlauf = [];
    $schritt = function (string $befehl, array $erwartet) use ($fp, &$verlauf): void {
        if ($befehl !== '') {
            fwrite($fp, $befehl . "\r\n");
        }
        $antwort = '';
        while (($zeile = fgets($fp, 1024)) !== false) {
            $antwort .= $zeile;
            if (strlen($zeile) < 4 || $zeile[3] === ' ') {
                break; // letzte Zeile einer (mehrzeiligen) Antwort
            }
        }
        // Befehl ohne Anmeldedaten mitschreiben (Benutzer/Passwort sind base64 und werden ausgeblendet)
        $anzeige = preg_match('/^(EHLO|STARTTLS|AUTH|MAIL|RCPT|DATA|QUIT)/', $befehl) ? $befehl : ($befehl === '' ? '(Verbindung)' : '(Inhalt/Anmeldung)');
        $verlauf[] = $anzeige . ' -> ' . str_replace(["\r", "\n"], ' ', trim($antwort));
        if (!in_array((int) substr($antwort, 0, 3), $erwartet, true)) {
            throw new RuntimeException(trim($antwort) !== '' ? trim($antwort) : 'keine Antwort');
        }
    };
    try {
        $schritt('', [220]);
        $schritt('EHLO hisobjekt.de', [250]);
        if (!$ssl && $c['sicherheit'] === 'tls') {
            $schritt('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('TLS-Verschlüsselung fehlgeschlagen');
            }
            $schritt('EHLO hisobjekt.de', [250]);
        }
        $schritt('AUTH LOGIN', [334]);
        $schritt(base64_encode($c['benutzer']), [334]);
        $schritt(base64_encode($c['passwort']), [235]);
        $schritt('MAIL FROM:<' . $von . '>', [250]);
        $schritt('RCPT TO:<' . $an . '>', [250, 251]);
        $schritt('DATA', [354]);
        // Zeilen, die mit einem Punkt beginnen, verdoppeln (SMTP-Regel)
        $schritt(preg_replace('/^\./m', '..', $nachricht) . "\r\n.", [250]);
        $schritt('QUIT', [221]);
    } catch (RuntimeException $e) {
        protokoll('SMTP-Fehler: ' . $e->getMessage() . "\n    Verlauf:\n    " . implode("\n    ", $verlauf));
        fclose($fp);
        return false;
    }
    fclose($fp);
    // vorletzte Antwort = Bestätigung des Mailservers nach dem Inhalt (enthält meist eine Vorgangsnummer)
    protokoll('SMTP: von ' . $c['server'] . ' angenommen: ' . ($verlauf[count($verlauf) - 2] ?? ''));
    return true;
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
if (feld('hp_x7') !== '' || ($dauer !== '' && ctype_digit($dauer) && (int) $dauer < MINDESTDAUER)) {
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

$betreffKodiert = mb_encode_mimeheader(mb_substr($betreff, 0, 150), 'UTF-8', 'Q');
$text = quoted_printable_encode(str_replace("\n", "\r\n", implode("\n", $zeilen)));
$smtp = smtp_konfiguration();

if ($smtp !== null) {
    $nachricht = implode("\r\n", [
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@hisobjekt.de>',
        'To: <' . EMPFAENGER . '>',
        'Subject: ' . $betreffKodiert,
        $kopfzeilen,
    ]) . "\r\n\r\n" . $text;
    $gesendet = smtp_senden($smtp, ABSENDER, EMPFAENGER, $nachricht);
    protokoll('Versand über ' . $smtp['server'] . ($gesendet ? ' erfolgreich' : ' FEHLGESCHLAGEN'));
} else {
    $gesendet = mail(EMPFAENGER, $betreffKodiert, $text, $kopfzeilen, '-f' . ABSENDER);
    protokoll('Versand per mail() ' . ($gesendet ? 'an den lokalen Mailserver übergeben' : 'FEHLGESCHLAGEN'));
}

zurueck($seite, $gesendet ? 'ok' : 'fehler');
