<?php
/**
 * VORLAGE für die Zugangsdaten zum Mailversand über IONOS.
 *
 * So geht's:
 * 1. Diese Datei kopieren und die Kopie in  his-mail-config.php  umbenennen.
 * 2. In der Kopie das Passwort des Postfachs kontakt@hisobjekt.de eintragen.
 * 3. Die Kopie hochladen – am besten in den Ordner ÜBER public_html, sonst in public_html.
 *    (In public_html ist sie per .htaccess gesperrt und gibt beim Aufruf nichts aus.)
 *
 * Die echte Datei his-mail-config.php niemals per E-Mail/Chat verschicken oder ins Git-Repository legen.
 * Solange his-mail-config.php fehlt, verschickt formular.php wie bisher per PHP mail().
 */
return [
    'server'     => 'smtp.ionos.de',
    'port'       => 587,          // 587 mit STARTTLS (Standard) – alternativ 465 mit 'ssl'
    'sicherheit' => 'tls',        // 'tls' (STARTTLS, Port 587) oder 'ssl' (Port 465)
    'benutzer'   => 'kontakt@hisobjekt.de',
    'passwort'   => '',           // hier das Postfach-Passwort eintragen (nur in der Kopie!)
];
