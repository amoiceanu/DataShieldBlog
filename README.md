# Securitate Digitală

Blog educațional în limba română despre securitate online și protecția datelor personale.

**Protejează-ți viața online.**

Site oficial: [securitatedigitala.ro](https://securitatedigitala.ro)

Aplicația folosește Laravel 12, Blade, Bootstrap 5.3, Bootstrap Icons, SCSS compilat prin Vite și MySQL.

## Cerințe

- PHP 8.2 sau mai nou, cu extensiile Laravel necesare
- Composer
- Node.js 22 și npm
- MySQL

## Instalare locală

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configurează conexiunea MySQL în `.env` și creează baza de date indicată prin `DB_DATABASE`, apoi rulează:

```sh
php artisan migrate --seed
npm run build
php artisan serve
```

Pe Windows, copiază `.env.example` în `.env` din PowerShell cu `Copy-Item .env.example .env`.

## Administrare

După migrare, creează un cont administrator din terminal. Comanda cere numele, e-mailul și o parolă de cel puțin 12 caractere:

```sh
php artisan admin:create
```

Autentificarea este la `/admin/login`. Din panoul `/admin/articole` poți crea, edita și șterge articole, le poți păstra ca draft sau publica și poți programa data publicării. Secțiunile se gestionează din `/admin/sectiuni`. Doar articolele publicate cu data ajunsă apar pe site și în sitemap.

## Catalog editorial

Catalogul editorial definește cele 10 secțiuni și adaugă cele 27 de articole noi ca publicate, cu metadate SEO, legături interne și surse. Pentru o bază de date existentă, după migrare rulează `php artisan db:seed --class=ContentCatalogSeeder`. Comanda este idempotentă: nu dublează articole și păstrează conținutul existent; articolele catalogului sunt publicate la prima importare, iar drafturile catalogului existente sunt publicate la rerulare. `php artisan migrate --seed` rulează și catalogul după cele trei articole introductive. Workflow-ul de deploy rulează migrarea și seederul catalogului după ce toate verificările CI trec.

## Google Search Console și indexare

Sitemap-ul dinamic se generează din articolele publicate și secțiunile care au conținut public. Dashboard-ul este disponibil administratorilor la `/admin/google-indexing`. Configurarea DNS/OAuth, limitele API și pașii de activare sunt documentate în [docs/GOOGLE_INDEXING_SETUP.md](docs/GOOGLE_INDEXING_SETUP.md); raportul inițial de producție este în [docs/GOOGLE_INDEXING_REPORT.md](docs/GOOGLE_INDEXING_REPORT.md). Auditul local se rulează cu `php artisan seo:check-indexability`; conectarea și trimiterea sitemap-ului necesită credențiale OAuth reale furnizate separat.

## Funcționalități

- Pagina principală, index cu căutare, filtrare și paginare și pagini individuale de articol
- Categorii și trei articole introductive încărcate prin seeder
- Panou administrativ protejat pentru articole și secțiuni, cu drafturi și publicare programată
- Formular de contact cu validare, acord de confidențialitate, limitare de cereri și honeypot
- Pagini despre proiect, confidențialitate și cookie-uri
- Sitemap XML, robots.txt, metadate Open Graph, canonical URLs și schema JSON-LD pentru articole
- Interfață responsive, navigare mobilă și căutare overlay

## Teste

```sh
php artisan test
composer test:gherkin
```

Testele PHPUnit și scenariile Gherkin folosesc SQLite în memorie și nu modifică baza de date de dezvoltare. Scenariile executabile sunt în `features/blog.feature` și folosesc Behat.

## CI

Workflow-ul GitHub Actions din `.github/workflows/ci.yml` rulează la fiecare pull request și push pe `main`: audit Composer și npm, compilarea asset-urilor, PHPUnit cu prag minim de acoperire de 70% și scenariile Gherkin.

Pentru ca verificările să fie obligatorii la integrarea pull request-urilor, configurează branch protection/ruleset în GitHub pentru `main` și cere verificările `Composer security audit`, `npm security audit`, `Compile frontend assets` și `PHPUnit, coverage, and Gherkin`.

Deploy-ul pornește doar la push pe `main`, după toate cele patru joburi CI. Configurează în **Settings → Secrets and variables → Actions** secretele `DEPLOY_USER` (utilizatorul SSH), `DEPLOY_PATH` (calea absolută a clonei Laravel de pe droplet), `DEPLOY_SSH_KEY` (cheia privată cu cheia publică instalată în `authorized_keys`) și `DEPLOY_KNOWN_HOSTS` (cheia hostului verificată pentru `165.22.88.77`). Runnerul trimite build-ul frontend verificat; dropletul trebuie să aibă PHP, Composer și acces SSH, dar nu are nevoie de Node.js. `.env` și permisiunile directorului `storage` trebuie configurate deja pe server.

## Notă

Conținutul publicat are scop educativ și informativ și nu reprezintă consultanță juridică sau de securitate informatică personalizată. Textele despre confidențialitate și cookie-uri sunt puncte de pornire, nu documente juridice definitive.
