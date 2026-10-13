# Google Search Console pentru SecuritateDigitala.ro

## Proprietate și verificare DNS

1. Deschide [Google Search Console](https://search.google.com/search-console/) cu contul Google care va administra site-ul și alege **Adaugă proprietate → Domeniu**.
2. Introdu exact `securitatedigitala.ro`. Google va afișa valoarea TXT reală pentru verificare. Copiaz-o din Search Console; nu există un token predefinit în aplicație.
3. În furnizorul DNS autoritativ al domeniului, creează o înregistrare TXT la numele rădăcină (`@`, sau câmpul echivalent), cu valoarea exactă afișată de Google. Nu adăuga ghilimele dacă interfața DNS nu le cere.
4. Așteaptă propagarea DNS, apoi apasă **Verifică** în Search Console. Păstrează TXT-ul cât timp îl folosești pentru verificare.

Aplicația nu citește și nu modifică DNS-ul. Dreptul de proprietar Search Console este separat de verificarea din DNS.

## OAuth și Search Console API

1. În [Google Cloud Console](https://console.cloud.google.com/), creează sau selectează un proiect și activează **Google Search Console API**.
2. Configurează ecranul OAuth consent cu utilizatorii care au voie să administreze conexiunea. Dacă aplicația OAuth este în modul Testing, adaugă contul de administrare drept test user.
3. Creează un OAuth client de tip **Web application**. Adaugă ca Authorized redirect URI exact:

   `https://securitatedigitala.ro/admin/google-indexing/callback`

4. Configurează în mediul aplicației de pe droplet valorile reale primite de la Google:

   ```dotenv
   GOOGLE_SEARCH_CONSOLE_CLIENT_ID=valoarea-reala-client-id
   GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET=valoarea-reala-client-secret
   GOOGLE_SEARCH_CONSOLE_REDIRECT_URI=https://securitatedigitala.ro/admin/google-indexing/callback
   ```

   Nu pune aceste valori în repository sau în `.env.example`. După schimbarea mediului, rulează `php artisan config:cache` dacă aplicația folosește cache de configurare.
5. Autentifică-te la `https://securitatedigitala.ro/admin/google-indexing` ca administrator și alege **Conectează Search Console**. Autorizează contul care are acces la proprietatea de domeniu `sc-domain:securitatedigitala.ro`.

Aplicația cere accesul OAuth `https://www.googleapis.com/auth/webmasters`, necesar pentru citirea rapoartelor și trimiterea sitemap-ului. Token-urile de acces și refresh sunt stocate criptat cu `APP_KEY`; păstrează cheia stabilă și protejată. Dacă o rotești, token-urile existente nu mai pot fi decriptate și conexiunea trebuie refăcută.

Dacă proprietatea nu apare în dashboard după OAuth, verifică în Search Console că acel cont are acces la proprietatea **Domain** și că API-ul este activat. Clientul OAuth trebuie să fie în stare validă, iar URI-ul callback să coincidă caracter cu caracter.

Google poate cere configurarea și verificarea ecranului OAuth consent înainte ca alți utilizatori decât test user-ii să autorizeze un scope de acces la Search Console.

## Sitemap și URL Inspection

Sitemap-ul este generat din baza de date la fiecare cerere și conține pagina principală, secțiunile cu articole publicate și articolele publicate cu dată efectivă: `https://securitatedigitala.ro/sitemap.xml`. Drafturile, URL-urile admin și URL-urile filtrate nu sunt incluse. `robots.txt` îl anunță pentru crawler-e.

În dashboard:

- **Validează sitemap** verifică răspunsul HTTP, XML-ul, URL-urile duplicate și originea canonică.
- **Trimite sitemap** este disponibil numai după OAuth și confirmarea accesului API la proprietatea de domeniu. Aplicația nu retrimite automat același conținut după o trimitere confirmată.
- **Actualizează datele Google** citește starea sitemap-ului și sumarul Search Analytics pentru ultimele 28 de zile calendaristice complete; Google poate întârzia datele.
- **Verifică în Google** cere URL Inspection doar pentru rândul selectat și afișează verdictul returnat de Google. Acțiunea respectă limitele API și nu rulează în masă.
- **Deschide URL Inspection** duce la instrumentul manual din Search Console. Dacă dorești, poți apăsa acolo **Solicită indexarea**. Aplicația nu pretinde că a făcut cererea.
- **Exportă URL-uri CSV** include doar URL-urile confirmate de ultimul audit local ca indexabile; înainte de primul audit, exportul cere rularea verificării.

Sitemap-ul este un indiciu pentru crawler și nu garantează descărcarea sau indexarea. URL Inspection API raportează versiunea cunoscută de Google, nu oferă o cerere generală de indexare pentru articole. Google Indexing API nu este folosit pentru paginile obișnuite de blog. Vezi documentația Google despre [trimiterea sitemap-urilor](https://developers.google.com/webmaster-tools/v1/sitemaps/submit), [URL Inspection](https://developers.google.com/webmaster-tools/v1/urlInspection.index/inspect) și [cotele API](https://developers.google.com/webmaster-tools/limits).

## Audit automat

Execută manual pe server sau într-un mediu cu acces la site:

```sh
php artisan seo:check-indexability
```

Comanda verifică URL-urile publice din baza de date față de răspunsul HTTP live, canonical, directivele robots, includerea și unicitatea din sitemap, precum și legăturile interne din homepage și paginile de secțiune. Ea nu trimite cereri către Google.

Aplicația înregistrează comanda o dată pe zi în schedulerul Laravel (`routes/console.php`). Pentru a o executa automat, hostul trebuie să ruleze schedulerul Laravel și un worker pentru coada configurată. Cron-ul/worker-ul sistemului de producție nu a fost modificat de această integrare.

Pentru audituri web asincrone, configurează în mediul de producție `QUEUE_CONNECTION=database` și rulează workerul prin managerul de procese al serverului, cu o comandă de forma `php artisan queue:work --queue=default --tries=1 --timeout=600`. Cu driverul `sync`, Laravel execută jobul în procesul cererii web. Instalează schedulerul Laravel conform [documentației Laravel](https://laravel.com/docs/12.x/scheduling) numai după configurarea operațională a serverului.

## Limitări

- Starea Google rămâne **Necunoscută** până când URL Inspection API întoarce și aplicația salvează un verdict real pentru acel URL.
- Lipsa impresiilor într-un raport Search Analytics nu dovedește că pagina nu este indexată.
- Un HTTP 200, un canonical valid sau prezența în sitemap demonstrează eligibilitatea tehnică locală, nu includerea în indexul Google.
- Inspectarea URL-urilor prin API consumă cota proiectului și a proprietății. Dashboard-ul oferă inspecții individuale la cerere și nu le rulează automat în masă.
