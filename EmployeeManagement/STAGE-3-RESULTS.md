# Employee Management 0.3.0 — Etapa 3: Holiday Management

Data: 20 octombrie 2026. Bază: 0.2.0. **Implementare funcțională incrementală, nu production-ready.** Documentul arhitectural aprobat, Attendance Management 1.11.0, Holiday Management 1.9.4 și instalația companiei nu au fost modificate.

## 1. Livrare și mediu

- [employee-management-0.3.0.zip](dist/employee-management-0.3.0.zip), **114.628 bytes**.
- SHA-256: `9d210d47bc12a8c61a5f005fca0d403f84c0bf86fe018c7475dab3079dc09069`.
- Un singur modul EmployeeManagement: **102 fișiere runtime**, 104 membri ZIP în total; 23 PHP, 62 JSON, 15 JS. Pending, teste și rapoarte nu intră în pachet.
- Compatibilitate declarată/testată exclusiv: **EspoCRM 10.0.1 / PHP 8.4.23 / Zile Sărbătoare 0.9.5**. DB de laborator: MariaDB 12.3.2. Fără upgrade al platformei.
- Laborator reutilizat: proiect `employee-feasibility-20261020`, Compose `/tmp/employee-feasibility-20261020-yDmmnL/compose.yaml`. Browserul este efemer pe aceeași rețea izolată.
- Instalare prin Extension Manager nativ, `php bin/command extension --file=...`; nu se copiază clase direct în runtime. Au fost instalate trecerea 0.2.0→0.3.0 și build-urile de dezvoltare 0.3.0. ZIP-ul indicat este exact cel instalat și verificat.

Originalele rămân instalate în laborator. Scrierile folosesc utilizatori regular, Roles, cereri, profiluri și calendare sintetice distincte. Nu s-au dezinstalat extensii, șters volume sau folosit datele companiei.

## 2. Funcționalități implementate

### Cereri și contabilizare

- Creare proprie Pending și rezervare imediată a zilelor, conform Holiday original. Soldul se debitează o singură dată la rezervare; aprobarea viitoare nu va face încă un debit.
- Rezervare managerială pentru un coleg eligibil. Regula originală a rezervării retroactive numai în luna curentă rămâne; angajatul nu poate depune o nouă cerere în trecut.
- Editare Pending: numai date/descriere, fără reatribuirea beneficiarului sau modificarea directă a statusului, zilelor ori documentelor. Recalcularea explicită a datelor reconciliază delta în sold/ledger. Editarea numai a descrierii păstrează snapshot-ul rezervat.
- Suprapuneri blocate numai de Pending, ApprovalProcessing, Approved și CancellationPending. Cancelled/Rejected nu mai blochează rezervări noi pe intervalul păstrat în istoric.
- Validările originale: date reale YYYY-MM-DD, capete inclusive, maximum 367 zile, cel puțin o zi lucrătoare, eligibilitate regular/activă, profil inițializat, limitele rezervării în trecut și limita negativă configurată. Payload-urile suplimentare sunt refuzate.
- Retragerea Pending restituie rezervarea și păstrează cererea ca Cancelled cu audit/ledger; nu execută DELETE fizic.
- Respingerea autorizată restituie rezervarea atomic. Managerul își poate decide propria cerere; nu s-a adăugat o interdicție de auto-decizie.

### Anulări

| Operație | Condiții păstrate | Efect contabil |
| --- | --- | --- |
| Approved → CancellationPending | Beneficiarul, motiv obligatoriu, înainte de începerea concediului | Nicio restituire; absența rămâne efectivă |
| CancellationPending → Cancelled | Aprobator configurat, autorizat nativ, revizie curentă | Restituire exact o dată |
| CancellationPending → Approved | Refuzul anulării unui concediu deja aprobat; documentul existent trebuie să aibă Attachment și fișier persistat | Fără debit suplimentar |
| Approved → Cancelled direct | Aprobator autorizat, motiv obligatoriu | Restituire exact o dată, documentele rămân istorice |

Aceste ramuri sunt executate în teste pe **fixture-uri Approved istorice cu documente sintetice**, create explicit de test în laborator. Nu sunt dovada implementării generării/aprobării prin Jobs. Un fixture fără document nu poate reveni la Approved prin refuzul anulării.

### Solduri, ledger și UI

Se păstrează formulele originale `balance`, `pendingDays`, `availableDays = balance + pendingDays`; ApprovalProcessing este inclus în rezervările în curs. Citirea soldului și a rezervărilor folosește aceeași tranzacție/lock pe beneficiar pentru a nu combina două momente contabile.

Corecțiile originale `correct` și `listCorrections` sunt active, împreună cu modalele originale. Au scope distinct: administrarea profilurilor singură nu acordă corecții. Formularul trimite și soldul pe care l-a afișat; dacă între timp soldul s-a schimbat, comanda este refuzată cu Conflict. API-ul delta original rămâne disponibil utilizatorului autorizat.

`#EmployeeManagement/holiday` oferă lista proprie, formularul reutilizat, editare/retragere și coada privată pentru aprobatori. `#EmployeeAdministration/profiles` expune corecția/istoricul numai rolului potrivit. Componentele native model/field views/modal/confirm/router sunt reutilizate; nu se adaugă un configurator WTC sau o administrare globală.

## 3. WTC și granița Jobs

Calculatorul original păstrează parserul, validările intervalului și regulile de rezervare; numărarea zilelor normative este adaptată la:

- `Espo\Tools\WorkingTime\CalendarFactory::create(WorkingTimeCalendar)`;
- `CalendarUtilityFactory::create(Calendar)`;
- `CalendarUtility::isWorkingDay` și `getSummedWorkingHours`, cu `Espo\Core\Field\DateTime` și timezone-ul calendarului;
- providerul original RO/ZileLibere pentru sărbătorile legale.

Nu se folosește createForUser pentru debit, deoarece indisponibilitățile personale ar putea scădea concediul încă o dată. WTC explicit lipsă este eroare de configurare, fără fallback L–V/Team/global. Metoda veche count/L–V nu este instalată în runtime; sursa integrală rămâne în pending. Employee Management nu creează/editează/atribuie WTC.

`calculationSnapshot` read-only păstrează calendarId, timezone, interval, zile/ore rezultate, distribuția pe luni și zilele publice excluse. Schimbarea WTC după rezervare nu modifică soldul/snapshot-ul contabilizat. Modificarea explicită a datelor Pending produce un nou rezultat rezervat și revizie. Nu este un al doilea sistem de configurare a programelor.

**Pending → ApprovalProcessing → Approved nu este încă activat.** Endpoint-ul approve este refuzat cu 409, inclusiv pentru autoaprobarea unui manager autorizat, fără schimbarea stării sau soldului. Nu există aprobare sincronă, Approved fără document, Job fictiv ori Processing abandonat intenționat.

Integrarea ulterioară folosește aceeași cerere, accountingKey/revision, rezervare, snapshot și tranzacție de comandă. Inițierea va persista snapshot-ul deciziei și Job-ul nativ atomic; worker-ul va salva toate documentele înainte de Approved, cu retry/recovery idempotente. Aceste părți rămân **NOT TESTED ca implementare Employee Management**, nu sunt declarate livrate prin simpla existență a metadatelor stării. Generatoarele și template-ul DOCX existente sunt păstrate și testate separat.

## 4. Tranzacții și ACL

Comenzile folosesc `EntityManager::getTransactionManager()->run` și repository ORM `forUpdate`, cu ordinea **User → Profile → Request**. Citirea preliminară a unei cereri identifică numai beneficiarul; validarea autoritativă a statusului, reviziei și eligibilității se face sub lock. Fiecare comandă schimbă revizia, inclusiv când delta este zero.

Unicul owner al efectului contabil este serviciul Holiday reutilizat/adaptat. Nu se instalează în paralel hook-ul contabil vechi; nu se contabilizează o dată în command și a doua oară în afterSave. CRUD generic, relationship mutations, mass actions și export generic rămân închise. Se păstrează controllerul/Record Service nativ pentru lectura autorizată; nu se rescrie Record Service.

Crearea folosește o cheie accounting unică și fingerprint legat de actor/beneficiar/payload. Comenzile păstrează pe cererea privată receipt-ul operației: aceeași cheie/payload este retry, alt payload produce 409. Ledger-ul păstrează cheile unice și snapshot-urile înainte/după. Coliziunea DB nativă 1062 este mapată la Conflict numai după rollback-ul tranzacției. Nu se construiesc tabele de locks, cozi sau registre de permisiuni paralele.

| Drept nativ | Operații |
| --- | --- |
| EmployeeManagement + EmployeeHolidayBooking + Request read/câmpuri | Cereri proprii și comenzile aferente |
| Cele de mai sus + EmployeeHolidayRequestManagement + Request read all | Rezervare/gestionare Pending pentru colegi |
| EmployeeHolidayApproval + aprobator configurat + citire nativă a cererii | Respingere și decizii de anulare; queue cere read all; aprobarea nouă rămâne blocată pentru Jobs |
| EmployeeAdministration + EmployeeHolidayProfileManagement + Profile/Ledger read all/câmpuri + EmployeeHolidayBalanceCorrection | Corecții și istoric |
| Admin, portal, API-user, system, inactiv ca actor | Refuz business; nu se schimbă securitatea globală a platformei |

Beneficiarii se validează separat; admin/inactiv nu pot primi cereri/profiluri și nu apar în selector/coadă/lista operațională. Scopes sunt implicit false și instalarea nu atribuie Roles. Lista de aprobatori este rutarea existentă de business, nu acordă permisiuni și nu înlocuiește ACL-ul nativ.

Custom queries/comenzi respectă și field ACL, nu doar read own/all. Documentele rămân legate de cererea privată și de parent ACL nativ. Nu există citire publică pentru Calendar, route #Admin sau calendarView înregistrate de pachet. Override-urile originalelor încă instalate în laborator nu sunt restaurate/șterse de Employee Management.

## 5. Reutilizare și modificări

Proveniența componentelor și adaptările sunt în [MIGRATION-INVENTORY.md](MIGRATION-INVENTORY.md), [STAGE-3-REUSE.json](STAGE-3-REUSE.json) și inventarul JSON complet: **13 mapări integrate în această etapă**, toate cele **196 fișiere originale runtime/lifecycle** și cele **175 importuri inițiale** urmărite în continuare.

Reutilizate: HolidayBalanceService pentru profiluri/ledger, rezervări, ajustări, validări și condițiile/refundurile anulărilor; WorkingDayCalculator/NonWorkingDayProvider; managed-booking, managed-employee-select, managed-employee/start-date fields, cancellation-reason, balance-correction/correction-history; acțiunile originale profiles și conținutul privat al cozii. Generatoarele PDF/XLSX/DOCX, BalanceMath și BookingDatePolicy sunt păstrate.

Adaptări justificate: namespace/ownership, native Roles/field ACL, excludere admin, lock și recitire autoritativă P3, revizii/receipt/fingerprint, lista pozitivă de overlap P5, citirea WTC D6 și snapshot-ul stabil D7/P8. API actions și adaptorul paginii private conectează serviciile existente la suprafața securizată; nu reprezintă o rescriere a calculelor/exporturilor.

**Nu s-au șters funcționalități sau surse originale/pending.** Nu sunt transferate comportamentele incompatibile deja excluse: aprobarea sincronă fără granița Jobs, hook-ul contabil duplicat, configurarea normei L–V ca sursă paralelă și override-urile/granturile globale. Restul componentelor legitime rămân explicit inventariate pentru etapele următoare.

## 6. Teste și dovezi

Toate testele cu scrieri rulează în laboratorul izolat; nu doar inspecție de clase. Dovezile sunt JSON în tests/evidence.

| Verificare | Rezultat | Dovadă / domeniu |
| --- | --- | --- |
| Holiday API | **PASS: 125 verificări, 0 eșecuri** | [stage-3-api.json](tests/evidence/stage-3-api.json): requests/reservations/validări, toate ramurile anulării, limite sold, ledger, concurență și rollback |
| Regresie administrare 0.2.0 | **PASS: 120 verificări, 0 eșecuri** | [stage-3-regression-administration.json](tests/evidence/stage-3-regression-administration.json); testul original adaptat numai pentru faptul că correct este acum o comandă reală, refuzată 403 fără noul scope |
| Regresie ACL 0.1.0 | **PASS: 126 verificări, 0 eșecuri** | [stage-3-regression-acl.json](tests/evidence/stage-3-regression-acl.json): own/all/private fields, generic CRUD/mass/link/export blocate și documente private |
| Browser Chromium | **PASS: 27 verificări, 0 eșecuri** | [stage-3-browser.json](tests/evidence/stage-3-browser.json): angajat, manager, fără Booking, fără entry, admin; formulare, confirmări, coadă și corecții/istoric; zero erori JS în scenariile executate |
| Generatoare/utilitare reutilizate | **PASS: 13 verificări** | [stage-3-renderers.json](tests/evidence/stage-3-renderers.json): PDF, XLSX cu Remote/CIC/CO, DOCX, formule sold și politica datelor |
| Câmp dată anuală original | **PASS: 2 teste** | tests/regression/reset-date-field.test.mjs |
| Instalare / rebuild / clear cache / retenție | **PASS** | [stage-3-lifecycle.json](tests/evidence/stage-3-lifecycle.json): hash-uri cereri/snapshot/receipt, profiluri, ledger, setări și Roles; originale/WTC/rute globale păstrate |
| Build / contract / ZIP | **PASS** | JSON și lint 23 PHP active; un modul, scopes default false, proveniență/inventar și conținut ZIP identic cu sursa runtime |
| Native app-check | **PASS** | Migration not needed / Database / Not in maintenance mode / Cron enabled; nu este probă de execuție Jobs Employee Management |

### Probe contabile importante

- WTC normativ 6 ore, excepție nelucrătoare și ZileLibere: 3 zile rezervate; indisponibilitatea personală dintr-una dintre zile nu le reduce la 2.
- Schimbarea WTC la 8 ore nu rescrie snapshot-ul existent. Extinderea explicită a cererii ajustează zilele și soldul, iar modificarea doar a descrierii nu recalculează istoricul.
- Două POST-uri HTTP reale concurente pentru aceeași creare: 200/200, exact un duplicate și un debit. Două retrageri cu chei diferite: 200/409, o restituire. Două rezervări concurente suprapuse: un singur câștigător.
- Coliziune ledger injectată ca fixture după care comanda încearcă efectiv salvarea soldului: Conflict, sold restaurat prin rollback, fără cerere/ledger parțial. Extinderea peste limita negativă nu schimbă datele, revizia sau soldul.
- Retry-uri pentru editare/retragere/corecție/anulare nu repetă delta. Cheie refolosită cu alt payload și formular de corecție bazat pe sold vechi: Conflict.
- Pending neaprobat nu poate deveni Approved prin API, nici la autoaprobare. Processing este protejat de editare/retragere/respingere și păstrează rezervarea în probele de stare viitoare.
- Admin ca actor/beneficiar, coleg pe ID privat, lipsă scope și field ACL restrictiv: refuzuri backend verificate, nu numai butoane ascunse.

În timpul dezvoltării: prima probă de anulare a folosit un fixture Attachment fără targetField nativ, deci nu a trecut; fixture-ul a fost corectat cu document persistat și testul rerulat. Adaptorul UI avea o apelare greșită translateOption, corectată la Language nativ. Automatizarea date picker-ului a fost corectată să folosească formatul și tastarea/blur native; intervalul salvat este acum verificat explicit. Aceste încercări inițiale nu sunt numărate PASS.

## 7. Limite și continuare

**Nelivrate / NOT TESTED funcțional în noul modul:** programarea și execuția Job-ului de aprobare, retry/reconciliere după worker întrerupt, finalizarea Approved după toate documentele, calendarul comun minimal, proiecția absenței, integrarea completă Attendance–Holiday/CIC/CCC, snapshot-urile normative lunare/înghețarea și corecțiile de pontaj, reset/acordări anuale, overview/dashlet publicat, exporturi prin endpoint-uri și lifecycle complet de release/uninstall. Codul original rămâne păstrat, fără activarea prematură a operațiilor.

Testele de anulare Approved utilizează fixture-uri istorice; nu presupun un flux de aprobare funcțional. Sunt testate rezervările/validările Holiday curente, nu toate cursele viitoare cu marcajele Attendance sau finalizarea documentelor. P3/P5 sunt tratate pe comenzile publicate; nu declarăm toate P1–P11 închise.

Nu se declară testate: alte versiuni Espo/PHP/dependențe, instalare standalone fără originalele din laborator, upload prin UI Extension Manager, toate căile import/API-user/portal, toate temele, performanța la volum mare ori scenarii complete DST/miezul nopții. Politica today păstrează DateTime nativ/configurația platformei din codul original; norma folosește timezone-ul WTC. Alegerea timezone-ului operațional pentru go-live și cazurile-limită rămân de verificat fără schimbarea globală tacită a configurației.

Următoarea integrare continuă sursele existente, nu le rescrie: Attendance/WTC/ZileSarbatoare și consistența CIC/CCC, apoi snapshot-ul deciziei, Jobs/documente/recovery și Approved definitiv, urmate de Calendarul minimal și testele de release. Până atunci nu se activează aprobări provizorii.

## 8. Reproducere și protejarea mediului

Build: bash EmployeeManagement/build.sh. Contract: python3 EmployeeManagement/tests/contract.py. Regresie anuală: node EmployeeManagement/tests/regression/reset-date-field.test.mjs.

Fixture-uri de laborator: tests/integration/stage-3.php, stage-3-regression-administration.php, stage-3-lifecycle.php și tests/browser/stage-3.cjs; plus fixture-urile etapelor 1–2 reutilizate. Cer opt-in EMPLOYEE_TEST_LAB, versiunea/siteUrl exact de laborator și parole sintetice din mediu. Nu se rulează pe companie. Testele sunt datate pentru setul sintetic octombrie–decembrie 2026.

Snapshot-urile/screenshots temporare sunt în directorul privat /tmp al laboratorului; dovezile JSON sunt salvate în EmployeeManagement/tests/evidence. La final laboratorul este oprit, cu volumele păstrate. Containerele companiei păstrează aceleași ID-uri și StartedAt ca înaintea etapei; nu au fost restartate sau actualizate.

Toate modificările repository-ului sunt în EmployeeManagement/. Niciun commit/push, nicio modificare a extensiilor originale sau a documentului arhitectural aprobat.
