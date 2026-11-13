<?php
/**
 * HIS Objektmanagement – Vorlagen für die Formular-Mails (HTML + Textfassung).
 * Wird von formular.php eingebunden; gibt bei direktem Aufruf nichts aus (zusätzlich per .htaccess gesperrt).
 *
 * Gestaltung wie die Website: schwarz-weiß, Logo im schwarzen Kopf, klare Typografie.
 * E-Mail-taugliches HTML: Tabellenlayout, Inline-Styles, Logo als eingebettetes Bild (CID).
 * Alle Eingaben aus dem Formular werden mit e() maskiert, damit niemand HTML in die Mail einschleusen kann.
 */

declare(strict_types=1);

const MAIL_LOGO_CID = 'logo@hisobjekt.de';
const MAIL_SCHRIFT  = "Helvetica,Arial,sans-serif";

function e(string $t): string
{
    return htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Rahmen jeder Mail: Kopf mit Logo, Inhalt, Fuß mit Kontaktdaten. */
function mail_rahmen(string $vorschau, string $inhalt): string
{
    $s = MAIL_SCHRIFT;
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light only">'
        . '<title>HIS Objektmanagement</title></head>'
        . '<body style="margin:0;padding:0;background:#F5F5F5;-webkit-text-size-adjust:100%;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($vorschau) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F5F5F5;">'
        . '<tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#FFFFFF;border-collapse:collapse;">'
        // Kopf
        . '<tr><td style="background:#0A0A0A;padding:30px 36px;">'
        . '<img src="cid:' . MAIL_LOGO_CID . '" width="150" height="65" alt="HIS Objektmanagement" style="display:block;border:0;width:150px;height:auto;">'
        . '</td></tr>'
        // Linie in Anlehnung an die Schwünge des Logos
        . '<tr><td style="height:4px;line-height:4px;font-size:0;background:#1A1A1A;">&nbsp;</td></tr>'
        . $inhalt
        // Fuß
        . '<tr><td style="background:#0A0A0A;padding:28px 36px;font-family:' . $s . ';font-size:13px;line-height:1.7;color:#C9C9C9;">'
        . '<p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:2px;color:#FFFFFF;">HIS OBJEKTMANAGEMENT – ALLES AUS EINER HAND.</p>'
        . '<p style="margin:0;">Wallensteinstraße 1A · 70437 Stuttgart<br>'
        . '<a href="tel:+4917672198299" style="color:#FFFFFF;text-decoration:none;">0176 72198299</a> · '
        . '<a href="mailto:kontakt@hisobjekt.de" style="color:#FFFFFF;text-decoration:none;">kontakt@hisobjekt.de</a> · '
        . '<a href="https://hisobjekt.de" style="color:#FFFFFF;text-decoration:none;">hisobjekt.de</a></p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Überschriftenblock mit kleiner Zeile darüber. */
function mail_kopfblock(string $zeile, string $titel, string $text): string
{
    $s = MAIL_SCHRIFT;
    return '<tr><td style="padding:36px 36px 8px;font-family:' . $s . ';color:#1A1A1A;">'
        . '<p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#6B6B6B;">' . e($zeile) . '</p>'
        . '<h1 style="margin:0 0 14px;font-size:26px;line-height:1.2;font-weight:800;color:#0A0A0A;">' . e($titel) . '</h1>'
        . '<p style="margin:0;font-size:15px;line-height:1.65;color:#575757;">' . $text . '</p>'
        . '</td></tr>';
}

/** Leistungen als „Chips“ im Stil der Website. */
function mail_chips(array $leistungen): string
{
    $html = '';
    foreach ($leistungen as $l) {
        $html .= '<span style="display:inline-block;margin:0 6px 8px 0;padding:6px 12px;border:1.5px solid #0A0A0A;'
            . 'font-family:' . MAIL_SCHRIFT . ';font-size:13px;font-weight:700;color:#0A0A0A;">' . e($l) . '</span>';
    }
    return $html;
}

/** Schwarzer Button (bei Hover in Mailprogrammen nicht invertierbar, daher schlicht). */
function mail_button(string $href, string $text, bool $hell = false): string
{
    $bg = $hell ? '#FFFFFF' : '#0A0A0A';
    $fg = $hell ? '#0A0A0A' : '#FFFFFF';
    return '<a href="' . e($href) . '" style="display:inline-block;margin:0 8px 8px 0;padding:13px 22px;background:' . $bg . ';'
        . 'border:2px solid #0A0A0A;color:' . $fg . ';font-family:' . MAIL_SCHRIFT . ';font-size:14px;font-weight:700;text-decoration:none;">'
        . e($text) . '</a>';
}

/**
 * Benachrichtigung an HIS Objektmanagement über eine neue Anfrage.
 * @return array{0:string,1:string,2:string} [Betreff, Text, HTML]
 */
function mail_benachrichtigung(array $d): array
{
    $s = MAIL_SCHRIFT;
    $istAnfrage = $d['typ'] === 'anfrage';
    $betreff = ($istAnfrage ? 'Anfrage' : 'Kontaktanfrage') . ' von ' . $d['name']
        . ($d['leistungen'] !== [] ? ' – ' . implode(', ', $d['leistungen']) : '');

    $felder = [
        ['Name', e($d['name'])],
        ['E-Mail', '<a href="mailto:' . e($d['email']) . '" style="color:#0A0A0A;">' . e($d['email']) . '</a>'],
        ['Telefon', $d['telefon'] !== '' ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $d['telefon'])) . '" style="color:#0A0A0A;">' . e($d['telefon']) . '</a>' : '–'],
    ];
    if ($istAnfrage) {
        $felder[] = ['Objektort / PLZ', e($d['ort'])];
        $felder[] = ['Zeitraum', $d['zeitraum'] !== '' ? e($d['zeitraum']) : '–'];
    }
    $zeilen = '';
    foreach ($felder as [$k, $v]) {
        $zeilen .= '<tr><td style="padding:12px 0;border-bottom:1px solid #E5E5E5;width:150px;vertical-align:top;font-family:' . $s . ';'
            . 'font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#6B6B6B;">' . e($k) . '</td>'
            . '<td style="padding:12px 0;border-bottom:1px solid #E5E5E5;font-family:' . $s . ';font-size:15px;line-height:1.5;color:#1A1A1A;">' . $v . '</td></tr>';
    }

    $inhalt = mail_kopfblock(
        $istAnfrage ? 'Neue Anfrage über die Website' : 'Neue Kontaktanfrage über die Website',
        $d['name'] . ' hat Ihnen geschrieben.',
        'Eingegangen am ' . e($d['zeit']) . '. Mit „Antworten“ in Ihrem Mailprogramm schreiben Sie direkt an die angegebene Adresse.'
    );
    if ($d['leistungen'] !== []) {
        $inhalt .= '<tr><td style="padding:24px 36px 0;">'
            . '<p style="margin:0 0 12px;font-family:' . $s . ';font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#6B6B6B;">Gewünschte Leistungen</p>'
            . mail_chips($d['leistungen']) . '</td></tr>';
    }
    $inhalt .= '<tr><td style="padding:16px 36px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $zeilen . '</table></td></tr>';
    $inhalt .= '<tr><td style="padding:24px 36px 0;">'
        . '<p style="margin:0 0 10px;font-family:' . $s . ';font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#6B6B6B;">Nachricht</p>'
        . '<div style="padding:18px 20px;background:#F5F5F5;border-left:3px solid #0A0A0A;font-family:' . $s . ';font-size:15px;line-height:1.65;color:#1A1A1A;">'
        . ($d['nachricht'] !== '' ? nl2br(e($d['nachricht'])) : '<span style="color:#6B6B6B;">(keine Nachricht)</span>')
        . '</div></td></tr>';
    $knoepfe = mail_button('mailto:' . $d['email'] . '?subject=' . rawurlencode('Ihre Anfrage bei HIS Objektmanagement'), 'Antworten');
    if ($d['telefon'] !== '') {
        $knoepfe .= mail_button('tel:' . preg_replace('/[^0-9+]/', '', $d['telefon']), 'Anrufen', true);
    }
    $inhalt .= '<tr><td style="padding:28px 36px 36px;">' . $knoepfe . '</td></tr>';

    $text = [
        $istAnfrage ? 'Neue Anfrage über die Website' : 'Neue Kontaktanfrage über die Website',
        str_repeat('-', 44),
        'Eingegangen: ' . $d['zeit'],
        'Name:        ' . $d['name'],
        'E-Mail:      ' . $d['email'],
        'Telefon:     ' . ($d['telefon'] !== '' ? $d['telefon'] : '–'),
    ];
    if ($istAnfrage) {
        $text[] = 'Ort / PLZ:   ' . $d['ort'];
        $text[] = 'Leistungen:  ' . implode(', ', $d['leistungen']);
        $text[] = 'Zeitraum:    ' . ($d['zeitraum'] !== '' ? $d['zeitraum'] : '–');
    }
    $text[] = '';
    $text[] = 'Nachricht:';
    $text[] = $d['nachricht'] !== '' ? $d['nachricht'] : '–';
    $text[] = '';
    $text[] = 'Datenschutzhinweis bestätigt: ja';

    return [$betreff, implode("\n", $text), mail_rahmen('Neue Anfrage von ' . $d['name'], $inhalt)];
}

/**
 * Eingangsbestätigung an die Person, die das Formular abgeschickt hat.
 * Enthält bewusst KEINE frei eingegebenen Inhalte (Name, Nachricht, Ort): Wer eine fremde Adresse
 * einträgt, kann so keine eigenen Texte über unseren Server an Dritte schicken. Nur die Leistungen
 * (feste Liste) werden genannt.
 * @return array{0:string,1:string,2:string} [Betreff, Text, HTML]
 */
function mail_bestaetigung(array $d): array
{
    $s = MAIL_SCHRIFT;
    $betreff = 'Ihre Anfrage bei HIS Objektmanagement – Eingangsbestätigung';

    $inhalt = mail_kopfblock(
        'Eingangsbestätigung',
        'Vielen Dank für Ihre Anfrage.',
        'Guten Tag,<br><br>wir haben Ihre Nachricht erhalten und melden uns so bald wie möglich bei Ihnen. '
        . 'Sie müssen nichts weiter tun.'
    );
    if ($d['leistungen'] !== []) {
        $inhalt .= '<tr><td style="padding:24px 36px 0;">'
            . '<p style="margin:0 0 12px;font-family:' . $s . ';font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#6B6B6B;">Ihre angefragten Leistungen</p>'
            . mail_chips($d['leistungen']) . '</td></tr>';
    }
    // So geht es weiter – drei Schritte mit Nummern im Stil der Website
    $schritte = [
        ['Rückmeldung', 'Wir melden uns bei Ihnen und klären offene Fragen.'],
        ['Besichtigung oder Beratung', 'Nach Absprache sehen wir uns Ihr Objekt an oder beraten Sie im Gespräch.'],
        ['Individuelles Angebot', 'Sie erhalten ein Angebot, das sich nach Ihrem Objekt und Ihren Wünschen richtet.'],
    ];
    $liste = '';
    foreach ($schritte as $i => [$titel, $txt]) {
        $liste .= '<tr><td style="width:44px;padding:0 0 18px;vertical-align:top;">'
            . '<div style="width:32px;height:32px;line-height:32px;text-align:center;background:#0A0A0A;color:#FFFFFF;font-family:' . $s . ';font-size:14px;font-weight:800;">' . ($i + 1) . '</div></td>'
            . '<td style="padding:4px 0 18px;vertical-align:top;font-family:' . $s . ';">'
            . '<p style="margin:0 0 2px;font-size:15px;font-weight:700;color:#0A0A0A;">' . e($titel) . '</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.55;color:#575757;">' . e($txt) . '</p></td></tr>';
    }
    $inhalt .= '<tr><td style="padding:28px 36px 0;">'
        . '<p style="margin:0 0 16px;font-family:' . $s . ';font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#6B6B6B;">So geht es weiter</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $liste . '</table></td></tr>';
    // Kontaktkasten
    $inhalt .= '<tr><td style="padding:12px 36px 0;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1.5px solid #0A0A0A;"><tr><td style="padding:22px 24px;font-family:' . $s . ';">'
        . '<p style="margin:0 0 6px;font-size:16px;font-weight:800;color:#0A0A0A;">Sie möchten uns direkt erreichen?</p>'
        . '<p style="margin:0 0 16px;font-size:14px;line-height:1.55;color:#575757;">Rufen Sie an oder schreiben Sie uns per WhatsApp – Ihr Ansprechpartner ist Ertan Hislisoy.</p>'
        . mail_button('tel:+4917672198299', '0176 72198299') . mail_button('https://wa.me/4917672198299', 'WhatsApp', true)
        . '</td></tr></table></td></tr>';
    // Hinweis
    $inhalt .= '<tr><td style="padding:24px 36px 34px;font-family:' . $s . ';font-size:12px;line-height:1.6;color:#6B6B6B;">'
        . 'Diese E-Mail wurde automatisch versendet, weil über hisobjekt.de eine Anfrage mit dieser E-Mail-Adresse gestellt wurde. '
        . 'Falls Sie das nicht waren, können Sie diese Nachricht ignorieren. '
        . 'Informationen zum Datenschutz: <a href="https://hisobjekt.de/datenschutz.html" style="color:#0A0A0A;">hisobjekt.de/datenschutz.html</a>'
        . '</td></tr>';

    $text = implode("\n", array_merge([
        'Vielen Dank für Ihre Anfrage.',
        '',
        'Guten Tag,',
        '',
        'wir haben Ihre Nachricht erhalten und melden uns so bald wie möglich bei Ihnen.',
        'Sie müssen nichts weiter tun.',
        '',
    ], $d['leistungen'] !== [] ? ['Ihre angefragten Leistungen: ' . implode(', ', $d['leistungen']), ''] : [], [
        'So geht es weiter:',
        '1. Rückmeldung – wir melden uns bei Ihnen und klären offene Fragen.',
        '2. Besichtigung oder Beratung – nach Absprache.',
        '3. Individuelles Angebot – passend zu Ihrem Objekt.',
        '',
        'Direkt erreichen: 0176 72198299 (auch WhatsApp) · kontakt@hisobjekt.de',
        '',
        'HIS OBJEKTMANAGEMENT – Alles aus einer Hand.',
        'Wallensteinstraße 1A · 70437 Stuttgart · hisobjekt.de',
        '',
        'Diese E-Mail wurde automatisch versendet, weil über hisobjekt.de eine Anfrage mit dieser',
        'E-Mail-Adresse gestellt wurde. Falls Sie das nicht waren, können Sie sie ignorieren.',
    ]));

    return [$betreff, $text, mail_rahmen('Wir haben Ihre Anfrage erhalten.', $inhalt)];
}

/**
 * MIME-Aufbau: multipart/alternative (Text + HTML), HTML mit eingebettetem Logo (multipart/related).
 * @return array{0:string,1:string} [Content-Type-Kopfzeile, Inhalt]
 */
function mail_mime(string $text, string $html, string $logoDatei): array
{
    $alt = 'his-alt-' . bin2hex(random_bytes(8));
    $rel = 'his-rel-' . bin2hex(random_bytes(8));
    $qp = function (string $t): string {
        return quoted_printable_encode(str_replace(["\r\n", "\n"], ["\n", "\r\n"], $t));
    };
    $teile = [
        '--' . $alt,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        '',
        $qp($text),
        '--' . $alt,
        'Content-Type: multipart/related; boundary="' . $rel . '"',
        '',
        '--' . $rel,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        '',
        $qp($html),
    ];
    $logo = @file_get_contents($logoDatei);
    if ($logo !== false) {
        array_push($teile,
            '--' . $rel,
            'Content-Type: image/png; name="his-logo.png"',
            'Content-Transfer-Encoding: base64',
            'Content-ID: <' . MAIL_LOGO_CID . '>',
            'Content-Disposition: inline; filename="his-logo.png"',
            '',
            rtrim(chunk_split(base64_encode($logo), 76, "\r\n"))
        );
    }
    array_push($teile, '--' . $rel . '--', '--' . $alt . '--', '');
    return ['Content-Type: multipart/alternative; boundary="' . $alt . '"', implode("\r\n", $teile)];
}
