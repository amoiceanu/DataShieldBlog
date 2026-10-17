# Verificarea datelor personale

Implementare pentru checkout-ul existent DataShieldBlog (Laravel 12, Blade, Bootstrap 5.3, MySQL/MariaDB). Brandingul global DataShield este păstrat; pagina utilizează titlurile cerute pentru SecuritateDigitala.ro. Nu există conturi publice sau panou admin anterior. Rapoartele folosesc sesiuni temporare, iar secțiunea admin folosește autentificarea HTTP Basic Laravel cu utilizatori existenți și rol explicit `is_exposure_admin`.

## Configurare

În mediul cloud pregătit anterior, începe cu `source /workspace/.cloud-tools/activate.sh`, apoi `cd /workspace/DataShieldBlog`. În alte medii folosește PHP >= 8.2, Composer și Node 20/22 instalate conform README.

1. `composer install --no-interaction --prefer-source` (sau `--prefer-dist` când GitHub API este accesibil), `npm ci`, `php artisan migrate`, `npm run build`.
2. Păstrează cheia `APP_KEY` existentă: criptează datele rapoartelor și joburile. Configurează o cheie aleatoare separată de cel puțin 32 bytes pentru `EXPOSURE_HMAC_KEY` în secret manager / `.env` ignorat. Poți genera și salva local fără afișare folosind `php artisan exposure:key`. Nu roti cheia fără a șterge întâi rapoartele active: identificatorii și autorizările devin invalide. Nu roti APP_KEY fără strategia Laravel de chei precedente.
3. `EXPOSURE_ENABLED=true` activează pagina. Lipsa cheii HMAC sau a configurației furnizorilor produce indisponibilitate, fără răspunsuri false. Activarea/dezactivarea din admin are prioritate față de valoarea inițială din mediu.
4. Configurează `HIBP_API_KEY`, `HIBP_USER_AGENT` identificabil și `HIBP_LICENSE_CONFIRMED=true` numai după verificarea drepturilor abonamentului pentru o funcție publică de verificare a adreselor confirmate. API-ul breached-account necesită abonament/cheie; endpointul de parole nu este folosit. Sunt căutate breșele disponibile API-ului, nu toate expunerile posibile, breșele sensibile sau datele private inaccesibile abonamentului.
5. Configurează `BRAVE_SEARCH_API_KEY` și `BRAVE_STORAGE_RIGHTS_CONFIRMED=true` numai pentru un plan care permite stocarea rezultatelor în rapoarte. Confirmarea este declarată de operator, nu validată automat de aplicație.
6. Configurează un transport Laravel real pentru e-mail (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, expeditorul verificat etc., conform furnizorului). Transporturile `log` și `array` sunt refuzate în afara testelor pentru a nu scrie adrese și linkuri în loguri. Nu utiliza mail log nici pentru acest flux în dezvoltare; testele folosesc Mail fake/array. Nu există mod demo sau fallback cu date fictive.
7. Configurează sesiuni și cache comune tuturor instanțelor. În producție folosește HTTPS, `APP_DEBUG=false`, `APP_URL` corect, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`. Accesul la raport necesită cookie-ul original; UUID-ul nu este autorizație. Cookie-ul de sesiune poate expira înaintea raportului.
8. După modificări de configurare: `php artisan config:cache` și `php artisan queue:restart`. Asigură acces HTTPS la `haveibeenpwned.com`, `api.search.brave.com` și serviciul de e-mail. Nu pune cheile în JavaScript sau în repository.

## Procese necesare

```sh
php artisan migrate --no-interaction
php artisan queue:work database --queue=exposure --tries=3 --timeout=30 --sleep=1
php artisan schedule:work
```

Coada modulului este **întotdeauna database/exposure**, independent de `QUEUE_CONNECTION=sync` existent în proiect. Tabelele jobs și failed_jobs sunt deja incluse în migrațiile proiectului. În producție gestionează workerul prin Supervisor/systemd și rulează cron `* * * * * cd /cale/catre/proiect && php artisan schedule:run` în loc de `schedule:work`. `retry_after` trebuie să fie mai mare de 30 secunde (implicit 90). MariaDB trebuie pornită separat în cloud cu `/workspace/.cloud-tools/start-db.sh`.

Pentru dezvoltare: `php artisan serve --no-reload --host=127.0.0.1 --port=8000` în cloud (flagul păstrează mediul PHP local), opțional `npm run dev`. Nu expune serverul de dezvoltare în producție.

Curățare manuală: `php artisan exposure:prune`. Scheduler o execută în fiecare minut: șterge rapoartele/challenge-urile expirate, marchează joburile blocate peste 20 minute drept incomplete și păstrează statisticile agregate 30 de zile. Verifică monitorizarea Scheduler și workerului: simpla definire a programării nu pornește procesele. Accesul la rapoartele expirate este blocat chiar dacă Scheduler nu rulează. Reducerea retenției în admin scurtează rapoartele existente fără a prelungi cele vechi.

## Administrare

`/admin/verificarea-datelor` este protejat prin `auth.basic`, rate limiting și `is_exposure_admin`; nu există acces implicit pentru toți utilizatorii. Folosește un utilizator Laravel existent cu parolă hashuită și acordă rolul prin `php artisan exposure:admin ID_NUMERIC`. Nu este creat niciun cont sau parolă implicită. Accesul HTTP Basic se folosește numai peste HTTPS, cu reverse proxy care transmite corect Authorization. Restrânge suplimentar ruta la rețeaua administrativă în producție. Nu se construiește autentificare publică doar pentru această pagină.

Setări: pagină/endpoints, meniu independent, taburi independente, limite IP/sesiune/adresă pe oră (inclusiv retrimiteri), cotă API zilnică per furnizor, interval între apeluri per furnizor, max. rezultate 1–20, retenție 1–72 ore (implicit 24). Cota zilnică este rezervată atomic în DB înaintea fiecărui apel, inclusiv retry; creșterea limitei nu cumpără credit la furnizor. Ajustează intervalul la RPS/RPM și planul contractat; implicit 2 secunde. Cache-ul comun este necesar pentru pacing și rate limiting distribuit. Afișarea statusului de configurare indică prezența cheilor și declarația de drepturi, nu o probă live sau soldul abonamentului. Statistici agregate: solicitări, apeluri, verificări finalizate, erori; nu sunt afișate date personale.

Dezactivarea paginii blochează și rutele de confirmare/status/rezultate; dezactivarea unui tab blochează operațiile acelui modul și joburile viitoare. Ascunderea din meniu nu dezactivează pagina. Rapoartele deja create rămân criptate până la expirare/ștergere; interogările aflate deja în rețea nu pot fi anulate retroactiv.

## Flux și confidențialitate

POST-urile nu pun datele în URL și nu reflashează inputul în sesiune. Numele acceptă litere, spații, cratimă și apostrof; localitatea acceptă text simplu. Interogările Brave sunt literali între ghilimele (maximum 50 de cuvinte în total), fără operatori controlați de utilizator. Pentru e-mail se păstrează adresa exactă (inclusiv `+`); formele cu ghilimele/backslash nu sunt acceptate. API-urile cer prin contract adresa/termenii în URL-ul **apelului server–furnizor**, niciodată în URL-urile aplicației sau loguri. Nu instrumenta aceste apeluri cu înregistrarea URL-urilor sau corpurilor.

E-mailul este confirmat prin token CSPRNG 256-bit, numai hash SHA-256 în tabelul de challenge, expirare 15 minute, utilizare unică prin tranzacție și row locking. GET-ul linkului doar transferă hashul în sesiune și redirecționează la o pagină curată; un scanner de e-mail nu pornește căutarea. POST-ul consumă tokenul în browserul original și permite reuse pentru aceeași adresă în acea sesiune timp de o oră. Tokenul nu conține e-mail. Retrimiterea invalidează linkul anterior.

Subiectul și rezultatele sunt criptate cu cast-urile Laravel. Identificatorul determinist al adresei și identificatorii antiabuz sunt HMAC cu cheia dedicată. Ownerul raportului derivă dintr-o valoare aleatoare în sesiune. Payloadurile joburilor sunt criptate (`ShouldBeEncrypted`), inclusiv tokenul pentru trimitere; jobul de verificare conține numai UUID. Excepțiile furnizorilor/transportului nu sunt propagate cu URL/corp/recipient către log sau failed_jobs. Răspunsurile private și erorile modulului au no-store, noindex și no-referrer. Rapoartele nu sunt adăugate în sitemap; noindex nu ține loc de autorizare.

**Configurarea infrastructurii:** exclude întregul prefix `/verificare-date-personale` din access log, analytics, session replay, APM și request-body capture, mai ales `/confirmare/*` (tokenuri), precum și apelurile HTTP către furnizori, e-mailul și antetul Authorization administrativ. Repository-ul nu are Telescope/Debugbar; dacă sunt instalate ulterior, exclude aceste requesturi/joburi/mailuri și operațiile de model sau dezactivează înregistrarea lor. Nu rula workerul cu instrumente care capturează argumente/variabile locale. Sesiunile și cache-ul conțin doar identificatori HMAC, nu termenii căutării. Operatorul trebuie să protejeze și să rotească logurile infrastructurii; aplicația nu poate configura un reverse proxy extern.

Ștergerea elimină rândul raportului și challenge-urile asociate; joburile restante nu mai pot găsi datele și nu apelează furnizorul. Eventualele joburi eșuate păstrează doar un payload criptat cu UUID/token, nu e-mailul sau rezultate; aplicați politica de retenție a cozii operaționale. Backupurile nu pot fi șterse individual de acest buton: documentează și aplică o retenție scurtă pentru ele. Nu se promite ștergerea în sistemele furnizorilor sau din paginile publice.

## Contractele furnizorilor și verificarea documentației

- HIBP: [documentația v3](https://haveibeenpwned.com/API/v3), [abonamente](https://haveibeenpwned.com/API/Key), [atribuire](https://haveibeenpwned.com/API/v3#License). Integrarea folosește `GET /api/v3/breachedaccount/{account}?truncateResponse=false`, `hibp-api-key` și `User-Agent`; 404 înseamnă zero breșe disponibile, nu indisponibilitate; 429 respectă Retry-After. Descrierile sunt text simplu, etichetele de date și indicatorii sunt ai sursei. Atribuire vizibilă HIBP / CC BY 4.0, inclusiv menționarea transformării descrierilor în text.
- Brave: [documentație web search](https://api-dashboard.search.brave.com/documentation/services/web-search), [planuri și drepturi](https://brave.com/search/api/), [repository oficial verificat](https://github.com/brave/brave-search-mcp-server). `GET https://api.search.brave.com/res/v1/web/search`, antet `X-Subscription-Token`, `q`, `count` <=20, `spellcheck=false`, `text_decorations=false`, `result_filter=web`. Folosește numai `web.results`: title, url, description; fără descărcarea paginilor. Atribuire Brave Search vizibilă. Data verificării este etichetată distinct de publicare.
- Timeout conectare 5 secunde, total 15; redirecturile API sunt dezactivate. Maximum 3 încercări de job, cu eliberare întârziată pentru 429 (Retry-After limitat la 300 secunde); celelalte erori sunt indisponibilitate fără retry agresiv. Nu există rezultate fabricate sau scoruri. Răspunsuri invalide, linkuri respinse, limitarea numărului de breșe sau joburi întrerupte sunt marcate incomplete.

**Limită a verificării în acest mediu:** la implementare (16 octombrie 2026), paginile HIBP API v3 și portalul Brave au răspuns HTTP 403 din rețeaua cloud. README-ul repository-ului oficial Brave a putut fi citit și confirmă limitele de căutare și autentificarea prin cheie. Nu pretindem că termenii contractuali/planurile curente HIBP sau drepturile de afișare/stocare Brave au fost reverificate integral online. Operatorul trebuie să le verifice în linkurile oficiale și în contract, inclusiv atribuirea și limitele efective, înainte să seteze flagurile de confirmare. Adaptoarele sunt testate cu mockuri, fără credite reale. Nu au fost făcute apeluri de verificare pentru persoane reale.

## Validare

Datele afișate utilizatorului folosesc Europe/Bucharest; contoarele și cotele zilnice folosesc UTC.

`php artisan test`, `npm run build`, `php artisan view:cache` și `php artisan schedule:list`. Testele folosesc SQLite în memorie și mockuri HTTP/mail/queue; includ tokenuri, autorizare între sesiuni, rate limiting, cote, dezactivare, ștergere/expirare, lipsa cheilor, răspunsuri eronate și escape HTML. Verificările vizuale folosesc Chromium la 1440px și 390px; capturile sunt artefacte locale în `/workspace/.cloud-tools`, nu date demonstrative în aplicație.
