# Dezvoltare și testare EspoCRM

Repository-ul conține extensiile din directoarele de la rădăcină și harness-ul comun [`build.sh`](build.sh). Regulile pentru agenți sunt în [`AGENTS.md`](AGENTS.md); convențiile suplimentare GeneratorPerioadeCursuri rămân în directorul extensiei.

## Instanța persistentă de test

URL: `http://178.104.183.29` · Compose: `/var/www/espocrm/docker-compose.yml` · serviciu aplicație: `espocrm`. Verifică mediul înainte de instalare sau integrare:

```bash
docker compose -f /var/www/espocrm/docker-compose.yml ps
docker compose -f /var/www/espocrm/docker-compose.yml exec -T espocrm php command.php version
docker compose -f /var/www/espocrm/docker-compose.yml exec -T espocrm php -v
docker compose -f /var/www/espocrm/docker-compose.yml exec -T espocrm php -r '$c=require "data/config.php"; echo $c["siteUrl"] ?? "<missing>", PHP_EOL;'
```

La migrare, mediul a raportat EspoCRM 10.0.10, PHP 8.4.27 și URL-ul de mai sus. Citește separat compatibilitatea din fiecare `manifest.json`. `EmployeeManagement` 0.8.15 cere exact EspoCRM 10.0.1 și PHP 8.4.23, așa că nu se instalează pe această instanță sub manifestul actual.

## Flux de lucru

Modifică sursele aici, rulează `./build.sh --extension ./NumeExtensie --validate` și testele relevante, apoi construiește ZIP-ul candidat numai când testezi sau livrezi o schimbare de extensie. Verifică inventarul și compatibilitatea înainte de instalarea nativă în EspoCRM de test. Rulează probele de integrare, API, ACL și browser cu date sintetice marcate unic și cleanup pe ID-uri exacte. Predă URL-ul și rezultatele pentru aprobarea vizuală; numai după aceea livrează același ZIP validat cu SHA-256. Instalarea în producție revine utilizatorului.

Hostul acestui workspace nu are PHP sau Node instalate. Pentru verificările statice se poate folosi CLI-ul imaginii EspoCRM cu repository-ul montat read-only, de exemplu:

```bash
docker run --rm --entrypoint bash -v /opt/espo_extensions:/repo:ro -w /repo espocrm/espocrm:fpm ./build.sh --extension ./GeneratorPerioadeCursuri --validate
```

Aceasta rulează doar harness-ul static; nu pornește o altă instanță EspoCRM.

## Node.js și Playwright fără instalare pe host

Wrapperul [`test-node.sh`](test-node.sh) folosește Docker cu Node.js LTS
`24.22.1` fixat în imaginea `node:24.22.1-bookworm-slim`. Ambele imagini sunt
fixate și prin digest în wrapper. Descoperă testele
`*.test.js` și toate scripturile `.mjs` din `tests/`, excluzând
testele browser:

```bash
./test-node.sh test --all
./test-node.sh test DocumentBuilder
./test-node.sh node --test AttendanceManagement/tests/contract.test.mjs
```

Pentru browser folosește imaginea Playwright deja utilizată de repository,
`mcr.microsoft.com/playwright:v1.61.1-noble`, care corespunde versiunii din
lockfile-urile existente și include Node.js 24.17.0. Wrapperul rulează automat
`npm ci` în container;
`node_modules` stă într-un volum Docker temporar și nu intră în Git:

```bash
./test-node.sh playwright AttendanceManagement
./test-node.sh playwright HolidayManagement
ESPO_BASE_URL=http://178.104.183.29 ./test-node.sh playwright GeneratorPerioadeCursuri --list
```

Ultima suită folosește instanța aprobată doar când extensia compatibilă este
instalată și sunt disponibile `ESPO_ADMIN_USERNAME` și `ESPO_ADMIN_PASSWORD`
pentru conturi de test. `--list` verifică descoperirea testelor fără autentificare.
Rulează `./test-node.sh --help` pentru formele complete. Containerele Node și
Playwright sunt efemere; ele nu creează instanțe EspoCRM.

## Teste care cer adaptare

| Teste | Situație și condiție de reutilizare |
|---|---|
| `EmployeeManagement/tests/integration/` | Legate de EspoCRM 10.0.1, PHP 8.4.23, `127.0.0.1:18089`, utilizatori/ID-uri și fixture-uri de laborator. Unele schimbă configurări, date sau lifecycle. Necesită compatibilitate declarată, fixture-uri sintetice proprii, izolare per rulare și cleanup verificat înainte de portare. Nu le executa pe instanța persistentă. |
| `HolidayManagement/tests/phase-001/docker.ps1` și `phase-002/docker.ps1` | Pornesc proiecte Compose temporare, folosesc parole fixe și `down -v`; presupun o bază disposable. Păstrează-le ca probe istorice; creează o suită nouă cu date marcate și teardown exact dacă este necesară integrarea persistentă. |
| `DocumentBuilder/tests/phase-03`, `phase-04` și ghidurile vechi de runtime | Verificările de sursă sunt fixate pe snapshot EspoCRM 10.0.0/Dompdf 3.1.5. Pentru 10.0.10 trebuie verificate separat API-urile și rendererul; testele manuale runtime cer fixture-uri și izolare. Nu trata snapshotul ca validare runtime curentă. |
| `ZileSarbatoare/docs/phase-6-runtime-validation.md` | Pașii de uninstall/reinstall, schimbarea setărilor și sincronizarea externă cer backup, date de test izolate și plan de restaurare; pașii de ștergere/upgrade nu se execută automat. |
| `GeneratorPerioadeCursuri/tests/integration/` și `tests/browser/` | Au garduri pentru instanța aprobată, dar rulează numai după instalarea nativă a unei versiuni compatibile și, pentru browser, după furnizarea credențialelor de test prin mediu. |

Rapoartele `*RESULTS*`, fișierele `tests/evidence/`, documentele de fezabilitate și jurnalul vechi de schimbări consemnează execuții trecute; nu sunt instrucțiuni curente și nu au fost rescrise.
