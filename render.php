<?php
/**
 * Kavárna Paulus — renderer.
 * Builds index.html from index.tpl.html + content.json.
 * Used by the build (CLI: php render.php) and by admin.php after saving.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('PAULUS_ADMIN')) { http_response_code(404); exit; }

/* photo slots: aspect ratio, output widths, sizes attribute, label (editor) */
const PAULUS_PHOTOS = [
    'g1' => ['r' => [16, 9], 'w' => [640, 960, 1280, 1600, 2000, 2400], 'sizes' => '(max-width: 1240px) 92vw, 1160px', 'label' => 'Galerie — fotka 1 (16:9)'],
    'g2' => ['r' => [16, 9], 'w' => [640, 960, 1280, 1600, 2000, 2400], 'sizes' => '(max-width: 1240px) 92vw, 1160px', 'label' => 'Galerie — fotka 2 (16:9)'],
    'g3' => ['r' => [16, 9], 'w' => [640, 960, 1280, 1600, 2000, 2400], 'sizes' => '(max-width: 1240px) 92vw, 1160px', 'label' => 'Galerie — fotka 3 (16:9)'],
    'visit' => ['r' => [4, 3], 'w' => [480, 800, 1200, 1600], 'sizes' => '(max-width: 980px) 92vw, 600px', 'label' => 'Vchod do kavárny (4:3)'],
    'ig1' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 1100px) 31vw, 190px', 'label' => 'Instagram 1 (čtverec)'],
    'ig2' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 1100px) 31vw, 190px', 'label' => 'Instagram 2 (čtverec)'],
    'ig3' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 1100px) 31vw, 190px', 'label' => 'Instagram 3 (čtverec)'],
    'ig4' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 1100px) 31vw, 190px', 'label' => 'Instagram 4 (čtverec)'],
    'ig5' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 1100px) 31vw, 190px', 'label' => 'Instagram 5 (čtverec)'],
    'ig6' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 1100px) 31vw, 190px', 'label' => 'Instagram 6 (čtverec)'],
];

/** responsive <picture> for a slot, or the placeholder while no photo is uploaded */
function paulus_photo(array $c, string $slot): string {
    $spec = PAULUS_PHOTOS[$slot] ?? null; $p = $c['photos'][$slot] ?? null;
    $ratio = $spec ? $spec['r'][0] . '/' . $spec['r'][1] : '1/1';
    $cls = ['16/9' => 'photo--169', '4/3' => 'photo--43', '1/1' => 'photo--11'][$ratio] ?? '';
    if (!$spec || !$p || empty($p['w'])) {
        if (str_starts_with($slot, 'ig')) return '<svg aria-hidden="true"><use href="#cam"/></svg>';
        $label = $slot === 'visit' ? 'Místo pro fotografii vchodu do kavárny' : 'Místo pro fotografii ' . substr($slot, 1);
        return '<div class="photo ' . $cls . '" role="img" aria-label="' . paulus_e($label) . '">Foto — ' . str_replace('/', ':', $ratio) . '</div>';
    }
    $set = fn(string $ext) => implode(', ', array_map(fn($w) => 'foto-' . $slot . '-' . $w . '.' . $p['id'] . '.' . $ext . ' ' . $w . 'w', $p['w']));
    $big = max($p['w']); $h = (int)round($big * $spec['r'][1] / $spec['r'][0]);
    $mid = $p['w'][(int)floor((count($p['w']) - 1) / 2)];
    return '<picture class="pic ' . $cls . '">' . (!empty($p['avif']) ? '<source type="image/avif" srcset="' . $set('avif') . '" sizes="' . $spec['sizes'] . '">' : '')
         . '<img src="foto-' . $slot . '-' . $mid . '.' . $p['id'] . '.webp" srcset="' . $set('webp') . '" sizes="' . $spec['sizes'] . '" width="' . $big . '" height="' . $h
         . '" loading="lazy" decoding="async" alt="' . paulus_e($p['alt'] ?? '') . '"></picture>';
}

const PAULUS_DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function paulus_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
function paulus_json($v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}
function paulus_hm(string $t): string { return (string)(int)substr($t, 0, 2) . ':' . substr($t, 3, 2); }

function paulus_hours_sentence(array $c): string {
    $parts = [];
    foreach ($c['hours'] as $h) $parts[] = $h['label'] . ' ' . paulus_hm($h['opens']) . '–' . paulus_hm($h['closes']);
    return implode(', ', $parts);
}

function paulus_render(array $c, string $tpl, array $assets = []): string {
    $t = $c['texts'];
    $site = $c['site'];
    $url = rtrim($site['url'], '/') . '/';
    $tel = preg_replace('/[^0-9+]/', '', $c['contact']['phone']);
    $hoursSentence = paulus_hours_sentence($c);

    /* ---- computed blocks ---- */
    $hoursHtml = '';
    foreach ($c['hours'] as $h) {
        $d = implode(',', array_map('intval', $h['days']));
        $hoursHtml .= '<span data-days="' . $d . '">' . paulus_e($h['label']) . '</span>'
                    . '<span data-days="' . $d . '">' . paulus_hm($h['opens']) . '–' . paulus_hm($h['closes']) . '</span>';
    }
    $menuHtml = '';
    foreach ($c['menu'] as $m) {
        if (trim($m['name']) === '') continue;
        $menuHtml .= '<li><span class="menu__name">' . paulus_e($m['name']) . '</span><span class="menu__dots" aria-hidden="true"></span>'
                   . '<span class="menu__price">' . paulus_e($m['price']) . '</span></li>';
    }
    $amenHtml = '';
    foreach ($c['amenities'] as $a) if (trim($a) !== '') $amenHtml .= '<li>' . paulus_e($a) . '</li>';
    $faqHtml = '';
    $faqLd = [];
    foreach ($c['faq'] as $f) {
        if (trim($f['q']) === '') continue;
        $a = str_replace('{otevreno}', $hoursSentence, $f['a']);
        $faqHtml .= '<details><summary><h3>' . paulus_e($f['q']) . '</h3></summary><p>' . paulus_e($a) . '</p></details>';
        $faqLd[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
    }
    $eventsHtml = '';
    foreach ($c['events'] as $ev) {
        if (trim($ev['title'] ?? '') === '') continue;
        $eventsHtml .= '<li><span class="ev__date">' . paulus_e($ev['date'] ?? '') . '</span><span class="ev__title">' . paulus_e($ev['title']) . '</span></li>';
    }
    if ($eventsHtml !== '') $eventsHtml = '<ul class="events">' . $eventsHtml . '</ul>';

    $sameAs = array_values(array_filter([$site['instagram_url'], $site['facebook_url']], fn($u) => $u !== '' && !preg_match('#^https?://(www\.)?(instagram|facebook)\.com/?$#', $u)));
    $cafe = [
        '@context' => 'https://schema.org',
        '@type' => 'CafeOrCoffeeShop',
        'name' => $site['name'],
        'description' => $c['seo']['description'],
        'url' => $url,
        'image' => $url . ($assets['og-image.jpg'] ?? 'og-image.jpg'),
        'logo' => $url . ($assets['logo.svg'] ?? 'logo.svg'),
        'telephone' => $c['contact']['phone'],
        'email' => $c['contact']['email'],
        'priceRange' => '55–139 Kč',
        'currenciesAccepted' => 'CZK',
        'paymentAccepted' => 'Hotovost, platební karta',
        'servesCuisine' => ['Káva', 'Snídaně', 'Pečivo'],
        'acceptsReservations' => true,
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => $c['contact']['street'], 'addressLocality' => $c['contact']['city'],
                      'postalCode' => $c['contact']['zip'], 'addressCountry' => 'CZ'],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 49.6337, 'longitude' => 17.3357],
        'openingHoursSpecification' => array_map(fn($h) => ['@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => array_map(fn($d) => PAULUS_DAYS[(int)$d], $h['days']), 'opens' => $h['opens'], 'closes' => $h['closes']], $c['hours']),
        'amenityFeature' => array_values(array_map(fn($a) => ['@type' => 'LocationFeatureSpecification', 'name' => $a, 'value' => true],
            array_filter($c['amenities'], fn($a) => trim($a) !== ''))),
        'containedInPlace' => ['@type' => 'Place', 'name' => 'Svatý Kopeček, Olomouc'],
    ];
    if ($sameAs) $cafe['sameAs'] = $sameAs;
    $faqBlock = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqLd];

    $raw = [
        'url' => paulus_e($url),
        'tel' => paulus_e($tel),
        'year' => date('Y'),
        'maps_url' => paulus_e('https://mapy.cz/zakladni?q=' . rawurlencode($site['name'] . ' Svatý Kopeček')),
        'hours_html' => $hoursHtml,
        'menu_html' => $menuHtml,
        'amenities_html' => $amenHtml,
        'faq_html' => $faqHtml,
        'events_html' => $eventsHtml,
        'ld_cafe' => paulus_json($cafe),
        'ld_faq' => paulus_json($faqBlock),
        'mail_voucher' => paulus_e('mailto:' . $c['contact']['email'] . '?subject=' . rawurlencode('Dárkový poukaz')),
        'mail_coffee' => paulus_e('mailto:' . $c['contact']['email'] . '?subject=' . rawurlencode('Káva domů')),
    ];

    /* ---- conditional block: analytics only when an ID is set ---- */
    $hasGa = (bool)preg_match('/^G-[A-Z0-9]{4,}$/', $site['ga_id']);
    $tpl = preg_replace('/\{\{#ga\}\}(.*?)\{\{\/ga\}\}/s', $hasGa ? '$1' : '', $tpl);

    return preg_replace_callback('/\{\{(t|tw|tp|v|raw|wave|photo):([\w.]+)(?::(\d+))?\}\}/', function ($m) use ($c, $t, $raw) {
        switch ($m[1]) {
            case 't':    return str_replace("\n", '<br>', paulus_e(str_replace("\r", '', $t[$m[2]] ?? '')));
            case 'tw':   /* words wrapped for the desktop title entrance (CSS only); line breaks kept */
                $i = 0;
                return implode('<br>', array_map(function ($line) use (&$i) {
                    return implode(' ', array_map(function ($w) use (&$i) { return '<span class="w"><span class="wi" style="--i:' . ($i++) . '">' . paulus_e($w) . '</span></span>'; },
                        preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY)));
                }, explode("\n", str_replace("\r", '', $t[$m[2]] ?? ''))));
            case 'tp':   return paulus_e(preg_replace('/\s*\n\s*/', ' ', $t[$m[2]] ?? ''));
            case 'v':    $v = $c; foreach (explode('.', $m[2]) as $k) $v = $v[$k] ?? ''; return paulus_e((string)$v);
            case 'raw':  return $raw[$m[2]] ?? '';
            case 'photo': return paulus_photo($c, $m[2]);
            case 'wave': return paulus_e(str_repeat($t[$m[2]] ?? '', (int)($m[3] ?? 4)));
        }
        return '';
    }, $tpl);
}

/** Render to disk atomically. Returns null on success, or an error message. */
function paulus_render_to(string $dir): ?string {
    $c = json_decode((string)@file_get_contents($dir . '/content.json'), true);
    if (!is_array($c)) return 'content.json nelze načíst';
    $tpl = @file_get_contents($dir . '/index.tpl.html');
    if ($tpl === false) return 'index.tpl.html chybí';
    $assets = json_decode((string)@file_get_contents($dir . '/assets.json'), true) ?: [];
    foreach (['index' => $tpl, 'soukromi' => @file_get_contents($dir . '/soukromi.tpl.html')] as $name => $t) {
        if ($t === false) continue;
        $tmp = $dir . '/' . $name . '.html.tmp';
        if (@file_put_contents($tmp, paulus_render($c, $t, $assets)) === false || !@rename($tmp, $dir . '/' . $name . '.html')) return $name . '.html nelze zapsat';
    }
    return null;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $err = paulus_render_to($argv[1] ?? __DIR__);
    if ($err) { fwrite(STDERR, $err . "\n"); exit(1); }
    echo "index.html rendered\n";
}
