# DataShield Blog

Blog educațional în limba română despre securitate digitală și protecția datelor personale. Aplicația folosește Laravel 12, Blade, Bootstrap 5.3, Bootstrap Icons, Vite și MySQL.

## Cerințe

- PHP 8.2 sau mai nou, cu extensiile Laravel necesare
- Composer
- Node.js 20 sau 22 și npm
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
```

Testele folosesc SQLite în memorie și nu modifică baza de date de dezvoltare.

## Notă

Conținutul publicat are scop educativ și informativ și nu reprezintă consultanță juridică sau de securitate informatică personalizată. Textele despre confidențialitate și cookie-uri sunt puncte de pornire, nu documente juridice definitive.

## Verificarea datelor personale

Pagina `/verificare-date-personale` verifică breșe prin Have I Been Pwned și mențiuni publice prin Brave Search, cu confirmarea adresei, rapoarte private criptate și joburi în coadă. Fără chei API/abonamente și transport de e-mail, afișează indisponibilitate, fără rezultate fictive.

Configurarea furnizorilor, accesul admin, workerul `database/exposure`, Scheduler, retenția și limitele sunt descrise în [documentația modulului](docs/verificarea-datelor-personale.md).

## Controlul paginilor și modulelor

În admin, secțiunea **Pagini și module** (`/admin/pagini-si-module`, disponibilă și prin `/admin`) permite activarea paginilor, a modulelor și a submodulelor, separat de afișarea lor în meniu. Folosește aceiași administratori autorizați ca verificarea datelor (`php artisan exposure:admin ID` pentru un utilizator existent), fără conturi implicite. Rulează `php artisan migrate` pentru tabela de setări.

Dezactivarea blochează accesul direct și endpointurile asociate, ascunde intrările de navigare și exclude paginile din sitemap. Ascunderea din meniu păstrează accesul direct și includerea în sitemap. Ghidurile depind de modulul Articole; cele două verificări depind de modulul Verificarea datelor personale. Opțiunile submodulelor sunt păstrate când părintele este dezactivat. Adminul rămâne accesibil inclusiv când pagina principală este oprită. Setările verificării datelor sunt partajate cu secțiunea sa existentă, păstrând limitele și retenția.

Pagini noi pot fi înregistrate în `config/site_pages.php`, apoi asociate rutelor în middleware-ul `SiteAvailability`; intrările nu permit executarea de rute sau cod introduse prin formularul admin. Disponibilitatea se aplică imediat; răspunsurile publice controlate nu folosesc cache public. Politicile CDN/proxy trebuie să respecte `Cache-Control: private, no-store`. Activarea unei pagini nu configurează furnizorii externi ai modulului.
