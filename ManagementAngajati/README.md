# ManagementAngajati

Extensie EspoCRM pentru gestionarea prezenței, condicii de prezență, concediilor și profilurilor angajaților.

Extensia este în dezvoltare și nu trebuie instalată în producție înaintea validării funcționale, vizuale și a calculelor de concediu.

## Compatibilitate

- EspoCRM: dezvoltată și testată pe `10.0.10`;
- PHP: `8.3` sau mai nou;
- dependență: extensia `Zile Sărbătoare` versiunea `0.9.5` sau mai nouă;
- Node.js: `18` sau mai nou;
- npm: `8` sau mai nou;
- Composer este necesar pentru construirea pachetului final; arhiva ZIP este creată prin biblioteca Node blocată în proiect.

Compatibilitatea declarată pentru instalare se află în `manifest.json` și `extension.json`. Nu modifica intervalele doar pentru a forța instalarea pe o versiune neverificată.

## Structura proiectului

Acesta este un proiect modern bazat pe instrumentele oficiale EspoCRM:

```text
ManagementAngajati/
├── src/
│   ├── files/       # fișierele instalate în EspoCRM
│   └── scripts/     # AfterInstall și AfterUninstall
├── tests/           # teste unitare și de integrare
├── build.sh         # bootstrap, validare și ZIP final
├── build.js         # build oficial espo-extension-tools
├── extension.json   # configurarea proiectului de build
├── manifest.json    # manifestul final al extensiei
├── package.json
├── package-lock.json
├── composer.json
└── composer.lock
```

În repository codul rămâne în `src/files` și `src/scripts`. La build, instrumentele EspoCRM generează pachetul instalabil cu structura cerută:

```text
manifest.json
README.md
files/
scripts/
```

Nu crea manual încă o copie `files/` la rădăcina proiectului. Aceasta ar duplica sursele și ar putea împacheta JavaScript necompilat.

## Pornire pe un server nou

### 1. Clonează repository-ul

```bash
git clone <adresa-repository> espo_extensions
cd espo_extensions/ManagementAngajati
```

Repository-ul nu conține parole, baza de date, instanța EspoCRM de test, `node_modules`, `vendor` sau fișiere generate.

### 2. Instalează cerințele sistemului

Verifică:

```bash
node --version
npm --version
php --version
composer --version
```

Instalează versiunile compatibile enumerate mai sus înainte de a continua.

### 3. Instalează dependențele proiectului

```bash
./build.sh --bootstrap
```

Comanda folosește exact versiunile blocate în `package-lock.json`, în `composer.lock` și în lockfile-ul Composer al modulului. Sunt instalate separat instrumentele de test și biblioteca PHPWord utilizată de extensie.
De asemenea, descarcă EspoCRM `10.0.10` în directorul local ignorat `site/` și instalează dependențele necesare testelor. Nu creează și nu inițializează o bază de date.

### 4. Verifică sursele

```bash
./build.sh --validate
```

Aceasta rulează:

- toate testele PHPUnit;
- analiza statică PHPStan;
- verificarea concordanței versiunilor din `package.json` și `manifest.json`.

### 5. Construiește extensia instalabilă

Din directorul extensiei:

```bash
./build.sh --zip
```

Sau din rădăcina repository-ului:

```bash
./build.sh --extension ./ManagementAngajati --zip
```

ZIP-ul final și suma SHA-256 sunt create în:

```text
dist/management-angajati-<versiune>.zip
```

Scriptul verifică faptul că arhiva conține `files/`, `scripts/` și bundle-ul frontend compilat. Manifestul final include dependența de `Zile Sărbătoare`.

## Pregătirea unei instanțe locale EspoCRM

Această secțiune este necesară numai dacă noul server va găzdui și mediul de dezvoltare EspoCRM.

1. Copiază configurația locală:

   ```bash
   cp config-default.json config.json
   ```

2. Păstrează `espocrm.branch` pe `10.0.10` și completează în `config.json` conexiunea către baza de date locală, URL-ul instanței și utilizatorul/grupul webserverului.
3. Nu comite `config.json`, parole, tokenuri sau baza de date.
4. Pune ZIP-urile dependențelor locale în `extensions/` numai dacă vrei ca build-ul complet să le instaleze automat.
5. Construiește instanța de dezvoltare:

   ```bash
   npm run all
   ```

Instanța rezultată se află în `site/`. Directorul `site/` este local, nu este sursă și nu intră în Git.

## Fluxul zilnic de dezvoltare

Sursa de adevăr este acest director din repository. Modifică numai fișierele din `src/` și testele din `tests/`.

Pentru copierea schimbărilor în instanța locală `site/`:

```bash
npm run sync
npm run rebuild
```

Rulează scriptul AfterInstall numai când s-a schimbat inițializarea ori configurarea instalării:

```bash
node build --after-install
```

Înainte de predare:

```bash
./build.sh --validate
./build.sh --zip
```

Instalează exact ZIP-ul rezultat într-o instanță de test prin mecanismul nativ EspoCRM. Nu copia manual fișiere în producție.

## Comenzile repository-ului comun

Din `/opt/espo_extensions` sunt disponibile:

```bash
./test-php.sh validate ManagementAngajati
./test-php.sh validate --all
./test-php.sh build ManagementAngajati
```

`validate` execută validarea statică a manifestului, JSON-ului și PHP-ului într-un container fără rețea. Pentru proiectul modern, `build` deleagă generarea către `ManagementAngajati/build.sh`, deci hostul trebuie să aibă dependențele pregătite prin `--bootstrap`.

## Versiuni

Versiunea trebuie să fie identică în:

- `package.json`;
- `package-lock.json`;
- `manifest.json`.

După schimbarea versiunii npm, actualizează și `manifest.json`, apoi rulează validarea completă. Build-ul refuză versiuni diferite.

## Ce nu se păstrează în Git

- `site/` și baza sa de date;
- `node_modules/`;
- `vendor/`;
- `build/` și ZIP-urile generate;
- `config.json`, `config.php` și fișiere `.env`;
- parole, cookie-uri, tokenuri sau fișiere de autentificare;
- documente și date generate în timpul testelor.

Datele existente ale angajaților, prezențele, soldurile și cererile de concediu se află în baza de date EspoCRM. Mutarea surselor nu migrează aceste date.

## Reguli de livrare

1. Rulează testele și analiza statică.
2. Construiește un singur ZIP candidat.
3. Notează suma SHA-256.
4. Instalează acel ZIP în instanța de test.
5. Verifică rolurile angajat, manager și administrator.
6. Obține aprobarea vizuală și funcțională.
7. Livrează în producție exact același ZIP verificat.

