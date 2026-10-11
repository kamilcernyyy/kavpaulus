# Kavárna Paulus — web (kavarnapaulus.cz)

Všechny soubory jsou v **jedné složce bez podsložek** — nic se při nahrávání nerozháže.

## Nasazení
**Wedos (ostrý web):** přes FTP nahraj *celý obsah* složky do `www/` — včetně skrytého `.htaccess`.
Až bude na kavarnapaulus.cz aktivní SSL, odkomentuj v `.htaccess` přesměrování na HTTPS.

**Vercel (jen testování):** nahraj stejný obsah do repa. Díky `.vercelignore` se tam PHP soubory
(editor, rezervace) nenahrají — formulář na Vercelu proto nabídne odeslání e-mailem. Vše ostatní funguje.

## Úprava textů — `kavarnapaulus.cz/admin.php`
- Heslo jsem předal v chatu; v souborech je uložen jen jeho otisk. **Po prvním přihlášení ho změň** (dole v editoru).
- Upravit jde: fotky, texty všech sekcí, kontakt, otevírací doba (dny + časy), menu, výhody, akce, časté dotazy,
  odkazy na Instagram/Facebook, SEO popisky a ID Google Analytics.
- Po uložení se web znovu vygeneruje jako statické HTML — zůstává stejně rychlý.
- Otevírací doba se změní najednou na webu, ve strukturovaných datech pro Google i v odpovědi v dotazech
  (do odpovědi stačí napsat `{otevreno}`).
- Před každým uložením se udělá záloha (`content-zaloha-*.json`, posledních 5). Interní soubory nejsou z webu dostupné.

## Rezervace
Formulář posílá e-mail na adresu kavárny z editoru (odpověď jde rovnou hostovi) a host hned dostane
automatické potvrzení „rezervaci jsme přijali“ se shrnutím — jeho úvodní text jde změnit v editoru.
Ochrana: past na roboty, minimální doba vyplnění, limit 5 rezervací/hod. z jedné adresy, kontrola všech polí.
**Po nasazení pošli zkušební rezervaci.** Kdyby nedorazila, na Wedosu založ schránku `rezervace@kavarnapaulus.cz`
(odesílatel musí být z vaší domény).

## Google Analytics
V editoru vlož měřicí ID (`G-…`). Teprve pak se objeví lišta cookies; bez souhlasu se nic neměří.
Odkaz „Nastavení cookies“ v patičce umožní souhlas změnit.

## Doplnit před spuštěním
- adresa a telefon (editor → Kontakt), odkazy na Instagram a Facebook
- `soukromi.html` → správce údajů (název/jméno, IČO, sídlo) — v souboru `soukromi.tpl.html`, řádek „Správce“
- fotky: v editoru sekce **Fotky** (galerie 3×, vchod, Instagram 6×). Stačí vybrat fotku z mobilu — editor ji
  zmenší, ořízne na správný poměr, opraví otočení a uloží v 4–6 velikostech (WebP, a AVIF pokud ho server umí).

## Logo
Na webu je zatím **dočasné logo** („Paulus“ + oblouk „KAVÁRNA“). Je v ikonách, náhledu pro sdílení i ve strukturovaných datech.
Až dorazí finální logo (ideálně SVG), vymění se na všech místech najednou.

## Animace
- Desktop: hlavička je kreslený pohled na Svatý Kopeček (podle dodané ilustrace), text je v kartě na trávníku.
  Z obrázku jsou vystřižené stromy, mraky a auto: stromy se kývají ve větru, mraky plují, auto občas odjede.
  Po cestičkách chodí lidé, ze střechy baziliky občas vzlétnou holubi, v nádvoří stříká kašna, oblohou přeletí ptáci.
  Obrázek se stahuje jen na počítači/tabletu na šířku (AVIF/WebP ve 3 velikostech).
  Úprava výřezů: tools/hero_pano.py (souřadnice v pixelech obrázku), pak build.py.
- Mobil: hlavička s rukama a šálky beze změny.
- Odznak „Nově otevřeno“ se točí na rohu průčelí kavárny v sekci Příběh.
- Po celé stránce se občas objeví drobnost: padající zrnka, listí, mráčky, ptáci, jiskřičky, balón nad mapou,
  papírová vlaštovka u rezervace, otazníky u dotazů. Vždy nejvýš dvě v sekci, jen když je sekce na obrazovce.
- Vlnitý text zrychlí podle scrollu a při scrollu nahoru jede pozpátku.
- Při „omezení pohybu“ v systému se nic z toho nespouští.

## Výsledky (Lighthouse, Chrome bez GPU = přísnější než běžný telefon)
Mobil i desktop: výkon 96–100 · přístupnost 100 · osvědčené postupy 100 · SEO 100.
Ověřeno na 9 velikostech obrazovky (telefon 360 px → monitor 2560 px), na výšku i na šířku, bez přetékání,
všechny ovládací prvky ≥ 44 px, funguje bez JavaScriptu i s omezením pohybu.

## Technika
- Fonty lokálně, ořezané na češtinu; metricky sladěné náhradní fonty (text při načtení neposkakuje).
- Hero: obrázek s průhledností (rychlé první vykreslení), video AV1/H.264 se spustí po načtení.
- Animace skoku do šálku: video AV1 674 kB (SSIM 0,9985 vůči originálu = okem nerozeznatelné; originál 1,95 MB).
- Bublinové pozadí se kreslí do malého plátna — stejný vzhled, zlomek výpočtu.
- Animace se připraví až u obrazovky a mimo ni se pozastaví; při „omezení pohybu“ se nespouští.
- Soubory s otiskem v názvu (`site.1a2b3c4d.js`) se cachují natrvalo; service worker zrychlí opakované návštěvy
  a umožní přidat web na plochu telefonu jako aplikaci.
