# Employee Management 0.5.0 — Etapa 5: integrarea Attendance–Holiday

Data: **20 octombrie 2026**. Bază: Employee Management **0.4.0**. Implementare incrementală efectivă; **nu production-ready**. Arhitectura aprobată, extensiile originale și instalația companiei nu au fost modificate.

## 1. Livrare și verdict

- [employee-management-0.5.0.zip](dist/employee-management-0.5.0.zip): **156.979 bytes**, SHA-256 **`b7fd301ac44c98e2a4fc2f88a7f9dd4a71d40acbec480d5f993ab1291c0d66d5`**.
- Un singur modul EmployeeManagement, **132 fișiere runtime / 134 membri ZIP**: 32 PHP, 21 JavaScript și 76 JSON. Testele, fixture-urile, pending, rapoartele și uneltele nu sunt instalate.
- Compatibilitate testată/declarată exclusiv **EspoCRM 10.0.1 / PHP 8.4.23 / Zile Sărbătoare 0.9.5**. Laborator MariaDB 12.3.2; platforma nu a fost actualizată.
- Instalare/înlocuire prin Extension Manager nativ, rebuild și clear cache executate pe ZIP-ul indicat. Fiecare membru ZIP este identic byte-for-byte cu sursa livrată.

**Funcțiile active Attendance și Holiday sunt acum coordonate tranzacțional:** rezervarea/modificarea/aprobarea verifică pontajele incompatibile, cele două componente folosesc rezultate normative lunare comune, iar anulările, proiecția absențelor și reminderele nu combină stări contabile diferite. Nu se introduce un al doilea ledger sau un workflow paralel.

**Aprobarea nouă rămâne blocată pentru etapa 6.** Endpoint-ul approve verifică acum și conflictul real, dar o cerere compatibilă este în continuare refuzată cu 409 până la Jobs/documente. Nu produce ApprovalProcessing provizoriu, Approved fără document, debit suplimentar, Job fictiv sau endpoint de finalizare accesibil public.

## 2. Ce s-a implementat efectiv

### 2.1. Conflictul concediu–pontaj

HolidayRequestService continuă metodele reserveHoliday/adjustHoliday și regulile din HolidayBalanceService 1.9.4. Același guard ORM verifică **AtWork și BusinessTrip** pentru beneficiarul și intervalul cererii:

1. La creare, înainte de debitarea rezervării și inserarea ledger-ului.
2. La modificare Pending, inclusiv când se schimbă numai descrierea sau numărul zilelor rămâne identic.
3. La încercarea de aprobare, după recitirea stării/reviziei și autorizarea decidentului, sub lock.

Conflictul întoarce 409 cu data incompatibilă în headerul nativ X-Status-Reason. Nu returnează înregistrarea Attendance ori informațiile private ale unui coleg neautorizat. Nu șterge și nu schimbă automat pontajul ca să permită rezervarea/aprobarea. Validările sunt backend, nu condiții de afișare a butoanelor.

Un Pending **nu este absență efectivă**. Se păstrează comportamentul existent: un angajat poate ponta după ce a rezervat un Pending; operația de aprobare trebuie să reverifice și să refuze pontajul incompatibil apărut între timp. Nu s-a introdus o interdicție nouă de lucru peste orice rezervare Pending. Processing și finalizarea sa reală vor fi tratate end-to-end în etapa Jobs, cu aceeași reverificare în tranzacția finală.

### 2.2. Același rezultat normativ, fără recalculare retroactivă

AttendanceMonthService din 0.4.0 este extins punctual:

- lockPeriods dobândește lunile afectate în ordine cronologică;
- snapshot selectează zilele/orele/intervalele din **normele lunare read-only existente**, cu exact validarea intervalului din WorkingDayCalculator original;
- la prima utilizare, inclusiv o rezervare Holiday, norma lunii este capturată prin calculatorul nativ WTC/ZileSarbatoare deja implementat;
- rezervarea păstrează versiunea 2 a snapshot-ului derivat, cu normSources pe luni; nu există câmpuri/API de configurare a unui al doilea program;
- touch schimbă revizia observabilă a lunilor afectate de tranziții ale absențelor efective, fără să rescrie norma sau să dezghețe foaia.

Astfel, o modificare ulterioară a orelor **sau zilelor săptămânale** WTC nu face ca Holiday să debiteze o normă nouă în timp ce Attendance afișează norma istorică a aceleiași luni. WTC rămâne sursa configurației normative; snapshot-ul este rezultatul istoric, nu un configurator alternativ. Nu se folosește UserCalendar/createForUser și nu se scade indisponibilitatea personală încă o dată.

Snapshot-urile contabile rezervate anterior nu sunt rescrise la upgrade sau la editarea numai a descrierii. Modificarea explicită a datelor Pending rezervă un nou rezultat și reconciliază delta prin serviciul contabil existent. Nu se reconstruiește un WTC istoric care nu a fost capturat; cazul unor date vechi contradictorii între un snapshot de rezervare anterior integrării și o normă lunară capturată ulterior necesită reconciliere explicită, nu debit/restituire ori migrare automată ascunsă. Acest caz de import contradictoriu **nu este certificat** de probele acestei etape; datele de aici sunt fixture-uri de test, nu o migrare de producție.

### 2.3. Anulări, CIC/CCC și înghețare

Providerul Attendance existent rămâne singura proiecție efectivă în lot: **Approved și CancellationPending** au aceeași prioritate până la anularea definitivă. Pending/ApprovalProcessing nu sunt proiectate ca CO, iar Cancelled/Rejected nu rezervă din nou perioada prin istoric.

| Tranziție / situație | Rezultat păstrat și verificat |
| --- | --- |
| Approved → CancellationPending | CO rămâne în pontaj; rezervarea/soldul/ledger-ul nu sunt restituite |
| CancellationPending → Approved, refuzul anulării | Păstrează CO și soldul; cere documentul existent persistat, nu aprobă o cerere Pending |
| CancellationPending → Cancelled sau anulare managerială Approved | Restituire o singură dată, dispariția CO din agregat, revizie și istoric păstrate |
| Cancelled / Rejected | Pot fi urmate de o nouă rezervare pe același interval; nu sunt șterse fizic |
| CO efectiv peste CIC/CCC ori MedicalLeave | CO are prioritate; nu se acceptă suprascriere manuală peste el |
| După anularea definitivă a CO în CIC/CCC | Reapare CIC/CCC; MedicalLeave rămâne permis, AtWork/BusinessTrip rămân refuzate inclusiv după un marcaj medical |
| Anulare în lună înghețată | frozen și norma rămân neschimbate; reapare marcajul anterior/CIC sau o lipsă reală, nu o zi lucrată fabricată |
| Corecție managerială după anulare | Motiv/revizie/autori/audit nativ; angajatul nu primește drept de corecție asupra lunii înghețate |

Regula înghețării la 100% din luna întreagă nu este modificată. O corecție operativă explicită a perioadei CIC poate schimba proiecția unei luni istorice, ca în implementarea originală, dar nu rescrie norma și nu dezgheață foaia. Dacă apar lipsuri, se aplică politica existentă a exporturilor incomplete și corecția managerială autorizată; nu se raportează artificial 100%.

### 2.4. Tranzacții, citiri și idempotency

Sunt utilizate TransactionManager și repository forUpdate native, fără tabel de mutex, event bus sau coadă proprie:

| Operație | Protocol |
| --- | --- |
| Scriere Attendance | User beneficiar → Month → înregistrarea zilnică; reverificare Holiday/CIC/revizie înainte de salvare |
| Comandă Holiday existentă | User beneficiar → Profile → Request, apoi lunile afectate în ordine cronologică → verificarea pontajelor incompatibile |
| Creare Holiday | User/Profile, captură/locks pe lunile normative, verificare suprapuneri și pontaje, rezervare + Request + ledger în aceeași tranzacție |
| Matrice/pagină proprie/export | Lock pe Month; citiri consistente non-locking ale pontajelor și absențelor în aceeași tranzacție/snapshot DB |
| Remindere | Se păstrează lock-ul lunii și tranzacția până la salvarea Notifications pentru lista lipsurilor calculată |

Agregatul read-only nu mai execută forUpdate pe toate pontajele. Aceasta evită amestecarea citirilor locking curente cu proiecția Holiday dintr-un snapshot anterior și păstrează ordinea fără a lua locks inverse pe Request/User. Scrierile individuale și guard-ul de conflict păstrează citirile autoritative locking. Nu se țin locks pe durata randării PDF/XLSX; documentul reprezintă agregatul coerent citit, iar următoarea descărcare recitește datele curente.

Contabilizarea rămâne numai în serviciul Holiday reutilizat. Cheile unice DB, reviziile, receipts și rollback-ul sunt cele existente; nu se instalează și hook-ul contabil original în paralel. Normalizarea ordinii cheilor comenzii este repetată la intrarea în service, astfel încât un apel intern viitor și retry-ul său prin API să aibă același fingerprint. Testul independent de anulare → retry HTTP a verificat o singură restituire și un singur ledger.

### 2.5. Defect real de notificare remediat

O probă de livrare efectivă a arătat că versiunea reutilizată salva Notifications fără destinatar: **Notification nativ are userId, nu assignedUserId**. Numărul sent al metodei nu dovedea livrarea către utilizator.

Adaptarea punctuală folosește API-ul real EspoCRM 10.0.1 **Notification::setUserId**. Textul original, lista de lipsuri și linkul spre foaia lunară rămân. Proba verifică acum atât userId persistat, cât și lectura notificării de către destinatar prin **API-ul nativ Notification**. Nu se rescrie mecanismul de notificări.

## 3. Reutilizare și dimensiunea modificării

[MIGRATION-INVENTORY.md](MIGRATION-INVENTORY.md), MIGRATION-INVENTORY.json și [STAGE-5-REUSE.json](STAGE-5-REUSE.json) identifică sursele și modificările. Toate **196 fișiere originale runtime/lifecycle** și **175 importuri inițiale** sunt urmărite/verificate prin hash. Pending este păstrat integral.

| Sursă originală | Ce s-a reutilizat / adaptat acum |
| --- | --- |
| HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/HolidayBalanceService.php; copia pending/Holiday | HolidayRequestService 0.4.0 continuă aceeași rezervare, ajustare, sold, restituire, ledger, date și condiții de stare. Adaptări: guard de pontaj, normă lunară comună, locks/revizii și normalizare receipt; nu o rescriere a contabilizării |
| AttendanceManagement/files/custom/Espo/Modules/AttendanceManagement/Tools/Attendance/AttendanceOverviewService.php; copia pending/Attendance | Aceeași matrice, priorități, completitudine, înghețare și remindere. Adaptări: snapshot consistent al citirilor, tranzacție de notificare și destinatarul nativ corect |
| HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayRequest/WorkingDayCalculator.php; copia pending/Holiday | Parserul și regula de interval existente sunt reutilizate prin period; motorul WTC deja integrat nu este duplicat |
| AttendanceMonthService 0.4.0, componentă nouă justificată în etapa 4 | Extins, nu înlocuit: locks ordonate, selecția intervalului din rezultate persistate și revizii. Nu este atribuit fictiv unui fișier original care nu îl conținea |
| ApprovedHolidayProvider, HolidayProfileService/BalanceMath și generatoarele originale | Reutilizate fără modificări suplimentare, testate ca regresii și prin operațiile integrate |
| Interfețele Attendance/Holiday și modalele originale | **Toate cele 21 fișiere JavaScript runtime sunt identice cu 0.4.0**; scenariile noi sunt executate prin aceleași formulare, confirmări, field views și download-uri |

Compararea ZIP 0.4.0 cu 0.5.0 arată **un singur fișier runtime adăugat**, HolidayAttendanceGuard.php; **cinci fișiere runtime existente modificate**, dintre care GetStatus schimbă numai versiunea. Mai sunt schimbate manifestul și README. **Niciun membru ZIP eliminat.** Generatoarele PDF, XLSX și DOCX și template-ul nu au fost modificate în această etapă.

Guard-ul nou este strict o interogare ORM de integrare D8, fără mutații, endpoint, contabilitate sau workflow propriu. Nu există o justificare sau o încercare de rescriere a Attendance/Holiday de la zero. Eliminări funcționale: **niciuna**.

## 4. Teste executate și dovezi

Toate scrierile au folosit laboratorul existent **employee-feasibility-20261020**, Compose `/tmp/employee-feasibility-20261020-yDmmnL/compose.yaml`, URL `http://127.0.0.1:18089`. Chromium este efemer pe rețeaua izolată existentă. Originalele Attendance 1.11.0/Holiday 1.9.4/ZileSarbatoare 0.9.5 rămân instalate în laborator; worker-ul nu a fost pornit pentru etapa 5.

PASS înseamnă execuție efectivă, nu existența unei clase. Numerele sunt aserțiuni, nu scenarii independente exhaustive.

| Suită | Rezultat final | Dovadă |
| --- | --- | --- |
| Integrare Attendance–Holiday, API/ORM/concurență controlată | **103 PASS / 0 FAIL** | [stage-5-api.json](tests/evidence/stage-5-api.json) |
| Concurență HTTP editare–pontare și anulare–pontare | **12 PASS / 0 FAIL** | [stage-5-extra.json](tests/evidence/stage-5-extra.json) |
| Browser, cinci roluri: angajat, manager, CIC, read-only, admin | **28 PASS / 0 FAIL** | [stage-5-browser.json](tests/evidence/stage-5-browser.json) |
| Regresie Attendance 0.4.0 | **74 PASS / 0 FAIL** | [stage-5-regression-attendance.json](tests/evidence/stage-5-regression-attendance.json) |
| Regresie Attendance suplimentară | **20 PASS / 0 FAIL** | [stage-5-regression-attendance-extra.json](tests/evidence/stage-5-regression-attendance-extra.json) |
| Browser Attendance original | **26 PASS / 0 FAIL** | [stage-5-regression-attendance-browser.json](tests/evidence/stage-5-regression-attendance-browser.json) |
| Regresie Holiday/contabilizare/rollback | **125 PASS / 0 FAIL** | [stage-5-regression-holiday.json](tests/evidence/stage-5-regression-holiday.json) |
| Regresie administrare delegată/profiluri | **120 PASS / 0 FAIL** | [stage-5-regression-administration.json](tests/evidence/stage-5-regression-administration.json) |
| Regresie ACL/confidențialitate/Attachment | **126 PASS / 0 FAIL** | [stage-5-regression-acl.json](tests/evidence/stage-5-regression-acl.json) |
| Renderere originale PDF/XLSX/DOCX și helpers | **13 PASS** | [stage-5-renderers.json](tests/evidence/stage-5-renderers.json) |
| Câmp anual MM-DD original | **2 PASS** | [stage-5-static.json](tests/evidence/stage-5-static.json), Node TAP |
| Reinstalare ZIP/rebuild/clear cache/retenție | **PASS** | [stage-5-lifecycle.json](tests/evidence/stage-5-lifecycle.json) |
| PHP/JSON, JavaScript, contract/proveniență, identitate surse–ZIP | **PASS** | [stage-5-static.json](tests/evidence/stage-5-static.json) |
| Runtime/versiuni/app-check și inspecție read-only companie | **PASS în domeniul consemnat** | [stage-5-environment.json](tests/evidence/stage-5-environment.json) |

Total: **649 aserțiuni funcționale/regresie**, fără eșecuri în rulările finale, plus lifecycle/contract/lint. Cele 371 regresii API Holiday/administrare/ACL sunt păstrate din etapele precedente, nu reimplementate. Metadatele fixtureVersion din dovezi indică versiunea istorică a scriptului reutilizat; testedPackageVersion și SHA indică pachetul **0.5.0** efectiv executat.

### Probe concrete de concurență și consistență

- **Creare peste AtWork/BusinessTrip:** 409 cu motivul real în header; soldul și numărul ledger nu se schimbă.
- **Editare după pontarea unui Pending:** inclusiv descriere-only, 409 și rollback al cererii/snapshot/reviziei/contabilizării. Corecția managerială explicită permite o editare compatibilă, dar nu elimină granița Jobs.
- **Creare–pontare și editare–pontare, HTTP paralel:** rezultate serializabile. Un Pending poate preceda pontarea fără a deveni Approved; încercarea ulterioară de aprobare vede pontajul și refuză conflictul.
- **Două conexiuni DB independente, bariere deterministe:** dacă finalizarea controlată documentată dobândește prima lock-ul User, marcajul real Attendance este refuzat cu 409; dacă marcajul real este primul, același guard refuză finalizarea cu 409. Niciun rezultat Approved plus AtWork și nicio debitare/înregistrare ledger suplimentară.
- **Anulare–pontare, HTTP paralel:** anularea finalizează o singură restituire; pontarea este 409 înainte de anularea efectivă sau 200 după ea. Retry-ul anulării nu inserează alt ledger.
- **Anulare–reminder:** proces separat ține tranzacția anulării înainte de commit, iar cererea HTTP de remindere folosește lock-ul lunii. Rezultatul include ziua redevenită lipsă după anulare; notificarea este atribuită și citită efectiv de beneficiar prin API nativ.
- **Approved → CancellationPending → Approved/Cancelled:** matricea păstrează CO până la Cancelled, fără refund anticipat; după decizia finală perioada poate fi rezervată din nou. Sunt verificate documentul existent și retry-urile.
- **CIC/CCC:** CO prioritar; după anulare reapare CIC, medicalul poate fi pontat și apoi nu deblochează lucrul/deplasarea.
- **Lună înghețată:** anulare, restabilirea marcajului anterior, corecție managerială cu motiv, refuz angajat, normă neschimbată, PDF valid și XLSX deschis/inspectat după regenerare. Browserul a corectat efectiv matricea și a descărcat exportul nou.
- **WTC 4/6/8 ore:** regresia cohortei de 15 persoane confirmă pentru septembrie 21 zile normative și 84/126/168 ore; WTC modificat/reatribuit nu rescrie istoria. Noile rezervări și matricea citesc aceeași normă capturată, inclusiv când se schimbă ulterior zilele săptămânale WTC. Testele Holiday confirmă că indisponibilitatea personală nu produce scădere dublă.
- **Securitate:** admin exclus ca actor/beneficiar, lipsă scope/FLS refuzată, colegul nu citește cererea/documentul privat, generic CRUD nu poate forța Approved. Acordarea permisiunilor tehnice native nu este modificată.

### Ce nu demonstrează fixture-ul de aprobare

Scriptul de test poate crea **numai în laborator**, după verificarea instanței și opt-in, înregistrări istorice documentate și o finalizare controlată folosind guard-ul runtime și locks reale. Unele fixture-uri mută un interval de o zi în trecut pentru verificarea anulării din luna înghețată; nu reprezintă o operație expusă utilizatorului.

Aceste probe **nu sunt un Job livrat**, nu testează scheduler/worker/retry/recovery și nu activează API-ul Pending→Approved. Fixture-urile și orice cod de setare controlată a stării sunt în afara ZIP-ului. Etapa 6 trebuie să repete validările în finalizatorul real, după generarea documentelor și înainte de Approved, în tranzacția care recitește starea/revizia.

### Lifecycle și păstrarea mediului

Reinstalarea exactului ZIP 0.5.0, rebuild și clear cache au păstrat hash-urile normelor/frozen, pontajelor/motivelor/reviziilor/autorilor, metadatelor Remote/CIC, cererilor/snapshot-urilor/receipts, profilurilor, ledger-ului, Roles și Notifications. Setările, WTC și datele originalelor au fost păstrate. app-check: migration, database, maintenance și cron OK; aceasta nu dovedește execuția Jobs Employee Management.

ID-urile și StartedAt containerelor companiei coincid cu baza cunoscută a etapelor precedente; au fost doar inspectate read-only. Nicio actualizare/dezinstalare a originalelor, nicio ștergere de volum și niciun commit/push. Fixture-ul cohortei a dezactivat temporar numai utilizatorii sintetici anteriori din laborator, apoi i-a reactivat; stările normative de test anterioare au fost salvate înainte de reseed.

În dezvoltare au existat rulări nereușite: invalidarea cache-ului Roles în setup, comparația strictă a obiectelor snapshot din test, citirea erorilor din body în loc de header, un fixture CIC incomplet și overlay-ul nativ de eroare peste butonul modalului. Au fost corectate și rerulate. Cele două probleme runtime demonstrate — destinatarul Notification și fingerprint API–apel intern — au fost corectate punctual. Numerele PASS de mai sus provin exclusiv din rulările finale pe ZIP-ul indicat.

## 5. Situația auditului P1–P11

Închiderea se raportează pentru suprafața efectiv livrată, nu pentru codul pending sau integrarea Jobs/Calendar viitoare.

| Problemă | Situație verificată după etapa 5 |
| --- | --- |
| **P1 — ACL/CRUD** | Protecțiile etapelor 1–4 sunt reconfirmate pe comenzile/entitățile active: native Roles/FLS, regular/activ, generic CRUD/link/mass/export refuzat, admin exclus. Nu se adaugă granturi. Etapa 5 nu pretinde certificarea unor endpoints încă nepublicate |
| **P2 — expunere privată** | Regresia de citire by-ID/Attachment și refuzurile între colegi trec; fără mandatory grants sau date private noi în Attendance. Calendarul comun minimal și testul său end-to-end rămân pentru etapa 7; P2 nu este declarat închis pentru acel consumator nelivrat |
| **P3 — stări stale/concurență** | **Închis incremental pe comenzile publicate:** locks pe beneficiar, recitire, revizii, rollback, receipts/ledger unic și curse reale cross-component. Garanția aprobare–pontare este demonstrată pentru guard în fixture controlat. **P3 rămâne parțial deschis** până la finalizatorul Job real și cursele sale cu reject/cancel/recovery |
| **P4 — CancellationPending divergent** | **Închis pentru integrarea Attendance–Holiday activă**, prin tranziții API și aceeași proiecție în pagina proprie, matrice, completitudine, remindere și exporturi. Calendarul viitor trebuie să preia aceeași semantică; nu este marcat testat acum |
| **P5 — Cancelled blochează perioada** | **Închis prin teste:** Cancelled și Rejected nu blochează; CancellationPending încă blochează; re-rezervarea și concurența debit/refund sunt verificate. Istoricul este păstrat |
| **P6 — override Admin** | Eliminarea din noul pachet este reconfirmată prin contract și retenția rutei globale la reinstall. Nu se dezinstalează/restaurează override-urile originalelor păstrate în laborator |
| **P7 — trasabilitate Attendance** | **Închis pentru funcțiile Attendance active:** autori/revizie/motiv/audit nativ și normă istorică, inclusiv corecție după anulare în lună înghețată. Atribuirea deciziei în Job-ul viitor nu este încă testată |
| **P8 — snapshot/documente/storage** | Snapshot-urile sunt stabile și norma comună este testată; **nu este închis**: Jobs, eșec storage, documente duplicate/orfane și recovery urmează etapa 6 |
| **P9 — N+1** | Proiecția Holiday în Attendance este în lot și reutilizată; **nu se declară închis integral**. Nu s-a executat query counting complet pentru profiluri/eligibilitatea setup-ului și toate rapoartele; acestea nu se deduc din simpla existență a unui batch provider |
| **P10 — lifecycle/navigare** | Reinstall/rebuild/cache/retention trec; **nu este închis complet**: upgrade eșuat, uninstall/reinstall și navigare grupată/customizată rămân pentru release |
| **P11 — conflict Calendar ZileSarbatoare** | Noul pachet nu înregistrează calendarView, deci nu introduce override-ul vechi; **nu se declară integrarea finală închisă** înainte de Calendarul minimal și probele sale în etapa 7 |

Etapa 5 închide efectiv defectele de integrare **P4/P5** în funcțiile active și extinde verificarea P3/P7. Nu declară toate P1–P11 rezolvate prin comasare sau prin existența unor fixtures.

## 6. Reproducere

Build și verificări fără scrieri DB:

```bash
bash EmployeeManagement/build.sh
python3 EmployeeManagement/tests/contract.py
node EmployeeManagement/tests/regression/reset-date-field.test.mjs
```

În laboratorul opt-in, cu parolele sintetice furnizate prin mediu și verificarea siteUrl/version din scripturi:

1. tests/integration/stage-4.php, stage-4-extra.php și browser stage-4.cjs pregătesc cohorta și verifică regresia Attendance.
2. tests/integration/stage-5.php, apoi același script cu argumentul **extra**; procesele child sunt pornite numai de test, nu din extensie.
3. tests/browser/stage-5.cjs verifică interfețele și folosește state.json-ul produs de suita API.
4. stage-4.php **restore**, apoi stage-3.php, stage-3-regression-administration.php, stage-1.php și renderers.php ca regresii. Holiday și administrarea nu se rulează simultan: modifică aceeași configurare sintetică a aprobatorilor.
5. stage-5-lifecycle.php **capture** → reinstalare prin `php bin/command extension --file=/feasibility/employee-management-0.5.0.zip` → rebuild.php → clear_cache.php → script **verify**; app-check.

Fixture-urile sunt datate **2026-10-20**; suita nouă refuză altă dată/instanță. Reseed-ul se face prin scriptul de test existent, niciodată printr-un endpoint al extensiei. Browserul poate închide notificarea nativă closable de eroare înainte de a închide modalul, fără force-click prin overlay. JSON-urile brute și capturile employee-stage5-employee.png/manager.png rămân în directorul privat al laboratorului; dovezile livrate sunt în tests/evidence.

## 7. Limite și etapele 6–8

- **Etapa 6 — NOT IMPLEMENTED:** Job nativ de aprobare, snapshot decizie/documente, ApprovalProcessing recuperabil, worker/restart, eșec storage, retry/recovery și finalizare Approved după toate documentele. Reverificarea guard-ului sub locks este obligatorie și la finalizare. Rezervarea existentă nu se debitează din nou. Resetările/acordările anuale și notificările lor rămân păstrate/neactivate până la integrarea Jobs corespunzătoare.
- **Etapa 7 — NOT IMPLEMENTED:** Calendar comun minimal EmployeeAbsence, overview/dashlet Holiday și confidențialitatea lor end-to-end, fără informații private sau înlocuirea view-ului ZileSarbatoare.
- **Etapa 8 — NOT TESTED complet:** fresh install standalone după eliminarea originalelor, upgrade eșuat/uninstall/reinstall, toate scenariile de navigare personalizată, integrarea documentelor/exporturilor complet finalizată, backup/restore și checklist release.
- **RISK / acoperire limitată:** barierele deterministe și cursele HTTP folosesc două conexiuni/operații; nu sunt benchmark sau demonstrație exhaustivă a tuturor intercalărilor. DST, programe peste miezul nopții/intervale fragmentate și timezone-uri multiple rămân în matricea finală de testare. Lunile neinițializate nu au un WTC istoric reconstituibil automat.
- **Compatibilitate:** alte versiuni EspoCRM, PHP sau ZileSarbatoare nu sunt declarate testate. Worker-ul neactivat și app-check OK nu sunt dovadă de funcționare a aprobării prin Jobs.

**Verdict:** Employee Management **0.5.0 este instalabil și testat pentru etapa 5**, cu logică existentă reutilizată, verificări backend și consistență demonstrată pentru operațiile enumerate. Se poate continua cu Jobs/documente, fără flux provizoriu de aprobare. Produsul **nu este declarat pregătit pentru producție**.

La final, aplicația și DB laboratorului sunt oprite; worker-ul a rămas oprit. Containerele, volumele și fixture-urile sunt păstrate pentru continuare. Instalația companiei nu a fost oprită sau modificată.
