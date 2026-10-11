<?php
/**
 * Kavárna Paulus — odeslání rezervace e-mailem kavárně.
 * Ochrana: honeypot, minimální doba vyplnění, limit 5 rezervací / hodinu z jedné IP, validace všech polí.
 */
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
function done(bool $ok, string $error = ''): void {
    global $wantsJson;
    if ($wantsJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE); }
    else { header('Location: ./?rezervace=' . ($ok ? 'odeslano' : 'chyba') . '#rezervace', true, 303); }
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: ./#rezervace', true, 303); exit; }

$c = json_decode((string)@file_get_contents(__DIR__ . '/content.json'), true);
$to = $c['contact']['email'] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) done(false, 'Rezervace teď nejde odeslat. Zavolejte nám prosím.');

$f = fn(string $k, int $max) => mb_substr(trim(str_replace(["\r", "\0"], '', (string)($_POST[$k] ?? ''))), 0, $max);
$one = fn(string $s) => preg_replace('/[\n\t]+/', ' ', $s);   /* single-line fields: no header injection */

/* spam traps: silently "succeed" so bots learn nothing */
if ($f('web', 100) !== '') done(true);
$t = (int)($_POST['t'] ?? 0);
if ($t > 0 && (microtime(true) * 1000 - $t) < 3000) done(true);

$name = $one($f('jmeno', 80)); $email = $one($f('email', 120)); $phone = $one($f('telefon', 30));
$date = $f('datum', 10); $time = $f('cas', 5); $people = $one($f('osoby', 3)); $note = $f('poznamka', 600);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) done(false, 'Vyplňte prosím jméno a platný e-mail.');
/* the name is echoed in the guest's confirmation — no links, so the form can't be abused to mail spam to strangers */
if (preg_match('~https?:|www\.|://|@|\.(com|cz|net|ru|info)\b~i', $name)) done(false, 'Do jména prosím napište jen jméno.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) done(false, 'Zkontrolujte prosím datum.');
$tz = new DateTimeZone('Europe/Prague');
if (new DateTime($date, $tz) < new DateTime('today', $tz)) done(false, 'Datum rezervace nemůže být v minulosti.');
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) done(false, 'Zkontrolujte prosím čas.');
if (!preg_match('/^([1-8]|9\+)$/', $people)) done(false, 'Zkontrolujte prosím počet osob.');
if ($phone !== '' && !preg_match('/^[0-9+ ()\-]{6,30}$/', $phone)) done(false, 'Zkontrolujte prosím telefon.');

/* rate limit per IP */
$key = sys_get_temp_dir() . '/paulus_rez_' . hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . __DIR__);
$hits = array_filter(json_decode((string)@file_get_contents($key), true) ?: [], fn($ts) => $ts > time() - 3600);
if (count($hits) >= 5) done(false, 'Odeslali jste už několik rezervací. Zavolejte nám prosím.');
$hits[] = time(); @file_put_contents($key, json_encode(array_values($hits)));

$d = DateTime::createFromFormat('Y-m-d', $date, $tz);
$nice = $d ? $d->format('j. n. Y') : $date;
$host = parse_url($c['site']['url'] ?? '', PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
$host = preg_replace('/^www\./', '', $host);

$body = "Nová rezervace z webu\n\n"
      . "Jméno:    $name\nE-mail:   $email\nTelefon:  " . ($phone ?: '—') . "\n"
      . "Datum:    $nice\nČas:      $time\nOsob:     $people\n\nPoznámka:\n" . ($note ?: '—') . "\n\n"
      . "Odpovězte na tento e-mail a rezervaci tím potvrďte hostovi.";
$subject = '=?UTF-8?B?' . base64_encode("Rezervace: $nice $time · $people os. · $name") . '?=';
$fromName = '=?UTF-8?B?' . base64_encode(($c['site']['name'] ?? 'Web') . ' — rezervace') . '?=';
$headers = implode("\r\n", [
    'From: ' . $fromName . ' <rezervace@' . $host . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);
$sent = @mail($to, $subject, $body, $headers, '-frezervace@' . $host);
if (!$sent) done(false, 'Odeslání se nepodařilo. Zavolejte nám prosím, nebo napište e-mail.');

/* automatic "we got it" e-mail to the guest (fixed text + the booking summary; replies go to the café) */
$site = $c['site']['name'] ?? 'Kavárna';
$intro = trim((string)($c['texts']['rez_mail_intro'] ?? 'děkujeme za rezervaci. Brzy vám ji potvrdíme.'));
$addr = trim(($c['contact']['street'] ?? '') . ', ' . ($c['contact']['zip'] ?? '') . ' ' . ($c['contact']['city'] ?? ''), ' ,');
$gBody = "Dobrý den, $name,\n\n$intro\n\n"
       . "Datum:  $nice\nČas:    $time\nOsob:   $people\n\n"
       . "Potřebujete rezervaci změnit? Stačí odpovědět na tento e-mail" . (!empty($c['contact']['phone']) ? ' nebo zavolat na ' . $c['contact']['phone'] : '') . ".\n\n"
       . "Těšíme se na vás\n$site\n$addr\n" . ($c['site']['url'] ?? '');
$gHeaders = implode("\r\n", [
    'From: =?UTF-8?B?' . base64_encode($site) . '?= <rezervace@' . $host . '>',
    'Reply-To: ' . $to,
    'Auto-Submitted: auto-replied',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);
@mail($email, '=?UTF-8?B?' . base64_encode("Rezervace přijata — $site, $nice $time") . '?=', $gBody, $gHeaders, '-frezervace@' . $host);
done(true);
