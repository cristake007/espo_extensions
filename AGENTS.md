# Reguli pentru toate extensiile EspoCRM

Sursa de adevăr este `/opt/espo_extensions`. Instrucțiunile suplimentare din directoarele extensiilor, inclusiv `GeneratorPerioadeCursuri/AGENTS.md`, rămân aplicabile.

## Mediu și limite

- Singura instanță EspoCRM aprobată pentru integrarea curentă este `http://178.104.183.29`, administrată din `/var/www/espocrm/docker-compose.yml`. Verifică înainte de folosire serviciile Compose, URL-ul configurat în EspoCRM, versiunea EspoCRM și versiunea PHP. Nu presupune compatibilitatea: citește `acceptableVersions` și `php` din manifestul fiecărei extensii. În prezent instanța raportează EspoCRM 10.0.10 și PHP 8.4.27; verifică din nou la fiecare task relevant.
- Nu crea laboratoare Docker temporare, nu reinstala aplicația sau baza de date pentru fiecare task și nu accesa servere, date sau credențiale de producție. Nu folosi `/opt/crm.cursurituv.ro` ori domeniile de producție ca ținte de test.
- Modifică extensiile numai în repository. Nu edita direct fișierele instalate în runtime. Nu modifica manifestele sau versiunile pentru a forța compatibilitatea.
- Nu publica parole, tokenuri sau cookie-uri în Git sau în rapoarte. Nu executa operațiuni distructive fără backup și autorizare. Nu face automat commit, push sau deployment în producție.

## Flux pentru o schimbare de extensie

1. Modifică sursele din repository și rulează `./test-php.sh validate NumeExtensie` sau, pe un host cu PHP, `./build.sh --extension ./NumeExtensie --validate`, plus testele statice și de regresie relevante. Păstrează harness-ul comun.
2. Numai pentru un candidat de extensie, construiește ZIP-ul prin `./test-php.sh build NumeExtensie` sau direct prin `./build.sh --extension ./NumeExtensie --zip files scripts` pe un host cu PHP și ZIP. Verifică inventarul ZIP, manifestul și compatibilitatea declarată cu versiunea efectivă a instanței.
3. Instalează candidatul în instanța de test prin **Administration > Extensions** sau comanda nativă `bin/command extension --file=...` în serviciul `espocrm` din Compose-ul aprobat. Nu copia surse direct în runtime. Fă backup înaintea oricărei operații cu efect distructiv.
4. Rulează integrarea, API, ACL și browser pe instanța aprobată. Folosește în testele noi date sintetice cu marcaj unic și șterge doar ID-urile create de acea rulare, după verificarea proprietății; nu folosi resetări sau ștergeri globale.
5. Corectează în repository, repetă verificările și predă rezultatele plus URL-ul de test pentru verificarea vizuală. După aprobarea vizuală, livrează exact ZIP-ul validat și SHA-256. Utilizatorul îl instalează manual în producție.

Migrarea mediului de testare nu este un release: nu construi ZIP-uri noi doar pentru documentație. Scripturile istorice care pornesc laboratoare separate, fac resetări globale sau cer fixture-uri vechi nu se execută pe această instanță până la izolarea explicită a datelor și rescrierea teardown-ului. Vezi [ghidul operațional](README.md) pentru inventar.

## Dezvoltarea extensiilor bazate pe șablonul oficial `ext-template`

- Repository-ul este un monorepo: fiecare extensie are propriul director, propriile `extension.json`, `package.json`, `composer.json`, `src/`, teste și build; `build.sh` din rădăcină este doar orchestratorul. Nu muta configurația unei extensii la rădăcina monorepo-ului și nu combina dependențele npm/Composer ale extensiilor.
- Urmează convențiile din [șablonul oficial EspoCRM](https://github.com/espocrm/ext-template) și instrumentele `espo-extension-tools` corespunzătoare versiunii fixate în lockfile. Nu implementa transpiler, bundler, loader, ACL, hooks sau mecanisme de entități paralele atunci când EspoCRM oferă deja soluția nativă.
- Pentru `extension.json` cu `bundled: true`, verifică împreună `Resources/module.json` (`bundled` și `jsTranspiled`), `Resources/metadata/app/client.json` (în special `scriptList` cu `__APPEND__` și calea `client/custom/modules/<module-kebab>/lib/init.js`), referințele `module:component` din metadata, identificatorii AMD și conținutul efectiv al bundle-ului. Nu amesteca încărcarea directă din `src/` cu distribuția exclusiv prin bundle.
- Dacă frontend-ul folosește AMD `define()`, identificatorii trebuie să fie compatibili cu loaderul EspoCRM și cu maparea din bundle; alternativ, folosește module ES doar dacă toolchain-ul oficial al proiectului le transpilează corect. Nu presupune că un ZIP valid sintactic conține module încărcabile. Verifică toate modulele necesare, nu numai existența fișierului `module-*.js`.
- La `--validate` execută verificările statice, testele PHPUnit/PHPStan și verificările frontend disponibile. La `--zip`, inspectează arhiva finală: manifest, versiuni sincronizate (`manifest.json`, `package.json`, `package-lock.json`), `files/`, `scripts/`, `lib/init.js`, bundle, mapări și modulele AMD așteptate când `bundled: true`. Orice lipsă relevantă este eroare de build; nu declara PASS din simpla existență a ZIP-ului.
- Testele trebuie să valideze comportamentul și contractele reale (metadata, rute, ACL, încărcarea modulelor, conținut ZIP). Evită aserțiunile fragile bazate exclusiv pe forma textuală a codului; când textul este esențial, normalizează terminatorii de linie `CRLF`/`LF` și actualizează testele odată cu o modificare legitimă de format.
- După instalarea candidatului în instanța de test aprobată, verifică prin browser autentificat navigarea în sidenav și încărcarea paginilor, a bundle-ului, a template-urilor și a resurselor JS; eșecurile 404, modulele lipsă și erorile de consolă sunt FAIL, chiar dacă PHPUnit/PHPStan au trecut. Testează și accesul pe rolurile relevante, nu numai ca admin.
- Pentru mai multe extensii moderne păstrează **o singură instanță EspoCRM instalată** pentru integrarea funcțională. Nu rula automat `npm run all` pentru fiecare extensie: comanda oficială poate crea o instanță separată în `site/`. `npm run prepare-test` poate pregăti doar sursele și dependențele EspoCRM necesare PHPUnit/PHPStan; nu echivalează automat cu o instanță instalată, dar poate duplica fișierele. Refolosirea unei singure baze de cod EspoCRM pentru analizele statice necesită configurare explicită, fără a modifica runtime-ul comun sau a presupune compatibilitatea.
- Nu executa instalări, upgrade-uri, resetări sau migrări în producție. Build-ul local, testarea și aprobarea vizuală preced livrarea ZIP-ului final.

## Migrarea extensiilor legacy la `ext-template`

- Migrează **o singură extensie odată**, într-un director de lucru/branch separat; păstrează sursa și ZIP-ul legacy intacte până la verificarea echivalenței. Nu rescrie extensii stabile doar pentru uniformizarea structurii.
- Inițializează proiectul nou cu șablonul oficial în subdirectorul extensiei, apoi transferă sursele `files/` în `src/files/`, `scripts/` în `src/scripts/` și adaptează metadatele, frontend-ul, autoload-ul și build-ul. Nu copia mecanic un ZIP existent ca proiect de dezvoltare.
- Păstrează identitatea extensiei și a datelor deja instalate: numele din manifest, namespace-ul modulului, scope-urile, numele entităților, câmpurile, relațiile, identificatorii de metadata și migrațiile necesare. Verifică separat mecanismul de upgrade EspoCRM, scripturile `BeforeInstall`/`AfterInstall`/`BeforeUninstall` dacă există, dependențele și compatibilitatea PHP/EspoCRM. Nu redenumi entitățile ori modulele fără un plan explicit de migrare a datelor.
- Compară ZIP-ul legacy cu ZIP-ul nou (structură, fișiere, metadata, hooks, ACL, JS/CSS, scripturi și autoload), apoi rulează teste de regresie și scenarii de upgrade cu backup pe instanța de test aprobată. Publică o nouă versiune doar după validarea upgrade-ului și aprobarea utilizatorului; nu instala niciodată în producție în cadrul migrării.

## Teste JavaScript și browser

Folosește `./test-node.sh test NumeExtensie` sau `./test-node.sh test --all` pentru testele Node, respectiv `./test-node.sh playwright NumeExtensie` pentru testele browser. Comanda `test` execută toate fișierele `*.test.js` și `.mjs` din `tests/`, cu excepția directorului `tests/browser/`; pentru acesta folosește comanda `playwright`. Wrapperul folosește imaginea Node LTS `node:24.22.1-bookworm-slim` și imaginea Playwright `mcr.microsoft.com/playwright:v1.61.1-noble`, potrivită cu lockfile-urile existente. Nu instala Node pe host și nu adăuga `node_modules` ori binare în Git. Pentru suita browser GeneratorPerioadeCursuri, wrapperul folosește `ESPO_BASE_URL`, `ESPO_ADMIN_USERNAME` și `ESPO_ADMIN_PASSWORD` încărcate automat conform secțiunii următoare; instalarea extensiei în runtime rămâne o condiție separată.

## Authenticated Browser Testing

- Instanța EspoCRM aprobată pentru toate testele browser autentificate este exclusiv `http://178.104.183.29`.
- Credențialele de test sunt păstrate exclusiv în `/root/.config/espocrm/test.env`. Nu le copia în repository, cod, comenzi, loguri, capturi sau rapoarte și nu afișa niciodată parola ori conținutul fișierului.
- `./test-node.sh playwright NumeExtensie` trebuie să încarce automat din acest fișier `ESPO_BASE_URL`, `ESPO_ADMIN_USERNAME` și `ESPO_ADMIN_PASSWORD` înainte de pornirea containerului și să le transmită în Docker fără a include valorile pe linia de comandă.
- Dacă fișierul lipsește, nu este un fișier obișnuit, nu poate fi citit, are sintaxă invalidă, nu definește toate variabilele obligatorii sau indică alt URL, testarea browser trebuie să se oprească și să raporteze explicit cauza fără a divulga valori sensibile.
- Dacă autentificarea eșuează, raportează testele autentificate ca `FAILED` și cauza observată. Testele autentificate omise se raportează separat ca `SKIPPED`; nu le include niciodată în totalul testelor trecute.
- Pentru orice modificare de interfață, verifică rezultatul în interfața EspoCRM reală autentificată, nu numai în fixture-uri sau pagini sintetice.
- Aceste reguli se aplică tuturor extensiilor existente și viitoare din repository.

## Validare PHP și ZIP fără PHP pe host

Folosește `./test-php.sh validate NumeExtensie`, `./test-php.sh validate --all`, `./test-php.sh build NumeExtensie` sau `./test-php.sh php -v`. Wrapperul apelează întotdeauna `build.sh`, nu implementează o validare paralelă. Pentru extensiile clasice, imaginea locală este construită din PHP CLI Alpine oficial `8.4.27`, fixat prin digest, și conține numai Bash și ZIP în plus față de imaginea de bază. Containerele sunt efemere și rulează fără rețea; repository-ul este read-only la validare, iar build-ul poate scrie numai în `dist/`. Pentru extensiile moderne (`extension.json` și `src/files`), wrapperul deleagă către `build.sh` de pe host, astfel încât validarea include și testele oficiale PHPUnit/PHPStan. Aceste extensii necesită `--bootstrap` înainte de prima validare/compilare. `validate --all` descoperă manifestele de la rădăcină, exclude scaffold-ul și raportează eșecul dacă testele complete ale vreunei extensii moderne nu pot rula. Rularea directă a `build.sh` rămâne suportată.
