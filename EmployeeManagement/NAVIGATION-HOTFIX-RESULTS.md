# Employee Management 0.7.0 — corecție urgentă de navigație și acces delegat

Data: 20 octombrie 2026. Etapa 8 nu a fost începută. Acest raport înlocuiește concluziile anterioare numai pentru navigație și accesul la paginile manageriale; rapoartele etapelor rămân istorice.

## Cauza exactă

1. **Link vechi către administrația tehnică.** `files/client/custom/modules/employee-management/src/views/attendance/overview.js` conținea literal `href="#Admin/employeeAttendanceSettings"` pe butonul **Employee Schedules**. Adaptarea vechii pagini Attendance a păstrat destinația Admin, deși controllerul și pagina refactorizată existau deja la `#EmployeeAdministration/attendanceMetadata`. Controllerul nativ Admin refuză corect utilizatorii regular, indiferent de scopes Employee Management atribuite: acordarea tuturor rolurilor operaționale nu poate autoriza acea rută tehnică. Nu era necesară relaxarea ACL sau acordarea dreptului global de administrator.
2. **Sidebarul nu fusese înregistrat.** Scope-urile EmployeeManagement, EmployeeAdministration, EmployeeAttendance și EmployeeAttendanceOverview aveau `tab: false`; installer-ul nu adăuga aceste scope-uri în configurația nativă `tabList`, iar traducerile pentru etichetele native de tab lipseau. `app/clientNavbar.json` definea exclusiv două `menuItems`, adică meniul din dreapta sus. Acesta nu populează sidebarul.
3. **Verificare UI incompletă.** Testul Attendance anterior deschidea direct ruta delegată Remote/CIC; nu apăsa butonul defect din centralizator. Testele etapei 7 verificau configurarea anuală și Calendarul, fără un parcurs complet al meniurilor manageriale. O pagină funcțională accesată direct nu demonstra că navigația către ea era funcțională.
4. **Intersecție ACL incompletă pentru Remote/CIC.** Controllerul delegat verifica EmployeeAdministration, dar verificarea backend `assertScheduleEditor()` nu cerea și scope-ul comun de intrare. API-ul putea fi accesat de un manager cu drepturile Attendance complete, fără intrare delegată. Corecția aliniază pagina, vizibilitatea butonului și API-ul la aceeași intersecție de permisiuni.

## Implementare

- Butonul Employee Schedules folosește exclusiv `#EmployeeAdministration/attendanceMetadata`.
- `canManageSchedules` provine din verificarea serverului, incluzând actor regular activ, EmployeeManagement, EmployeeAdministration, Overview, Management și ACL/FLS de citire pentru datele operaționale. Vizitatorul cu numai Overview nu primește un buton către o pagină refuzată. Scrierea verifică suplimentar FLS edit, ca anterior.
- Patru scope-uri devin taburi native, cu iconuri și traduceri EN/RO. AfterInstall adaugă numai intrările lipsă, înainte de delimitatorul More, respectând ordinea și grupurile existente. Detectează și intrările deja plasate în grupuri. Reinstalarea nu le dublează.
- AfterUninstall elimină numai taburile proprii, inclusiv din grupuri. Codul de eliminare este autonom: în EspoCRM 10.0.1, AfterUninstall rulează după ștergerea fișierelor modulului. Nu depinde de o clasă a extensiei care ar fi fost deja eliminată. Curățarea este verificată separat; certificarea completă a dezinstalării rămâne în afara acestei remedieri.
- `#Admin`, Settings global, Roles și administrarea WTC nu au fost relaxate. Rolurile nu sunt acordate/modificate de installer. Nu există override global Admin/Navbar și nici inserare DOM pentru sidebar.

| Funcție | Ruta operațională |
| --- | --- |
| Workspace și date private | `#EmployeeManagement` |
| Pontaj propriu | `#EmployeeAttendance` |
| Centralizator | `#EmployeeAttendanceOverview` |
| Cereri concediu | `#EmployeeManagement/holiday` |
| Administrare delegată | `#EmployeeAdministration` |
| Politici Attendance | `#EmployeeAdministration/attendance` |
| Configurări Holiday | `#EmployeeAdministration/holiday` |
| Profiluri | `#EmployeeAdministration/profiles` |
| Employee Schedules / Remote–CIC | `#EmployeeAdministration/attendanceMetadata` |

Configurările și Remote/CIC erau deja implementate în zona delegată; a fost reparată destinația de navigație rămasă către Admin și integrarea în sidebar. Endpointurile de configurare continuă să folosească allowlist și ConfigWriter, fără acces delegat la întregul Settings.

## Probe executate

Laborator existent izolat `employee-feasibility-20261020`, EspoCRM 10.0.1 / PHP 8.4.23, port local 18089. Utilizator de test **regular, activ, non-admin**, cu rolul de management existent atribuit explicit; fără edit global User, Role sau WTC. Au fost creați utilizatori sintetici separați pentru scenariile de refuz. Nu s-au modificat rolurile originale. Instanța companiei nu a fost actualizată; se livrează ZIP-ul pentru instalare.

| Dovezi | Rezultat |
| --- | --- |
| `tests/evidence/navigation-before.json` | **4 PASS**: reproducere link Admin, absență sidebar, 403 după click pe Schedules, fără erori JS. Baseline-ul folosește pachetul anterior și configurația inițială a sidebarului. |
| `tests/evidence/navigation-browser.json` | **34 PASS**: patru intrări native unice, click real pe Schedules, salvare/reîncărcare/restaurare Remote, Attendance și Holiday, parametri profil salvați și restaurați, toate cele patru secțiuni delegate, pontaj/centralizator/concedii/workspace, zero erori HTTP neașteptate sau JS. `#Admin/employeeAttendanceSettings` rămâne 403. |
| `tests/evidence/navigation-acl-browser.json` | **54 PASS**: manager, angajat, numai intrare delegată, toate drepturile manageriale fără intrare delegată, numai citire overview și administrator tehnic. Acces direct UI/API și mutații fără drepturi refuzate. Managerul primește 403 la PUT Settings global, lista Roles și creare WTC. Administratorul tehnic este refuzat pe business. |
| `tests/evidence/navigation-records-browser.json` | **21 PASS**: linkurile workspace-ului încarcă toate cele patru liste native și detaliile de pontaj, cerere, profil și ledger; fără butoane generice create/edit și fără erori JS. |
| `tests/evidence/navigation-upgrade.json` | **17 PASS** pe reinstalare repetată: configurări Employee Management, calendarEntityList, rute Admin, Calendar view, navigație străină, Roles, Users, WTC/ranges, profiluri, ledger, cereri și Remote/CIC păstrate; fiecare tab propriu există o singură dată. |
| `tests/regression/navigation-lifecycle.php` | **5 PASS**: ordine/grupuri/More, idempotency, curățare selectivă și grupuri goale; executabil fără autoload Espo pentru eliminarea taburilor. |
| Build și contract | Lint PHP, JSON, identitate ZIP–surse, proveniență și absența linkurilor Admin în frontend: PASS. Regresia MM-DD: **2 PASS**. |

**109 verificări browser pentru remediere**, plus cele 4 controale de reproducere, 17 de upgrade și 5 pentru navigația din lifecycle. Sunt controale, nu funcționalități distincte. Rezultatele celor 109 verificări sunt rerulate pe ZIP-ul final instalat. Valorile de configurare modificate în probe sunt restaurate; profilul nou este o fixture sintetică. Capturi: `navigation-sidebar.png`, `navigation-attendance.png`, `navigation-holiday.png`, `navigation-profiles.png`, `navigation-schedules.png`, `navigation-admin-denied.png`.

GET Settings poate returna subsetul public nativ pentru un regular; testul verifică refuzul **scrierii** globale. Extensia nu modifică acest contract al platformei.

## Livrare și verificare după instalare

Pachet: `dist/employee-management-0.7.0.zip`, 186733 bytes.

SHA-256: `8a329d3ec5b4c2c87d182c47792f0033665774133d2a97b607ffedfd221ec78a`.

Instalare/upgrade inclusiv peste 0.7.0 prin installer-ul nativ EspoCRM, apoi reîncărcare completă a browserului. Intrările laterale sunt filtrate prin Roles native. Preferințele personale care înlocuiesc complet tabList global sunt respectate; taburile pot fi selectate în editorul nativ de navigație, fără acordarea unor permisiuni noi.

1. Autentificare cu manager regular; verificarea celor patru intrări din sidebar.
2. Deschiderea centralizatorului și click pe Employee Schedules; ruta trebuie să fie delegată.
3. Deschiderea tuturor celor patru secțiuni de administrare; salvare și reîncărcare pentru Attendance, Holiday, profiluri și Remote/CIC.
4. Accesul direct la `#Admin/employeeAttendanceSettings` trebuie să rămână refuzat.

Proveniența corecției și hash-urile anterioare sunt în `NAVIGATION-HOTFIX-REUSE.json` și `MIGRATION-INVENTORY.json`. Documentația/testele/pending nu intră în ZIP-ul instalabil; README-ul și întregul runtime corectat sunt incluse. **Etapa 8 rămâne neîncepută; remedierea nu reprezintă executarea sau certificarea ei.**
