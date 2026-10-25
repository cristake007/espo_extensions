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
