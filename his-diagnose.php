<?php
/**
 * HIS Objektmanagement – EINMALIGE Diagnose für den Formularversand.
 * Gibt KEIN Passwort aus und verschickt KEINE Mail. Nach der Prüfung bitte vom Server löschen.
 * Aufruf nur mit Schlüssel: /his-diagnose.php?k=b946fc841d4bff3edadae470
 */
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
if (($_GET['k'] ?? '') !== 'b946fc841d4bff3edadae470') { http_response_code(404); exit; }

$z = function (string $t) { echo $t, "\n"; flush(); };
$z('PHP ' . PHP_VERSION . ' | SAPI ' . PHP_SAPI . ' | Benutzer ' . (function_exists('get_current_user') ? get_current_user() : '?'));
$z('Webordner: ' . __DIR__ . ' | darüber: ' . dirname(__DIR__));
$z('open_basedir: ' . (ini_get('open_basedir') ?: 'nicht gesetzt') . ' | disable_functions: ' . (ini_get('disable_functions') ?: '-'));
$home = [getenv('HOME') ?: '-', (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? '-') : 'posix fehlt'), '/usr/home/' . get_current_user()];
$z('Home-Kandidaten: ' . implode(' | ', array_map(function ($h) { return $h . (@is_dir($h) ? ' (vorhanden' . (@is_writable($h) ? ', beschreibbar)' : ', nicht beschreibbar)') : ' (fehlt)'); }, $home)));
$z('formular.php md5: ' . (@is_file(__DIR__ . '/formular.php') ? md5_file(__DIR__ . '/formular.php') : 'FEHLT'));
$z('Schreibrecht darüber: ' . (@is_writable(dirname(__DIR__)) ? 'ja' : 'nein') . ' | im Webordner: ' . (@is_writable(__DIR__) ? 'ja' : 'nein'));
$orte = array_values(array_unique(array_filter(array_merge($home, [dirname(__DIR__), __DIR__]), function ($o) { return $o !== '-' && @is_dir($o); })));
foreach (array_map(function ($o) { return $o . '/his-formular.log'; }, $orte) as $l) { $z('Protokoll ' . $l . ': ' . (@is_file($l) ? filesize($l) . ' Byte' : 'nicht vorhanden')); }

$cfg = null;
foreach (array_map(function ($o) { return $o . '/his-mail-config.php'; }, $orte) as $d) {
    $da = @is_file($d); $les = $da && @is_readable($d);
    $z('Zugangsdatei ' . $d . ': ' . ($da ? 'vorhanden' : 'fehlt') . ($da ? ($les ? ', lesbar' : ', NICHT lesbar') : ''));
    if ($les && $cfg === null) {
        $c = include $d;
        if (!is_array($c)) { $z('  -> liefert kein Array (Syntax/return prüfen)'); continue; }
        $z('  server=' . ($c['server'] ?? '-') . ' port=' . ($c['port'] ?? '-') . ' sicherheit=' . ($c['sicherheit'] ?? '-') . ' benutzer=' . ($c['benutzer'] ?? '-') . ' passwort=' . (empty($c['passwort']) ? 'LEER' : 'gesetzt (' . strlen((string) $c['passwort']) . ' Zeichen)'));
        $cfg = $c + ['port' => 587, 'sicherheit' => 'tls'];
    }
}
if ($cfg === null) { $z('=> keine nutzbare Zugangsdatei'); exit; }

// Verbindung + Anmeldung testen (kein MAIL FROM, keine Mail)
$ssl = $cfg['sicherheit'] === 'ssl';
$ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $cfg['server']]]);
$fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $cfg['server'] . ':' . (int) $cfg['port'], $nr, $err, 15, STREAM_CLIENT_CONNECT, $ctx);
if (!$fp) { $z('SMTP-Verbindung FEHLGESCHLAGEN: ' . $err . ' (' . $nr . ')'); exit; }
stream_set_timeout($fp, 15);
$r = function () use ($fp) { $a = ''; while (($l = fgets($fp, 1024)) !== false) { $a .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return trim(str_replace(["\r", "\n"], ' ', $a)); };
$s = function (string $cmd, string $zeige) use ($fp, $r, $z) { fwrite($fp, $cmd . "\r\n"); $a = $r(); $z($zeige . ' -> ' . $a); return (int) substr($a, 0, 3); };
$z('Verbindung -> ' . $r());
$s('EHLO hisobjekt.de', 'EHLO');
if (!$ssl && $cfg['sicherheit'] === 'tls') {
    if ($s('STARTTLS', 'STARTTLS') === 220) {
        $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        $z('TLS: ' . ($ok ? 'ok' : 'FEHLGESCHLAGEN ' . (error_get_last()['message'] ?? '')));
        if (!$ok) exit;
        $s('EHLO hisobjekt.de', 'EHLO');
    }
}
$s('AUTH LOGIN', 'AUTH LOGIN');
$s(base64_encode((string) $cfg['benutzer']), '(Benutzer)');
$code = $s(base64_encode((string) $cfg['passwort']), '(Passwort)');
$z($code === 235 ? '=> ANMELDUNG ERFOLGREICH' : '=> ANMELDUNG ABGELEHNT');
$s('QUIT', 'QUIT');
