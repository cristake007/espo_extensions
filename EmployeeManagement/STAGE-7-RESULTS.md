> Raport istoric. Corecția urgentă a navigației și validarea completă a paginilor non-admin sunt documentate în [NAVIGATION-HOTFIX-RESULTS.md](NAVIGATION-HOTFIX-RESULTS.md). Etapa 8 nu a fost începută.

# Employee Management 0.7.0 — Calendar comun și automatizări anuale

Data: **20 octombrie 2026**. Bază: **0.6.0**. Implementare incrementală efectivă, **nu production-ready**. Extensiile originale, instalația companiei și documentele arhitecturale aprobate nu au fost modificate.

## 1. Livrare și mediu verificat

- [employee-management-0.7.0.zip](dist/employee-management-0.7.0.zip): **183.598 bytes**, SHA-256 `a6d7365ea0aad3e72cfabe2566a487663303c54327299dcb867e54db3f1d0868`.
- Un singur modul EmployeeManagement. ZIP: **164 fișiere**, inclusiv 44 PHP runtime + 2 scripturi lifecycle, 92 JSON runtime + manifest, 21 JavaScript, DOCX/TPL/CSS și README. Testele, pending și fault-urile de laborator nu intră în pachet.
- Față de 0.6.0: **26 fișiere adăugate, 15 modificate, niciun fișier eliminat**. Manifestul și README sunt incluse în cele 15.
- Verificat: **EspoCRM 10.0.1, PHP 8.4.23, MariaDB 12.3.2, Zile Sărbătoare 0.9.5**. Nu se declară compatibilitate cu versiuni netestate.
- Laboratorul reutilizat: Compose `employee-feasibility-20261020`, `/tmp/employee-feasibility-20261020-yDmmnL/compose.yaml`, URL `http://127.0.0.1:18089`, rețea internă izolată. Originalele Attendance 1.11.0 și Holiday 1.9.4 au rămas instalate/nemodificate acolo.

Instalarea/reinstalarea prin comanda nativă Extensions, rebuild, clear cache și app-check au trecut. La încheiere, containerele **laboratorului** sunt oprite, volumele și probele sunt păstrate; opțiunea anuală este false, expresia Job-ului este restaurată la `5 0 * * *`, nu rămân triggere de fault sau override Calendar temporar.

## 2. Funcționalități operaționale

### Calendar comun nativ și confidențialitate

`EmployeeAbsence` este un Event nativ, cu controller Record, entityDefs/scopes/clientDefs/layouts și ACL native. În Calendar apar **numele angajatului și intervalul inclusiv**, numai pentru Approved și CancellationPending. Pending nu apare în această sursă comună; Cancelled/Rejected nu o mai alimentează.

Proiecția nu are relație spre cererea, profilul sau documentele private. Corelarea internă sourceId este interzisă prin entityAcl; nu este afișată și nu poate fi folosită ca filtru API. Autori/atribute suplimentare globale Calendar nu sunt preluate automat din obiectele private. Culoarea originală calendarColor este exclusiv prezentare: citită live printr-un join ORM restrâns la acel atribut, expusă prin câmp virtual notStorable și protejată de FLS; nu se creează altă configurație de culoare.

Publicarea proiecției rulează prin hook ORM afterSave **în tranzacția existentă de finalizare Approved**, după verificarea documentelor. CancellationPending păstrează aceeași proiecție. Anularea definitivă șterge logic proiecția în tranzacția restituirii/ledger-ului; eșecul ștergerii face rollback întregii comenzi. Hook-ul nu contabilizează solduri.

EmployeeAbsence.read=all trebuie acordat explicit prin Roles; implicit este refuzat. CRUD, link writes, mass operations și export sunt închise inclusiv când un rol încearcă să le acorde. Filtrul Select și checker-ul de acces prin ID exclud beneficiarii admin/inactivi. Actorul tehnic este exclus din operațiile extensiei, fără alterarea privilegiilor globale native EspoCRM.

Sursa privată `EmployeeHolidayRequest` reutilizează interogarea Calendar originală, dar afișează numai Pending autorizate. Colegii nu primesc read-all pe cereri pentru a vedea absențele. Pentru citirea cererilor altora se cere și scope-ul de aprobare, pe lângă ACL/FLS native; skipAcl nu ocolește aceste verificări. Nu există un al doilea marker pentru aceeași cerere Approved.

Cele două surse respectă fusul orar din Preferences/config native și limita superioară exclusivă a interogării Calendar. Sunt verificate efectiv o singură zi în Day, intervale parțiale, Europe/Bucharest și America/New_York. Nu se schimbă datele cererilor sau norma WTC.

### Acordări anuale prin Scheduled Jobs native

`Jobs/ProcessEmployeeHolidayResets` adaptează JobDataLess/run original și apelează metodele reutilizate din HolidayProfileService. Cheia proprie evită coliziunea cu vechiul ProcessHolidayResets. Metadata ScheduledJob păstrează expresia originală **`5 0 * * *`**, prin Populator nativ; programarea și execuția apar în ScheduledJob/Job/istoricul native. Managerul nu primește acces la panoul global Jobs.

Instalarea nu activează acordarea: flag-ul `employeeHolidayAutomaticAnnualGrantEnabled` primește false **numai dacă lipsește**. Managerul autorizat îl configurează explicit în pagina delegată, prin câmpul bool nativ existent. Upgrade-ul păstrează atât false, cât și true configurat. Un ScheduledJob tehnic Active cu flag business false nu modifică solduri.

Regula originală este păstrată: **sold nou = sold vechi + entitlement**. nextResetDate avansează cu un an; recuperarea anilor scadenți păstrează limita originală de 100 iterații. CarryOverLimit rămâne prag de avertizare, nu expirare sau plafon nou. Sunt procesate numai profilurile inițializate ale utilizatorilor regular activi.

Fiecare scadență folosește cheie stabilă profil/dată, hash al operației și verificarea actuală sub locks User → Profile. Soldul, nextResetDate, ledger-ul annualGrant și notificările aferente sunt tranzacționale. Două Jobs concurente nu acordă de două ori aceeași scadență. Dacă un profil eșuează, profilurile deja comise nu sunt recreditate la execuția următoare; profilul nereușit se reia fără pierderea scadenței.

Notificările originale de prag sunt reutilizate, cu linkuri spre rutele delegate/private. Destinatarii trebuie să fie regular activi și să aibă read/FLS actual pe profil/sold. Lista configurată de aprobatori nu acordă singură permisiuni. Nu sunt introduse expirări, proratări sau alte reguli HR.

### Lifecycle

AfterInstall reutilizează ConfigWriter și Populator native: adaugă idempotent numai cele două surse Calendar proprii, setează default-ul anual absent și face backfill idempotent. Nu schimbă Roles, WTC, tabList sau rutele globale Admin/Calendar.

Cleanup-ul adaptat este în **AfterUninstall**, nu BeforeUninstall: în 10.0.1 upgrade-ul sare peste after-script-ul dezinstalării, dar nu peste before-script. Astfel, un upgrade nu dezactivează accidental Job-ul deja configurat. Cleanup-ul este limitat la propriile surse/Job și nu șterge date/fișiere/setări. **Dezinstalarea completă nu este încă certificată practic.**

## 3. Reutilizarea efectivă și justificarea adaptărilor

Proveniența și hash-urile exacte sunt în [STAGE-7-REUSE.json](STAGE-7-REUSE.json), [MIGRATION-INVENTORY.md](MIGRATION-INVENTORY.md) și [MIGRATION-INVENTORY.json](MIGRATION-INVENTORY.json): **10 mapări de reutilizare**, toate cele **196 surse originale** și **175 importuri inițiale** păstrate/verificate. Prefixul H de mai jos este `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/`; destinațiile PHP sunt în `files/custom/Espo/Modules/EmployeeManagement/`.

| Fișier original / bază pending | Reutilizare și modificări |
| --- | --- |
| H `Services/HolidayRequest.php`, pending `Services/EmployeeHolidayRequest.php` | Services/EmployeeHolidayRequest.php și Services/EmployeeAbsence.php păstrează getCalenderQuery, SelectBuilder, coloanele native și culoarea. Adaptări punctuale: separarea privat/public, strict ACL/FLS, eligibilitate, allowlist și limite de dată locale |
| H `Tools/HolidayBalance/HolidayBalanceService.php`, copia pending | HolidayProfileService preia reset/processDueResets/applyResetGrant/nextYear/formatDays și notificările. Formulele originale rămân; se adaugă chei idempotente, locks, autorizare și corectarea citirii concurente demonstrate |
| H `Jobs/ProcessHolidayResets.php` și `Resources/metadata/app/scheduledJobs.json` | Același entry point nativ și aceeași expresie; numele devine ProcessEmployeeHolidayResets pentru evitarea coliziunii |
| HolidaySettingsService și frontend original `views/admin/settings.js`, prin adaptorul delegat existent | Aceleași setări/validări/câmpuri native. Se elimină numai blocarea temporară din etapa 2 a valorii automaticAnnualGrant=true, acum implementată |
| H notificări/traduceri balanceWarning* | Mesaje/praguri păstrate; rute private/delegate și destinatari cu ACL/FLS actual |
| H `scripts/AfterInstall.php`, `scripts/BeforeUninstall.php` | ConfigWriter/Populator și cleanup reutilizate, limitate la înregistrări proprii; cleanup mutat în AfterUninstall pentru semantica reală de upgrade |
| Serviciile Request/Profile active în 0.6.0 | Eliminarea proiecției locking assignedUserName nefolosite; identificarea owner-ului înaintea tranzacției și verificarea sa după lock. Fără rescrierea rezervărilor sau a contabilității |

Cod nou justificat, fără echivalent sigur în original: entitatea minimală Event, metadata/controller/layouts, proiecția și hook-ul, AdditionalApplier/checker pentru eligibilitate. Nu există motor Calendar, configurator WTC, ledger paralel sau runner Jobs propriu.

**Nicio funcționalitate originală nu este eliminată.** DOCX/template, PDF/XLSX, normele WTC/ZileSarbatoare, CIC/CCC, înghețarea și logica validă a cererilor sunt reutilizate. Vechile override-uri globale/granturi nesigure și resetul distructiv nu sunt activate. Overview/dashlet Holiday complet și endpoint/UI manual force-reset rămân în inventar/pending, neexpuse în această versiune; nu sunt înlocuite prin alte comportamente.

## 4. Defecte demonstrate și remediate în această etapă

1. **Concurență anuală / MariaDB 1020:** identificarea profilului printr-o citire nonlocking în interiorul tranzacției stabilea un snapshot Repeatable Read înaintea lock-ului User. Al doilea Job putea citi o versiune contabilă veche. Identificarea owner-ului precede acum tranzacția; apoi User/Profile se citesc locking și owner-ul se reverifică. Eliminarea join-ului nefolosit singură nu rezolva defectul. Proba finală folosește două Jobs native pornite în aceeași secundă, trei profiluri și barieră SQL SLEEP(2), **attempts=0**, fără retry implicit ascuns: ambele Success, câte un credit/ledger per profil.
2. **Restaurarea proiecțiilor șterse logic:** lookup-ul implicit excludea rândul șters și putea lovi cheia unică sourceId la backfill. Acum folosește QueryBuilder.withDeleted și repository.restoreDeleted native. Repetarea backfill-ului și soft-delete/restaurare păstrează același ID, fără duplicate.
3. **Absență de o zi lipsă în Calendar Day:** trunchierea UTC a limitelor din query-ul original excludea date locale valide și putea include ziua precedentă. Adaptarea locală a limitelor trece cele opt probe API de margine și randarea browser Day pe ZIP-ul final. Nu se schimbă regula intervalului inclusiv al concediului.

Încercările exploratorii care au demonstrat aceste defecte **nu sunt numărate drept PASS**. Un conflict de suprapunere provocat de reutilizarea unei cohorte vechi în prima ordine de regresie a fost rezolvat prin cohortă proaspătă/ordinea corectă a fixture-urilor, nu prin relaxarea regulilor backend.

## 5. Teste executate și dovezi

**845 verificări în rulările finale selectate, zero eșecuri:** 155 API noi, 68 browser noi, 619 regresie, 2 regresie Node și 1 comparație lifecycle. Numărul este de aserțiuni/controale, nu de funcționalități distincte. Validările statice nu sunt adăugate acestui total.

| Suită | Rezultat observat |
| --- | --- |
| [API etapa 7](tests/evidence/stage-7-api.json) | **155 PASS**: aprobare reală/document, Calendar minimal, ID/filtre/link/export/FLS, CancellationPending/anulare, opt-in anual, catch-up/avertizări, administratori/inactivi excluși, concurență, eșec/reluare și conservarea ledger-ului |
| [Browser view legacy](tests/evidence/stage-7-browser-legacy.json) | **35 PASS**, 6 roluri: autoaprobare prin Job, DOCX descărcabil, checkbox anual, popup minimal, moduri Calendar, refuzuri private și fără erori JS |
| [Browser view ZileSarbatoare](tests/evidence/stage-7-browser-zile.json) | **33 PASS**, 6 roluri: decorarea efectivă a sărbătorii 30 noiembrie, marker Approved/CancellationPending, Day cu o singură zi, ACL/FLS, fără erori JS |
| Regresie Attendance | **74 API + 20 extra + 26 browser PASS**: WTC 4/6/8 ore, normă istorică, înghețare/corecții, CIC/CCC/medical, exporturi/remindere și administrare existente |
| Regresie integrare Attendance–Holiday | **103 + 12 PASS**: conflicte, anulări, rezervări, concurență, revizii, sold/ledger și exporturi. Aceste fixture-uri istorice folosesc finalizare controlată exclusiv de laborator; suita nouă testează suplimentar Jobs reale |
| Regresie Holiday / administrare / ACL | **125 / 120 / 126 PASS**. Două fixture-uri adaptate punctual: true este acum setare anuală validă; sursa Calendar privată Pending este acum înregistrată. Restul controalelor sunt păstrate |
| Renderere și câmpul reset-date | **13 + 2 PASS**, generatoarele/template-ul nu sunt rescrise |
| [Reinstalare/rebuild/cache](tests/evidence/stage-7-lifecycle.json) | **PASS**: hash-uri identice pentru pontaje, norme, profiluri, ledger, cereri/snapshots/manifeste, proiecții, documente, Jobs de aprobare, Notifications și Roles; originalele/tabList/rutele existente păstrate; annualFlag=true și ScheduledJob păstrate |
| [Verificări statice](tests/evidence/stage-7-static.json) | **PASS**: lint 46 PHP runtime/lifecycle și 4 fixture-uri, parse 93 JSON inclusiv manifest, syntax 21 JS runtime + browser, contract/proveniență/identitate ZIP–surse |

Fișierele `tests/evidence/stage-7-regression-*.json` păstrează rezultatele complete. Versiunea istorică dintr-un fixture este versiunea fixture-ului, **nu** versiunea extensiei instalate; wrapper-ul identifică pachetul 0.7.0 testat.

### Calificarea exactă a artefactelor testate

Primele cinci faze API (setup/calendar/annual-setup/annual-complete/calendar-boundary-setup: **84 controale**) și browser legacy (**35**) au rulat pe candidatul `8aa68c9c9162ac7dbd349b09539bd5e8d0dd73ebf10eefa8a33362c500797120`, înaintea ultimelor adaptări exclusiv Calendar pentru date/culoare.

Celelalte **71 API**, browser Zile (**33**), toate cele **619 regresii**, Node și lifecycle au trecut pe ZIP-ul final `a6d7365e…`. Serviciile contabile, Job-ul anual și ACL nu au fost schimbate între acești candidați; pe ZIP-ul final au fost totuși repetate concurența anuală, eșecul/reluarea, publicarea/aprobarea reală și anularea tranzacțională. Dovezile includ pentru fiecare rulare `testedArtifactSha256` și `executedOnExactFinalZip`; nu se pretinde rerularea fiecărui control pe ultimii bytes.

### Probe de eșec și atomicitate

- Eșec injectat la ledger annualGrant pentru al doilea profil: Job nativ Failed; primul profil rămâne comis, al doilea revine integral. Noua execuție nativă continuă, fără recreditarea primului.
- Eșec injectat la inserarea EmployeeAbsence în finalizarea aprobării: cererea rămâne ApprovalProcessing, fără publicare prematură; staging-ul DOCX rămâne privat. Retry-ul finalizează cu același document și fără alt debit/ledger.
- Eșec injectat la ștergerea proiecției în anulare: statusul/refund-ul/ledger-ul sunt rollback. Două confirmări concurente ulterioare cu aceeași cheie returnează succes idempotent, o singură restituire și dispariția markerului.
- Conversia temporară a beneficiarului sintetic în admin ascunde proiecția din feed și prin ID; restaurarea tipului nu generează altă contabilizare.

Nu s-au repetat kill/restart-urile de worker din etapa 6: codul acelui mecanism este neschimbat; proba acestei etape acoperă noua frontieră tranzacțională Calendar. Nu se certifică prin aceasta toate scenariile de crash/hardware.

## 6. Calendar și ZileSarbatoare: limitarea reală a laboratorului

Noul pachet **nu conține override clientDefs/Calendar sau înlocuire de view Calendar**. Se integrează prin Event/scopes/servicii Calendar și adăugare idempotentă la lista nativă de surse.

Laboratorul păstrează vechiul Holiday, care încă suprascrie view-ul ZileSarbatoare, și o sondă veche FeasibilityAbsence fără checker pentru administrator, care provoca un 500 independent de noul modul. Pentru proba reală Zile s-a selectat **temporar numai în laborator** metadata originală Zile printr-un fișier Custom byte-identic; sonda veche a fost temporar exclusă din lista Calendar. S-au verificat efectiv decorarea zilelor legale și noua sursă împreună, nu numai existența claselor.

Ambele modificări temporare au fost restaurate înaintea baseline-ului lifecycle. View-ul global și ruta Admin observate după test rămân cele ale extensiilor vechi; Employee Management nu le-a rescris sau reparat prin modificarea originalelor. **Coexistența fără conflict cu vechile override-uri nu este declarată rezolvată.** Certificarea instalării standalone Employee Management + Zile, fără vechile module, rămâne etapa 8; procedura manuală recomandă acel mediu separat.

## 7. Situația P1–P11

| Problemă | Închidere demonstrată / limită |
| --- | --- |
| P1 — ACL/CRUD | Extinsă verificarea la proiecția nouă: strict-default deny, read-only, FLS, ID/link/filter/export/API și populație regular. Regresia ACL 126 trece |
| P2 — expunere Calendar/granturi obligatorii | **Închisă pentru noul flux comun testat**: colegul vede nume/date, nu cerere/motiv/profil/sold/document; fără read-all forțat pe cereri și fără granturi automate |
| P3 — concurență/stări vechi | Extinsă închiderea la acordări anuale și anulare/proiecție: Jobs concurente fără retry ascuns, single-credit/ledger și rollback verificat |
| P4 — CancellationPending | Calendarul nou păstrează markerul până la anularea definitivă; regresia matrice/rapoarte/export păstrează aceeași prioritate |
| P5 — Cancelled rezervă intervalul | Regresia integrării trece; Calendarul elimină proiecția, Rejected nu o publică. Fără alt owner contabil |
| P6 — ruta Admin | Noul pachet nu o suprascrie; lifecycle păstrează ruta globală deja existentă. Override-urile originalelor nu sunt modificate |
| P7 — trasabilitate/normă | Regresiile normei istorice, freeze/corecții și audit trec; ledger anual cu actor sistem/cheie/scadență, fără rescriere WTC |
| P8 — aprobare/storage | Extinsă închiderea la publicarea Calendar în tranzacția Approved/documente și la rollback/retry. Rămâne limita DB/FileStorage și testarea exhaustivă de release |
| P9 — N+1 | Nu se declară închis global. Query Calendar este batch, fără profil privat încărcat per marker; backfill/anual sunt intenționat per tranzacție/profil. Profilarea finală rămâne necesară |
| P10 — lifecycle/navigare | Reinstalare idempotentă și retenție trecute, inclusiv opt-in anual true. Standalone, uninstall, upgrade eșuat și navigare grupată/customizată nu sunt certificate aici |
| P11 — view Calendar/Zile | Noul modul nu suprascrie view-ul; integrarea cu view-ul Zile real a trecut în condițiile de laborator explicate. Conflictul instalării paralele a originalelor și certificarea standalone rămân de închis la release |

## 8. Funcționalități păstrate și lucrări rămase

Operaționale cumulativ: administrare delegată/roluri native, profiluri/solduri/corecții/ledger, cereri/anulări, Attendance și matrice/remindere/exporturi, WTC/ZileSarbatoare și normă istorică, CIC/CCC/medical, înghețare/corecții auditate, aprobări prin Jobs/DOCX private cu retry, Calendar minimal și acordări anuale opt-in.

Nu sunt încă certificate/integrate complet: Holiday overview/dashlet original complet, API/UI manual force-reset păstrat în pending, instalare standalone curată fără originale, dezinstalare/reinstalare completă, upgrade eșuat/backup-restore, profilare de performanță și checklist final de release. Resetul distructiv rămâne intenționat neexpus. Calendar TeamTimeline/shared-calendar, toate tranzițiile DST și traversarea reală a schimbării anului nu au fost probate exhaustiv. Scadențele anuale au fost create ca date de test, fără schimbarea ceasului sau a regulilor companiei.

ScheduledJob folosește ceasul/timezone-ul și disponibilitatea cron/daemon native; expresia nu este un SLA de execuție. Nu există infrastructură suplimentară sau drepturi Jobs globale pentru manager.

Instrucțiuni: [MANUAL-TEST-STAGE-7.md](MANUAL-TEST-STAGE-7.md). Fixture-uri: `tests/integration/stage-7.php`, `stage-7-lifecycle.php`, cele două regresii adaptate și `tests/browser/stage-7.cjs`; sunt opt-in și restrânse la laboratorul sintetic, nu scripturi pentru producție. Capturile browser sunt păstrate în directorul laboratorului, `employee-stage7-{legacy|zile}-{role}.png`.

[Dovezile mediului](tests/evidence/stage-7-environment.json) consemnează și inspecția read-only a containerelor companiei: aceleași ID-uri și StartedAt, toate running. Nu s-au executat instalări, API-uri de scriere sau operații DB asupra acestora. **0.7.0 este o livrare incrementală testată, nu o autorizare de producție.**
