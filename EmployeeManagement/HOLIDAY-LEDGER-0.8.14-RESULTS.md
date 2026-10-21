# Employee Management 0.8.14 — Holiday Ledger Results

## Rezultat

Interfața `#EmployeeHolidayLedger` are acum spațiul vizual Employee Management cerut, dar rămâne construită peste lista Record nativă EspoCRM 10.0.1. Nu a fost introdus un API Ledger, un motor separat de filtrare, SQL personalizat, o pagină HTML paralelă sau ocoliri ACL. Ledger-ul și serviciile sale contabile nu au fost modificate.

## Arhitectura nativă folosită

- `controllers/record` și Record API pentru listă și detaliu;
- layout-urile native `list.json`, `listSmall.json`, `detail.json` și `filters.json`;
- metadata `entityDefs.collection` pentru ordonarea implicită `createdAt DESC`;
- filtrul link nativ `assignedUser`, cu selectorul standard User prezentat direct în antet managerului;
- paginarea, sortarea, căutarea, navigarea, loading/error/empty state native;
- Roles/ACL/FLS native și filtrele de acces aplicate de Record API;
- traducerile native din metadata i18n;
- `iconClass: fas fa-history` din `clientDefs`, pentru tabul/sidebar-ul nativ.

Layout-urile native sunt păstrate pentru câmpuri, listă, listSmall și detaliu, dar nu au fost suficiente pentru prezentarea vizuală solicitată. View-ul paginii extinde `views/list`, view-ul rândurilor extinde `views/record/list`, iar selectorul este `views/fields/user`. `SearchManager`, colecția, Record API, sortarea și paginarea rămân cele native. CSS-ul nou este izolat sub `.employee-holiday-ledger-page`. Rendererul existent pentru `delta` extinde în continuare `views/fields/float`. Nu a fost necesar PHP nou, controller nou sau endpoint nou.

## ACL Own / All

Auditul EspoCRM 10.0.1 a confirmat că `OwnerUserFieldProvider` identifică linkul existent `assignedUser` către `User` drept câmp de ownership. Filtrul nativ `OnlyOwn` aplică `assignedUserId = utilizatorul autentificat`, iar accesul direct la entitate este verificat de `DefaultOwnershipChecker`. `DefaultFilterResolver` aplică nivelurile efective `Own`, `All` sau `No` la interogările Record API.

Testele runtime din laborator au confirmat:

- `Read: Own`: numai cele 3 înregistrări proprii, detaliul propriu HTTP 200, detaliul altui angajat HTTP 403;
- un filtru construit manual cu `assignedUserId` al altui angajat returnează listă goală;
- `Read: All`: managerul vede mai mulți angajați, filtrează un angajat, golește filtrul și își poate filtra propriul istoric;
- un Role moștenit prin Team acordă corect `Read: All`;
- `Read: No` refuză lista și detaliul;
- FLS ascunde `reason`, iar câmpurile interne `idempotencyKey` și `operationHash` sunt interzise prin entity ACL;
- create, update și delete sunt refuzate.

Rolurile nu sunt modificate la instalare. Administratorul trebuie să configureze manual în mediul țintă:

- angajați: `EmployeeHolidayLedger → Read: Own`, Create/Edit/Delete `No`;
- manageri: `EmployeeHolidayLedger → Read: All`, Create/Edit/Delete `No`;
- managerii trebuie să aibă și accesul nativ de citire `User` corespunzător angajaților pe care selectorul trebuie să-i găsească.

Numele și ID-urile Rolurilor nu sunt folosite în logica extensiei.

## Interfață

Pagina are antetul „Istoric concedii / Holiday History”, descriere, icon, panou compact de istoric, contor, loading, empty și error state. Lista desktop afișează, în ordine: data operațiunii, operațiunea, angajatul, soldul anterior, modificarea, soldul ulterior și actorul. Fiecare rând are accent semantic pentru adăugare, deducere sau modificare zero; soldul rezultat și delta sunt evidențiate fără rezumate calculate. Lista mobilă folosește layout-ul compact nativ și afișează data, operațiunea, modificarea și soldul rezultat fără overflow la nivelul viewport-ului. Data rămâne link nativ către detaliu.

Filtrarea managerului folosește selectorul EspoCRM `User`, vizibil în antet, și scrie filtrul `assignedUser` în `SearchManager` nativ. „Toți angajații” șterge filtrul, iar „Istoricul meu” selectează utilizatorul autentificat. Colecția este reîncărcată prin Record API, unde ACL-ul se aplică din nou. Angajatului cu `Read: Own` nu i se afișează selectorul, iar lista rămâne limitată nativ la propriile înregistrări.

## Fișiere modificate sau adăugate

- `manifest.json`, `README.md`, `MIGRATION-INVENTORY.json`, `Tools/Workspace/Api/GetStatus.php` — versiune și documentație 0.8.14;
- `Resources/metadata/entityDefs/EmployeeHolidayLedger.json` — ordonare implicită și renderer pentru `delta`;
- `Resources/metadata/scopes/EmployeeHolidayLedger.json` — nivelurile Role native: Read All/Own/No și write No;
- `Resources/metadata/entityAcl/EmployeeHolidayLedger.json` — protecția câmpurilor tehnice;
- `Resources/layouts/EmployeeHolidayLedger/list.json` — coloanele desktop;
- `Resources/layouts/EmployeeHolidayLedger/listSmall.json` — lista compactă nativă;
- `Resources/layouts/EmployeeHolidayLedger/detail.json` — panourile read-only;
- `Resources/layouts/EmployeeHolidayLedger/filters.json` — filtrele native;
- `Resources/i18n/{ro_RO,en_US}/EmployeeHolidayLedger.json` — etichete și panouri;
- `Resources/metadata/clientDefs/EmployeeHolidayLedger.json` — view-urile native extinse și `iconClass`;
- `Resources/metadata/app/client.json` — încărcarea foii CSS Ledger;
- `files/client/.../views/holiday-ledger/list.js` — shell vizual peste `views/list`, selector User și stări;
- `files/client/.../views/holiday-ledger/record/list.js` — clase semantice peste rândurile Record native;
- `files/client/.../css/holiday-ledger.css` — stiluri izolate desktop/mobil;
- `files/client/.../views/fields/signed-holiday-days.js` — formatare semnată peste rendererul float nativ;
- `tests/regression/holiday-ledger-native-ui.test.mjs` — contract UI/metadata;
- `tests/integration/holiday-ledger-runtime.php` — ACL/API/FLS runtime;
- `tests/integration/holiday-ledger-browser.cjs` — Chromium desktop/mobil;
- `tests/integration/holiday-ledger-upgrade.php` — hash-uri înainte/după upgrade;
- `tests/contract.py` — contractul arhivei 0.8.14.

Nu au fost modificate `HolidayProfileService::createLedger()`, `ReadOnlyAccessChecker`, guard-urile read-only, controllerul Ledger existent sau alte servicii Holiday/Attendance.

## Teste

- metadata/UI Ledger: PASS;
- runtime ACL/API/FLS: 21/21 PASS;
- Chromium real, desktop și mobil: 26/26 PASS;
- listă în engleză și mobil în română: PASS;
- filtrare `assignedUser`, resetarea filtrului, navigare în detaliu și paginare: PASS;
- valori `+5`, `−3`, `0` și traduceri pentru tipuri: PASS;
- PHP syntax pentru toate fișierele modulului: PASS;
- DOCX verification 0.8.13: PASS (18/18 JS și regresia PHP completă);
- Holiday approval email 0.8.12: PASS (13/13);
- Attendance manager controls: PASS (7/7);
- Holiday polling: PASS (12/12);
- package/extension contract: PASS.

Capturile Chromium din laborator:

- înainte, listă Record implicită: `/tmp/employee-feasibility-20261020-yDmmnL/employee-holiday-ledger-0.8.14-desktop-list.png`
- angajat desktop: `/tmp/employee-feasibility-20261020-yDmmnL/employee-holiday-ledger-0.8.14-employee-desktop-after.png`
- manager desktop cu selector: `/tmp/employee-feasibility-20261020-yDmmnL/employee-holiday-ledger-0.8.14-manager-desktop-after.png`
- manager filtrat: `/tmp/employee-feasibility-20261020-yDmmnL/employee-holiday-ledger-0.8.14-manager-filtered-after.png`
- angajat mobil: `/tmp/employee-feasibility-20261020-yDmmnL/employee-holiday-ledger-0.8.14-employee-mobile-after.png`
- icon sidebar: `/tmp/employee-feasibility-20261020-yDmmnL/employee-holiday-ledger-0.8.14-sidebar-icon-after.png`

Validarea vizuală confirmă că pagina diferă efectiv de lista Record implicită, păstrează limbajul vizual Holiday/Attendance, selectorul managerului este evident, iar lista mobilă arată data, operațiunea, delta și soldul rezultat. Engleza și româna au fost testate. Nu au existat erori client sau overflow de viewport.

## Upgrade și conservarea datelor

Upgrade-ul izolat 0.8.13 → 0.8.14 a trecut fără diferențe. Numerele și hash-urile înainte/după au fost identice:

| Date | Număr | SHA-256 |
|---|---:|---|
| EmployeeHolidayLedger | 897 | `b7711b76c757b259264cd4dc05be55d43b53d005c76f1b35b6c409398c8541eb` |
| EmployeeHolidayRequest | 314 | `16ab29d5369c597ebd3ed61467d9a1a9ad36c2471a194313761b6177a35691b8` |
| EmployeeHolidayProfile | 346 | `e9675d45744699a63f00c83159cd59a710befe2bdccf8248d15040886bdaa60e` |
| EmployeeHolidayDocument | 4 | `54cadb3aadb1ec97c4fdcecc2adda6f41e86a6bf00d4aaae05e1176df9602526` |
| Holiday attachments | 113 | `a9ce0793181f320c4b2d561e19716bdbf23a6eae01171d839d84ae176da8fc65` |
| Role | 503 | `8c29d70d64dadec7ca491df36187086184ac7e4ce9395b1d7ac8fa0d4b05b7c1` |

Nu s-au modificat Ledger-ul, soldurile, drepturile anuale, cererile, documentele, atașamentele sau Rolurile existente. Rolurile sintetice folosite pentru ACL au fost create numai după comparația de upgrade, exclusiv în laborator.

Instalarea repetată în laborator a pachetului corectat peste 0.8.14 a păstrat, de asemenea, identice 902 Ledger entries, 315 cereri, 349 profiluri, 4 documente, 113 atașamente și 507 Roluri. Toate hash-urile înainte/după au fost identice.

## Procedură pentru o instanță care are deja 0.8.14

Arhiva corectată păstrează intenționat versiunea 0.8.14. Dacă Extension Manager acceptă instalarea pachetului corectat peste aceeași versiune, se poate folosi fluxul normal, urmat de Rebuild și Clear Cache. Dacă instanța refuză reinstalarea aceleiași versiuni, nu se modifică manifestul și nu se forțează Extension Manager-ul:

1. se face backup și se intră într-o fereastră de mentenanță;
2. se copiază numai fișierele frontend/metadata enumerate mai sus din arhiva corectată în directoarele `custom/` și `client/custom/` ale instanței, păstrând owner-ul utilizatorului web;
3. se rulează comenzile native `php command.php rebuild` și `php command.php clear-cache` din rădăcina EspoCRM;
4. utilizatorii reîncarcă aplicația fără cache și se verifică ruta `#EmployeeHolidayLedger`.

Aceasta este o actualizare a extensiei din spațiul `custom`, nu un patch al fișierelor core EspoCRM. Nu se dezinstalează extensia și nu se rulează migrare sau modificare de date.

## Pachet

- Arhivă: `dist/employee-management-0.8.14.zip`
- SHA-256 al pachetului 0.8.14: `7286532624f8a5459e495b29627d06721db775f177392f9da37dfb3c7ed1ea13`
- Validarea DOCX, Attendance și polling din versiunile anterioare rămâne inclusă în regresii.

Laboratorul izolat a fost folosit pentru instalare, API și Chromium. Producția nu a fost accesată.
