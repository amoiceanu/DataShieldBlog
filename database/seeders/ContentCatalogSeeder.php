<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ContentCatalogSeeder extends Seeder
{
    private const SECTIONS = [
        ['Securitate dispozitive', 'securitate-dispozitive', 'Protejează telefonul, calculatorul și dispozitivele conectate împotriva amenințărilor digitale.'],
        ['Parole și autentificare', 'parole-autentificare', 'Învață să folosești parole sigure, manageri de parole, passkeys și autentificare în doi pași.'],
        ['Fraude online', 'fraude-online', 'Identifică tentativele de phishing, mesajele false, escrocheriile și site-urile frauduloase.'],
        ['Date personale', 'date-personale', 'Descoperă cum să îți protejezi identitatea și informațiile personale pe internet.'],
        ['Confidențialitate', 'confidentialitate', 'Controlează informațiile colectate despre tine și limitează urmărirea activității online.'],
        ['Rețele sociale', 'retele-sociale', 'Protejează conturile Facebook, Instagram, WhatsApp și alte platforme sociale.'],
        ['Ghiduri practice', 'ghiduri-practice', 'Tutoriale explicate pas cu pas pentru rezolvarea problemelor de securitate digitală.'],
        ['Inteligență artificială', 'inteligenta-artificiala', 'Înțelege riscurile tehnologiilor AI, deepfake-urilor și utilizării datelor personale în instrumentele AI.'],
        ['Securitate financiară', 'securitate-financiara', 'Protejează cardurile bancare, plățile online și conturile financiare împotriva fraudelor.'],
        ['Știri și alerte', 'stiri-alerte', 'Găsește avertizări oficiale și recomandări pentru verificarea amenințărilor cibernetice.'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->renameAndMergeSections();
            $categories = [];

            foreach (self::SECTIONS as [$name, $slug, $description]) {
                $categories[$slug] = Category::updateOrCreate(['slug' => $slug], compact('name', 'slug', 'description'));
            }

            $this->moveExistingArticles($categories);
            $this->addExistingArticleMetadata();

            foreach ($this->articles() as $data) {
                $related = $this->relatedLinks($data['related']);
                $sources = $this->sourceLinks($data['sources']);
                $content = $data['content'].$related.$sources;
                $category = $categories[$data['category']];
                $secondary = $data['secondary_keywords'];

                $article = Article::firstOrCreate(['slug' => $data['slug']], [
                    'category_id' => $category->id,
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $content,
                    'image' => $data['image'] ?? null,
                    'author' => 'Redacția Securitate Digitală',
                    'published_at' => today()->toDateString(),
                    'reading_time' => $data['reading_time'],
                    'is_featured' => false,
                    'status' => 'published',
                    'seo_title' => $data['seo_title'],
                    'meta_description' => $data['meta_description'],
                    'focus_keyword' => $data['focus_keyword'],
                    'secondary_keywords' => $secondary,
                ]);

                if ($article->status !== 'published' || ! $article->published_at || $article->published_at->isAfter(today())) {
                    $article->update(['status' => 'published', 'published_at' => today()->toDateString()]);
                }

                if (! $article->image && ! empty($data['image'])) {
                    $article->update(['image' => $data['image']]);
                }
            }

            Cache::forget('blog.categories');
        });
    }

    private function renameAndMergeSections(): void
    {
        foreach ([
            'securitate-online' => 'securitate-dispozitive',
            'parole' => 'parole-autentificare',
            'social-media' => 'retele-sociale',
            'ghiduri' => 'ghiduri-practice',
        ] as $oldSlug => $newSlug) {
            $old = Category::where('slug', $oldSlug)->first();
            if (! $old) {
                continue;
            }

            $target = Category::where('slug', $newSlug)->first();
            if ($target) {
                $old->articles()->update(['category_id' => $target->id]);
                $old->delete();
            } else {
                $old->update(['slug' => $newSlug]);
            }
        }

        $phishing = Category::where('slug', 'phishing')->first();
        if ($phishing) {
            $fraud = Category::firstOrCreate(
                ['slug' => 'fraude-online'],
                ['name' => 'Fraude online', 'description' => 'Identifică tentativele de phishing, mesajele false, escrocheriile și site-urile frauduloase.'],
            );
            $phishing->articles()->update(['category_id' => $fraud->id]);
            $phishing->delete();
        }
    }

    private function moveExistingArticles(array $categories): void
    {
        Article::where('slug', '10-reguli-simple-protejare-date-personale-online')
            ->update(['category_id' => $categories['date-personale']->id]);

        Article::where('slug', 'cum-recunosti-email-phishing')
            ->update(['category_id' => $categories['fraude-online']->id]);
    }

    private function addExistingArticleMetadata(): void
    {
        $metadata = [
            '10-reguli-simple-protejare-date-personale-online' => [
                'seo_title' => '10 reguli pentru protecția datelor personale',
                'meta_description' => 'Zece obiceiuri practice pentru a reduce expunerea datelor personale, a securiza conturile și a limita riscul de fraudă online.',
                'focus_keyword' => 'protecția datelor personale',
                'secondary_keywords' => ['securitate online', 'protejarea conturilor', 'igienă digitală'],
            ],
            'cum-recunosti-email-phishing' => [
                'seo_title' => 'Cum recunoști un e-mail de phishing',
                'meta_description' => 'Învață să verifici expeditorul, linkurile și solicitările urgente și află ce pași să urmezi după un mesaj de phishing.',
                'focus_keyword' => 'e-mail de phishing',
                'secondary_keywords' => ['mesaje frauduloase', 'linkuri false', 'protecție împotriva phishingului'],
            ],
            'date-personale-nu-posta-online' => [
                'seo_title' => 'Ce date personale să nu publici online',
                'meta_description' => 'Află ce date personale, documente și detalii despre locație pot crește riscul de furt de identitate și cum le protejezi.',
                'focus_keyword' => 'date personale online',
                'secondary_keywords' => ['furt de identitate', 'confidențialitate', 'siguranță pe rețele sociale'],
            ],
        ];

        foreach ($metadata as $slug => $values) {
            $article = Article::where('slug', $slug)->first();
            if (! $article) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (blank($article->{$key})) {
                    $article->{$key} = $value;
                }
            }
            $article->save();
        }
    }

    private function relatedLinks(array $slugs): string
    {
        $links = Article::whereIn('slug', $slugs)->orderBy('title')->get(['title', 'slug']);
        if ($links->isEmpty()) {
            return '';
        }

        return '<h2>Articole conexe</h2><ul>'.
            $links->map(fn (Article $article): string => '<li><a href="/articole/'.e($article->slug).'">'.e($article->title).'</a></li>')->implode('').
            '</ul>';
    }

    private function sourceLinks(array $sources): string
    {
        return '<h2>Surse și resurse utile</h2><ul>'.
            collect($sources)->map(fn (array $source): string => '<li><a href="'.e($source[1]).'" target="_blank" rel="noopener noreferrer">'.e($source[0]).'</a></li>')->implode('').
            '</ul>';
    }

    private function articles(): array
    {
        return [
            [
                'title' => 'Cum recunoști un SMS fals și eviți fraudele prin mesaje',
                'slug' => 'cum-recunosti-sms-fals-fraude-mesaje', 'category' => 'fraude-online',
                'excerpt' => 'Mesajele despre colete blocate, taxe restante sau conturi suspendate pot fi tentative de smishing. Învață să verifici expeditorul și linkul și află ce să faci dacă ai deschis pagina sau ai introdus date.',
                'seo_title' => 'Cum recunoști un SMS fals și eviți smishingul',
                'meta_description' => 'Învață să recunoști SMS-urile false despre colete, taxe sau conturi și să reacționezi dacă ai deschis un link ori ai introdus date.',
                'focus_keyword' => 'SMS fals', 'secondary_keywords' => ['smishing', 'fraudă prin SMS', 'linkuri false'], 'reading_time' => 6,
                'related' => ['cum-recunosti-email-phishing'],
                'sources' => [
                    ['ENISA: ghid de igienă cibernetică', 'https://www.enisa.europa.eu/topics/cyber-hygiene'],
                    ['NIST: cum funcționează phishingul prin SMS și voce', 'https://www.nist.gov/blogs/cybersecurity-insights/phishing-resistance-protecting-keys-your-kingdom'],
                    ['Servicii publice: raportarea incidentelor către DNSC', 'https://www.serviciipublice.gov.ro/serviciu/preluare-alerte-de-securitate-cibernetica'],
                ],
                'content' => <<<'HTML'
<p>Un SMS fals încearcă să te facă să apeși pe un link, să plătești o sumă mică sau să oferi datele contului. Atacurile prin mesaje text se numesc <strong>smishing</strong> — o formă de phishing care folosește SMS-uri sau aplicații de mesagerie. Un singur indiciu nu dovedește că mesajul este fraudulos; verifică împreună expeditorul, contextul, linkul și acțiunea cerută.</p>
<h2>Mesaje false întâlnite frecvent</h2>
<p>Un mesaj despre „colet blocat” poate cere o taxă mică de reprogramare și numărul cardului. Altul pretinde că ai o datorie, că ți se suspendă contul sau că trebuie să confirmi o plată. Escrocul urmărește fie datele de autentificare, fie datele cardului, fie instalarea unei aplicații care oferă acces la telefon.</p>
<p>Expeditorul poate apărea sub numele unei firme cunoscute, iar conversația poate ajunge în același fir cu mesaje autentice dacă identificatorul a fost falsificat sau reutilizat. Numele afișat nu autentifică mesajul. Nu răspunde și nu folosi numărul de telefon ori linkul din mesaj pentru a verifica povestea.</p>
<h2>Semnale de avertizare</h2>
<ul><li>Solicită plata printr-un link scurt, prin transfer neobișnuit sau cu datele cardului, deși nu ai o comandă în așteptare.</li><li>Te presează cu un termen foarte scurt, amenință cu suspendarea contului sau promite un premiu neașteptat.</li><li>Adresa site-ului conține greșeli, cuvinte în plus ori un domeniu care nu aparține organizației.</li><li>Ți se cere parola, codul unic, PIN-ul sau instalarea unei aplicații de la un link primit prin mesaj.</li></ul>
<p>HTTPS indică o conexiune criptată, nu faptul că pagina aparține firmei invocate. Pe telefon poți apăsa lung pe un link pentru a-i vedea destinația fără să-l deschizi; pentru o verificare mai sigură, închide mesajul și intră manual în aplicația oficială sau tastează adresa cunoscută. Dacă există o comandă reală, verific-o în contul comerciantului sau contactează serviciul prin datele sale publicate separat.</p>
<h2>Dacă ai deschis linkul</h2>
<p>Dacă doar ai vizitat pagina și nu ai introdus nimic și nu ai instalat nimic, închide fila, nu accepta notificările site-ului și nu descărca fișiere. Actualizează browserul și sistemul. O scanare poate fi potrivită dacă ai descărcat sau instalat ceva; simpla deschidere a unui link nu confirmă automat compromiterea telefonului.</p>
<p>Dacă ai introdus o parolă, schimb-o imediat din aplicația ori pagina oficială, de pe un dispozitiv despre care nu ai motive concrete să crezi că este compromis. Schimb-o și oriunde ai reutilizat-o, activează autentificarea în doi pași și revocă sesiunile necunoscute. Dacă ai introdus datele cardului sau ai autorizat o plată, sună banca folosind numărul de pe card sau din aplicația oficială și cere instrucțiuni pentru blocarea ori contestarea operațiunilor. Nu aștepta răspunsul expeditorului.</p>
<h2>Ce poți face preventiv</h2>
<p>Folosește un manager de parole: completarea automată ar trebui să se oprească pe un domeniu diferit de cel real, ceea ce poate fi un indiciu util. Dezactivează previzualizările care afișează coduri pe ecranul blocat dacă telefonul este folosit de alte persoane. Raportează mesajul prin instrumentele operatorului ori platformei și, pentru un incident cibernetic, consultă canalele oficiale DNSC. Păstrează captura și adresa suspectă dacă raportezi; nu o redistribui ca avertizare cu link activ.</p>
HTML,
            ],
            [
                'title' => 'Cele mai frecvente fraude online din România și cum te protejezi',
                'slug' => 'fraude-online-frecvente-romania-protectie', 'category' => 'fraude-online',
                'excerpt' => 'Fraudele online folosesc mesaje urgente, magazine inexistente, anunțuri false și identități imitate. Ghidul arată cum să verifici cererile și plățile și ce pași să urmezi după o tranzacție suspectă online.',
                'seo_title' => 'Fraude online frecvente în România: semne și protecție',
                'meta_description' => 'Recunoaște phishingul, magazinele false, impersonarea și escrocheriile din anunțuri. Află cum verifici o ofertă și ce faci după o fraudă.',
                'focus_keyword' => 'fraude online România', 'secondary_keywords' => ['magazine online false', 'escrocherii online', 'anunțuri frauduloase'], 'reading_time' => 7,
                'related' => ['cum-recunosti-email-phishing', 'date-personale-nu-posta-online'],
                'sources' => [
                    ['ENISA: cumpărături online mai sigure', 'https://www.enisa.europa.eu/news/enisa-news/tips-for-cybersecurity-when-buying-and-selling-online'],
                    ['FTC: cumpărarea de pe marketplace-uri', 'https://consumer.ftc.gov/articles/buying-online-marketplace'],
                    ['DNSC: canale oficiale de raportare a incidentelor', 'https://www.serviciipublice.gov.ro/serviciu/preluare-alerte-de-securitate-cibernetica'],
                ],
                'content' => <<<'HTML'
<p>Frauda online nu are o singură formă. Uneori un mesaj imită banca, curierul sau o instituție; alteori o reclamă duce la un magazin care nu livrează, un anunț fals te scoate din platformă sau un presupus consultant promite câștiguri sigure. Scopul comun este să obțină bani, date de card, acces la conturi ori controlul dispozitivului. Verifică fiecare cerere printr-un canal pe care îl găsești independent.</p>
<h2>Phishing și impersonare</h2>
<p>Un mesaj poate anunța o plată respinsă, o amendă, un colet blocat sau o problemă de securitate. Linkul deschide o pagină asemănătoare cu cea reală, unde ți se cer parola și codul de autorizare. Instituțiile nu au nevoie să le trimiți prin SMS. Închide mesajul și verifică direct în aplicația oficială; dacă ești contactat telefonic, sună înapoi la numărul publicat pe site-ul instituției.</p>
<h2>Magazine și anunțuri false</h2>
<p>Un preț mult sub piață, plata urgentă în afara platformei, lipsa datelor clare despre comerciant ori reguli de retur greu de găsit justifică prudență. Nici recenziile și nici HTTPS nu garantează singure că vânzătorul este legitim: recenziile pot fi fabricate, iar HTTPS doar criptează conexiunea. Caută denumirea comerciantului împreună cu termeni precum „reclamație” și verifică datele de contact, politica de retur și metoda de plată înainte de comandă.</p>
<p>Pe platformele de anunțuri, un cumpărător fals poate trimite un link pentru „încasarea banilor” sau curierat, solicitând datele cardului și codul primit de la bancă. Pentru a primi o plată nu trebuie să divulgi codul de securitate, PIN-ul sau parola de online banking. Rămâi în sistemul de mesagerie și plată al platformei, acolo unde există protecție pentru tranzacție.</p>
<h2>Investiții, suport tehnic și recuperarea banilor</h2>
<p>O ofertă care promite randament garantat sau cere să instalezi o aplicație de acces la distanță merită verificată separat. Nu trimite bani suplimentari pentru „deblocarea” câștigurilor și nu permite unui necunoscut să controleze telefonul ori calculatorul. După o pierdere, pot apărea persoane care pretind că recuperează fondurile contra unui avans; niciun intermediar nu poate garanta recuperarea.</p>
<h2>Pași de protecție</h2>
<ol><li>Oprește conversația și nu folosi datele de contact primite în mesaj.</li><li>Verifică oferta pe site-ul oficial sau direct în aplicație.</li><li>Plătește printr-o metodă care oferă proceduri de dispută; citește condițiile furnizorului.</li><li>Păstrează factura, anunțul, conversația, confirmarea transferului și capturile.</li><li>Dacă ai făcut o plată suspectă, contactează imediat banca; dacă ai oferit date de acces, schimbă parolele și revocă sesiunile.</li></ol>
<p>Disponibilitatea unei rambursări depinde de metoda de plată, autorizarea tranzacției, contractul cu furnizorul și împrejurări. Raportează anunțul în platformă și cere îndrumare instituțiilor competente. Pentru incidente cibernetice, DNSC oferă canale oficiale de raportare; pentru o posibilă infracțiune, păstrează dovezile și contactează Poliția.</p>
HTML,
            ],
            [
                'title' => 'Cum identifici un site fals înainte să introduci date personale',
                'slug' => 'cum-identifici-site-fals-date-personale', 'category' => 'fraude-online',
                'excerpt' => 'O pagină falsă poate copia identitatea unei bănci sau a unui magazin. Verifică domeniul, metoda de contact și contextul linkului înainte să introduci parola, datele cardului sau informații personale online.',
                'seo_title' => 'Cum identifici un site fals înainte de autentificare',
                'meta_description' => 'Verifică domeniul, HTTPS, datele comerciantului și linkurile înainte să introduci date pe un site care ar putea fi fals.',
                'focus_keyword' => 'site fals', 'secondary_keywords' => ['domeniu fraudulos', 'site de phishing', 'verificare magazin online'], 'reading_time' => 6,
                'related' => ['cum-recunosti-email-phishing'],
                'sources' => [
                    ['ENISA: phishing și verificarea sursei', 'https://tools.enisa.europa.eu/topics/incident-response/glossary/phishing-spear-phishing'],
                    ['ENISA: cumpărături online mai sigure', 'https://www.enisa.europa.eu/news/enisa-news/tips-for-cybersecurity-when-buying-and-selling-online'],
                    ['FTC: verificarea marketplace-ului și a vânzătorului', 'https://consumer.ftc.gov/articles/buying-online-marketplace'],
                ],
                'content' => <<<'HTML'
<p>Un site fals încearcă să pară suficient de familiar încât să introduci o parolă, un cod bancar ori date de contact. Poate copia logo-ul, culorile și formularele unei bănci sau ale unui magazin, dar să trimită informațiile atacatorului. Verificarea trebuie să înceapă de la adresa exactă și de la motivul pentru care ai ajuns acolo, nu de la aspectul paginii.</p>
<h2>Verifică domeniul, nu doar logo-ul</h2>
<p>Citește domeniul literă cu literă și caută substituții, cuvinte adăugate, cratime neobișnuite sau terminații diferite. Un nume de companie poate apărea într-un subdomeniu al unui domeniu străin: textul din stânga nu spune cine controlează domeniul principal. Pe telefon, bara de adresă poate fi scurtată; atinge-o pentru a vedea adresa completă. Pentru conturi importante, folosește un semn de carte salvat anterior sau aplicația oficială.</p>
<h2>Ce îți spune HTTPS și ce nu îți spune</h2>
<p>HTTPS criptează traficul dintre browser și server și ajută la prevenirea interceptării pe traseu. Nu verifică dacă firma este reală, dacă oferta este onestă sau dacă domeniul nu a fost înregistrat de un escroc. Pictograma lacătului nu este o certificare comercială. Dacă browserul afișează o avertizare de certificat, nu o ocoli ca să ajungi la o pagină de plată.</p>
<h2>Verifică comerciantul și cererea</h2>
<p>La o cumpărătură, caută numele legal, date de contact funcționale, condițiile de livrare și retur și descrieri coerente. Caută separat numele magazinului plus „reclamație” sau „fraudă”; rezultatele pot ajuta, dar absența plângerilor nu dovedește legitimitatea. Prețurile improbabil de mici, numărătoarele care reîncep și insistența pentru transfer direct sunt motive să amâni plata.</p>
<p>O pagină de autentificare neașteptată trebuie tratată cu prudență, chiar dacă folosește marca pe care o cunoști. Băncile și platformele pot cere confirmări legitime în aplicație, dar nu transmite coduri unice sau parole unei persoane care te-a contactat. Dacă ai un dubiu, închide pagina și pornește sesiunea din aplicația cunoscută.</p>
<h2>O listă scurtă înainte să trimiți date</h2>
<ol><li>Ai inițiat tu accesarea site-ului sau ai urmat un link nesolicitat?</li><li>Se potrivește domeniul complet cu adresa oficială pe care o folosești de obicei?</li><li>Poți confirma oferta printr-un canal independent?</li><li>Ți se cer date care nu sunt necesare pentru acțiune, precum PIN-ul sau codul de autorizare?</li><li>Metoda de plată și politica de retur sunt explicate clar?</li></ol>
<p>Dacă ai introdus o parolă, schimb-o din site-ul oficial și actualizează toate conturile unde era reutilizată. Dacă ai transmis date bancare, contactează banca imediat. Păstrează adresa și mesajul pentru raportare, dar nu încărca date personale într-un serviciu public de analiză a linkurilor.</p>
HTML,
            ],
            [
                'title' => 'Ce faci dacă ai accesat un link de phishing',
                'slug' => 'ce-faci-dupa-link-phishing', 'category' => 'fraude-online',
                'excerpt' => 'Reacția depinde de ce s-a întâmplat după accesarea linkului. Separă simpla vizitare de introducerea parolei, autorizarea unei plăți sau instalarea unui fișier și urmează pașii potriviți fiecărui caz în siguranță.',
                'seo_title' => 'Ai accesat un link de phishing? Pașii de urmat',
                'meta_description' => 'Află ce să faci după ce ai deschis un link de phishing, ai introdus o parolă, ai autorizat o plată sau ai instalat un fișier.',
                'focus_keyword' => 'am accesat un link de phishing', 'secondary_keywords' => ['parolă compromisă', 'link suspect', 'securizarea contului'], 'reading_time' => 7,
                'related' => ['cum-recunosti-email-phishing', 'cum-recunosti-sms-fals-fraude-mesaje'],
                'sources' => [
                    ['ENISA: recunoașterea și reacția la phishing', 'https://tools.enisa.europa.eu/topics/incident-response/glossary/phishing-spear-phishing'],
                    ['Google: securizarea unui cont posibil compromis', 'https://support.google.com/accounts/answer/6294825?hl=ro'],
                    ['DNSC: raportarea incidentelor cibernetice în România', 'https://www.serviciipublice.gov.ro/serviciu/preluare-alerte-de-securitate-cibernetica'],
                ],
                'content' => <<<'HTML'
<p>Deschiderea unui link nu înseamnă automat că telefonul sau calculatorul a fost compromis. Riscul depinde de ce ai făcut pe pagină și dacă ai descărcat ori instalat ceva. Închide pagina și reconstruiește calm pașii: ai introdus o parolă, un cod primit prin SMS, date de card sau ai aprobat o solicitare? Ai instalat o aplicație sau un profil? Răspunsul schimbă ordinea măsurilor.</p>
<h2>Dacă doar ai vizitat pagina</h2>
<p>Închide fila și nu apăsa pe notificări, ferestre de descărcare ori butoane de „curățare”. Șterge descărcările necunoscute fără să le deschizi, actualizează browserul și sistemul și rulează verificarea de securitate integrată dacă ai descărcat un fișier. Doar accesarea paginii, în absența altor indicii, nu confirmă că s-a instalat malware. Evită instrumentele care promit să repare imediat dispozitivul și cer bani sau acces de la distanță.</p>
<h2>Dacă ai introdus parola sau codul</h2>
<p>De pe o sesiune sigură, intră manual în serviciul legitim și schimbă parola. Dacă aceeași parolă era folosită în altă parte, schimb-o și acolo, începând cu adresa principală de e-mail și conturile financiare. Activează MFA sau o passkey acolo unde este disponibilă, verifică adresa și metodele de recuperare, revocă sesiunile/dispozitivele pe care nu le recunoști și elimină aplicațiile conectate pe care nu le-ai autorizat. Nu șterge e-mailurile de securitate; ele pot ajuta la stabilirea momentului incidentului.</p>
<p>Un cod de unică folosință poate autoriza o autentificare sau o tranzacție; nu îl dicta și nu îl introduce într-o pagină deschisă din mesaj. Dacă l-ai transmis, tratează contul ca urgent: folosește recuperarea oficială și contactează serviciul prin canalele sale cunoscute.</p>
<h2>Dacă ai introdus date de card sau ai aprobat o plată</h2>
<p>Sună imediat banca folosind numărul de pe card sau aplicația sa oficială. Spune ce date ai introdus și dacă ai confirmat o tranzacție. Cere să evalueze blocarea cardului, a accesului online ori contestarea operațiunii; pașii și drepturile depind de tipul plății și de situația concretă. Verifică tranzacțiile recente și păstrează numărul sesizării și confirmările.</p>
<h2>Dacă ai instalat o aplicație sau fișier</h2>
<p>Nu mai folosi dispozitivul afectat pentru operațiuni bancare până nu îl verifici. Dacă un necunoscut a primit acces de la distanță, deconectează temporar dispozitivul de la rețea și contactează banca de pe alt telefon. Dezinstalează aplicația numai dacă știi cum să o faci în siguranță; pentru simptome persistente, cere ajutor tehnic de încredere. Păstrează dovezile și raportează incidentul DNSC sau Poliției, după natura lui.</p>
<p>Schimbarea tuturor parolelor de pe același dispozitiv suspect poate expune din nou conturile. Securizează întâi dispozitivul sau folosește un altul, apoi verifică sesiunile și metodele de recuperare. Notează ce s-a întâmplat și când; această cronologie ajută banca și serviciile să răspundă mai repede.</p>
HTML,
            ],
            [
                'title' => 'Cum verifici dacă adresa ta de email a fost compromisă',
                'slug' => 'verificare-email-compromis-brese-date', 'category' => 'date-personale',
                'excerpt' => 'O adresă găsită într-o breșă nu arată singură dacă cineva a intrat în contul tău. Verifică notificările și activitatea, folosește un serviciu de căutare reputabil și schimbă parolele reutilizate online.',
                'seo_title' => 'Cum verifici dacă emailul tău apare într-o breșă',
                'meta_description' => 'Verifică adresa de e-mail în breșe cunoscute, înțelege ce înseamnă rezultatul și securizează parolele și conturile reutilizate.',
                'focus_keyword' => 'email compromis', 'secondary_keywords' => ['verificare breșă de date', 'Have I Been Pwned', 'parolă reutilizată'], 'reading_time' => 6,
                'related' => ['date-personale-nu-posta-online', '10-reguli-simple-protejare-date-personale-online'],
                'sources' => [
                    ['Have I Been Pwned: întrebări frecvente despre breșe', 'https://haveibeenpwned.com/Faqs'],
                    ['Google: securizarea unui cont posibil compromis', 'https://support.google.com/accounts/answer/6294825?hl=ro'],
                    ['CISA: parole, MFA și phishing', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Poți verifica dacă o adresă de e-mail apare într-o breșă cunoscută, dar rezultatul nu spune de unul singur dacă cineva îți controlează acum inboxul. O breșă poate expune adrese, nume, parole vechi sau alte câmpuri, în funcție de serviciu. Uneori setul de date este incomplet ori etichetat ca neverificat. Folosește rezultatul ca motiv să verifici conturile, nu ca diagnostic definitiv.</p>
<h2>Cum verifici fără să îți expui parola</h2>
<p>Introdu doar adresa de e-mail într-un serviciu cunoscut precum Have I Been Pwned și citește explicația despre incident. Serviciul arată ce breșe cunoscute includ adresa; nu îți va spune parola asociată. Nu introduce parola curentă într-un site care promite să verifice dacă a fost furată și nu descarca liste de credențiale.</p>
<p>Verifică și inboxul pentru alerte de securitate autentice, activitatea de conectare și dispozitivele din setările contului. Accesează pagina de securitate tastând singur adresa oficială. Mesajele de tip „contul tău a fost spart, plătește acum” pot fi ele însele escrocherii și nu dovedesc că expeditorul are acces la cont.</p>
<h2>Dacă adresa apare într-o breșă</h2>
<p>Identifică serviciul și data breșei, apoi schimbă parola pe acel serviciu. Dacă parola a fost reutilizată, schimb-o pe toate conturile respective, începând cu e-mailul principal, bankingul și conturile care pot recupera alte conturi. Alege parole unice generate și păstrate într-un manager. Nu adăuga variații simple ale aceleiași parole.</p>
<p>Activează autentificarea în doi pași sau o passkey. Verifică numărul și adresa de recuperare, regulile de redirecționare ale e-mailului, filtrele, aplicațiile conectate și sesiunile active. Un atacator care a creat o regulă să ascundă alertele poate rămâne prezent chiar după schimbarea parolei. Închide sesiunile pe care nu le recunoști și actualizează codurile de recuperare.</p>
<h2>Ce informații pot fi expuse?</h2>
<p>Unele incidente includ numai adresa de e-mail și informații publice; altele pot cuprinde nume, număr de telefon, istoric de cumpărături ori parole hash-uite. Nu presupune că toate câmpurile au fost expuse doar pentru că adresa apare într-un rezultat. Citește descrierea incidentului și notificarea serviciului. O adresă într-o breșă poate crește riscul de mesaje țintite, dar nu înseamnă automat furt de identitate.</p>
<h2>Limitele verificării</h2>
<p>Absența adresei dintr-un serviciu nu dovedește că datele nu au fost niciodată expuse: serviciul poate să nu cunoască incidentul, iar unele breșe sensibile nu sunt publice. Monitorizează conturile importante, instalează actualizările și reacționează la activitate reală neautorizată. Nu plăti pentru promisiuni de ștergere a tuturor copiilor de pe internet; odată copiate, datele pot circula în mai multe locuri.</p>
HTML,
            ],
            [
                'title' => 'Ce este furtul de identitate și cum te protejezi',
                'slug' => 'furt-de-identitate-protectie-pasi', 'category' => 'date-personale',
                'excerpt' => 'Furtul de identitate înseamnă folosirea datelor tale pentru a pretinde că ești tu. Află ce informații sunt vizate, cum reduci expunerea și cum reacționezi când apar conturi, cereri sau tranzacții pe care nu le recunoști.',
                'seo_title' => 'Furtul de identitate: prevenție și pași după incident',
                'meta_description' => 'Află cum sunt folosite datele furate, ce măsuri preventive poți lua și cui să te adresezi dacă observi o impersonare.',
                'focus_keyword' => 'furt de identitate', 'secondary_keywords' => ['date personale furate', 'impersonare online', 'protecția identității'], 'reading_time' => 7,
                'related' => ['date-personale-nu-posta-online', 'verificare-email-compromis-brese-date'],
                'sources' => [
                    ['FTC: pași de recuperare după furt de identitate', 'https://www.identitytheft.gov/Steps'],
                    ['DNSC: raportarea incidentelor cibernetice', 'https://www.serviciipublice.gov.ro/serviciu/preluare-alerte-de-securitate-cibernetica'],
                    ['Poliția Română: informații și servicii', 'https://www.politiaromana.ro/'],
                ],
                'content' => <<<'HTML'
<p>Furtul de identitate apare când cineva folosește datele unei persoane pentru a se da drept ea: poate încerca să preia un cont, să deschidă un serviciu, să ceară bani cunoscuților sau să efectueze tranzacții. Nu orice mesaj suspect și nici o singură autentificare nerecunoscută confirmă furtul identității, dar merită verificată orice activitate concretă pe care nu o recunoști.</p>
<h2>Ce date sunt folosite</h2>
<p>Atacatorii pot combina numele, adresa, telefonul, data nașterii, copii de acte, adresa de e-mail și parole obținute din servicii diferite. Un singur detaliu poate părea banal; împreună, datele pot ajuta la impersonare sau la ghicirea răspunsurilor de recuperare. Documentele și selfie-urile cu acte sunt deosebit de sensibile. Trimite-le numai când există un motiv legitim, către un destinatar verificat și printr-un canal potrivit.</p>
<h2>Prevenție realistă</h2>
<ul><li>Publică cât mai puține date de contact, documente și informații despre rutina zilnică.</li><li>Folosește parole unice, MFA și o adresă de recuperare pe care o controlezi.</li><li>Verifică periodic sesiunile, aplicațiile conectate și alertele de la bancă și furnizorii de servicii.</li><li>Distruge în siguranță documentele pe hârtie care conțin identificatori și nu păstra copii de acte în galerii sincronizate fără protecție adecvată.</li><li>Nu trimite coduri de autentificare unei persoane care te sună, chiar dacă știe câteva date personale.</li></ul>
<h2>Ce faci când observi o utilizare neautorizată</h2>
<p>Începe cu contul sau tranzacția afectată. Sună banca dacă apar plăți sau cereri financiare; cere instrucțiuni pentru blocarea cardului, securizarea accesului și contestarea operațiunilor. Schimbă parola serviciului compromis de pe un dispozitiv sigur și revocă sesiunile necunoscute. Verifică dacă au fost schimbate datele de recuperare și securizează e-mailul principal înaintea altor conturi.</p>
<p>Contactează compania în care a apărut contul sau cererea falsă folosind datele de contact găsite independent. Cere blocarea activității neautorizate și păstrează numărul sesizării. Dacă există indicii de infracțiune, păstrează copii ale mesajelor, extraselor și răspunsurilor și contactează Poliția. Dacă incidentul implică acces informatic, raportarea către DNSC poate oferi îndrumare tehnică. Procedurile și remedierile depind de organizația afectată și de faptele concrete.</p>
<h2>Ai grijă la a doua fraudă</h2>
<p>După un incident, unele persoane pot pretinde că sunt investigatori, bancheri sau firme de recuperare a banilor și pot cere taxe în avans, coduri ori acces la distanță. Confirmă fiecare solicitare apelând organizația la un număr oficial. Nu există o metodă sigură care să garanteze recuperarea banilor și nimeni nu are nevoie de parola ta pentru a depune o sesizare.</p>
<p>Păstrează un jurnal cu datele, conturile afectate, persoanele contactate și măsurile luate. Te ajută să nu omiți un cont de recuperare și oferă instituțiilor o cronologie clară. Pentru situații juridice sau financiare complexe, cere sfatul instituției competente ori al unui specialist.</p>
HTML,
            ],
            [
                'title' => 'Cum verifici dacă telefonul tău a fost compromis',
                'slug' => 'cum-verifici-telefon-compromis', 'category' => 'securitate-dispozitive',
                'excerpt' => 'Bateria consumată sau telefonul lent au multe cauze și nu dovedesc singure o compromitere. Verifică aplicațiile, permisiunile, sesiunile și alertele și urmează pași proporționali cu indiciile găsite după o posibilă compromitere.',
                'seo_title' => 'Cum verifici dacă telefonul a fost compromis',
                'meta_description' => 'Verifică aplicațiile, permisiunile și conturile telefonului fără să tragi concluzii dintr-un simptom nespecific.',
                'focus_keyword' => 'telefon compromis', 'secondary_keywords' => ['securitate telefon', 'aplicații suspecte', 'permisiuni Android iPhone'], 'reading_time' => 6,
                'related' => ['ce-faci-dupa-link-phishing', 'securizare-telefon-android-iphone'],
                'sources' => [
                    ['Google: securizarea unui cont posibil compromis', 'https://support.google.com/accounts/answer/6294825?hl=ro'],
                    ['Android: controale de confidențialitate și permisiuni', 'https://www.android.com/intl/en_nz/safety/privacy/'],
                    ['Apple: protecția dispozitivului furat', 'https://support.apple.com/guide/iphone/use-stolen-device-protection-iph17105538b/27/ios/27'],
                ],
                'content' => <<<'HTML'
<p>Telefonul care se încălzește, se descarcă repede sau funcționează lent nu este neapărat compromis. Actualizările, semnalul slab, o baterie uzată și aplicațiile solicitante pot produce aceleași simptome. Caută indicii verificabile: o aplicație pe care nu ai instalat-o, un profil de administrare necunoscut, permisiuni neobișnuite, mesaje trimise fără tine sau alerte de conectare pe care nu le recunoști. Un indiciu merită verificat, dar nu stabilește singur cauza.</p>
<h2>Verificări de bază</h2>
<ol><li>Deschide lista de aplicații și elimină aplicațiile pe care nu le recunoști sau nu le mai folosești. Înainte de eliminare, verifică dacă nu este o componentă de sistem ori de la operator.</li><li>Revizuiește permisiunile pentru locație, cameră, microfon, contacte, SMS și accesibilitate. O aplicație ar trebui să aibă doar accesul necesar funcției sale.</li><li>Instalează actualizările disponibile pentru sistem și aplicații din magazinul oficial.</li><li>Verifică sesiunile și dispozitivele conectate la e-mail, Apple Account, Google Account și aplicațiile de mesagerie.</li><li>Uită-te la alertele operatorului despre SIM, redirecționarea apelurilor sau schimbarea cartelei dacă ai pierdut brusc serviciul.</li></ol>
<h2>Android și iPhone</h2>
<p>Pe Android, setările exacte diferă între producători: caută „Confidențialitate” sau „Manager de permisiuni” în Setări, verifică tabloul de bord de confidențialitate și Play Protect și uită-te la aplicațiile instalate din surse externe. Accesul la Accesibilitate, administrarea dispozitivului ori instalarea aplicațiilor necunoscute poate fi legitim pentru unele utilitare, dar merită revizuit dacă nu îți amintești să-l fi acordat.</p>
<p>Pe iPhone, verifică Settings &gt; Privacy &amp; Security pentru accesul aplicațiilor și Settings &gt; General &gt; VPN &amp; Device Management pentru profiluri pe care nu le recunoști. Telefoanele administrate de serviciu sau școală pot avea profiluri legitime; întreabă administratorul înainte să le ștergi. Verifică lista dispozitivelor asociate contului Apple și activează protecțiile disponibile pentru dispozitive pierdute.</p>
<h2>Dacă suspiciunea rămâne</h2>
<p>Actualizează sistemul, dezinstalează aplicațiile clar necunoscute și rulează instrumentele integrate de securitate. Dacă un cont trimite mesaje ori tranzacții fără acordul tău, securizează-l de pe alt dispozitiv și revocă sesiunile. Dacă cineva a instalat software de control la distanță, nu accesa banca de pe telefon până când nu ai îndrumare tehnică de încredere.</p>
<p>Resetarea la setările din fabrică poate elimina unele probleme software, dar șterge datele locale și nu rezolvă neapărat accesul atacatorului la conturi. Fă backup selectiv al fotografiilor și documentelor importante, evitând restaurarea automată a aplicațiilor pe care nu le-ai verificat. În cazurile cu risc financiar, amenințări ori acces persistent, contactează operatorul, producătorul sau un specialist înainte să resetezi dispozitivul.</p>
HTML,
            ],
            [
                'title' => 'Ai nevoie de antivirus pe Windows 11?',
                'slug' => 'ai-nevoie-antivirus-windows-11', 'category' => 'securitate-dispozitive',
                'excerpt' => 'Windows 11 include Windows Security și Microsoft Defender Antivirus, dar protecția depinde și de actualizări, setări și obiceiuri. Află ce acoperă instrumentele integrate și când poate fi util un produs suplimentar.',
                'seo_title' => 'Ai nevoie de antivirus pe Windows 11? Ghid practic',
                'meta_description' => 'Află ce protecție oferă Microsoft Defender în Windows 11, care sunt limitele și când are sens să alegi un antivirus suplimentar.',
                'focus_keyword' => 'antivirus Windows 11', 'secondary_keywords' => ['Microsoft Defender', 'Windows Security', 'protecție malware PC'], 'reading_time' => 6,
                'related' => ['10-reguli-simple-protejare-date-personale-online', 'ce-faci-dupa-link-phishing'],
                'sources' => [
                    ['Microsoft Support: protecția din Windows Security', 'https://support.microsoft.com/en-us/windows/security/windows-security/stay-protected-with-the-windows-security-app'],
                    ['CISA: actualizări și protecție de bază', 'https://www.cisa.gov/secure-our-world'],
                    ['ENISA: bune practici de igienă cibernetică', 'https://www.enisa.europa.eu/topics/cyber-hygiene'],
                ],
                'content' => <<<'HTML'
<p>Pentru mulți utilizatori, protecția integrată în Windows 11 este un punct de pornire rezonabil: aplicația Windows Security include Microsoft Defender Antivirus, firewall și alte controale. Nu există însă un produs care să elimine toate riscurile. Protecția depinde de actualizări, setările active, sursa aplicațiilor și modul în care reacționezi la mesaje și ferestre de autentificare suspecte.</p>
<h2>Ce face Microsoft Defender</h2>
<p>Defender poate verifica fișiere și aplicații, detecta comportamente cunoscute ca malițioase și oferi protecție în timp real atunci când setările sunt active. Windows Update livrează remedieri pentru sistem; aplicația Windows Security arată starea pentru virusuri, firewall și controale disponibile. Poți porni o scanare manuală din Virus &amp; threat protection atunci când ai un motiv concret, precum un fișier suspect.</p>
<p>Verifică în Windows Security dacă protecția în timp real și actualizările de securitate sunt active. Dacă instalezi un antivirus terț, Windows poate schimba produsul care gestionează protecția în timp real; evită să dezactivezi un control fără să știi ce îl înlocuiește. Două soluții care se suprapun pot crea notificări și performanță slabă fără să dubleze automat beneficiile.</p>
<h2>Când poate avea sens o soluție suplimentară</h2>
<p>Un produs suplimentar poate fi potrivit dacă ai cerințe de administrare pentru muncă, folosești funcții extra pe care le înțelegi sau ai nevoie de protecție pe mai multe dispozitive inclusă într-un abonament. Compară politica de confidențialitate, durata licenței, performanța și procedura de suport. Evită ferestrele care afirmă că au găsit zeci de probleme înainte să ruleze o scanare reală și ofertele care te presează să plătești imediat.</p>
<p>Un antivirus nu poate garanta că un site este legitim, nu te oprește întotdeauna să introduci parola pe o pagină falsă și nu anulează o plată pe care ai autorizat-o. Nici o alertă izolată nu este dovadă că un fișier a fost infectat; verifică numele alertei prin consola produsului și urmează documentația furnizorului.</p>
<h2>Obiceiurile care contează indiferent de produs</h2>
<ul><li>Instalează actualizările de Windows, browser și aplicații fără întârzieri mari.</li><li>Descarcă programe din site-ul producătorului sau magazinul oficial și refuză instalatoarele oferite în reclame neașteptate.</li><li>Folosește cont de utilizator standard pentru activitățile obișnuite când este practic.</li><li>Fă backup regulat pe un mediu separat sau într-un serviciu de încredere și verifică periodic că poți restaura fișierele.</li><li>Folosește parole unice și MFA pentru e-mail și conturi importante.</li></ul>
<p>Dacă suspectezi un fișier, nu îl mai deschide și scanează-l cu Windows Security. Dacă dispozitivul afișează comportamente persistente sau ai instalat software la cererea unui necunoscut, deconectează-l de la conturile financiare și cere ajutor tehnic de încredere. Alegerea antivirusului este doar o parte dintr-un plan care include actualizări, backup și verificarea atentă a solicitărilor.</p>
HTML,
            ],
            [
                'title' => 'Cum îți securizezi telefonul Android sau iPhone',
                'slug' => 'securizare-telefon-android-iphone', 'category' => 'securitate-dispozitive',
                'excerpt' => 'Un telefon bine securizat folosește un ecran blocat, actualizări, permisiuni limitate, backup și metode pregătite pentru pierdere sau furt. Pașii diferă între Android și iPhone, dar principiile sunt aceleași acasă.',
                'seo_title' => 'Cum securizezi telefonul Android sau iPhone',
                'meta_description' => 'Configurează blocarea ecranului, actualizările, permisiunile și backupul pentru a reduce riscurile de furt și acces neautorizat.',
                'focus_keyword' => 'securizare telefon', 'secondary_keywords' => ['securitate Android', 'securitate iPhone', 'telefon furat'], 'reading_time' => 7,
                'related' => ['cum-verifici-telefon-compromis', 'cum-verifici-permisiuni-aplicatii-telefon'],
                'sources' => [
                    ['Android: setările de confidențialitate și permisiuni', 'https://www.android.com/intl/en_nz/safety/privacy/'],
                    ['Apple: protecția dispozitivului furat pe iPhone', 'https://support.apple.com/guide/iphone/use-stolen-device-protection-iph17105538b/27/ios/27'],
                    ['CISA: actualizează software-ul și folosește MFA', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Telefonul conține e-mail, fotografii, conversații și adesea acces la conturi financiare. Îl poți proteja cu câteva setări simple, dar nu există o configurație care să prevină orice furt sau fraudă. Începe cu blocarea ecranului și actualizările; apoi limitează accesul aplicațiilor și pregătește pașii pentru pierdere sau furt.</p>
<h2>Blochează ecranul și protejează contul principal</h2>
<p>Alege un PIN sau o parolă pe care alte persoane nu o pot ghici din informații publice. Biometria poate fi comodă, însă păstrează un cod puternic ca metodă de rezervă. Setează blocarea automată după o perioadă scurtă și ascunde conținutul notificărilor sensibile pe ecranul blocat. Evită coduri precum 0000, anul nașterii sau un tipar vizibil.</p>
<p>Contul Google sau Apple controlează sincronizarea, localizarea și recuperarea telefonului. Activează MFA, păstrează e-mailul și numărul de recuperare actuale și salvează codurile de recuperare într-un loc separat și sigur. Verifică periodic lista dispozitivelor asociate; elimină doar dispozitivele pe care le poți identifica drept necunoscute.</p>
<h2>Actualizări și aplicații</h2>
<p>Activează actualizările automate ale sistemului și instalează aplicațiile din magazinele oficiale. Verifică numele dezvoltatorului, recenziile relevante și permisiunile înainte de instalare. O aplicație pentru lanternă nu are, de regulă, nevoie de contacte sau microfon pentru funcția sa. Revizuiește accesul la locație, cameră, microfon, fotografii și notificări; oferă permisiunea „doar când folosești aplicația” când aceasta corespunde nevoii.</p>
<p>Pe Android, caută Managerul de permisiuni, Play Protect și setările pentru instalarea din surse necunoscute; denumirile variază după producător. Pe iPhone, folosește Privacy &amp; Security și verifică aplicațiile conectate la Apple Account. Un profil de administrare pe un telefon de serviciu poate fi legitim, așa că întreabă administratorul înainte să îl elimini.</p>
<h2>Backup și protecție împotriva furtului</h2>
<p>Activează backupul pentru fotografii, contacte și documentele de care ai nevoie, apoi verifică dacă poți restaura datele pe un dispozitiv nou. Backupul sincronizat nu este singura copie; pentru documente importante păstrează și o copie separată. Activează serviciul oficial de localizare a dispozitivului și familiarizează-te cu opțiunile de blocare, mod pierdut și ștergere la distanță. Aceste funcții necesită de obicei configurare înainte să pierzi telefonul.</p>
<p>Apple oferă protecții suplimentare pentru situația în care iPhone-ul este furat, iar Android pune la dispoziție controale de confidențialitate și localizare care depind de versiune și producător. Verifică documentația dispozitivului tău; nu te baza pe un singur nume de meniu, deoarece interfața se poate schimba.</p>
<h2>În caz de pierdere sau furt</h2>
<p>Folosește serviciul oficial de localizare de pe alt dispozitiv și marchează telefonul ca pierdut dacă opțiunea există. Contactează operatorul pentru blocarea SIM-ului și schimbă parolele conturilor importante de pe un dispozitiv sigur. Anunță banca dacă telefonul deblocat sau aplicația bancară ar putea fi accesibilă. Nu merge singur la o locație indicată de hartă și nu elimina dispozitivul din cont dacă instrucțiunile oficiale spun că astfel ai dezactiva protecția anti-furt.</p>
HTML,
            ],
            [
                'title' => 'Cum creezi parole sigure și ușor de gestionat',
                'slug' => 'cum-creezi-parole-sigure-manager', 'category' => 'parole-autentificare',
                'excerpt' => 'Parolele lungi și unice reduc impactul unei breșe, iar un manager le poate crea și păstra în siguranță. Învață să folosești fraze-parolă fără să reutilizezi modele previzibile pentru conturile importante.',
                'seo_title' => 'Cum creezi parole sigure și unice',
                'meta_description' => 'Creează parole lungi și unice, folosește un manager de parole și evită reutilizarea și regulile de compoziție previzibile.',
                'focus_keyword' => 'parole sigure', 'secondary_keywords' => ['manager de parole', 'parole unice', 'frază-parolă'], 'reading_time' => 6,
                'related' => ['10-reguli-simple-protejare-date-personale-online', 'cum-recunosti-email-phishing'],
                'sources' => [
                    ['CISA: parole puternice și manageri de parole', 'https://www.cisa.gov/secure-our-world'],
                    ['NIST: cerințe și recomandări despre parole', 'https://pages.nist.gov/800-63-4/sp800-63b/authenticators/'],
                    ['ENISA: bune practici de igienă cibernetică', 'https://www.enisa.europa.eu/topics/cyber-hygiene'],
                ],
                'content' => <<<'HTML'
<p>O parolă bună este lungă, unică pentru contul respectiv și greu de ghicit. Lungimea și unicitatea contează mai mult decât înlocuirea previzibilă a literelor cu cifre. Dacă aceeași parolă este folosită la mai multe servicii, o breșă la unul dintre ele poate permite încercări automate pe celelalte. Un manager de parole rezolvă problema memorării și poate genera parole aleatorii pentru fiecare cont.</p>
<h2>Generează parole unice</h2>
<p>Folosește un manager cu reputație bună, protejează-l printr-o parolă principală puternică și activează MFA dacă serviciul oferă această opțiune. Alege o parolă principală pe care nu o folosești nicăieri altundeva; o frază lungă alcătuită din cuvinte alese aleatoriu poate fi mai ușor de reținut decât un șir scurt cu simboluri. Nu folosi un citat celebru, numele familiei, data nașterii sau o propoziție pe care ai publicat-o.</p>
<p>Lasă generatorul managerului să creeze parole diferite pentru site-uri și aplicații. Lungimea acceptată diferă între servicii, așa că generatorul poate oferi și o variantă compatibilă. Dacă un site cere să schimbi periodic parola fără să existe un indiciu de compromitere, nu inventa rotații minore; schimbă parola când este expusă, reutilizată, trimisă cuiva sau când serviciul te notifică despre un incident relevant.</p>
<h2>Fraze-parolă și exemple</h2>
<p>O frază-parolă este o combinație mai lungă de cuvinte. Pentru un cont important, selectează cuvintele aleatoriu cu instrumentul managerului, nu dintr-o expresie ușor de asociat cu tine. Exemple precum <code>pisica2026!</code> sau <code>Parola123</code> sunt doar avertismente despre tipare slabe și <strong>nu trebuie folosite</strong>. Atacatorii testează dicționare, substituții comune și parole scurse înainte să încerce combinații complet aleatorii.</p>
<h2>Folosește managerul în siguranță</h2>
<ul><li>Instalează aplicația și extensia doar din sursa oficială și verifică atent domeniul înainte să completezi automat o parolă.</li><li>Activează MFA și păstrează codul de recuperare offline într-un loc sigur.</li><li>Nu exporta parolele într-un fișier neprotejat și șterge copiile temporare după import.</li><li>Verifică parolele reutilizate sau compromise în funcțiile managerului, fără să trimiți parolele către site-uri necunoscute.</li><li>Planifică accesul de recuperare dacă pierzi telefonul sau cheia de criptare.</li></ul>
<p>Un manager nu protejează împotriva unei pagini de phishing dacă introduci manual datele acolo; completarea automată care refuză un domeniu diferit este însă un semnal practic. Pentru servicii compatibile, ia în calcul o passkey, care nu presupune memorarea unei parole și este legată de domeniul serviciului. Nu partaja parola principală prin e-mail sau chat.</p>
HTML,
            ],
            [
                'title' => 'Ce este autentificarea în doi pași și cum o activezi',
                'slug' => 'autentificare-doi-pasi-cum-activezi', 'category' => 'parole-autentificare',
                'excerpt' => 'Autentificarea în doi pași adaugă o verificare peste parolă, dar metodele oferă niveluri diferite de protecție. Află cum alegi între aplicații, chei de securitate, passkeys și SMS și cum îți pregătești recuperarea.',
                'seo_title' => 'Autentificarea în doi pași: activare și metode',
                'meta_description' => 'Află ce înseamnă 2FA și MFA, cum compari aplicațiile, SMS-ul și cheile de securitate și cum păstrezi codurile de recuperare.',
                'focus_keyword' => 'autentificare în doi pași', 'secondary_keywords' => ['2FA', 'MFA', 'cod de recuperare'], 'reading_time' => 7,
                'related' => ['cum-creezi-parole-sigure-manager', 'cum-recunosti-email-phishing'],
                'sources' => [
                    ['Google: activează verificarea în doi pași', 'https://support.google.com/accounts/answer/185839?hl=ro'],
                    ['ENISA: recomandări pentru autentificare', 'https://www.enisa.europa.eu/topics/cyber-hygiene'],
                    ['NIST: tipuri de autentificatori și coduri', 'https://pages.nist.gov/800-63-4/sp800-63b/authenticators/'],
                ],
                'content' => <<<'HTML'
<p>Autentificarea în doi pași (2FA) cere două verificări, iar autentificarea multifactor (MFA) este termenul mai larg pentru folosirea a cel puțin două categorii diferite de factori: ceva ce știi, ceva ce ai sau ceva ce ești. Dacă cineva obține parola, a doua verificare poate împiedica accesul. Protecția depinde însă de metodă și de cum reacționezi la solicitări necerute.</p>
<h2>Compară metodele</h2>
<p><strong>Passkeys și cheile de securitate</strong> folosesc autentificare criptografică legată de serviciu și sunt printre cele mai rezistente metode la pagini false. Passkey-ul poate fi păstrat pe telefon, calculator sau într-un manager compatibil; cheia fizică trebuie protejată ca un obiect important. Configurează o metodă de recuperare înainte să pierzi dispozitivul.</p>
<p><strong>Aplicațiile de autentificare</strong> generează coduri pe dispozitiv și nu depind de livrarea unui SMS. Codurile sunt totuși sensibile: o pagină de phishing poate cere codul imediat și îl poate folosi înainte să expire. Introdu codul doar pe site-ul accesat independent și nu-l dicta unei persoane care te sună.</p>
<p><strong>SMS-ul</strong> este mai bun decât doar parola în multe situații, dar poate fi expus prin redirecționarea mesajelor, schimbarea SIM-ului sau manipularea operatorului. Dacă serviciul oferă o metodă mai rezistentă, prefer-o pentru e-mail, administrarea conturilor și bani. Metoda adecvată depinde de ce suportă contul și de riscul tău; activează orice MFA oferită în loc să rămâi doar cu o parolă reutilizată.</p>
<h2>Cum o activezi fără să te blochezi</h2>
<ol><li>Deschide aplicația sau site-ul oficial tastând singur adresa și intră la Securitate.</li><li>Alege metoda preferată și urmează pașii din serviciu; denumirile meniurilor se pot schimba.</li><li>Adaugă o a doua metodă de rezervă dacă serviciul permite, de exemplu o cheie suplimentară sau o aplicație pe alt dispozitiv.</li><li>Salvează codurile de recuperare într-un manager sau într-un loc offline separat de telefon.</li><li>Testează metoda pe un dispozitiv de încredere înainte să închizi toate sesiunile.</li></ol>
<h2>Solicitări neașteptate și recuperare</h2>
<p>Nu aproba o notificare de autentificare pe care nu ai pornit-o. Repetarea cererilor poate fi o tentativă de a te epuiza până apeși „Aprobă”. Respinge solicitarea, schimbă parola dacă ai motive să crezi că a fost expusă și verifică activitatea recentă. Păstrează codurile de rezervă private: orice persoană care le vede poate încerca recuperarea contului.</p>
<p>MFA nu oprește toate atacurile. Poți fi păcălit să aprobi o sesiune sau să instalezi malware; combin-o cu parole unice, actualizări și accesarea serviciilor prin aplicația oficială. Înainte să schimbi telefonul, transferă metodele de autentificare și verifică recuperarea conturilor esențiale.</p>
HTML,
            ],
            [
                'title' => 'Ce sunt passkeys și de ce sunt mai sigure decât parolele',
                'slug' => 'ce-sunt-passkeys-parole', 'category' => 'parole-autentificare',
                'excerpt' => 'Passkeys sunt credențiale criptografice păstrate pe dispozitiv sau sincronizate printr-un manager compatibil. Ele pot simplifica autentificarea și reduc riscul ca o parolă să fie introdusă pe un site de phishing.',
                'seo_title' => 'Ce sunt passkeys și cum înlocuiesc parolele',
                'meta_description' => 'Află cum funcționează passkeys, de ce sunt rezistente la phishing și ce să verifici pentru recuperare și compatibilitate.',
                'focus_keyword' => 'passkeys', 'secondary_keywords' => ['chei de acces', 'autentificare fără parolă', 'FIDO2'], 'reading_time' => 6,
                'related' => ['autentificare-doi-pasi-cum-activezi', 'cum-creezi-parole-sigure-manager'],
                'sources' => [
                    ['Google: autentifică-te cu o passkey', 'https://support.google.com/accounts/answer/13548313?hl=ro'],
                    ['NIST: autentificatori criptografici și rezistență la phishing', 'https://pages.nist.gov/800-63-4/sp800-63b/authenticators/'],
                    ['ENISA: passkeys și autentificare rezistentă la phishing', 'https://www.enisa.europa.eu/topics/cyber-hygiene'],
                ],
                'content' => <<<'HTML'
<p>O passkey este o credențială digitală bazată pe o pereche de chei criptografice. Serviciul păstrează o cheie publică, iar cheia privată rămâne pe dispozitivul tău sau într-un manager de passkeys. La autentificare, deblochezi dispozitivul cu PIN, amprentă ori recunoaștere facială; cheia semnează o provocare fără să trimită o parolă reutilizabilă către site.</p>
<h2>De ce ajută împotriva phishingului</h2>
<p>Passkey-ul este asociat cu domeniul serviciului. Dacă ajungi pe un domeniu care doar seamănă cu cel real, browserul nu ar trebui să ofere aceeași credențială pentru site-ul legitim. Asta reduce dependența de atenția utilizatorului și de memorarea domeniilor. Biometria este folosită local pentru deblocare; conform documentației Google, datele biometrice rămân pe dispozitiv și nu sunt trimise companiei.</p>
<p>„Mai sigură” nu înseamnă invulnerabilă. Dacă cineva îți poate debloca dispozitivul sau intră în contul de sincronizare care păstrează passkeys, riscul se schimbă. Protejează contul principal cu MFA, folosește blocare puternică și nu crea passkeys pe un calculator partajat. Pentru conturi critice, păstrează o metodă de recuperare suplimentară pe care o poți accesa.</p>
<h2>Compatibilitate și sincronizare</h2>
<p>Suportul depinde de sistemul de operare, browser, serviciu și manager. Unele passkeys pot fi sincronizate între dispozitive prin ecosistemul Apple, Google sau un manager compatibil; altele sunt legate de o cheie hardware sau de un singur dispozitiv. Nu presupune că toate serviciile permit același flux. Consultă documentația contului și verifică ce opțiuni sunt disponibile înainte să elimini parola ori alt factor.</p>
<h2>Cum începi</h2>
<ol><li>Actualizează telefonul, calculatorul și browserul și activează blocarea ecranului.</li><li>Accesează manual serviciul oficial și caută în setările de securitate „Passkeys” sau „Chei de acces”.</li><li>Urmează instrucțiunile de creare și confirmă că dispozitivul sau managerul selectat este al tău.</li><li>Adaugă o metodă de recuperare și testează autentificarea pe un alt dispozitiv, dacă este posibil.</li><li>Revizuiește lista passkeys periodic și elimină credențialele pentru dispozitive pe care nu le mai deții.</li></ol>
<p>Passkeys nu sunt același lucru cu un cod primit prin SMS și nici cu parola managerului. Învață cum recuperezi accesul dacă telefonul se pierde. O passkey poate fi o alegere foarte bună pentru conturi compatibile, iar managerul de parole rămâne util pentru serviciile care nu au implementat încă această metodă.</p>
HTML,
            ],
            [
                'title' => 'Ce știe Google despre tine și cum controlezi datele colectate',
                'slug' => 'ce-stie-google-despre-tine-control-date', 'category' => 'confidentialitate',
                'excerpt' => 'Datele asociate contului Google depind de produsele folosite și de setările active. Revizuiește istoricul, locația, personalizarea și aplicațiile conectate și descarcă informațiile înainte să ștergi ceva din contul tău personal.',
                'seo_title' => 'Ce date Google salvează și cum le controlezi',
                'meta_description' => 'Revizuiește activitatea, istoricul locațiilor, personalizarea reclamelor și datele contului Google din Privacy Checkup și My Activity.',
                'focus_keyword' => 'date Google', 'secondary_keywords' => ['Google My Activity', 'istoric locații Google', 'setări confidențialitate Google'], 'reading_time' => 6,
                'related' => ['date-personale-nu-posta-online', 'verificare-email-compromis-brese-date'],
                'sources' => [
                    ['Google: găsește, controlează și șterge datele din cont', 'https://support.google.com/accounts/answer/7660719?hl=ro'],
                    ['Google: cum funcționează setările de confidențialitate', 'https://support.google.com/accounts/answer/10400210?hl=ro'],
                    ['Google: descarcă o copie a datelor tale', 'https://takeout.google.com/'],
                ],
                'content' => <<<'HTML'
<p>Ce informații sunt salvate în contul Google depinde de serviciile folosite și de setările active. Istoricul căutărilor, activitatea din aplicații, clipurile vizionate, locația și datele de profil pot apărea în controale diferite. Nu există o singură pagină care să reprezinte toate copiile de date; verifică și browserul, dispozitivul și setările fiecărui produs.</p>
<h2>Începe cu Privacy Checkup și My Activity</h2>
<p>Intră în contul Google tastând manual myaccount.google.com și deschide Privacy Checkup și My Activity. Revizuiește Web &amp; App Activity, YouTube History și Timeline acolo unde sunt disponibile. Poți opri salvarea anumitor tipuri de activitate, seta ștergerea automată ori șterge un interval. Setările pot avea denumiri sau comportamente noi pe măsură ce Google le actualizează; citește explicația afișată pentru contul tău înainte să schimbi opțiunile.</p>
<p>Istoricul locațiilor nu este singura sursă posibilă de aproximare a poziției. Aplicațiile pot folosi permisiunea dispozitivului, iar serviciile web pot deduce o zonă aproximativă din adresa IP. Oprirea unei singure setări nu șterge automat orice dată asociată tuturor produselor.</p>
<h2>Personalizare și reclame</h2>
<p>În setările de personalizare poți controla anumite informații folosite pentru recomandări sau reclame personalizate. Oprirea personalizării nu înseamnă neapărat că nu vei mai vedea reclame și nici nu elimină datele deja salvate. Setările sunt separate de istoricul pe care îl gestionezi în My Activity și de datele păstrate în Gmail, Drive sau Photos.</p>
<h2>Descarcă înainte să ștergi</h2>
<p>Dacă vrei o copie, folosește Google Takeout pentru a selecta produsele și arhiva pe care vrei să o descarci. Verifică unde salvezi arhiva: poate conține documente, fotografii și conversații sensibile. Păstreaz-o criptată și șterge copia după ce nu mai ai nevoie de ea. Înainte să ștergi un serviciu sau contul, citește ce se pierde și dacă există o perioadă de recuperare.</p>
<h2>Controale utile pe telefon</h2>
<p>Revizuiește aplicațiile conectate la cont și elimină accesul celor pe care nu le mai folosești. Verifică permisiunea de locație direct în sistemul de operare; un site poate primi locație aproximativă din IP chiar dacă nu primește coordonatele GPS. Pentru dispozitive partajate, folosește profiluri separate și deconectează-te când termini.</p>
<p>Revizuirea confidențialității este un proces, nu o dovadă că toate datele au dispărut. Serviciile au nevoie de unele informații pentru funcționare, securitate sau obligații aplicabile. Alege setările în funcție de ce funcții folosești, exportă datele importante și verifică anual controalele, mai ales după actualizări ale interfeței.</p>
HTML,
            ],
            [
                'title' => 'Cum reduci urmărirea activității tale pe internet',
                'slug' => 'cum-reduci-urmarirea-pe-internet', 'category' => 'confidentialitate',
                'excerpt' => 'Site-urile pot recunoaște vizite prin cookies, trackere și alte tehnici, în funcție de browser și setări. Limitează urmărirea prin controale de confidențialitate, dar nu confunda modul privat cu anonimatul în toate browserele folosite.',
                'seo_title' => 'Cum reduci trackingul și urmărirea online',
                'meta_description' => 'Limitează cookies și trackere prin setările browserului și extensii reputabile și află ce nu ascunde modul de navigare privată.',
                'focus_keyword' => 'urmărire online', 'secondary_keywords' => ['cookies terțe', 'trackere web', 'navigare privată'], 'reading_time' => 6,
                'related' => ['ce-stie-google-despre-tine-control-date', 'date-personale-nu-posta-online'],
                'sources' => [
                    ['Mozilla: modul de navigare privată și limitele sale', 'https://www.mozilla.org/en-US/firefox/browsers/incognito-browser/'],
                    ['Firefox Help: protecția împotriva urmăririi', 'https://support.mozilla.org/en-US/kb/private-browsing-use-firefox-without-history'],
                    ['Google: controale pentru datele și activitatea contului', 'https://support.google.com/accounts/answer/7660719?hl=ro'],
                ],
                'content' => <<<'HTML'
<p>Urmărirea online poate folosi cookies, identificatori din browser, pixeli invizibili, conturi conectate și informații pe care le introduci în formulare. Unele tehnologii păstrează autentificarea sau coșul de cumpărături; altele ajută companiile să măsoare vizitele pe mai multe site-uri. Nu există un buton care să oprească toate formele de colectare fără să afecteze unele funcții.</p>
<h2>Controlează cookies și setările browserului</h2>
<p>În setările browserului poți bloca sau limita cookies de la terți, șterge datele site-urilor și folosi protecția integrată împotriva trackerelor. Dacă un site important nu funcționează, acordă o excepție doar acelui domeniu și revizuiește-o ulterior. Ștergerea cookies te poate deconecta și nu elimină înregistrările păstrate de site-uri pe serverele lor.</p>
<p>Firefox, Chrome, Edge și Safari oferă controale diferite; actualizează browserul și consultă documentația producătorului pentru versiunea instalată. Extensiile de confidențialitate pot bloca conținut, dar pot citi datele paginilor în funcție de permisiunile acordate. Instalează puține, din magazinul oficial, verifică autorul, actualizările și accesul solicitat și elimină extensiile nefolosite.</p>
<h2>Modul privat nu te face anonim</h2>
<p>Fereastra privată reduce ce rămâne în istoricul local după ce o închizi și poate separa cookies de sesiunea normală. Ea nu ascunde activitatea față de site-ul accesat, angajatorul care administrează rețeaua, furnizorul de internet sau serviciile în care te autentifici. Descărcările și semnele de carte pot rămâne pe dispozitiv; site-ul poate folosi contul, IP-ul sau alte semnale pentru a recunoaște sesiunea.</p>
<p>Un VPN schimbă cine vede conexiunea de rețea, dar nu șterge cookies și nu te face invizibil pentru conturile în care te-ai conectat. Blochează trackerele la nivel de browser și folosește conturi separate dacă vrei să reduci legarea activităților personale de cele profesionale.</p>
<h2>Pași simpli, fără promisiuni absolute</h2>
<ol><li>Revizuiește setările cookies și trackerelor o dată pe trimestru.</li><li>Limitează permisiunile de locație, notificări și cameră pentru site-uri.</li><li>Folosește un profil de browser distinct pentru activități diferite.</li><li>Deconectează-te de pe conturi când nu ai nevoie de personalizare continuă.</li><li>Nu instala extensii care promit anonimat total și cer acces la toate site-urile fără explicație.</li></ol>
<p>Reducerea urmăririi este un compromis între comoditate și control. Alege setările pe care le poți păstra, citește notificările de consimțământ și caută politica de confidențialitate pentru serviciile care primesc date sensibile.</p>
HTML,
            ],
            [
                'title' => 'Telefonul tău te ascultă? Ce este mit și ce este realitate',
                'slug' => 'telefonul-te-asculta-mit-realitate', 'category' => 'confidentialitate',
                'excerpt' => 'Reclamele care par să răspundă unei conversații nu dovedesc singure că telefonul a înregistrat-o. Vezi ce pot face microfonul, asistenții vocali și sistemele de publicitate și cum verifici permisiunile pe Android și iPhone.',
                'seo_title' => 'Telefonul te ascultă? Mituri despre reclame și microfon',
                'meta_description' => 'De ce vezi reclame despre lucruri pe care le-ai discutat? Află cum funcționează publicitatea personalizată și cum îți protejezi confidențialitatea.',
                'focus_keyword' => 'telefonul mă ascultă', 'secondary_keywords' => ['telefonul ascultă conversațiile', 'reclame personalizate', 'microfon Android', 'microfon iPhone', 'confidențialitate telefon'],
                'reading_time' => 8, 'image' => 'telefon-microfon-privacy.jpg',
                'related' => ['cum-reduci-urmarirea-pe-internet', 'ce-stie-google-despre-tine-control-date', 'cum-verifici-permisiuni-aplicatii-telefon', 'securizare-telefon-android-iphone', 'date-personale-nu-posta-online'],
                'sources' => [
                    ['Android Help: schimbarea permisiunilor aplicațiilor', 'https://support.google.com/android/answer/9431959?hl=ro'],
                    ['Android Help: verificarea istoricului din Privacy Dashboard', 'https://support.google.com/android/answer/13530434?hl=ro'],
                    ['Apple Support: controlul accesului la microfon pe iPhone', 'https://support.apple.com/en-euro/guide/iphone/iph6e7d349d1/26/ios/26'],
                    ['Apple Support: raportul de confidențialitate al aplicațiilor', 'https://support.apple.com/en-ie/102188'],
                    ['Google My Ad Center: controlul reclamelor personalizate', 'https://support.google.com/My-Ad-Center-Help/answer/12155154?hl=ro'],
                    ['Android Help: setările de publicitate și Topics', 'https://support.google.com/android/answer/13720755?hl=ro'],
                    ['Apple Support: controale pentru urmărirea între aplicații', 'https://support.apple.com/en-ie/guide/iphone/iph4f4cbd242/ios'],
                ],
                'content' => <<<'HTML'
<p>Poate ai vorbit despre un produs, iar mai târziu ai văzut o reclamă pentru el. Experiența pare personală și poate ridica întrebarea: „Telefonul mă ascultă?”. O reclamă potrivită nu dovedește însă că microfonul a înregistrat conversația. Publicitatea poate folosi căutări, pagini vizitate, aplicații, interacțiuni, locație aproximativă, informații de cont sau activitatea altor dispozitive conectate. Uneori explicația este mai simplă: ai remarcat reclama tocmai fiindcă subiectul era recent în mintea ta.</p>
<h2>Poate un smartphone să acceseze conversațiile?</h2>
<p>Da, hardware-ul telefonului poate capta sunet, iar o aplicație cu permisiunea potrivită poate folosi microfonul în situațiile permise de sistem și de setările dispozitivului. Apelurile video, mesajele vocale, dictarea și înregistrarea audio sunt exemple obișnuite. Asistenții vocali pot aștepta o comandă de activare; implementarea și prelucrarea diferă între produse. Nu presupune că orice funcție este procesată numai local sau, invers, că fiecare conversație este transmisă în permanență către un server. Consultă setările și documentația asistentului pe care îl folosești.</p>
<p>Accesul la microfon depinde de permisiunile sistemului, versiunea Android ori iOS și comportamentul aplicației. Sistemele moderne oferă controale și indicatori, dar denumirile și nivelul de detaliu pot varia. Un indicator arată activitate a senzorului; singur, el nu spune ce conținut a fost păstrat sau trimis. Dacă observi acces neașteptat, verifică raportul sistemului, aplicația și momentul înainte de a trage concluzii.</p>

<h2>Microfon, asistent vocal și publicitate: trei lucruri diferite</h2>
<p><strong>Permisiunea pentru microfon</strong> este o decizie a sistemului de operare care permite unei aplicații să ceară acces la intrarea audio. Permisiunea poate fi refuzată sau retrasă. Ea nu este o dovadă că aplicația folosește microfonul în orice moment și nici că înregistrările sunt folosite pentru reclame.</p>
<p><strong>Asistentul vocal</strong> răspunde unei comenzi, precum activarea printr-o frază sau apăsarea unui buton. Un dispozitiv poate analiza un semnal audio pentru a detecta comanda, iar un serviciu poate procesa cererea după activare, în funcție de produs și setări. Verifică istoricul comenzilor și controalele vocale din contul asistentului; oprește activarea vocală dacă nu o folosești.</p>
<p><strong>Publicitatea personalizată</strong> poate folosi activitatea asociată conturilor, interesele selectate, istoricul unor interacțiuni, informații furnizate și semnale din site-uri sau aplicații, după setările și politicile aplicabile. De exemplu, Google descrie folosirea activității din servicii și a preferințelor din My Ad Center pentru anumite reclame. Alte platforme și aplicații au propriile sisteme și controale. Aceste practici documentate nu sunt același lucru cu înregistrarea conversațiilor ambientale.</p>

<h2>Ce dovezi avem despre ascultarea pentru reclame?</h2>
<p>Faptul că o aplicație cere permisiune la microfon arată că poate avea nevoie de funcția audio; nu demonstrează că înregistrează continuu sau că folosește sunetul pentru a selecta reclame. În mod similar, o reclamă potrivită după o conversație nu identifică de una singură sursa semnalului. Pentru o concluzie tehnică ar fi nevoie de date verificabile despre accesul la senzor, traficul transmis și prelucrarea făcută de aplicație, nu doar de o coincidență observată.</p>
<p>Raportul de confidențialitate al iPhone-ului și Privacy Dashboard pe Android pot indica ce aplicație a accesat microfonul și când, pe versiunile care oferă aceste funcții. Un acces în timpul unui apel este de așteptat; unul neașteptat merită investigat. Jurnalul nu îți spune automat ce s-a întâmplat cu sunetul după acces și nici nu identifică singur motivul unei reclame.</p>
<p>Nu există temei să afirmăm ca fapt general că Facebook, Google, Apple sau orice alt furnizor înregistrează în secret toate conversațiile pentru reclame. Asta nu face imposibilă orice utilizare abuzivă a microfonului de către o aplicație. Dacă ai o suspiciune concretă, notează ora, aplicația și indicatorul observat, revizuiește permisiunile și raportează comportamentul prin canalul platformei sau al magazinului de aplicații.</p>

<h2>Cum funcționează de fapt reclamele personalizate</h2>
<p>Platformele pot folosi activitatea contului pentru a estima ce subiecte te interesează: căutări, clipuri vizionate, reclame cu care ai interacționat, informații de profil sau activitate salvată din servicii partenere, în funcție de cont și de setări. Un site poate folosi propriile cookies și instrumente de măsurare. Aplicațiile pot avea identificatori publicitari sau controale pentru subiecte de interes. O companie poate afișa reclame contextuale bazate pe pagina pe care o citești, fără să știe ce ai discutat acasă.</p>
<p>Cookie-urile și tehnologii similare pot ajuta la menținerea unei sesiuni, măsurarea vizitelor sau personalizarea conținutului. Dacă ești autentificat, activitatea poate fi asociată contului în limitele politicilor și setărilor serviciului. Unele aplicații schimbă semnale prin SDK-uri de publicitate sau analiză; politicile magazinului și ale sistemului limitează anumite practici, dar nu toate aplicațiile sunt identice. Citește etichetele de confidențialitate, permisiunile și explicațiile dezvoltatorului.</p>
<h2>De ce o reclamă poate părea suspect de exactă?</h2>
<p>În primul rând, uităm zecile de reclame care nu au legătură cu discuțiile noastre și le ținem minte pe cele care se potrivesc. Aceasta este o explicație posibilă, nu o dovadă despre cazul fiecăruia. În al doilea rând, poate ai căutat produsul, ai urmărit un clip, ai vizitat un magazin online sau ai discutat într-o aplicație despre care nu îți amintești. Uneori recomandarea este contextuală: reclama apare pe o pagină despre același subiect.</p>

<h2>Cum verifici permisiunea de microfon pe Android</h2>
<ol>
<li>Deschide <strong>Setări</strong> și caută „Permisiuni”, „Microfon” sau „Privacy Dashboard”. Pe multe dispozitive, calea este <strong>Securitate și confidențialitate → Confidențialitate → Manager de permisiuni → Microfon</strong>; pe altele poate fi <strong>Aplicații → [numele aplicației] → Permisiuni</strong>.</li>
<li>Revizuiește aplicațiile cu acces permis. Pentru fiecare, întreabă-te dacă funcția are nevoie de microfon, de exemplu apeluri, dictare sau filmare.</li>
<li>Deschide Privacy Dashboard pentru istoricul accesărilor dacă versiunea ta de Android îl include. Google indică faptul că intervalul vizibil diferă în funcție de versiune.</li>
<li>Schimbă accesul la „Permite doar când folosești aplicația”, „Întreabă de fiecare dată” sau „Nu permite”, după opțiunile oferite.</li>
<li>Pe unele telefoane poți opri accesul general la microfon din controalele de confidențialitate rapide. Meniurile depind de producător și de versiunea sistemului.</li>
</ol>
<p>Ghidul nostru despre <a href="/articole/cum-verifici-permisiuni-aplicatii-telefon">verificarea permisiunilor aplicațiilor</a> explică și alte controale utile. Dacă dezactivezi microfonul pentru o aplicație de apeluri, funcția audio nu va merge până nu îi redai accesul.</p>

<h2>Cum verifici permisiunea de microfon pe iPhone</h2>
<ol>
<li>Deschide <strong>Configurări → Intimitate și securitate → Microfon</strong> și revizuiește aplicațiile care au cerut acces. Poți opri permisiunea separat pentru fiecare aplicație.</li>
<li>Un indicator portocaliu în bara de stare arată că microfonul este folosit; indicatorul verde apare când este folosită camera, singură sau împreună cu microfonul. Deschide Centrul de control pentru a vedea ce aplicație a folosit recent senzorul.</li>
<li>Activează <strong>Raportul de confidențialitate al aplicațiilor</strong> în Configurări → Intimitate și securitate. Acesta începe să colecteze date după activare și poate afișa accesul la senzori și activitatea de rețea recentă.</li>
<li>În <strong>Configurări → Intimitate și securitate → Urmărire</strong>, revizuiește ce aplicații au cerut permisiunea de urmărire între aplicații și site-uri ale altor companii. Poți modifica deciziile sau opri solicitările viitoare.</li>
</ol>
<p>Indicatorul confirmă accesarea senzorului, nu motivul; compară ora cu funcția folosită și oprește permisiunea dacă accesul rămâne neexplicat.</p>

<h2>Dezactivează accesul inutil, nu funcțiile de care depinzi</h2>
<p>Retrage microfonul aplicațiilor care nu au nevoie de audio și testează dacă restul funcțiilor continuă să meargă. Dezinstalează aplicații pe care nu le mai folosești și instalează programe doar din magazinele oficiale. Verifică numele dezvoltatorului, actualizările și politica de confidențialitate. Evită aplicațiile care cer microfon pentru o funcție care nu are legătură cu sunetul și nu instala fișiere de configurare trimise de necunoscuți.</p>
<p>Acordă acces temporar dacă sistemul oferă opțiunea. Retragerea permisiunii nu șterge înregistrările deja încărcate; verifică setările contului și politica furnizorului pentru acestea.</p>

<h2>Redu urmărirea reclamelor</h2>
<ul>
<li>Revizuiește setările de reclame din conturile folosite, precum My Ad Center pentru serviciile Google, și oprește personalizarea dacă nu o dorești. Vei putea vedea în continuare reclame contextuale sau prin alte rețele.</li>
<li>Pe Android, verifică setările de confidențialitate pentru reclame și identificatorul publicitar; pe iPhone, gestionează solicitările din secțiunea Urmărire. Denumirile și opțiunile depind de versiune și cont.</li>
<li>În browser, limitează cookies de la terți, șterge datele site-urilor pe care nu le mai folosești și verifică protecția împotriva trackerelor. Așteaptă-te ca unele sesiuni să se închidă.</li>
<li>Refuză permisiunile de locație și contacte când nu sunt necesare. Oprește sincronizarea pe dispozitive partajate și deconectează conturile pe care nu le folosești.</li>
<li>Folosește parole unice și verifică aplicațiile terțe conectate la conturile tale. Pentru mai multe măsuri, citește ghidul nostru despre <a href="/articole/cum-reduci-urmarirea-pe-internet">reducerea urmăririi online</a>.</li>
</ul>

<h2>Listă practică pentru confidențialitatea telefonului</h2>
<ul>
<li>Revizuiește permisiunea de microfon și elimină accesul aplicațiilor care nu îl folosesc justificat.</li>
<li>Verifică indicatorii de microfon și istoricul senzorilor după ce instalezi aplicații noi.</li>
<li>Oprește asistentul vocal sau activarea la comandă dacă nu folosești funcția.</li>
<li>Controlează personalizarea reclamelor în conturi, sistemul de operare și aplicațiile folosite.</li>
<li>Limitează cookies, identificatorii publicitari, locația și accesul la contacte în funcție de nevoie.</li>
<li>Actualizează sistemul și aplicațiile și elimină programele necunoscute.</li>
<li>Verifică periodic politica de confidențialitate și opțiunile după actualizări importante.</li>
</ul>

<h2>Întrebări frecvente</h2>
<h3>Telefonul ascultă mereu conversațiile pentru reclame?</h3>
<p>O reclamă potrivită nu dovedește că telefonul a înregistrat conversația. Sistemele și aplicațiile pot accesa microfonul în condiții permise, dar nu avem bază să afirmăm că toate conversațiile sunt înregistrate în mod obișnuit pentru reclame. Verifică accesul la senzor și setările platformei.</p>
<h3>Pot opri complet accesul aplicațiilor la microfon?</h3>
<p>Poți retrage permisiunea fiecărei aplicații și, pe unele versiuni Android, poți opri global microfonul. Apelurile, mesajele vocale și filmarea nu vor putea capta audio cât timp accesul este blocat.</p>
<h3>Ce înseamnă punctul portocaliu de pe iPhone?</h3>
<p>El indică folosirea microfonului. Punctul verde indică folosirea camerei, singură sau împreună cu microfonul. Centrul de control poate arăta aplicația care a folosit recent senzorul.</p>
<h3>De ce văd reclame despre un produs pe care doar l-am menționat?</h3>
<p>Pot exista mai multe explicații: activitate online anterioară, reclame contextuale, conturi ori dispozitive partajate, interese estimate sau simpla atenție sporită față de subiect. Reclama singură nu identifică sursa semnalului.</p>
<h3>Dacă opresc reclamele personalizate, nu mai văd reclame?</h3>
<p>Nu. De regulă vei vedea în continuare reclame contextuale sau reclame oferite de alte platforme, însă anumite activități și preferințe nu vor mai fi folosite de serviciul respectiv pentru personalizare, în limitele descrise în setările sale.</p>
<h2>Concluzie</h2>
<p>Microfonul este o funcție reală, iar aplicațiile pot primi acces când le acorzi permisiunea, dar o reclamă surprinzător de exactă nu demonstrează că ai fost ascultat. Activitatea de cont, istoricul de navigare, cookies, identificatorii publicitari și contextul oferă suficiente explicații posibile pentru personalizare. Verifică permisiunile pe Android sau iPhone, folosește rapoartele de confidențialitate și ajustează separat setările de reclame ale fiecărei platforme. Dacă observi acces neașteptat, investighează aplicația și datele disponibile înainte să concluzionezi ce s-a întâmplat.</p>
HTML,
            ],
            [
                'title' => 'Este sigur să folosești un VPN? Avantaje și limite',
                'slug' => 'vpn-sigur-avantaje-si-limite', 'category' => 'confidentialitate',
                'excerpt' => 'Un VPN criptează tunelul dintre dispozitiv și furnizorul VPN și poate limita ce observă rețeaua locală. Nu ascunde activitatea față de site-urile unde te autentifici și mută o parte din încredere către operatorul VPN.',
                'seo_title' => 'VPN: avantaje, riscuri și limite reale',
                'meta_description' => 'Află ce protejează un VPN, ce informații poate vedea furnizorul și cum alegi serviciul fără promisiuni de anonimat total.',
                'focus_keyword' => 'VPN sigur', 'secondary_keywords' => ['VPN avantaje', 'confidențialitate VPN', 'furnizor VPN'], 'reading_time' => 6,
                'related' => ['cum-reduci-urmarirea-pe-internet', 'ce-stie-google-despre-tine-control-date'],
                'sources' => [
                    ['National Counterintelligence and Security Center: VPNs și limite', 'https://www.dni.gov/files/NCSC/documents/campaign/DoD_IAPM_Guide_March_2021.pdf'],
                    ['Mozilla: navigarea privată nu oferă anonimat complet', 'https://www.mozilla.org/en-US/firefox/browsers/incognito-browser/'],
                    ['CISA: bune practici pentru securizarea dispozitivelor', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Un VPN (rețea privată virtuală) creează un tunel criptat între dispozitiv și serverul furnizorului VPN. Pe o rețea Wi-Fi publică, acest lucru poate limita ce poate citi administratorul rețelei despre traficul tău. Site-urile văd de obicei adresa IP a serverului VPN, nu pe cea a conexiunii tale, dar serviciile în care te autentifici te pot identifica în continuare prin cont.</p>
<h2>Ce nu face VPN-ul</h2>
<p>VPN-ul nu te protejează de un site de phishing, un fișier malițios, o parolă furată sau o plată autorizată de tine. Nu șterge cookies și nu împiedică identificarea prin cont, browser ori informații pe care le publici. Modul privat și VPN-ul rezolvă probleme diferite: primul limitează urmele locale în anumite condiții, al doilea schimbă traseul traficului și cine îl poate observa pe acea rută.</p>
<h2>Încrederea se mută către furnizor</h2>
<p>Fără VPN, furnizorul tău de internet poate vedea anumite metadate despre conexiuni. Cu VPN, operatorul VPN devine un intermediar care poate vedea metadate de rețea și trebuie să proceseze traficul. HTTPS continuă să cripteze conținutul dintre browser și site; VPN-ul nu ar trebui descris ca posibilitatea de a citi toate parolele HTTPS, dar nici nu face furnizorul de VPN incapabil să observe nimic. Practicile de jurnalizare, jurisdicția, auditul și răspunsul la incidente variază între furnizori.</p>
<h2>Cum alegi un furnizor</h2>
<ul><li>Citește politica de confidențialitate și află exact ce date de conectare și diagnosticare păstrează.</li><li>Caută informații verificabile despre audituri independente, proprietate și vulnerabilități remediate.</li><li>Verifică aplicațiile, actualizările, suportul și metoda de plată, nu doar promisiunile „fără loguri”.</li><li>Evită VPN-urile gratuite cu model de business neclar și permisiuni excesive.</li><li>Folosește MFA pe contul VPN și descarcă aplicația numai din sursa oficială.</li></ul>
<h2>Când e util</h2>
<p>Poate fi util pentru a reduce expunerea metadatelor față de o rețea locală sau pentru conectare la resursele unei organizații. Nu este o necesitate universală pentru fiecare accesare de site și nu înlocuiește actualizările, HTTPS, parolele unice ori protecția conturilor. Înainte să îl activezi, definește ce problemă vrei să rezolvi și verifică dacă furnizorul ales este potrivit acelei probleme.</p>
<p>Dacă folosești un VPN de serviciu, urmează instrucțiunile organizației și nu instala un produs personal care interferează cu el. Pentru confidențialitate online, combină mai multe măsuri și tratează orice promisiune de anonimat total cu scepticism.</p>
HTML,
            ],
            [
                'title' => 'Cum îți securizezi contul de Facebook',
                'slug' => 'securizare-cont-facebook', 'category' => 'retele-sociale',
                'excerpt' => 'Protejează contul Facebook cu o parolă unică, autentificare în doi pași și verificări regulate ale sesiunilor. Pregătește din timp metodele de recuperare și fii atent la mesaje false de suport.',
                'seo_title' => 'Cum îți securizezi contul de Facebook',
                'meta_description' => 'Activează 2FA, verifică sesiunile și alertele Facebook și pregătește metodele oficiale de recuperare a contului.',
                'focus_keyword' => 'securizare cont Facebook', 'secondary_keywords' => ['Facebook 2FA', 'recuperare cont Facebook', 'sesiuni Facebook'], 'reading_time' => 5,
                'related' => ['date-personale-nu-posta-online', 'cum-creezi-parole-sigure-manager'],
                'sources' => [
                    ['Facebook Help Center: securitatea contului', 'https://www.facebook.com/help/235353253505947'],
                    ['Facebook Help Center: recuperarea unui cont compromis', 'https://www.facebook.com/help/1216349518398524/'],
                    ['CISA: MFA și parole puternice', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Contul Facebook poate conține conversații, fotografii și acces la pagini sau aplicații asociate. O parolă unică și autentificarea în doi pași reduc riscul de preluare, iar verificarea sesiunilor te ajută să observi accesul pe care nu îl recunoști. Meniurile Meta se mai schimbă; folosește Accounts Center și documentația oficială pentru calea disponibilă în aplicația ta.</p>
<h2>Întărește autentificarea</h2>
<p>Folosește o parolă diferită de cea pentru e-mail și alte rețele. Activează autentificarea în doi pași printr-o cheie de securitate, aplicație de autentificare sau metodă disponibilă în cont. Păstrează codurile de recuperare într-un loc sigur, separat de telefon. Activează alertele pentru autentificări noi și nu aproba solicitări de conectare pe care nu le-ai inițiat.</p>
<h2>Verifică activitatea și recuperarea</h2>
<p>În setările de securitate, verifică unde ești conectat și deconectează sesiunile pe care nu le recunoști. Confirmă adresa de e-mail și numărul de telefon de recuperare și elimină metodele vechi pe care nu le mai controlezi. Revizuiește aplicațiile și site-urile conectate; revocă accesul celor pe care nu le folosești. Verifică și dacă au fost adăugate adrese de contact sau administratori de pagină fără acordul tău.</p>
<h2>Recunoaște mesajele false de suport</h2>
<p>Un mesaj poate susține că pagina va fi închisă dacă nu „verifici” imediat contul printr-un link. Nu introduce parola într-o pagină deschisă din acel mesaj și nu trimite coduri. Deschide aplicația direct și verifică notificările oficiale din cont. Dacă ai un cont compromis, folosește fluxul oficial de recuperare de pe un dispozitiv folosit anterior, nu un „agent” care cere bani sau acces la distanță.</p>
<h2>Redu informațiile publice</h2>
<p>Revizuiește publicul pentru postări, lista de prieteni, numărul de telefon și adresa de e-mail. Limitează cine te poate găsi sau eticheta și verifică postările în care ești menționat. Setările de audiență reduc vizibilitatea, dar nu împiedică capturile de ecran sau redistribuirea de către destinatari.</p>
<p>Dacă apar postări ori mesaje pe care nu le-ai creat, salvează dovezile, anunță contactele să ignore cererile de bani și securizează întâi adresa de e-mail asociată. Schimbarea parolei poate să nu închidă toate sesiunile; verifică manual dispozitivele conectate și aplicațiile. Consultă centrul oficial de ajutor dacă nu mai poți intra.</p>
HTML,
            ],
            [
                'title' => 'Cum îți protejezi contul de Instagram împotriva hackerilor',
                'slug' => 'protejeaza-cont-instagram-hackeri', 'category' => 'retele-sociale',
                'excerpt' => 'Securizează parola, e-mailul asociat și autentificarea suplimentară, apoi verifică activitatea și aplicațiile conectate. Mesajele false despre încălcări de copyright sau suport încearcă adesea să fure accesul contului. Pregătește recuperarea din timp.',
                'seo_title' => 'Cum protejezi contul Instagram de hackeri',
                'meta_description' => 'Activează autentificarea suplimentară pe Instagram, protejează e-mailul și recunoaște mesajele false de suport și recuperare.',
                'focus_keyword' => 'protejare cont Instagram', 'secondary_keywords' => ['Instagram 2FA', 'cont Instagram spart', 'phishing Instagram'], 'reading_time' => 5,
                'related' => ['securizare-cont-facebook', 'cum-recunosti-email-phishing'],
                'sources' => [
                    ['Meta: măsuri de securitate și recuperare pentru Facebook și Instagram', 'https://about.fb.com/news/2025/12/making-it-easier-to-access-account-support-on-facebook-and-instagram/'],
                    ['Meta: Accounts Center și conturi conectate', 'https://www.facebook.com/help/943858526073065'],
                    ['CISA: parole și MFA', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Un cont Instagram poate fi preluat prin reutilizarea unei parole scurse, un mesaj de phishing sau accesul la adresa de e-mail asociată. Protecția eficientă începe cu securizarea e-mailului, fiindcă acesta poate primi linkuri de resetare. Activează autentificarea în doi pași, verifică sesiunile și folosește doar fluxul oficial de recuperare.</p>
<h2>Protejează parola și e-mailul</h2>
<p>Folosește o parolă unică, păstrată într-un manager, și schimb-o dacă ai introdus-o pe un site care nu era Instagram. Pe e-mailul asociat activează MFA și verifică numărul de recuperare, dispozitivele și regulile de redirecționare. Dacă cineva controlează inboxul, poate intercepta solicitările de resetare chiar dacă Instagram are o parolă nouă.</p>
<p>În Accounts Center deschide setările de securitate disponibile și activează autentificarea suplimentară. Alege o metodă pe care o poți recupera și păstrează codurile offline. Revizuiește sesiunile de conectare și deconectează dispozitivele pe care nu le recunoști; dacă nu ești sigur, schimbă parola și apoi verifică din nou.</p>
<h2>Ferește-te de mesaje de phishing</h2>
<p>Escrocii pot trimite notificări despre încălcarea drepturilor de autor, verificarea insignelor, sponsorizări sau închiderea contului. Ei cer să te autentifici pe un formular extern ori să le trimiți un cod. Nu răspunde prin linkul din mesaj. Deschide aplicația direct și verifică secțiunea de notificări sau starea contului. O solicitare urgentă nu devine legitimă doar pentru că folosește logo-ul Instagram.</p>
<h2>Revizuiește aplicațiile conectate</h2>
<p>Elimină instrumente de creștere a audienței, programare sau analiză pe care nu le mai folosești și pe care nu le recunoști. Unele servicii cer acces larg pentru o funcție limitată. Verifică permisiunile acordate și schimbă parola dacă ai introdus-o direct într-o aplicație terță, în loc să folosești autorizarea oficială.</p>
<h2>Dacă pierzi accesul</h2>
<p>Folosește pagina oficială de recuperare din aplicație sau Help Center și urmează verificările disponibile pentru cont. Acționează de pe un dispozitiv și o rețea folosite anterior, dacă se poate. Cere prietenilor să raporteze profilul compromis și anunță-i prin alt canal să nu trimită bani ori coduri. După recuperare, elimină sesiunile și aplicațiile necunoscute și verifică dacă adresa de e-mail și numărul au fost schimbate.</p>
<p>Meta poate modifica denumirile funcțiilor și disponibilitatea în funcție de regiune. Dacă meniul s-a schimbat, caută „Security” sau „Account Center” în setările aplicației și folosește linkul de ajutor oficial, nu rezultatele sponsorizate care promit recuperarea contului.</p>
HTML,
            ],
            [
                'title' => 'Ți-a fost spart contul de Facebook sau Instagram? Iată ce trebuie să faci imediat',
                'slug' => 'cont-facebook-instagram-spart-recuperare', 'category' => 'retele-sociale',
                'excerpt' => 'Dacă observi postări, mesaje sau autentificări pe care nu le recunoști, securizează mai întâi e-mailul și contul, apoi folosește recuperarea oficială Facebook sau Instagram. Urmează pașii în ordine, fără să trimiți coduri nimănui.',
                'seo_title' => 'Cont Facebook sau Instagram spart: pașii de recuperare',
                'meta_description' => 'Află cum recuperezi un cont Facebook sau Instagram compromis, cum elimini accesul atacatorilor și ce măsuri previn atacurile viitoare.',
                'focus_keyword' => 'cont Facebook spart', 'secondary_keywords' => ['recuperare cont Facebook', 'cont Instagram hackuit', 'recuperare Instagram', 'cont compromis', 'autentificare în doi pași'],
                'reading_time' => 8, 'image' => 'cont-facebook-instagram-spart.jpg',
                'related' => ['securizare-cont-facebook', 'protejeaza-cont-instagram-hackeri', 'autentificare-doi-pasi-cum-activezi', 'cum-creezi-parole-sigure-manager', 'cum-recunosti-email-phishing', 'primele-30-minute-cont-compromis'],
                'sources' => [
                    ['Facebook: recuperează un cont compromis', 'https://www.facebook.com/hacked'],
                    ['Instagram: recuperează un cont compromis', 'https://www.instagram.com/hacked/'],
                    ['Meta Help Center: conturi Facebook compromise', 'https://www.facebook.com/help/1216349518398524/'],
                    ['CISA: parole și autentificare multifactor', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Când cineva obține acces neautorizat la un cont Facebook sau Instagram, poate schimba parola ori datele de recuperare, publica în numele tău, citi mesaje private sau trimite cereri de bani celor din lista de prieteni. Uneori atacatorul folosește o sesiune deja conectată, așa că schimbarea parolei nu este întotdeauna singurul pas necesar. Un cont compromis nu înseamnă automat că telefonul este infectat; începe cu protejarea e-mailului și cu instrumentele oficiale ale platformei.</p>
<p>Dacă ai încă acces, acționează de pe un telefon sau calculator de încredere și tastează tu adresa serviciului în browser. Nu folosi linkuri din mesaje care pretind că sunt de la suport. Urmează pașii de mai jos în ordinea potrivită situației tale. Denumirile meniurilor Facebook și Instagram se pot schimba în funcție de versiunea aplicației, regiune și tipul contului.</p>

<h2>Semne că un cont ar putea fi compromis</h2>
<p>Verifică dacă au apărut postări, comentarii, reacții sau mesaje pe care nu le-ai scris; dacă prietenii primesc solicitări de bani ori coduri; dacă parola, adresa de e-mail, numărul de telefon sau numele contului au fost schimbate; sau dacă primești alerte de conectare pe care nu le recunoști. Pot apărea și pagini ori reclame pe care nu le-ai creat sau aplicații conectate necunoscute.</p>
<p>O alertă singură nu dovedește preluarea contului: locația poate fi aproximativă, iar un dispozitiv nou poate fi al tău. Verifică activitatea deschizând direct aplicația sau site-ul oficial. Nu apăsa pe butonul dintr-un e-mail alarmist înainte să confirmi că mesajul este autentic. Dacă bănuiești că și adresa de e-mail este accesată de altcineva, securizeaz-o imediat de pe un dispozitiv de încredere.</p>

<h2>Ce faci imediat dacă încă poți intra în cont</h2>
<ol>
<li><strong>Securizează adresa de e-mail asociată.</strong> Schimbă-i parola cu una unică, activează autentificarea în doi pași și verifică adresele și numerele de recuperare. Caută reguli de redirecționare, filtre sau sesiuni pe care nu le-ai configurat. E-mailul este adesea cheia pentru resetarea celorlalte conturi.</li>
<li><strong>Schimbă parola rețelei sociale.</strong> Alege una lungă, aleatorie și nefolosită în altă parte. Un manager de parole te poate ajuta să o generezi și să o păstrezi. Dacă parola veche a fost refolosită, schimb-o și pe celelalte servicii unde apărea, începând cu e-mailul.</li>
<li><strong>Verifică datele de contact și metodele de recuperare.</strong> Elimină adresele, numerele și conturile conectate pe care nu le recunoști. Confirmă că datele tale sunt scrise corect și că le poți accesa.</li>
<li><strong>Revocă accesul necunoscut.</strong> În setările de securitate, verifică sesiunile și dispozitivele conectate, apoi deconectează-le pe cele pe care nu le recunoști. Revizuiește aplicațiile și site-urile terțe autorizate și retrage accesul nefolosit sau suspect.</li>
<li><strong>Activează autentificarea în doi pași.</strong> Preferă o aplicație de autentificare sau o cheie de securitate dacă sunt disponibile și păstrează codurile de recuperare separat, într-un loc sigur.</li>
</ol>
<p>Înainte să închizi o sesiune necunoscută, asigură-te că ai o metodă de recuperare pe care o controlezi. Nu trimite nimănui parola, codul primit prin SMS, codul de autentificare sau codurile de recuperare. Nici o persoană care se prezintă drept angajat al platformei nu ar trebui să aibă nevoie de ele.</p>

<h2>Cum recuperezi un cont Facebook</h2>
<p>Deschide <a href="https://www.facebook.com/hacked" target="_blank" rel="noopener noreferrer">pagina oficială Facebook pentru conturi compromise</a>, de preferat de pe un dispozitiv pe care l-ai folosit înainte pentru autentificare. Introdu datele cerute direct în pagina Facebook și urmează verificările afișate pentru contul tău. Dacă poți încă intra, folosește și opțiunile de securitate ale contului pentru schimbarea parolei, verificarea sesiunilor și confirmarea datelor de contact.</p>
<p>Dacă nu mai recunoști adresa sau numărul afișat, continuă prin opțiunea de recuperare oferită de pagină. Caută în inboxul adresei originale notificări despre schimbarea datelor contului; dacă mesajul este autentic, folosește numai linkul oficial din acel mesaj. Nu există o metodă care să garanteze recuperarea: platforma poate solicita verificări diferite sau poate limita temporar încercările. Evită serviciile neoficiale care cer bani, parola ori acces la distanță.</p>

<h2>Cum recuperezi un cont Instagram</h2>
<p>Accesează <a href="https://www.instagram.com/hacked/" target="_blank" rel="noopener noreferrer">fluxul oficial Instagram pentru conturi compromise</a> sau opțiunea de ajutor de pe ecranul de autentificare al aplicației. Introdu numele de utilizator, e-mailul sau numărul solicitat și urmează instrucțiunile afișate atunci. Opțiunile pot varia: ți se poate cere să confirmi o adresă, să primești un link de conectare sau să efectuezi o verificare suplimentară. Folosește doar aplicația și domeniile oficiale.</p>
<p>Dacă încă poți intra, verifică în zona de securitate activitatea de conectare, sesiunile, metodele de autentificare și aplicațiile conectate. Unele setări se găsesc în Accounts Center, iar denumirile și poziția lor pot diferi între versiunile aplicației. După recuperare, verifică dacă au fost schimbate adresa, numărul, numele de utilizator ori conturile legate. Nu repeta la nesfârșit solicitarea de coduri dacă pagina îți cere să aștepți.</p>

<h2>Dacă atacatorul a schimbat parola sau adresa de e-mail</h2>
<p>Pornește recuperarea din pagina oficială chiar dacă parola nu mai funcționează. Verifică inboxul și folderul de spam al adresei care era asociată înainte de incident. Unele notificări de securitate pot oferi un pas de contestare, dar verifică expeditorul și destinația linkului înainte să îl deschizi. Dacă nu mai ai acces la e-mail sau la numărul de telefon, folosește opțiunile alternative afișate de platformă; disponibilitatea lor depinde de cont și de verificările aplicabile.</p>
<p>Dacă e-mailul a fost compromis, recuperează-l prin furnizorul său înainte de a cere noi linkuri de resetare. Altfel, un atacator care îl controlează poate intercepta mesajele. Nu crea un cont nou folosind datele vechi ca substitut pentru recuperare și nu trimite documente decât printr-o cerere autentică, în fluxul securizat al platformei.</p>

<h2>Închide sesiunile necunoscute și verifică aplicațiile</h2>
<p>După ce ai securizat parola și e-mailul, caută secțiunea de activitate sau dispozitive conectate în setările contului. Deconectează sesiunile pe care nu le recunoști și revocă aplicațiile terțe care nu mai sunt necesare. Dacă lista nu indică clar un dispozitiv, schimbă parola de pe dispozitivul sigur și alege opțiunea de deconectare a celorlalte sesiuni, dacă platforma o oferă. Nu presupune că schimbarea parolei închide automat fiecare sesiune deja activă.</p>
<p>Verifică și accesul la pagini, conturi profesionale, metode de plată și reclame dacă le administrezi. Elimină administratorii necunoscuți și contactează platforma prin instrumentele oficiale dacă nu poți restabili singur controlul.</p>

<h2>Activează autentificarea în doi pași</h2>
<p>Autentificarea în doi pași adaugă o confirmare la parolă. Activeaz-o în setările de securitate sau în Accounts Center, folosind o aplicație de autentificare, o cheie de securitate ori altă metodă disponibilă. SMS-ul poate fi mai bun decât lipsa unei protecții suplimentare, însă păstrează numărul de telefon sigur și nu comunica nimănui codurile primite. Salvează codurile de recuperare offline, separat de telefon.</p>
<p>Parcurge și ghidul nostru despre <a href="/articole/autentificare-doi-pasi-cum-activezi">activarea autentificării în doi pași</a> și articolul despre <a href="/articole/cum-creezi-parole-sigure-manager">parole unice cu un manager de parole</a>. Aceste măsuri reduc riscul, dar nu înlocuiesc verificarea periodică a sesiunilor și a metodelor de recuperare.</p>

<h2>Protejează-ți prietenii și contactele</h2>
<p>Dacă din cont au plecat mesaje sau postări, anunță contactele printr-un alt canal, de exemplu telefon sau e-mail. Spune-le să ignore cererile de bani, coduri, voturi sau linkuri trimise în perioada respectivă și să nu descarce fișierele primite. Cere-le să raporteze mesajele sau profilul dacă instrumentele platformei oferă această opțiune. Dacă cineva a trimis deja bani, roagă-l să contacteze imediat banca sau furnizorul de plată.</p>
<p>După ce recapeți contul, șterge conținutul fraudulos, verifică mesajele trimise și postează o informare clară. Nu publica capturi care expun parole, coduri, adrese private sau datele de contact ale altor persoane. Dacă atacatorul a accesat conversații private, avertizează persoanele vizate fără să redistribui conținutul sensibil.</p>

<h2>Greșeli frecvente de evitat</h2>
<ul>
<li>Nu plăti persoane care promit recuperarea garantată și nu le oferi parola ori codurile.</li>
<li>Nu instala aplicații de control la distanță și nu trimite copii de acte în afara unei verificări oficiale.</li>
<li>Nu folosi linkuri din mesaje nesolicitate despre suspendare, copyright sau verificarea insignelor.</li>
<li>Nu schimba toate setările simultan de pe un dispozitiv posibil compromis; folosește unul actualizat și de încredere.</li>
<li>Nu șterge imediat e-mailurile de alertă sau mesajele frauduloase; păstrează dovezile necesare recuperării și raportării.</li>
</ul>
<p>Dacă ai deschis un link de phishing, citește și ghidul nostru despre <a href="/articole/ce-faci-dupa-link-phishing">pașii de făcut după accesarea unui link suspect</a>. Dacă ai pierdut accesul la mai multe conturi, urmează și planul pentru <a href="/articole/primele-30-minute-cont-compromis">primele minute după compromiterea unui cont</a>.</p>

<h2>Listă de verificare pentru prevenție</h2>
<ul>
<li>Folosește parole unice pentru contul social și adresa de e-mail asociată.</li>
<li>Activează autentificarea în doi pași și păstrează codurile de recuperare într-un loc sigur.</li>
<li>Verifică periodic sesiunile, numerele și adresele de recuperare.</li>
<li>Elimină aplicațiile conectate pe care nu le mai folosești.</li>
<li>Activează notificările pentru conectări noi, dacă sunt disponibile.</li>
<li>Ține sistemul de operare, browserul și aplicațiile actualizate.</li>
<li>Nu comunica parole sau coduri și confirmă cererile neobișnuite prin alt canal.</li>
</ul>

<h2>Întrebări frecvente</h2>
<h3>Pot recupera un cont dacă atacatorul a schimbat adresa de e-mail?</h3>
<p>Începe recuperarea din fluxul oficial Facebook sau Instagram și verifică notificările trimise la adresa folosită anterior. Platforma poate oferi verificări suplimentare, însă pașii depind de cont și nu există garanția recuperării.</p>
<h3>Schimbarea parolei deconectează atacatorul?</h3>
<p>Poate opri folosirea parolei vechi, dar verifică separat sesiunile și dispozitivele conectate. Închide accesul necunoscut din setările oficiale și revizuiește aplicațiile terțe.</p>
<h3>Este sigur să folosesc o firmă care promite recuperarea contului?</h3>
<p>Nu îi da parola, coduri, bani sau acces la distanță. Folosește paginile oficiale și evită recuperatorii neafiliați; nicio persoană externă nu poate garanta rezultatul procesului Meta.</p>
<h3>Trebuie să șterg aplicația de pe telefon?</h3>
<p>Nu automat. Actualizează aplicația din magazinul oficial și securizează mai întâi e-mailul și contul. Dacă suspectezi și compromiterea dispozitivului, cere ajutor tehnic de încredere și nu instala instrumente trimise de necunoscuți.</p>
<h3>Ce fac dacă prietenii au primit cereri de bani?</h3>
<p>Anunță-i imediat printr-un canal diferit și cere-le să nu plătească. Dacă au trimis deja bani, trebuie să contacteze banca sau serviciul de plată cât mai repede și să păstreze conversațiile și detaliile tranzacției.</p>
<h3>Autentificarea în doi pași garantează că nu voi fi atacat?</h3>
<p>Nu. Ea adaugă o barieră importantă, dar trebuie să protejezi și e-mailul, să verifici sesiunile și să nu aprobi solicitări sau coduri pe care nu le-ai cerut.</p>

<h2>Concluzie</h2>
<p>În cazul unui cont Facebook sau Instagram compromis, protejează întâi e-mailul și folosește instrumentul oficial de recuperare, apoi schimbă parola, verifică datele de contact și închide sesiunile necunoscute. Anunță contactele dacă au fost trimise mesaje frauduloase și nu promite nimănui recuperarea sigură. După ce recapeți accesul, activează autentificarea în doi pași și păstrează metodele de recuperare actuale.</p>
HTML,
            ],
            [
                'title' => 'Cum recunoști tentativele de fraudă pe WhatsApp',
                'slug' => 'recunoastere-frauda-whatsapp', 'category' => 'retele-sociale',
                'excerpt' => 'Escrocii pot cere codul de înregistrare, bani pentru o urgență sau acces printr-un link. Verifică solicitările prin alt canal, activează verificarea în doi pași și află cum recuperezi contul compromis.',
                'seo_title' => 'Cum recunoști fraudele și mesajele false pe WhatsApp',
                'meta_description' => 'Nu distribui coduri WhatsApp, verifică cererile urgente prin alt canal și învață să blochezi și să recuperezi un cont.',
                'focus_keyword' => 'fraudă WhatsApp', 'secondary_keywords' => ['cod verificare WhatsApp', 'cont WhatsApp compromis', 'mesaje false WhatsApp'], 'reading_time' => 6,
                'related' => ['securizare-cont-facebook', 'cum-recunosti-sms-fals-fraude-mesaje'],
                'sources' => [
                    ['WhatsApp Help Center: recuperează un cont compromis', 'https://faq.whatsapp.com/1131652977717250/?helpref=hc_fnav'],
                    ['WhatsApp Help Center: alertă de înregistrare pe dispozitiv nou', 'https://faq.whatsapp.com/1234181997583400'],
                    ['Meta: funcții noi de securitate WhatsApp', 'https://about.fb.com/news/2026/08/new-account-security-features-for-whatsapp/'],
                ],
                'content' => <<<'HTML'
<p>O fraudă pe WhatsApp poate veni de la un necunoscut sau de la un prieten al cărui cont a fost preluat. Mesajele cer adesea bani urgent, un cod de verificare, vot pentru un concurs sau accesarea unui link de livrare. Chiar dacă numele și fotografia sunt familiare, confirmă solicitările neobișnuite prin apel telefonic la numărul cunoscut sau printr-o conversație separată.</p>
<h2>Nu divulga niciodată codul de înregistrare</h2>
<p>Codul primit prin SMS permite înregistrarea numărului pe un dispozitiv. WhatsApp spune explicit să nu îl distribui nimănui, nici măcar prietenilor sau familiei. Un atacator poate pretinde că a trimis codul din greșeală sau că are nevoie de el pentru un vot. Dacă îl primești fără să fi cerut autentificarea, nu îl transmite și activează verificarea în doi pași în setările contului.</p>
<p>Un cod PIN de verificare în doi pași este diferit de codul de înregistrare. Nu îl trimite unei persoane care spune că este de la suport. Păstrează adresa de e-mail de recuperare actuală dacă funcția o oferă și folosește o parolă unică pentru e-mailul asociat.</p>
<h2>Verifică mesajele de la contacte</h2>
<p>Dacă un membru al familiei cere bani sau date, sună-l folosind numărul salvat, nu doar un apel WhatsApp pornit din aceeași conversație. Escrocii pot prelua conturi reale și pot cunoaște context din conversații mai vechi. Ferește-te de oferte care cer plata în afara platformei, instalarea unei aplicații de control la distanță sau scanarea unui QR primit de la un necunoscut.</p>
<h2>Blochează și raportează</h2>
<p>Nu răspunde la mesaje suspecte și folosește opțiunile WhatsApp de raportare și blocare. Verifică periodic dispozitivele conectate și elimină sesiunile web sau desktop pe care nu le recunoști. Setările de confidențialitate pentru fotografie, status și grupuri pot limita mesajele nedorite, deși nu împiedică toate formele de fraudă.</p>
<h2>Dacă ți-a fost preluat contul</h2>
<p>Reînregistrează numărul în aplicația oficială cu codul de șase cifre primit prin SMS sau apel; documentația WhatsApp precizează că reînregistrarea deconectează dispozitivele care foloseau contul. Dacă ți se cere un PIN pe care nu îl cunoști, urmează procesul oficial și perioada de așteptare indicată, fără să plătești unui „recuperator”. Avertizează prietenii prin alt canal că cineva te poate imita, apoi verifică dispozitivele conectate și activează protecțiile disponibile.</p>
<p>Nu șterge aplicația înainte să înțelegi efectul asupra backupului local. Dacă o persoană a cerut bani prin contul tău, păstrează capturile și detaliile plății; contactează banca rapid dacă ai trimis bani.</p>
HTML,
            ],
            [
                'title' => 'Ce faci în primele 30 de minute după compromiterea unui cont',
                'slug' => 'primele-30-minute-cont-compromis', 'category' => 'ghiduri-practice',
                'excerpt' => 'Acționează în ordinea care limitează pagubele: securizează e-mailul principal, schimbă datele de autentificare de pe un dispozitiv sigur, revocă sesiunile și verifică tranzacțiile și recuperarea contului compromis în siguranță, pas cu pas.',
                'seo_title' => 'Cont compromis: plan de acțiune pentru primele 30 de minute',
                'meta_description' => 'Urmează pași clari după preluarea unui cont: securizează e-mailul, schimbă parolele, revocă sesiunile și contactează banca.',
                'focus_keyword' => 'cont compromis ce faci', 'secondary_keywords' => ['recuperare cont', 'parolă furată', 'sesiuni active'], 'reading_time' => 7,
                'related' => ['ce-faci-dupa-link-phishing', 'cum-recunosti-email-phishing'],
                'sources' => [
                    ['Google: securizarea unui cont compromis', 'https://support.google.com/accounts/answer/6294825?hl=ro'],
                    ['CISA: protecția conturilor cu parole, MFA și actualizări', 'https://www.cisa.gov/secure-our-world'],
                    ['DNSC: raportarea incidentelor cibernetice', 'https://www.serviciipublice.gov.ro/serviciu/preluare-alerte-de-securitate-cibernetica'],
                ],
                'content' => <<<'HTML'
<p>Primele minute după o preluare de cont contează, dar graba poate duce la ștergerea dovezilor sau schimbarea parolei de pe un dispozitiv încă nesigur. Notează ce ai observat și stabilește dacă este afectat e-mailul, un cont social, un serviciu de muncă sau un cont financiar. Dacă există tranzacții ori acces la bani, sună banca imediat, chiar înainte să parcurgi toate verificările.</p>
<h2>Minutele 0–5: limitează accesul și păstrează dovezile</h2>
<p>Oprește conversația cu presupusul atacator și nu trimite coduri. Fă capturi ale alertelor, adreselor web și mesajelor, fără să redistribui linkurile. Dacă ai instalat un program de control la distanță sau cineva îți controlează ecranul, deconectează dispozitivul de la rețea și folosește alt dispozitiv cunoscut pentru schimbarea parolelor. Nu reseta imediat telefonul dacă ai nevoie să păstrezi dovezi pentru bancă sau angajator.</p>
<h2>Minutele 5–12: securizează e-mailul principal</h2>
<p>E-mailul este adesea cheia pentru resetarea celorlalte conturi. Accesează-l tastând manual adresa oficială, schimbă parola cu una unică și revizuiește numărul, adresa de recuperare, sesiunile, dispozitivele și aplicațiile conectate. Verifică reguli de redirecționare, filtre sau delegări pe care nu le-ai creat. Activează MFA și păstrează codurile de recuperare separat de telefon.</p>
<h2>Minutele 12–20: securizează conturile legate</h2>
<p>Schimbă parolele reutilizate, începând cu bankingul, rețelele sociale și serviciile de muncă. Folosește recuperarea oficială dacă nu te mai poți conecta și raportează schimbările de adresă ori număr pe care nu le-ai autorizat. Revocă sesiunile străine și accesul aplicațiilor necunoscute. Anunță administratorul IT dacă a fost afectat un cont de serviciu; poate bloca sesiuni și tokenuri pe care nu le poți controla singur.</p>
<h2>Minutele 20–30: verifică banii și notifică persoanele afectate</h2>
<p>Uită-te la tranzacțiile recente și sună banca dacă ai introdus date de card, aprobat o plată ori observi o operațiune necunoscută. Cere pașii pentru blocare și contestare; rezultatul depinde de metoda și momentul plății. Dacă un cont social a trimis cereri de bani, avertizează contactele prin alt canal. Păstrează numerele de sesizare și cronologia.</p>
<p>După această jumătate de oră, continuă recuperarea cu furnizorul contului și verifică dispozitivele. Dacă incidentul implică o posibilă infracțiune, raportează Poliției; pentru incidente de securitate cibernetică, folosește canalele DNSC. Nu plăti persoane care promit recuperarea garantată a contului sau a banilor și cer o taxă în avans.</p>
HTML,
            ],
            [
                'title' => 'Cum elimini extensiile suspecte din browser',
                'slug' => 'cum-elimini-extensii-suspecte-browser', 'category' => 'ghiduri-practice',
                'excerpt' => 'Extensiile pot citi sau modifica paginile în funcție de permisiunile acordate. Verifică autorul și accesul solicitat, dezactivează întâi extensia suspectă și elimin-o din meniul browserului web. Revizuiește lista periodic în toate browserele instalate.',
                'seo_title' => 'Cum găsești și elimini extensiile suspecte',
                'meta_description' => 'Verifică permisiunile extensiilor și elimină-le în Chrome, Edge sau Firefox; află când merită resetat browserul.',
                'focus_keyword' => 'eliminare extensii suspecte', 'secondary_keywords' => ['extensii Chrome', 'extensii Edge', 'extensii Firefox'], 'reading_time' => 6,
                'related' => ['cum-verifici-permisiuni-aplicatii-telefon', 'cum-verifici-telefon-compromis'],
                'sources' => [
                    ['Google Chrome Help: instalează și gestionează extensii', 'https://support.google.com/chrome/answer/2664769?hl=ro'],
                    ['Microsoft Support: elimină extensii Edge', 'https://support.microsoft.com/en-us/edge/add-turn-off-or-remove-extensions-in-microsoft-edge'],
                    ['Mozilla Support: dezactivează sau elimină extensii Firefox', 'https://support.mozilla.org/en-US/kb/disable-or-remove-add-ons'],
                ],
                'content' => <<<'HTML'
<p>O extensie poate adăuga funcții utile, dar permisiunea de a citi sau modifica datele site-urilor vizitate îi poate oferi acces larg. O extensie necunoscută nu dovedește automat o infectare; verifică ce ai instalat, cine a publicat-o și ce permisiuni are. Dacă browserul a început să redirecționeze căutările sau să afișeze ferestre neașteptate, dezactivează extensiile una câte una și observă dacă problema dispare.</p>
<h2>Ce verifici înainte de eliminare</h2>
<p>Deschide pagina de extensii din meniul browserului și notează numele, dezvoltatorul, data instalării și permisiunile. Caută numele exact pe site-ul magazinului oficial și verifică dacă extensia este actualizată și dacă editorul este cel așteptat. Recenziile pot fi manipulate, iar numele populare pot fi imitate. Nu instala un „scanner” dintr-o fereastră apărută pe site-ul suspect.</p>
<h2>Chrome</h2>
<p>Deschide meniul cu trei puncte, alege Extensions &gt; Manage Extensions, apoi dezactivează extensia pe care vrei s-o verifici. Dacă simptomele dispar și nu depinzi de ea, selectează Remove. În pagina Details poți limita accesul la datele site-urilor; acordă acces doar când folosești extensia sau numai pe site-urile necesare. Numele meniurilor pot varia ușor în funcție de limbă și versiune.</p>
<h2>Edge</h2>
<p>Din meniul Extensions deschide Manage extensions. Oprește comutatorul pentru a dezactiva temporar; pentru eliminare folosește Remove din meniul extensiei. Verifică și dacă o extensie a fost instalată de o organizație sau politică de dispozitiv înainte să o ștergi de pe un calculator administrat de serviciu.</p>
<h2>Firefox</h2>
<p>Deschide meniul, alege Add-ons and themes, apoi Extensions. Poți dezactiva comutatorul pentru verificare sau selecta meniul cu trei puncte și Remove. Dacă butonul de eliminare lipsește, extensia poate fi administrată de o politică a dispozitivului; cere ajutor administratorului în loc să încerci să ocolești politica.</p>
<h2>Când resetezi setările</h2>
<p>După îndepărtarea extensiei, verifică motorul de căutare, pagina de pornire, permisiunile site-urilor și notificările. Resetarea browserului poate dezactiva extensii și restaura setări, dar poate șterge preferințe sau sesiuni. Fă backup la favorite și parolele necesare înainte, iar apoi reinstalează doar extensiile de care ai nevoie din magazinul oficial. Dacă extensia reapare, scanează dispozitivul cu un instrument de securitate de încredere și verifică programele instalate recent.</p>
HTML,
            ],
            [
                'title' => 'Cum verifici permisiunile aplicațiilor de pe telefon',
                'slug' => 'cum-verifici-permisiuni-aplicatii-telefon', 'category' => 'ghiduri-practice',
                'excerpt' => 'Cameră, microfon, locație și contacte sunt date sensibile. Revizuiește permisiunile în setările Android sau iPhone și oferă acces doar când este necesar funcției aplicației. Repetă verificarea periodic pentru toate aplicațiile instalate.',
                'seo_title' => 'Cum verifici permisiunile aplicațiilor pe telefon',
                'meta_description' => 'Revizuiește accesul aplicațiilor la cameră, microfon, locație, contacte și fotografii pe Android și iPhone.',
                'focus_keyword' => 'permisiuni aplicații telefon', 'secondary_keywords' => ['permisiuni Android', 'permisiuni iPhone', 'acces cameră microfon locație'], 'reading_time' => 6,
                'related' => ['securizare-telefon-android-iphone', 'cum-verifici-telefon-compromis'],
                'sources' => [
                    ['Android: controale de confidențialitate și permisiuni', 'https://www.android.com/intl/en_nz/safety/privacy/'],
                    ['Apple: controlează accesul aplicațiilor la funcțiile hardware', 'https://support.apple.com/en-euro/guide/iphone/iph168c4bbd5/ios'],
                    ['Apple: confidențialitate și servicii de localizare', 'https://support.apple.com/en-us/102515'],
                ],
                'content' => <<<'HTML'
<p>Permisiunile controlează ce informații și funcții poate folosi o aplicație. Accesul la locație poate fi necesar pentru navigare, iar camera și microfonul pentru apeluri, dar nu fiecare aplicație are nevoie de acces permanent. Revizuiește permisiunile după instalarea aplicațiilor și din când în când; sistemele mai noi pot reseta automat accesul aplicațiilor nefolosite.</p>
<h2>Începe cu permisiunile sensibile</h2>
<p>Verifică locația, camera, microfonul, contactele, fotografiile, SMS-urile, calendarul și accesul la notificări. Întreabă-te ce funcție folosește efectiv acel acces. Dacă o aplicație de lanternă cere contacte sau SMS, refuză permisiunea și verifică dacă există o explicație credibilă. O aplicație legitimă poate cere acces într-un moment precis; poți acorda doar cât timp o folosești, când opțiunea există.</p>
<h2>Android</h2>
<p>În Setări caută Privacy &gt; Permission Manager sau caută direct „Permisiuni”. Deschide fiecare categorie și revizuiește aplicațiile aprobate. Android afișează, în funcție de versiune, un tablou de bord care arată accesul recent și poate reseta automat permisiunile aplicațiilor inactive. Denumirile și locul meniului diferă după producător; folosește căutarea din Settings dacă nu găsești calea indicată.</p>
<h2>iPhone</h2>
<p>Deschide Settings &gt; Privacy &amp; Security și alege categoria, de exemplu Location Services, Camera, Microphone, Contacts sau Photos. Poți revoca accesul pentru fiecare aplicație și, la unele categorii, acorda acces selectiv. Indicatorii de sistem arată când camera sau microfonul sunt folosite; App Privacy Report poate ajuta la revizuirea activității, dacă este disponibil și activat.</p>
<h2>Ce faci când o aplicație refuză să funcționeze</h2>
<p>Unele funcții chiar depind de permisiunea cerută: o aplicație de hărți are nevoie de locație pentru navigare, iar una de apeluri are nevoie de microfon. Poți acorda acces temporar când folosești funcția, apoi să îl retragi. Dacă explicația nu corespunde funcției, caută documentația dezvoltatorului sau alege o alternativă. Evită să acorzi acces permanent doar pentru a închide o fereastră de avertizare.</p>
<p>Revocarea permisiunii nu șterge date pe care aplicația le-a colectat deja sau trimis la server. Consultă setările contului și politica de confidențialitate dacă vrei să ceri ștergerea datelor. Elimină aplicațiile nefolosite, actualizează telefonul și instalează aplicații numai din surse de încredere.</p>
HTML,
            ],
            [
                'title' => 'Cum recunoști un videoclip deepfake',
                'slug' => 'cum-recunosti-video-deepfake', 'category' => 'inteligenta-artificiala',
                'excerpt' => 'Un deepfake poate imita chipul sau vocea unei persoane, dar indiciile vizuale nu oferă un verdict sigur. Verifică sursa, caută confirmări independente și nu trimite bani doar pe baza unui apel emoțional.',
                'seo_title' => 'Cum recunoști un deepfake și verifici sursa',
                'meta_description' => 'Înțelege ce este un deepfake, ce indicii pot ajuta și de ce trebuie verificată sursa înainte să distribui sau să acționezi.',
                'focus_keyword' => 'cum recunoști un deepfake', 'secondary_keywords' => ['video fals AI', 'clonare voce', 'fraudă deepfake'], 'reading_time' => 6,
                'related' => ['recunoastere-frauda-whatsapp', 'ce-date-nu-introduci-instrumente-ai'],
                'sources' => [
                    ['FBI IC3: fraude facilitate de inteligența artificială generativă', 'https://www.ic3.gov/PSA/2024/PSA241203'],
                    ['ENISA: amenințări cibernetice și tendințe', 'https://www.enisa.europa.eu/topics/cyber-threats/threat-landscape'],
                    ['CISA: practici de verificare și securitate de bază', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Un deepfake este conținut audio, imagine sau video creat ori modificat cu tehnici de inteligență artificială pentru a imita o persoană. Poate fi folosit în glume, materiale artistice sau fraude de impersonare. Calitatea variază, iar un clip scurt, comprimat sau filmat în lumină slabă poate părea neobișnuit și fără să fie fals. Niciun semn vizual izolat nu oferă un verdict sigur.</p>
<h2>Indiciile care merită verificate</h2>
<p>Unele clipuri pot avea mișcări nefirești ale gurii, umbre incoerente, margini fluctuante în jurul feței, sincronizare imperfectă între voce și imagine sau detalii care se schimbă între cadre. Înregistrările audio pot avea ritm, respirație sau intonație neobișnuite. Aceste indicii sunt imperfecte: compresia, camera, iluminarea și conexiunea video pot produce artefacte similare, iar instrumentele de generare se îmbunătățesc.</p>
<h2>Verifică proveniența, nu doar pixelii</h2>
<ul><li>Caută clipul sau afirmația pe canalul oficial al persoanei ori instituției.</li><li>Verifică dacă organizații de presă de încredere au relatat independent același lucru.</li><li>Folosește căutarea inversă de imagini pentru cadre cheie și caută o versiune mai veche a clipului.</li><li>Confirmă cererile de bani sau informații printr-un număr deja salvat ori o altă persoană cunoscută.</li><li>Nu redistribui clipul doar pentru că pare urgent sau șocant.</li></ul>
<h2>Fraude prin voce sau video</h2>
<p>Un atacator poate imita vocea unei rude care cere ajutor urgent sau un presupus director care solicită un transfer. Înainte de orice plată, oprește apelul și sună persoana la un număr cunoscut. Familia poate stabili o expresie de verificare pentru urgențe, dar aceasta trebuie păstrată privată. O instituție nu ar trebui să ceară parola bancară, codul de autentificare sau instalarea unei aplicații de la distanță într-un apel video.</p>
<h2>Limitele detecției automate</h2>
<p>Instrumentele care pretind că detectează orice deepfake pot da rezultate fals pozitive sau fals negative. Nu încărca un clip privat ori cu date sensibile într-un site necunoscut doar pentru a obține un scor. Contextul, sursa și confirmarea printr-un canal separat sunt adesea mai utile pentru o decizie practică decât un detector fără metodologie publică.</p>
<p>Dacă un deepfake este folosit pentru a cere bani sau a impersona pe cineva, păstrează URL-ul, momentul și mesajele și raportează conținutul platformei. Contactează banca dacă ai transferat bani și autoritățile dacă există prejudiciu sau amenințare. Nu afirma că un material este fals până când nu ai suficiente dovezi; descrie ce ai verificat și ce rămâne incert.</p>
HTML,
            ],
            [
                'title' => 'Ce date personale nu ar trebui să introduci în ChatGPT sau alte instrumente AI',
                'slug' => 'date-personale-nu-introduce-instrumente-ai', 'category' => 'inteligenta-artificiala',
                'excerpt' => 'Tratează prompturile ca informații pe care le trimiți unui serviciu online. Evită date de identificare, parole, coduri, documente confidențiale și informații financiare și verifică setările și politica produsului înainte de utilizare.',
                'seo_title' => 'Ce date personale să nu introduci în instrumente AI',
                'meta_description' => 'Protejează parolele, actele, datele financiare și informațiile confidențiale când folosești ChatGPT sau alte instrumente AI.',
                'focus_keyword' => 'date personale ChatGPT', 'secondary_keywords' => ['confidențialitate AI', 'date în prompturi', 'ChatGPT data controls'], 'reading_time' => 6,
                'related' => ['date-personale-nu-posta-online', 'ce-stie-google-despre-tine-control-date'],
                'sources' => [
                    ['OpenAI Help Center: controalele de date din ChatGPT', 'https://help.openai.com/en/articles/7730893-data-controls-in-chatgpt'],
                    ['OpenAI Help Center: întrebări despre Temporary Chat', 'https://help.openai.com/en/articles/8914046-temporary-chat-faq/'],
                    ['CISA: protejarea informațiilor și conturilor', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Un prompt trimis unui instrument AI este transmis unui serviciu online pentru procesare. Modul în care datele sunt păstrate, revizuite sau folosite depinde de produs, tipul contului, setările active și termenii serviciului. Nu presupune că o conversație este privată doar pentru că nu apare într-un motor de căutare. Dacă ai dubii, anonimizează informația sau folosește un instrument aprobat de organizația ta.</p>
<h2>Nu introduce secrete care oferă acces</h2>
<p>Nu trimite parole, coduri unice, coduri de recuperare, chei API, PIN-uri sau întrebări de securitate. Aceste valori permit acces direct la conturi și sisteme. Pentru ajutor tehnic, înlocuiește-le cu marcaje fictive precum <code>[TOKEN]</code> și șterge datele reale din capturile de ecran înainte să le atașezi.</p>
<h2>Limitează datele de identificare și financiare</h2>
<p>Evită CNP-ul, seria și numărul actului, adresa completă, numărul cardului, CVV-ul, IBAN-ul asociat unor tranzacții sensibile și documentele bancare. Nu încărca facturi, extrase, acte medicale ori contracte care conțin nume și semnături dacă nu ai autoritatea și o nevoie clară. Dacă vrei să analizezi un text, elimină numele, numerele, adresele și identificatorii înainte de a-l trimite.</p>
<h2>Documente de muncă și proprietate intelectuală</h2>
<p>Nu lipi cod sursă privat, date ale clienților, strategii comerciale, credențiale sau documente marcate confidențial într-un cont personal. Verifică regulile angajatorului și setările workspace-ului: conturile de organizație pot avea politici diferite de păstrare, administrare și acces. Un buton de dezactivare a instruirii modelelor nu este echivalent cu ștergerea conversațiilor și nu înlocuiește regulile de clasificare a datelor.</p>
<h2>Verifică setările serviciului</h2>
<p>În ChatGPT, OpenAI oferă controale pentru utilizarea conversațiilor la îmbunătățirea modelelor, export, ștergere și Temporary Chat; disponibilitatea depinde de plan și workspace. Temporary Chat nu apare în istoric și nu este folosit pentru îmbunătățirea modelelor cât timp rămâne temporar, dar poate fi păstrat pentru o perioadă limitată din motive de siguranță, conform documentației curente. Citește pagina oficială înainte să introduci date de lucru sau personale.</p>
<p>AI poate produce răspunsuri greșite, deci nu folosi rezultatul ca verificare oficială a unui contract, diagnostic ori transfer bancar. Pentru prompturi utile, descrie problema cu date fictive și păstrează doar contextul strict necesar. Dacă ai trimis accidental o parolă sau un token, rotește-l imediat și revizuiește activitatea contului asociat.</p>
HTML,
            ],
            [
                'title' => 'Cum îți protejezi cardul bancar când faci cumpărături online',
                'slug' => 'protejeaza-card-bancar-cumparaturi-online', 'category' => 'securitate-financiara',
                'excerpt' => 'Cumpără de la comercianți pe care îi poți verifica, folosește metode de plată cu proceduri de contestare și activează notificările bancare. HTTPS singur nu certifică magazinul. Folosește carduri virtuale dacă banca le oferă.',
                'seo_title' => 'Cum protejezi cardul la cumpărături online',
                'meta_description' => 'Verifică magazinul, folosește autentificarea plăților și notificările bancare și reacționează rapid la tranzacții necunoscute.',
                'focus_keyword' => 'protejare card cumpărături online', 'secondary_keywords' => ['plată online sigură', 'card virtual', 'fraudă cu cardul'], 'reading_time' => 6,
                'related' => ['cum-identifici-site-fals-date-personale', 'fraude-online-frecvente-romania-protectie'],
                'sources' => [
                    ['ENISA: securitate la cumpărături online', 'https://www.enisa.europa.eu/news/enisa-news/tips-for-cybersecurity-when-buying-and-selling-online'],
                    ['FTC: cumperi de pe un marketplace', 'https://consumer.ftc.gov/articles/buying-online-marketplace'],
                    ['CISA: parole unice și actualizări', 'https://www.cisa.gov/secure-our-world'],
                ],
                'content' => <<<'HTML'
<p>Plata online este mai sigură când verifici comerciantul, alegi o metodă potrivită și urmărești tranzacțiile. Nu introduce datele cardului doar pentru că pagina are lacăt în bara de adresă: HTTPS criptează conexiunea, dar nu demonstrează că magazinul este legitim. O reclamă bine făcută sau un preț foarte mic nu oferă garanții despre livrare ori rambursare.</p>
<h2>Verifică magazinul înainte de plată</h2>
<p>Accesează site-ul tastând adresa sau dintr-o sursă cunoscută, verifică domeniul complet și caută date de contact, condiții de livrare, politică de retur și numele comerciantului. Caută numele magazinului împreună cu termeni precum „reclamație” sau „fraudă”. Recenziile de pe același site pot fi controlate de vânzător, așa că verifică surse independente. Atenție la vânzătorii care te scot din platforma de anunțuri pentru plată directă.</p>
<h2>Alege și autentifică plata</h2>
<p>Folosește autentificarea în aplicația băncii sau 3-D Secure atunci când este oferită și citește suma și numele comerciantului înainte să aprobi. Un cod de autorizare nu este un cod de reducere; nu îl dicta la telefon și nu îl introduce pe o pagină deschisă dintr-un mesaj. Cardurile virtuale sau numerele temporare pot limita expunerea la un comerciant, dacă banca le oferă; verifică limitele, comisioanele și unde funcționează.</p>
<p>Plătește prin platforma oficială și evită metodele greu de urmărit atunci când vânzătorul insistă să ocolești sistemul. Păstrează confirmarea comenzii, conversațiile, factura și termenii ofertați. Posibilitatea de a contesta o plată depinde de tipul cardului, de autorizare, de termen și de condițiile băncii sau furnizorului, deci întreabă emitentul înainte de cumpărare dacă ai nelămuriri.</p>
<h2>Activează notificările și verifică extrasul</h2>
<p>Activează notificări pentru plăți și verifică periodic tranzacțiile, inclusiv sumele mici. O debitare mică poate fi o verificare de card sau o operațiune neautorizată; nu ghici, verifică în aplicația băncii. Blochează temporar cardul dacă banca oferă funcția și contactează emitentul prin numărul de pe card sau aplicația oficială când vezi o tranzacție necunoscută.</p>
<p>Nu salva cardul pe site-uri pe care nu le folosești și protejează e-mailul care primește confirmările de cumpărare. Dacă ai introdus datele într-un site fals, contactează banca rapid, cere evaluarea blocării cardului și păstrează URL-ul pentru raportare. Evită persoanele care promit returnarea garantată a banilor contra unei taxe.</p>
HTML,
            ],
            [
                'title' => 'Ce trebuie să faci dacă ai trimis bani unui escroc online',
                'slug' => 'ce-faci-dupa-ce-ai-trimis-bani-escroc', 'category' => 'securitate-financiara',
                'excerpt' => 'Contactează imediat banca sau furnizorul folosit pentru transfer, cere oprirea ori contestarea plății și păstrează dovezile. Recuperarea nu este garantată și depinde de tipul tranzacției și metoda folosită în funcție de procedură.',
                'seo_title' => 'Ai trimis bani unui escroc? Pași rapizi',
                'meta_description' => 'Află pe cine contactezi după o plată frauduloasă, ce dovezi să păstrezi și cum eviți escrocheriile de recuperare a banilor.',
                'focus_keyword' => 'am trimis bani unui escroc', 'secondary_keywords' => ['plată frauduloasă', 'contestare transfer bancar', 'raportare fraudă online'], 'reading_time' => 6,
                'related' => ['protejeaza-card-bancar-cumparaturi-online', 'fraude-online-frecvente-romania-protectie'],
                'sources' => [
                    ['FTC: pași după ce ai plătit un escroc', 'https://consumer.ftc.gov/articles/what-do-if-you-were-scammed'],
                    ['Poliția Română: informații și servicii', 'https://www.politiaromana.ro/'],
                    ['DNSC: raportare și îndrumare pentru incidente', 'https://www.serviciipublice.gov.ro/serviciu/preluare-alerte-de-securitate-cibernetica'],
                ],
                'content' => <<<'HTML'
<p>Acționează imediat, chiar dacă nu știi dacă plata poate fi oprită. Contactează banca sau serviciul folosit pentru transfer prin numărul de pe card, aplicația oficială ori datele găsite independent. Spune că suspectezi o fraudă, indică suma și ora și întreabă dacă tranzacția poate fi blocată, rechemată sau contestată. Răspunsul depinde de instrumentul de plată, dacă ai autorizat-o și cât timp a trecut; nu există garanție de recuperare.</p>
<h2>Ce să faci în funcție de plată</h2>
<ul><li><strong>Card:</strong> cere băncii să evalueze blocarea cardului și contestarea debitării. Urmează pașii și termenele pe care ți le comunică.</li><li><strong>Transfer bancar:</strong> sună imediat banca și cere verificarea posibilității de rechemare sau blocare. Trimite referința tranzacției și informațiile despre destinatar.</li><li><strong>Aplicație de plăți:</strong> raportează plata în aplicație și contactează emitentul cardului ori banca dacă metoda este legată de ei.</li><li><strong>Criptomonedă, numerar sau voucher:</strong> transferul poate fi foarte greu sau imposibil de inversat. Contactează platforma și păstrează identificatorii tranzacției; nu plăti „taxe de recuperare” unor intermediari.</li></ul>
<p>Acestea sunt categorii generale, nu o promisiune privind regulile unei bănci românești. Citește contractul cu furnizorul și urmează instrucțiunile sale. Dacă ai autorizat plata după ce ai fost indus în eroare, explică exact împrejurările; nu o descrie ca tranzacție neautorizată dacă ai confirmat-o tu.</p>
<h2>Păstrează dovezile</h2>
<p>Salvează conversațiile, numărul de telefon, profilul, adresa site-ului, anunțul, chitanța, referința de plată și răspunsurile băncii. Nu șterge contul și nu continua negocierea cu escrocul. Dacă platforma permite raportare, fă-o după ce ai păstrat informațiile utile. Notează persoanele contactate, ora apelului și numărul sesizării.</p>
<h2>Raportează și protejează conturile</h2>
<p>Dacă ai transmis date de card sau credențiale, securizează contul bancar și e-mailul asociat, schimbă parolele reutilizate și revocă sesiunile necunoscute. Depune sesizare la Poliție când suspectezi o infracțiune și consultă DNSC pentru componenta de securitate cibernetică. Păstrează copii ale dovezilor și atașează doar informațiile relevante prin canalele oficiale.</p>
<p>După pierdere, pot apărea falși recuperatori care pretind că au găsit banii sau cunosc persoane din bancă. Nu plăti avans, nu oferi coduri și nu instala software la distanță. O instituție legitimă nu îți cere parola completă pentru a procesa sesizarea.</p>
HTML,
            ],
            [
                'title' => 'Cum verifici dacă o alertă de securitate este reală',
                'slug' => 'cum-verifici-alerta-securitate-reala', 'category' => 'stiri-alerte',
                'excerpt' => 'Alerta poate fi autentică, învechită, scoasă din context sau falsă. Confirmă mesajul pe pagina oficială a organizației, verifică data și domeniul și nu folosi linkul alarmist pentru autentificare înainte de verificare.',
                'seo_title' => 'Cum verifici dacă o alertă de securitate este reală',
                'meta_description' => 'Verifică data, sursa și linkurile unei alerte de securitate înainte să o distribui, să instalezi ceva sau să îți schimbi datele.',
                'focus_keyword' => 'alertă de securitate reală', 'secondary_keywords' => ['verificare alertă cibernetică', 'alertă falsă', 'surse oficiale securitate'], 'reading_time' => 5,
                'related' => ['cum-recunosti-email-phishing', 'cum-identifici-site-fals-date-personale'],
                'sources' => [
                    ['CISA: alerte și recomandări de securitate', 'https://www.cisa.gov/news-events/cybersecurity-advisories'],
                    ['CERT-EU: avize de securitate', 'https://www.cert.europa.eu/publications/security-advisories/2026'],
                    ['DNSC: serviciu oficial de raportare a incidentelor', 'https://www.serviciipublice.gov.ro/serviciu/preluare-alerte-de-securitate-cibernetica'],
                ],
                'content' => <<<'HTML'
<p>O alertă de securitate poate anunța o vulnerabilitate reală, o campanie de phishing sau o recomandare pentru un produs. Mesajele false copiază deseori numele unei instituții și creează presiune: „distribuie imediat”, „instalează acest update” sau „autentifică-te ca să eviți blocarea”. Înainte să acționezi, verifică dacă alerta apare și pe pagina oficială a organizației invocate.</p>
<h2>Verifică emitentul și data</h2>
<p>Accesează site-ul printr-un semn de carte sau tastând domeniul, nu prin linkul din mesaj. Caută titlul alertei, numărul de referință, produsul afectat și data publicării ori actualizării. O recomandare veche poate fi încă valabilă, dar verifică dacă există o versiune mai nouă. Citește dacă avertizarea vine de la producător, o instituție publică sau un terț care comentează alerta.</p>
<h2>Inspectează linkurile și acțiunile cerute</h2>
<p>Un mesaj oficial poate conține linkuri, dar domeniul trebuie să fie cel real al organizației. Numele afișat și HTTPS nu confirmă singure autenticitatea. Nu instala un fișier atașat unei alerte nesolicitate și nu introduce parola într-o pagină de „verificare a contului”. Dacă este recomandată o actualizare, deschide aplicația sau instrumentul de update al producătorului direct.</p>
<h2>Compară surse independente</h2>
<p>Verifică dacă producătorul produsului afectat a publicat o recomandare și dacă autorități sau echipe de răspuns recunoscute au relatat același lucru. Pentru România consultă canalele DNSC; pentru avertizări europene, CERT-EU și ENISA publică resurse și avize. Caută după identificatorul vulnerabilității sau numărul alertei, dar nu presupune că rezultatele populare sunt oficiale.</p>
<h2>Nu amplifica dezinformarea</h2>
<p>Dacă nu găsești confirmare, nu redistribui mesajul cu linkuri active și nu declara automat că alerta este falsă. Poți întreba organizația pe un canal publicat independent sau raporta mesajul platformei. Elimină datele personale din capturi înainte de distribuire. Dacă ai introdus credențiale sau ai instalat fișierul, urmează pașii de securizare a contului și dispozitivului și raportează incidentul.</p>
<p>O alertă credibilă descrie de obicei produsele sau versiunile vizate, sursa și pașii de mitigare. Chiar și atunci, aplică numai recomandările care se potrivesc dispozitivului tău și păstrează un backup înaintea modificărilor care pot afecta sistemul.</p>
HTML,
            ],
            [
                'title' => 'Unde găsești avertizări oficiale de securitate cibernetică în România',
                'slug' => 'avertizari-oficiale-securitate-cibernetica-romania', 'category' => 'stiri-alerte',
                'excerpt' => 'Pentru informații actuale, consultă DNSC, furnizorul produsului și surse europene precum ENISA sau CERT-EU. Verifică data, publicul vizat și pașii recomandați înainte să redistribui o alertă importantă către alte persoane.',
                'seo_title' => 'Surse oficiale de avertizări cibernetice în România',
                'meta_description' => 'Găsește avertizări de la DNSC, ENISA, CERT-EU și furnizorii produselor și află cum verifici relevanța lor.',
                'focus_keyword' => 'avertizări securitate cibernetică România', 'secondary_keywords' => ['DNSC alerte', 'CERT-EU advisories', 'raportare incident cibernetic'], 'reading_time' => 5,
                'related' => ['cum-verifici-alerta-securitate-reala', 'fraude-online-frecvente-romania-protectie'],
                'sources' => [
                    ['DNSC: serviciul public de raportare a alertelor', 'https://www.serviciipublice.gov.ro/serviciu/preluare-alerte-de-securitate-cibernetica'],
                    ['CERT-EU: avize de securitate', 'https://www.cert.europa.eu/publications/security-advisories/2026'],
                    ['ENISA: peisajul amenințărilor cibernetice', 'https://www.enisa.europa.eu/topics/cyber-threats/threat-landscape'],
                    ['CISA: alerte și avize de securitate', 'https://www.cisa.gov/news-events/cybersecurity-advisories'],
                ],
                'content' => <<<'HTML'
<p>Pentru o alertă relevantă publicului din România, începe cu Directoratul Național de Securitate Cibernetică (DNSC) și cu furnizorul produsului menționat. Apoi compară cu surse europene, precum ENISA și CERT-EU, sau cu autoritatea cibernetică a țării afectate. Nu există un singur flux care să acopere toate aplicațiile, vulnerabilitățile și escrocheriile.</p>
<h2>Surse românești</h2>
<p>DNSC publică informări și oferă canale de raportare și îndrumare pentru incidente. Portalul de servicii publice descrie raportarea alertelor și indică opțiuni precum 1911 și platforma DNSC; verifică pagina curentă pentru disponibilitate, program și pașii aplicați situației tale. Pentru o posibilă infracțiune sau prejudiciu, Poliția Română este sursa potrivită pentru sesizare. O alertă tehnică și o plângere penală nu sunt același lucru, iar în unele cazuri poate fi util să faci ambele.</p>
<h2>Surse europene și furnizori</h2>
<p>ENISA publică analize și materiale despre tipuri de amenințări, iar CERT-EU publică avize tehnice. Aceste resurse pot fi orientate către organizații și specialiști, așa că verifică dacă recomandarea se aplică unui utilizator casnic. Pentru un telefon, browser sau router, consultă și pagina oficială de securitate a producătorului; acolo apar versiunile afectate și actualizările disponibile.</p>
<h2>Cum interpretezi o avertizare</h2>
<ul><li>Verifică data publicării și data ultimei actualizări.</li><li>Identifică exact produsul, versiunea și grupul vizat.</li><li>Separă recomandările urgente de contextul tehnic și de măsurile opționale.</li><li>Accesează linkurile tastând domeniul oficial sau folosind semne de carte salvate.</li><li>Nu transmite coduri, parole ori copii de acte pentru a „verifica” dacă ești afectat.</li></ul>
<h2>Raportează fără să expui alte persoane</h2>
<p>Păstrează mesajul, URL-ul, data și orice referință tehnică. Înainte să postezi o captură public, ascunde numele, adresa, numărul de telefon și codurile. Pentru raportare folosește serviciul oficial menționat și urmează instrucțiunile acestuia; dacă banii sau conturile sunt în pericol, contactează banca ori furnizorul contului imediat.</p>
<p>Alertele evoluează, iar o pagină poate fi actualizată sau retrasă. Verifică sursa chiar în ziua în care acționezi și nu trata o listă de știri veche ca pe o informare curentă. Acest articol este evergreen și nu afirmă existența unui incident recent.</p>
HTML,
            ],
        ];
    }
}
