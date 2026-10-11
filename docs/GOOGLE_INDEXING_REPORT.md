# Raport inițial: Google Indexing

**Domeniu verificat:** `https://securitatedigitala.ro`  
**Data verificării live:** 11 octombrie 2026 (Europe/Bucharest)  
**Metodă:** cereri HTTP reale către producție cu un User-Agent Googlebot la paginile HTML; XML-ul sitemap-ului a fost parsată local.

## Rezultate inițiale din producție

| Verificare | Rezultat |
|---|---|
| Homepage | HTTP 200 |
| Listă articole | HTTP 200 |
| Articole publicate descoperite în sitemap | 30 |
| Articole sitemap verificate | 30/30 HTTP 200; 30/30 canonical concordant cu URL-ul; 0 cu `noindex` |
| Sitemap și URL-urile sale | HTTP 200, `application/xml`, XML valid, 36 URL-uri distincte; toate 36 au HTTP 200 fără redirect |
| URL-uri de secțiune dedicate în sitemap | 0; versiunile inițiale foloseau query filters cu canonical către `/articole` |
| Filtru de secțiune prin query | HTTP 200, dar canonical-ul era `/articole` și nu avea directivă robots explicită; codul schimbat marchează filtrele și paginile paginated `noindex,follow` |
| `robots.txt` | **HTTP 404** (nginx); fixul din cod pune un fișier static `public/robots.txt` |
| HTTP către domeniul canonic | Redirecționează cu un hop la HTTPS (`http://…` → `https://securitatedigitala.ro/`) |
| HTTPS `www` | **HTTP 200 fără redirect** la domeniul canonic; codul Laravel adaugă un redirect pentru rutele aplicației. Fișierele servite direct de nginx pot cere și o regulă la nivel de virtual host. |
| Admin ca vizitator | `/admin/sectiuni` redirecționează la `/admin/login`; pagina de login returnează HTTP 200 și `noindex,nofollow` |
| Conexiune Search Console | Neconectată în mediul de audit; nu au fost furnizate token-uri OAuth |
| Trimitere sitemap la Google | Nefăcută; necesită conectarea autorizată a unui cont cu acces la proprietate |
| Stare de indexare Google | **Necunoscută**; nu s-a interogat o proprietate Google autorizată |

Sitemap-ul inițial conținea homepage, pagina de articole, patru pagini publice auxiliare și 30 de articole. Au fost verificate toate cele 30 de pagini de articol cu un User-Agent Googlebot: răspuns 200, canonical concordant și fără `noindex`; acest test de User-Agent nu verifică rețeaua Googlebot autentică sau starea din index. Codul adaugă URL-uri canonice dedicate pentru cele 10 secțiuni care au articole publicate și restrânge sitemap-ul la homepage, secțiuni și articole eligibile. După deploy, inventarul așteptat este 41 URL-uri dacă toate cele 10 secțiuni au cel puțin un articol publicat.

## Eligibilitate locală și starea Google

Producția inițială avea 30 articole publicate confirmate prin sitemap și răspunsurile lor HTTP. Cele 10 pagini canonice noi pentru secțiuni nu erau încă deployate la momentul acestui audit. Auditul complet cu tabel per URL se poate genera, după deploy și migrare, din **Admin → Google Indexing → Rulează audit** sau cu `php artisan seo:check-indexability`. El măsoară starea tehnică live la momentul rulării.

Nici prezența în sitemap, nici răspunsul HTTP 200 nu sunt tratate drept dovadă că Google a indexat o pagină. Pentru confirmare, conectează Search Console și folosește URL Inspection individual; fără autorizație, statusul Google trebuie să rămână necunoscut.

## Acțiuni rămase pentru operator

1. După deploy, confirmă `robots.txt` cu HTTP 200 și URL-ul de sitemap corect.
2. În DNS-ul autoritativ, adaugă TXT-ul real furnizat în Google Search Console pentru proprietatea Domain `securitatedigitala.ro`, apoi verifică proprietatea.
3. Activează Search Console API și configurează client OAuth și redirect URI urmând [GOOGLE_INDEXING_SETUP.md](GOOGLE_INDEXING_SETUP.md).
4. Rulează auditul și revizuiește URL-urile cu probleme locale.
5. Conectează contul autorizat, verifică proprietatea, validează sitemap-ul și trimite-l o singură dată după ce conținutul lui este stabil.
6. Verifică statusuri Google doar prin Search Console sau răspunsuri autentificate ale URL Inspection API. Cererea manuală de indexare rămâne în interfața Google.
7. Cere administratorului infrastructurii să adauge redirect `www` la nivel nginx/DNS pentru fișierele statice dacă vrea redirect complet pentru toate căile.

**Nu s-a modificat DNS, nu s-a conectat un cont Google, nu s-a trimis sitemap-ul și nu s-a făcut deploy.**
