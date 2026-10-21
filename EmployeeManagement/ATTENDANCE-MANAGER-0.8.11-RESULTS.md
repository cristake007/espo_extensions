# Employee Management 0.8.11 — Manager Attendance UI

## Cauză și corecție

`#EmployeeAttendanceOverview` folosea dropdown-ul `manager-mark-attendance`, deschidea obligatoriu modalul `attendance-correction` și trimitea operația `managerMark`. API-ul cerea cheia `reason`, iar `AttendanceOverviewService::markEmployee()` respingea orice marcare managerială cu motiv gol. Aceeași comandă acoperea atât marcarea uzuală, cât și corecțiile excepționale permise după înghețarea lunii.

În 0.8.11, celulele editabile afișează direct P, D și CM. Comanda nouă `managerMarkRoutine` nu primește și nu stochează un motiv, dar păstrează actorul manager, ACL/FLS, lock-urile User→Month→Record, revizia optimistă, WTC, Holiday și CIC/CCC. Este permisă numai în lunile editabile. Comanda existentă `managerMark` nu a fost relaxată: continuă să solicite motiv pentru corecțiile excepționale, inclusiv cele din luni înghețate.

Frontend-ul marchează vizual opțiunea activă, ignoră click-ul pe ea, serializează scrierea per angajat/zi, actualizează imediat celula și sumarul, iar la eșec restaurează statusul anterior. Toate butoanele redevin editabile după finalizarea request-ului, astfel încât managerul poate corecta imediat o selecție greșită prin alt click.

## Upgrade fără pierderi

Upgrade-ul nativ 0.8.10 → 0.8.11 a fost executat numai în laboratorul izolat EspoCRM 10.0.1. Înainte și după instalare au fost comparate numărătorile și hash-urile câmpurilor de business:

| Set de date | Înainte | După | Hash identic |
| --- | ---: | ---: | :---: |
| EmployeeAttendanceRecord | 4.486 | 4.486 | Da |
| EmployeeAttendanceWorkSchedule | 29 | 29 | Da |
| EmployeeAttendanceMonth | 10 | 10 | Da |
| EmployeeHolidayRequest | 311 | 311 | Da |
| EmployeeHolidayProfile | 344 | 344 | Da |
| EmployeeHolidayLedger | 895 | 895 | Da |
| EmployeeAbsence | 65 | 65 | Da |
| Documente Holiday | 108 | 108 | Da, inclusiv SHA-256 conținut |

Configurările Attendance/Holiday, `calendarEntityList` și `tabList` au rămas identice. Pachetul final a fost reinstalat încă o dată peste 0.8.11 după fixture-urile funcționale; toate numărătorile și hash-urile au rămas din nou identice. Nu există modificări de schemă, migrare de date sau apeluri de reset în lifecycle.

Dovezi: `tests/evidence/attendance-manager-0.8.11-upgrade.json` și `tests/evidence/attendance-manager-0.8.11-replacement.json`.

## Validare

- Test frontend Node: 7/7 PASS — randare, active state, fără modal/reason, no-op, rollback și rapid-click.
- Test API laborator: 13/13 PASS — ACL, allowlist, P→D, idempotency, audit, revision conflict, frozen month, reason-ul corecției excepționale, CIC și Holiday.
- Test browser Chromium pe interfața reală: 15/15 PASS — grup de 88 px, etichete accesibile, D→P→D, fără modal, anti-duplicare, HTTP 503 rollback, CIC și lună înghețată.
- Contract/provenance, JSON/PHP validation, PDF layout, Holiday polling și reset-date regressions: PASS.
- Testul istoric `tests/regression/navigation-lifecycle.php` este FAIL înainte de a testa Attendance: apelează metoda eliminată anterior `TabList::install()`. Nu a fost modificat sau slăbit în această livrare; contractul actual de navigație este PASS.

Captură browser: `tests/evidence/attendance-manager-0.8.11.png`.

Artefact: `dist/employee-management-0.8.11.zip`
SHA-256: `0bddfa3aecbea2385f92c28d633f18170f9fa4cf115f9960528963671cee15b5`
