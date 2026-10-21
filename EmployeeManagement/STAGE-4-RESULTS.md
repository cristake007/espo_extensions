# Employee Management 0.4.0 — Etapa 4: Attendance Management

Data: 20 octombrie 2026. Bază: Employee Management 0.3.0. **Implementare incrementală funcțională, nu production-ready.** Nu au fost modificate extensiile originale, documentul arhitectural aprobat sau instalația companiei.

## 1. Livrare și mediu verificat

- [employee-management-0.4.0.zip](dist/employee-management-0.4.0.zip), **154.326 bytes**.
- SHA-256: `216c9a352470a8191722ccf5450ca5976e093b814b370344c1799e9bcc65e22e`.
- Un singur modul EmployeeManagement: **131 fișiere runtime**, 133 membri ZIP; 31 PHP, 21 JS și 76 JSON. Pending, fixture-urile, uneltele de integrare și rapoartele nu intră în pachet.
- Compatibilitate declarată/testată exclusiv: **EspoCRM 10.0.1 / PHP 8.4.23 / Zile Sărbătoare 0.9.5**. MariaDB de laborator: 12.3.2. Nu s-a actualizat platforma.
- Laboratorul existent: proiect `employee-feasibility-20261020`, Compose `/tmp/employee-feasibility-20261020-yDmmnL/compose.yaml`. Browser Chromium efemer pe rețeaua izolată existentă, fără infrastructură permanentă suplimentară.
- Instalare prin Extension Manager nativ: `php bin/command extension --file=/feasibility/employee-management-0.4.0.zip`. Au fost instalate trecerea de dezvoltare 0.3.0→0.4.0 și înlocuirile 0.4.0; ultima reinstalare a verificat exact ZIP-ul cu hash-ul de mai sus. Fiecare membru al ZIP-ului este identic cu sursa livrată.

Attendance Management 1.11.0, Holiday Management 1.9.4 și ZileSarbatoare rămân instalate în laborator. Nu s-au dezinstalat originale, șters volume ori transferat date ale companiei. Worker-ul Jobs al laboratorului nu a fost activat pentru această etapă.

## 2. Funcționalități operaționale

### Pontare, matrice și administrare

- `#EmployeeAttendance`: pagina originală de pontare, situația proprie și navigarea lunară. Ruta nativă de arhivă este `#EmployeeAttendance/month/YYYY-MM`, inclusiv la reîncărcarea paginii.
- `#EmployeeAttendanceOverview`: matricea lunară comună originală, completitudine, lipsuri, remindere, PDF și XLSX. Lectura matricei, managementul și exportul sunt drepturi distincte.
- `#EmployeeAdministration/attendanceMetadata`: Remote, CIC/CCC și perioadele inclusive existente; validările originale ale perioadelor sunt păstrate. Nu există editor de ore, creare/atribuire WTC sau acces la administrarea platformei.
- Politicile existente privind lunile anterioare editabile și exporturile incomplete rămân active. Inițializatorul original de arhivă este reactivat în setările delegate: schimbă prima lună vizibilă la luna curentă, **fără ștergerea datelor**.
- Populația este formată din utilizatori regular activi; administratorii tehnici nu sunt actori sau beneficiari Attendance și nu intră în matrice, completitudine ori remindere.

Pagina proprie, matricea, completitudinea, înghețarea și exporturile folosesc **aceeași agregare server** din AttendanceOverviewService. AttendanceService proiectează numai datele utilizatorului curent din această agregare; nu implementează un al doilea motor WTC/CIC. Datele colegilor nu sunt returnate de endpoint-ul personal.

### Norma WTC și istoricul

Calculatorul WTC comun din etapa 3 este reutilizat, nu duplicat. El folosește API-urile native `Espo\Tools\WorkingTime\CalendarFactory::create(WorkingTimeCalendar)`, `CalendarUtilityFactory::create`, `CalendarUtility::isWorkingDay`, `getSummedWorkingHours` și `Extractor::extract`, cu `Espo\Core\Field\DateTime` și timezone-ul calendarului.

Se citește calendarul explicit atribuit utilizatorului de administratorul tehnic. Nu se folosește `createForUser` pentru normă: indisponibilitatea personală nu se scade în calculul programului. Providerul RO/ZileLibere existent este reutilizat pentru sărbătorile legale. Lipsa WTC este semnalată explicit prin `normConfigured`, nu mascată printr-un program L–V/global/Team; pontarea fără normă este refuzată.

`EmployeeAttendanceMonth` păstrează rezultatul derivat: calendarId, timezone, zile, ore și intervale zilnice, plus starea lunii/revizia. La **prima utilizare a lunii** se capturează întreaga normă pentru utilizatorii eligibili configurați. Inițializarea poate avea loc și la lectura autorizată a situației. Schimbarea ulterioară a orelor WTC, a atribuirii sau a sărbătorilor nu recalculează acest rezultat. Nu există API de editare a normei și nu se configurează un al doilea program.

Într-o lună neînghețată poate fi capturat un utilizator eligibil care încă nu are rezultat; o lună înghețată nu își extinde retroactiv populația normativă. Listele operaționale filtrează în continuare utilizatorii regular activi. Lunile încă neinițializate folosesc configurația WTC curentă: nu se pretinde reconstrucția unui program istoric necapturat anterior.

### Înghețare, corecții și exporturi

- Înghețarea este persistentă, la **100% din luna întreagă**: fără marcaje lipsă, WTC neconfigurat sau zile normative viitoare. Completarea tuturor zilelor scurse din luna curentă nu produce o înghețare prematură.
- Angajatul nu mai poate modifica luna înghețată. Managerul autorizat poate corecta cu motiv obligatoriu și verificarea reviziei; corecția nu dezgheață luna și nu schimbă norma.
- Sunt păstrate autorii, sursa Self/Manager, data marcării, motivul și revizia, plus auditul nativ EspoCRM pentru câmpurile urmărite. Testul a observat două Note de audit pentru modificările de status verificate.
- Remote/CIC sunt metadate operative, nu normă. Corecțiile lor manageriale explicite rămân curente și auditate, inclusiv când afectează proiecția unei perioade istorice, conform comportamentului original. Câmpul intern policies păstrează referința de la înghețare, nu un configurator alternativ care anulează aceste corecții.
- Generatoarele PDF/XLSX originale recitesc agregatul curent la fiecare descărcare. Nu se servește un fișier vechi salvat înaintea corecției. XLSX a fost deschis și verificat pentru marcajul actualizat și indicatorii Remote/CIC/CO/CM; PDF a fost verificat ca document valid.

### CIC/CCC, absențe și remindere

Perioadele CIC/CCC originale sunt păstrate. CO efectiv are prioritate; atât Approved, cât și CancellationPending rămân absențe efective. CIC blochează AtWork și BusinessTrip, dar permite MedicalLeave, inclusiv prin UI și corecție managerială. Un marcaj MedicalLeave nu dezactivează restricția CIC pentru o modificare ulterioară în lucrat/deplasare.

Datele viitoare sunt refuzate atât pentru angajat, cât și pentru manager. Pontarea lucrat/deplasare peste CO sau CIC este refuzată în backend. Reminderele originale utilizează Notifications native și lista lipsurilor efective, fără zile viitoare sau administratori; nu sunt necesare Jobs noi pentru această acțiune explicită.

Attendance doar **citește în lot** absențele Holiday. Nu rezervă/restituie solduri, nu aprobă cereri, nu modifică ledger-ul și nu creează marcaje zilnice CO în paralel cu Holiday.

## 3. ACL și operații backend

Toate scopes noi rămân implicit false; instalarea nu atribuie Roles și nu introduce mandatory grants. Administratorul tehnic configurează drepturile native, dar operațiile de business îi sunt refuzate separat prin eligibilitatea regular/activă.

| Drept nativ | Acces necesar |
| --- | --- |
| EmployeeManagement + EmployeeAttendance | Situație/pontare proprie; Record și WorkSchedule read own și câmpurile necesare |
| EmployeeAttendanceOverview | Matrice comună; Record și WorkSchedule read all și câmpuri |
| EmployeeAttendanceManagement, împreună cu cele pentru matrice | Corecții, remindere și Remote/CIC; edit FLS pentru câmpurile modificate |
| Permisiunea nativă Export | Suplimentar pentru PDF/XLSX; se respectă și exportDisabled global |
| EmployeeAdministration + EmployeeAttendanceSettings | Politici și inițializarea arhivei, fără Settings global |

Comenzile au payload-uri allowlist, actorul vine din contextul autentificat, iar beneficiarul este validat separat. Nu se acceptă ore/WTC, ownership falsificat sau status Holiday în comanda de pontare. CRUD generic/mass actions/relationships rămân închise. Entitățile interne Month/WorkSchedule nu au controller CRUD generic, deci accesul direct la acele rute produce 404; ORM-ul și metadata sunt înregistrate și folosite efectiv.

Status și câmpurile operative editabile prin comenzi sunt guvernate de **FLS edit nativ**; atributul metadata readOnly nu trebuie să anuleze inclusiv dreptul comenzii autorizate. Protecțiile Record Service/access checker/hooks continuă să interzică scrierile CRUD generice. Această distincție a fost verificată atât în browser, cât și prin API cu FLS edit refuzat.

Mutațiile folosesc tranzacții ORM native și locks în ordinea **User → Month → Record**, recitire autoritativă, unicitate și revizie. Crearea concurentă a stării lunii folosește unicitatea DB și tranzacția nativă, nu tabele de mutex. Două cereri HTTP concurente cu revizia zero au produs **200/409 și o singură înregistrare**.

Pachetul nu suprascrie #Admin, Calendar sau Settings global. Override-urile vechi încă existente în laborator aparțin originalelor păstrate instalate; această etapă nu pretinde că le-a restaurat sau dezinstalat.

## 4. Reutilizarea efectivă

Proveniența completă este în [MIGRATION-INVENTORY.md](MIGRATION-INVENTORY.md), [STAGE-4-REUSE.json](STAGE-4-REUSE.json) și MIGRATION-INVENTORY.json: **18 integrări urmărite în etapa 4**, toate cele **196 fișiere originale runtime/lifecycle** și **175 importuri inițiale** păstrate/verificate prin hash.

Sursele backend originale sunt în `AttendanceManagement/files/custom/Espo/Modules/AttendanceManagement/`; frontend în `AttendanceManagement/files/client/custom/modules/attendance-management/`. Copiile mecanice din pending/Attendance au fost baza principală a activării.

| Fișier original | Reutilizare / modificare punctuală |
| --- | --- |
| Tools/Attendance/AttendanceOverviewService.php | Matrice, contoare, lipsuri, Remote/CIC/perioade, validări și notificări; WTC/snapshot în locul regulii fixe, agregare comună și comandă tranzacțională |
| Tools/Attendance/AttendanceService.php | DTO și comportamentul paginii proprii; delegare către aceeași agregare/comandă, în locul duplicării clasificării |
| Tools/Attendance/AttendanceAccessChecker.php | Adaptare la scopes/read own-all/FLS/export native, fără fallback admin |
| Tools/Attendance/ApprovedHolidayProvider.php | Proiecția existentă adaptată pentru lot și CancellationPending; nicio logică contabilă duplicată |
| Tools/Attendance/AttendancePdfGenerator.php, AttendanceXlsxGenerator.php | Rendererele/librăriile/layout-ul păstrate; numai selecția programului zilnic adaptată la intervalele WTC, cu compatibilitatea DTO-ului vechi în regresie |
| Tools/Attendance/Api/PostAttendancePdf.php, PostAttendanceXlsx.php | Formatul răspunsului, filename/base64 și verificarea completitudinii păstrate; guard nativ export adăugat |
| Tools/Attendance/AttendanceSettingsService.php | Politicile existente și initializeArchive reactivat cu ACL delegat, fără ștergeri |
| src/views/attendance/my.js, overview.js, schedules.js | Interfețele originale, selector lună, marcaje, remindere și descărcări; endpoint-uri/ACL/revizie/motiv adaptate; editorul normativ înlocuit conform D6 |
| src/controllers/attendance.js, attendance-overview.js, css/attendance.css | Wiring și CSS reutilizate; routes/guards native, CSS original neschimbat |
| Resources/metadata/entityDefs/AttendanceRecord.json, AttendanceWorkSchedule.json | Metadata/ownership ORM adaptate, audit și revizie; WorkSchedule păstrează numai Remote/CIC/perioade, nu ore normative |
| Holiday src/views/modals/cancellation-reason.js | Modalul nativ existent adaptat pentru motivul corecției Attendance |

Cod nou strict necesar: AttendanceMonthService și entitatea internă pentru rezultate istorice/freeze; action-ul subțire și rutele de conectare. Calculatorul WTC existent a fost extins cu intervalele native și acceptarea unei luni fără zile lucrătoare, fără schimbarea validării zero zile pentru rezervările Holiday. Aceste componente nu înlocuiesc generatoarele sau regulile CIC cu o rescriere.

**Nu s-a eliminat nicio funcționalitate legitimă, setare sau sursă originală/pending.** Configuratorul custom al normei și granturile/override-urile nesigure nu sunt activate, conform deciziilor deja aprobate. Hook-urile care ar dubla scrierile nu sunt instalate în paralel. Funcțiile Holiday/Jobs/Calendar încă neintegrate rămân inventariate, nu sunt declarate implementate.

## 5. Teste executate și dovezi

PASS înseamnă execuție efectivă pe pachetul final, nu simpla existență a unei clase. Numerele reprezintă aserțiuni, nu scenarii independente exhaustive.

| Suită | Rezultat | Dovadă |
| --- | --- | --- |
| Attendance API principal | **74 PASS / 0 FAIL** | [stage-4-api.json](tests/evidence/stage-4-api.json) |
| Attendance API suplimentar/concurență | **20 PASS / 0 FAIL** | [stage-4-extra.json](tests/evidence/stage-4-extra.json) |
| Browser Chromium, șase roluri | **26 PASS / 0 FAIL** | [stage-4-browser.json](tests/evidence/stage-4-browser.json) |
| Regresie Holiday/contabilizare/concurență | **125 PASS / 0 FAIL** | [stage-4-regression-holiday.json](tests/evidence/stage-4-regression-holiday.json) |
| Regresie administrare/profiluri | **120 PASS / 0 FAIL** | [stage-4-regression-administration.json](tests/evidence/stage-4-regression-administration.json) |
| Regresie ACL/date private/Attachment | **126 PASS / 0 FAIL** | [stage-4-regression-acl.json](tests/evidence/stage-4-regression-acl.json) |
| Renderere originale PDF/XLSX/DOCX și helpers de business | **13 PASS / 0 FAIL** | [stage-4-renderers.json](tests/evidence/stage-4-renderers.json) |
| Câmp anual MM-DD original | **2 PASS / 0 FAIL** | tests/regression/reset-date-field.test.mjs, Node TAP |
| Reinstalare nativă/rebuild/clear cache/integritate | **PASS** | [stage-4-lifecycle.json](tests/evidence/stage-4-lifecycle.json) |
| PHP lint/metadata, contract/hash-uri, identitate surse–ZIP | **PASS** | build.sh --validate; tests/contract.py; comparație byte-for-byte a fiecărui membru ZIP |

În total: **94 aserțiuni API Attendance, 26 browser, 371 regresii API și 15 regresii renderere/helpers/câmp anual**, toate fără eșecuri în rulările finale. Scripturile sunt în tests/integration/stage-4.php, stage-4-extra.php, stage-4-lifecycle.php și tests/browser/stage-4.cjs; suitele precedente sunt reutilizate ca regresii, nu rescrise.

Dovezi concrete importante:

- Cohortă sintetică de 15 utilizatori regular activi; WTC 4/6/8 ore, cu o zi legală sintetică RO/ZileLibere în septembrie 2026: **21 zile / 84, 126 și 168 ore**. Același calendar este reutilizat. Fără WTC, afișarea indică neconfigurat și marcajul este refuzat.
- Modificarea WTC 4→8 ore și schimbarea intervalului au păstrat norma istorică de 4 ore și începutul 08:00; reatribuirea WTC utilizatorului de 6 ore a păstrat norma capturată de 6 ore. Managerul nu a putut modifica User.workingTimeCalendarId prin API: 403.
- Ultima zi lipsă completată prin API-ul real al angajatului a produs 100% și frozen persistent. Marcajul ulterior al angajatului: 409; corecția managerului cu motiv: 200; revizia veche: 409. Norma și frozen au rămas neschimbate.
- CIC: AtWork/BusinessTrip refuzate, MedicalLeave permis; CO activ prioritar. Browserul a executat efectiv medicalul și corecția în luna înghețată, a salvat metadatele operative și a descărcat XLSX. Nu au fost observate erori JavaScript în rolurile testate.
- Actor admin: 403; beneficiar admin: exclus/refuzat. Rol fără drepturi/câmpuri: 403; read-all nu acordă management; lipsa permisiunii Export: 403; export incomplet când este interzis: 409. Citirea privată a colegilor și descărcarea documentelor rămân protejate în regresii.
- Reinstalarea/rebuild/clear cache au păstrat hash-urile normelor, frozen, pontajelor/motivelor/reviziilor, Remote/CIC, cererilor/snapshot-urilor, profilurilor, ledger-ului și Roles; au rămas neschimbate setările și înregistrările originale. app-check: DB, migrations, maintenance și cron OK; aceasta **nu dovedește implementarea Jobs Employee Management**.

Fixture-urile au creat direct absențe istorice Approved/CancellationPending numai pentru proiecție; acestea nu sunt rezultatul unui nou flux de aprobare. Soldurile/anulările au fost verificate separat prin suitele Holiday existente. Pentru cohorta Attendance, ceilalți utilizatori sintetici ai laboratorului au fost temporar dezactivați și apoi reactivați. Reinițializarea fixture-urilor lunare a fost precedată de salvarea stării în laborator; nu există endpoint de reset al normei în extensie.

În dezvoltare au fost corectate incompatibilități demonstrate: readOnly metadata care bloca FLS edit inclusiv pentru comenzi autorizate și un pattern de rută care nu era capturat de routerul nativ. Aserțiunile/selectoarele fixture-urilor au fost ajustate pentru numere JSON și rutele interne fără controller (404, nu 403). Numerele PASS de mai sus provin din rerulările finale, nu din încercările inițiale eșuate.

## 6. Reproducere și limite

Build și contract local:

```bash
bash EmployeeManagement/build.sh
python3 EmployeeManagement/tests/contract.py
node EmployeeManagement/tests/regression/reset-date-field.test.mjs
```

Testele de integrare refuză altă instanță decât laboratorul opt-in Espo 10.0.1/siteUrl 127.0.0.1:18089 și necesită credențialele sintetice din mediul de test. Ordine: stage-4.php → stage-4-extra.php → browser stage-4.cjs → stage-4.php restore → regresii → lifecycle capture/install/rebuild/clear_cache/verify. Nu rulați Holiday și administrarea simultan: ambele verifică și modifică temporar aceleași setări sintetice de aprobatori. Browserul folosește starea fixture-ului Attendance, nu date hardcodate din companie. Scripturile nu sunt un mecanism de migrare/producție.

Limitări explicite:

- **Etapa 5 / NOT TESTED ca integrare completă:** atomicitatea la tranzițiile Holiday versus pontare, reconcilierea snapshot-urilor dintre componente și conflictele la aprobare/anulare. În etapa 4, citirea absențelor este funcțională; nu se declară rezolvată concurența dintre toate fluxurile viitoare Holiday și Attendance.
- **Jobs/documente / NOT IMPLEMENTED:** Pending→ApprovalProcessing→Approved, retry/recovery și aprobarea numai după document rămân blocate pentru etapa Jobs. Nu există aprobare provizorie care trebuie rescrisă. Resetările/acordările anuale automate și Calendarul comun minimal rămân pentru etapele lor.
- **Istoric:** stabilitatea începe la prima captură a lunii. Nu există reconstrucție a WTC anterior acestei capturi. Corecțiile Remote/CIC sunt operații explicite auditate; nu modifică norma și nu dezgheață foaia.
- **RISK / acoperire limitată:** lock-ul lunar serializează operații scurte; s-au verificat cohorta de 15 persoane și două cereri HTTP concurente, nu un benchmark de volum mare. DST, programe peste miezul nopții, toate combinațiile de intervale fragmentate și schimbări de timezone nu sunt certificate exhaustiv de această etapă.
- **Lifecycle / NOT TESTED aici:** dezinstalarea, instalarea complet independentă după eliminarea originalelor și release-ul de producție. Rendererele folosesc librăriile vendor native ale Espo instalat, nu clase din modulele vechi; independența întregului pachet după dezinstalarea originalelor nu se deduce doar din acest fapt.

## 7. Verdict și continuare

**Etapa 4 este implementată și verificată pentru funcțiile enumerate; versiunea 0.4.0 este instalabilă în mediul EspoCRM 10.0.1 testat.** Pontarea, matricea, completitudinea, WTC/ZileSarbatoare, norma istorică, CIC/CCC, înghețarea, corecțiile auditate, reminderele și exporturile sunt active, cu cod Attendance 1.11.0 reutilizat prioritar.

Continuarea este etapa 5: consistența Attendance–Holiday, apoi Jobs/documente, Calendar și release. Până la închiderea acestor puncte și testarea integrării complete, extensia **nu este declarată pregătită pentru producție**. Instalația companiei și sursele originale rămân nemodificate; volumele laboratorului sunt păstrate pentru continuare.
