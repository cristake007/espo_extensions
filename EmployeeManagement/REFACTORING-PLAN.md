# EmployeeManagement — plan de refactorizare arhitecturală

Audit realizat pe codul local curent din `/opt/espo_extensions`, pe instanța de test aprobată `http://178.104.183.29` și pe sursele EspoCRM 10.0.10 instalate acolo. Data auditului: 2026-10-22.

## 1. Executive Summary

EmployeeManagement poate fi refactorizată fără schimbarea regulilor de business și fără pierderea designului. Ținta fezabilă este un singur stylesheet și un singur fișier JavaScript fizic. Pentru JavaScript, fișierul unic trebuie să conțină module AMD **numite**, păstrând identificatorii actuali (`employee-management:...`); simpla concatenare nu este validă. Bundle-ul trebuie declarat atât în `scriptList`, cât și în `developerModeScriptList`, deoarece EspoCRM nu expune în metadata extensiei un mecanism suportat pentru înregistrarea lazy a unui bundle nou. Consecința măsurată este încărcarea globală a aproximativ 49,9 KiB compresați pentru EmployeeManagement, în schimbul eliminării fetch-urilor JS la navigarea prin extensie.

Problema prioritară este controlul accesului. Verificările `isRegular()` blochează administratorii nativi în frontend, API, access checkers, servicii și feed-uri calendar înainte ca ACL-ul Espo să decidă. Planul cere eliminarea acestor gate-uri aplicate actorului și revenirea la `Acl`, action levels, FLS și `checkEntity` native; nu se introduce o clasă, permisiune sau ramură specială pentru administrator. În paralel, cele 22 de scopes combină permisiuni de pagină, capabilități și acces la entități, iar numeroase operațiuni cer simultan un boolean și `read: all`. Planul propune 17 scopes: cele 9 scopes de entitate, păstrate ca niveluri de acces la date, și 8 capabilități clare. Migrarea Roles este obligatorie, idempotentă, tranzacțională și fail-closed; nu se șterge sau redenumește nimic înainte de migrarea și verificarea rolurilor existente.

Integrarea cromatică trebuie inversată: EmployeeManagement consumă numai tokenii semantici ai temei active, iar TuvtkTheme completează și suprascrie familia nativă Espo `--brand-*`, `--state-*`, `--btn-*` și `--panel-*`. Astfel extensia păstrează verdele, ambra, roșul și albastrul distincte și rămâne independentă de TuvtkTheme.

Rezultatul auditului este:

- **13 MUST FIX**;
- **10 SHOULD IMPROVE**;
- **7 KEEP AS IS**;
- **3 NEEDS DECISION**.

Acest document este exclusiv un plan. Nu s-au modificat surse CSS/JS/PHP/metadata, Roles, ACL, baza de date sau versiuni și nu s-a construit ori instalat niciun ZIP.

## 2. Inventarul tehnic actual

### 2.1 Starea repository-ului și diferențele față de GitHub

La începutul auditului, `main`, `origin/main` și GitHub indicau același commit: `23fe52a2374185cdc80a5e9d117119ca820da12c`. Working tree era deja modificat; aceste schimbări preexistau și au fost păstrate:

```text
 M EmployeeManagement/MIGRATION-INVENTORY.json
 M EmployeeManagement/README.md
 M EmployeeManagement/files/custom/Espo/Modules/EmployeeManagement/Tools/Workspace/Api/GetStatus.php
 M EmployeeManagement/manifest.json
 M EmployeeManagement/tests/contract.py
 M TuvtkTheme/README.md
 M TuvtkTheme/files/client/custom/css/tuvtk.css
 M TuvtkTheme/manifest.json
 M TuvtkTheme/tests/browser/authenticated-navigation.spec.mjs
 M TuvtkTheme/tests/browser/login-theme.spec.mjs
 M TuvtkTheme/tests/theme-architecture.test.mjs
?? EmployeeManagement/ARCHITECTURE-AUDIT.md
```

Diferențele relevante sunt actualizarea locală EmployeeManagement `0.8.15 → 0.8.16`, declararea EspoCRM 10.0.10/PHP 8.4.27 și actualizarea statusului raportat; TuvtkTheme trece `1.0.19 → 1.0.20` și conține corecții pentru bara laterală. Codul local actual este sursa principală a acestui plan. Fișierul neversionat `EmployeeManagement/ARCHITECTURE-AUDIT.md` este incomplet și descrie 0.8.15; nu a fost modificat sau înlocuit.

### 2.2 Runtime și compatibilitate verificată

- Serviciile din `/var/www/espocrm/docker-compose.yml` erau pornite și sănătoase.
- `siteUrl` configurat este exact URL-ul aprobat.
- Runtime: EspoCRM 10.0.10 și PHP 8.4.27.
- Instalate: EmployeeManagement 0.8.16, TuvtkTheme 1.0.20 și dependența de concedii 0.9.5.
- `EmployeeManagement/manifest.json:1-18` declară `acceptableVersions: ["10.0.1", "10.0.10"]` și PHP `["8.4.23", "8.4.27"]`; TuvtkTheme declară EspoCRM `>=10` și PHP `>=8.4`.
- Verificări read-only executate: `./test-php.sh validate EmployeeManagement` — PASS; `./test-node.sh test EmployeeManagement` — PASS, 29/29, plus regresiile pentru aprobări, documente și ledger. Nu s-a rulat build.

### 2.3 Inventar CSS

`files/client/custom/modules/employee-management/css/` conține:

| Fișier | Linii | Octeți | Încărcare actuală |
|---|---:|---:|---|
| `attendance.css` | 2.723 | 68.406 | globală, primul în listă |
| `holiday.css` | 895 | 22.225 | globală |
| `administration.css` | 729 | 18.862 | globală |
| `holiday-ledger.css` | 406 | 10.309 | globală, ultimul |
| **Total** | **4.753** | **119.802** | toate la bootstrap |

Ordinea este fixată în `files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/app/client.json:2-8`. În browser, cele patru resurse au însumat 16.197 octeți transferați/14.997 encoded la bootstrap, înainte de accesarea extensiei.

### 2.4 Inventar JavaScript

Cele 28 de module însumează 5.059 linii și 234.156 octeți necomprimați:

| Zonă | Module actuale și interval integral |
|---|---|
| Controllers | `src/controllers/attendance-overview.js:1-10`; `attendance.js:1-11`; `workspace.js:1-105` |
| Administration | `src/views/administration/index.js:1-85`; `profiles.js:1-509`; `settings.js:1-304` |
| Attendance | `src/views/attendance/my.js:1-425`; `overview.js:1-740`; `schedules.js:1-445` |
| Field views | `src/views/fields/annual-reset-date.js:1-35`; `attendance-test-data-reset.js:1-148`; `signed-holiday-days.js:1-16`; `test-data-reset.js:1-134` |
| Holiday request/document | `src/views/holiday-request/index.js:1-574`; `holiday-request/fields/managed-employee.js:1-28`; `holiday-request/fields/start-date.js:1-11`; `holiday-document/verification.js:1-121` |
| Holiday management | `src/views/holiday-management/index.js:1-211` |
| Holiday ledger | `src/views/holiday-ledger/list.js:1-261`; `holiday-ledger/record/list.js:1-27` |
| Modals | `src/views/modals/attendance-correction.js:1-66`; `balance-correction.js:1-144`; `cancellation-reason.js:1-66`; `complete-initial-setup.js:1-100`; `correction-history.js:1-79`; `managed-booking.js:1-294`; `managed-employee-select.js:1-91` |
| Workspace | `src/views/workspace.js:1-19` |

Măsurare browser autentificată pe instanța aprobată:

| Moment | JS total | JS EmployeeManagement | Transfer EmployeeManagement |
|---|---:|---:|---:|
| Bootstrap Espo | 5 fișiere / 787.319 B | 0 | 0 |
| Prima navigare `#EmployeeManagement` ca admin | +1 | `workspace.js` | 1.288 B transfer / 988 B encoded; apoi 403 frontend |
| Încărcare controlată a tuturor modulelor prin loader | +27 | restul modulelor | 56.987 B transfer / 48.887 B encoded |
| Total maxim actual | 28 | toate | 58.275 B transfer / 49.875 B encoded |

Existența a 28 de fișiere nu înseamnă încărcarea tuturor la bootstrap. Loaderul aduce controllerul/view-ul solicitat și ulterior modulele noi ale paginilor sau modalelor accesate. Măsurarea completă a fiecărei pagini cu interacțiuni nu a fost posibilă cu administratorul configurat deoarece 403-ul actual este defectul auditat; repetarea măsurării după remedierea M-01 este criteriu de acceptare.

Graful din `Resources/metadata/app/clientRoutes.json:2-85` și controllers arată costul incremental așteptat pentru un utilizator autorizat, care trebuie confirmat după M-01: prima deschidere workspace = controller `workspace` + view `workspace` (2); Holiday după workspace = view `holiday-request/index` (1), apoi câte un modal numai la acțiune; Attendance = controller `attendance` + view `attendance/my` (2); Overview = controller `attendance-overview` + view `attendance/overview` (2); Administration după workspace = view-ul secțiunii (1); Holiday Management/Verification = view-ul respectiv (1). Modulele deja încărcate nu se transferă din nou în aceeași sesiune SPA. Aceste valori sunt analiza loaderului, nu sunt prezentate drept măsurătoare reușită a paginilor blocate.

### 2.5 Metadata, entități și backend

- `Resources/metadata/app/acl.json:2-27` declară 22 de scopes: 9 de entitate și 13 booleene.
- Entitățile EmployeeManagement sunt: `EmployeeAbsence`, `EmployeeAttendanceMonth`, `EmployeeAttendanceRecord`, `EmployeeAttendanceWorkSchedule`, `EmployeeHolidayRequest`, `EmployeeHolidayProfile`, `EmployeeHolidayLedger`, `EmployeeHolidayDocument` și `EmployeeHolidayDocumentVerificationLog`.
- Toate cele nouă au `customizable: false`; șapte permit Layout Manager, iar documentele și logurile de verificare au `layouts: false`.
- `EmployeeAbsence`, luna, pontajul, programul, profilul, cererea și ledger-ul au access checkers/hook-uri read-only. Documentele și logurile dezactivează doar acțiuni în UI (`recordDefs/...:2-8`), fără protecție backend echivalentă.
- Backend-ul activ însumează aproximativ 7.163 linii. Serviciile critice folosesc tranzacții, locks, snapshot-uri și idempotency; acestea nu trebuie rescrise în refactorizarea UI/ACL.

### 2.6 Registrul constatărilor

Fiecare intrare include dovada, cauza, mecanismul Espo relevant, schimbarea, dependențele, riscul, pașii și verificarea. Detaliile și matricele apar în secțiunile tematice.

| ID / clasificare | Dovadă, comportament și cauză | Mecanism și soluție concretă | Fișiere/dependențe, risc și pași | Acceptare și regresie |
|---|---|---|---|---|
| **M-01 MUST FIX** | `controllers/workspace.js:7,14,47,60,74,95`, `controllers/attendance.js:4`, `controllers/attendance-overview.js:4` și checkerele PHP resping `!isRegular()`. Admin/super-admin primesc 403 sau UI ascuns înainte ca ACL-ul nativ să decidă. | Se elimină gate-urile de tip utilizator aplicate actorului, fără înlocuire cu `isAdmin()` și fără clasă/politică specială. Controllers și backend folosesc direct `Acl::check`/`checkScope`, `getLevel`, FLS și `checkEntity`; `DefaultTable` acordă nativ administratorului nivelul maxim. | Toate punctele din §8; depinde de teste pe administrator și Roles. Riscul este eliminarea accidentală a unui `isRegular()` care verifică beneficiarul. Pași: clasificare actor/beneficiar, eliminare numai actor gate, păstrare verificări business, test API/UI. | Admin real accesează toate funcțiile prin ACL-ul core; regular rămâne limitat de Roles; utilizatorul fără ACL primește 403. Teste PHP + Playwright pe fiecare rută/acțiune. |
| **M-02 MUST FIX** | Decizia de acces este duplicată între controllers, `GetStatus.php:23-56`, checkere și servicii; UI poate diverge de API. | Endpoint-ul de status și UI reflectă direct aceleași rezultate native `Acl::check`, `checkEntity` și FLS folosite de backend. Access checkers existente pot compune cerințele operației, dar nu mai filtrează tipul actorului și nu introduc un sistem paralel. | Controllers, status API și checkere. Depinde de M-01/M-03. Risc mediu de diferențe UI/API; schimbare întâi backend, apoi frontend. | Aceeași matrice de acces în unit tests și browser; niciun buton afișat dacă API l-ar refuza și niciun 403 artificial pentru admin. |
| **M-03 MUST FIX** | `app/acl.json:2-27` are 22 scopes, iar checkerele cer booleene plus `read === all` (`AttendanceAccessChecker.php:20,25,50`; `HolidayRequestService.php:53,356,377,501`). | Separarea capabilităților de nivelurile entităților; 17 scopes țintă, `Acl::checkEntity()` și niveluri own/team/all. | Metadata scopes/aclDefs/i18n și toate checkerele. Depinde de M-04. Risc critic de modificare involuntară a drepturilor. | Matricea §7 este implementată exact; teste per rol/operație și comparație pre/post-migrare. |
| **M-04 MUST FIX** | Eliminarea/redenuminerea scopes ar lăsa Roles existente cu chei vechi sau drepturi pierdute; unele permisiuni sunt satisfăcute prin conjuncții. | Migrator idempotent AfterInstall, tranzacție per Role, backup, dry-run/report, marker de schemă, mapare fail-closed, curățarea cache-ului ACL. | `scripts/AfterInstall.php:12-29`, viitor serviciu de migrare, Role data. Depinde de M-03. Risc critic de escaladare/pierdere. | Re-rularea nu schimbă rezultatul; nicio permisiune nouă fără antecedent complet; rolurile ambigue sunt raportate și rămân restrictive. |
| **M-05 MUST FIX** | `EmployeeHolidayDocument` și `...VerificationLog` au doar UI disabled în `recordDefs:2-8`; lipsesc din `aclDefs`, deci API generic poate permite mutații în funcție de ACL. | `ReadOnlyAccessChecker` și hook-uri/servicii care blochează generic create/edit/delete/link; numai serviciile interne explicite pot scrie. Aceasta este o invariantă a entității, nu o restricție specială pentru admin. | `Resources/metadata/aclDefs`, `recordDefs`, `Classes/Acl/ReadOnlyAccessChecker.php:15-35`. Risc mare pentru integritatea documentelor/auditului. | CRUD generic = 403 pentru orice actor, inclusiv admin; fluxul funcțional intern de emitere/verificare continuă. Test API + regresie documente. |
| **M-06 MUST FIX** | Patru CSS globale, cascade dependentă de ordine și blocuri legacy/specializate suprapuse. | Un singur `employee-management.css`, structura exactă din §3, o singură intrare `cssList`. | Cele patru CSS și `app/client.json:2-8`. Depinde de baseline vizual. Risc mare de regresie vizuală. | Un singur request CSS, capturi/computed styles identice în temele TUVTK/Espo, desktop/mobil. |
| **M-07 MUST FIX** | Employee folosește aliasuri `--tuvtk-*`; TuvtkTheme definește primary/danger, dar nu familia semantică success/warning/info/state. | Employee consumă numai tokeni semantici Espo; TuvtkTheme suprascrie complet `--brand-*`, `--state-*`, `--btn-*`, `--panel-*`. | Toate CSS Employee și `TuvtkTheme/.../tuvtk.css:5-137`. Release separat înainte de consolidarea Employee. Risc de contrast/culoare greșită. | Toate stările rămân distincte; fără referințe `--tuvtk-*` în Employee; contrast WCAG verificat. |
| **M-08 MUST FIX** | 28 module sunt încărcate lazy; concatenarea anonimă ar încălca AMD. Loaderul rezolvă `employee-management:x` la fișier individual. | Un fișier fizic cu 28 `define('employee-management:...', deps, factory)` și înregistrare în `scriptList` + `developerModeScriptList`; ID-urile metadata/rute rămân. | Toate JS și `app/client.json`. Depinde de teste de loader/dev mode. Risc mare: duplicate/anonymous define sau ordine greșită. | Exact un JS Employee în Network, toate cele 28 ID-uri rezolvabile în prod/developer mode, fără dublă execuție. |
| **M-09 MUST FIX** | Entitățile nu au relație nativă `teams`; codul cere `all` și selectează utilizatori regular. Un rol acordat unei Team nu creează ownership de record. | Acum: query/provider comun care deleagă strict către ACL-ul și query access-control nativ, fără reguli proprii de autorizare și fără a expune fals nivelul team. Ulterior: link `teams`/`teamsIds`, propagare din beneficiarul User, backfill și query ACL-aware. | EntityDefs/scopes/checkere/providers. Depinde de M-03 și schema viitoare. Risc critic de acces cross-team. | În etapa curentă nicio lărgire; ulterior manager Team A nu vede Team B, admin vede tot prin core, teste negative obligatorii. |
| **M-10 MUST FIX** | `HolidayRequestService.php:431-473` tratează actorul curent ca beneficiar; un admin fără profil propriu nu poate gestiona corect soldul colegului. | Separare actor/beneficiar în toate comenzile; actorul este autorizat prin ACL-ul nativ, iar eligibilitatea și profilul se verifică pe beneficiarul regular. | Request/Profile services, API payloads și modale. Depinde de M-01. Risc mare de validare pe persoana greșită. | Admin fără profil propriu poate opera pentru angajat eligibil; nu poate crea concediu pentru beneficiar neeligibil. |
| **M-11 MUST FIX** | `GetStatus.php:47` raportează încă `businessMutationsEnabled: false`, eticheta approval spune „commands pending”, iar controllers blochează înainte de capabilitățile reale. | Status derivat direct din ACL-ul nativ, i18n actualizat și rute controlate prin rezultatele ACL ale serverului. | `GetStatus.php`, controllers, `Global.json`. Depinde de M-01/M-03. Risc de UI fals. | Status, butoane și API sunt coerente; nu mai există text stale. |
| **M-12 MUST FIX** | Cascadea legacy `attendance.css:1-1064` este suprapusă de blocurile scoped `1065-2723`; `!important` și media queries fragmentate maschează dependențe. | Inventar computed-style, mutare proprietate-cu-proprietate, eliminare numai după dovada neutilizării, responsive unic la final. | CSS și template-uri. Depinde de M-06. Risc mare de pixel drift. | Baseline vizual și stări interactive fără diferențe; fiecare `!important` rămas este justificat/allowlisted. |
| **M-13 MUST FIX** | Refactorizarea atinge UI, ACL și upgrade în jurul unor tranzacții/ledger/documente sensibile. | Porți de regresie pe servicii, upgrade și istoric înaintea fiecărui slice; algoritmii business nu se modifică. | Testele §15 și serviciile §11. Risc critic de corupere/rezultate diferite. | Suite PHP/JS/Playwright, snapshot de rezultate și verificare integritate istorică toate PASS înainte de ZIP candidat. |
| **S-01 SHOULD IMPROVE** | Antete/carduri/panouri/tabele repetă stiluri. | Clase native `.panel`, `.table`, `.btn`, `.label`, `.form-control` plus primitive Employee comune. | CSS/templates; depinde de M-06. Risc vizual mediu. | Mai puține reguli duplicate, computed styles neschimbate. |
| **S-02 SHOULD IMPROVE** | Polling-ul din `holiday-request/index.js:160-183,335-379,511-540` este local și nu oprește requestul in-flight. | Coordinator comun în bundle: un singur request, visibility/focus, backoff, cleanup și opțional abort. | View-ul request și eventual management. Depinde de M-08. Risc de race. | Niciun request după remove; maximum unul in-flight; backoff verificat cu mock timers. |
| **S-03 SHOULD IMPROVE** | După acțiuni locale se face frecvent refetch + rerender complet (`my.js:315-334`, request `542-572`). | Aplicare răspuns pe Model/Collection și evenimente `listenTo`; fetch doar la conflict/rezultat incomplet. | Views + contract API. Risc de stare stale. | O acțiune produce un request de mutație și update local; conflictul forțează fetch controlat. |
| **S-04 SHOULD IMPROVE** | `attendance/overview.js:445-480,524-579` reconstruiește matricea după schimbarea unei celule. | Subview/rând/celulă și recalcul numai pentru celula/sumarul afectat. | Overview view. Risc vizual mediu. | Contoare și celulă corecte fără full matrix rerender. |
| **S-05 SHOULD IMPROVE** | `schedules.js:50-53,65-195` conține handlers fără controale în template; `model.on` la `:255` nu este legat de lifecycle. | Eliminarea codului dovedit mort; `listenTo`; eliberare subviews/models. | Schedules view/templates. Risc mic. | Zero listener duplicat după 20 navigări; setările rămân în admin `settings.js:276-303`. |
| **S-06 SHOULD IMPROVE** | Ledger extinde `ListView`, dar dezactivează header/search la `list.js:78-80` și dublează filtrarea. | Refolosire search/filter/list lifecycle native și compunerea hero-ului custom. | Ledger list/metadata layouts. Risc mediu. | Filtrare, paginare, navigare și hero identice funcțional. |
| **S-07 SHOULD IMPROVE** | `administration/profiles.js:327-334,401-418,445-485` reîncarcă/re-randează după salvări. | Collection + row subviews; patch numai profilul/sumarul afectat. | Profiles view. Risc mediu. | O corecție nu reconstruiește întreaga pagină; sold/istoric rămân sincronizate. |
| **S-08 SHOULD IMPROVE** | Toate entitățile sunt `customizable:false`, inclusiv date operaționale cu risc redus. | Permitere controlată numai pentru AttendanceRecord/WorkSchedule după D-03; blocare câmpuri stock cu `customizationDisabled`, fără relationships/formula. | scope/entityDefs și teste contract. Risc de câmpuri ignorate de UI custom. | Câmp custom sigur poate fi creat și persistat; câmpurile critice nu pot fi alterate. |
| **S-09 SHOULD IMPROVE** | Nu există bugete automate pentru requesturi, bytes și rerender. | Playwright PerformanceResourceTiming + contoare instrumentate în test. | Browser tests. Risc mic. | Pragurile §15 opresc regresiile. |
| **S-10 SHOULD IMPROVE** | Etichete mixte/tehnice și lipsă pentru document/log; utilizatorul nu distinge pagina de operație. | Nume bilingve clare din §7; scopes interne etichetate explicit „intern”. | `i18n/ro_RO,en_US/Global.json`. Depinde de M-03. | Role UI este inteligibil fără documentație externă. |
| **K-01 KEEP AS IS** | Selectorul lună/an este intenționat (`attendance/my.js:337-407`). | Componentă custom păstrată; nu se înlocuiește cu date picker de zile. | Attendance UI. Risc dacă este schimbat. | Aceeași selecție lună/an, tastatură și mobil. |
| **K-02 KEEP AS IS** | Cardurile, matricea de pontaj și hero-urile sunt funcționale și definesc designul. | Se păstrează markup-ul/structura vizuală; doar reguli comune/native unde nu schimbă aspectul. | CSS/templates. | Capturi pixel/computed-style echivalente. |
| **K-03 KEEP AS IS** | Tranzacțiile, lock-urile, snapshot-urile, ledger-ul și idempotency sunt deja în serviciile PHP. | Nicio schimbare algoritmică în slice-urile UI/ACL. | Serviciile §11. | Testele business produc aceleași rezultate și hash-uri. |
| **K-04 KEEP AS IS** | View, ModalView, ListView, `createView`, field views și `fetchToModel` sunt deja folosite corect. | Bundle-ul păstrează moștenirea și API-urile native, nu introduce framework. | Toate JS. | Toate modulele se instanțiază prin factory-ul Espo. |
| **K-05 KEEP AS IS** | Nu s-a identificat push nativ generic pentru schimbări de entități în 10.0.10. | Polling rămâne numai pentru schimbări de la alt utilizator/worker, optimizat prin S-02. | Holiday request. | A doua sesiune este reflectată în intervalul definit, fără promisiune falsă de push. |
| **K-06 KEEP AS IS** | Verificările beneficiarului regular din `HolidayProfileService.php:565,644,840`, `HolidayApprovalService.php:161` și `AbsenceAccessChecker.php:18` protejează eligibilitatea. | Se disting verificările de actor de cele de beneficiar: gate-urile actorului se elimină, iar aceste validări business ale beneficiarului rămân. | Servicii business. | Drepturile native ale administratorului nu transformă un beneficiar neeligibil în angajat eligibil. |
| **K-07 KEEP AS IS** | `AttendanceDataReset`/`HolidayDataReset` șterg date sintetice; resetarea anuală/manuală a soldului este alt flux. | Capabilități și teste distincte; nu se combină comenzile sau textele. | Reset services/Profile service. | Nicio comandă de sold nu șterge date de test și invers. |
| **D-01 NEEDS DECISION** | Bundle-ul unic este fezabil, dar devine global prin metadata și costă aproximativ 49,9 KiB encoded la bootstrap. | Recomandare: acceptarea costului pentru ținta obligatorie; fără loader paralel. Alternativa minimă suportată ar fi păstrarea modulelor individuale, nu un bootstrap custom. | `app/client.json` și toate JS. Decizie înainte de slice JS. | Semnarea explicită a bugetului; măsurare pre/post. |
| **D-02 NEEDS DECISION** | ACL-ul nativ permite administratorilor să aprobe, dar `HolidayApproverProvider.php:53-67` enumeră numai regular pentru notificări. A transforma automat toți adminii în destinatari ar produce email în masă. | Recomandare: autoritatea de aprobare rezultă exclusiv din ACL-ul nativ; lista destinatarilor este o configurare de notificare separată și nu modifică autorizarea. | Provider/notifier. Depinde de M-01/M-03. | Test separat ACL pentru aprobare versus lista de recipients; fără email neașteptat tuturor adminilor. |
| **D-03 NEEDS DECISION** | Custom UI nu afișează automat câmpurile noi chiar dacă Entity Manager le permite. | Recomandare: activați doar AttendanceRecord/WorkSchedule după extensibility contract; restul rămân false. | §10. Risc de a promite personalizare incompletă. | POC/test contract înainte de schimbarea metadata. |

## 3. CSS — plan de consolidare într-un singur fișier

### 3.1 Inventar de reguli și dependențe de cascadă

`attendance.css` combină un strat legacy (`:1-1064`) cu secțiuni scoped pentru personal (`:1065-1598`), overview (`:1599-2327`) și schedules (`:2328-2723`). Ordinea actuală face ca regulile mai noi să corecteze selectori anteriori. Blocul `.attendance-overview-matrix-title` este împărțit inutil între `:1737-1750`. Media queries sunt fragmentate la `:216`, `:921`, `:1038`, `:1534`, `:1549`, `:2230`, `:2252`, `:2661` și `:2677`.

În `holiday.css`, `.employee-holiday-request-actions` este definit la `:319-324` și completat din nou la `:604-607`; media queries sunt la `:800` și `:820`. Blocul manager legacy `:286-329` și adaptarea `:805-813` nu au consumator static identificat în template-urile/JS curente și sunt candidat la eliminare **numai** după coverage DOM din Playwright.

În `administration.css`, aceleași familii de pagină/card apar în blocurile desktop de la `:4`, `:23`, `:255` și în variantele mobile `:651`, `:657`, `:663`; media queries sunt la `:637` și `:648`. `holiday-ledger.css` are media queries la `:320` și `:332` și repetă primitive de hero, card și tabel din celelalte fișiere.

`!important` apare în `attendance.css:401,526-527,874-875,918,1024-1026,2134-2161` și `administration.css:474-475`. Cele din zona status/button `:2134-2161` compensează specificitatea `.btn`; trebuie înlocuite cu selectori de modifier scoped. Override-urile de afișare responsive se păstrează temporar doar dacă testul de computed style dovedește că sunt necesare.

Căutarea statică a identificat selectori legacy fără referință, însă nu este suficientă pentru eliminare: stările de pontaj sunt construite dinamic în `attendance/my.js:184-188,214,278` și `attendance/overview.js:262`. Eliminarea se face numai după inventar runtime de clase, inclusiv modale și stări de eroare.

### 3.2 Clase native și reguli reutilizabile

Se reutilizează fără schimbarea designului structurile native `.container`, `.panel`, `.panel-heading`, `.panel-body`, `.table`, `.table-responsive`, `.btn` și variantele semantice, `.label`, `.form-control`, `.text-muted`, `.list-container`, `.hidden` și structurile field. Clasele Employee rămân pentru carduri, matrice, timeline/ledger, celule de status și layout-urile care nu au echivalent nativ.

Primitivele interne unificate vor acoperi:

- shell-ul paginii, hero/header, titlu, subtitlu și toolbar;
- card, card-header/body/footer, empty/loading/error states;
- tabel responsive, toolbar de filtrare și pagination wrapper;
- badge/status/action buttons;
- modal sections și form grids;
- focus ring, disabled și skeleton/loading.

Nu se schimbă markup-ul doar pentru a reduce numărul de selectori dacă aceasta ar modifica dimensiuni, spacing sau accesibilitate.

### 3.3 Structura finală exactă

Unicul fișier va fi `files/client/custom/modules/employee-management/css/employee-management.css`, în această ordine:

```css
/* 1. Tokeni și stiluri comune */
/* aliasuri către tokenii semantici Espo; reseturi strict scoped; primitive */

/* 2. Pagini și panouri */
/* shell, hero, cards, panels, tables, forms, modals, loading/empty/error */

/* 3. Attendance */
/* personal, month/year selector, daily overview, matrix, schedules */

/* 4. Holiday Management */
/* request, management, documents, verification, approval/rejection/cancel modals */

/* 5. Administration */
/* landing, profiles, settings, reset/test-data panels */

/* 6. Holiday Ledger */
/* list, filters, rows, record presentation */

/* 7. Responsive */
/* max-width: 1100px; 991px; 767px, în ordine descrescătoare */
```

Aliasurile Employee nu vor referi `--tuvtk-*`; ele vor indica exclusiv `--brand-primary/success/warning/danger/info`, familiile `--state-*`, tokenii text/panel/form și fallback-uri native Espo.

### 3.4 Plan de migrare CSS

1. Capturi și computed styles pentru toate paginile/stările în TUVTK și tema Espo, la desktop și mobil.
2. Crearea secțiunilor 1-2 prin copiere controlată, păstrând valorile și specificitatea efectivă.
3. Mutarea Attendance pe subsecțiuni, comparând proprietate-cu-proprietate stratul legacy și cel scoped; combinarea duplicatei de titlu.
4. Mutarea Holiday, Administration și Ledger; deduplicarea numai când proprietățile calculate sunt identice.
5. Mutarea tuturor media queries în secțiunea 7, păstrând aceeași ordine de breakpoint și cascade.
6. Înlocuirea `!important` prin modifier/scoping; orice excepție rămasă primește comentariu cu motiv și test.
7. Coverage runtime pentru candidații neutilizați; eliminare doar dacă nu apar în niciun flux desktop/mobil/modal/error.
8. Schimbarea `Resources/metadata/app/client.json` de la patru intrări la una.
9. După paritate, eliminarea celor patru fișiere vechi în același commit/slice; nicio perioadă cu încărcare dublă.

### 3.5 Criterii și teste vizuale

- Network conține exact `employee-management.css`, fără cele patru fișiere vechi.
- Capturi Playwright autentificate pentru fiecare pagină, modal, status, empty/loading/error, desktop 1440×900 și mobil 390×844, în ambele teme.
- Comparație computed style pe font, color, background, border, spacing, dimensions și overflow pentru elementele-cheie.
- Matricea de pontaj, cardurile și selectorul lună/an rămân identice.
- Tab order, focus ring, zoom 200%, contrast și reduced-motion sunt verificate manual.
- Zero selectori globali ne-scopați care modifică ecrane non-EmployeeManagement; zero `!important` nejustificat.

## 4. JavaScript — plan de consolidare și native reuse

### 4.1 Mecanismul loaderului EspoCRM 10.0.10

Loaderul real din runtime, `client/lib/espo.js:2`, implementează AMD și acceptă `define(id, dependencies, factory)`. ID-ul `employee-management:views/attendance/my` este normalizat către `client/custom/modules/employee-management/src/views/attendance/my.js`. Loaderul are intern `mapBundleFile`/`addBundleMapping`, iar bundle-urile core sunt înregistrate în `client/lib/original/espo-main.js:111256-111265`; nu s-a identificat o cheie metadata suportată pentru ca o extensie să adauge lazy o asemenea mapare.

`application/Espo/Core/Utils/Metadata/Builder.php:40-60` concatenează forțat `scriptList`, `cssList` și `linkList` din metadata extensiilor. `application/Espo/Core/Utils/ClientManager.php:201-220,213-235,306-317,360-400` arată că `scriptList` este folosit în producție, iar `developerModeScriptList` în developer mode. Prin urmare, declararea numai într-una dintre liste ar produce defect dependent de mod.

### 4.2 Arhitectura bundle-ului unic

Ținta este:

`files/client/custom/modules/employee-management/employee-management.js`

Fișierul conține 28 de definiții AMD **numite**, cu aceleași ID-uri și aceleași dependențe ca modulele curente. Exemplul de formă, nu implementare:

```js
define('employee-management:views/attendance/my', ['view', 'model'], function (View, Model) {
    return View.extend({ /* implementarea existentă refactorizată */ });
});
```

`clientDefs`, routes, field definitions și apelurile `createView` păstrează ID-urile actuale. `app/client.json` va declara fișierul în ambele liste de scripturi. Fișierele individuale se elimină numai după ce un test încarcă explicit toate cele 28 ID-uri în ambele moduri. Nu se folosesc module anonime, eval, un registry propriu, un al doilea loader sau un bootstrap care injectează scripturi.

Impactul demonstrat: numărul maxim de requesturi Employee JS scade 28→1, dar costul de aproximativ 49.875 B encoded se mută la bootstrap pentru toți utilizatorii. Aceasta este D-01. Dacă bugetul global nu este acceptat, alternativa minimă compatibilă cu loaderul nativ este păstrarea structurii actuale; nu există dovadă pentru un singur fișier lazy configurabil exclusiv din metadata extensiei.

### 4.3 Reutilizarea mecanismelor native

- Păstrare `View`, `ModalView`, `ListView`, field views și `createView()`; nu se introduce React/Vue.
- Modelele și colecțiile devin sursa de stare, iar `listenTo()` înlocuiește listener-ele directe care nu sunt curățate automat.
- `RecordView` se folosește numai unde ecranul reprezintă efectiv un record și poate respecta layout/ACL; nu se forțează asupra matricelor sau dashboardurilor compuse.
- `ListView` rămâne baza ledger-ului, cu mecanismele native de search, pagination, collection events și refresh reactivate unde păstrează designul.
- Field views native rămân pentru date/status și validare. `fetchToModel()` + `validate()` din `administration/settings.js:240-256` este modelul corect de păstrat.
- `Espo.Ajax` rămâne transportul, iar `Espo.Ui` gestionează notify/error/confirm; răspunsurile se aplică Model/Collection înainte de un fetch suplimentar.
- Fiecare view care creează timers, subviews sau modele ad-hoc le oprește/eliberează în `remove`/cleanup; testul navigării repetate detectează multiplicarea.

### 4.4 Analiza ecranelor prioritare

| Fișier | Comportament actual | Plan concret |
|---|---|---|
| `holiday-request/index.js:160-183,220-283,288-312,325-379,386-468,511-572` | Înregistrează visibility, încarcă listă+sold, calculează semnături, face polling 5s/30s/backoff 120s și rerender complet; cleanup-ul oprește timerul, dar nu requestul in-flight. | Coordinator comun din §5; colecție/model; patch local după acțiune; rerender structural numai când lista chiar se schimbă; guard/abort pentru răspuns târziu. |
| `attendance/my.js:117-138,315-334` | GET+render și, după POST, încă un GET/rerender complet. | API de mutație returnează starea canonică; actualizare locală a zilei și sumarului; fetch numai la conflict. Selectorul `:355-407` rămâne custom. |
| `attendance/overview.js:445-480,524-579` | Manager action actualizează datele, apoi reconstruiește matricea. | Cell/row subviews sau patch DOM încapsulat, evenimente model și recalcul al sumarului afectat. |
| `attendance/schedules.js:43-63,65-195,255,357-370` | Încarcă programul, conține handlers vechi fără controale, listener direct și field views native. | Eliminare dead code după coverage; `listenTo`; păstrarea date field views; cleanup subviews/model. |
| `administration/profiles.js:327-334,401-418,445-485` | Reîncarcă/re-randează după salvări. | Collection și row subviews; patch pentru profilul afectat și refresh explicit al istoricului asociat. |
| `administration/settings.js:202-256,276-303` | `createView`, field views, fetch/validate corecte; include inițializarea programelor. | Păstrare a mecanismelor; separare clară settings vs initialize schedules; fără duplicarea handlerelor din schedules. |
| `holiday-ledger/list.js:78-100` | Extinde ListView, dar oprește header/search și adaugă filtru propriu. | Reactivare mecanisme native și integrarea filtrului angajat în search/filter metadata; hero custom compus deasupra listei. |

## 5. Actualizări live și lifecycle EspoCRM

EspoCRM 10.0.10 oferă evenimente locale pe Model/Collection, `listenTo`, fetch/refresh și lifecycle de view. Auditul nu a identificat un mecanism push generic, suportat, care să notifice view-urile despre orice schimbare de entitate produsă de alt utilizator. Prezența infrastructurii websocket în produs nu justifică inventarea unui canal pentru această extensie.

### 5.1 Cele patru cazuri distincte

1. **După acțiunea utilizatorului curent:** răspunsul mutației actualizează imediat modelul/colecția și componentele dependente. Nu se așteaptă polling-ul și nu se face GET redundant dacă răspunsul este complet.
2. **Schimbare pe server/worker:** coordinatorul face polling numai cât view-ul este activ și vizibil; compară o revizie/semnătură și face fetch complet doar când s-a schimbat.
3. **Navigare SPA:** `afterRender`/setup face un fetch proaspăt; `remove` oprește timer, listener și request; revenirea pornește o singură instanță.
4. **Alt utilizator/sesiune:** polling-ul rămâne necesar. La focus/`visibilitychange` către visible se face refresh imediat, apoi interval normal.

### 5.2 Coordinatorul comun propus

Coordinatorul este un modul AMD numit în același fișier fizic. El păstrează cel mult un request in-flight, interval normal 30s, interval scurt 5s numai cât o operație asincronă este `processing`, backoff plafonat la 120s și reset după succes. Primește callback-uri `fetchRevision`, `fetchData`, `applyData`; folosește `listenTo` pentru modelele locale și anulează sau invalidează răspunsurile după cleanup. Dacă backend-ul nu poate livra o revizie ieftină, semnătura actuală rămâne temporar, dar listă și sold se cer într-un singur ciclu, fără requesturi paralele duplicate.

### 5.3 Criterii de acceptare lifecycle

- Un singur timer și maximum un request in-flight per view.
- Zero requesturi și zero update DOM după `remove`.
- 20 de navigări SPA înainte/înapoi nu multiplică listeners sau requesturi.
- O mutație locală se vede imediat și produce un singur POST; GET ulterior numai dacă răspunsul indică conflict/incomplet.
- O mutație din a doua sesiune apare în maximum intervalul configurat sau imediat la refocus.
- Erorile tranzitorii aplică backoff, iar 401/403 opresc polling-ul și afișează starea corectă.

## 6. Theme Integration — paleta semantică TUVTK

### 6.1 Situația curentă

`TuvtkTheme/files/client/custom/css/tuvtk.css:1-2` importă CSS-ul Espo; `:5-137` definește tokeni TUVTK. Primary și danger sunt mapate la `:28-36`, textul la `:37-46`, suprafețele la `:49-63`, formularele la `:65-78`, butoanele la `:80-100` și navigarea la `:102-136`. Lipsesc valori complete pentru `--brand-success`, `--brand-warning`, `--brand-info`, familiile `--state-success-*`, `--state-warning-*`, `--state-info-*`, variantele button și panel.

CSS-ul nativ Espo 10.0.10 (`client/css/espo/espo.css`) definește `--brand-success/warning/info`, variante `-5/-10`, `--state-*-bg/text/border`, `bg-5/bg-10/text-10/text-10l`, precum și tokeni `--btn-*` și `--panel-*`. EmployeeManagement folosește momentan aliasuri cu prioritate `--tuvtk-*` și fallback nativ în `administration.css:5-12`, `attendance.css:1067-1074,1601-1608,2330-2337`, `holiday.css:2-9` și `holiday-ledger.css:2-9`, creând o dependență opțională nedorită și patru seturi repetate. Culori hardcodate relevante sunt numai fallback-ul `#fff` din `holiday.css:351` și umbra `rgba(0,0,0,...)` din `attendance.css:694`; acestea trebuie mapate la tokeni/suprafețe compatibile.

### 6.2 Paleta TUVTK propusă

| Semantic | Base | Hover | Active | Fundal deschis | Text pe fundal | Border |
|---|---|---|---|---|---|---|
| Primary | `#164194` | `#103475` | `#0C285A` | `#EAF0FB` | `#123878` | `#8FA9D8` |
| Success | `#2E7D32` | `#256628` | `#1B4D1E` | `#EAF5EC` | `#1F5D24` | `#9BC7A0` |
| Warning | `#A85D00` | `#874A00` | `#6B3B00` | `#FFF4DB` | `#704400` | `#E8B85C` |
| Danger | `#D41131` | `#AE0E28` | `#870B1F` | `#FDEBED` | `#8B1025` | `#E899A6` |
| Info | `#1976A3` | `#135E82` | `#0E4965` | `#E7F4FA` | `#15556F` | `#8EC4DA` |
| Neutral/disabled | tokenii existenți surface/canvas/divider/text | — | — | `#F3F6FA` | `#667085` | divider-ul temei |

Valorile se mapează în TuvtkTheme la tokenii nativi, inclusiv derivatele `-5/-10`, `text-10/text-10l`, `--btn-{semantic}-{bg,border,color,hover-*,active-*}` și `--panel-{semantic}-*`. Pentru butonul warning, culoarea textului se alege după verificarea contrastului; nu se presupune automat alb. Verificarea matematică inițială a perechilor din tabel dă minimum 4,59:1; implementarea trebuie să reconfirme valorile calculate după maparea finală. Praguri: 4,5:1 pentru text normal, 3:1 pentru text mare și componente UI/focus.

### 6.3 Separarea responsabilităților și validare

Modificarea paletei este un release TuvtkTheme independent. EmployeeManagement nu declară culori TUV și nu cere instalarea temei; cu tema Espo moștenește paleta Espo. TuvtkTheme nu primește tokeni denumiți după EmployeeManagement, ci completează contractul semantic general.

Acceptare: inventar automat al variabilelor lipsă, pagină specimen cu buttons/labels/alerts/panels/form states pentru toate cele cinci semantici, contrast automat și verificare manuală în light/dark dacă tema le suportă. EmployeeManagement se testează ulterior cu ambele teme și trebuie să afișeze success verde, warning ambra, danger roșu și info distinct de primary.

## 7. Permissions & ACL — restructurarea celor 22 de scopes

### 7.1 Modelul nativ relevant

În EspoCRM 10.0.10, `application/Espo/Core/Acl.php:66-69,90-108,120-157` furnizează `getLevel`, `check`, `checkScope` și `checkEntity`. `DefaultAccessChecker.php:75-90` deleagă nivelurile `own/team/all` către ownership, iar `DefaultOwnershipChecker.php:53-84,86-114` verifică `assignedUser` și relația nativă `teams`/`teamsIds`. `DefaultTable.php:190-218,251-265` acordă administratorilor nivelul maxim; verificările custom `isRegular()` și comparațiile exacte cu `all` anulează în prezent acest comportament nativ.

Principiul țintă este:

- un **scope de entitate** răspunde la „asupra căror înregistrări?” prin `own/team/all/no` și FLS;
- o **capabilitate booleană** răspunde la „poate executa această operație sensibilă?”;
- o **pagină** este vizibilă când utilizatorul are cel puțin o capabilitate/entitate utilă, nu printr-o permisiune de navigare duplicată;
- backend-ul verifică întotdeauna entitatea țintă și capabilitatea; ascunderea paginii/butonului nu este control de securitate.

### 7.2 Matricea completă a celor 22 de scopes actuale

Coloanele de mai jos acoperă explicit: (1) numele tehnic, (2) eticheta actuală, (3) funcția, (4) locurile de verificare PHP/JS, (5) operațiile backend, (6) permisiunile simultane, (7) redundanța, (8) eliminare/consolidare, (9) numele propus RO/EN și (10) efectul migrării. Definiția centrală este `Resources/metadata/app/acl.json:4-25`, iar etichetele sunt în `Resources/i18n/{ro_RO,en_US}/Global.json:2-22`.

| Scope tehnic | Etichetă actuală RO / EN | Funcție și verificări exacte | Backend dependent și co-permisiuni | Verdict și țintă RO / EN | Migrare Roles |
|---|---|---|---|---|---|
| `EmployeeAbsence` | Absențe colegi / Colleague Absences | Date calendar read-only; `Tools/Calendar/EmployeeAbsenceService.php:26`; `Classes/Acl/AbsenceAccessChecker.php:18`; feed `Services/EmployeeAbsence.php:66-78`. | Citirea absențelor; actorul este filtrat suplimentar prin regular și access checker. | **Păstrează entitate**, nu redundant. „Concedii — absențe în calendar” / “Leave — calendar absences”. | Cheia și nivelul rămân; ulterior se adaugă `team`, fără auto-grant. |
| `EmployeeAttendanceRecord` | Pontaje / Attendance records | Înregistrări zilnice; `AttendanceAccessChecker.php:16-39,41-75`; `attendance/{my,overview}.js`; reset admin `AdministrationAccessChecker.php:38-53`. | read/edit/export, manager mark, reset; astăzi cere și `EmployeeAttendance`, Overview/Management, export nativ și uneori `read:all`. | **Păstrează entitate**. „Pontaj — înregistrări” / “Attendance — records”. Boolean-urile de pontaj se consolidează în nivelurile acesteia. | Nivelul existent se păstrează; vechile booleene pot ridica numai acțiunile conform mapării §7.5. |
| `EmployeeHolidayRequest` | Cereri de concediu / Holiday requests | Cereri și workflow; `HolidayRequestService.php:43-70,207-229`; `HolidayApproverProvider.php:28-49`; views request/management. | create/calculate/approve/reject/cancel; cere astăzi Booking/RequestManagement/Approval plus `read:all` și FLS. | **Păstrează entitate**. „Concedii — cereri” / “Leave — requests”. Operațiile self/manager folosesc niveluri + capabilitatea Approval. | Nivelurile actuale se păstrează; Booking/RequestManagement se proiectează în create/edit/delete, fără lărgire. |
| `EmployeeHolidayProfile` | Solduri de concediu / Holiday profiles | Profil/sold; `AdministrationAccessChecker.php:70-79`; `HolidayRequestService.php:431-531`; `HolidayProfileService.php`. | list/get/bulk initialize/correct/reset; cere ProfileManagement, Admin și `read:all` în ecranele administrative. | **Păstrează entitate**. „Concedii — solduri și profiluri” / “Leave — balances and profiles”. | Nivelul rămâne; edit devine nivelul de target pentru operații, nu generic CRUD. |
| `EmployeeHolidayLedger` | Registrul concediilor / Holiday ledger | Istoric contabil; `administration` checker `:75`; `views/holiday-ledger/list.js:82-100`; `workspace.js:16,22`. | read/filter/history; scriere numai internă din serviciile de profil/request. Cere Profile read și uneori `all`. | **Păstrează entitate read-only**. „Concedii — registru sold” / “Leave — balance ledger”. | Nivelul rămâne; `team` numai după ownership. Nicio permisiune generică de write. |
| `EmployeeHolidayDocument` | fără etichetă explicită / fără etichetă | Documente de aprobare; `Tools/HolidayDocument/HolidayApprovalDocumentService.php:23-185`, `HolidayDocumentRegistry.php:22-92` și verificare în `HolidayDocumentVerificationService.php:37-128`; recordDefs doar UI disabled. | emitere, reemitere, download/verificare; scriere internă. Autoritatea derivă din Request/Approval. | **Păstrează entitate internă**, M-05. „Concedii — documente (intern)” / “Leave — documents (internal)”. | Cheia rămâne; nivelele existente nu dau write generic; se adaugă checker read-only. |
| `EmployeeHolidayDocumentVerificationLog` | fără etichetă explicită / fără etichetă | Audit verificări; `Tools/HolidayDocument/HolidayDocumentVerificationService.php:119-128,172-193`; recordDefs doar UI disabled. | append intern după verificare/rate limit; fără edit/delete. | **Păstrează entitate internă**, M-05. „Concedii — jurnal verificări (intern)” / “Leave — verification log (internal)”. | Cheia rămâne, generic CRUD blocat; accesul de citire administrativ se testează separat. |
| `EmployeeAttendanceMonth` | Attendance — Historical norm (internal) / identic | Snapshot normă lunară; metadata scope/entity și `AttendanceMonthService.php:16-104`; fără control de pagină separat. | read snapshot, lock/touch intern; write numai din serviciu. | **Păstrează entitate internă read-only**. „Pontaj — normă istorică (intern)” / “Attendance — historical norm (internal)”. | Cheia/nivelul rămân; fără grant nou. |
| `EmployeeAttendanceWorkSchedule` | Attendance — Remote / CIC / identic | Program remote/CIC; `AttendanceAccessChecker.php:16-29,46-57`; `attendance/schedules.js`. | read/edit program; astăzi se combină cu Management și Record access/FLS. | **Păstrează entitate**. „Pontaj — program Remote/CIC” / “Attendance — Remote/CIC schedule”. | Nivelul rămâne; team se activează numai după ownership nativ. |
| `EmployeeManagement` | Spațiu Employee Management / Employee Management workspace | Poartă de intrare workspace; `GetStatus.php:26-56`; `controllers/workspace.js:7-22,46-66,92-103`; routes/tabs. | Status și acces self-service; se combină cu entitățile funcționale. | **Păstrează capabilitate**. „Employee Management — acces self-service” / “Employee Management — self-service access”. | Valoarea booleană se păstrează; nu acordă singură acces la date. |
| `EmployeeAdministration` | Administrare Employee Management / ... administration | Zonă administrativă; `AdministrationAccessChecker.php:15-21`; `workspace.js:73-89`; admin views. | settings, profiles, reseturi; cere capabilități specifice și niveluri pentru utilizatorii controlați prin Roles. | **Păstrează capabilitate**. „Employee Management — administrare delegată” / “... — delegated administration”. Adminul primește rezultatul maxim din ACL-ul nativ, fără excepție custom. | Valoarea se păstrează pentru Roles; administratorului nu i se adaugă permisiuni speciale. |
| `EmployeeAttendanceSettings` | Politici pontaj / Attendance policies | Configurări attendance; admin settings și `AdministrationAccessChecker.php:38-53`. | get/update settings, initialize/archive schedule. Cere Administration. | **Păstrează capabilitate**. „Pontaj — configurare politici” / “Attendance — policy settings”. | Valoarea se păstrează. |
| `EmployeeHolidaySettings` | Configurări concedii / Holiday settings | Setări concedii; admin settings și `AdministrationAccessChecker.php:55-68`. | get/update holiday settings și parte din reset test data. Cere Administration. | **Păstrează capabilitate**. „Concedii — configurare politici” / “Leave — policy settings”. | Valoarea se păstrează. |
| `EmployeeHolidayProfileManagement` | Administrare profiluri concedii / Manage holiday profiles | Deschide/autorizează gestiunea profilurilor; `AdministrationAccessChecker.php:70-79`; profiles view. | list/init/profile operations; cere Administration + Profile/Ledger `read:all`. | **Elimină/consolidează**: redundant cu Administration + nivelurile Profile/Ledger. | Dacă true și conjuncția veche completă este satisfăcută, migrează `EmployeeHolidayProfile.edit` la nivelul autorizat; altfel raport/no grant. |
| `EmployeeHolidayApproval` | Aprobare concedii (comenzi încă inactive) / Holiday approval (commands pending) | Autorizează approve/reject/cancel; `HolidayApproverProvider.php:28-49`; `HolidayRequestService.php:58-63,224-229`; `controllers/workspace.js:23,46-52`; `Tools/HolidayDocument/HolidayApprovalService.php:30-179`. | Comenzi workflow; cere Request access/checkEntity și actor eligibil. | **Păstrează capabilitate**, eticheta corectată: „Concedii — aprobare, respingere și anulare” / “Leave — approve, reject and cancel”. | Valoarea se păstrează; accesul la target nu devine automat `all`. |
| `EmployeeHolidayBooking` | Concedii — Cereri proprii / Holiday — Book own requests | Self-service create/calculate; `controllers/workspace.js:20-22,59-66`, request view și `HolidayRequestService.php:172-205`. | create own, balance/calendar validation; cere EmployeeManagement + Request create/FLS. | **Elimină/consolidează** în `EmployeeHolidayRequest.create` și own edit/delete. | True ridică doar create=yes și own edit/delete dacă scope-ul Request nu este `no`; nu acordă team/all. |
| `EmployeeHolidayRequestManagement` | Concedii — Gestiune cereri angajați / ... Manage employee requests | Manager list/commands; `HolidayRequestService.php:50-55,497-531`; management view. | vizualizare/selectare beneficiar și management; cere Request/Profile `read:all` și Approval pentru decizii. | **Elimină/consolidează** în nivelurile Request/Profile plus Approval. | True se mapează la nivelul efectiv existent; `all` numai dacă vechea conjuncție includea `read:all`. |
| `EmployeeHolidayManagement` | Gestionare concedii — navigare / Holiday Management — navigation | Permisiune de pagină/tab; `controllers/workspace.js:13-43` și view management. | Nicio operație distinctă; backend tot cere alte permisiuni. | **Elimină**, redundantă. Vizibilitatea se derivă din Request/Profile/Ledger/Approval. | Nu produce grant; se elimină după ce rutele derivă capabilitățile. Rolurile cu numai acest flag pierd doar pagina inutilizabilă. |
| `EmployeeHolidayBalanceCorrection` | Concedii — Corecții sold / Holiday — Balance corrections | Ajustări și reset manual anual; profile modal/service `HolidayProfileService.php:484,758-840`. | correct/reset cu ledger/idempotency; cere Administration, Profile target și FLS. | **Păstrează capabilitate**. „Sold concediu — corecții și resetare manuală anuală” / “Leave balance — corrections and manual annual reset”. | Valoarea se păstrează; nu se confundă cu data reset. |
| `EmployeeAttendance` | Attendance — Own entries / identic | Pagina și mutațiile pontajului propriu; `controllers/attendance.js:4`; `AttendanceAccessChecker.php:30-39`; my view. | getMine/mark self; cere Record own/FLS. | **Elimină/consolidează** în Record own read/edit; pagina derivată. | True acordă cel mult own read/edit dacă vechiul Record permitea acces; niciodată team/all. |
| `EmployeeAttendanceOverview` | Attendance — Overview / identic | Daily Overview; `controllers/attendance-overview.js:4`; `AttendanceAccessChecker.php:35-39`; overview view. | listă/matrice; cere Record/Schedule și uneori `read:all`. | **Elimină/consolidează** în nivelul read Record/Schedule. | Se păstrează nivelul efectiv anterior; true singur nu ridică la all. |
| `EmployeeAttendanceManagement` | Attendance — Management / identic | Manager mark/edit și programe; `AttendanceAccessChecker.php:41-57`; overview/schedules actions. | mark for others, schedule edit; cere Record/Schedule `read/edit:all` și FLS. | **Elimină/consolidează** în edit Record/WorkSchedule. | `all` numai dacă flag true **și** vechiul nivel relevant era all; team nu se auto-acordă. |

### 7.3 Matricea țintă — 17 scopes

Matricea are 9 scopes de date și 8 capabilități. „Manager” nu este tip custom de cont, ci un utilizator căruia i se acordă un Role; „Team” este același mecanism Roles + ownership nativ, după etapa Teams. Administratorul primește accesul maxim direct din `DefaultTable`/ACL core, fără scope, bypass sau clasă suplimentară. Generic write asupra entităților read-only rămâne interzis ca invariantă a entității, nu ca limitare a administratorului.

| Scope țintă | Angajat | Manager autorizat | Team viitor | Administrator | Observație backend |
|---|---|---|---|---|---|
| `EmployeeAbsence` | own/datele permise | team/all conform Role | team | all | `checkEntity`, calendar ACL-aware |
| `EmployeeAttendanceRecord` | read/edit own | read/edit team sau all | team | all | validări business/status/FLS rămân |
| `EmployeeAttendanceWorkSchedule` | read own | read/edit team sau all | team | all | target = angajatul programului |
| `EmployeeAttendanceMonth` | read own dacă necesar | read team/all | team | all | generic write interzis |
| `EmployeeHolidayRequest` | create + read/edit/delete own în limitele stării | read/edit team/all | team | all | workflow cere și Approval |
| `EmployeeHolidayProfile` | read own | read/edit team/all prin comenzi | team | all | generic write nu înlocuiește serviciul |
| `EmployeeHolidayLedger` | read own | read team/all | team | all | append numai intern |
| `EmployeeHolidayDocument` | read dacă request-ul este accesibil | read după request | moștenește request | all | create/edit/delete generic interzis |
| `EmployeeHolidayDocumentVerificationLog` | no | read doar rol audit explicit | no implicit | all | append intern; date sensibile |
| `EmployeeManagement` | da | da | da | implicit da | acces self-service/workspace |
| `EmployeeAdministration` | nu implicit | numai rol delegat | numai rol delegat | implicit da | poarta administrării delegate |
| `EmployeeAttendanceSettings` | nu | opțional, explicit | opțional | implicit da | configurare politici |
| `EmployeeHolidaySettings` | nu | opțional, explicit | opțional | implicit da | configurare politici |
| `EmployeeHolidayApproval` | nu implicit | explicit + target access | explicit + team | implicit da | approve/reject/cancel |
| `EmployeeHolidayBalanceCorrection` | nu | explicit + target access | explicit + team | implicit da | corecții/reset anual manual |
| `EmployeeAttendanceDataReset` **nou** | nu | nu implicit | nu | implicit da | exclusiv date sintetice de test |
| `EmployeeHolidayDataReset` **nou** | nu | nu implicit | nu | implicit da | exclusiv date sintetice de test |

Cele două scopes noi înlocuiesc conjuncțiile greu de explicat din `AdministrationAccessChecker.php:38-68`. Ele pornesc `false` și se acordă unui Role existent numai dacă acel Role, luat individual, satisfăcea întreaga conjuncție veche. Drepturile cumulate din mai multe Roles se raportează pentru review, nu se materializează automat într-un Role aleator.

### 7.4 Acces la pagină versus operație

Workspace-ul și meniurile se afișează dacă endpoint-ul de status raportează cel puțin o funcție accesibilă. Ecranele Attendance personal, Overview, Holiday self-service, management, ledger și Administration nu mai au booleene de navigare separate. Fiecare buton se bazează pe capabilitatea operației plus accesul la target; API repetă verificarea. Deep-link-ul către o pagină fără nicio funcție utilă dă 403, dar lipsa unui flag de navigare eliminat nu blochează o operație autorizată.

### 7.5 Mapping old → new și migrarea Roles

| Vechi | Nou | Regulă de migrare |
|---|---|---|
| cele 9 scopes de entitate | aceleași 9 | Copiere exactă a nivelurilor și `fieldData`; nu se inventează `team`. |
| `EmployeeManagement` | același | Copiere booleană. |
| `EmployeeAdministration` | același | Copiere booleană. |
| `EmployeeAttendanceSettings` | același | Copiere booleană. |
| `EmployeeHolidaySettings` | același | Copiere booleană. |
| `EmployeeHolidayApproval` | același | Copiere booleană; target access rămâne separat. |
| `EmployeeHolidayBalanceCorrection` | același | Copiere booleană. |
| `EmployeeAttendance` | `EmployeeAttendanceRecord` own read/edit | Numai dacă flag=true și vechiul scope Record nu era `no`; fără ridicare la all. |
| `EmployeeAttendanceOverview` | Record/WorkSchedule read | Păstrează nivelul efectiv existent; flag-ul singur nu ridică nivelul. |
| `EmployeeAttendanceManagement` | Record/WorkSchedule edit | `all` numai cu flag=true și vechiul acces relevant `all`; altfel fail-closed/review. |
| `EmployeeHolidayProfileManagement` | Profile edit + Profile/Ledger read | Numai cu Administration și conjuncția veche completă; nivelul nu depășește nivelul anterior. |
| `EmployeeHolidayBooking` | Request create + own edit/delete | Numai dacă Request nu era `no`; fără team/all. |
| `EmployeeHolidayRequestManagement` | Request/Profile read/edit target | Nivelul maxim este cel deja prezent; Approval rămâne separat. |
| `EmployeeHolidayManagement` | nimic | Navigarea devine derivată; nu creează capabilități. |
| conjuncția veche pentru reset pontaj | `EmployeeAttendanceDataReset` | True numai dacă același Role avea toate flagurile/nivelurile/FLS cerute de `AdministrationAccessChecker.php:38-53`. |
| conjuncția veche pentru reset concedii | `EmployeeHolidayDataReset` | True numai dacă același Role avea toate cerințele `:55-68`. |

Ordinea de upgrade:

1. backup DB și export read-only al Roles relevante;
2. introducerea noilor scopes și codului dual-read, fără eliminarea celor vechi;
3. dry-run care produce raport pe Role: input, output, ambiguități și refuzuri;
4. migrare idempotentă într-un serviciu apelat din `scripts/AfterInstall.php:12-29`, tranzacție per Role și marker `employeeManagementAclSchemaVersion`;
5. curățarea cache-ului prin mecanismul core `Acl\Cache\Clearer::clearForAllInternalUsers()` (`application/Espo/Core/Acl/Cache/Clearer.php:46-50`) și timestamp-ul folosit de hook-ul Role (`Classes/RecordHooks/Role/AfterSave.php:41-52`);
6. comparație efectivă pre/post pentru utilizatori sintetici și aprobare manuală a ambiguităților;
7. în release ulterior, oprirea dual-read și eliminarea metadata veche; cheile necunoscute rămase în backup nu sunt considerate mecanism de rollback.

Rollback-ul înseamnă restaurarea backup-ului și versiunii anterioare, nu inversarea euristică a nivelurilor. Niciun `team` și nicio capabilitate nouă nu se acordă automat. Administratorii nativi nu au nevoie de modificarea Role-urilor pentru acces complet.

## 8. Administrator Access — eliminarea blocajelor 403

### 8.1 Mecanismul nativ care trebuie restaurat

Nu se introduce `EmployeeManagementActorPolicy`, un scope special pentru administrator, o ramură `if ($user->isAdmin())` sau alt sistem paralel de autorizare. `application/Espo/Core/Acl.php:66-69,90-108,120-157` rămâne API-ul de decizie, iar `DefaultTable.php:190-218,251-265` acordă nativ administratorilor nivelul maxim. Access checker-ele EmployeeManagement trebuie să exprime numai cerințele funcției — scope, action level, FLS și acces la record — fără să restrângă în prealabil tipul actorului.

| Context | Sursa autorizării | Comportament țintă |
|---|---|---|
| Administrator/super-admin nativ | `DefaultTable` și `Acl` core | acces complet implicit la funcțiile extensiei, fără Role/scope/clasă specială |
| Utilizator obișnuit | Roles, scope/action levels, FLS și `checkEntity` | own/team/all conform configurației native |
| Portal | `aclPortal:false` din scopes și ACL-ul de portal Espo | comportamentul nativ al metadata; extensia nu adaugă un gate de tip utilizator |
| API | autentificarea, contextul și ACL-ul nativ Espo | fără ramură EmployeeManagement după tipul contului |
| System/job intern | entry point-ul jobului și invarianta business dedicată | ramurile system-only deja necesare joburilor rămân; nu sunt tratate ca autorizare de administrator |
| Angajat beneficiar | regulile business EmployeeManagement | trebuie să rămână regular/activ și eligibil unde serviciul cere aceasta |

Pașii obligatorii sunt: clasificarea fiecărui `isRegular()` ca verificare de **actor** sau **beneficiar**; eliminarea verificărilor de actor `isRegular()`/`isActive()`; păstrarea verificărilor ACL native deja existente; completarea cu `checkEntity`/FLS numai când lipsește controlul recordului; păstrarea integrală a validărilor beneficiarului. Un gate de actor eliminat nu se înlocuiește cu `isAdmin()`.

Metadata nu necesită o excepție pentru admin. `Resources/metadata/app/acl.json:2-27` poate rămâne `strictDefault` pentru utilizatorii obișnuiți, deoarece `DefaultTable` furnizează administratorului nivelul maxim. Scopes de entitate rămân `acl:true`, iar `aclPortal:false` menține controlul portalurilor în mecanismul nativ. Restructurarea celor 22 de scopes din §7 urmărește claritatea Roles și Teams, nu acordarea accesului administratorului; nu se adaugă `EmployeeManagementAdmin`, `adminAccess` sau altă metadata specială.

### 8.2 Inventarul complet al blocajelor Administrator

| Categorie | Fișier și linii | Efect actual | Remediere și regresie |
|---|---|---|---|
| 403 frontend | `src/controllers/attendance.js:4`; `attendance-overview.js:4` | admin oprit înainte de view | elimină `isRegular()`/`isActive()`; păstrează `getAcl().checkScope(...)`; Playwright admin și regular fără ACL |
| 403/UI ascuns | `src/controllers/workspace.js:7,14,47,60,74,95` | workspace/admin/holiday branches presupun regular și `read:all` | elimină filtrele de tip; derivă vizibilitatea direct din ACL-ul client și statusul server; test meniu + deep-link |
| 403 status API | `Tools/Workspace/Api/GetStatus.php:23-56` | `!isRegular()` la `:26`; status fals/stale la `:47` | elimină gate-ul de tip și calculează statusul prin `$acl->check`, levels și FLS; fără bypass admin |
| 403 Attendance API | `Tools/Attendance/Api/Attendance.php:13`; exporturile `PostAttendance{Pdf,Xlsx}.php:25`; gate în `AttendanceAccessChecker.php:12-15` | orice admin respins înainte de ACL | `assertActor()` nu mai verifică tipul; păstrează scope/action/FLS și accesul la record |
| 403 Administration API | `Tools/Administration/Api/Administration.php:21-30`; gate în `AdministrationAccessChecker.php:15-21` | admin respins înainte de scope/operație | elimină `isRegular()`/`isActive()` și lasă ACL-ul nativ să satisfacă scopes/nivelurile; invariants/reset ownership rămân |
| 403 Holiday API | `Tools/HolidayBalance/Api/Holiday.php:15-24`; gate în `HolidayRequestService.php:36-40` | admin respins pentru toate comenzile | elimină gate-ul de actor; păstrează scope/FLS/`checkEntity` și separă actorul de beneficiar |
| 403 document verify | `Tools/HolidayDocument/Api/VerifyHolidayDocument.php:15`; gate în `HolidayDocumentVerificationService.php:107-116` | verificarea autentificată admin poate fi refuzată | elimină filtrul de tip și păstrează verificările native de scope/nivel, rate-limit și hash |
| Decident async exclus | `Tools/HolidayDocument/HolidayApprovalService.php:115-118` | job-ul respinge decident admin la revalidare | revalidează decidentul cu ACL-ul nativ creat pentru acel user; nu verifică tipul userului |
| Read-only entity access | `Classes/Acl/ReadOnlyAccessChecker.php:15-35` | `isRegular` blochează citirea admin | `checkRead()` deleagă direct la `parent::checkRead`; create/edit/delete rămân false ca invariantă read-only |
| Feed calendar gol | `Services/EmployeeAbsence.php:66-78` | admin primește listă goală, nu neapărat 403 | elimină filtrul de tip al actorului; aplică query/access control nativ și test calendar |
| Feed request gol | `Services/EmployeeHolidayRequest.php:64-88` | admin exclus din feed | elimină filtrul de tip al actorului; păstrează scope, FLS și filtrarea ACL a query-ului |
| Aprover absent | `Tools/HolidayAdministration/HolidayApproverProvider.php:28-49` | admin nu trece `isRegular()` înainte de ACL | elimină filtrul de tip; metoda decide exclusiv prin ACL-ul nativ creat pentru candidat |
| Aprover enumeration | `HolidayApproverProvider.php:53-67` | query selectează numai regular, deci adminul nu poate fi candidat | enumeră utilizatorii interni activi eligibili pentru evaluare și filtrează fiecare candidat prin ACL-ul nativ; selecția destinatarilor de email rămâne problema separată D-02 |

Verificările `isRegular()` care privesc **beneficiarul**, nu actorul, rămân: `HolidayProfileService.php:565,644,840`, `Tools/Calendar/EmployeeAbsenceService.php:26`, `HolidayApprovalService.php:158-163`, `AbsenceAccessChecker.php:18` și `HolidayRequestService.php:72-77`. Ramura job-ului intern pentru reset anual din `HolidayProfileService.php:545-548` rămâne. `HolidayProfileService.php:491-495` este deja corect: numai contextul `system` al job-ului evită accesul interactiv, iar orice alt actor trece prin access checker și scope; blocul nu se elimină, ci beneficiază automat de corectarea nativă a checker-ului.

Criteriul static după implementare este o allowlist semantică: `isRegular()` poate rămâne numai în verificările de beneficiar enumerate mai sus. Nu trebuie să mai existe în controllers, `GetStatus`, access checker-ul read-only, gate-urile API, feed-urile actorului sau evaluarea aprobatorului. Liniile pot migra la refactorizare, dar rolul fiecărei verificări trebuie demonstrat prin test.

### 8.3 Matrice de verificare

| Operație | Employee own | Manager autorizat | Admin nativ fără profil personal | Regular fără ACL | Beneficiar invalid |
|---|---|---|---|---|---|
| pontaj propriu/management pontaj | allow own | allow după nivel | allow all prin ACL core | deny | deny |
| overview/toți autorizați | după nivel | team/all | allow all prin ACL core | deny | n/a |
| create concediu | allow pentru sine | conform target permis | poate crea pentru beneficiar eligibil; nu necesită profil admin | deny | deny |
| manage/approve/reject/cancel coleg | deny implicit | după Approval + target | allow prin ACL core | deny | deny |
| sold/istoric coleg | deny implicit | după target | allow prin ACL core | deny | n/a |
| corecție/reset anual sold | deny | explicit capability | allow prin ACL core | deny | deny |
| reset date sintetice | deny | numai scope explicit | allow prin ACL core | deny | n/a |
| settings/programe | deny | scope explicit | allow prin ACL core | deny | validările datelor rămân |
| export PDF/XLSX | own dacă permis | target level | allow prin ACL core | deny | n/a |

Acceptarea M-01 cere testarea tuturor celulelor, atât prin API direct, cât și prin UI. Accesul rezultat nativ pentru admin nu ocolește protecția istoricului, tranzacțiile, validarea stării, eligibilitatea beneficiarului sau read-only-ul generic. Testele pentru portal/API/system verifică separat comportamentul standard rezultat din metadata și configurația Espo, fără a codifica o politică EmployeeManagement după tipul contului.

## 9. Future Teams Compatibility

### 9.1 Obstacole actuale

1. Scopes pentru AttendanceRecord, WorkSchedule, Request, Profile și Ledger declară în fișierele `Resources/metadata/scopes/*.json:2-42` numai `all/own/no`, fără `team`.
2. EntityDefs nu declară relația nativă `teams`; `DefaultOwnershipChecker.php:86-114` nu poate decide ownership de echipă fără `CoreEntity` + `teamsIds`.
3. Comparații exacte `getLevel(..., 'read') === 'all'` apar în frontend la `holiday-request/index.js:223`, `holiday-ledger/list.js:84`, `workspace.js:16,22` și backend la `GetStatus.php:52`, `HolidayApproverProvider.php:44`, `HolidayRequestService.php:53,356,377,501`, `AdministrationAccessChecker.php:43-44,61,75`, `AttendanceAccessChecker.php:20,25,50` (la `:44` este și condiția `delete:all` din aceeași conjuncție de reset).
4. Providers/interogări selectează „toți utilizatorii regular” (`HolidayApproverProvider.php:53-67`, `HolidayRequestService.php:497-531`) fără filtru ACL/Team.
5. Atribuirea unui Role unei Team face permisiunile Role efective pentru membrii echipei, dar nu atribuie automat recordurile de business acelei Team.

### 9.2 Modificări necesare acum, fără activarea Teams

- M-03 înlocuiește comparațiile exacte cu verificări action-level și `Acl::checkEntity()` acolo unde recordul există.
- Se introduce un provider de query pentru angajați care folosește exclusiv access-control-ul nativ și nu ia decizii proprii de autorizare; niciun view nu mai interoghează direct toți regular.
- Query-urile folosesc builder/repository cu strict access control, nu liste globale urmate de filtrare în browser.
- Actorul și beneficiarul sunt câmpuri conceptual separate în toate comenzile.
- Contractele API acceptă un target autorizat și returnează capabilități per record.
- Nu se adaugă încă `team` în lista de niveluri: ar afișa o opțiune care nu funcționează fără ownership și backfill.
- Nu se creează tabel Department, Team custom sau registry paralel de roluri.

### 9.3 Modificări la activarea efectivă Teams

1. Adăugare relație nativă `teams` și `teamsIds` pentru AttendanceRecord, WorkSchedule, Request, Profile, Ledger și Absence; documentele moștenesc accesul request-ului. AttendanceMonth rămâne intern/derivat, iar VerificationLog rămâne audit administrativ.
2. Politică deterministă de propagare a echipelor de la beneficiarul User la record, inclusiv schimbarea membership-ului; decizie explicită dacă istoricul păstrează echipa la momentul tranzacției sau se resincronizează.
3. Migrare/backfill pentru toate recordurile existente, cu raport pentru utilizatori fără Team sau cu mai multe Teams.
4. Adăugare `team` în `aclActionLevelListMap` numai pentru acțiunile suportate.
5. Toate lists/exports/calendar/providers trec prin query ACL-aware și `checkEntity`.
6. Teste Team A/Team B, utilizator multi-team, manager general cu Role direct și admin global; niciun acces implicit cross-team.

Această etapă necesită migrare de date și nu trebuie combinată cu refactorizarea actuală CSS/JS sau cu prima migrare ACL.

## 10. Entity Manager — configurabilitate și restricții

Espo Admin folosește opțiunile scope `entityManager.fields`, `relationships`, `formula`, `edit` și flag-ul de câmp `customizationDisabled` (`client/lib/original/espo-admin.js:4461-4500,18718,19415-19417,22591-22592,23013-23014`). Layout Manager este separat și deja disponibil când `layouts:true`.

| Entitate | Restricție actuală | Risc business | Propunere | Justificare/test |
|---|---|---|---|---|
| EmployeeAbsence | `customizable:false`, layouts true; scope `:2-6` | derivată din concediu/calendar | păstrează false | câmpurile sursă/timp sunt protejate; layout poate fi ajustat. |
| EmployeeAttendanceMonth | false, layouts true; scope `:2-42` | normă istorică, locks/snapshot | păstrează false | nu se permit câmpuri/formule pe agregat intern. |
| EmployeeAttendanceRecord | false, layouts true; scope `:2-42` | status/date folosite în calcul | **D-03:** permite numai fields după contract; relationships/formula/edit false; stock fields locked | test câmp custom persistat și ignorarea controlată în matrice/export; invariants neschimbate. |
| EmployeeAttendanceWorkSchedule | false, layouts true; scope `:2-42` | intervale remote/CIC | **D-03:** aceeași extensibilitate limitată | stock dates/user/status `customizationDisabled`; UI custom trebuie să expună extensia declarat. |
| EmployeeHolidayRequest | false, layouts true; scope `:2-42` | workflow, snapshots, hashes, approvals | păstrează false | câmp custom neintegrat în snapshot/document ar induce inconsistențe. |
| EmployeeHolidayProfile | false, layouts true; scope `:2-42` | sold contabil | păstrează false | soldurile se schimbă exclusiv prin servicii/ledger. |
| EmployeeHolidayLedger | false, layouts true; scope `:2-37` | jurnal append-only/idempotency | păstrează false | nicio formulă/relație custom pe sursa contabilă. |
| EmployeeHolidayDocument | false, layouts false; scope `:2-14` | hash, cod, snapshot și verificare | păstrează false | M-05 adaugă și read-only backend. |
| EmployeeHolidayDocumentVerificationLog | false, layouts false; scope `:2-14` | audit/rate limiting | păstrează false | structură de audit fixă, append-only. |

Recomandarea implicită este ca AttendanceRecord și WorkSchedule să rămână încă `customizable:false` până când un test de extensibility dovedește cum apar câmpurile în UI custom, export și API. Dacă D-03 este aprobată, activarea lor este un slice separat după ACL, cu allowlist de tipuri de câmp, toate câmpurile stock critice `customizationDisabled:true` și fără relationships/formula. Layouturile sigure pot rămâne configurabile fără a activa Entity Manager complet.

## 11. Business Functionality Preservation

### 11.1 Inventarul funcțional care devine suită de caracterizare

| Funcționalitate | Implementare relevantă | Invariant de păstrat |
|---|---|---|
| Attendance personal și înregistrare | `Tools/Attendance/AttendanceService.php:37-60`; `views/attendance/my.js` | aceleași stări/zi, validări, calcul normă și răspuns canonic |
| Daily Overview și manager operations | `AttendanceOverviewService.php:47-54,253-311,366`; overview view | aceeași matrice, remote/CIC, conflicte concediu și reminders |
| Program de lucru | `AttendanceSettingsService.php:22-78`; schedules/settings views | initialize/update/archive fără pierderea istoricului |
| Normă lunară istorică | `AttendanceMonthService.php:16-104` | lock, snapshot și touch identice |
| Cereri concediu | `HolidayRequestService.php:109-229` | calculate/create/command, tranzacție și target validation |
| Solduri/profiluri | `HolidayProfileService.php:24-188,385,484-586,758-840` | bulk init, ledger, idempotency, reset anual/manual și corecții |
| Weekend/sărbători/zile lucrătoare | `WorkingDayCalculator.php:25-56`; `NonWorkingDayProvider.php:21`; `BookingDatePolicy.php:11` | același număr de zile și aceleași limite |
| Aprobare/respingere/anulare | `HolidayApprovalService.php:30-179` și serviciile command | token, retry, revalidare, state machine și rollback |
| Documente aprobare/verificare | `Tools/HolidayDocument/HolidayApprovalDocumentService.php:23-185`; `HolidayDocumentVerificationService.php:37-193`; `HolidayDocumentReissueService.php:32-241` | snapshot/template hash, cod, rate limit, audit și tranzacții |
| Ledger și istoric | Profile/Request services și Ledger entity | append-only, operation hash și idempotency key |
| Export PDF/XLSX | `AttendanceAccessChecker.php:59-75` și endpoints/exporters | ACL/FLS, conținut și formatare identice |
| Reset sold manual/anual | `HolidayProfileService.php:484-586`; job system `:545-548` | nu șterge date de test; actor/job distinct |
| Reset date sintetice | `AttendanceDataResetService.php:32-52`; `HolidayDataResetService.php:32-70` | preview, ownership marker, tranzacție; nu schimbă solduri reale |
| Setări administrative | Settings services/views | validare `fetchToModel`, aceleași default-uri și efecte |

### 11.2 Reguli de protecție

- Slice-urile CSS/JS nu schimbă payload, algoritmi PHP, tranzacții sau schema.
- Slice-ul Administrator elimină numai restricțiile suplimentare ale actorului și restabilește ACL-ul nativ; toate validările beneficiarului, stării, istoricului și datelor rămân.
- Slice-ul ACL mută sursa deciziei, nu semantica operației.
- Nicio migrare nu recalculează solduri, zile, snapshots, hashes sau ledger.
- Datele sintetice de test poartă marcaj unic; teardown șterge exclusiv ID-urile create de acea rulare.
- Testele de caracterizare salvează rezultatele curente înaintea implementării și compară valori, nu numai coduri HTTP.

## 12. Dependency Map

```text
Baseline de caracterizare și capturi
├── Restaurare ACL nativ ───────> status/UI coerente ────────────> migrare ACL
│                                                      └─────────> pregătire Teams
├── Paletă semantică TuvtkTheme ──> consolidare CSS EmployeeManagement
├── Contract loader AMD ──────────> bundle JS unic ──> lifecycle/polling comun
└── Contract business/upgrade ────> toate release-urile și porțile ZIP

Migrare ACL ──> query/record authorization team-ready ──> schema + backfill Teams (viitor)
Migrare ACL ──> opțional Entity Manager controlat
```

| Producător | Consumatori | Contract care nu poate fi rupt |
|---|---|---|
| TuvtkTheme tokeni nativi | Employee CSS și orice alt ecran Espo | nume native, contrast, fallback Espo |
| ACL nativ Espo (`Acl`, `DefaultTable`, FLS, ownership) | checkere, servicii, GetStatus, controllers | fără gate de tip actor și fără bypass/clasă admin; aceeași decizie server/UI |
| Entity ACL + ownership | lists, exports, calendar, workflow | own/team/all și `checkEntity` pe target |
| HolidayRequest/Profile services | UI, approval, docs, ledger | tranzacții, snapshots, idempotency, beneficiar eligibil |
| Module IDs AMD | routes, clientDefs, createView, field definitions | ID-urile actuale rămân stabile după bundling |
| Role migrator | Roles existente și cache ACL | fail-closed, idempotent, auditabil, rollback prin backup |

Fișierele cu impact transversal maxim sunt `AttendanceAccessChecker.php`, `AdministrationAccessChecker.php`, `HolidayRequestService.php`, `HolidayApproverProvider.php`, `GetStatus.php`, `Resources/metadata/app/acl.json`, metadata scopes și `Resources/metadata/app/client.json`. Ele nu trebuie schimbate simultan în același release fără porțile descrise mai jos.

## 13. Implementation Slices

Fiecare slice este un candidat livrabil separat. Niciun ZIP nu se generează doar pentru documentație; ZIP-ul se generează numai după validările slice-ului, apoi se instalează prin mecanismul nativ în instanța aprobată și se validează manual. CSS/JS nu se amestecă cu ACL/migrarea Roles.

### Slice 0 — teste de caracterizare și baseline, fără schimbare funcțională

- **Fișiere:** teste PHP/JS/Playwright din EmployeeManagement; fixtures sintetice strict izolate; fără surse runtime.
- **Modificări exacte:** contracte pentru calcul zile, ledger, aprobări, documente, reseturi distincte; capturi vizuale; măsurarea requests/bytes/renders; matricea accesului actual inclusiv 403 admin.
- **Dependențe:** runtime 10.0.10/8.4.27 și credentials loader conform AGENTS.md.
- **Validări:** `./test-php.sh validate EmployeeManagement`, `./test-node.sh test EmployeeManagement`, `./test-node.sh playwright EmployeeManagement`.
- **Risc:** testele pot depinde de date existente. Se folosesc numai date sintetice marcate unic și teardown pe ID-urile proprii.
- **Criteriu ZIP:** nu se generează ZIP; este o poartă de caracterizare.
- **Aprobare manuală:** baseline vizual și rezultatele business sunt semnate ca sursă de adevăr.

### Slice 1 — restaurarea ACL-ului nativ pentru Administrator, fără schema ACL nouă

- **Fișiere:** exclusiv checkerele/serviciile din §8.2, `GetStatus.php`, controllers și teste; nu se creează clasă de autorizare.
- **Modificări exacte:** eliminarea gate-urilor de actor `isRegular()`/`isActive()`, fără înlocuire cu `isAdmin()`; separarea actor/beneficiar; autorizare prin `Acl`, levels, FLS și `checkEntity`; capabilitățile vechi și cele 22 scopes rămân încă neschimbate.
- **Dependențe:** Slice 0; D-02 pentru recipients poate rămâne cu comportamentul recomandat fără notificarea tuturor adminilor.
- **Validări:** matrice PHP/API/UI §8.3, inclusiv admin fără profil propriu; toate regresiile business.
- **Riscuri:** eliminarea unui `isRegular()` de beneficiar, omiterea unui `checkEntity` sau introducerea accidentală a unui bypass `isAdmin()`.
- **Criteriu ZIP:** validare PHP/Node/Playwright completă, diff limitat la eliminarea restricțiilor și folosirea ACL-ului nativ, inventar ZIP/manifest compatibil și zero migrare DB/Role.
- **Aprobare manuală:** admin real parcurge toate funcțiile; un regular fără ACL este refuzat, iar portalul urmează `aclPortal:false` și ACL-ul nativ, fără gate EmployeeManagement după tip.

### Slice 2 — paleta semantică TuvtkTheme, release independent

- **Fișiere:** exclusiv `TuvtkTheme/files/client/custom/css/tuvtk.css` și testele/docs temei.
- **Modificări exacte:** maparea paletei §6.2 în familiile native brand/state/button/panel, inclusiv hover/active/light/text/border/disabled.
- **Dependențe:** niciuna față de EmployeeManagement; poate preceda CSS Employee.
- **Validări:** test inventar tokeni, contrast, pagină specimen, testele TuvtkTheme, navigare autentificată.
- **Riscuri:** schimbare globală a componentelor Espo; warning/danger cu contrast insuficient.
- **Criteriu ZIP:** numai schimbări TuvtkTheme, suita temei PASS, inventar și SHA-256 după aprobarea vizuală.
- **Aprobare manuală:** ecrane Espo standard + EmployeeManagement, desktop/mobil; culorile semantice distincte.

### Slice 3 — un singur CSS EmployeeManagement

- **Fișiere:** noul `css/employee-management.css`, cele patru CSS eliminate la final, `Resources/metadata/app/client.json`, teste vizuale.
- **Modificări exacte:** structura §3.3, deduplicare demonstrată, tokeni exclusiv nativi, responsive consolidat, eliminare controlată `!important`/dead CSS.
- **Dependențe:** Slice 0 și preferabil Slice 2; funcționează și cu tema Espo fără Slice 2.
- **Validări:** exact un request CSS, snapshot/computed-style în ambele teme și breakpoints, test conflict pe ecrane non-Employee.
- **Riscuri:** cascade/order drift, clase dinamice considerate eronat neutilizate.
- **Criteriu ZIP:** niciun alt runtime source modificat; toate testele vizuale și statice PASS; inventar ZIP conține numai CSS-ul nou.
- **Aprobare manuală:** semnare pixel/design pentru carduri, matrici, tabele, modale, stări și responsive.

### Slice 4 — bundle JS unic și lifecycle

- **Fișiere:** noul `employee-management.js`, cele 28 fișiere individuale eliminate la final, `app/client.json`, teste loader/JS/Playwright.
- **Modificări exacte:** 28 AMD named definitions, aceleași ID-uri/dependențe; `scriptList` + `developerModeScriptList`; coordinator refresh; `listenTo`/cleanup; update local; îmbunătățirile S-02–S-07 aprobate.
- **Dependențe:** Slice 0; Slice 1 permite măsurarea autentică a paginilor admin. D-01 trebuie acceptată.
- **Validări:** încărcarea explicită a tuturor ID-urilor în prod/developer mode, un singur request Employee JS, navigare și modale, mock timers, două sesiuni, buget bytes/renders.
- **Riscuri:** ID duplicate, dependență circulară, execuție înainte de loader, cost global, cleanup incomplet.
- **Criteriu ZIP:** testele PHP neschimbate PASS, toate testele JS/Playwright PASS, inventar ZIP are exact un JS Employee și nu conține sursele individuale.
- **Aprobare manuală:** toate fluxurile UI și Network/Performance sunt verificate; selectorul lună/an rămâne neschimbat conceptual.

### Slice 5 — restructurare ACL și migrare Roles

- **Fișiere:** `Resources/metadata/app/acl.json`; toate `metadata/scopes`, `aclDefs`, `entityAcl`, `clientDefs` afectate; i18n; checkere/providers/status; serviciu migrator și `scripts/AfterInstall.php`; teste upgrade.
- **Modificări exacte:** matricea țintă de 17 scopes, cele două reset scopes, eliminarea booleenelor redundante după dual-read, M-05 pentru document/log, mapping-ul §7.5 și cache clear.
- **Dependențe:** Slice 1 și backup DB; nu include CSS/JS bundling.
- **Validări:** dry-run pe copii ale tuturor Roles, idempotency, matrice pre/post, FLS, no-escalation, upgrade de la versiunea curentă, rollback rehearsal.
- **Riscuri:** pierdere/escaladare de drepturi, cache stale, roluri cumulate ambigue, generic CRUD pe entități interne.
- **Criteriu ZIP:** raportul migratorului fără ambiguități neaprobate, suite complete PASS, inventar/manifest verificate și backup confirmat înainte de instalarea candidatului.
- **Aprobare manuală:** administratorul Espo inspectează Role UI și confirmă etichetele/nivelurile; scenariile Employee/Manager/No-access/Admin sunt semnate.

### Slice 6 — pregătire internă pentru Teams, fără activarea nivelului team

- **Fișiere:** provider de query ACL-aware; query services; checkerele și providers din §9.1; teste.
- **Modificări exacte:** eliminarea tuturor comparațiilor exacte `=== all`, query ACL-aware delegat mecanismului nativ, actor/beneficiar explicit. Providerul nu conține reguli proprii. Nu se schimbă schema și nu se afișează `team`.
- **Dependențe:** Slice 5.
- **Validări:** own/all identic cu comportamentul aprobat, teste negative cross-user și contract pregătit pentru `checkEntity`.
- **Riscuri:** scădere de performanță prin check per-record sau lărgire prin query global.
- **Criteriu ZIP:** query count/budget și ACL tests PASS; fără metadata de ownership falsă.
- **Aprobare manuală:** Manager vede exact populația acordată de Role; niciun record suplimentar.

### Slice 7 — Teams nativ, proiect viitor separat

- **Fișiere:** entityDefs/scopes/ORM, backfill migrator, providers/checkere/lists/exports/calendar, teste Teams.
- **Modificări exacte:** `teams`/`teamsIds`, propagare și backfill, nivel `team`, query strict access-control; fără registru custom.
- **Dependențe:** Slice 6 și decizia de istoric al membership-ului.
- **Validări:** Team A/B, multi-team, Role direct, manager general, admin, schimbare membership, export și calendar.
- **Riscuri:** cross-team leakage și ownership istoric incorect.
- **Criteriu ZIP:** backfill dry-run + backup + zero record neclasificat neaprobat; suite și audit query PASS.
- **Aprobare manuală:** matrice completă de izolare pe date sintetice.

### Slice 8 — Entity Manager controlat, opțional

- **Fișiere:** scope/entityDefs pentru AttendanceRecord/WorkSchedule, i18n și teste contract/UI.
- **Modificări exacte:** numai dacă D-03 este aprobată: fields allowlist, stock fields locked, relationships/formula/edit false; nicio entitate financiară/audit configurabilă.
- **Dependențe:** Slices 5–6 și POC de afișare/export.
- **Validări:** create/edit/remove câmp custom sigur, API/export/UI, upgrade și invariants.
- **Riscuri:** câmpuri persistate dar invizibile sau omise din export.
- **Criteriu ZIP:** contract de extensibilitate complet PASS; fără schimbări în servicii business.
- **Aprobare manuală:** administratorul confirmă exact opțiunile disponibile și blocarea câmpurilor critice.

## 14. Migration & Backward Compatibility

### 14.1 Compatibilitate pe artefacte

- **Date business:** nicio tabelă, valoare de sold, cerere, ledger, snapshot sau document nu este rescrisă în slice-urile 1–6. Migrarea Teams este singura etapă cu backfill de ownership și este separată.
- **JavaScript:** identificatorii AMD rămân neschimbați, deci metadata, rutele și `createView` continuă să funcționeze. Compatibilitatea nu se bazează pe vechile căi fizice; testul module-ID este contractul.
- **CSS:** clasele DOM rămân; calea celor patru fișiere nu este API public. Eliminarea lor se face în același candidat în care `cssList` indică fișierul nou.
- **API:** endpoint-urile și payload-urile rămân, cu adăugarea non-breaking a capabilităților/reviziei dacă este necesar. Orice schimbare de răspuns folosită pentru update local trebuie testată cu clientul vechi în fereastra de upgrade sau lansată atomic.
- **Roles:** dual-read temporar și migratorul §7.5 preced eliminarea scopes vechi. Adminul nativ primește accesul maxim din ACL-ul core, nu prin mutarea datelor din Roles și nu prin cod special al extensiei.
- **TuvtkTheme:** folosește tokeni Espo nativi; EmployeeManagement nu declară dependență de versiunea TUVTK și funcționează cu tema Espo.

### 14.2 Upgrade și rollback

Upgrade-ul de la versiunea locală curentă trebuie testat pe o copie controlată a structurii Role/ACL, fără date reale. Ordinea este backup → instalare candidat prin `bin/command extension --file=...` în serviciul aprobat → rebuild/cache nativ → migrator/report → smoke API/UI → suită completă. Nu se copiază surse în runtime.

La eroare înainte de finalizarea migrării, tranzacția Role curentă revine și markerul de schemă nu avansează. La eroare după instalare, rollback-ul suportat este restaurarea backup-ului plus extensia anterioară; nu se execută transformări inverse presupuse. Raportul păstrează numai IDs/decizii tehnice necesare, fără parole, cookies sau date personale.

### 14.3 Compatibilitatea Roles existente

- Se evaluează fiecare Role independent, apoi accesul efectiv al unor utilizatori sintetici cu Roles multiple.
- O conjuncție satisfăcută numai prin agregarea mai multor Roles este marcată pentru review; nu se scrie automat într-unul dintre ele.
- `fieldData` se copiază pentru scopes păstrate și se intersectează, niciodată se lărgește, când o permisiune este derivată.
- Valori invalide/necunoscute, Role inactive sau scope lipsă produc rezultat restrictiv și raport.
- Echipele care au Roles atribuite păstrează capabilitățile Role; aceasta nu este încă echivalentă cu accesul `team` la recorduri.
- Re-rularea migrării cu markerul curent este no-op verificabil.

## 15. Automated and Manual Acceptance Tests

Toate testele folosesc infrastructura existentă din AGENTS.md. Browserul primește credențialele exclusiv prin `/root/.config/espocrm/test.env`; valorile nu apar în comenzi/loguri. Testele noi creează date sintetice cu marker unic și șterg exclusiv ID-urile proprii. Auditul curent nu a modificat date reale.

### 15.1 Teste automate

| Categorie | Test automat obligatoriu | Criteriu |
|---|---|---|
| Validare pachet | `./test-php.sh validate EmployeeManagement` | manifest, PHP syntax, metadata și inventar valide pe 8.4.27 |
| Unit PHP — ACL nativ | admin/super-admin și regular cu/fără Roles; portal conform `aclPortal`; contexte API/system conform core | nicio ramură de autorizare după tip în extensie; rezultatele provin din ACL/metadata native |
| Unit PHP — ACL | toate cele 17 scopes, actions/FLS și own/team/all/no | fiecare operație are allow și deny; fără fallback `all` hardcodat |
| Unit PHP — business | working days/weekend/holiday, balances, ledger, idempotency, approval state machine, documents | rezultate identice baseline-ului; rollback la eroare |
| Integration PHP/API | Attendance, request, management, approval/reject/cancel, correction/reset anual, test-data reset, settings, PDF/XLSX | cod, payload, tranzacție și istoric corecte; 403 negative |
| Document security | generic create/edit/delete/link pentru Document/VerificationLog | 403 pentru actor interactiv, inclusiv admin; servicii interne PASS |
| Role migrator | fixtures cu fiecare combinație veche, multiple Roles, invalid/missing, re-run | mapping exact, fail-closed, raport și idempotency |
| Upgrade | snapshot metadata/Role înainte și după versiunea curentă → candidat | zero pierdere/escaladare și marker corect |
| JavaScript modules | require pentru toate cele 28 ID-uri în prod/developer mode | toate rezolvă o singură dată din bundle |
| JavaScript lifecycle | fake timers/request mocks, visibility/focus/remove, errors/backoff | un timer/request, zero callback după remove, intervale 5/30/120 corecte |
| JavaScript views | model/collection events, save/conflict, cell/row patches | update local fără GET redundant; conflictul face refresh |
| Static CSS | exact un fișier/list entry, token lint, selector/important allowlist | zero `--tuvtk-*`, zero culoare neallowlistată, zero important neexplicat |
| Node suite | `./test-node.sh test EmployeeManagement` | toate testele existente și noi PASS |
| Playwright auth | `./test-node.sh playwright EmployeeManagement` | login real; niciun test auth omis numărat ca PASS |
| Performance browser | ResourceTiming + request/rerender counters | CSS 4→1; JS Employee maximum 28→1; bytes în bugetul aprobat D-01; fără request duplicat |
| Theme contract | TuvtkTheme tests + specimen Employee în TUVTK/Espo | toți tokenii, contrast și culori distincte |

### 15.2 Matrice automată de identități

Se creează utilizatori sintetici pentru: Administrator real, Super-admin dacă mediul permite, Manager cu nivel all, Manager viitor/team fixture, Angajat own, regular fără permisiuni și contexte portal/API configurate conform metadata/ACL core. Pentru fiecare se testează:

- workspace/menu și deep-links;
- attendance own/overview/mark colleague/schedule;
- holiday own balance/history/create/cancel;
- manager list/approve/reject/cancel și documente;
- profile correction/manual annual reset;
- attendance/holiday test-data reset ca operații distincte;
- settings, exports și calendar feeds;
- generic entity API și FLS.

Administratorul este testat fără EmployeeHolidayProfile propriu. Angajatul beneficiar este regular activ cu profil valid. Un beneficiar portal/admin/inactiv este refuzat chiar dacă actorul este administrator.

### 15.3 Playwright și regresie vizuală

- TUVTK și tema Espo, desktop 1440×900 și mobil 390×844.
- Toate paginile, stările empty/loading/error/processing și toate modalele.
- Navigare SPA în buclă de 20 de ori; back/forward și deep-link reload.
- Salvare locală: UI se actualizează imediat, focusul rămâne logic și nu apare flicker/full-page rerender.
- Două contexte browser: al doilea aprobă/modifică, primul reflectă schimbarea prin polling/refocus.
- Snapshot plus computed styles pentru matrice/card/header/table/status/disabled/focus.
- Network: exact un CSS și un JS Employee după slice-urile respective; inventar pe bootstrap, prima pagină și navigare completă.
- Accessibility: keyboard-only, focus visible, nume accesibile, 200% zoom și contrast.

### 15.4 Verificări manuale

| Verificare | Cine/scenariu | Acceptare |
|---|---|---|
| Design | owner produs, ambele teme și breakpoints | identic ca structură/aspect; semantic colors aprobate |
| Administrator | admin nativ fără profil | toate funcțiile cerute accesibile, fără 403 artificial |
| Manager | regular cu Role target | vede/operează numai populația autorizată |
| Angajat | regular own | numai date proprii și self-service |
| Fără permisiuni | regular/portal | UI ascuns și API 403; zero date în payload |
| Roles UI | administrator Espo | cele 17 denumiri sunt clare; scopes interne marcate |
| Upgrade | copie controlată a configurației curente | drepturile efective și istoricul sunt păstrate |
| Date istorice | cereri/ledger/documente sintetice pre-upgrade | count, values, hashes și relations neschimbate |
| Teams viitor | Team A/B pe date sintetice | zero acces cross-team; manager general/admin conform matricei |

Un test autentificat omis se raportează `SKIPPED`, separat; nu intră în numărul PASS. Orice eșec de autentificare este `FAILED` cu motivul observat, fără divulgarea credentialelor.

## 16. Risk Register

| Risc | Probabilitate / impact | Control preventiv | Detectare și rollback |
|---|---|---|---|
| Bypass admin custom introdus din nou | medie / critic | interdicție pentru `isAdmin()`/clasă/scope special; numai ACL nativ | test static + matrice unit/API; rollback slice 1 |
| Pierdere/escaladare Role la consolidare | medie / critic | dual-read, dry-run, per-Role transaction, fail-closed, backup | comparație efectivă pre/post; restore DB/extensie |
| Generic write în documente/log | medie / critic | read-only checker + hooks/backend deny | teste API CRUD; rollback metadata/code |
| Regresie vizuală din cascadă CSS | mare / mare | migrare property-level, baseline computed/screenshot | visual diff; revenire la artefactul anterior |
| CSS dinamic eliminat greșit | medie / mare | runtime class coverage pe toate stările | Playwright state coverage |
| Bundle AMD nu pornește în developer mode | medie / mare | named defines + ambele script lists | require-all în ambele moduri |
| Cost global al bundle-ului | cert / mediu | D-01 și buget explicit; minificarea nativă | ResourceTiming; alternativă = module individuale |
| Polling duplicat/memory leak | medie / mediu | coordinator, one-in-flight, cleanup/abort | fake timers + 20 navigări |
| Date stale după patch local | medie / mare | răspuns canonic, revision/conflict fetch | două sesiuni + teste conflict |
| Cross-team data leakage | mare dacă activat prematur / critic | nu expune team fără ownership/backfill; strict query | Team A/B negative; oprire rollout |
| Modificarea calculului business accidental | mică / critic | slice boundaries și caracterizare | valori/hashes/ledger baseline; rollback |
| Confuzie reset sold vs date test | medie / critic | scopes, texte, endpoint-uri și tests distincte | audit trail și integration tests |
| Entity Manager expune câmp critic | medie / mare | default false; allowlist + customizationDisabled | UI/API negative; rollback slice 8 |
| Notificări către toți adminii | medie / mediu | D-02 separă autoritatea de recipients | test recipient list și mail sink |
| Tema TUVTK afectează global Espo | medie / mare | release separat, specimen componente native | screenshots globale; rollback theme |

## 17. Final Implementation Checklist

### Înainte de orice implementare

- [ ] Planul și deciziile D-01, D-02 și D-03 sunt aprobate explicit.
- [ ] Working tree preexistent este inventariat din nou; nicio schimbare a utilizatorului nu este suprascrisă.
- [ ] Runtime-ul aprobat, URL-ul, EspoCRM, PHP și compatibilitatea manifestelor sunt reverificate.
- [ ] Slice 0 oferă baseline business, vizual, ACL și performance.

### CSS și temă

- [ ] TuvtkTheme completează separat tokenii semantici nativi și trece contrastul.
- [ ] EmployeeManagement nu referă `--tuvtk-*` și funcționează în tema Espo.
- [ ] `employee-management.css` are exact cele 7 secțiuni din §3.3.
- [ ] `app/client.json` încarcă un singur CSS; vechile fișiere nu sunt în ZIP.
- [ ] Designul, cardurile, matricea și responsive sunt aprobate în ambele teme.

### JavaScript și lifecycle

- [ ] Există exact un fișier JS Employee cu 28 module AMD numite și ID-uri stabile.
- [ ] Fișierul este în `scriptList` și `developerModeScriptList`.
- [ ] Nu există loader/registry/framework paralel.
- [ ] Update local, polling, SPA/refocus, altă sesiune și cleanup trec testele.
- [ ] Selectorul lună/an rămâne componenta intenționat personalizată.

### Administrator și ACL

- [ ] Nu există `EmployeeManagementActorPolicy`, scope admin, `isAdmin()` bypass sau altă autorizare custom pentru administrator.
- [ ] Toate gate-urile de actor din §8.2 au fost eliminate/testate; controalele native ACL au rămas, iar verificările beneficiarului nu au fost modificate.
- [ ] Admin fără profil propriu poate gestiona angajatul eligibil.
- [ ] Document/VerificationLog sunt read-only la API generic.
- [ ] Matricea de 17 scopes și etichetele bilingve sunt implementate exact.
- [ ] Migratorul Roles este dry-run, idempotent, tranzacțional, fail-closed și curăță cache-ul.
- [ ] Resetarea datelor de test și resetarea soldurilor rămân capabilități/comenzi distincte.

### Teams, Entity Manager și business

- [ ] Toate comparațiile `=== all` sunt eliminate în favoarea access/action checks corecte.
- [ ] Query-urile de angajați sunt ACL-aware și centralizate.
- [ ] Nivelul `team` nu este expus înainte de relații, propagare și backfill.
- [ ] Nu există tabel Team/Department sau roluri paralele custom.
- [ ] Entitățile financiare, workflow și audit rămân neconfigurabile.
- [ ] D-03 este implementată numai după contractul pentru câmpuri custom.
- [ ] Tranzacțiile, ledger-ul, idempotency, snapshots, documentele și calculul zilelor sunt neschimbate.

### Poarta de release

- [ ] Slice-ul nu combină CSS/JS cu ACL/migrarea Roles.
- [ ] `validate`, suitele PHP/Node și Playwright autentificat sunt PASS; SKIPPED este raportat separat.
- [ ] Testele Admin/Manager/Employee/No-access și upgrade sunt PASS.
- [ ] Nu au fost folosite date reale; teardown a șters numai datele sintetice proprii.
- [ ] ZIP-ul candidat este construit numai după validări, inventariat și instalat nativ în test.
- [ ] URL-ul de test și rezultatele manuale sunt predate pentru aprobare vizuală.
- [ ] Numai ZIP-ul exact validat și SHA-256 sunt livrate după aprobare; fără commit/push/deploy automat.

### Rezumat executabil al țintei

- **CSS recomandat:** un singur `employee-management.css`, cu secțiunile Tokeni/comune; Pagini/panouri; Attendance; Holiday Management; Administration; Holiday Ledger; Responsive.
- **JavaScript:** un singur fișier este demonstrabil fezabil prin AMD named modules, cu prețul încărcării globale de aproximativ 49,9 KiB encoded; nu există o mapare lazy de extensie demonstrată în metadata 10.0.10.
- **Permisiuni:** inventarul curent are 22 scopes; ținta are 17 (9 entități + 8 capabilități), cu migrare fail-closed.
- **Administrator:** toate cele 13 categorii de blocaj/filtrare din §8.2 se remediază prin eliminarea restricțiilor de actor și revenirea la ACL-ul nativ, fără policy sau bypass special; validările beneficiarului rămân.
- **TuvtkTheme:** trebuie să furnizeze family complete primary/success/warning/danger/info și neutral/disabled prin tokenii semantici nativi.
- **Teams:** obstacolele sunt lipsa ownership `teams`, lipsa nivelului metadata, comparațiile exacte cu `all` și query-urile globale de regular; activarea cere schema și backfill separat.
- **Ordine:** baseline → restaurarea ACL-ului nativ pentru Administrator → Tuvtk palette → CSS unic → JS unic/lifecycle → ACL+Role migration → pregătire Teams → Teams viitor → Entity Manager opțional.
