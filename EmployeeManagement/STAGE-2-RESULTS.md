# Employee Management 0.2.0 — rezultate etapa 2

Data: 20 octombrie 2026. **Administrare delegată implementată și testată; extensia nu este production-ready.** Bază: 0.1.0. Nu au fost modificate arhitectura aprobată, extensiile originale sau instalația companiei.

## 1. Livrabil și mediu

- ZIP: [dist/employee-management-0.2.0.zip](dist/employee-management-0.2.0.zip), **90.187 bytes**.
- SHA-256: `7204a372ccb7f5d0f35a8c15f70298eb3bf1672f0facb6b8f7a363f18ba386cf`.
- Un singur modul EspoCRM: `EmployeeManagement`; 87 fișiere runtime și două fișiere de pachet. Pending, teste, unelte și rapoarte nu sunt instalate.
- Compatibilitate declarată și verificată exclusiv: **EspoCRM 10.0.1, PHP 8.4.23, Zile Sărbătoare 0.9.5**. Laboratorul utilizează MariaDB **12.3.2**.
- Laborator reutilizat: `employee-feasibility-20261020`, Compose `/tmp/employee-feasibility-20261020-yDmmnL/compose.yaml`. Toate scrierile de test folosesc utilizatori, roluri și entități sintetice din acest laborator.
- Attendance Management 1.11.0, Holiday Management 1.9.4 și ZileSarbatoare rămân instalate în laborator. Nu s-a făcut upgrade EspoCRM, uninstall al originalelor sau migrare a datelor lor.

Instalarea se face prin Extension Manager nativ, CLI `bin/command extension --file=...`, nu prin copierea directă a claselor în runtime. A fost executată trecerea 0.1.0→0.2.0 și reinstalarea build-urilor de dezvoltare 0.2.0. ZIP-ul final este cel instalat și testat.

## 2. Funcționalități operaționale

### Administrare comună

Intrarea nativă din meniul utilizatorului deschide `#EmployeeAdministration`. Panourile Attendance, Holiday și Profiles sunt vizibile numai pentru secțiunile autorizate prin Roles. Se folosesc router/controller/view, modele și field views native EspoCRM; nu există un registru paralel de permisiuni.

- **Attendance:** salvarea numărului de luni anterioare editabile și a opțiunii de export incomplet, cu validările originale. Prima lună a arhivei este afișată read-only; comanda de inițializare a arhivei nu este expusă înaintea integrării WTC/istoric.
- **Holiday:** drept anual implicit, data resetării, pragul de avertizare a soldului acumulat, limita negativă, unul/doi aprobatori eligibili și cele două blocuri de semnături. Data anuală păstrează normalizarea originală MM-DD și validarea datei native.
- **Profiluri:** lista angajaților regular activi, inițializare cu sold de deschidere, actualizarea parametrilor și funcția originală Complete Initial Setup. Actualizarea parametrilor nu permite modificarea soldului unui profil inițializat.
- **Complete Initial Setup:** păstrează condițiile originale: profil inițializat cu sold zero, fără cereri și numai intrări initialization/bulkUpdate cu delta zero. Nu este o comandă de corecție generală a soldului.
- **Ledger:** aceleași tipuri de operații, formule, delta și snapshot înainte/după; actorul provine exclusiv din contextul serverului. Batch-ul este atomic. Retry-ul aceleiași operații nu dublează creditarea.

Opțiunea acordării anuale automate rămâne vizibilă, dar read-only și neactivabilă prin API până la implementarea Jobs. Setările păstrate pentru etapele următoare nu sunt prezentate ca fluxuri de business deja funcționale.

### API-uri expuse

Prefix: `/api/v1/EmployeeManagement/administration/`.

| Metodă și rută | Operație |
| --- | --- |
| GET / PUT attendance | Citire/salvare politici Attendance |
| GET / PUT holiday | Citire/salvare configurări Holiday |
| GET approvers | Candidați regular activi, autorizați nativ, numai ID/nume |
| GET profiles | Listă administrativă și parametri ai profilurilor |
| POST profiles/bulkInitialize | Inițializare / actualizare parametri, batch atomic |
| POST profiles/completeInitialSetup | Completarea inițializării în condițiile originale |

Payload-urile sunt allowlist. Nu sunt acceptate chei precum `siteUrl`, `workingTimeCalendarId`, `isAdmin`, actor impus de client sau nume de aprobatori falsificate. Operațiile correct/reset/decision neimplementate sunt refuzate.

## 3. ACL și separarea responsabilităților

Toate scopes noi au default false; instalarea nu acordă automat roluri sau permisiuni. Administratorul tehnic configurează Roles native și rămâne responsabil de WTC și infrastructură.

| Actor / drepturi native | Attendance | Holiday | Profiles | Infrastructură |
| --- | --- | --- | --- | --- |
| Administrator tehnic, type admin | 403 | 403 | 403 | Drepturile globale native rămân intacte |
| Regular activ + EmployeeAdministration + EmployeeAttendanceSettings | Citire/salvare | 403 fără drept separat | 403 fără drept separat | Niciun grant suplimentar |
| Regular activ + EmployeeAdministration + EmployeeHolidaySettings | 403 fără drept separat | Citire/salvare | 403 fără drept separat | Niciun grant suplimentar |
| Regular activ + EmployeeAdministration + EmployeeHolidayProfileManagement + Profile/Ledger read all și câmpurile necesare | 403 fără drept separat | 403 fără drept separat | Operațiile de profil de mai sus | Niciun grant suplimentar |
| Manager cu toate drepturile necesare | Da | Da | Da | Fără Settings global, Role sau administrare WTC |
| Entry fără secțiuni / read own / field ACL restrictiv | Numai secțiunile autorizate | Numai secțiunile autorizate | Refuzat dacă lipsesc drepturile necesare | Fără acces suplimentar |
| Angajat fără drept de administrare | 403 | 403 | 403 | Neschimbat |

`AdministrationAccessChecker` intersectează ACL-ul nativ cu eligibilitatea business regular/activă. Nu încearcă să retragă privilegiile globale ale adminului. Se verifică separat actorul și beneficiarul: un admin sau un utilizator inactiv nu poate primi profil prin aceste operații și nu apare în lista operațională.

Administrarea profilurilor nu ocolește read own sau field ACL: sunt cerute explicit read all și acces la atributele returnate pe Profile/Ledger. Lista aprobatorilor nu atribuie roluri; candidații trebuie să aibă scope-ul nativ `EmployeeHolidayApproval`. Aprobarea propriu-zisă rămâne neactivată până la etapa sa.

Cele patru entități private păstrează ACL-ul own/all din 0.1.0. CRUD generic, link/unlink, mass update/delete și export generic rămân refuzate, inclusiv adminului. Descărcarea Attachment păstrează verificarea cererii-părinte. Nu se introduce citire publică a cererilor pentru Calendar.

**#Admin nu este definit sau modificat de noul pachet.** În laborator, override-ul global Attendance original și calendarView Holiday original încă există deoarece originalele nu au fost dezinstalate. Nu pretindem că noul modul a restaurat global aceste rute; testul de retenție confirmă că nu le-a modificat.

## 4. Reutilizare și adaptări justificate

Inventarul complet, inclusiv fișierele originale și destinațiile, este în [MIGRATION-INVENTORY.md](MIGRATION-INVENTORY.md), [MIGRATION-INVENTORY.json](MIGRATION-INVENTORY.json) și [STAGE-2-REUSE.json](STAGE-2-REUSE.json). Sunt urmărite **196 fișiere runtime/lifecycle originale**, nu numai componentele activate. Cele 175 de importuri inițiale din REUSE-MAP.json și hash-urile surselor continuă să fie verificate.

| Componentă originală | Reutilizare / modificare |
| --- | --- |
| Attendance Tools/Attendance/AttendanceSettingsService.php | Păstrate get/update, valori implicite și validări; namespace/config proprii, ACL nativ și allowlist |
| Holiday Tools/HolidayAdministration/HolidaySettingsService.php | Păstrate câmpuri, limite, semnături și ConfigWriter; eliminat fallback admin din subsetul activ, validare eligibilitate aprobatori |
| Holiday Tools/HolidayBalance/HolidayBalanceService.php | Extrase metodele de profil/ledger și Complete Initial Setup în HolidayProfileService; fără rescrierea formulelor sau a ledger-ului |
| Holiday src/views/admin/profiles.js și src/views/modals/complete-initial-setup.js | Tabelul, modalul și field views native păstrate; endpoint-uri/traduceri adaptate, fără expunerea corecțiilor încă neimplementate |
| Holiday src/views/fields/annual-reset-date.js | Comportament neschimbat; testul original este reutilizat cu numai calea modulului adaptată |
| Attendance src/views/admin/manager-index.js | Panourile existente adaptate la secțiuni ACL și #EmployeeAdministration |
| Cele două entityDefs/Settings.json | Declarațiile utile refolosite în metadata proprie, fără suprascrierea Settings global |
| Generatoarele PDF/XLSX/DOCX, template DOCX, BalanceMath și BookingDatePolicy | Păstrate din 0.1.0; regresia executată din nou pe 0.2.0 |

Adaptări punctuale necesare:

- Ownership uniform `assignedUser`, verificare actor/beneficiar și fără fallback de privilegii admin în operațiile delegate.
- Tranzacție pe întregul batch; lock pe User existent înainte de profil, inclusiv când profilul încă nu există; ordine stabilă a beneficiarilor pentru batch.
- Fingerprint `operationHash` pe ledger leagă cheia de idempotency de operație și parametri. Refolosirea aceleiași chei pentru alt beneficiar/payload produce 409, nu un rezultat al altei operații.
- Un adaptor UI mic pentru setări conectează serviciile reutilizate la field views native. Vechile views/settings/record/edit erau legate de Settings global și #Admin, iar PUT Settings ca manager este verificat 403. Nu a fost reinventat un editor numeric/date sau sistemul Config.
- Modelul, paginile și interdicțiile CRUD din 0.1.0 sunt extinse, nu înlocuite de un framework nou.

**Eliminări funcționale: niciuna în această etapă.** Originalele și pending sunt păstrate. Butoanele pentru corecții/istoric de corecții, inițializarea arhivei, resetarea datelor de test și fluxurile dependente de infrastructură nelivrată nu sunt expuse. Acestea sunt diferențiate de excluderile arhitecturale deja aprobate: mandatory grants, override #Admin/Calendar, injecții globale de meniu, configurator paralel de normă și lifecycle cu mutații globale. Justificările și sursele sunt în inventar.

## 5. Teste executate și dovezi

| Verificare | Rezultat observat | Dovadă / limită |
| --- | --- | --- |
| Build și contract | **PASS** | JSON/manifest, lint pentru 19 fișiere PHP active, un modul, ZIP allowlist și provenance pentru toate sursele/inventarul |
| API administrare delegată | **PASS: 120 verificări, 0 eșecuri** | [stage-2-api.json](tests/evidence/stage-2-api.json) |
| Browser Chromium, nouă categorii de roluri | **PASS: 51 verificări, 0 eșecuri** | [stage-2-browser.json](tests/evidence/stage-2-browser.json) |
| Regresie API/ACL/confidențialitate 0.1.0 pe 0.2.0 | **PASS: 126 verificări, 0 eșecuri** | [stage-2-regression-api.json](tests/evidence/stage-2-regression-api.json) |
| Generatoare și utilitare originale | **PASS: 13 verificări** | [stage-2-renderers.json](tests/evidence/stage-2-renderers.json); PDF/XLSX/DOCX, Remote/CIC/CO în XLSX, calcule sold și politică date |
| Regresia originală date anuale | **PASS: 2 teste** | tests/regression/reset-date-field.test.mjs; MM-DD normalizat și date/valori invalide lăsate validării native |
| Instalare / rebuild / clear-cache | **PASS** | Comenzile native executate cu succes pe ZIP-ul final în laborator |
| Retenție la reinstalare 0.2.0 și rebuild | **PASS** | [stage-2-lifecycle.json](tests/evidence/stage-2-lifecycle.json); configurații, profile, ledger, roluri, originale, WTC și rute globale neschimbate |
| app-check | **PASS** | Migration not needed, Database, Not in maintenance mode, Cron enabled: OK; nu este o probă de execuție Jobs Employee Management |

### Ce s-a verificat efectiv

- Autentificare verificată înaintea refuzurilor ACL; intersecții entry/section/read all/field ACL. Acces refuzat și prin URL direct și API, nu numai ascunderea meniului.
- În browser: intrare nativă în meniu, panouri filtrate, salvare Attendance/Holiday cu reîncărcare, inițializare profil, completare sold inițial prin modalul original și salvarea parametrilor. Fără butoane de corecție sau arhivare neactivate; zero erori JS în scenariile etapei 2 testate.
- API: tipuri și chei invalide, cross-section, refuz admin/beneficiar admin/inactiv, fără escaladare în Settings/Role/WTC; candidatură aprobare numai prin drepturi native.
- Retry fără ledger duplicat; cheie cu alt payload/beneficiar refuzată; actualizarea parametrilor nu corectează soldul; completarea inițializării nu devine o corecție repetabilă.
- Batch valid-urmat-de-invalid: rollback pentru profil **și** ledger, nu numai un răspuns de eroare.
- Două cereri HTTP efectiv concurente cu aceeași operație de inițializare: 200/200, exact un rezultat duplicate, un singur profil și o singură intrare ledger.
- Cererile/documentele private ale colegului rămân inaccesibile; generic CRUD/mass/link/export rămân blocate. Regresia 0.1.0 este păstrată separat de probele istorice ale etapei 1.

În automatizarea browser s-au adaptat selectorii la data-name nativ și introducerea numerelor prin evenimente reale de tastatură pentru AutoNumeric. Nu s-au rescris widget-urile originale pentru a satisface testul. Prima încercare a testului suplimentar de retenție nu a putut citi fixture-ul privat ca www-data; rularea CLI corectă a laboratorului a executat ulterior testul complet. Aceste încercări nu au fost numărate drept PASS.

Proba suplimentară cu openingBalance="1e999" a demonstrat inițial HTTP 500: fingerprint-ul introdus în adaptor era calculat înaintea validării valorii finite. Validarea originală este acum executată înainte de hash, cu HTTP 400 verificat în regresie. Nu s-au modificat formulele sau regulile de sold pentru a corecta această eroare de integrare.

## 6. Limite și etapa următoare

**Nelivrate / NOT TESTED funcțional în noul modul:** cereri/aprobări/respingere/anulare, rezervări de concediu, corecții/resetări de sold și istoric de corecții, acordări automate, operații de pontaj, înghețare/corecții manageriale, integrare WTC/snapshot/ZileSarbatoare, consistență CIC/CCC, Jobs/documente la aprobare și recovery, Calendar comun minimal și exporturi prin endpoint-uri. Codul original relevant este inventariat și păstrat pentru integrare incrementală.

Testarea concurenței acoperă inițializarea publicată în această etapă, nu toate cursele viitoare de aprobare/anulare/reset. Nu declarăm rezolvate integral P1–P11. P1/P2 sunt întărite pentru suprafața nou expusă, P6/P10 nu sunt reintroduse; restul problemelor se închid odată cu componentele corespunzătoare.

Nu sunt declarate testate: alte versiuni EspoCRM/PHP/dependențe, instalare standalone fără originalele din laborator, upload ZIP prin UI Extension Manager, uninstall complet, TUVTK Theme și toate căile portal/API-user/import. Upgrade-ul nativ de dezvoltare și retenția la reinstalare nu înlocuiesc întreaga suită de release/lifecycle.

Următoarea etapă activează incremental serviciile Holiday existente: cereri, rezervări, solduri și decizii, cu autorizare și consistență pentru fiecare comandă; aprobarea definitivă rămâne condiționată de fluxul Jobs/documente aprobat. Nu se pornește o rescriere generală.

## 7. Reproducere și protejarea mediului

Build local: bash EmployeeManagement/build.sh. Contract: python3 EmployeeManagement/tests/contract.py. Regresie câmp anual: node EmployeeManagement/tests/regression/reset-date-field.test.mjs.

Fixture-uri: tests/integration/stage-2.php, stage-2-lifecycle.php și tests/browser/stage-2.cjs; folosesc opt-in EMPLOYEE_TEST_LAB=employee-feasibility-20261020, versiune/siteUrl de laborator și parole sintetice furnizate prin mediu. Nu se rulează pe instalația companiei. Testele de regresie API și generatoare reutilizează fixture-urile etapei 1.

Dovezile JSON sunt păstrate în tests/evidence; screenshot-urile și fixture-urile temporare sunt în /tmp/employee-feasibility-20261020-yDmmnL/employee-stage2-*. Browserul este efemer. La încheiere laboratorul este oprit cu volumele păstrate; nu se șterge niciun volum. Instalația companiei păstrează aceleași ID-uri de containere și StartedAt ca înaintea etapei.

Toate schimbările acestei etape sunt în EmployeeManagement/. Sursele Attendance/Holiday/ZileSarbatoare și scriptul build comun nu au modificări. Nu s-a executat commit/push.
