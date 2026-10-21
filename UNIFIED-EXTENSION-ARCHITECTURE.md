# Employee Management — arhitectura extensiei unificate

Data inițială: 19 octombrie 2026. Actualizare finală: **20 octombrie 2026 — deciziile de fezabilitate aprobate și autorizarea implementării incrementale**.

Surse analizate: Attendance Management **1.11.0**, Holiday Management **1.9.4**, integrarea relevantă din Zile Sărbătoare **0.9.5**, auditul P1–P11 și codul oficial EspoCRM **10.0.10**, tag `10.0.10`, commit `f990998f2173c103d36ab5b35d759f676c59502a`.

Baza runtime este acum **EspoCRM 10.0.1 / PHP 8.4.23 / MariaDB 12.3.2**, demonstrată în [raportul de fezabilitate](EMPLOYEE-MANAGEMENT-FEASIBILITY.md). Referințele 10.0.10 din analiza inițială sunt trasabilitate de sursă, nu certificare runtime sau cerință de upgrade. Instalația existentă rămâne nemodificată; implementarea și testele cu scrieri folosesc exclusiv laboratorul izolat. Nu se modifică celelalte reguli aprobate de pontaj, CIC/CCC, concedii sau înghețare lunară.

## Rezumat executiv

Recomand **un singur pachet Employee Management și un singur modul EspoCRM `EmployeeManagement`**, cu două componente interne, Attendance și Holiday. Separarea lor este dată de entități, servicii de business, scopes ACL și interfețe, nu de două cicluri independente de instalare.

Păstrăm ORM-ul, metadata, hooks, ledger-ul concediilor, idempotency, aprobările, calculatoarele și exporturile care au o justificare reală. Refactorizăm limitele de autorizare și tranzacție, administrarea, interpretarea comună a absențelor și lifecycle-ul pachetului. Nu construim un motor de permisiuni, un Entity Manager, un router, o coadă de joburi sau un framework de evenimente propriu.

Administrarea delegată va avea ruta **`#EmployeeAdministration`**, accesibilă managerilor regular autorizați din meniul nativ din dreapta sus, exclusiv pentru funcționalitățile extensiei. `#Admin`, administrarea Roles, instalarea extensiilor și crearea/configurarea/atribuirea WTC rămân responsabilitatea administratorului tehnic EspoCRM. Fiecare operație Employee Management verifică pe server atât eligibilitatea de business, cât și permisiunile native.

**Principiul de responsabilitate:** administratorul EspoCRM configurează infrastructura și drepturile; managerii Employee Management operează extensia; angajații folosesc funcționalitățile acordate prin roluri. Administratorul tehnic nu este angajat, beneficiar sau operator Employee Management: nu are pontaj/profil/sold, nu depune sau aprobă concedii și nu modifică pontaje. Nu intră în populația calculelor, statisticilor, rapoartelor sau exporturilor. Aceste reguli de business nu încearcă să-i elimine privilegiile globale native.

Managerul este un utilizator regular, inclus în populația angajaților, cu roluri native acordate de administrator. În limita acestor roluri gestionează toți angajații, corectează pontaje inclusiv în luni înghețate, aprobă/respinge inclusiv propriile cereri, gestionează setările specifice extensiei și operațiile de sold autorizate. Nu poate administra WTC, Roles, extensii sau setări globale prin Employee Management.

Programul normativ este citit **exclusiv din WTC nativ**, adoptat în urma probelor practice. Dimensiunea estimată este de aproximativ **15 angajați și maximum patru programe distincte**. Employee Management citește calendarul normativ explicit, nu disponibilitatea personală UserCalendar; compune rezultatul cu ZileSarbatoare și fixează rezultate istorice read-only pentru perioadele care necesită stabilitate. Nu există program configurabil paralel sau delegare a administrării WTC către manageri.

Decizii aprobate pentru implementare:

- Calendarul comun arată numai numele angajatului și intervalul absenței Approved/CancellationPending prin Event-ul minimal `EmployeeAbsence`. Fără Pending, motive, solduri, documente sau legături publice la cererea privată. Eliminăm mandatory read-all care a reprodus P2.
- Documentele de aprobare sunt generate prin Jobs native din snapshot. Cererea trece prin `ApprovalProcessing`, recuperabilă; devine definitiv `Approved` **numai după generarea și salvarea tuturor documentelor**. Retry-urile sunt idempotente, iar operațiile Processing abandonate se recuperează explicit.

Nu recomand rescrierea completă. Comasarea nu rezolvă automat niciuna dintre problemele P1–P11.

## 1. Evaluarea arhitecturii actuale și baza verificată

### 1.1. Ce este deja corect

Ambele extensii sunt în principal construite pe mecanismele EspoCRM, nu pe un framework paralel:

| Componentă actuală | Evaluare | Tratament în extensia unificată |
| --- | --- | --- |
| PHP în `custom/Espo/Modules`, frontend în `client/custom/modules` | Extensibilitate nativă; fără modificarea core-ului | Păstrăm modelul, schimbăm namespace-ul într-un singur modul |
| `entityDefs`, `scopes`, `clientDefs`, layouts | Înregistrare nativă a entităților și UI | Consolidăm și corectăm definițiile, nu le înlocuim cu configurări proprii |
| EntityManager, repositories, constructor DI | Mecanisme native adecvate | Păstrăm; queries în lot în loc de N+1 |
| `HolidayRequest` de tip Event și integrarea Calendar | Model potrivit cererilor calendaristice | Păstrăm cererea; separăm vizibilitatea sa de vizibilitatea absenței publice |
| `recordDefs` și hooks de protecție a entităților interne | Graniță API justificată | Completăm acoperirea pentru toate căile CRUD și relationships |
| `HolidayProfile`, `HolidayLedger`, chei unice și idempotency | Contabilizare de business justificată | Păstrăm, cu tranzacții coerente pentru întregul caz de utilizare |
| Jobs/ScheduledJobs pentru acordările anuale | Folosire corectă a infrastructurii existente | Păstrăm runner-ul EspoCRM, fără cron separat |
| Matrice lunară, XLSX/PDF, DOCX românesc | Cerințe care nu sunt înlocuite automat de CRUD/export generic | Păstrăm output-urile și testele lor; simplificăm doar codul redundant |
| `ConfigWriter` și păstrarea setărilor deja salvate | Abordare corectă | Păstrăm, cu allowlist strict pentru administrarea delegată |

`readOnly` în `entityDefs` nu este exclusiv o restricție vizuală: în 10.0.10 este preluat în restricțiile backend de `Core/Acl/GlobalRestriction.php`. Totuși, nu interzice singur ștergerea unei entități și nu execută autorizarea într-un serviciu care folosește direct ORM.

`preserveAuditLog` păstrează înregistrările de audit existente; nu activează auditarea câmpurilor. `stream: false` nu exclude automat auditarea: `Tools/Stream/HookProcessor::afterSaveNoStream` procesează câmpurile auditate dacă salvarea nu folosește opțiuni care o dezactivează.

### 1.2. Unde sunt problemele reale

Defectele confirmate în cod sunt concentrarea unor drepturi prea largi, definiții ACL ineficiente, citiri de stare neprotejate uniform la concurență, contradicții între componente și suprascrieri globale de rută/view. Unele consecințe, precum o intercalare concurentă concretă sau un fișier orfan după o eroare de storage, sunt riscuri deduse din cod, nu incidente reproduse într-o instanță reală.

`HolidayBalanceService` concentrează cereri, decizii, profiluri, contabilizare și resets. Separarea responsabilităților este justificată aici; introducerea unui strat generic de repositories, DTO-uri pentru fiecare entitate, event bus sau motor de workflow nu este justificată.

### 1.3. Versiunea vizată și limitele verificării

- Manifestele actuale declară EspoCRM `>=10.0.0`; testul Docker Holiday folosește `10.0.2`. Aceasta nu dovedește testarea tuturor versiunilor 10.x.
- Prima versiune este dezvoltată și testată **exclusiv pentru 10.0.1**, versiunea instalației existente. Nu actualizăm EspoCRM și nu declarăm compatibilitate cu versiuni netestate.
- PHP recomandat inițial: **8.4**, în acord cu Holiday și ZileSarbatoare. Nu declarăm alte combinații PHP compatibile înainte de CI.
- Versiunea instalației existente a fost verificată read-only. Instalarea, metadata, WTC, ACL, browser, Jobs/restart și lifecycle au probe reale în laborator; acestea nu certifică încă implementarea unificată sau toate cursele concurente.
- În auditul anterior au trecut lint-ul pentru 81 fișiere PHP, 66 teste JS și șase suite PHP pure/cu stubs. Aceste rezultate nu sunt certificare de integrare și nu validează arhitectura viitoare.

Pentru prima versiune `acceptableVersions` este **`["10.0.1"]`**, verificat prin installer. Plaja PHP inițială este 8.4.23, singurul runtime testat. Extinderea suportului necesită teste explicite; niciun upgrade de producție nu este implicit autorizat.

### 1.4. Mecanisme native confirmate

Documentația online este actualizată continuu. Pentru implementare au prioritate codul și comportamentul runtime 10.0.1 verificate în laborator; inventarul de mai jos provine din analiza inițială de sursă 10.0.10 și se folosește numai în limitele confirmate pe 10.0.1.

| Necesitate | Mecanism verificat în 10.0.10 | Consecință arhitecturală |
| --- | --- | --- |
| Un pachet, mai multe fișiere/module | Manifest + `files/` + `scripts/`; copierul nu impune un singur modul | Alegerea unu/două module nu schimbă numărul de pachete |
| Permisiuni configurabile | Roles, scopes, `Acl`/`AclManager`, access/ownership checkers native | Fără tabel de permisiuni sau roluri propriu |
| Restricții câmpuri | `entityAcl`, `entityDefs.readOnly`, ACL de câmp | Nu confundăm ascunderea layout-ului cu autorizarea |
| Hooks CRUD | `recordDefs.earlyBeforeCreateHookClassNameList`, `earlyBeforeUpdateHookClassNameList`, `beforeDeleteHookClassNameList` | Defaults și guards la granița Record Service |
| Operații de business | Clase `Espo\Core\Api\Action`, rute native `Resources/routes.json` | Endpoint-uri doar unde CRUD simplu nu exprimă operația |
| Meniu utilizator | `app.clientNavbar.menuItems` și `accessDataList` | Intrare nativă spre workspace delegat, fără inserare DOM |
| Tranzacții/locking | `EntityManager->getTransactionManager()->run(...)`, repository `forUpdate()` | Verificarea stării și mutația trebuie să fie în aceeași tranzacție |
| Control optimist | `entityDefs.optimisticConcurrencyControl` și implementarea Record Service | Protecție suplimentară pentru formulare, nu substitut pentru locking contabil |
| Program de lucru | `Espo\Tools\WorkingTime\CalendarFactory`, `CalendarUtilityFactory`, `CalendarUtility` | Reutilizăm calculul nativ după clarificarea semanticii, nu creăm un scheduler propriu |
| Joburi | `JobSchedulerFactory`, `JobScheduler`, `Job`/`JobDataLess`, ScheduledJob Populator | Fără queue/worker separat |
| Fișiere | Entitatea `Attachment`, `FileStorageManager`, ACL Attachment | Nu publicăm URL-uri de storage și nu inventăm tranzacție DB+fișiere |
| Dependențe de extensii | `manifest.dependencies`, verificat de `Core/Upgrades/Actions/Helper::checkDependencies` | Putem impune versiunea ZileSarbatoare prin Extension Manager |

Surse oficiale: [metadata](https://docs.espocrm.com/development/metadata/), [ACL](https://docs.espocrm.com/development/acl/), [API actions](https://docs.espocrm.com/development/api-action/), [pachete](https://docs.espocrm.com/development/extension-packages/). Referințele exacte de cod sunt în anexa A.

## 2. Arhitectura propusă

### 2.1. Un modul sau două module în același pachet?

| Alternativă | Avantaje | Dezavantaje | Evaluare |
| --- | --- | --- | --- |
| Un modul `EmployeeManagement`, două componente interne | Un owner pentru Settings, navbar, ACL și Calendar; DI și integrare directe; lifecycle unic | Namespace-urile și asset-urile actuale trebuie redenumite; separarea logică trebuie respectată explicit | **Recomandată**, fiindcă produsul va fi livrat și actualizat împreună |
| Două module `AttendanceManagement` și `HolidayManagement`, un pachet | Refolosește căile actuale; util dacă modulele vor redeveni produse independente | Ownership comun mai dificil; cod comun plasat arbitrar într-un modul sau risc de al treilea modul; ordinea metadata rămâne relevantă | Validă tehnic, dar fără beneficiu suficient pentru cerința actuală |
| Două module plus modul comun de infrastructură | Separare formală foarte puternică | Trei module, dependențe și administrare mai complicate decât domeniul | Nerecomandată în acest proiect |

Un modul nu înseamnă un serviciu monolitic. Componentele nu își modifică reciproc entitățile fără operația de business responsabilă.

### 2.2. Responsabilități

| Zonă | Responsabilitate | Nu deține |
| --- | --- | --- |
| Attendance | Înregistrări zilnice, corecții, perioadă editabilă, completitudine, remindere, rapoarte și exporturi | Solduri și tranziții Holiday |
| Holiday Request | Creare/modificare cerere, tranziții, autorizarea deciziei și anulării | Programul normativ global, sincronizare Nager.Date |
| Holiday Balance | Profiluri, entitlement, rezervare/restituire, ledger, corecții și acordări anuale | Generare UI, Calendar frontend |
| Work Calendar | Citirea WTC nativ atribuit angajatului și combinarea normei cu zilele nelucrătoare externe | Crearea/editarea/atribuirea WTC, solduri, aprobări sau administrarea globală |
| Administration | Workspace pentru managerii regular, secțiuni Employee Management autorizate, API-uri limitate de setări | WTC, Roles, permisiuni globale, extensii sau setări globale EspoCRM |
| Documents | DOCX din snapshot, atașamente și reluarea generării | Decizia de aprobare sau recalcularea retroactivă a soldului |
| Calendar integration | Event-uri autorizate și reprezentarea minimală a absențelor | Acordarea de drepturi asupra cererii-sursă |

`HolidayBalanceService` rămâne responsabil pentru invariantul sold–ledger. Operațiile de cerere sunt coordonate de serviciul Holiday Request. Separăm aceste responsabilități folosind clasele existente reorganizate; nu introducem o ierarhie de servicii generice.

### 2.3. Ce păstrăm, ce refactorizăm, ce eliminăm

**Păstrăm:** entitățile de business, utilitarele matematice, politica datelor de rezervare, restricțiile valide, ledger-ul și cheile unice, notificările native, exporturile și template-ul DOCX, layouts și field views justificate. Păstrăm regulile aprobate de pontaj, CIC/CCC, concedii, Calendar, Jobs și înghețare lunară, inclusiv corecțiile manageriale autorizate în lunile înghețate. Administratorii sunt excluși explicit din populația și operațiunile de business, nu doar omiși implicit la inițializare. Acordările anuale rămân dezactivate implicit.

**Refactorizăm:** accesul ACL și validările de eligibilitate, separarea dreptului de overview de drepturile de modificare/configurare, tranzacțiile complete CRUD/decizie, citirea programului normativ din WTC, citirile în lot, generarea documentelor și instalarea. Calculul zilelor nelucrătoare este deținut într-un singur loc, folosind contractul public existent ZileSarbatoare. Eliminăm editorul propriu de ore normative și orice comandă delegată de administrare/atribuire WTC.

**Eliminăm:** suprascrierile `Admin`, granturile mandatory globale Holiday/Calendar, cheile CRUD fără efect din `aclDefs`, scripturile globale care injectează meniuri sau caută elemente prin MutationObserver, dublarea provider-ului ZileLibere și comparațiile izolate de status cu semantică divergentă. Nu eliminăm toate access checkers: o clasă care folosește ACL nativ și verifică o regulă de business poate fi justificată.

Nu introducem o entitate Employee care să dubleze `User`. `HolidayProfile` este profilul contabil al unui User, nu un al doilea sistem de identitate.

## 3. Structura directoarelor

Structura de mai jos descrie ținta incrementală, nu o promisiune că toate componentele sunt deja implementate. Numele claselor proprii nu reprezintă API-uri EspoCRM. Entitățile instalate au prefixul `Employee` (de exemplu `EmployeeHolidayRequest`, `EmployeeAttendanceRecord`); denumirile scurte din model desemnează componentele domeniului. Această separare împiedică suprascrierea metadata/tabelelor extensiilor originale păstrate în laborator. Codul reutilizat încă neactivat este păstrat în `pending/`, în afara ZIP-ului, cu proveniență și pași de activare documentați.

```text
EmployeeManagement/
├── manifest.json
├── scripts/
│   ├── BeforeInstall.php
│   ├── AfterInstall.php
│   ├── BeforeUninstall.php
│   └── AfterUninstall.php             # numai dacă există cleanup necesar după scoaterea fișierelor
├── files/
│   ├── custom/Espo/Modules/EmployeeManagement/
│   │   ├── Entities/                 # doar clase cu comportament real, ex. HolidayRequest/Event
│   │   ├── Services/
│   │   │   ├── HolidayRequest.php    # numai integrarea Calendar legacy, dacă rămâne necesară
│   │   │   └── EmployeeAbsence.php   # proiecția minimală aprobată pentru Calendarul comun
│   │   ├── Tools/
│   │   │   ├── Attendance/           # servicii, calcul și Api/ pentru pontaj
│   │   │   ├── HolidayRequest/       # fluxul cererii, decizii, anulări și Api/
│   │   │   ├── HolidayBalance/       # ledger/profil/corecții și Api/
│   │   │   ├── HolidayDocument/      # generator și persistarea atașamentelor
│   │   │   ├── WorkCalendar/         # citire WTC + calcul comun, fără administrare de calendare
│   │   │   └── Administration/       # Settings allowlist și Api/ dedicate
│   │   ├── Classes/
│   │   │   ├── Record/Hooks/         # defaults/guards CRUD
│   │   │   ├── Acl/                  # numai checker-ele suplimentare necesare
│   │   │   └── Select/               # numai filtrele native suplimentare necesare
│   │   ├── Hooks/                    # guards și efecte tranzacționale ORM, fără contabilizare dublată
│   │   ├── Jobs/                     # resets și documente, runner EspoCRM
│   │   └── Resources/
│   │       ├── module.json
│   │       ├── routes.json
│   │       ├── metadata/
│   │       │   ├── entityDefs/
│   │       │   ├── scopes/
│   │       │   ├── clientDefs/
│   │       │   ├── entityAcl/
│   │       │   ├── aclDefs/          # numai definiții recunoscute de framework
│   │       │   ├── recordDefs/
│   │       │   ├── selectDefs/       # numai dacă există filtre suplimentare
│   │       │   ├── dashlets/
│   │       │   └── app/             # navbar, routes frontend, adminPanel, config, jobs, ACL
│   │       ├── layouts/
│   │       ├── i18n/ro_RO/
│   │       ├── i18n/en_US/
│   │       └── templates/holiday-request.docx
│   └── client/custom/modules/employee-management/
│       ├── src/controllers/         # Attendance, overview-uri și administrarea delegată
│       ├── src/views/
│       │   ├── administration/
│       │   ├── attendance/
│       │   ├── holiday-request/
│       │   ├── holiday-overview/
│       │   ├── fields/
│       │   └── dashlets/
│       ├── src/handlers/            # acțiuni native de record unde sunt suficiente
│       ├── res/templates/
│       └── css/                     # stiluri locale, fără modificări de temă/core
└── tests/
    ├── unit/
    ├── contract/
    ├── integration/
    ├── concurrency/
    ├── lifecycle/
    └── browser/
```

`Controllers/` backend suplimentare se păstrează numai dacă mai au o responsabilitate neacoperită de controllerul Record și API actions. Controllerul Holiday actual care restrânge necondiționat lista la utilizatorul curent nu trebuie să devină un substitut pentru ACL. Lista „cererile mele” va folosi filtre native de listă; ACL controlează toate căile de citire.

Nu adăugăm `Binding.php`, repositories sau clase Entity goale doar pentru uniformitate. Folosim DI automată și repository-ul generic unde sunt suficiente. API-urile de comenzi pot avea prefix `/EmployeeManagement/...`; URL-urile concrete se fixează după aprobarea arhitecturii. Nu extindem Record Service pentru logica obișnuită de business: chiar `Espo\Services\Record` recomandă compoziția prin `recordDefs`, iar metodele CRUD core nu sunt prezentate drept API de personalizare. Excepția îngustă poate fi serviciul de compatibilitate Calendar, dacă funcția sa verificată mai este necesară.

## 4. Modelul entităților și relațiilor

### 4.1. Model minim recomandat

| Entitate | Rol și relații | Invarianti și metadata |
| --- | --- | --- |
| `User` nativ | Identitatea angajatului și a managerului regular; autorii operațiilor sunt tot User | Populație operațională = regular activi, niciodată admin/API/portal/system; administratorul gestionează exclusiv platforma și nu primește profil/sold/pontaj |
| `AttendanceRecord` | `assignedUser` → User; o înregistrare pe angajat/zi | Index unic `(assignedUserId, date)`; status efectiv introdus manual, source, actor și timestamps; API writes numai prin operațiile Attendance |
| `AttendanceWorkSchedule` | `assignedUser` → User; exclusiv metadatele specifice pontajului, precum remote și CIC/CCC, cu valabilitate | Nu mai deține ore/zile normative sau editor de programe; nu creează și nu atribuie WTC. Eventualele snapshot-uri istorice sunt date derivate read-only, nu o configurație concurentă |
| `HolidayProfile` | `assignedUser` → User; `holidayRequests` și `ledgerEntries` hasMany | Un profil per angajat, index unic; entitlement, sold, reset; soldul nu este editabil prin CRUD generic |
| `HolidayRequest` | Event; `assignedUser` → User, `profile` → HolidayProfile; autori ai deciziilor → User; documente → Attachment | Dates all-day, status, zile rezervate, revizie, snapshot de calcul și aprobare; index pe angajat/perioadă; ownership nativ |
| `HolidayLedger` | `profile` → HolidayProfile, `assignedUser` → beneficiar, `request` → HolidayRequest, `actor` → User | Append-only, cheie unică de idempotency, delta și before/after; fără update/delete API; nu înlocuiește auditul celorlalte entități |
| `EmployeeAbsence` | Event minimal derivat din HolidayRequest; `assignedUser` → User | Numele angajatului și intervalul, fără motive, sold, documente sau statut intern; sursă unică internă neexpusă; fără CRUD de utilizator |
| `Attachment`, `Job`, `ScheduledJob`, audit nativ | Infrastructură EspoCRM reutilizată | Fără tabele proprii care reproduc aceste funcții |
| `ZileLibere` externă | Citită prin contractul public ZileSarbatoare | Nu este copiată și nu este modificată de Employee Management |
| `WorkingTimeCalendar` / `WorkingTimeRange` native | Norma și excepțiile, citite prin asocierea WTC nativă a angajatului | Create/configurate/atribuite de administratorul EspoCRM; numai citire din Employee Management, fără entitate de program alternativă |

**Selecția angajaților:** toate listele operaționale, inițializările de profil, beneficiarii comenzilor, matricile, reminderele, Calendarul de absențe Employee Management, calculele de zile/ore, numărătorile, statisticile și exporturile folosesc aceeași eligibilitate regular/activ. Managerii sunt și ei angajați eligibili; administratorii tehnici sunt excluși chiar dacă au un rol Employee Management, un ID transmis direct sau date reziduale. Pentru istoric se păstrează regulile deja aprobate de arhivare/înghețare și păstrarea datelor, fără ștergere ori recalculare retroactivă automată prin această actualizare.

**Beneficiarul se validează separat de actor:** existența unui profil sau a unui User nu dovedește eligibilitatea. Nu se creează profil/sold, cerere, pontaj ori absență pentru un administrator; nu este oferit ca angajat sau aprobator în selectoarele extensiei. `User.workingTimeCalendar` este relația nativă administrată tehnic; Employee Management o citește, dar nu o dublează printr-o asociere editabilă proprie.

**Ownership:** normalizăm câmpurile actuale `user` la `assignedUser` în entitățile centrate pe angajat, inclusiv Profile și Ledger. `createdBy`/`modifiedBy` reprezintă autorul, nu beneficiarul. Astfel folosim `own` nativ, inclusiv filtrarea listelor, fără checker de ownership și filtru SQL diferite pentru fiecare entitate. Nu păstrăm două linkuri User sinonime.

Alternativa este păstrarea lui `user` plus implementări native de ownership/select adaptate. Este validă, dar adaugă cod fără beneficiu de migrare, deoarece nu există date de producție. `aclDefs.readOwnerUserField` nu trebuie presupus ca înlocuitor universal pentru toate verificările de ownership și toate operațiile.

Entity type-urile actuale pot rămâne pentru a reduce schimbările de UI/teste. Namespace-ul PHP nu izolează numele entităților: prefixarea lor cu un prefix de produs/organizație este alternativa mai sigură pentru distribuție publică. Decizia numelor definitive se ia **înainte** de primul release cu date reale, nu ulterior.

### 4.2. Relații și integritatea datelor

```text
User
├── AttendanceRecord [1:N, angajat + zi unic]
├── workingTimeCalendar → WorkingTimeCalendar [relație nativă, administrată tehnic]
├── AttendanceWorkSchedule [1:N, numai metadate remote/CIC/CCC cu valabilitate]
└── HolidayProfile [1:1]
    ├── HolidayLedger [1:N, append-only]
    └── HolidayRequest [1:N]
        ├── Attachment [0:N, câmp approvalDocuments]
        └── EmployeeAbsence [0:1, proiecție pentru Calendarul comun]
```

Relațiile sunt declarate în `entityDefs.links`; câmpurile link sunt consecvente cu relațiile, iar inversul se declară când este efectiv folosit. Nu presupunem existența unor foreign keys sau cascades SQL pe care metadata nu le garantează. Nu permitem ștergerea fizică a profilului, ledger-ului ori cererilor finalizate prin API; dezactivarea unui User nu șterge istoricul.

Cheile unice rămân ultima protecție împotriva duplicării; validările din servicii produc mesaje explicite. Relațiile beneficiar/profil/sursă nu sunt reatribuibile prin link/unlink endpoints. Testăm explicit aceste endpoint-uri, nu doar PUT-ul recordului.

Pentru istoricul operațional păstrăm protecțiile de ștergere și contabilitate prevăzute anterior. Citirea WTC și eventualele snapshot-uri normative trebuie să respecte istoricul și înghețarea lunară aprobate; nu introducem prin aceasta un sistem propriu de versionare/editare a programelor. Corecția managerului într-o lună înghețată este o operație explicită și auditată, nu o recalculare generală ori dezghețare implicită a lunii.

### 4.3. Stări și efecte contabile

| Stare HolidayRequest | Sold | Blochează altă rezervare pe interval | Absență efectivă în Attendance | Calendar comun recomandat |
| --- | --- | --- | --- | --- |
| `Pending` | Zile rezervate | Da | Nu | Nu; cererea proprie rămâne vizibilă în zona privată |
| `ApprovalProcessing` | Aceeași rezervare, fără debit nou; eroarea/retry nu o restituie automat | Da | Nu | Nu, până la Approved definitiv |
| `Approved` | Rezervarea rămâne consumată | Da | Da | Da |
| `CancellationPending` | Fără restituire încă | Da | **Da** | **Da**, fără publicarea motivului anulării |
| `Rejected` | Restituire o singură dată | Nu | Nu | Nu |
| `Cancelled` | Restituire o singură dată | Nu | Nu | Nu |

Retragerea unei cereri Pending trebuie să păstreze o urmă verificabilă. Recomand retragere cu istoric/soft-delete și restituire atomică, nu ștergere fizică. Nu este obligatorie introducerea unui status suplimentar `Withdrawn`; alegerea terminologiei se aprobă înaintea implementării.

Tranzițiile acceptate sunt explicite: Pending → ApprovalProcessing → Approved; Pending → Rejected/retragere; Approved → CancellationPending/Cancelled excepțional; CancellationPending → Cancelled sau Approved dacă anularea este respinsă. Eșecul generării păstrează starea intermediară și eroarea recuperabilă; retry-ul aceleiași revizii nu creează o rezervare, debit sau intrare ledger suplimentară. Nu se permite rescrierea datei unei cereri aprobate ca simplă editare. Pentru reprogramare folosim anulare și o nouă cerere, până când există o cerință justificată pentru amendamente.

## 5. Permisiuni și administrare delegată

### 5.1. Principiul de autorizare

Roles și ACL native sunt unica sursă a drepturilor configurabile, create și atribuite exclusiv de administratorul tehnic. Scopes boolean sunt potrivite pentru comenzi care nu corespund unei acțiuni CRUD, iar ACL de entitate controlează citirea și operațiile pe înregistrări. Autorizația finală este intersecția dintre **eligibilitatea actorului pentru business**, dreptul nativ și invariantul operației; dreptul de edit nu permite aprobarea unui concediu prin PUT status. Eligibilitatea nu este un sistem paralel de permisiuni: administratorul tehnic nu participă domeniului, chiar dacă ACL-ul său nativ este permisiv.

Nu livrăm `mandatory` care acordă Calendar sau `HolidayRequest.read=all`. Default-urile de lipsă de acces folosesc `app.acl.strictDefault`, iar drepturile se acordă explicit prin Roles. Nu atribuim automat roluri utilizatorilor existenți la instalare.

Configurația unei liste de una/două persoane poate rămâne o regulă de rutare/semnătură dacă este cerută, dar **nu este un sistem de autorizare**. Aprobatorul este un manager regular activ cu scope-ul boolean nativ `HolidayApproval`. Dacă se păstrează și o listă de approvers ca regulă de business, persoana trebuie să îndeplinească **ambele** condiții: permisiune nativă și includere în lista de rutare. Niciun ID configurat nu acordă singur drepturi și niciun administrator nu devine aprobator prin ACL-ul global sau prin includerea într-o listă. Managerul autorizat poate aproba și respinge inclusiv propriile cereri, prin același flux, cu aceeași contabilizare și audit.

**Administratorul tehnic EspoCRM** instalează/actualizează/dezinstalează extensii, creează și configurează Roles și permisiuni native, atribuie rolurile utilizatorilor și desemnează managerii, creează/configurează/atribuie WTC și administrează platforma. Nu este operator Employee Management: nu solicită, depune pentru alții, aprobă sau anulează operațional concedii, nu marchează/corectează pontaje, nu inițializează/corectează solduri și nu configurează extensia în locul managerului prin workspace-ul delegat. Nu există fallback de business de forma „dacă este admin, permite”.

**Managerul Employee Management** este un regular activ cu rolurile native corespunzătoare. Poate administra datele și setările specifice extensiei în limita drepturilor acordate, inclusiv toate pontajele, corecții în luni înghețate și aprobarea propriului concediu. Nu primește `isAdmin`, dreptul de a crea/atribui Roles, administrare WTC sau acces la setările globale EspoCRM. Contabilizarea, auditul și celelalte validări rămân obligatorii inclusiv pentru autoaprobare și corecțiile manageriale.

**Limita reală a separării:** nu schimbăm ACL-ul global al administratorului și nu promitem că putem ascunde datele de cineva care controlează platforma, codul, baza de date și backup-urile. Validările extensiei împiedică participarea sa prin fluxurile/API-urile de business și mutațiile protejate ale entităților; nu elimină accesul tehnic nativ de administrare, inspecție sau recuperare. Nu adăugăm un deny global pentru admin și nu transformăm managerii în administratori tehnici.

### 5.2. Scopes operaționale propuse

Numele noi sunt propuneri de metadata, nu facilități preexistente în core.

| Scope | Responsabilitate | Motivul separării |
| --- | --- | --- |
| `Attendance` | Pagina proprie și marcarea propriilor zile, împreună cu ownership | Self-service poate fi acordat/revocat nativ |
| `AttendanceOverview` | Citirea matricii manageriale | Citirea nu acordă automat editare sau configurare |
| `AttendanceManagement` | Gestionarea pontajelor tuturor angajaților, corecții inclusiv în luni înghețate, remindere și metadatele remote/CIC/CCC deja prevăzute | Operații de business distincte de overview; nu acordă administrare sau atribuire WTC |
| `AttendanceSettings` | Politici globale Attendance și inițializare arhivă vizibilă | Putere mai mare decât completarea pontajului |
| `HolidayOverview` | Rapoarte și matrice Holiday în limita datelor autorizate | Nu include automat documente, motive și ledger complet |
| `HolidayRequestManagement` | Depunere în numele altui angajat | Nu include decizie sau corecție de sold |
| `HolidayApproval` | Decizii de aprobare/respingere/anulare | Rol de business configurabil în EspoCRM |
| `HolidayProfileManagement` | Citire/inițializare parametri profiluri | Nu acordă corecții ulterioare de sold |
| `HolidayBalanceCorrection` | Corecții justificate, cu ledger și idempotency | Drept contabil separat |
| `HolidayManagementSettings` | Setări globale Holiday | Nu acordă automat aprobare sau modificări contabile |
| `EmployeeAdministration` | Intrarea în workspace-ul delegat | Poartă de navigare comună; nu acordă drepturile secțiunilor |

**Clarificare autorizată pentru 0.7.2 (raportul lunar PDF):** managerul cu AttendanceManagement, overview și read all/FLS poate descărca raportul lunar fără permisiunea globală Export. Aceasta este o autorizare limitată la raportul de pontaj, prin Roles native; nu acordă Export generic. Viewer-ul fără management păstrează cerința Export=yes. Dezactivarea explicită globală a exporturilor rămâne respectată. Cerința de completitudine este separată și controlată de Allow incomplete exports.

Nu introducem câte un scope pentru fiecare buton. Exportul generic folosește dreptul de citire a datelor și permisiunea nativă de export. Pentru raportul lunar PDF se aplică explicit clarificarea 0.7.2 de mai sus; nu există grant global suplimentar. Existența unui scope boolean nu trebuie să permită citirea nefiltrată a entităților protejate. Eliminăm propunerea `AttendanceScheduleManagement` ca drept de administrare a programului: nu există scope, endpoint sau interfață Employee Management pentru creare, editare ori atribuire WTC. Metadatele remote/CIC/CCC rămân operații Attendance, fără schimbarea regulilor lor de business.

### 5.3. Matrice de roluri și responsabilități

Sunt trei responsabilități, nu tipuri de utilizator noi: angajat regular, manager regular cu roluri native și administrator tehnic EspoCRM. Managerul este și angajat; nu introducem funcții sau roluri HR suplimentare. Setul efectiv de scopes se atribuie nativ și poate limita operațiile managerului.

| Operație | Angajat regular | Manager Employee Management, regular activ | Administrator tehnic EspoCRM |
| --- | --- | --- | --- |
| Apariție în pontaj/liste/calcule/statistici/rapoarte | Da, conform regulilor existente | Da, ca angajat | **Nu**, nici ca rând, beneficiar sau element al totalurilor |
| Profil și sold de concediu | Da, după inițializare | Da, ca angajat | **Nu** |
| Citire/marcare pontaj propriu | Da, în fereastra permisă | Da, ca angajat | **Nu** |
| Gestionare/corectare pontaje ale tuturor angajaților | Nu | Da, cu `AttendanceManagement`; inclusiv în lunile înghețate, cu audit | **Nu**, inclusiv prin API |
| Modificare metadate remote/CIC/CCC | Nu | Cu dreptul Attendance corespunzător, fără schimbarea regulilor aprobate | Nu ca operator al extensiei |
| Citire rapoarte/extensie și exporturi | Numai funcțiile proprii acordate | Cu scopes de overview/read și permisiunea nativă de export | Nu ca operator al extensiei; inspecția tehnică nativă nu este eliminată |
| Creare/editare cerere proprie Pending | Da, conform regulilor aprobate | Da, ca angajat | **Nu** |
| Depunere pentru alt angajat | Nu | Cu `HolidayRequestManagement`, numai pentru beneficiari eligibili | **Nu** |
| Aprobare/respingere concedii | Nu | Cu `HolidayApproval`, inclusiv **propriile cereri**, prin același flux auditat | **Nu**, chiar dacă ACL global permite accesul |
| Solicitare anulare/anulare finală | Propria cerere eligibilă | Solicitare proprie sau decizie autorizată, conform fluxului deja prevăzut | **Nu** |
| Sold și istoric propriu | Cu read/own | Ca angajat | Nu are profil/sold propriu |
| Inițializare/administrare profiluri | Nu | Cu `HolidayProfileManagement`, numai pentru angajați eligibili | **Nu** ca operație de business |
| Corecții sold | Nu | Cu `HolidayBalanceCorrection`, motiv și ledger | **Nu** ca operație de business |
| Setări specifice Employee Management | Nu | `EmployeeAdministration` + scopes ale secțiunilor | Nu prin workspace-ul sau comenzile delegate |
| Documente/motive private ale colegilor | Nu | Numai cu read autorizat pe cererea-sursă, nu doar cu drept de overview | Nu printr-un rol operațional; accesul tehnic nativ rămâne intact |
| Calendar minimal absențe colegi | Cu read pe `EmployeeAbsence` și Calendar | La fel | Nu este beneficiar și nu primește absență; Calendarul global nativ nu este restricționat prin această regulă |
| Creare/configurare/atribuire WTC | Nu | **Nu** prin Employee Management | **Da**, exclusiv prin infrastructura nativă |
| Creare/configurare/atribuire Roles și permisiuni | Nu | **Nu** | **Da**, inclusiv desemnarea managerilor |
| Instalare/actualizare/dezinstalare extensii și administrare globală | Nu | **Nu** | **Da** |

Autoaprobarea managerului autorizat este **permisă conform cerinței**, nu un override tehnic și nu o excepție care cere un al doilea aprobator. Se păstrează toate validările de stare, sold, date, suprapunere, concurență și audit. Autorul cererii și decidentul pot fi același regular; niciunul nu poate fi administrator tehnic.

Luna înghețată rămâne înghețată pentru angajat. Managerul cu dreptul de corecție poate interveni explicit și auditat, fără a transforma luna în editabilă pentru ceilalți și fără dezghețare ori recalculare automată. Nu schimbăm în această actualizare momentul sau mecanismul înghețării.

### 5.4. ACL de entitate și protecția API

| Entitate/API | Politică recomandată |
| --- | --- |
| `HolidayRequest` | Employee: create yes, read own, edit own pentru Pending; fără ștergere fizică. Aprobator: read all în aria aprobată, decizie numai prin comandă. Managerul care depune pentru coleg folosește comanda dedicată, nu assignment arbitrar. Nu există filtrare forțată „own” pentru toți în controller. |
| `AttendanceRecord` | Read own pentru angajat, all pentru managerul autorizat; create/edit/delete generice blocate. Comenzile de marcaj verifică scope, beneficiar, perioadă, absențe și invarianti. |
| `AttendanceWorkSchedule` | Numai metadate Attendance și date istorice derivate, fără CRUD generic necontrolat și fără editarea/atribuirea normei WTC. |
| `WorkingTimeCalendar` / `WorkingTimeRange` / `User.workingTimeCalendar` | Employee Management numai citește norma. Nu expune API sau formular de creare/editare/atribuire; administrarea rămâne în mecanismele native, la administratorul tehnic. |
| `HolidayProfile` / `HolidayLedger` | Read own/all după rol; scrieri numai prin operațiile contabile. Nicio editare de `balance`, `actor`, `assignedUser`, `delta` sau idempotency din payload arbitrar. |
| `EmployeeAbsence` | Read all pentru rolul care vede absențele comune; no create/edit/delete prin API. Nicio relație accesibilă public care oferă documente sau detalii din HolidayRequest. |
| Setări delegate | `EmployeeAdministration` + scope-ul secțiunii; numai câmpurile allowlist; validare tip/range/User și drepturi pe server. |
| Endpoint-uri agregate | Nu returnează entități ORM serializate integral. Selectează explicit câmpurile, aplică nivelul own/all și ACL de câmp relevante; dacă nu au un dataset minimal autorizat separat, cer read pe entitatea-sursă. |
| Download/export | Aceleași filtre și ACL ca citirea sursei; se verifică și accesul la ID-uri transmise direct. Fără autorizare exclusiv prin ascunderea butonului. |

Record hooks protejează acțiunile CRUD generice; restricțiile de câmp/link și, când sunt necesare, link checkers native protejează și endpoint-urile de relationships. Guards de business nu sunt înlocuite de `readOnly` sau `createDisabled` frontend. ORM-ul nu aplică automat ACL-ul Record Service, deci orice API action care îl folosește verifică explicit dreptul și aria de date.

**Validări backend obligatorii pentru separarea responsabilităților:**

1. Pentru orice operație interactivă de business, actorul se obține din contextul autentificat al serverului și trebuie să fie **regular activ, nu admin**. Nu acceptăm `actorId`, tipul utilizatorului sau un flag de autorizare din payload drept dovadă. Verificarea ACL nativă favorabilă unui administrator nu anulează această condiție.
2. Pentru operațiile manageriale verificăm suplimentar scope-ul relevant. Nu folosim `isAdmin()` ca ramură de permitere. Pentru propriul concediu, un manager cu `HolidayApproval` trece aceeași verificare și nu este respins fiindcă este și solicitant.
3. Beneficiarul fiecărui marcaj, profil, sold, cereri sau corecții trebuie să îndeplinească eligibilitatea de angajat. Validarea se aplică și la ID-uri introduse direct, bulk initialization, import/acțiuni generice și schimbări de link; un admin nu poate fi țintă nici când actorul este un manager valid.
4. Aplicăm verificările la intrarea în servicii/API și guards pe mutațiile entităților proprii prin mecanismele native existente, inclusiv CRUD/link endpoints. Starea și eligibilitatea relevante se reverifică în tranzacția autoritativă §6.2. Ascunderea meniului sau filtrarea selectorului nu sunt suficiente.
5. API-urile operaționale de listare/raportare/export și workspace-ul sunt pentru utilizatorii regular autorizați, iar toate dataset-urile exclud administratorii ca angajați. Nu încercăm prin aceste guards să rescriem drepturile native ale administratorului asupra infrastructurii sau să blocăm global lectura tehnică a datelor.
6. Joburile automate deja prevăzute rulează în contextul intern legitim și aplică aceeași selecție a beneficiarilor regular eligibili; nu inițializează/grantează solduri pentru administratori și nu îi aleg ca aprobatori operaționali pe baza ACL global. Nu există flag API de tip „system” sau „skipBusinessEligibility” care să transforme o cerere interactivă în operație internă.

Nu creăm un sistem de permisiuni nou pentru aceste validări și nici un bypass general pentru joburi. Lifecycle-ul tehnic al pachetului și administrarea nativă WTC/Roles nu trec prin operațiile de angajat/manager ale extensiei.

**Aria managerilor:** conform cerinței, managerii cu drepturile corespunzătoare gestionează **toți angajații eligibili**, nu doar o echipă. Nu afișăm nivelul `team` în ACL fără relație `teams` reală și nu introducem prin această actualizare segregare pe echipe. Atribuirea unui rol unui Team este un mecanism nativ de acordare a capabilităților, nu un tabel propriu „manager–angajați”. Administratorii rămân excluși indiferent de rol sau echipă.

**API/portal/system:** portal dezactivat pentru domeniu. API users nu primesc granturi implicite; în prima versiune recomand excluderea comenzilor de angajat/decizie pentru API users, până la aprobarea cazurilor de integrare. Acordările automate rulează ca job intern, nu prin impersonarea unui manager. Conturile inactive sunt respinse pentru operații interactive, fără ștergerea istoricului.

### 5.5. Meniul din dreapta sus și workspace-ul comun

1. O intrare unică `Employee Management` în `app.clientNavbar.menuItems`, cu link `#EmployeeAdministration`, icon și traduceri native.
2. `accessDataList` verifică `EmployeeAdministration`. Controllerul workspace-ului și API-urile verifică suplimentar că actorul este regular activ, nu administrator tehnic, apoi drepturile secțiunii. Rolurile delegate includ explicit dreptul de intrare împreună cu drepturile operaționale.
3. Secțiunile Attendance Settings, metadate Attendance remote/CIC/CCC, Holiday Settings, Profiles și Balance Corrections apar numai când dreptul lor este prezent. Nu includ editor de program normativ, WTC, Roles, permisiuni globale sau Extension Manager. Accesul direct prin URL și API verifică aceleași condiții.
4. `#Admin` rămâne neschimbat, pentru administratorul tehnic. Eliminăm recomandarea unui link `app.adminPanel` către workspace-ul operațional pentru administratori; Employee Management nu îi oferă un rol de manager implicit.
5. Refolosim views, modele, field views, date/time selectors, layouts, modaluri și stilurile native. UI specific se păstrează doar pentru matrice, profiluri/bulk operations și gruparea secțiunilor.
6. Setările delegate sunt citite/scrise prin API-uri mici allowlist și `ConfigWriter`. Nu oferim modelului delegat acces la întregul endpoint `/Settings` și nu relaxăm controllerul global Admin.

**Detaliu verificat:** `Utils.checkAccessDataList` din 10.0.10 aplică lista cumulativ, nu ca OR. Nu enumerăm toate drepturile secțiunilor în acea listă, deoarece ar cere toate permisiunile simultan. Scope-ul comun este cea mai simplă soluție nativă; costul său este configurarea explicită a unui drept de intrare. Alternativa fără acest drept ar fi un item view custom care calculează OR din ACL, dar nu este recomandată inițial.

**Limită de vizibilitate:** nu presupunem că `accessDataList` exclude administratorul, deoarece ACL-ul său nativ poate permite scope-ul. Chiar dacă un link rămâne vizibil, accesul la workspace și comenzile sale este refuzat de validarea de business. Nu modificăm navbar-ul global sau ACL-ul admin pentru a obține doar o ascundere cosmetică și nu inventăm o cheie metadata „non-admin only”.

Nu avem MutationObserver global, manipularea meniului Admin sau „fallback” care introduce o a doua intrare de administrare.

### 5.6. Documente și date sensibile

`approvalDocuments` rămâne câmp Attachment nativ pe cererea privată. Attachment are parent/targetField corecte; descărcarea folosește mecanismele native. Accesul la absența publică nu este acces la părinte.

Implementarea native Attachment verifică părintele/câmpul și poate avea fallback la ownership-ul atașamentului. De aceea testăm și `createdBy`/`assignedUser` ale atașamentului, download prin ID, preview și legături indirecte; nu declarăm protecția completă doar pentru că parent este setat. Un manager care nu are read pe cerere nu primește read pe fișier prin faptul că a declanșat o operație secundară.

Motivele medicale, anulările și soldurile nu intră în Calendarul comun, loguri publice sau payload-uri de navbar. Configurațiile globale rămân la nivel admin în `app.config`; API-ul delegat expune numai subsetul autorizat.

## 6. Integrarea Attendance–Holiday–ZileSarbatoare

### 6.1. O singură semantică pentru absența efectivă

Holiday deține cererea și soldul; Attendance doar citește absența efectivă. Semantica este aceeași în pagina proprie, matrice, completitudine, remindere și exporturi: **Approved și CancellationPending sunt absențe active**.

Nu creăm un AttendanceRecord „Holiday” pentru fiecare zi de concediu și nu ștergem automat marcajele manuale sub o absență. Absența este o suprapunere calculată din sursa Holiday. La anularea finală dispare suprapunerea și sunt reevaluate înregistrările existente, fără a inventa automat prezență.

Provider-ul actual poate fi păstrat/refactorizat ca funcție de citire a unei perioade pentru **mai mulți angajați**, nu apelat individual în bucla matricii. Nu avem cache persistent de zile de concediu și nici evenimente asincrone între cele două componente pentru adevărul contabil.

**Precedență propusă, pentru compatibilitate:** clasificăm întâi dacă ziua este normativ lucrătoare; pe zilele relevante concediul activ are prioritate față de CIC, iar acestea au prioritate față de marcajul manual. Excepțiile de business trebuie testate, nu deduse din ordinea accidentală a condițiilor. Calendarul de absențe poate arăta intervalul complet inclusiv weekendul; numărul zilelor contabile exclude zilele nenormative conform politicii aprobate.

### 6.2. Creare, modificare, anulare și concurență

Nu este suficient `transactionalSave: true`: Record Service poate încărca entitatea înaintea tranzacției de salvare, iar două operații pot calcula din aceeași stare veche.

Recomand protocolul următor, fără serviciu generic de locking și fără tabele de locks:

1. Autentificare, verificarea eligibilității actorului regular activ și a dreptului, apoi identificarea beneficiarului eligibil, niciodată admin. Autoaprobarea managerului cu dreptul relevant este permisă. O citire preliminară poate identifica beneficiarul unei cereri, dar nu este sursa deciziei finale.
2. Pornirea tranzacției native **înainte de citirea autoritativă și verificările de stare**.
3. Lock ORM `forUpdate()` pe rândul `User` al beneficiarului; apoi, dacă sunt necesare, HolidayProfile, HolidayRequest și AttendanceRecord. Toate operațiile folosesc această ordine. Pentru operații pe mai mulți angajați, ordinea ID-urilor este stabilă sau procesăm tranzacții independente per angajat.
4. Recitirea și validarea cererii/beneficiarului sub lock, inclusiv status/revizie și eligibilitate curente. Beneficiarul nu poate fi reatribuit. Pentru corecția managerului într-o lună înghețată verificăm dreptul de corecție și păstrăm auditul, fără să aplicăm drepturi de administrator tehnic sau să dezghețăm luna.
5. Verificarea suprapunerilor și a conflictelor de pontaj; calculul zilelor, verificarea soldului, actualizarea cererii/profilului și ledger-ului, audit și proiecția Calendar în aceeași tranzacție.
6. Commit. Efectele lente externe, precum generarea DOCX și notificările neesențiale, nu țin lock-ul de contabilitate deschis.

Lock-ul pe User este **per angajat**, nu global, și există și înainte de inițializarea profilului. Alegerea are un cost: serializarea operațiilor Employee Management pe același angajat și posibilă așteptare a altor actualizări ale acelui User. Tranzacțiile trebuie să fie scurte și testate pe DB real. Alternativa este coordonarea pe HolidayProfile, dar nu acoperă angajații fără profil și nici toate conflictele cross-component fără alte convenții. Nu creăm un tabel de mutex-uri pentru a evita acest cost.

**CRUD standard, fără rescrierea Record Service:** păstrăm controllerul/serviciul nativ, validarea, ACL și restricțiile sale de câmp. Folosim `transactionalSave: true` și un guard ORM autoritativ în `beforeSave`/`beforeRemove`. În 10.0.10 tranzacția repository-ului cuprinde aceste hooks, atât pentru save cât și pentru remove. Guard-ul dobândește lock-urile în ordinea de mai sus, recitește cererea curentă și respinge cu Conflict operația construită dintr-o stare/revizie depășită. Verifică cel puțin status, beneficiar, zile, date și revizia contabilă relevante; nu validează numai `getFetched('status')` și nu salvează peste schimbarea aprobată între timp.

Astfel, citirea preliminară făcută de Record Service poate rămâne în afara tranzacției: **nu este folosită ca adevăr contabil**, iar o stare stale este respinsă înainte de update/delete. Operația și contabilizarea folosesc starea recitită sub lock. Crearea este serializată pe User/profil înainte de verificarea suprapunerilor. API-urile decizie/anulare deschid explicit tranzacția nativă și folosesc același protocol; nu există un al doilea flux de contabilizare în controller.

Record hooks pregătesc defaults/guards fără efecte contabile; hooks ORM apelează serviciul de business responsabil pentru efecte, o singură dată. Efectele care necesită ID-ul noului record pot fi în `afterSave` obișnuit, care este în interiorul tranzacției verificate, nu în `lateAfterSave`, care este ulterior. La implementare se fixează pentru fiecare mutație **un singur owner al efectului contabil**, cu teste care numără înregistrările ledger. `skipHooks`/`silent` nu sunt scurtături în operațiile interactive. Nu deschidem tranzacții într-un record before hook cu speranța că un after hook le va închide: excepțiile intermediare ar face această convenție nesigură.

Operațiile repetate folosesc chei de idempotency și constrângeri unice. O cheie existentă se acceptă ca retry numai dacă reprezintă aceeași operație/țintă, nu un payload diferit. Controlul optimist nativ ajută formularele vechi, dar nici nu serializează două rezervări noi, nici nu înlocuiește verificarea curentă a statusului.

**Conflict concediu–pontaj:** recomand blocarea aprobării dacă există pontaj manual incompatibil în zilele lucrătoare cerute; aprobatorul vede conflictul și un manager îl rezolvă explicit, auditat. Alternativa este păstrarea și mascarea automată a pontajului, ca în afișarea actuală; este mai comodă, dar poate ascunde contradicții. Nu ștergem automat marcaje pentru a face aprobarea să treacă. Decizia D8 stabilește regula finală.

### 6.3. Programul de lucru și Working Time Calendar

EspoCRM **10.0.1** are nativ `WorkingTimeCalendar`, `WorkingTimeRange`, program săptămânal, intervale/excepții, timezone și calendare la nivel User/Team/global. `CalendarUtilityFactory` poate crea utilitare pentru User, Team sau global; `CalendarUtility` oferă `isWorkingDay`, `getWorkingDays`, `getSummedWorkingHours`. Aceste calcule au fost executate practic pentru programe de 4/6/8 ore și intervale separate.

**Decizie: ADOPT WTC**, cu persistența obligatorie a rezultatelor normative istorice read-only demonstrată ca necesară în fezabilitate (88→132 ore pentru aceeași lună trecută după modificarea calendarului). Estimăm aproximativ 15 angajați și maximum patru programe distincte. Nu proiectăm ture, administrare HR sau un sistem suplimentar de calendare pentru acest volum.

| Responsabil | Operații permise în arhitectură |
| --- | --- |
| Administrator tehnic EspoCRM | Creează și configurează calendarele WTC prin interfețele native și atribuie explicit calendarul corespunzător fiecărui angajat prin relația nativă User |
| Manager Employee Management | Semnalează necesarul de program administratorului; nu creează, editează sau atribuie WTC prin extensie și nu primește astfel de permisiuni odată cu rolurile Employee Management |
| Attendance și Holiday | Citesc WTC atribuit, calculează zilele/orele prin API-urile native potrivite și combină rezultatul cu ZileSarbatoare; fără scrieri în WTC, WorkingTimeRange sau User.workingTimeCalendar |

WTC nu este echivalent cu un registru de pontaj sau cu ledger-ul concediilor. Nu presupunem că oferă versiuni istorice imutabile ale programului, remote, CIC/CCC, regula de rezervare sau formatul românesc de export. Datele specifice Employee Management rămân separate, cu regulile de business deja aprobate.

**Granițe obligatorii:**

- nu păstrăm `startTime/endTime` sau zile săptămânale Attendance ca sursă normativă concurentă; nu există editor propriu de ore/programe;
- nu introducem scopes, endpoint-uri sau formulare de delegare a WTC; `#EmployeeAdministration` nu include WTC, iar managerul nu primește edit general pe User;
- nu relaxăm restricția nativă pentru `User.workingTimeCalendar` și nu construim o operație îngustă care s-o ocolească pentru manager; atribuirea este tehnică, în platforma nativă;
- remote și CIC/CCC rămân metadate operaționale Attendance; administrarea lor nu devine administrare WTC și nu modifică norma nativă;
- nu copiem automat concediile ori ZileLibere în WorkingTimeRange și nu sincronizăm două surse editabile. Integrarea aprobată cu ZileSarbatoare rămâne de citire;
- la lipsa unui calendar explicit atribuit ori la o configurație inutilizabilă, extensia semnalează problema de configurare administratorului prin fluxul operațional existent și nu inventează un program L–V sau ore implicite. Istoricul deja înghețat nu este recalculat dintr-un fallback.

**Contractul de implementare și regresie, bazat pe testele de fezabilitate:**

1. Rezolvarea calendarului nativ atribuit fiecărui angajat și citirea consecventă de către ambele componente. Codul nativ `createForUser` poate recurge la Team/global; nu confundăm acel fallback cu cerința unei atribuiri explicite pe angajat și nu îl activăm tacit în Employee Management.
2. Calculul zilelor și orelor pentru cele maximum patru programe, inclusiv intervale multiple într-o zi, zile fără ore și programe diferite între angajați; orele de export provin din WTC sau din snapshot-ul normativ al perioadei, nu din câmpuri configurabile paralele.
3. Separarea normei de disponibilitate: concediul activ al unui User nu trebuie exclus încă o dată din zilele pentru care se calculează debitul de concediu. Nu folosim orbește `createForUser` dacă include indisponibilități personale; alegem calendarul normativ atribuit prin `CalendarFactory::create(WorkingTimeCalendar)` și utilitarul aferent când semantica o cere.
4. Integrarea ZileSarbatoare și precedența deja prevăzută pentru sărbători/excepții lucrătoare. Nu se schimbă regulile aprobate printr-un simplu OR între surse și nu se dublează excluderile.
5. Date de business de tip `date`, timezone-ul aprobat, DST și capetele inclusive/exclusive. Metodele WTC primesc obiecte native `Espo\Core\Field\DateTime`, nu stringuri sau un `DateTimeImmutable` presupus compatibil. Concediile rămân în zile întregi; testarea intervalelor parțiale nu introduce half-day bookings.
6. Păstrarea istoricului când administratorul modifică WTC sau îl reatribuie unui angajat: lunile înghețate și concediile deja contabilizate nu se rescriu implicit. Un WTC editabil și asocierea curentă de pe User nu certifică singure istoricul programului.

Snapshot-urile normative necesare istoricului sunt **date derivate, read-only**, nu calendare configurabile suplimentare. Detaliile lor tehnice se validează fără a modifica regulile deja aprobate de înghețare sau dreptul managerului de corecție auditată. Nu presupunem că WTC oferă nativ istoric imutabil și nu obligăm administratorul să opereze un nou modul de versionare Employee Management.

Fezabilitatea calculului și separarea normei de disponibilitatea personală sunt demonstrate pe 10.0.1. Rămâne de implementat și verificat produsul: rezultatele istorice se fixează pentru lunile înghețate și calculele Holiday contabilizate; nu se regenerează din WTC-ul curent. Corecția managerială a pontajului înghețat rămâne explicită și auditată, fără alterarea automată a normei istorice. Nu păstrăm editorul custom ca fallback. Timezone-ul business se tratează explicit: instalația existentă este UTC, probele au folosit Europe/Bucharest; pachetul nu schimbă timezone-ul global.

Referință oficială: [Working Time Calendar](https://docs.espocrm.com/user-guide/working-time-calendar/); detaliile API sunt confirmate în sursa 10.0.10 din anexa A.

### 6.4. Contractul cu ZileSarbatoare

Recomand dependență explicită de **Zile Sărbătoare 0.9.5 sau o versiune ulterioară validată**, nu fallback tăcut la „fără sărbători”. În manifest folosim cheia nativă `dependencies` cu numele real al extensiei, **`Zile Sărbătoare`**, nu namespace-ul PHP și nici Package ID din descriere. Plaja exactă se fixează prin testele release-ului.

Attendance și Holiday nu mai cunosc structura internă `ZileLibere`. Injectăm contractul existent:

`Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereCalendar`.

Pentru perioade folosim `getDateLiberePentruLuni(year, months, countryCode)`, pe anii/lunile necesare și filtrăm intervalul cerut. Evităm `esteZiLibera` într-o buclă de 31 × N angajați. Contractul citește datele deja stocate și nu face HTTP. Nu importăm Nager.Date din cererea de pontaj sau concediu.

Disponibilitatea extensiei nu garantează că anii necesari au fost sincronizați. Contractul public întoarce date, dar nu certifică acoperirea unui an. Nu inventăm `isYearSynchronized`. Prima versiune are o verificare de pregătire operațională și avertizare explicită pentru perioade viitoare; o certificare automată necesită o extensie reală și aprobată a contractului ZileSarbatoare. Nu interpretăm o listă goală drept „sincronizare validă fără sărbători”.

Alternativa dependenței opționale este posibilă, dar cere un mod fără ZileSarbatoare asumat explicit de administrator, nu activat accidental când metadata lipsește. Recomand dependență obligatorie pentru politica RO actuală.

### 6.5. Calendar fără conflicte globale

Sunt două probleme distincte: **confidențialitatea datelor** și **coliziunea view-ului**.

#### A. Vizibilitatea cererii față de vizibilitatea absenței

| Alternativă | Beneficiu | Limitare | Recomandare |
| --- | --- | --- | --- |
| Doar HolidayRequest, read own/aprobator | Cel mai simplu, fără proiecție | Angajatul nu vede concediile colegilor în Calendarul nativ | Alegeți dacă renunțăm la funcția comună |
| HolidayRequest read all + field ACL | Mai puține entități | ACL de câmp pe rol nu oferă automat „câmp privat doar pe propriul record”; riscul expunerii documentelor și cererilor Pending persistă | Nerecomandată ca politică implicită |
| `EmployeeAbsence` minimală, Event nativ, separată de cerere | Păstrează absențele comune și ACL nativ pentru ambele seturi; click-ul deschide detaliu minimal sigur | O entitate derivată și sincronizare tranzacțională | **Recomandată dacă păstrăm Calendarul comun** |

Proiecția nu este un ledger suplimentar sau un nou sistem de calendar. Conține numai informația publicabilă; se creează/actualizează/dezactivează în tranzacția tranziției Holiday, are o sursă unică și poate fi reconstruită controlat din cereri. Attendance și soldurile continuă să citească sursa Holiday, nu proiecția. Datele tehnice de legătură cu sursa nu sunt expuse în layout, payload, filtre, relationships sau export public; folosim restricțiile native adecvate, nu doar ascunderea câmpului.

Regula aprobată: `EmployeeAbsence` afișează Approved/CancellationPending, iar sursa Calendar `HolidayRequest` afișează numai Pending proprii sau autorizate aprobatorului. Cererile Approved/CancellationPending rămân accesibile în lista/detail-ul privat, dar nu se mai desenează a doua oară ca request în Calendar. Testăm Calendar propriu, comun și al altui User; calendarul comun afișează exclusiv numele și intervalul, fără Pending sau ApprovalProcessing și fără acces implicit la cererea privată.

#### B. Ownership-ul frontend Calendar

`clientDefs.Calendar.calendarView` este o valoare scalară, nu o listă de extensii. În codul actual Holiday înlocuiește view-ul ZileSarbatoare, fiind încărcat după el, și extinde direct `crm:views/calendar/calendar`. Simplul schimb al ordinii modulelor nu rezolvă problema.

**Preferință:** eliminăm override-ul Calendar din Employee Management dacă modelul Event, numele corect, culoarea și acțiunile native ale recordului satisfac UX-ul. ZileSarbatoare rămâne owner-ul view-ului care decorează zilele nelucrătoare. Acțiunile de aprobare apar pe record/detail/modal prin metadata/acțiuni native, nu prin rescrierea Calendarului.

**Alternativa controlată:** dacă prototipul demonstrează nevoia unui refresh sau comportament Calendar suplimentar, există un singur override final Employee Management, care extinde explicit view-ul ZileSarbatoare, păstrează `super` și listener cleanup, cu ordine de modul documentată și dependență declarată. Lanțul de moștenire este intenționat, nu doi înlocuitori concurenți. Nu modificăm ZileSarbatoare în această etapă și nu presupunem existența unui `calendarDecorators` API.

În 10.0.10, backend-ul Calendar acceptă `Services/{Entity}::getCalenderQuery` ca mecanism de compatibilitate; ortografia este chiar a core-ului. Este disponibil și poate fi păstrat îngust dacă afișarea comună o cere. Aplică strict ACL pe entitatea afișată, nu `skipAcl` pentru a ocoli read own. Query-ul generic nativ filtrează după utilizatorul calendarului; nu presupunem că va afișa singur toate absențele colegilor.

Metode Calendar frontend precum `addModel`/`updateModel` sunt marcate private în core. Nu bazăm arhitectura nouă pe suprascrierea lor. Dacă sunt inevitabile limitări de refresh, le documentăm și testăm pe versiunea exactă, înainte de a promite refresh automat. FullCalendar direct, monkey-patching-ul și încă un observer global nu sunt soluția implicită.

### 6.6. Istoric, calcul și documente

Snapshot-ul rezervării păstrează zilele contabile și distribuția lor pe luni, calendarul/regula relevantă și versiunea calculului, în câmpuri native JSON read-only pe cerere. La editarea unei cereri Pending se recalculează explicit și se înregistrează revizia/delta. La aprobare, recomand păstrarea calculului rezervat; schimbările ulterioare ZileLibere nu modifică tacit soldul sau documentul.

Snapshot-ul aprobării include datele care trebuie să rămână fixe în DOCX: numele beneficiarului, decidentul, semnăturile/titlurile, datele, zilele și valorile contabile relevante. Un retry nu citește numele, entitlement-ul sau configurația de semnături actuale pentru a rescrie o decizie veche.

Alternativa este recalcularea la aprobare cu reconciliere de sold, afișarea diferenței și confirmare explicită. Este validă, dar mai complexă; nu alegem eroarea generică „totalurile lunare nu coincid” drept politică.

**Documente, decizie aprobată:** în tranzacția de inițiere salvăm snapshot-ul, `ApprovalProcessing`, starea `Pending` a generării și programăm Job-ul nativ cu cheie de operație și revizie. Rezervarea existentă rămâne neschimbată. `JobSchedulerFactory` persistă prin același EntityManager; participarea Job-ului la rollback a fost demonstrată pe 10.0.1. Nu utilizăm un hook `afterCommit` presupus existent.

Jobul generează fișierele din snapshot în afara lock-ului contabil lung, persistă Attachment/FileStorage și, sub o tranzacție scurtă cu lock și reverificarea reviziei, finalizează **Approved numai dacă toate documentele există**. În acea finalizare publică absența efectivă; nu debitează din nou rezervarea și nu dublează ledger-ul. Generarea/finalizarea sunt idempotente pe cerere/revizie/lună; `JobScheduler` nu oferă automat această unicitate de business. Documentele sunt accesibile prin ACL-ul cererii private imediat după finalizare.

Folosim grupuri native de Job pentru serializarea generărilor aceleiași cereri, plus verificări de stare și rezultate existente. Erorile au stare vizibilă și retry controlat; nu presupunem retry infinit sau exact-once nativ. La anulare păstrăm documentele ca istoric, nu le mai prezentăm ca aprobare activă. Întreruperile dintre scrierea fișierului și commit cer cleanup compensator și verificare operațională; nici varianta async nu face storage-ul tranzacțional.

**Recuperare obligatorie:** păstrăm starea operației, Job ID, revizia/cheia, încercările și eroarea în date persistente. Un Job Failed după pierderea worker-ului nu repară automat `Processing` (demonstrat; aproximativ 300 secunde în configurația testată). Reconcilierea verifică Job-ul și rezultatele persistate, apoi permite reprogramarea controlată a aceleiași operații; nu pornește o a doua aprobare și nu presupune timeout egal cu pierderea definitivă a unui worker încă activ. Managerul vede/reia numai operația de business autorizată, nu primește administrare globală Jobs. Nu mai propunem alternativa sincronă pentru prima versiune.

Audităm nativ câmpurile relevante Attendance, program, cerere și parametri de profil: status, date, ore relevante, remote/CIC, source, autor/reason de corecție. Adăugăm `createdBy`/`modifiedBy` nativ unde lipsesc. Ledger-ul rămâne sursa tranzacțiilor de sold; nu îl folosim ca substitut pentru toate modificările de pontaj. La joburi, autorul tehnic poate fi system, iar actorul de business al deciziei rămâne explicit în cerere/snapshot.

O arhivă care se recalculează din setările curente nu este un registru istoric imutabil. Necesitatea snapshot-urilor normative read-only este demonstrată și aprobată. Respectăm înghețarea lunară: schimbarea tehnică a WTC nu rescrie o lună înghețată, iar corecția explicită a managerului rămâne permisă și auditată, fără recalcularea automată a normei; vezi D9.

## 7. Rezolvarea explicită a auditului P1–P11

Convenție pentru dovezi: `A/` = `AttendanceManagement/`, `H/` = `HolidayManagement/`, `Z/` = `ZileSarbatoare/`. Pentru PHP/metadata, `M/` înseamnă `files/custom/Espo/Modules/{modulul extensiei}/`; pentru frontend `C/` înseamnă `files/client/custom/modules/{attendance-management|holiday-management|zile-sarbatoare}/`. Numerele sunt liniile surselor existente, nu ale unor fișiere viitoare.

### P1 — HIGH — ACL declarativ fără efect și protecție CRUD incompletă

- **Dovadă/cauză:** `A/M/Resources/metadata/aclDefs/AttendanceRecord.json:1`, `AttendanceWorkSchedule.json:1`; `H/M/Resources/metadata/aclDefs/HolidayProfile.json:1`, `HolidayLedger.json:1`. Cheile read/edit/delete/stream booleene nu sunt configurația CRUD recunoscută în aclDefs. WorkSchedule nu are guards CRUD echivalente cu celelalte entități.
- **Soluție:** eliminarea acelor chei; politici native în Roles/strictDefault; ownership prin assignedUser; guards CRUD și relationships pentru entități gestionate exclusiv prin comenzi. Nu presupunem că toți utilizatorii au acces acum: exploatarea depinde de rolul efectiv.
- **Mecanism:** scopes, `Acl`, restricții `entityAcl`, record hooks, link checkers unde este necesar; aclDefs numai pentru parametrii checker-elor reali.
- **Componentă:** toate entitățile interne; WorkSchedule numai pentru metadatele Attendance rămase, nu ca program normativ alternativ WTC.
- **Verificare:** matrice HTTP CRUD/list/read-by-id/related/link/unlink/mass actions pentru user fără rol, employee, manager și admin; încercări de DELETE WorkSchedule și payload-uri de ownership/sold; administratorul este refuzat la mutațiile de business chiar cu ACL permisiv, iar managerul nu poate administra WTC prin extensie. Verificăm ACL metadata compilată după rebuild.

### P2 — HIGH — Calendar și cereri cu granturi globale obligatorii

- **Dovadă/cauză:** `H/M/Resources/metadata/app/acl.json:2` acordă mandatory Calendar și read all HolidayRequest; `H/M/Controllers/HolidayRequest.php:15` filtrează lista proprie, nu accesul by ID; layout-ul detail include informații/documente private.
- **Soluție:** fără mandatory grants, read own pentru cereri, read autorizat pentru managerii cu drepturile de aprobare/citire relevante; documentele legate de cererea privată; dacă se păstrează absențele comune, Event minimal separat conform D4. Nu introducem un rol HR suplimentar.
- **Mecanism:** Roles, ACL de entitate/câmp, Attachment ACL și Event/Calendar native. Filtrul de listă nu mai este protecție de securitate.
- **Componentă:** HolidayRequest, Calendar, documente, toate API-urile agregate.
- **Verificare:** un employee poate vedea absența comună dar primește acces refuzat la cererea/documentele colegului; teste cu ID cunoscut, attachments/preview/download, export și API user; un rol care refuză Calendar rămâne respectat.

### P3 — HIGH — Tranziții concurente bazate pe stări vechi

- **Dovadă/cauză:** `H/M/Tools/HolidayBalance/HolidayBalanceService.php:260`, `:297`, `:334` folosesc date fetched/stări încărcate înainte de lock; decizia de la `:469` blochează cererea, dar protocolul nu este uniform. Tranzacția repository save/remove nu acoperă implicit citirea inițială Record Service.
- **Soluție:** protocol unic §6.2 pentru create/update/delete/decizie/anulare, cu citire curentă sub lock, respingerea stării stale și efecte contabile atomice. Protecție și pentru conflictul cross-component pontaj–aprobare.
- **Mecanism:** `transactionalSave` + guard autoritativ în hooks ORM pentru CRUD; tranzacție explicită pentru comenzile de business; `forUpdate`, indexuri unice și idempotency; OCC nativ suplimentar pentru formulare. Nu recomandăm suprascrierea metodelor CRUD core ca să adăugăm un wrapper tranzacțional.
- **Componentă:** Holiday Request/Balance și Attendance writes.
- **Verificare:** două conexiuni DB independente, sincronizate prin bariere de test: edit–approve, delete–approve, approve–reject, cancel–cancel, două create suprapuse, două corecții, mark–approve. Verificăm status final, o singură rezervare/restituire, ledger și lipsa modificării unei cereri finalizate. Scenariul auditului era risc potențial, nu cursă executată în producție.

### P4 — HIGH — CancellationPending tratat diferit între componente

- **Dovadă/cauză:** `A/M/Tools/Attendance/ApprovedHolidayProvider.php:28` selectează numai Approved; `H/M/Tools/HolidayBalance/HolidayBalanceService.php:632` păstrează rezervarea când începe anularea; HolidayOverview de la `:124` include ambele stări active.
- **Soluție:** Approved și CancellationPending sunt aceeași categorie de absență efectivă până la decizia finală. Toți consumatorii Attendance folosesc aceeași citire în lot și aceeași regulă.
- **Mecanism:** enum metadata și filtre ORM; regula este de business, framework-ul nu o deduce.
- **Componentă:** pontaj propriu/managerial, rapoarte, remindere, export, proiecția Calendar.
- **Verificare:** tranziție Approved → CancellationPending → Approved/Cancelled, inclusiv trecerea peste începutul concediului; marcarea rămâne blocată până la anularea finală, soldul nu este restituit anticipat.

### P5 — MEDIUM — Cancelled continuă să blocheze intervalul

- **Dovadă/cauză:** `H/M/Tools/HolidayBalance/HolidayBalanceService.php:1347` exclude Rejected, dar nu Cancelled, din verificarea suprapunerilor.
- **Soluție:** lista pozitivă a stărilor care rezervă intervalul: Pending, ApprovalProcessing, Approved, CancellationPending. Istoricul Cancelled rămâne păstrat.
- **Mecanism:** condiție ORM în tranzacția rezervării, nu query SQL manual.
- **Componentă:** rezervări noi și modificări Pending.
- **Verificare:** după cancel final se poate rezerva din nou aceeași perioadă; înainte de cancel final nu; Rejected nu blochează; două rezervări simultane nu trec ambele.

### P6 — MEDIUM — Două extensii înlocuiesc ruta Admin

- **Dovadă/cauză:** `A/M/Resources/metadata/app/clientRoutes.json:2`, `H/M/Resources/metadata/app/clientRoutes.json:2`; ordini de modul 120/110. Attendance câștigă merge-ul controllerului Admin; Holiday are și referințe directe către setările Attendance.
- **Soluție:** workspace comun `#EmployeeAdministration`, intrare navbar nativă, scopes distincte pe secțiuni; niciun override al Admin și nicio injecție DOM.
- **Mecanism:** clientNavbar, clientRoutes/clientDefs ale scope-ului propriu, controller/view native extinse și validarea actorului regular; fără link operațional adminPanel pentru administratorul tehnic.
- **Componentă:** frontend administrare și Settings API delegate.
- **Verificare:** matrice de roluri cu doar un drept delegat, atât dreptul comun cât și cel de secțiune, combinații parțiale și admin; #Admin rămâne inaccesibil regularilor, meniul apare o singură dată, iar #EmployeeAdministration și API-urile operaționale refuză administratorul tehnic. Nicio secțiune nu expune WTC, Roles sau instalarea extensiilor.

### P7 — MEDIUM — Trasabilitate insuficientă a pontajelor și programului

- **Dovadă/cauză:** `A/M/Resources/metadata/entityDefs/AttendanceRecord.json:13` nu auditează câmpurile; lipsesc createdBy/modifiedBy; `A/M/Tools/Attendance/AttendanceOverviewService.php:357` scrie source Manager fără identitatea managerului. preserveAuditLog nu activează auditul.
- **Soluție:** câmpuri standard de autor, câmpuri audited și motiv pentru corecțiile manageriale, inclusiv cele în luni înghețate. Istoricul normei provine din citirea WTC/snapshot-uri validate, nu dintr-un editor de program propriu. Nu adăugăm un al doilea ledger general pentru a reproduce auditul nativ.
- **Mecanism:** hooks ORM standard pentru createdBy/modifiedBy, audit nativ de câmp, preservarea logului. Verificăm opțiunile de salvare care pot opri auditarea.
- **Componentă:** AttendanceRecord, AttendanceWorkSchedule, profiluri și operații de administrare relevante.
- **Verificare:** doi operatori regular autorizați modifică succesiv aceeași zi; sunt identificabili actorul, valorile vechi/noi și motivul, inclusiv la corecție în lună înghețată. Administratorul tehnic nu poate corecta ziua; auditul configurării WTC rămâne distinct de auditul pontajului. Joburile nu atribuie fals decizia unei persoane; exportul nu publică audit privat.

### P8 — MEDIUM — Aprobarea dependentă de recalculare și de storage netranzacțional

- **Dovadă/cauză:** `H/M/Tools/HolidayDocument/HolidayApprovalDocumentService.php:62` recalculează segmentele din sărbătorile curente și la `:69` aruncă eroare dacă totalul diferă; `:111` scrie Attachment și fișiere în fluxul aprobării. DB rollback nu anulează automat fișierele.
- **Soluție:** snapshot la rezervare/decizie și documente deterministe; Jobs native, ApprovalProcessing recuperabilă, Approved numai după salvarea documentelor, retry/finalizare idempotente și reconcilierea operațiilor abandonate. Cleanup compensator la frontiera DB–storage; rezervarea și ledger-ul nu se dublează.
- **Mecanism:** câmpuri native JSON/readOnly, TransactionManager, JobScheduler, Attachment/FileStorage; nu un afterCommit presupus sau coadă proprie.
- **Componentă:** Holiday Request/Balance, Documents și jobul de generare.
- **Verificare:** modificarea ZileLibere între rezervare și aprobare; schimbarea numelui/semnăturilor înainte de retry; eșec la al doilea fișier lunar; crash înainte/după persistare; retry fără documente duplicate; rollback fără Job contabil rămas. Fișierele orfane sunt risc condițional, nu incident confirmat în audit.

### P9 — MEDIUM — Interogări N+1

- **Dovadă/cauză:** `A/M/Tools/Attendance/AttendanceOverviewService.php:85` apelează provider-ul concediilor per angajat; `H/M/Tools/HolidayBalance/HolidayBalanceService.php:127` caută User per profil; `:1097` poate executa queries request/ledger pentru eligibilitatea setup-ului per profil.
- **Soluție:** selecții ORM pe setul de userIds/profiles, grupare în memorie pe date, agregări și paginare unde sunt potrivite. Contextul unei luni se încarcă o dată per request.
- **Mecanism:** repositories/Query Builder/Select Builder native și indexuri metadata. Fără cache persistent distribuit sau strat repository propriu doar pentru acest scop.
- **Componentă:** overview/export Attendance, profile și booking users Holiday.
- **Verificare:** query counting pe aproximativ 15 angajați și maximum patru WTC, cu seturi mai mari numai ca stres opțional; queries de domeniu principale să nu crească liniar cu angajații. Măsurăm separat costul ACL și randarea/exportul, iar administratorii nu intră în numărători. Țintele de timp se stabilesc pe infrastructură de test, nu se inventează valori acum.

### P10 — MEDIUM — Lifecycle incomplet pentru navigare în grupuri

- **Dovadă/cauză:** `A/scripts/AfterInstall.php:64` tratează special grupul propriu și poate pierde alți copii; `A/scripts/BeforeUninstall.php:25`, `H/scripts/AfterInstall.php:67`, `H/scripts/BeforeUninstall.php:39` lucrează incomplet cu structura grupată.
- **Soluție:** o singură politică de instalare și reconciliere care recunoaște structura nativă `tabList`/`itemList`; caută și elimină numai intrările deținute, oriunde sunt plasate. Upgrade-ul nu reintroduce automat taburi eliminate intenționat de administrator.
- **Mecanism:** Config/ConfigWriter și formatul navbar nativ; transformare locală mică, fără helper framework nou și fără a presupune un API recursiv inexistent.
- **Componentă:** scripts lifecycle, taburi Attendance/Holiday, Calendar config.
- **Verificare:** listă plată, grupuri, copii străini în grupul vechi, instalare repetată, upgrade și uninstall; nu apar duplicate, nu rămân linkuri moarte și nu dispar intrări străine.

### P11 — MEDIUM — Înlocuirea view-ului Calendar ZileSarbatoare

- **Dovadă/cauză:** `H/M/Resources/metadata/clientDefs/Calendar.json:2` și `Z/M/Resources/metadata/clientDefs/Calendar.json:2` scriu calendarView; `H/C/src/views/calendar/calendar.js:1` extinde direct view-ul core, pierzând decorarea din `Z/C/src/views/calendar/calendar.js:19`.
- **Soluție:** eliminarea override-ului Employee dacă funcționalitatea poate fi exprimată prin Event/record metadata; altfel moștenire explicită din view-ul ZileSarbatoare, cu un singur owner final și limitări de compatibilitate documentate.
- **Mecanism:** metadata merge nativ, view inheritance și lifecycle de view; calendarEntityList și ACL pentru entitățile afișate. Nu există un mecanism verificat de compunere automată a două valori calendarView.
- **Componentă:** Calendar frontend și contractul versiunilor cu ZileSarbatoare.
- **Verificare:** metadata efectivă după rebuild, lună/săptămână/listă/timeline, filtre on/off ZileLibere și absențe, refresh după decizie/anulare, navigare repetată fără listener leaks, scenarii cu altă extensie Calendar. Coliziunea surselor este confirmată; efectele vizuale finale necesită browser real.

## 8. Instalare, actualizare, mentenanță și testare

### 8.1. Identitatea pachetului și instalarea curată

O singură identitate stabilă în manifest și o singură versiune SemVer. În installer-ul 10.0.10 extensia existentă este identificată după `manifest.name`; textul „Package ID” din description nu este cheia de upgrade. Numele provizoriu trebuie aprobat înainte de prima instalare de producție și păstrat stabil.

Pachetul nu include fișiere în `application/`, client core sau modulele altor extensii. Nu împachetăm ZileSarbatoare în interiorul său: rămâne dependență instalată separat, cu lifecycle propriu. „Un pachet” înseamnă un pachet pentru Attendance și Holiday, nu însușirea infrastructurii externe.

La instalare:

1. Extension Manager validează EspoCRM/PHP și `manifest.dependencies`.
2. Fișierele și entitățile sunt namespaced/prefixate; nu suprascriu pachetele vechi. Coexistența este permisă controlat în laborator pentru verificarea primei etape, nu reprezintă recomandare de operare simultană în producție. Installer-ul nu dezinstalează originale și nu șterge date automat.
3. Copierul și rebuild-ul native înregistrează modulul, metadata și schema.
4. Prima etapă folosește numai metadata de navigare și nu modifică tabList/calendarEntityList ori ScheduledJobs. Când componentele respective vor fi activate, `AfterInstall` aplică numai defaults lipsă și populează joburile prin mecanismul nativ; nu creează solduri pentru toți utilizatorii și nu acordă zile de concediu.
5. Administratorul tehnic configurează și atribuie Roles native, desemnează managerii regular, creează/configurează WTC pentru cele maximum patru programe estimate și atribuie calendarul corespunzător fiecărui angajat. Estimarea dimensionează testele, nu introduce o limită artificială în platformă. Verifică infrastructura, cron/daemon, dependențele și acoperirea tehnică ZileLibere. Nu inițializează profiluri/solduri și nu preia operațiunile de aprobare sau pontaj. Managerii desemnați configurează numai setările Employee Management și inițializează profilurile/parametrii operaționali în limita rolurilor; readiness-ul este verificat separat pentru partea tehnică și cea operațională.
6. Acordările anuale rămân dezactivate până la activare explicită. Reset-ul masiv de date de test nu este inclus în UI-ul de producție recomandat; fixture/reset rămâne în mediul de test. Orice instrument de ștergere reală ar necesita o cerință și aprobare separate.

În mediile existente fără date de producție recomand scoaterea controlată a celor două pachete vechi prin Extension Manager și instalarea curată a produsului unificat. Tabelele, config-ul și customizările rămase se inventariază înainte de operație; lipsa datelor de producție nu autorizează ștergerea automată a datelor de test sau configurației. Nu promitem upgrade in-place din două identități de extensie într-una singură.

### 8.2. Particularitate verificată a upgrade-ului EspoCRM

În 10.0.10, `Core/Upgrades/Actions/Extension/Install` dezinstalează versiunea veche în cadrul upgrade-ului. Rulează uninstall-ul cu `skipAfterScript=true`, **nu** cu skipBeforeScript. Prin urmare, `BeforeUninstall` vechi poate fi executat și în timpul unei actualizări.

Consecințe obligatorii:

- `BeforeUninstall` nu șterge date de business, setări persistente, audit sau documente și nu resetează profiluri/solduri.
- Nu eliminăm permanent taburile personalizate la fiecare upgrade pentru a le recrea la nivel superior. Curățarea configurațiilor care trebuie păstrate la upgrade se plasează în `AfterUninstall`, care este omis în fluxul de upgrade verificat, după ce confirmăm în test accesul la serviciile native necesare.
- Oprirea joburilor pentru uninstall trebuie coordonată cu acest lifecycle; la upgrade păstrăm/reconciliem identitatea și programarea ScheduledJob, fără a reseta programul configurat de administrator.
- Nu presupunem un parametru universal `isUpgrade` disponibil identic în toate scripturile. În sursa verificată este setat pentru instalare; cleanup-ul se proiectează după fluxul real și se testează end-to-end.

Instalarea este orchestrată de EspoCRM, dar nu este o tranzacție atomică pentru fișiere + config + DB. Este necesar backup operațional și procedură de recuperare testată; nu afirmăm că rollback-ul installer-ului reface automat toate efectele custom.

### 8.3. Actualizări viitoare fără pierderea datelor

- Entity type-urile, cheile configurației și numele pachetului devin contracte stabile după release. Nu le redenumim cosmetic cu date existente.
- Schema se definește în metadata și se reconstruiește nativ; modificările de date necesită pași de upgrade expliciți, idempotenți, limitați și testați. Folosim scripturile de instalare native; nu presupunem că infrastructura internă de migrations pentru upgrade-ul core-ului este automat un API public pentru extensii.
- Pentru un număr mic de transformări folosim versiune de schemă deținută de pachet și pași expliciți în scripturile sale, fără engine de migrare propriu. Marcajul se actualizează numai după succes. Idempotency este verificată pe pași parțial executați.
- Schimbările destructive de coloane/câmpuri se fac etapizat, cu export/backup și plan de recuperare; rebuild-ul nu este o migrare de business.
- Defaults se aplică numai dacă lipsesc; nu suprascriem roluri, layouts, taburi, programe sau entitlement-uri salvate.
- Nu adoptăm `skipBackup: true` ca optimizare implicită. Backup-ul de fișiere al installer-ului nu înlocuiește backup DB și storage.
- Dacă un upgrade atinge contabilizarea, verificăm înainte/după invariantele sold–ledger pe setul de date de test reprezentativ.

### 8.4. Rebuild și clear cache

Metadata și schema trebuie să fie reconstruibile exclusiv din fișierele pachetului și customizările legitime ale instanței. Nu generăm definiții esențiale printr-un request de frontend și nu patch-uim cache-uri compilate.

Testăm `rebuild`, clear cache și repornirea worker-ului pe instanța de test cu date existente. Nici rebuild, nici clear cache nu acordă entitlement, nu restituie sold, nu regenerează documente și nu mută taburi. După rebuild verificăm metadata efectivă: owner Calendar, rute proprii, restricții ACL și entități. Instalarea prin Extension Manager are deja pași de rebuild nativi; scripturile nu pornesc recursiv încă un installer/rebuild propriu.

### 8.5. Dezinstalare controlată

Politica recomandată este **dezinstalare reversibilă cu păstrarea datelor**. Scoatem codul și înregistrările de integrare deținute de extensie; păstrăm tabelele/înregistrările de business, auditul, documentele și setările necesare reinstalării. Acestea nu mai sunt accesibile prin UI-ul extensiei până la reinstalare.

Uninstall complet elimină referințele proprii din tabList, calendarEntityList și alte config-uri, fără să elimine ZileLibere, Calendarul altor module sau grupurile personalizate ale utilizatorului. Navbar-ul și rutele declarative dispar odată cu metadata pachetului. Cleanup-ul după uninstall folosește doar serviciile core rămase disponibile, nu clasele modulului deja scos.

Joburile proprii sunt oprite/curățate prin entitățile și runner-ul native; nu lăsăm în coadă joburi care referă clase eliminate. Procedura operațională oprește lansarea lor și așteaptă încheierea celor active înainte de scoaterea codului. Nu presupunem că ștergerea unui ScheduledJob oprește un Job deja în execuție. Testăm stările Pending/Running/Failed și reinstalarea cu joburi istorice păstrate.

Instalarea, actualizarea, dezinstalarea, backup-ul și recuperarea sunt responsabilități ale administratorului tehnic, nu operații ale managerului în `#EmployeeAdministration`. Pachetul nu șterge, nu modifică și nu reatribuie calendarele WTC native la uninstall/upgrade; acestea aparțin infrastructurii platformei. Păstrarea datelor/snapshot-urilor proprii nu îi acordă administratorului un rol operațional în extensie.

Nu implementăm „delete everything” la uninstall și nu ștergem tabele. Purjarea ireversibilă este o operațiune separată, administrativă, cu confirmare, backup și politică de retenție aprobată. Datele păstrate au în continuare cerințe de protecție/retentie; lipsa UI-ului nu este măsură suficientă de confidențialitate.

### 8.6. Compatibilitate și dependențe

| Element | Politică recomandată |
| --- | --- |
| EspoCRM | Exclusiv 10.0.1 în prima versiune; manifest exact; fără upgrade al instalației existente sau suport declarat pentru versiuni netestate |
| PHP | Exclusiv 8.4.23 în prima versiune; alte versiuni numai cu teste și biblioteci compatibile |
| DB | Backend-ul real folosit în producție trebuie să fie în CI; `forUpdate`, indexuri și concurență testate pe el. Nu declarăm suport pentru toate DB doar fiindcă ORM este portabil |
| ZileSarbatoare | Dependență manifest după numele real + versiune/plajă validată; contract public, fără copierea codului intern |
| WTC | Adoptat: infrastructură nativă administrată/atribuită tehnic, normă explicită + ZileSarbatoare; numai citire; snapshot-urile istorice read-only și regresiile produsului sunt release gate, fără fallback custom |
| PDF/XLSX/DOCX | Inventarierea claselor/bibliotecilor existente și a versiunilor; nu presupunem că o clasă vendor internă este API stabil de extensie. Teste golden/structurale pentru output și verificarea licențelor |
| Alte extensii Calendar | Detectarea și documentarea owner-ului final scalar; fără promisiune de compoziție automată |
| Frontend | Mecanisme native de view/action; fără metode private drept contract stabil sau dependențe de DOM global |

Dacă păstrăm Dompdf direct pentru registrul matricial, justificarea este controlul unui document agregat, nu un record PDF generic. Păstrăm opțiunile restrictive existente pentru remote/PHP/JavaScript și escaparea datelor. Pentru XLSX păstrăm celulele text explicite. Nu este obligatoriu un wrapper suplimentar peste generatorii existenți.

### 8.7. Strategia de testare

Testele existente se păstrează ca specificații de regresie, dar assertion-urile care verifică implementări eliminate — de exemplu override-ul Admin — se înlocuiesc cu teste ale comportamentului corect. Nu menținem un defect doar pentru a păstra verde un contract test.

| Nivel | Acoperire minimă | Criteriu de acceptare |
| --- | --- | --- |
| Static/contract | JSON/schema native, namespace/autoload, rute, metadata, structura ZIP, lipsa fișierelor core, lint PHP/JS | Pachet valid și toate clasele/rutele rezolvabile; numai fișiere deținute |
| Unit | BalanceMath, stări, booking dates, precedență absențe, zile lucrătoare, DST, împărțire pe luni | Aceleași reguli între Attendance, Holiday, export și documente |
| Integrare EspoCRM | Laboratorul izolat 10.0.1 demonstrat în fezabilitate, DB și storage reale, ZileSarbatoare, Roles native, WTC atribuite tehnic | Metadata, schema, hooks, audit și ACL funcționează în framework, nu numai în stubs; setup-ul tehnic nu creează date de angajat pentru admin |
| Securitate API | Matricea §5, CRUD și link endpoints, acces prin ID, mass actions, export/attachments, setări allowlist; admin ca actor și beneficiar | Administratorul este refuzat în fluxurile de business chiar dacă ACL nativ permite; managerul rămâne regular, fără administrare globală/WTC; nu există editare directă de sold/status |
| Eligibilitate și rapoarte | Admin activ cu roluri Employee Management, angajat regular, manager regular, cont inactiv, ID-uri directe și date reziduale | Admin nu apare în liste/pontaje/Calendar de absențe, nu primește profil/sold și nu afectează numărători, totaluri sau exporturi; regulile istoricului înghețat sunt păstrate |
| Responsabilități WTC | Administratorul creează/configurează/atribuie nativ; două componente citesc aceleași maximum patru programe pentru circa 15 angajați; calendar lipsă, modificat sau reatribuit | Nicio scriere WTC/WorkingTimeRange/User.workingTimeCalendar din extensie și niciun editor/scop delegat; zile/ore/ZileSarbatoare/istoric corecte, fără fallback tacit |
| Regresie business | Managerul își vede propria cerere Pending în lista/coada autorizată și o aprobă/respinge; corectează propria zi sau a unui coleg într-o lună înghețată; încercări echivalente ale unui angajat și admin | Managerul cu drepturi reușește prin același flux auditat; angajatul nu ocolește înghețarea, admin nu operează extensia; contabilizarea, CIC/CCC, Calendar și Jobs rămân conforme regulilor aprobate |
| Concurență | Scenariile P3 cu minimum două conexiuni/procese și bariere deterministe | Sold/ledger/status/proiecție consistente după orice intercalare acceptată |
| Lifecycle | Fresh install, reinstall, upgrade N→N+1, upgrade eșuat, rebuild/cache, uninstall/reinstall cu date și config custom, executate tehnic | Fără pierdere date/customizări, fără schimbarea WTC native, joburi fără duplicate/clase lipsă; managerul nu poate instala sau dezinstala extensii |
| Browser | Navbar, acces direct, UI delegată numai Employee Management, Calendar, aprobări/anulări, formulare, matrice și responsive | #Admin nealterat, inaccesibil managerului regular; admin refuzat în workspace-ul de business; fără pagini WTC/Roles globale în workspace; Calendar păstrează sărbătorile și confidențialitatea |
| Documente/export | Snapshot, luni multiple, caractere RO, text/formule, paginare, eșec storage/retry | Output corect și determinist; fără duplicare sau publicare prematură a documentelor |
| Performanță | Aproximativ 15 angajați, maximum patru WTC și istoric multianual; seturi mai mari numai pentru stres opțional | Queries în lot, memorie/latență măsurate și limite explicite pentru perioadă/export; fără infrastructură suplimentară pentru volumul estimat |

Testele de lifecycle și concurență se execută exclusiv într-un mediu izolat. Nu folosim producția pentru a demonstra integritatea. Readiness-ul tehnic, al administratorului, include cron/daemon, backup/restaurare, dependențe, roluri native și WTC atribuit fiecărui angajat. Readiness-ul operațional, al managerilor, include setările Employee Management, profiluri/solduri, aprobări și corecții autorizate. Ambele verifică rezultatele de integrare ZileLibere, datele de test și download-urile conform drepturilor; nu se folosește contul admin drept substitut pentru manager în testele pozitive de business.

Un refuz corect al operației de business pentru admin nu trebuie să blocheze administrarea nativă Roles/WTC/Extensions sau backup/restaurare. Invers, reușita unei operații native de administrare nu dovedește că admin poate opera concedii ori pontaje. Nu certificăm prin aceste teste că datele sunt inaccesibile administratorului tehnic la nivel de platformă/DB.

## 9. Etapele recomandate de implementare

Implementarea incrementală este autorizată prin cerința curentă. Fezabilitatea nativă este încheiată; nu reluăm o analiză generală. Prima livrare este pachetul unificat instalabil cu citire privată securizată. Comenzile nesecurizate sau neintegrate rămân neexpuse; fiecare etapă adaugă funcții verificabile și teste.

| Etapă | Livrabil | Condiție de ieșire |
| --- | --- | --- |
| 0. Decizii și specificație | Consemnarea responsabilităților stabilite și a regulilor deja aprobate; inventar funcțional și baseline teste | D3/D5/D6/D10 reflectă cerința actuală; nu redeschidem celelalte decizii de business |
| 1. Fezabilitate nativă | Validare pe instanță izolată a citirii WTC atribuit tehnic, calculului/istoricului, Calendar și ACL/Attachment, plus lifecycle upgrade/uninstall | WTC este utilizabil fără administrare delegată sau program paralel; orice limită reală este raportată înainte de implementarea componentei afectate |
| 2. Bază funcțională unificată | Modul, manifest exact 10.0.1/dependențe, metadata, entități private read-only, navigare și cod reutilizat, ZIP | Install/rebuild și teste de confidențialitate; coexistarea controlată în laborator nu suprascrie originale; mutații încă blocate |
| 3. ACL și administrare | Workspace numai Employee Management, scopes/roluri native atribuite tehnic, eligibilitate regular, API allowlist, guards CRUD/link | P1, P2, P6 închise; admin exclus din business, managerul fără privilegii globale; autoaprobarea autorizată și corecțiile în luni înghețate verificate |
| 4. Tranzacții și contabilizare | Flux unic cereri/profil/ledger, idempotency, snapshot, locking cross-component | P3–P5 închise; teste concurente și regresie sold/ledger |
| 5. Program și Attendance | Citire normă exclusiv WTC, snapshot-uri istorice, integrarea absențelor, audit și batch reads, fără editor de programe | P4, P7, P9 închise; completitudine/export/istoric fără divergențe și fără administratori în populația de angajați |
| 6. Jobs/documente, apoi Calendar | ApprovalProcessing, finalizare Approved după salvarea documentului, recuperare/idempotency; apoi proiecție minimală și view ownership | P8, P11 închise; confidențialitate și failure/retry testate |
| 7. Lifecycle și release | Upgrade N→N+1, retenție, cleanup navbar/jobs, performanță, backup/restore | P10 închis; toate criteriile release trecute, checklist de go-live aprobat |

Testarea nu este amânată la etapa 7. Fiecare etapă livrează testele comportamentului său; etapa finală verifică produsul instalabil complet. Separarea namespace-urilor și mutarea fișierelor nu se consideră dovadă de rezolvare a auditului.

## 10. Decizii arhitecturale și actualizarea responsabilităților

Actualizarea din 20 octombrie fixează separarea responsabilităților în D3/D5/D6/D10: acestea nu mai propun alternative care contrazic cerința curentă. D9 reflectă păstrarea înghețării lunare și a corecției manageriale, fără redefinirea mecanismului. Celelalte decizii și regulile de business aprobate nu sunt modificate sau supuse din nou aprobării prin această actualizare. Variantele incompatibile nu se implementează simultan „ca să avem flexibilitate”.

| ID | Decizie și alternative | Recomandare argumentată | Efect dacă se alege altfel |
| --- | --- | --- | --- |
| **D1** | Un modul sau două module într-un pachet; numele definitiv al pachetului | Un modul `EmployeeManagement`, un pachet cu nume stabil Employee Management. Cel mai mic cost de ownership comun pentru un produs unic | Două module sunt valide, dar cer convenție explicită pentru cod comun și metadata; numele manifest nu se schimbă ulterior cosmetic |
| **D2** | Identitate tehnică fără coliziuni; ownership | Prefix `Employee` pentru entitățile proprii, namespace unic EmployeeManagement; assignedUser consecvent. Necesare pentru coexistare fără suprascrierea originalelor în laborator | Codul/asset-urile se adaptează mecanic; nu migrăm sau ștergem datele originale. Codul de business încă neadaptat rămâne neexpus, nu se activează cu ownership inconsistent |
| **D3** | Administrare delegată — responsabilitate stabilită | Workspace `#EmployeeAdministration` numai pentru managerii regular autorizați și numai pentru funcțiile extensiei; navbar nativ, scope comun + scopes pe secțiuni; fără #Admin override | WTC, Roles, permisiuni globale, instalarea extensiilor și setările platformei nu intră în workspace. Admin rămâne tehnic, nu operator implicit; vizibilitatea unui link nu înlocuiește validarea backend |
| **D4** | Calendar comun — stabilit | `EmployeeAbsence` minimală pentru Approved/CancellationPending, exclusiv numele angajatului și intervalul | Nu publicăm Pending/ApprovalProcessing, motive, solduri, documente sau legături care deschid cererea privată; nu suprascriem calendarView ZileSarbatoare |
| **D5** | Roluri și eligibilitate — regulă stabilită | Admin configurează/atribuie rolurile native; managerul regular activ operează toți angajații în limita scopes, aprobă/respinge inclusiv propriile cereri și corectează pontaje inclusiv în luni înghețate; admin exclus ca actor și beneficiar de business | Nicio ramură „admin permite” în business, nicio interdicție de autoaprobare, nicio segregare team nouă. Auditul, soldul și celelalte validări se păstrează; privilegiile tehnice native nu sunt eliminate |
| **D6** | WTC adoptat | Exclusiv WTC nativ creat/configurat/atribuit tehnic, normă explicită citită separat de indisponibilitatea personală; ZileSarbatoare păstrat | Calcul demonstrat pe 10.0.1; rezultate istorice read-only obligatorii, nu editor paralel și nu delegare WTC |
| **D7** | Jobs și Approved după document — stabilit | Snapshot rezervare/decizie, ApprovalProcessing recuperabilă, Job nativ, Approved definitiv numai după document persistat | Retry/finalizare idempotente; rezervare/ledger o singură dată; reconciliere Processing abandonat. DB și storage nu devin atomic tranzacționale |
| **D8** | Conflict între concediu aprobat și pontaj existent; retragere Pending | Aprobare blocată până la rezolvarea auditabilă a pontajului incompatibil; retragere păstrată în istoric, nu ștergere fizică | Mascarea automată păstrează compatibilitatea actuală, dar ascunde contradicții; modificarea sau ștergerea automată a pontajului nu este recomandată |
| **D9** | Istoric și înghețare lunară — stabilit | Rezultate normative istorice persistate read-only, lunile înghețate și concediile contabilizate nu se recalculează din WTC curent; corecțiile manageriale rămân explicite și auditate | Fără program editabil suplimentar, dezghețare sau recalculare automată; nu schimbăm politica sau momentul înghețării |
| **D10** | Compatibilitate și responsabilități — stabilit | Prima versiune exclusiv 10.0.1/PHP8.4.23. Admin răspunde de lifecycle, Roles/WTC și infrastructură; managerii de operațiunile autorizate; API users fără comenzi implicite | Niciun upgrade de producție; fără compatibilitate netestată; instalarea nu înscrie admin ca angajat și nu modifică WTC |

**Starea actualizării:** deciziile de mai sus sunt aprobate, nu redeschise. Implementarea incrementală este autorizată, pornind cu un singur pachet/modul, namespace-uri fără conflicte, navigare nativă și date private cu ACL native. Codul vechi se reutilizează, dar mutațiile, Jobs, Calendarul public și setările încă nesecurizate nu se expun în prima etapă. Testele și funcționalitățile efectiv livrate se consemnează în EmployeeManagement, fără a declara produsul production-ready.

## Anexa A — Referințe oficiale și trasabilitate

### A.1. Documentație oficială consultată

- [Pachete de extensie](https://docs.espocrm.com/development/extension-packages/) — manifest, files/scripts, lifecycle și intervale de versiuni.
- [Metadata](https://docs.espocrm.com/development/metadata/) — organizare, merge și extensibilitate.
- [ACL](https://docs.espocrm.com/development/acl/) și [aclDefs](https://docs.espocrm.com/development/metadata/acl-defs/) — servicii/checkers native.
- [app.acl](https://docs.espocrm.com/development/metadata/app-acl/) — diferența mandatory/strictDefault.
- [recordDefs](https://docs.espocrm.com/development/metadata/record-defs/), [entityDefs](https://docs.espocrm.com/development/metadata/entity-defs/) și [entityAcl](https://docs.espocrm.com/development/metadata/entity-acl/) — model, guards și restricții.
- [clientNavbar](https://docs.espocrm.com/development/metadata/app-client-navbar/) — intrări native în meniul utilizatorului.
- [Working Time Calendar](https://docs.espocrm.com/user-guide/working-time-calendar/) — capacități funcționale, nu o promisiune de echivalență cu pontajul.
- [Jobs](https://docs.espocrm.com/development/jobs/) — programare și execuție native.

### A.2. Sursa oficială EspoCRM 10.0.10 verificată

Checkout-ul local folosit pentru citire: `/tmp/espo-navbar-review.IfnLQF`. Documentul nu depinde de păstrarea acelui director; referințele permanente sunt cele de mai jos.

| Subiect | Sursă exactă |
| --- | --- |
| Parametrii aclDefs recunoscuți | [schema/metadata/aclDefs.json](https://github.com/espocrm/espocrm/blob/10.0.10/schema/metadata/aclDefs.json) |
| ReadOnly backend din entityDefs/entityAcl | [GlobalRestriction.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Acl/GlobalRestriction.php#L78) |
| Ownership implicit assignedUser/createdBy/teams | [DefaultOwnershipChecker.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Acl/DefaultOwnershipChecker.php#L57) |
| Citirea și mutația CRUD native | [Core/Record/Service.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Record/Service.php#L719) |
| Recomandarea compoziției, nu moștenirii serviciului legacy | [Services/Record.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Services/Record.php#L37) |
| Tranzacția save/remove | [ORM/Repository/RDBRepository.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/ORM/Repository/RDBRepository.php#L154) |
| Tranzacții explicite și savepoints | [ORM/TransactionManager.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/ORM/TransactionManager.php#L72) |
| Restricția globală Admin | [client/src/controllers/admin.js](https://github.com/espocrm/espocrm/blob/10.0.10/client/src/controllers/admin.js#L46) |
| Navbar menu metadata | [client/src/views/site/navbar.js](https://github.com/espocrm/espocrm/blob/10.0.10/client/src/views/site/navbar.js#L1418) |
| Semantica cumulativă accessDataList | [client/src/utils.ts](https://github.com/espocrm/espocrm/blob/10.0.10/client/src/utils.ts#L314) |
| Calendar backend și getCalenderQuery | [Crm/Tools/Calendar/Service.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Modules/Crm/Tools/Calendar/Service.php#L226) |
| Selectarea scalară calendarView | [calendar-page.js](https://github.com/espocrm/espocrm/blob/10.0.10/client/modules/crm/src/views/calendar/calendar-page.js#L318) |
| Calendar frontend: click/refresh și metode private | [calendar.js](https://github.com/espocrm/espocrm/blob/10.0.10/client/modules/crm/src/views/calendar/calendar.js#L962) |
| ACL Attachment | [Classes/Acl/Attachment/AccessChecker.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Classes/Acl/Attachment/AccessChecker.php#L60) |
| Audit fără stream activ | [Tools/Stream/HookProcessor.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Tools/Stream/HookProcessor.php#L649) |
| Factory și utilitare WTC | [CalendarFactory.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Tools/WorkingTime/CalendarFactory.php), [CalendarUtilityFactory.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Tools/WorkingTime/CalendarUtilityFactory.php), [CalendarUtility.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Tools/WorkingTime/CalendarUtility.php) |
| Cum sunt compuse calendarele User | [UserCalendar.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Tools/WorkingTime/UserCalendar.php) |
| Restricția linkului WTC pe User | [Resources/metadata/app/acl.json](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Resources/metadata/app/acl.json) |
| JobScheduler și persistarea Job | [JobScheduler.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Job/JobScheduler.php), [EntityCreator.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Job/JobScheduler/Creators/EntityCreator.php) |
| Dependințe native verificate la instalare | [Upgrades/Actions/Helper.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Upgrades/Actions/Helper.php#L55), [Extension/Install.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Upgrades/Actions/Extension/Install.php#L241) |
| Identitatea pachetului și uninstall la upgrade | [Extension/Install.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Upgrades/Actions/Extension/Install.php#L122) |
| Ordinea scripturilor/rebuild la instalare | [Base/Install.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Upgrades/Actions/Base/Install.php#L54) |
| Hooks before/after la dezinstalare și skipAfterScript | [Base/Uninstall.php](https://github.com/espocrm/espocrm/blob/10.0.10/application/Espo/Core/Upgrades/Actions/Base/Uninstall.php#L56) |

### A.3. Puncte de intrare în codul proiectului

- [Attendance README](AttendanceManagement/README.md), [manifest](AttendanceManagement/manifest.json), [AttendanceOverviewService](AttendanceManagement/files/custom/Espo/Modules/AttendanceManagement/Tools/Attendance/AttendanceOverviewService.php).
- [Holiday README](HolidayManagement/README.md), [manifest](HolidayManagement/manifest.json), [HolidayBalanceService](HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/HolidayBalanceService.php), [documente](HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayDocument/HolidayApprovalDocumentService.php).
- [ZileSarbatoare README](ZileSarbatoare/README.md), [contractul public ZileLibereCalendar](ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereCalendar.php), [binding-ul său DI](ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Binding.php).

**Concluzie:** extensia unificată trebuie să rămână o aplicație de business pe infrastructura EspoCRM. Codul particular este justificat pentru pontaj, reguli de concediu, contabilizare și documente; nu este justificat pentru a înlocui ACL, administrarea globală, ORM, schema, installer-ul sau job runner-ul native. Această separare, nu simpla comasare a fișierelor, este criteriul de reușită al proiectului.
