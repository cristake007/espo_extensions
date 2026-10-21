# Employee Management 0.6.0 — Etapa 6: Jobs native și documente

Data: **20 octombrie 2026**, Europe/Bucharest. Bază: **0.5.0**. Implementare efectivă, incrementală; **nu production-ready**. Arhitectura aprobată, extensiile originale și instalația companiei nu au fost modificate.

## 1. Livrare și verdict

- [employee-management-0.6.0.zip](dist/employee-management-0.6.0.zip): **166.337 bytes**; SHA-256 **`0cdcb453fe7e96473313abbdd5722dae69dd6811cde0dd823c8cef3035d6f909`**.
- Un singur modul EmployeeManagement; **136 fișiere runtime / 138 membri ZIP**: 35 PHP, 21 JavaScript, 77 JSON, un template DOCX, un template frontend și CSS. Testele, fault-urile de laborator, pending și documentația de lucru nu intră în ZIP.
- Compatibilitate efectiv verificată/declarată: **EspoCRM 10.0.1 / PHP 8.4.23 / Zile Sărbătoare 0.9.5**. DB de laborator: **MariaDB 12.3.2**. Nu se declară versiuni netestate și nu s-a actualizat platforma.
- Instalare nativă a versiunii 0.6.0 peste baza existentă și reinstalare a ZIP-ului final, rebuild, clear cache și app-check: executate în laborator.

**Fluxul Pending → ApprovalProcessing → Approved este operațional prin Job nativ real.** Managerul autorizat poate aproba inclusiv propriile cereri. Approved apare numai după verificarea fișierelor persistate, publicarea lor privată și reverificarea Attendance/ACL. Eșecul și întreruperea worker-ului păstrează rezervarea; retry-ul controlat reia aceeași operație, fără încă un debit, ledger sau document pentru aceeași lună.

## 2. Implementarea efectivă

### Inițiere și contabilizare

HolidayRequestService continuă implementarea reutilizată din HolidayBalanceService 1.9.4. Comanda approve aplică verificările existente de actor regular/activ, scope EmployeeHolidayApproval, aprobator configurat, ACL/FLS al cererii, revizie, suprapuneri și pontaje AtWork/BusinessTrip. Beneficiarul trebuie să aibă acces nativ de citire la cererea proprie și approvalDocuments.

În tranzacția existentă User → Profile → Request → luni, se capturează decizia, se trece în ApprovalProcessing și se programează Job-ul. Programarea este atomică cu salvarea cererii; un rollback nu lasă un Job activ fără tranziția aferentă. API-ul nu acceptă status, Job ID sau token furnizate de client.

Soldul a fost deja rezervat la creare, ca înainte. **Aprobarea, execuția Job-ului și retry-ul nu debitează soldul și nu inserează ledger.** HolidayRequestService rămâne unicul owner al contabilizării. Nu este instalat și hook-ul contabil original în paralel. Anularea finală reutilizează restituirea/receipt-ul existent, o singură dată; documentele rămân istoric privat.

### Infrastructura nativă utilizată

| Mecanism 10.0.1 utilizat efectiv | Utilizare |
| --- | --- |
| Espo\Core\Job\JobSchedulerFactory, scheduler și Job\Data | setClassName, setGroup, setData, withTargetId/withTargetType și schedule; grup nativ employee-holiday:requestId |
| Espo\Core\Job\Job / Jobs/GenerateHolidayApproval | Entry point subțire run(Data); execuție de daemon/JobRunner nativ, nu apel HTTP sincron sau runner propriu |
| Job nativ | Pending/Ready/Running/Success/Failed persistate; corelare cu cererea. schedule este void în 10.0.1: inserarea se recitește după clasă/target/grup sub lock și token-ul se verifică |
| EntityManager, repositories, forUpdate, select și TransactionManager.run | Tranziții, manifest, revizii și finalizare; proiecții explicite pentru a evita locks ORM pe autori străini |
| Espo\Entities\Attachment și Espo\Core\FileStorage\Manager | Parent/câmp native, putContents/exists/getContents și verificarea SHA-256 înainte de publicare |
| Acl / AclManager.createUserAcl și entityAcl nativ | Read pe cerere și documente, FLS actual, staging forbidden, verificarea beneficiarului și decidentului |
| Entry point nativ download și modale/view lifecycle native | Descărcare autorizată, confirmare, polling al stării și cleanup la ieșirea din view |

Nu există tabel propriu de Jobs, daemon, wrapper de coadă sau panou global Jobs pentru manager. Job.attempts=0 dezactivează retry-ul tehnic implicit pentru aceste operații: reluarea este explicită, autorizată și vizibilă. Istoricul Job-urilor native rămâne tehnic; UI-ul extensiei expune numai starea operației și mesajul sigur.

### Snapshot și documente lunare

HolidayApprovalDocumentService este adaptarea serviciului original, nu un generator nou. Păstrează segmentarea inclusivă pe luni, numele românești și fișierele, câmpurile DOCX, fallback-ul semnăturilor, formatarea și aritmetica soldului din original. Zilele fiecărei luni provin din **calculationSnapshot rezervat**, nu dintr-un nou calcul WTC la execuție.

La decizie sunt fixate numele angajatului, aprobatorul, data, semnăturile/titlurile, valorile contabile și toate valorile de randare. Snapshot-ul păstrează hash-ul calculului și al template-ului. Retry-ul nu recitește aceste valori din configurația sau profilul curent. În probele reale, schimbarea numelui, semnăturii configurate și WTC după decizie nu a modificat valorile originale din DOCX.

**HolidayDocumentGenerator și template-ul original sunt păstrate fără modificări suplimentare.** Template-ul este identic byte-for-byte cu Holiday 1.9.4. Un hash diferit oprește procesarea; nu se regenerează tacit cu altă versiune. Dacă template-ul se schimbă în timpul unei operații, administratorul tehnic trebuie să restaureze versiunea înregistrată înainte de retry. Nu se construiește un registru suplimentar de template-uri în această etapă.

### Persistare, publicare și idempotency

1. Worker-ul revendică operația curentă sub lock și incrementează încercarea tehnică. Snapshot-ul nu se schimbă.
2. Randarea DOCX se face **în afara locks contabile**.
3. Se persistă Attachment-ul și ID-ul/hash-ul său în manifest **înainte de scrierea FileStorage**. Câmpul său este approvalStaging, interzis nativ la citire.
4. Scrierea fișierului și verificarea hash-ului se serializează cu fencing-ul operației într-o tranzacție scurtă pe beneficiar. Un retry reutilizează ID-ul și fișierul valid; un fișier absent/corupt este regenerat pe același ID urmărit.
5. Finalizarea recitește cererea/revizia/snapshot-ul, locks pe luni, guard-ul Attendance, eligibilitatea/accesul beneficiarului și autorizarea curentă a decidentului. Verifică existența, hash-ul și parent-ul **tuturor** documentelor.
6. Numai atunci schimbă câmpurile Attachment în approvalDocuments și cererea în Approved/Completed, în aceeași tranzacție DB. Revizia lunii este actualizată fără rescrierea normei ori dezghețare.

DB și FileStorage **nu sunt declarate atomic tranzacționale împreună**. Manifestul durabil și publicarea întârziată permit reconcilierea/reluarea, nu o promisiune de exactly-once pentru orice incident hardware. Job-urile duplicate ori vechi verifică operationKey/token/status și devin no-op. Retry-ul nu schimbă snapshot-ul, approvalRevision sau rezervarea; emite un nou token și un nou Job nativ.

### Eșec și recuperare după întrerupere

Excepțiile păstrează ApprovalProcessing, marchează approvalState=Failed și un mesaj sigur, apoi sunt retransmise către JobRunner pentru Failed nativ. Nu se înregistrează Success fals și nu se expune stack trace în pagina angajatului.

După kill/restart, codul PHP nu poate salva starea de eșec în momentul întreruperii. Se folosește **detecția nativă a procesului dispărut**: Job-ul devine Failed. API-ul de business derivă Failed/mesaj/retry din acel Job chiar dacă approvalState brut a rămas Processing. Managerul reia explicit aceeași operație. Nu există un ScheduledJob paralel de recuperare și nu se înlocuiește un Job Pending/Ready/Running pe baza unui timeout propriu.

Intervalul nativ implicit de detecție este **300 secunde**, nu un SLA garantat. Pentru proba de kill/restart a fost redus **numai în laborator la 5 secunde**, apoi restaurat și verificat la 300. Daemonul are interval 10 secunde, maximum 5 procese și jobRunInParallel=true. Datele native ale operațiilor păstrate arată **1–10 secunde de la executeTime la startedAt** în eșantionul raportat; aceasta nu este o promisiune de latență în producție.

### Confidențialitate și integrare Attendance

- Documentele parțiale sunt refuzate inclusiv pentru beneficiar/manager prin ACL-ul nativ Attachment bazat pe parent/câmp. Nu există override Attachment ACL în pachet.
- entityAcl interzice staging, snapshot/manifest/token/Job ID și JSON-urile contabile interne. Astfel un GET generic nu poate ocoli FLS prin operationReceipts sau calculationSnapshot.
- Un receipt repetat păstrează efectul contabil, **nu drepturile istorice**: documentele/stările sunt filtrate prin ACL/FLS actual. Revocarea read pe approvalDocuments blochează atât receipt-ul, cât și descărcarea directă.
- Administratorul tehnic rămâne exclus ca actor/beneficiar în business; worker-ul system este doar executor tehnic. decidedBy/decidedAt provin din managerul decident, modifiedBy poate fi system; ambele urme sunt păstrate.
- ApprovalProcessing rezervă zile/sold, dar nu este CO efectiv. Dacă apare ulterior AtWork/BusinessTrip, finalizarea reală eșuează până la corecția managerială explicită. Nu se maschează pontaje pentru a aproba.
- Approved/CancellationPending au aceeași prioritate ca în etapa 5; Cancelled/Rejected nu blochează. CIC/CCC, medicalul permis, înghețarea și corecțiile auditate sunt păstrate prin regresii.
- Nicio rută #Admin, calendarView global, grant mandatory sau permisiune globală Jobs nu este introdusă.

## 3. Reutilizare și modificări

Mapările exacte, sursele originale/pending și hash-urile sunt în [STAGE-6-REUSE.json](STAGE-6-REUSE.json), [MIGRATION-INVENTORY.md](MIGRATION-INVENTORY.md) și MIGRATION-INVENTORY.json. Sunt urmărite în continuare **196 fișiere originale și 175 importuri inițiale**.

| Componentă originală / bază | Modificare justificată |
| --- | --- |
| Holiday 1.9.4 Tools/HolidayDocument/HolidayDocumentGenerator.php + Resources/templates/holiday-request.docx | Niciuna suplimentară; renderer/template originale folosite efectiv de worker |
| Holiday 1.9.4 Tools/HolidayDocument/HolidayApprovalDocumentService.php, copia pending/Holiday | Serviciul lunar adaptat la snapshot și randare separată de salvarea sincronă. Recalcularea calendarului și vechea dependență count sunt înlocuite punctual prin rezultatul deja rezervat, conform P8 |
| Holiday 1.9.4 Tools/HolidayBalance/HolidayBalanceService.php → HolidayRequestService existent | Inițiere/retry native și DTO/receipts protejate; formulele, rezervarea, ledger-ul, reviziile și anulările sunt păstrate |
| Aceeași sursă → HolidayProfileService; Attendance 1.11.0 Tools/Attendance/AttendanceOverviewService.php | Numai proiecții locking necesare, explicate mai jos; fără schimbare de reguli/contabilizare |
| Holiday frontend views/dashlets/holiday-approvals.js, adaptorul privat și modalele deja integrate | Reactivare aprobare/retry, stare/eroare/polling și link nativ la documente; nu rescriere a interfeței |
| HolidayRequest entityDefs original | Extindere read-only pentru procesare/staging; entityAcl nativ pentru confidențialitate |
| BalanceMath, BookingDatePolicy, WTC/ZileSarbatoare, PDF/XLSX, reguli CIC și proiecția absențelor existente | Reutilizate, fără rescriere în etapa 6; regresii efective |

Cod nou strict necesar: HolidayApprovalService, adaptorul Jobs/GenerateHolidayApproval și metadata entityAcl. AttendanceMonthService/HolidayAttendanceGuard deja existente primesc proiecții locking restrânse. API allowlist, traduceri și capabilitățile frontend sunt adaptate punctual.

**Defect de concurență observat și remediat:** prima probă cu două aprobări reale pentru beneficiari diferiți a produs deadlock MariaDB. Citirile ORM forUpdate implicite includeau join-uri spre createdBy/modifiedBy/decidedBy și luau locks pe Users străini. Au fost restrânse prin select nativ la atributele necesare, păstrând ordinea User/Profile/Request/Month. După adaptare, aprobările paralele/self și regresiile de concurență au trecut. Nu s-a modificat core-ul; nu se afirmă eliminarea tuturor deadlock-urilor posibile din orice combinație de extensii.

Diferența ZIP față de 0.5.0: **4 fișiere runtime adăugate, 11 existente modificate, niciun membru eliminat**; separat manifest și README actualizate. Un singur fișier JavaScript runtime este modificat. Generatoarele PDF/XLSX/DOCX și template-ul sunt neschimbate față de 0.5.0.

**Eliminări funcționale: niciuna.** Apelul sincron vechi este înlocuit cu același generator prin Job, nu cu un generator nou. Originalele/pending/setările/exporturile sunt păstrate. Nu se activează hook-ul contabil paralel sau fluxul Approved fără document.

## 4. Teste reale și dovezi

Laboratorul existent: proiect **employee-feasibility-20261020**, Compose `/tmp/employee-feasibility-20261020-yDmmnL/compose.yaml`, URL `http://127.0.0.1:18089`. Chromium efemer pe rețeaua izolată existentă; fără infrastructură nouă permanentă. Originalele rămân instalate în laborator. Testele sunt în afara pachetului și cer opt-in/versiunea/URL-ul de laborator.

PASS înseamnă operație executată; numerele sunt **aserțiuni**, nu scenarii independente exhaustive.

| Suită | Rezultat final | Dovadă |
| --- | --- | --- |
| API/Jobs/DOCX, eșec, recovery, concurență, ACL și verificări suplimentare | **121 PASS / 0 FAIL** | [stage-6-api.json](tests/evidence/stage-6-api.json) |
| Browser: manager/self, angajat, coleg, fără Approval, admin | **25 PASS / 0 FAIL**, fără erori JavaScript | [stage-6-browser.json](tests/evidence/stage-6-browser.json) |
| Regresie integrare Attendance–Holiday / concurență suplimentară | **103 + 12 PASS / 0 FAIL** | [integrare](tests/evidence/stage-6-regression-integration.json), [concurență](tests/evidence/stage-6-regression-integration-extra.json) |
| Regresie Attendance, suplimentară și browser | **74 + 20 + 26 PASS / 0 FAIL** | [Attendance](tests/evidence/stage-6-regression-attendance.json), [suplimentar](tests/evidence/stage-6-regression-attendance-extra.json), [browser](tests/evidence/stage-6-regression-attendance-browser.json) |
| Regresie Holiday, administrare și ACL | **125 + 120 + 126 PASS / 0 FAIL** | [Holiday](tests/evidence/stage-6-regression-holiday.json), [administrare](tests/evidence/stage-6-regression-administration.json), [ACL](tests/evidence/stage-6-regression-acl.json) |
| Renderere PDF/XLSX/DOCX și helpers; câmp anual MM-DD | **13 + 2 PASS** | [renderere](tests/evidence/stage-6-renderers.json), [static](tests/evidence/stage-6-static.json) |
| Reinstalare ZIP/rebuild/clear cache/retenție | **PASS** | [stage-6-lifecycle.json](tests/evidence/stage-6-lifecycle.json) |
| Lint PHP/JSON/JS, contract/proveniență/identitate surse–ZIP | **PASS** | [stage-6-static.json](tests/evidence/stage-6-static.json) |
| Runtime/Job timestamps/app-check/fault cleanup/izolare | **PASS în domeniul raportat** | [stage-6-environment.json](tests/evidence/stage-6-environment.json), [observații native](tests/evidence/stage-6-observations.json) |

**Total: 767 aserțiuni funcționale/regresie trecute**, plus lifecycle/contract/lint. Rulările finale nu au eșecuri. Eșecul de deadlock descoperit în dezvoltare și interferența între suitele care schimbă populația sintetică au fost investigate; după remediere, suitele dependente de populație au fost rulate secvențial. Nu sunt numărate ca PASS încercările nereușite.

Trasabilitatea artifactului: fazele inițiale setup–post/restart au rulat pe candidatul 0.6.0 înaintea ultimei întăriri a filtrării receipts/JSON intern. Orchestratorul Job și generatorul nu au fost schimbate prin acea întărire. **Pe ZIP-ul final cu SHA de mai sus** au fost executate din nou security, aprobare/descărcare reală, eșec/retry de renderer, fluxul complet din browser, toate regresiile tabelului și lifecycle-ul. Dovezile marchează această diferență; nu pretind o nouă execuție a întregii secvențe kill/restart pe fiecare rebuild. FixtureVersion istoric în regresii identifică scriptul reutilizat, nu versiunea pachetului instalat.

### Probe concrete

- **Două approve HTTP simultane, aceeași cheie:** 200/200, un receipt duplicat, un singur Job și nicio a doua debitare. Aprobări pentru beneficiari diferiți și self rulează prin worker real.
- **Procesare reală:** ApprovalProcessing este observat înaintea pornirii worker-ului; după execuție există documentele pentru toate lunile, Job Success și descărcare DOCX validă prin entry point nativ.
- **Snapshot:** DOCX deschis ca ZIP/XML conține numele/semnătura originale, nu valorile schimbate după decizie. WTC schimbat nu recalculează zilele documentelor.
- **Eșec după primul document:** trigger SQL condiționat exclusiv de o cerere sintetică refuză inserarea celui de-al doilea Attachment. Job Failed, stare intermediară, rezervare neschimbată, primul fișier persistat dar inaccesibil. După eliminarea trigger-ului, retry-ul păstrează primul ID, salvează documentul lipsă și finalizează fără debit/ledger suplimentar. Aceasta este probă de eroare la limita DB/Attachment, **nu simulare de disk full**.
- **Eșec DOCX autentic:** numai template-ul EmployeeManagement instalat în laborator a fost mutat reversibil. Worker-ul real eșuează fără Approved/Attachment; după restaurarea aceluiași template, retry-ul finalizează. Sursa originală Holiday și template-ul din repository nu sunt modificate.
- **Întrerupere reală:** trigger temporar ține al doilea insert; după primul document persistat și Job Running, containerul worker izolat primește **SIGKILL**. După restart, detecția nativă marchează Job-ul Failed; reluarea controlată reutilizează fișierul inițial și finalizează. Un Job încă Running refuză retry-ul cu 409. Nu s-a schimbat manual statusul Job-ului pentru a trece testul.
- **Duplicate native:** două Jobs suplimentare programate efectiv pentru aceeași operație finalizată nu produc documente noi. Retry în timp ce noul Job este live primește 409.
- **Conflict în Processing:** un BusinessTrip real introdus după inițiere este văzut de finalizator; Approved este refuzat. Corecția managerială explicită/auditată și retry-ul finalizează aceeași rezervare.
- **ACL:** coleg/noapproval nu aprobă și nu descarcă documentele; angajatul nu poate relua aprobarea; admin nu intră în business; managerul nu vede administrarea globală Jobs; staging este refuzat inclusiv proprietarului. Revocarea FLS blochează și download-ul, și receipt-ul repetat. Update neautorizat Attachment este 403.
- **Anulare reală după Job:** Approved → CancellationPending → Cancelled, o singură restituire/ledger, retry idempotent; documentele rămân istoric privat. Suma ledger-ului este comparată cu soldul pentru toți cei șase utilizatori ai cohortei Jobs.
- **Regresii:** WTC 4/6/8 ore și istorie, completitudine/înghețare, corecții manageriale, CIC/CCC/medical/CO, remindere, PDF/XLSX actualizate, rezervări, anulări, solduri, rollback, Roles/FLS și administrare delegată.

Cele trei probe vechi care așteptau „Jobs indisponibile” sunt înlocuite **numai în copiile de regresie etapa 6** cu verificări stale-revision la aprobare; acestea păstrează fixture-ul Pending pentru restul suitei. Scripturile istorice nu sunt modificate. Aprobarea reală nu este declarată testată de acele 409, ci de noua suită nativă și browser.

### Lifecycle și reproducere

Reinstalarea finală/rebuild/cache verifică hash-uri pentru cereri, snapshot/manifest/receipts, profiluri, ledger, norme/înghețare, pontaje, notificări, Roles, setări, toate fișierele private și corelările/stările Jobs. Originalele, WTC, tabList, calendarEntityList și rutele globale rămân neschimbate față de baseline-ul sintetic.

`tests/integration/stage-6.php` are faze explicite: setup/conflict-setup cu worker oprit; normal/failure/retried cu worker pornit; crash-ready → kill worker → restart → pornire worker → recover/recovered; browser-ready → tests/browser/stage-6.cjs → post. Fazele security/smoke-complete, renderer-fail/renderer-retry/renderer-complete și browser-repeat/browser-repeat-complete verifică întăririle finale. Renderer-fail cere worker oprit până la mutarea reversibilă a template-ului. Scriptul stage-6-lifecycle.php capture/verify verifică retenția, stage-6-observations.php verifică runtime-ul și cleanup-ul. Aceste fixture-uri nu se rulează pe companie sau în paralel cu suitele care modifică populația/rolurile/configurația.

La final, fault trigger=0, template restaurat/hash original, fără backup temporar rămas și jobPeriodForNotExistingProcess=300. **Numai app/db/worker ale laboratorului sunt oprite; volumele sunt păstrate.** Containerele companiei au aceleași ID-uri și StartedAt ca înainte. Nu s-au executat teste business în companie, commit sau push.

## 5. Situația auditului P1–P11

| Problemă | Stare demonstrată după etapa 6 |
| --- | --- |
| **P1 — ACL/CRUD** | Protecțiile funcțiilor active reconfirmate; approve/retry au guards backend, generic CRUD nu poate forța starea. Niciun grant global; manager fără Jobs/Settings/Role/WTC globale |
| **P2 — date private** | Documente/staging/descărcare/receipt/FLS și JSON intern protejate și testate. **Închis pentru fluxul privat livrat**; Calendarul comun nelivrat nu este certificat |
| **P3 — concurență/stale** | Extins la inițiere paralelă, Job real, Processing conflict, duplicate și recuperare; deadlock-ul observat remediat punctual. **Închis incremental în scenariile executate**, nu garanție pentru orice interleaving/platformă/storage |
| **P4 — CancellationPending** | Regresii plus anulare după aprobare reală; aceeași prioritate până la Cancelled. Calendarul viitor trebuie să preia aceeași semantică |
| **P5 — Cancelled blochează** | Regresie trecută: Cancelled/Rejected nu blochează; istoric păstrat |
| **P6 — #Admin** | Noul pachet nu definește ruta și reinstall o păstrează; override-urile originalelor încă instalate în lab nu sunt atribuite Employee Management |
| **P7 — trasabilitate** | Audit/revizie/motiv Attendance reconfirmate; decident managerial și momentul deciziei păstrate în Job, distinct de autorul tehnic system |
| **P8 — documente/snapshot/storage** | **Remedierea fluxului de aprobare implementată și verificată**: snapshot, fișiere private urmărite înainte de scriere, finalizare după persistare, eșec/retry/kill/recovery fără duplicate. Validarea exhaustive a tuturor incidentelor storage rămâne deschisă; vezi limitele de mai jos |
| **P9 — N+1** | Nu se declară închis integral; providerul Attendance rămâne batch, dar nu există încă query counting/benchmark complet. Lista privată poate face citiri Job/Attachment per cerere |
| **P10 — lifecycle/navigare** | Reinstalare/rebuild/cache/retenție trecute, inclusiv documente/Jobs. Standalone, uninstall, upgrade eșuat și navigare grupată/customizată rămân pentru release |
| **P11 — Calendar/ZileSarbatoare** | Niciun override Calendar nou. Integrarea minimală finală și probele ei rămân etapa 7 |

Nu sunt declarate toate P1–P11 închise prin existența unui ZIP sau a unor clase native.

## 6. Limitări și următoarele etape

- **NOT TESTED:** epuizare disk/quota, storage extern, corupție sau kill exact în interiorul putContents/commit/publicării, pierdere DB ori volume, toate interleaving-urile cu alte extensii. Mecanismul le tratează defensiv prin manifest/hash/fencing, dar nu sunt marcate PASS. Nu există tranzacție distribuită DB–storage.
- **RISK:** template schimbat în timpul procesării necesită restaurarea versiunii capturate; nu există regenerare automată din configurația nouă. Revocarea drepturilor decidentului/beneficiarului blochează finalizarea până la rezolvarea autorizării, nu o ocolește.
- Recuperarea este **detectare nativă + retry managerial explicit**, nu retry automat nelimitat. Starea brută a cererii poate rămâne Processing după crash; pagina/API-ul de business derivă eșecul din Job. Sistemul trebuie să aibă daemon/cron și storage funcționale, administrate tehnic.
- **Etapa 7:** Calendar comun minimal, fără date private/override ZileSarbatoare; Holiday overview/dashlet complet. Sursele lor rămân în pending.
- **Înainte de release/etapa 8:** integrarea și regresia resetărilor/acordărilor anuale/notificărilor originale, momentan păstrate și neactivate; standalone fără originale/probe de fezabilitate, lifecycle complet/upgrade eșuat/uninstall, retenție storage/Jobs, performanță, cazuri calendaristice limită și checklist final. Etapa 6 a activat Jobs de aprobare/documente, nu toate Jobs originale.
- Nu există curățare distructivă automată a istoricului/documentelor la uninstall. Testele de ștergere/dezinstalare nu au fost executate în această etapă.

**Verdict:** etapa 6 este implementată și verificabilă pentru fluxul de aprobare/DOCX/retry privat descris. Se poate continua integrarea incrementală; **0.6.0 nu este declarată production-ready**.
