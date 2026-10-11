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

## Funcționalități

- Pagina principală, index cu căutare, filtrare și paginare și pagini individuale de articol
- Categorii și trei articole introductive încărcate prin seeder
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

## Notă

Conținutul publicat are scop educativ și informativ și nu reprezintă consultanță juridică sau de securitate informatică personalizată. Textele despre confidențialitate și cookie-uri sunt puncte de pornire, nu documente juridice definitive.
