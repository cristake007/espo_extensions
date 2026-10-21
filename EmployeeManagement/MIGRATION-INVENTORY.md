# Inventar și reutilizare — etapa 7 / 0.7.0

Surse principale: Attendance Management **1.11.0** și Holiday Management **1.9.4**, nemodificate. `MIGRATION-INVENTORY.json` acoperă exhaustiv **196 fișiere runtime/lifecycle**, cu hash-uri, destinații și explicații pentru înregistrările globale neinstalate. `REUSE-MAP.json` păstrează cele 175 de importuri inițiale; template-ul DOCX este identic byte-for-byte. Prezența codului în pending nu este considerată PASS funcțional.

## Componente integrate și fișiere originale

În tabel, A = `AttendanceManagement/files/custom/Espo/Modules/AttendanceManagement/`, H = `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/`. Frontend A/H = `AttendanceManagement/files/client/custom/modules/attendance-management/`, respectiv `HolidayManagement/files/client/custom/modules/holiday-management/`. Căile integrale sunt și în inventarul JSON.

| Componentă | Original | Modificări și justificări |
| --- | --- | --- |
| Politici Attendance | A `Tools/Attendance/AttendanceSettingsService.php` | Păstrate get/update, default-uri și validarea 0–120/bool; namespace/config noi, scope nativ distinct, regular activ și allowlist. Inițializarea originală a arhivei este reactivată în etapa 4, cu ACL backend și fără ștergerea datelor |
| Setări Holiday | H `Tools/HolidayAdministration/HolidaySettingsService.php` | Păstrate câmpuri, validări, limite, semnături și ConfigWriter. Fără fallback admin; aprobatori regular activi cu scope nativ. Unknown keys/tipuri greșite refuzate. Acordarea automată este opt-in explicit din etapa 7, prin aceleași câmpuri/validări și ScheduledJob nativ; Jobs de documente sunt active din etapa 6 |
| Profiluri și ledger | H `Tools/HolidayBalance/HolidayBalanceService.php` | Extrase metodele de profil și helper-ele în HolidayProfileService, fără dependențele aprobării/documentelor. Păstrate formule, delta, snapshot, date, culoare și condițiile Complete Initial Setup. Adaptare user→assignedUser, ACL și eligibilitate. Batch atomic, lock User→profil și fingerprint de idempotency pentru concurență și retry; nu un ledger nou |
| Tabel profiluri | Frontend H `src/views/admin/profiles.js` | Păstrate tabelul, modelele, field views native și fluxurile save/initialize/complete. În etapa 3 sunt reactivate acțiunile originale de corecție și istoric, numai cu scope-ul nativ distinct |
| Modal completare | Frontend H `src/views/modals/complete-initial-setup.js` | Același modal și float nativ; URL/traduceri adaptate, cheie de retry stabilă pe durata modalului |
| Dată anuală | Frontend H `src/views/fields/annual-reset-date.js` | Identic funcțional, inclusiv MM-DD; testul original `HolidayManagement/tests/phase-001/reset-date-field.test.mjs` are doar calea modulului adaptată |
| Panouri administrare | Frontend A `src/views/admin/manager-index.js` | Structură/panouri Bootstrap păstrate; secțiuni filtrate prin ACL, traduceri proprii și #EmployeeAdministration în loc de #Admin |
| Declarații câmpuri | A și H `Resources/metadata/entityDefs/Settings.json` | Refolosite în metadata proprie, fără Settings global. Tipuri/default-uri/limite păstrate. Aprobatorii folosesc multiEnum nativ cu candidați autorizați, fără User edit sau selector global nerestricționat |

Metode extrase din HolidayBalanceService: `listProfiles`, `bulkInitialize`, `initializeOne`, `findEligibleUser`, `validateCalendarColor`, `findLedgerByKey`, `createLedger`, `snapshot`, `mutationResult`, `duplicateResult`, `getDefaultNextResetDate`, `validateIdempotencyKey`, `validateDate`, `finiteNumber`, `message`, `completeInitialSetup`, `canCompleteInitialSetup`, `lockProfile`.

### Componente Holiday integrate în etapa 3

`STAGE-3-REUSE.json` fixează hash-ul fiecărei surse pending și destinația. `MIGRATION-INVENTORY.json` le leagă de căile originale de mai sus și hash-urile actuale ale destinațiilor, fără a pierde inventarul etapei 2.

| Fișier original | Destinație / adaptare efectivă |
| --- | --- |
| H `Tools/HolidayBalance/HolidayBalanceService.php` | `Tools/HolidayBalance/HolidayRequestService.php`: getMyBalance/getPendingDays, listManagedBookingUsers, prepareHolidayForUser, reserveHoliday, adjustHoliday, politica datelor, suprapuneri, limita negativă și mesajele sunt extrase/adaptate, nu refăcute ca un alt motor contabil. Formulele de restituire din cancelHoliday/processApprovalDecision și condițiile requestCancellation/cancelApprovedHoliday sunt păstrate în comenzile tranzacționale |
| Același HolidayBalanceService | `HolidayProfileService.php`: adăugate metodele originale correct/listCorrections; aceleași delta, motive, ledger și istoric. Lock User înaintea profilului, fingerprint cu operație/payload și verificarea soldului deschis în modal. Helper-ele de ledger/snapshot sunt reutilizate de ambele servicii |
| H `Tools/HolidayRequest/WorkingDayCalculator.php` | `Tools/EmployeeHolidayRequest/WorkingDayCalculator.php`: parserul, limita 367 zile, intervalul inclusiv și validarea zero zile sunt păstrate. Metoda snapshot citește WTC nativ explicit în locul regulii fixe L–V; păstrează zile/ore/luni rezultate. Metoda veche count/L–V rămâne în pending, nu în runtime: înlocuirea aprobată D6 elimină posibilitatea unei a doua surse de normă, fără pierderea regulilor de rezervare |
| H `Tools/HolidayRequest/NonWorkingDayProvider.php` | Query-ul original RO/ZileLibere este reutilizat; metadatele lipsă produc o eroare de configurare, nu un calendar presupus fără sărbători |
| Frontend H `src/views/modals/managed-booking.js` | Modal comun pentru creare proprie/managerială și editare Pending; păstrează modelele și field views native. Endpoint-uri noi, beneficiar fix pentru cererea proprie, revizie și cheie de retry stabilă. Managerul folosește date picker nativ fără limita today, întrucât regula originală permite zile trecute din luna curentă |
| Frontend H `src/views/modals/managed-employee-select.js`, `src/views/holiday-request/fields/managed-employee.js`, `src/views/holiday-request/fields/start-date.js` | Reutilizate în selectorul/formularul existent, fără un alt sistem de selectare a angajaților sau widget date propriu |
| Frontend H `src/views/modals/cancellation-reason.js` | Modalul original pentru motiv, fără modificare funcțională |
| Frontend H `src/views/modals/balance-correction.js`, `correction-history.js` | Aceleași modale și câmpuri/tabel; rute și traduceri proprii, cheie stabilă și expectedBalance în formular pentru a nu corecta după un sold vechi |
| Frontend H `src/views/dashlets/holiday-approvals.js` | Conținutul privat al rândurilor/cozii și UX de decizie sunt baza adaptorului mic `views/holiday-request/index.js`. Acesta reunește lista proprie/coada și apelează modalele originale. Nu copiază acțiunea veche de aprobare sincronă și nu înlocuiește Calendarul |

Cod de conectare nou: `Tools/HolidayBalance/Api/Holiday.php`, metadata de rute/scopes/snapshot/revizie și adaptorul workspace. Justificare: P1/P2/P3/P5 și D7 impun intersecția ACL, starea autoritativă și un singur owner al contabilizării. Nu se instalează hook-ul vechi `Hooks/HolidayRequest/Balance.php` în paralel cu comenzile. CRUD generic rămâne blocat; nu se rescrie Record Service. Nicio componentă validată nu este înlocuită cu un framework nou.

## Cod nou de conectare

`AdministrationAccessChecker` concentrează intersecția nativă actor/entry/section/read-all/field ACL, în locul verificărilor vechi cu fallback admin. Nu stochează roluri sau permisiuni. API action-ul și rutele proprii doar apelează serviciile reutilizate.

`views/administration/settings.js` este adaptorul dintre serviciile existente și field views native. Vechile `views/admin/settings.js` extindeau `views/settings/record/edit`, legat de modelul/configul global și ieșirea #Admin. PUT Settings ca manager este demonstrat 403. Adaptarea schimbă conectarea și gruparea UI, nu validatoarele sau logica setărilor; declarațiile originale și câmpul anual custom sunt păstrate. Nu se reconstruiește administrarea platformei.

## Funcții păstrate pentru etapele următoare

| Funcție | Sursă păstrată | Stare 0.7.0 |
| --- | --- | --- |
| Pontaj propriu/managerial și corecții în luni înghețate | AttendanceService/Overview și API/views integrate | Active: WTC, normă istorică, înghețare persistentă la 100%, corecții cu motiv/revizie și audit nativ |
| Remote, CIC/CCC, perioade, completitudine/remindere | Servicii, model și UI Attendance existente | Active; fără editor normativ; medical permis în CIC, CO prioritar, notificări native pentru lipsurile efective |
| Matrice și PDF/XLSX | Overview și generatoare originale | Active, cu intervale WTC pe celulă și descărcare regenerată; drepturi native distincte read/management/export |
| Cereri, rezervări, suprapuneri, decizii/anulări | HolidayBalanceService complet, hooks/API/views din pending și subsetul activ | Active. Pending→ApprovalProcessing→Approved rulează prin Job nativ, inclusiv autoaprobare. Verificarea pontajelor se repetă și în finalizare; soldul/ledger-ul nu se debitează din nou. Anulările sunt testate inclusiv după aprobări reale |
| Corecții sold și istoric | correct, listCorrections și modalele originale | Active, cu EmployeeHolidayBalanceCorrection plus administrarea profilurilor. Profile management singur nu acordă drept de corecție |
| Reset și acordări anuale | reset, processDueResets, applyResetGrant și helpers din pending | Integrate în HolidayProfileService; ScheduledJob nativ distinct, formula cumulativă originală, ledger unic/scadență și notificări originale filtrate ACL/FLS. Implicit fără acordare, până la configurare explicită; comanda manuală force/reset rămâne neexpusă |
| Complete Initial Setup | Metodele și modalul original | Activ, cu aceleași condiții restrictive și teste |
| DOCX și atașamente | Generator/template originale și HolidayApprovalDocumentService adaptat în runtime | Active: snapshot decizie, documente lunare, Attachment/FileStorage native, staging privat, finalizare verificată, retry și recuperare după worker întrerupt |
| Calendar/overview/dashlet Holiday | Serviciul Calendar original și sursele frontend/metadata din pending | Calendar nativ activ prin EmployeeAbsence minimală pentru Approved/CancellationPending și sursă privată Pending. Coada privată existentă păstrată; overview/dashlet complet rămân neintegrate, fără ștergerea surselor |
| Arhivă și flag acordări automate | Serviciile/UI complete din pending | Inițializarea Attendance activă în administrarea delegată. Acordările automate Holiday devin configurabile explicit în etapa 7, nu se activează la instalare/upgrade |
| Reset distructiv date de test | HolidayDataResetService și field view original adaptate | Activ în 0.7.7 prin administrarea delegată, cu preview, confirmare exactă, ACL cumulativ și curățarea artefactelor unificate (staging, proiecții Calendar, Jobs proprii) |

## Excluderi aprobate din pachet

Nu s-au șters funcții sau fișiere originale ori pending. Nu sunt transferate în pachet: override-urile #Admin (D3/P6), mandatory grants (P1/P2), injecțiile globale de meniu/taburi (P10), override-ul Calendar care înlocuiește ZileSarbatoare (P11), configurarea custom a normei (D6) și scripturile vechi de lifecycle cu mutații globale (D10). Aceste decizii nu justifică ștergerea funcțiilor legitime dependente: ele rămân în inventar pentru activare ulterioară.

Eliminări funcționale în etapa 3: **niciuna**. Nu se transferă în runtime generatorul sincron din tranziția Pending→Approved: justificarea demonstrată este P8/D7, iar sursa completă și generatorul/template DOCX rămân păstrate pentru integrarea Jobs. Nu se dublează hook-ul contabil cu serviciul de comenzi. Nu s-au șters originale, pending, setări sau exporturi.

Testul de contract verifică toate sursele și hash-urile, plus destinațiile adaptate. Testele API/browser certifică numai funcțiile efectiv executate, nu întregul cod pending.

## Attendance 1.11.0 — integrarea efectivă din etapa 4

STAGE-4-REUSE.json și integratedStage4 din inventarul JSON fixează sursele/hash-urile/destinațiile. Cele 175 de copii importate și toate cele 196 de surse originale rămân urmărite; excepțiile de la adaptarea mecanică sunt enumerate, nu acceptate global.

| Original (prefixele A / Frontend A de mai sus) | Reutilizare și adaptare |
| --- | --- |
| A `Tools/Attendance/AttendanceOverviewService.php` | Matrice, rânduri, contoare, missingUsers, Remote/CIC, perioade, completitudine și notificări reutilizate. Snapshot WTC înlocuiește zilele/orele fixe. Un singur aggregate pentru pagina proprie, matrice, freeze și export; lock User→Month→Record, revizie și motiv managerial |
| A `Tools/Attendance/AttendanceService.php` | Contractul și fluxul paginii proprii păstrate; calculul și scrierea delegate serviciului Overview existent pentru a nu dubla motorul WTC/CIC/înghețare. DTO-ul expune numai angajatul curent. Refactorizare justificată de sursa unică și P3/P4/P9, nu rescriere UI |
| A `Tools/Attendance/AttendanceAccessChecker.php` | Native scopes regular/activ; overview separat de management; native record/FLS și Export, fără fallback admin sau granturi automate |
| A `Tools/Attendance/ApprovedHolidayProvider.php` | Provider original read-only, acum batch și Approved/CancellationPending. Fără ledger, aprobări sau modificări de cereri Holiday în Attendance |
| A `Tools/Attendance/Api/PostAttendancePdf.php`, `PostAttendanceXlsx.php` | Același contract filename/mediaType/content base64 și validarea completitudinii; ACL nativ de export adăugat, aggregate recitit la fiecare descărcare |
| A `Tools/Attendance/AttendancePdfGenerator.php`, `AttendanceXlsxGenerator.php` | Rendering/layout/librării și Remote/CIC/CO/CM păstrate. Adaptarea este punctuală: per-cell schedule din WTC snapshot, cu fallback la DTO-ul original pentru regresie. Niciun generator rescris |
| Frontend A `src/views/attendance/my.js` | Pagina originală, butoane, selector și sumar; rute proprii, revizie, frozen și MedicalLeave permis în CIC |
| Frontend A `src/views/attendance/overview.js` | Matrice/sumar/remindere/download existente; native backend capabilities, motiv/revizie, celule nenormative, readonly vs management și progres pe întreaga lună |
| Frontend A `src/views/attendance/schedules.js` | Rândurile Remote/CIC și date picker nativ păstrate. Editorul de ore este înlocuit conform D6 de WTC tehnic; politicile/arhiva folosesc administrarea delegată existentă, nu un panou duplicat |
| Frontend A `src/controllers/attendance.js`, `attendance-overview.js`, `css/attendance.css` | View wiring/CSS reutilizate cu guards și rute native proprii, fără #Admin/Calendar global |
| A `Resources/metadata/entityDefs/AttendanceRecord.json`, `AttendanceWorkSchedule.json` | Ownership assignedUser și native ORM; auditate status/source/date/Remote/CIC/perioade și autori. Record adaugă motiv/revizie. Status și câmpurile operative sunt guvernate de FLS edit pentru comenzi; CRUD generic rămâne închis |
| A `Tools/Attendance/AttendanceSettingsService.php::initializeArchive` | Inițializator original reactivat în serviciul delegat, cu scope separat de settings. Vizibilitate de la luna curentă; fără ștergerea pontajelor/normei |

Cod nou strict necesar: AttendanceMonthService + entityDefs EmployeeAttendanceMonth pentru rezultatul normativ lunar read-only și starea frozen/revision; action/rute pentru serviciile existente. Calculatorul WTC din etapa 3 este reutilizat de ambele componente, extins punctual cu intervale native și lună fără zile. Modalul motivului adaptează modalul Holiday existent. Nu există un al doilea calculator sau configurator de program.

Norma se fixează la prima utilizare a lunii; schimbarea WTC/atribuirii/sărbătorilor nu o rescrie. Lunile neinițializate citesc configurația curentă. La 100% din toate zilele normative, fără viitoare/lipsuri, foaia se îngheață. Corecțiile manageriale păstrează frozen/norma, iar exportul recitește marcajele. Remote/CIC rămân modificări operative explicite și auditate, cu comportamentul original; nu devin o configurație paralelă a normei.

Eliminări funcționale în etapa 4: **niciuna**. Editorul custom L–V/orar și hooks care ar dubla scrierile nu sunt activați conform deciziilor aprobate; sursele originale/pending rămân integrale. Consistența tranzițiilor Holiday și conflictele cu pontajele sunt integrate în etapa 5, descrisă mai jos. Jobs/documente, Calendar și release rămân pentru etapele lor, fără aprobări provizorii.

## Attendance–Holiday — integrarea efectivă din etapa 5

Versiunea 0.5.0 continuă implementarea 0.4.0; nu importă din nou și nu rescrie serviciile originale. STAGE-5-REUSE.json / integratedStage5 fixează **trei adaptări punctuale** și hash-urile surselor pending/originale. Inventarul păstrează cele **196 fișiere originale** și **175 importuri inițiale**, plus toate mapările etapelor 2–4.

| Fișier original / bază deja integrată | Destinație | Adaptare și justificare |
| --- | --- | --- |
| H Tools/HolidayBalance/HolidayBalanceService.php, copia pending/Holiday; HolidayRequestService 0.4.0 | Tools/HolidayBalance/HolidayRequestService.php | Sunt păstrate prepare/reserve/adjust, regulile datelor, soldurile, ledger-ul, suprapunerile, restituirile, autorizarea, reviziile și receipts. Se adaugă verificarea pontajelor incompatibile la reserve/adjust/approve, inclusiv editarea numai a descrierii. Se folosesc normele lunare comune și locks pentru lunile afectate. Cheile payload-ului sunt normalizate și la intrarea în service pentru retry API–apel intern identic; nu se creează contabilizare paralelă |
| A Tools/Attendance/AttendanceOverviewService.php, copia pending/Attendance; implementarea 0.4.0 | Tools/Attendance/AttendanceOverviewService.php | Matricea, CIC/CCC, prioritățile, completitudinea, înghețarea și reminderele sunt păstrate. Citirea agregată folosește același snapshot DB pentru pontaje și absențe, protejat de lock-ul lunii; numai comenzile păstrează citirile locking ale pontajelor. Se păstrează tranzacția până la salvarea Notifications, pentru consistență cu anularea concurentă. Defect demonstrat remediat: destinatarul nativ este Notification.setUserId, nu assignedUserId; înainte se salvau notificări fără userId |
| H Tools/HolidayRequest/WorkingDayCalculator.php, copia pending/Holiday și adaptorul WTC deja activ | Tools/EmployeeHolidayRequest/WorkingDayCalculator.php | Parserul strict, intervalul inclusiv și limita 367 zile sunt aceleași. Se extrage metoda period pentru a reutiliza validarea la selecția normelor istorice; motorul nativ WTC/ZileSarbatoare nu este rescris sau duplicat |
| AttendanceMonthService introdus justificat în 0.4.0; fără echivalent original | Tools/Attendance/AttendanceMonthService.php | Extins cu lockPeriods în ordine cronologică, selecția intervalului din normele read-only și touch pentru revizia lunii la tranzițiile efective. Holiday și Attendance folosesc același rezultat normativ, inclusiv după schimbarea zilelor/orelor WTC. Snapshot-ul contabil existent nu se recalculează implicit; o editare explicită a datelor poate rezerva un nou rezultat, ca înainte |
| Statusurile zilnice originale A/entityDefs AttendanceRecord și regulile D8; conectare nouă necesară | Tools/Attendance/HolidayAttendanceGuard.php | Query ORM restrâns la beneficiar/interval/AtWork/BusinessTrip, sub locks User/lună. Refuză conflictul, nu modifică pontaje, solduri, cereri sau documente. Este reutilizabil la inițierea și finalizarea Job-ului din etapa 6; nu este endpoint sau workflow de aprobare |

### Cod reutilizat fără modificări suplimentare în această etapă

- ApprovedHolidayProvider în lot, din Attendance 1.11.0: Approved și CancellationPending rămân absențe efective; Cancelled/Rejected nu sunt proiectate. Aceeași regulă alimentează pagina proprie, matricea, completitudinea, rapoartele, reminderele și exporturile.
- HolidayProfileService/ledger/BalanceMath și metodele contabile extrase din HolidayBalanceService 1.9.4: un singur owner și aceleași formule. Nu se adaugă debit la aprobare sau restituire la simpla cerere de anulare.
- NonWorkingDayProvider și calculatorul WTC din etapele 3–4: nicio a doua configurație de program sau disponibilitate personală scăzută încă o dată.
- Interfețele originale my/overview/schedules, modalul managed-booking, modalele motivului/corecțiilor, lista/coada privată și field views native: sunt verificate în browser, fără rescriere în etapa 5.
- Generatoarele originale AttendancePdfGenerator, AttendanceXlsxGenerator și HolidayDocumentGenerator/template: nicio modificare în etapa 5, regresie de randare executată; exporturile recitesc agregatul curent.

### Reguli păstrate și limite de activare

Pending/ApprovalProcessing rezervă intervalul și soldul, dar **Pending nu devine absență efectivă** și nu se introduce o regulă nouă care să blocheze automat pontarea peste el. Dacă angajatul a lucrat după rezervare, editarea și aprobarea reverifică pontajul și refuză conflictul până la rezolvarea explicită, auditată. Nu sunt șterse sau mascate automat pontaje incompatibile pentru a aproba o cerere.

CO efectiv are prioritate față de CIC/CCC și marcajul manual; după anularea definitivă reapare CIC/CCC ori marcajul medical anterior. Medicalul rămâne permis în CIC, iar lucrul/deplasarea rămân interzise. Înghețarea și norma nu sunt resetate de anulare; o lipsă rezultată într-o lună înghețată necesită corecție managerială auditată, nu dezghețare implicită.

**Stare istorică 0.5.0:** aprobarea nouă era indisponibilă; fixture-urile de finalizare controlată din tests/integration/stage-5.php nu erau un Job livrat. Etapa 6 de mai jos implementează aprobarea nativă reală, inclusiv reverificarea guard-ului în finalizare; nu se livrează Approved fără document sau endpoint de test.

### Funcții păstrate la încheierea etapei 5 — stare istorică 0.5.0

| Componentă | Sursă și stare |
| --- | --- |
| Jobs, documente, ApprovalProcessing, retry/recovery și finalizare Approved | HolidayApprovalDocumentService, generatorul/template și sursele Jobs/API din pending sunt păstrate; orchestrarea Employee Management nu este încă activată |
| Resetări/acordări anuale și notificări aferente | Metodele și Jobs originale din pending rămân neactivate, inclusiv pragurile/setările; nu sunt eliminate |
| Calendar comun minimal, overview și dashlet Holiday | Backend/frontend/metadata originale păstrate; numai coada privată este activă. Nu se acordă acces public la cererea/documentele private și nu se suprascrie view-ul ZileSarbatoare |
| Lifecycle complet și release | Pachetul este instalabil; standalone fără originale, uninstall/reinstall, eșecuri upgrade și checklist-ul go-live rămân pentru etapa de release |

Eliminări funcționale în etapa 5: **niciuna**. Au fost eliminate numai citirea locking nepotrivită din agregatul read-only și atribuirea ineficientă assignedUserId pe Notification, înlocuită prin API-ul nativ demonstrat. Nicio sursă originală/pending, setare, interfață, export sau regulă aprobată nu a fost eliminată. Rapoartele și dovezile efective sunt în STAGE-5-RESULTS.md și tests/evidence/stage-5-*.json.

## Jobs native și documente — integrarea efectivă din etapa 6

Versiunea **0.6.0** continuă serviciile active din 0.5.0. STAGE-6-REUSE.json și integratedStage6 din inventarul JSON identifică **șase adaptări**, fișierele originale, copiile pending și hash-urile. Cele **196 surse originale** și **175 importuri inițiale** sunt păstrate; inventarul verifică și hash-urile destinațiilor curente ale etapelor precedente.

Prefixul H desemnează HolidayManagement/files/custom/Espo/Modules/HolidayManagement; A desemnează echivalentul AttendanceManagement. Destinațiile PHP de mai jos sunt în files/custom/Espo/Modules/EmployeeManagement.

| Sursa reutilizată | Destinație / modificări efective |
| --- | --- |
| H Tools/HolidayDocument/HolidayDocumentGenerator.php și Resources/templates/holiday-request.docx, deja importate | **Generatorul și template-ul sunt neschimbate** față de copia importată; template identic byte-for-byte cu originalul, generator cu adaptarea inițială de namespace. Nu s-au înlocuit librăriile, formatul DOCX sau câmpurile documentului |
| H Tools/HolidayDocument/HolidayApprovalDocumentService.php, copia pending/Holiday | Tools/HolidayDocument/HolidayApprovalDocumentService.php: păstrează segmentarea lunară inclusivă, numele românești/fișierelor, câmpurile, semnăturile și fallback-ul, formatarea și aritmetica originală. Valorile se capturează la decizie din profil și snapshot-ul rezervării. Recalcularea calendarului la generare este eliminată justificat prin P8; metoda count veche nici nu corespundea calculatorului WTC activ. Randarea folosește exclusiv valorile capturate; templateHash refuză schimbarea tacită a șablonului |
| H Tools/HolidayBalance/HolidayBalanceService.php, prin HolidayRequestService deja integrat | Tools/HolidayBalance/HolidayRequestService.php: approve trece în ApprovalProcessing și programează Job-ul în tranzacția existentă. retryApproval păstrează rezervarea/revizia/snapshot-ul; anulările, refunds, receipts și formulele contabile rămân aceleași. DTO/documentele și receipts repetate sunt filtrate prin ACL/FLS **actual**, nu prin drepturile existente când s-a salvat receipt-ul |
| Aceeași sursă, prin HolidayProfileService | Tools/HolidayBalance/HolidayProfileService.php: numai proiecții explicite pe citirile forUpdate; omit join-urile către autori fără a schimba solduri, validări sau ledger |
| A Tools/Attendance/AttendanceOverviewService.php, deja integrat | Tools/Attendance/AttendanceOverviewService.php: aceleași matrice, exporturi, CIC/CCC, norme, corecții și înghețare. Proiecțiile locking User/metadate/record nu mai iau implicit locks pe autori străini. Adaptarea răspunde unui deadlock MariaDB **observat în execuții paralele reale**, nu unei preferințe de stil |
| Holiday frontend src/views/dashlets/holiday-approvals.js + adaptorul privat/modalele native deja active | files/client/custom/modules/employee-management/src/views/holiday-request/index.js: reactivează decizia și retry cu confirmare nativă, eroare vizibilă, polling numai cât există Processing și cleanup la remove. Descărcarea folosește entryPoint=download; documentele parțiale nu sunt afișate. Nu reconstruiește dashletul/Calendarul global |
| H Resources/metadata/entityDefs/HolidayRequest.json | Resources/metadata/entityDefs/EmployeeHolidayRequest.json: câmpuri read-only pentru snapshot, manifest, corelare Job, stare/eroare/încercări și staging. Statusul/revizia sunt actualizate numai de serviciile backend; CRUD generic rămâne închis |

### Cod nou necesar, fără infrastructură paralelă

- **Tools/HolidayDocument/HolidayApprovalService.php:** coordonează JobSchedulerFactory/Data native, manifestul durabil al Attachment-urilor, fencing token și verificarea/finalizarea. Nu calculează/debitează solduri și nu inserează ledger; aceasta rămâne responsabilitatea serviciului Holiday reutilizat.
- **Jobs/GenerateHolidayApproval.php:** adaptor subțire Espo\Core\Job\Job. Folosește daemonul, grupurile și stările native. Nicio coadă proprie, ScheduledJob de recuperare sau permisiune globală Jobs pentru manager.
- **Resources/metadata/entityAcl/EmployeeHolidayRequest.json:** forbidden nativ pe staging și datele tehnice/JSON interne, inclusiv operationReceipts/calculationSnapshot. Împiedică ocolirea FLS prin JSON; nu introduce un override ACL Attachment sau un sistem propriu de permisiuni.
- AttendanceMonthService și HolidayAttendanceGuard din etapele 4/5 primesc numai proiecții locking restrânse, pentru același deadlock demonstrat. Guard-ul este apelat din nou în finalizarea reală a Job-ului.
- Allowlist API, traduceri și indicatorul de capabilitate/versionare sunt adaptate punctual. Se reutilizează rutele/ACL/modalele existente; nu apare o rută Admin sau un editor WTC.

### Comportament conservat și recuperare

Rezervarea este debitată la cerere, ca înainte. Inițierea, execuția, retry-ul și duplicatele Job-ului nu mai debitează și nu produc ledger. Approved și CancellationPending rămân singurele absențe efective; Processing păstrează rezervarea, dar nu fabrică CO în pontaj. Un conflict apărut între decizie și finalizare produce eșec recuperabil, nu suprascriere automată a pontajului.

Attachment-ul și ID-ul său din manifest sunt persistate **înaintea** scrierii FileStorage. Câmpul approvalStaging este interzis nativ la citire. Numai după verificarea tuturor fișierelor/hash-urilor, a beneficiarului, a decidentului și a guard-ului Attendance sunt publicate approvalDocuments și Approved în aceeași tranzacție DB. Retry-ul reia aceeași operație și aceleași ID-uri; fișierele valide sunt reutilizate.

După kill/restart, JobRunner detectează nativ procesul pierdut. Cererea rămâne ApprovalProcessing; API-ul de business prezintă Failed și retry pe baza Job-ului terminal, chiar dacă approvalState brut a rămas Processing. Managerul reia explicit; niciun Job Running nu este înlocuit după un timeout propriu. Snapshot-ul deciziei nu se recalculează din WTC, profil, numele utilizatorului sau semnăturile modificate ulterior. Dacă template-ul se schimbă în timpul operației, versiunea înregistrată trebuie restaurată înainte de retry.

### Păstrate fără noi modificări / rămase neactivate

PDF/XLSX, BalanceMath, politica datelor, rezervările, corecțiile, inițializarea profilurilor, WTC/ZileSarbatoare, proiecția Approved/CancellationPending și regulile CIC/CCC sunt reutilizate și regresate, nu rescrise. Adaptările istorice pentru WTC și consistență rămân cele documentate în etapele 3–5.

Resetările/acordările anuale și notificările lor, Holiday overview/dashlet complet, Calendarul minimal comun și resetul distructiv de date de test rămân integral păstrate în pending/originale. Primele două categorii necesită integrare înainte de release; Calendarul este etapa 7. Resetul distructiv nu este expus conform arhitecturii. Release-ul complet, standalone fără originale, uninstall și scenariile de upgrade eșuat rămân etapa 8.

**Eliminări funcționale în etapa 6: niciuna.** Apelul sincron de generare din vechea aprobare este înlocuit, nu șters ca funcționalitate: același generator/template lucrează acum prin Job cu snapshot și Attachment/FileStorage urmărite. Nu se instalează hook-ul contabil vechi în paralel cu serviciul de comenzi. Nu s-au șters originale, pending, setări sau exporturi. Dovezi și limite: STAGE-6-RESULTS.md și tests/evidence/stage-6-*.json.


## Holiday 1.9.4 — Calendar și acordări anuale, etapa 7 / 0.7.0

Proveniența exactă: **STAGE-7-REUSE.json** (10 adaptări) și **integratedStage7** din inventarul JSON. Toate cele 196 surse originale și cele 175 importuri inițiale rămân păstrate/verificate prin hash; pending nu este modificat. Secțiunile etapelor anterioare de mai sus rămân istoric al livrărilor respective, nu starea curentă.

| Sursa originală Holiday / copia pending | Destinație și modificare justificată |
| --- | --- |
| Services/HolidayRequest.php → pending Services/EmployeeHolidayRequest.php | Services/EmployeeHolidayRequest.php păstrează contractul nativ getCalenderQuery, SelectBuilder, coloanele și culoarea; restrânge sursa privată la Pending/autorizare/FLS. Services/EmployeeAbsence.php adaptează aceeași interogare la datele publice minimale, fără link la cererea/profilul privat și fără bypass skipAcl; numai culoarea originală este citită prin join ORM allowlist, cu FLS pe câmpul de prezentare |
| Tools/HolidayBalance/HolidayBalanceService.php | În HolidayProfileService sunt integrate reset, processDueResets, applyResetGrant, nextYear, formatDays și notificările de prag. Soldul rămâne balance + entitlement; avansul anual și limita originală 100 iterații sunt păstrate. Nu există expirare ori plafon nou |
| Jobs/ProcessHolidayResets.php + app/scheduledJobs.json | JobDataLess/run păstrat, denumire ProcessEmployeeHolidayResets pentru a evita coliziunea cu originalul instalat; aceeași expresie 5 0 * * * și Populator nativ. Flag-ul automat rămâne false în lipsa configurării și se reverifică sub lock |
| HolidaySettingsService și views/admin/settings.js | Aceleași câmpuri/validări/câmpuri native în adaptorul delegat existent. Este eliminată numai blocarea temporară din etapa 2 a opțiunii true, acum implementată efectiv. Roles native rămân unica sursă a drepturilor |
| Notificările originale și traducerile balanceWarning* | Aceleași valori/mesaje/prag; linkurile sunt adaptate la rutele private/delegate, nu #Admin. Destinatarii trebuie să fie regular activi și să aibă citire nativă/FLS pe profil/sold; configurarea approvers nu acordă singură acces |
| scripts/AfterInstall.php | Reutilizează ConfigWriter/Populator: numai adăugare idempotentă a celor două surse Calendar proprii, default annual=false numai dacă lipsește și backfill de proiecție. Nu modifică taburi, roluri, WTC sau originalele |
| scripts/BeforeUninstall.php | Cleanup adaptat în AfterUninstall pentru a respecta skipAfterScript la upgrade în 10.0.1. Curăță numai înregistrările proprii și dezactivează ScheduledJob propriu, fără ștergerea datelor/fișierelor/setărilor. Dezinstalarea completă rămâne de certificat în etapa 8 |
| Serviciile integrate de profil/cereri din 0.6.0 | Proiecții locking fără assignedUserName nefolosit; pentru mutațiile de profil, identificarea owner-ului precede tranzacția, iar prima citire contabilă urmează lock-ului User. Necesitate demonstrată prin eroarea MariaDB 1020 în proba cu două Jobs native, nu preferință de stil. Formulele/rezervările/documentele nu sunt rescrise |

**Cod nou strict necesar:** Event/Controller/metadata/layouts EmployeeAbsence, proiecția sincronă, hook-ul ORM afterSave și filtrele/checker-ul native pentru citirea publică. Proiecția conține numai nume/date/identitate angajat și corelare internă forbidden; nu are link la HolidayRequest, motive, sold sau documente. Culoarea este exclusiv prezentare nativă, virtuală/notStorable, nu o a doua configurație; query-ul păstrează live setarea originală calendarColor printr-un join ORM allowlist și respectă FLS pe color. Generic CRUD/link writes și export sunt închise. Nu există al doilea motor Calendar, WTC, contabilitate sau runner Jobs.

Proiecția se publică în tranzacția Approved și se păstrează la CancellationPending. Anularea finală o șterge logic în aceeași tranzacție cu restituirea/ledger-ul. Backfill-ul reutilizează inclusiv rândul șters logic prin QueryBuilder.withDeleted și repository.restoreDeleted, fără duplicarea cheii sursei. Schimbarea tehnică temporară a eligibilității ascunde rândul prin Select/record ACL, fără a șterge istoricul unei absențe active. Nu este creată proiecție nouă pentru un beneficiar ineligibil.

Generatorul DOCX, template-ul, PDF/XLSX, BookingDatePolicy, BalanceMath, norma WTC/ZileSarbatoare, CIC/CCC și mecanismul înghețării nu sunt rescrise. **Nicio funcționalitate originală nu este eliminată.** Nu sunt transferate vechile granturi mandatory, override-urile Calendar/Admin sau resetul distructiv.

**Încă păstrate/neintegrate:** overview/dashlet Holiday complet, endpoint-ul/UI manual force-reset și resetul distructiv (acesta din urmă intenționat neexpus). În etapa finală rămân validarea release/standalone fără originale, lifecycle complet/upgrade eșuat/dezinstalare/reinstalare, performanță și matricea finală de compatibilitate. Raportul STAGE-7-RESULTS diferențiază probele executate de limitele rămase.

Adaptare Calendar demonstrată prin regresie: query-ul original trunchia from/to UTC și omitea cereri de o singură zi din modul Day. Cele două surse folosesc acum datele locale ale viewer-ului, citite prin Preferences/config native, și limita superioară exclusivă. Probele executate acoperă Europe/Bucharest, America/New_York, o limită parțială de zi și randarea reală Day; nu se schimbă datele cererilor, WTC sau contabilitatea.
