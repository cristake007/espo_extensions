# Reguli pentru toate extensiile EspoCRM

Sursa de adevăr este `/opt/espo_extensions`. Instrucțiunile suplimentare din directoarele extensiilor, inclusiv `GeneratorPerioadeCursuri/AGENTS.md`, rămân aplicabile.

## Mediu și limite

- Singura instanță EspoCRM aprobată pentru integrarea curentă este `http://178.104.183.29`, administrată din `/var/www/espocrm/docker-compose.yml`. Verifică înainte de folosire serviciile Compose, URL-ul configurat în EspoCRM, versiunea EspoCRM și versiunea PHP. Nu presupune compatibilitatea: citește `acceptableVersions` și `php` din manifestul fiecărei extensii. În prezent instanța raportează EspoCRM 10.0.10 și PHP 8.4.27; verifică din nou la fiecare task relevant.
- Nu crea laboratoare Docker temporare, nu reinstala aplicația sau baza de date pentru fiecare task și nu accesa servere, date sau credențiale de producție. Nu folosi `/opt/crm.cursurituv.ro` ori domeniile de producție ca ținte de test.
- Modifică extensiile numai în repository. Nu edita direct fișierele instalate în runtime. Nu modifica manifestele sau versiunile pentru a forța compatibilitatea.
- Nu publica parole, tokenuri sau cookie-uri în Git sau în rapoarte. Nu executa operațiuni distructive fără backup și autorizare. Nu face automat commit, push sau deployment în producție.

## Flux pentru o schimbare de extensie

1. Modifică sursele din repository și rulează `./build.sh --extension ./NumeExtensie --validate`, plus testele statice și de regresie relevante. Păstrează harness-ul comun.
2. Numai pentru un candidat de extensie, construiește ZIP-ul prin `./build.sh --extension ./NumeExtensie --zip files scripts`. Verifică inventarul ZIP, manifestul și compatibilitatea declarată cu versiunea efectivă a instanței.
3. Instalează candidatul în instanța de test prin **Administration > Extensions** sau comanda nativă `bin/command extension --file=...` în serviciul `espocrm` din Compose-ul aprobat. Nu copia surse direct în runtime. Fă backup înaintea oricărei operații cu efect distructiv.
4. Rulează integrarea, API, ACL și browser pe instanța aprobată. Folosește în testele noi date sintetice cu marcaj unic și șterge doar ID-urile create de acea rulare, după verificarea proprietății; nu folosi resetări sau ștergeri globale.
5. Corectează în repository, repetă verificările și predă rezultatele plus URL-ul de test pentru verificarea vizuală. După aprobarea vizuală, livrează exact ZIP-ul validat și SHA-256. Utilizatorul îl instalează manual în producție.

Migrarea mediului de testare nu este un release: nu construi ZIP-uri noi doar pentru documentație. Scripturile istorice care pornesc laboratoare separate, fac resetări globale sau cer fixture-uri vechi nu se execută pe această instanță până la izolarea explicită a datelor și rescrierea teardown-ului. Vezi [ghidul operațional](README.md) pentru inventar.

## Teste JavaScript și browser

Folosește `./test-node.sh test NumeExtensie` sau `./test-node.sh test --all` pentru testele Node, respectiv `./test-node.sh playwright NumeExtensie` pentru testele browser. Comanda `test` execută toate fișierele `*.test.js` și `.mjs` din `tests/`, cu excepția directorului `tests/browser/`; pentru acesta folosește comanda `playwright`. Wrapperul folosește imaginea Node LTS `node:24.22.1-bookworm-slim` și imaginea Playwright `mcr.microsoft.com/playwright:v1.61.1-noble`, potrivită cu lockfile-urile existente. Nu instala Node pe host și nu adăuga `node_modules` ori binare în Git. Pentru suita browser GeneratorPerioadeCursuri, setează `ESPO_BASE_URL=http://178.104.183.29` și credențialele de test prin variabile de mediu; instalarea extensiei în runtime rămâne o condiție separată.
