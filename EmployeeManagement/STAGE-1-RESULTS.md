# Employee Management 0.1.0 — rezultate etapa 1

Data: 20 octombrie 2026. **Implementare instalabilă de dezvoltare; nu production-ready.**

## Livrabil și mediu

- Arhitectura a fost actualizată cu deciziile finale: 10.0.1, WTC nativ + istoric read-only, normă distinctă de disponibilitate personală, Jobs cu ApprovalProcessing recuperabil și Approved numai după document, confidențialitate și separarea administratorului tehnic.
- Director: `EmployeeManagement/`; un singur modul instalat: `EmployeeManagement`.
- Pachet: `dist/employee-management-0.1.0.zip`, **80.904 bytes**.
- SHA-256: `0e4cf3c5f1bf4c72ad730793745ed6728fb5ed7b370dce2a22bc8187aaf3fbb3`.
- Manifest: exact **EspoCRM 10.0.1**, **PHP 8.4.23**, **Zile Sărbătoare 0.9.5**. Nu declarăm compatibilitate cu alte versiuni.
- Runtime: laboratorul existent `employee-feasibility-20261020`, aceleași imagini ca în fezabilitate; MariaDB **12.3.2**. Nicio infrastructură permanentă nouă.
- Originalele Attendance 1.11.0, Holiday 1.9.4 și ZileSarbatoare 0.9.5 au rămas instalate în laborator. Singura extensie scoasă a fost sonda temporară **Feasibility Probe**, care ocupa ruta EmployeeAdministration. Arhivele ei și datele sintetice sunt păstrate; poate fi reinstalată.

Instalația companiei nu a fost folosită pentru scrieri, instalări sau restart. Cele patru containere existente au aceleași ID-uri și StartedAt ca în raportul de fezabilitate. Sursele originale Attendance/Holiday/ZileSarbatoare și scriptul comun build.sh nu au modificări tracked. Nu s-a făcut commit/push.

## Ce funcționează efectiv

1. Instalare prin backend-ul nativ Extension Manager, folosind CLI `bin/command extension --file=...`; metadata și schema sunt înregistrate de rebuild-ul EspoCRM.
2. Patru entități ORM reutilizate/adaptate: `EmployeeAttendanceRecord`, `EmployeeHolidayRequest`, `EmployeeHolidayProfile`, `EmployeeHolidayLedger`. Ownership nativ uniform `assignedUser`, relații între cerere/profil/ledger, indexuri și câmpuri păstrate din modele.
3. Liste/detail native securizate: own pentru angajat, all numai dacă rolul acordă explicit. Nu există mandatory grants pe noile entități sau Calendar.
4. Navigare nativă în meniul utilizatorului, workspace `#EmployeeManagement`, pagină autorizată de stare `#EmployeeAdministration`. Aceasta din urmă **nu configurează încă setări** și nu expune WTC/Roles/Settings globale.
5. API status cu verificare server-side a actorului regular/activ și a scope-ului. Administratorul tehnic primește 403 pe aceste operații de workspace.
6. Scrieri CRUD, link/unlink, mass update/delete și export generic blocate pe backend în această etapă, inclusiv când un rol acordă prea mult sau când actorul este admin.
7. Documente private: download-ul nativ Attachment verifică ACL-ul cererii-părinte.
8. Generatoarele PDF, XLSX și DOCX existente rulează din noul namespace pe date sintetice. Nu sunt încă expuse prin endpoint-uri de export/aprobare.

Nu există create/edit/aprobare/pontare operațională în această versiune. Datele testate au fost introduse ca fixture-uri ORM exclusiv în laborator; acest lucru **nu este** un flux de business finalizat sau un bypass API oferit utilizatorului.

## Reutilizare, nu rescriere

`REUSE-MAP.json` urmărește **175 fișiere importate**: **24** active și **151** păstrate în pending, separat de codul nou. Template-ul DOCX este suplimentar, identic byte-for-byte. ZIP-ul conține 69 fișiere sub `files/`, manifest și README.

| Cod existent | Tratament în această etapă |
| --- | --- |
| AttendancePdfGenerator, AttendanceXlsxGenerator | Instalate; numai namespace adaptat; randare verificată |
| HolidayDocumentGenerator și template DOCX | Instalate; generator numai namespace adaptat, template identic; randare verificată |
| BalanceMath, BookingDatePolicy | Instalate; adaptări mecanice; probe de comportament păstrate |
| Modele AttendanceRecord/HolidayRequest/Profile/Ledger și layouts utile | Reutilizate, prefixate; assignedUser, autori nativi, read-only; Holiday rămâne Event dar nu este sursă Calendar publică în 0.1.0 |
| Servicii de sold/ledger/decizie/anulare, pontaj/înghețare/CIC/CCC, overview-uri și UI utile | Păstrate în pending cu namespace/referințe adaptate; **nu sunt instalate sau declarate operaționale** |
| Mandatory grants, override-uri #Admin/navbar admin, Calendar global și câmpuri Settings globale invazive | Nu au fost transferate în pachetul activ; înregistrările globale cunoscute au fost excluse și din importul pending |
| Editorul vechi de ore normative | Nu este înregistrat sau expus; viitoarea integrare citește exclusiv WTC, păstrând numai metadatele Attendance justificate |

Testul de contract verifică hash-urile surselor originale și faptul că fișierele PHP importate au exclusiv transformările mecanice declarate. Integrarea ulterioară a serviciilor va necesita adaptări funcționale țintite, în special queries user→assignedUser; copia pending nu este gata de activare integrală.

## Rezultate pe pachetul final

| Verificare executată | Rezultat | Dovezi / limite |
| --- | --- | --- |
| Validare JSON, manifest, modul și lint PHP activ | **PASS** | Build-ul comun validează cele 14 fișiere PHP active; un singur modul |
| Proveniență și allowlist ZIP | **PASS** | `python3 EmployeeManagement/tests/contract.py`; pending/teste/rapoarte nu intră în arhivă |
| Instalare Extension Manager CLI | **PASS** | Instalare inițială și înlocuiri de dezvoltare 0.1.0→0.1.0 pe laborator; nu upgrade de producție |
| Rebuild și clear-cache | **PASS** | Comenzile native au terminat cu succes pe ZIP-ul final |
| app-check | **PASS** | Migration not needed, Database, Not in maintenance mode, Cron enabled: OK; nu pretindem că aceasta testează execuția unui nou Job Employee Management |
| API/ORM/ACL | **PASS: 126 verificări, 0 eșecuri** | [api.json](tests/evidence/api.json) |
| Browser | **PASS: 30 verificări, 0 eșecuri** | [browser.json](tests/evidence/browser.json), Chromium efemer în rețeaua laboratorului |
| Randare și utilitare reutilizate | **PASS: 13 verificări** | [renderers.json](tests/evidence/renderers.json); PDF semnătură validă, XLSX ZIP/worksheet/Remote/CIC/CO, DOCX ZIP/date/nume/semnături, matematică sold și politică date |
| Retenție după înlocuirea build-ului și rebuild | **PASS** | [lifecycle.json](tests/evidence/lifecycle.json): câte două înregistrări în fiecare entitate nouă; originalele, numărătorile WTC, tabList/calendarEntityList și metadata globale rămân identice |

### Ce demonstrează cele 126 verificări API

- Autentificarea celor cinci utilizatori regular sintetici este verificată separat (`App/user` 200), înainte de a considera un 403 drept refuz ACL.
- Pe toate cele patru entități: proprietarul citește propriul record; lista sa conține numai recordul propriu; managerul citește recordul altui angajat dacă are read all; colegul, utilizatorul refuzat și cel fără rol nu îl pot citi prin ID.
- Proprietarul, managerul și admin nu pot POST/PUT/DELETE; managerul și admin nu pot mass update/delete sau export generic. Rolurile de test acordă deliberat create/edit/delete prea larg, pentru a verifica și granița backend a etapei.
- Workspace și administrare: manager autorizat 200, angajat numai workspace 200, administrare 403; admin și utilizatorii neautorizați 403.
- Link/unlink pe profil→cereri refuzate managerului și adminului; listarea cererilor prin profilul colegului este refuzată.
- Reproducerea noii politici P2: cererea colegului returnează 403 și nu conține nota privată sintetică. Download: owner/manager 200, coleg/utilizator refuzat 403.
- Numărătorile entităților originale rămân neschimbate pe durata suitei.

### Corecții rezultate din teste, nu PASS presupus

Prima probă browser a identificat o apelare frontend greșită la `this.error403()` și o verificare de listă care presupunea coloana name din layout-ul vechi. Corecția folosește `baseController.error403()` nativ pentru refuzul inclusiv pe rute SPA și include identificarea cererii/angajatului în lista privată. Testele finale au fost reluate pe ZIP-ul corectat.

La încercarea colegului de a deschide un record privat, view-ul Record nativ redă 403 și poate raporta în bundle excepția opacă `i` (fără stack). Proba o înregistrează explicit ca excepție nativă a scenariului negativ; nu o ascunde ca „zero erori JS” și nu modifică core-ul. Managerul, proprietarul, admin și utilizatorul fără rol nu au erori JS neașteptate în scenariile testate. Nicio notă privată nu este afișată colegului.

## Situația auditului și limitările rămase

| Audit | Situație în 0.1.0 |
| --- | --- |
| P1 | Fundamente ACL corecte și mutații generice refuzate, testate. Invarianti/eligibilitate pe toate comenzile viitoare rămân de implementat; nu declarăm P1 închis pentru produsul complet |
| P2 | Expunerea prin ID/document este eliminată pe noile entități testate; Calendarul comun minimal nu este încă activ |
| P3–P5 | Comenzile vulnerabile nu sunt instalate. Locking/revizii, suprapuneri și ciclul anulare→Attendance rămân pentru etapele de business |
| P6 | Noul pachet nu suprascrie #Admin. Coexistența în laborator păstrează override-ul **original** Attendance; restaurarea finală a rutei native într-o instalare fără originale nu este declarată testată |
| P7 | Câmpuri de autor native introduse în modelele reutilizate; auditul corecțiilor/freeze se testează când se activează scrierile |
| P8 | Generatoare păstrate și randare demonstrată; ApprovalProcessing/Jobs/snapshot/recovery/ledger asincron nu sunt implementate aici |
| P9 | Nu există overview-ul operațional activ; optimizarea batch și query profiling rămân de făcut |
| P10 | Navigarea nouă este exclusiv metadata, fără modificare tabList sau cleanup distructiv. Retenția la înlocuirea build-ului este demonstrată; upgrade real N→N+1/uninstall complet rămân pentru testele lifecycle |
| P11 | Noul pachet nu modifică calendarView. Laboratorul încă are override-ul Holiday original; compoziția finală cu ZileSarbatoare rămâne de verificat la activarea Calendarului comun |

**NOT TESTED / încă nelivrate:** upload prin interfața web Extension Manager, instalare standalone fără pachetele originale, alte versiuni EspoCRM/PHP/ZileSarbatoare, TUVTK Theme, toate căile import/bulk/portal/API-user, curse concurente de business, snapshot/freeze, integrarea normei WTC în noul modul, solduri/aprobări operaționale, Jobs Employee Management și recovery, Calendar comun, exporturi prin endpoint-uri, upgrade N→N+1 și uninstall complet al produsului. Nu confundăm probele de fezabilitate anterioare cu implementarea acestor funcții.

Datele originale au fost confirmate de utilizator drept date de test. Nu a fost totuși necesară ștergerea lor: noile entități sunt distincte, iar probele folosesc numai fixture-uri sintetice. Nu există migrare automată din pachetele vechi.

## Următoarea etapă

Continuăm cu ACL operațional și administrare delegată: scopes native pentru fiecare comandă, selecție regular/activă și validare separată actor/beneficiar, API settings allowlist și reutilizarea interfețelor native. Apoi activăm incremental serviciile existente Holiday, nu le rescriem. Ordinea ulterioară rămâne cea cerută: Attendance/WTC și istoric, consistență/CIC, Jobs/documente, Calendar minimal, regresie/exporturi/upgrade.

Operațiile încă nesecurizate nu se activează înaintea testelor. Pachetul 0.1.0 este o bază funcțională verificabilă pentru dezvoltare, **nu autorizare de instalare în producție**.

Dovezile temporare suplimentare și screenshot-urile sunt în `/tmp/employee-feasibility-20261020-yDmmnL/employee-stage1-*`. Dovezile JSON finale sunt copiate și în `tests/evidence/` pentru trasabilitate. La final laboratorul este oprit, cu volumele sintetice păstrate; niciun volum existent nu este șters.
