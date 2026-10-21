# Employee Management — fezabilitate tehnică pe EspoCRM Docker

Data: **20 octombrie 2026**, Europe/Bucharest. Probele au rulat în noaptea de 19/20 octombrie; timestamp-urile tehnice reproduse mai jos sunt **UTC**.

Bază: [arhitectura actualizată](UNIFIED-EXTENSION-ARCHITECTURE.md), inclusiv secțiunea de audit P1–P11, sursele Attendance Management 1.11.0, Holiday Management 1.9.4 și ZileSarbatoare 0.9.5.

**Rezultat principal:** mecanismele native necesare au fost demonstrate practic pe **EspoCRM 10.0.1**. Administrarea delegată, restricționarea actorului admin la granița business, ACL own/all, Event-uri minimale și Jobs cu DOCX sunt fezabile. **WTC: CONDITIONAL**, din cauza istoricului, nu a calculului programelor fixe. Nu există încă o validare end-to-end a viitoarei extensii unificate.

Nu am implementat Employee Management, nu am modificat extensiile originale sau documentul arhitectural aprobat și nu am actualizat/dezinstalat pachete în instalația existentă. Sonda temporară descrisă mai jos este un test de mecanisme native, nu codul viitoarei extensii.

## Convenții și limite de interpretare

| Marcaj | Semnificație |
| --- | --- |
| **PASS** | Operația a fost executată și rezultatul indicat a fost observat |
| **FAIL** | Test executat; comportamentul observat nu satisface cerința indicată |
| **NOT TESTED** | Nu a fost executată verificarea necesară; existența unei clase nu este considerată dovadă |
| **RISK** | Limită sau risc care necesită o măsură de proiectare ori verificări suplimentare |

Toate probele de aplicație din raport sunt pe **10.0.1 / PHP 8.4.23 / MariaDB 12.3.2**, dacă nu este indicat explicit altceva. „PASS mecanism” nu înseamnă „PASS implementare Employee Management”. De exemplu, un guard demonstrat pe o entitate-probă nu certifică toate endpoint-urile viitoarei extensii.

## 1. Configurația Docker identificată

### 1.1. Instalația existentă — inspecție exclusiv read-only

| Element | Rezultat observat |
| --- | --- |
| Proiect Compose | `interncursurituvro` |
| Fișier Compose | `/opt/intern.cursurituv.ro/docker-compose.yml`, identificat din etichetele Docker |
| Aplicație | `espocrm`, ID scurt `2b50b998f3f0` |
| Daemon | `espocrm-daemon`, ID scurt `f61631ee6f8e` |
| WebSocket | `espocrm-websocket`, ID scurt `81788952dd3f` |
| DB | `espocrm-db`, ID scurt `5b68370c7445` |
| Imagine aplicație | `local/espocrm:latest`, imagine efectivă `sha256:0f0ba6a03793b06728d97616b081b2d59e78e90e1df7be4927465034e684641a` |
| **EspoCRM real** | **10.0.1**, verificat în `data/state.php`; laboratorul a confirmat și prin Config runtime |
| PHP | **8.4.23** |
| Motor DB | **MariaDB 12.3.2-MariaDB-ubu2404**; platforma configurată în EspoCRM este `Mysql` |
| Imagine DB efectivă | `sha256:628f228f0fd5913a220438693576b29b6fe4dc1fa0a1298c0e98579fae28635f` |
| Site configurat | `https://intern.cursurituv.ro` |
| Expunere existentă | Aplicația pe `127.0.0.1:18080`; WebSocket pe `127.0.0.1:18081` |
| Timezone aplicație existentă | `UTC` |
| Stocare | Volume persistente distincte pentru DB, data, custom, client/custom și extensions |
| Populație existentă, agregată | 11 regular activi, un administrator activ și un system user |
| WTC existente | Tabele native disponibile; **0 calendare și 0 WorkingTimeRange** |
| Date de business existente | 6 HolidayRequest, 12 HolidayProfile, 26 AttendanceRecord, 34 ZileLibere, la inspecția inițială |

Nu au fost afișate parole, token-uri, nume de angajați sau conținutul înregistrărilor reale. Citirile DB au folosit SELECT-uri de versiune, inventar și agregare. Nu am pornit aplicația CLI completă în instalația existentă pentru teste de business.

**Statut operațional:** nu există o confirmare suficientă că instalația este de test. Domeniul real, datele operaționale și istoricul de Jobs nu permit tratarea ei ca laborator. A fost clasificată **„statut neconfirmat; tratată ca producție”**. Toate scrierile au fost mutate într-un proiect izolat, conform cerinței.

### 1.2. Jobs/cron/daemon existente

- `espocrm-daemon` execută efectiv `/usr/local/bin/php /var/www/html/daemon.php`.
- `daemonInterval = 10`, `daemonMaxProcessNumber = 5`, `jobRunInParallel = true`.
- `cronDisabled` nu este activat; în imaginea inspectată nu există `/etc/cron.d`. Nu am presupus că există și un cron separat pe host; acesta este **NOT TESTED**.
- Tabelul Job avea 14.651 `Success` și 19 `Pending` la citire. Exemple recente `SubmitPopupReminders` și `ProcessJobQueueQ0/E0` aveau execuții reușite la intervale de aproximativ zece secunde. Aceasta este dovadă de activitate, nu test de generare a documentelor în producție.
- ScheduledJobs active includ procesarea cozilor/grupurilor native, `SyncZileSarbatoare` și `ProcessHolidayResets`. Simplul statut Active nu certifică succesul sincronizării Nager.Date sau al acordărilor anuale.

### 1.3. Module și extensii instalate

| Extensie instalată în instanța existentă | Versiune |
| --- | --- |
| Attendance Management | 1.11.0 |
| Holiday Management | 1.9.4 |
| Zile Sărbătoare | 0.9.5 |
| TUVTK Theme | 1.0.13 |

Modulele custom identificate sunt `AttendanceManagement`, `HolidayManagement`, `ZileSarbatoare`, `TuvtkTheme`. Au fost identificate și versiunile vechi neinstalate în istoricul Extension Manager; acestea nu au fost confundate cu versiuni active.

Contractul public `ZileLibereCalendar`, serviciul său și `Binding.php` există atât în instalația existentă, cât și în copia instalată în laborator. Disponibilitatea lui WTC este confirmată prin schema nativă în instalația existentă și prin operațiile reale de mai jos în laborator.

### 1.4. Laboratorul folosit și izolarea

Proiect nou: **`employee-feasibility-20261020`**, cu servicii `app`, `db`, `worker`, rețea Docker `internal: true` și volume complet noi. Au fost folosite **aceleași ID-uri de imagine** ca în instalația existentă, fără pull, upgrade sau clonarea bazei reale. Aplicația nouă a fost inițializată numai pe DB goală de test.

- Niciun volum al instalației existente nu a fost atașat laboratorului.
- Nu au fost copiate utilizatori, solduri, fișiere de configurare sau documente reale.
- Au fost instalate în laborator arhivele existente, nemodificate: ZileSarbatoare 0.9.5, Holiday 1.9.4 și Attendance 1.11.0.
- Au fost create 15 utilizatori regular sintetici, roluri native de manager/angajat și patru calendare pentru probele normative. O probă CRUD separată a creat încă un calendar, pentru verificarea administrării native; estimarea de patru programe nu a fost transformată într-o limită artificială.
- O extensie temporară **Feasibility Probe**, versiunile 0.0.1–0.0.3, a verificat scopes, hooks, un Event minimal, meniu, API allowlist, Job și lifecycle. Nu definește entitățile Employee Management și nu modifică fluxurile Holiday/Attendance originale.
- Browserul de probă a rulat într-un container efemer separat, în aceeași rețea izolată, dintr-o imagine locală. Chromium: **153.0.8010.12**.
- TUVTK Theme nu a fost instalată în laborator. Integrarea vizuală completă cu această temă rămâne **NOT TESTED**.

La final, **cele trei containere ale laboratorului au fost oprite**, nu lăsate ca infrastructură suplimentară activă. Datele sintetice și dovezile temporare au fost păstrate pentru inspecție. Browserul efemer a fost eliminat automat. Containerele originale au aceleași ID-uri și timestamp-uri de pornire ca înaintea testelor.

**Diferență importantă față de arhitectură:** documentul folosește 10.0.10 ca baseline recomandat, dar instanța reală este **10.0.1**. Nu am actualizat-o. Rezultatele acestui raport nu sunt certificare runtime pentru 10.0.10.

## 2. Working Time Calendar — rezultate practice

### 2.1. API-uri native efectiv folosite

- Entitățile native `User`, `WorkingTimeCalendar`, `WorkingTimeRange` și relația `User.workingTimeCalendar`.
- `Espo\Tools\WorkingTime\CalendarFactory::create(...)` pentru calendarul normativ explicit.
- `CalendarUtilityFactory::create(...)` și `createForUser(...)` pentru compararea normei cu disponibilitatea personală.
- `CalendarUtility::isWorkingDay`, `getWorkingDays`, `getSummedWorkingHours`.
- `Extractor::extract(...)` pentru intervalele orare efective.
- `Espo\Core\Field\DateTime` și timezone `Europe/Bucharest` în probele de calcul.
- ORM native pentru fixture-uri; suplimentar, HTTP CRUD nativ pentru crearea și atribuirea WTC de către administrator.

Acestea au fost apelate în aplicația reală din container, pe schema și DB reale ale laboratorului, nu pe stubs.

### 2.2. Matricea verificărilor

| ID | Test executat | Rezultat observat | Verdict / compatibilitate |
| --- | --- | --- | --- |
| W01 | Creare patru WTC, distribuire la 15 regulari | Calendarele au fost salvate și reutilizate; angajații 1 și 5 aveau același `workingTimeCalendarId` | **PASS** — reutilizare fără copiere per angajat |
| W02 | `POST /WorkingTimeCalendar` și `PUT /User/{id}` ca admin sintetic | Ambele HTTP **200**, calendarul atribuit a fost returnat pe User | **PASS** — administrare și atribuire native |
| W03 | Manager regular încearcă `POST /WorkingTimeCalendar` | HTTP **403** | **PASS** — rolul managerului nu acordă administrare WTC |
| W04 | Citirea unei zile pentru programe de 4/6/8 ore | **4 / 6 / 8 ore**; sâmbăta obișnuită nu era lucrătoare | **PASS** |
| W05 | Program cu două intervale | `08:00–12:00`, `13:00–17:00`; total **8 ore** | **PASS** — nu este necesar calculator de intervale propriu |
| W06 | Normă noiembrie 2026, fără excepții | **21 zile**; 84 / 126 / 168 ore | **PASS** |
| W07 | WorkingTimeRange: 16 noiembrie liber și 7 noiembrie sâmbătă lucrătoare | Ziua de 16 nu era lucrătoare; sâmbăta era; totalul rămânea 21 zile/168 ore | **PASS** |
| W08 | Adăugare încă unei excepții nelucrătoare, 17 noiembrie | **20 zile / 160 ore** pentru WTC de 8 ore | **PASS**; calculul pe interval și suma verificărilor zilnice au coincis |
| W09 | ZileLibere sintetică, 30 noiembrie, citită prin serviciul public ZileSarbatoare | Contractul a întors `2026-11-30`; WTC singur considera data lucrătoare | **PASS** pentru citirea contractului; **RISK** dacă se presupune integrare automată |
| W10 | Unirea zilelor publice cu norma WTC, fără copiere în WorkingTimeRange | **20 zile**; 80 / 120 / 160 ore pentru 4h/6h/8h | **PASS** — compoziție de rezultate native, nu un al doilea calculator de program |
| W11 | Indisponibilitate personală pe 10 noiembrie pentru un angajat de 6h | Calendarul User: nelucrătoare, 20 zile/120 ore; calendarul normativ explicit: lucrătoare, 21 zile/126 ore | **PASS** — distincția este demonstrată; folosirea variantei User pentru debitul Holiday este **RISK** |
| W12 | HolidayRequest Approved sintetică pe 11 noiembrie, fără WorkingTimeRange personal aferent | Atât calendarul User, cât și cel normativ considerau data lucrătoare | **PASS** pentru comportamentul observat: Holiday original nu transformă automat această cerere în indisponibilitate WTC |
| W13 | Recalculare septembrie 2026 înainte/după schimbarea WTC 4h→6h | Aceleași **22 zile**, dar norma istorică a devenit **88→132 ore** | **FAIL** pentru cerința „istoric fix folosind numai WTC curent” |
| W14 | Reatribuire User la alt WTC, recalculând aceeași perioadă | Rezultatul perioadei s-a schimbat conform calendarului nou | **FAIL** pentru presupunerea că relația curentă păstrează automat istoricul atribuirii |
| W15 | Luni cu schimbare DST, program de 8h în zile lucrătoare | Martie 2026: **176 ore**; octombrie 2026: **176 ore** | **PASS** pentru aceste două scenarii, nu pentru orice program de noapte |
| W16 | Interval 2 noiembrie 00:00 → 3 noiembrie 00:00 | `getWorkingDays`: **2**; `getSummedWorkingHours`: **8** | **PASS** ca observație de semantică; **RISK** de off-by-one dacă se folosește aceeași limită fără normalizare |

În W10 am compus un set de date publice și rezultatul nativ pentru fiecare dată. Nu am implementat din nou regulile weekday/timeRanges/exceptions. Unirea cu ZileSarbatoare este necesară deoarece în această instalație WTC nu consumă automat entitatea `ZileLibere`.

Cererea Approved din W12 a fost introdusă ca fixture cu hooks ocolite, pentru a izola efectul unui record existent asupra WTC. **Nu** este dovadă a unui flux de aprobare valid. Separat, în §3 există o aprobare reală prin API-ul Holiday original.

### 2.3. Rezultate numerice de referință

Noiembrie 2026, program L–V, înaintea excepțiilor native suplimentare:

| Program | Zile native | Ore native | Zile după excluderea ZileLibere 30.11 | Ore după excludere |
| --- | --- | --- | --- | --- |
| 08:00–12:00 | 21 | 84 | 20 | 80 |
| 09:00–15:00 | 21 | 126 | 20 | 120 |
| 08:00–16:00 | 21 | 168 | 20 | 160 |
| 08:00–12:00 + 13:00–17:00 | 21 | 168 | 20 | 160 |

Sursa zilei publice a fost o înregistrare **sintetică** în ZileLibere. Nu a fost testată sincronizarea Nager.Date și nu a fost importat conținutul real al calendarului companiei.

### 2.4. Evitarea dublei scăderi a concediului

Rezultatul W11 arată concret că `createForUser` poate include indisponibilități personale. Nu trebuie folosit ca normă de debitare Holiday fără o semantică explicită.

Separarea adecvată este:

- **Normă:** WTC explicit atribuit, citit prin calendarul specific, plus zilele publice conform politicii ZileSarbatoare.
- **Absență efectivă:** HolidayRequest Approved/CancellationPending și regulile Attendance deja aprobate.
- **Disponibilitate personală CRM:** UserCalendar/WorkingTimeRange personale, dacă se folosesc pentru alte funcții CRM; nu sunt încă o reducere a normei de concediu.

Holiday original nu a creat WorkingTimeRange nici în proba de aprobare reală: după aprobarea propriei cereri, `isWorkingDay` continua să fie true pentru norma acelei zile. Nu recomand introducerea unei sincronizări Holiday→WorkingTimeRange doar pentru înlocuirea programului Attendance.

### 2.5. Istoricul — condiția nerezolvată de WTC singur

W13 nu este o presupunere: am calculat o lună **trecută**, septembrie 2026, am modificat calendarul sintetic și am recalculat-o. Rezultatul 88→132 ore dovedește că sursa curentă nu este o arhivă normativă imutabilă. Reatribuirea calendarului are aceeași limită conceptuală, observată în W14.

Condiția de adoptare este păstrarea **rezultatelor normative istorice read-only**, cel puțin pentru lunile înghețate și calculele de concediu deja contabilizate. Aceste rezultate pot fi snapshot-uri ale normei aplicabile și ale sursei WTC, nu calendare configurabile concurente. Trebuie definit explicit când se fixează norma unei perioade deschise și ce înseamnă o modificare/reatribuire ulterioară.

Această cerință **nu schimbă** dreptul managerului de corecție auditată într-o lună înghețată. O corecție explicită nu este echivalentă cu recalcularea tuturor lunilor din WTC-ul curent. Nu a fost implementat sau testat un mecanism de snapshot/înghețare Employee Management în această etapă.

### 2.6. Simplifică WTC codul existent?

**Da, pentru configurarea și calculul programului fix, cu o condiție de istoric.** Pentru 15 angajați și patru programe, WTC elimină necesitatea editorului propriu de ore normative, validării paralele de intervale și a unui motor propriu de weekday/exceptions. Probele demonstrează că nu trebuie rescris acel calcul.

Rămân necesare, fiind reguli distincte: remote/CIC/CCC, marcajele zilnice, completitudinea, absențele Holiday, unirea cu ZileSarbatoare, snapshot-urile istorice și exporturile. Integrarea trebuie să fie o citire/compunere mică, nu încă un serviciu de configurare WTC sau o copie a datelor sale editabile.

Înlocuirea poate fi **mai complexă pe termen scurt** decât tabela actuală cu start/end dacă se compară numai numărul de linii al calculatorului simplu L–V. Dar tabela actuală nu rezolvă nici ea imutabilitatea istorică. Nu este corect să atribuim întregul cost al istoricului lui WTC. Nu am implementat înlocuirea, deci nu pretind o reducere de LOC măsurată sau un benchmark final.

**Verdict WTC: CONDITIONAL.** Adoptarea este recomandată numai după aprobarea și verificarea persistenței normative istorice read-only, a limitelor de interval și a regulii normă vs disponibilitate. Nu recomand KEEP EXISTING ca fallback tacit și nu recomand menținerea simultană a două configurări de program.

## 3. ACL, separarea responsabilităților și administrarea delegată

### 3.1. Probe backend și HTTP

Sonda a folosit Roles/scopes native, ownership prin assignedUser, ACL implicit EspoCRM, `Espo\Core\Record\Hook\SaveHook` în `recordDefs` și `Espo\Core\Api\Action`. Guard-ul verifică actorul autentificat regular/activ și refuză admin; nu schimbă ACL global.

| ID | Test real | Rezultat | Interpretare |
| --- | --- | --- | --- |
| A01 | ACL nativ pentru admin | Scopes de manager/workspace/WTC și read pe recordul-probă: true | **PASS** — privilegiul nativ există; nu este suficient ca autorizare business |
| A02 | Admin încearcă POST record-probă și PUT pe record frozen | **403 / 403** prin record hook, deși ACL global permite | **PASS** — excluderea actorului admin este fezabilă fără modificarea securității globale |
| A03 | Admin accesează API settings delegat | **403** prin API action | **PASS** |
| A04 | Același admin creează și atribuie WTC nativ | **200 / 200** | **PASS** — restricția business nu elimină responsabilitatea tehnică |
| A05 | Manager regular citește recordul altui angajat | **200**, cu read all nativ | **PASS** |
| A06 | Angajat citește direct ID-ul recordului privat al altuia | **403**, prin ACL own | **PASS** — nu doar filtru în listă |
| A07 | Record-probă `frozen=true`: manager vs angajat proprietar | Manager: **200**; angajat: **403** | **PASS mecanism** de excepție managerială prin hook + scope; înghețarea lunară completă este **NOT TESTED** |
| A08 | Manager GET/PUT setare delegată allowlist | **200**, valoarea `manager-saved` a persistat | **PASS** |
| A09 | Manager injectează `siteUrl` în API-ul delegat | **400** | **PASS** — allowlist aplicată |
| A10 | Manager încearcă PUT Settings global și POST Role | **403 / 403** | **PASS** — fără escaladare la administrator global prin acele API-uri |
| A11 | Manager încearcă creare WTC | **403** | **PASS** |
| A12 | Selectare populație regular activă | 15 angajați sintetici din 18 User totale în laborator | **PASS** pentru selecția nativă; nu este încă filtrare implementată în toate rapoartele viitoare |

Laboratorul a avut două conturi admin sintetice, rezultate din pregătirea imaginii, și system user; niciunul nu a fost inclus în selecția de 15 regulari. În instalația existentă a fost observat un singur admin activ. Nu am testat migrarea unui User din regular în admin cu istoric de pontaj înghețat.

**Limită:** guard-ul demonstrat nu reprezintă implementarea tuturor verificărilor beneficiarului, bulk/link/import/portal sau a tuturor invariantenților ORM ai produsului. Acoperirea completă a acestora rămâne **NOT TESTED** și va fi necesară în implementare. Totuși, condiția `isAdmin` poate fi verificată și impusă efectiv în extensie, nu este blocată de privilegiile globale.

### 3.2. Autoaprobarea — probă pe Holiday Management original

În laborator, un manager sintetic regular a fost configurat ca aprobator în mecanismul Holiday existent; s-a creat un profil sintetic inițializat cu 21 zile. Nu au fost schimbate clasele sau fluxul extensiei.

1. `POST /HolidayRequest`, în numele utilizatorului autentificat, pentru 04.11.2026: **200**, status Pending.
2. Același regular a trimis decizia Approved prin `/HolidayManagement/requests/{id}/decision`: **200**.
3. Rezultat: status **Approved**, sold **20**, **o înregistrare ledger** asociată cererii și **un Attachment DOCX** legat de cerere.
4. Norma WTC a zilei a rămas lucrătoare, confirmând că acest flux nu creează automat WorkingTimeRange personal.

**PASS pentru posibilitatea autoaprobării pe fluxul existent.** Nu există aici o interdicție nativă EspoCRM care obligă solicitantul și aprobatorul să fie diferiți. Proba folosește lista de aprobatori existentă, nu viitorul scope `HolidayApproval`; combinarea lui cu noua politică de eligibilitate trebuie implementată și testată separat.

Un câmp attachmentMultiple nu trebuie evaluat doar prin `Entity::get` pe un record neîncărcat suplimentar: în această probă valoarea ORM simplă era null, dar numărătoarea Attachment legate de cerere a confirmat documentul. Nu am transformat acea citire incompletă într-un FAIL al generatorului.

### 3.3. Calendar comun fără informații private

Au fost executate două probe distincte:

**C01 — situația extensiei originale: FAIL de confidențialitate.** Un alt angajat sintetic a cerut direct `GET /HolidayRequest/{id}` pentru cererea fixture a colegului. Răspunsul a fost **200**, incluzând `description = PRIVATE SYNTHETIC NOTE`. Acest rezultat reproduce practic riscul P2, în configurația nouă de test fără ACL suplimentar de câmp. Nu am citit cereri reale și nu pretind că orice instanță cu orice rol/configurație este identică.

**C02 — Event minimal separat: PASS mecanism.** O entitate temporară FeasibilityAbsence, de tip Event, a conținut numai numele sinteticului și intervalul 11–12 noiembrie. Un coleg a primit:

- HTTP **200** prin API-ul Calendar nativ `GET /Activities?...&scopeList=FeasibilityAbsence`, cu `name`, `dateStartDate`, `dateEndDate` ale colegului;
- HTTP **200** pe detaliul minimal, fără câmpuri de documente, motive, sold sau relație expusă spre cererea privată;
- HTTP **403** pe recordul privat-probă al altui utilizator, protejat prin own ACL;
- HTTP **403** la download-ul DOCX privat al operației-probă, în timp ce proprietarul autorizat primea **200**.

Pentru afișarea markerului comun în Calendarul propriu s-a folosit mecanismul verificat `Services/{Entity}::getCalenderQuery`, cu **strict access control**, fără `skipAcl` pentru a ocoli securitatea. Este necesară o extensie îngustă a query-ului, deoarece query-ul generic are și filtrul utilizatorului calendarului.

Aceasta demonstrează fezabilitatea separării dataset-urilor cu ACL nativ. Nu este proiecția EmployeeAbsence implementată, nu certifică sincronizarea ei la toate tranzițiile și nu rezolvă singură expunerea actualului HolidayRequest. Entitatea publică nu trebuie să ofere o cale indirectă spre datele private.

### 3.4. Administrarea delegată și interfața minimă

În browser real au fost folosite `app.clientNavbar.menuItems`, `accessDataList`, o rută proprie `#EmployeeAdministration`, un controller diagnostic cu verificare regular/activ/ACL și **view-ul nativ `views/list`**, fără implementarea unui alt sistem de administrare.

| Probă browser | Observație | Verdict |
| --- | --- | --- |
| Manager regular | O singură intrare către #EmployeeAdministration; lista nativă s-a randat cu datele sintetice; fără page errors | **PASS** |
| Angajat fără scope | Zero intrări în meniul corespunzător; accesul URL direct a afișat **403 – You don't have access to this area** | **PASS** |
| Admin tehnic pe ruta delegată | Acces direct **403** prin guard-ul controllerului | **PASS** pentru excluderea din workspace, nu pentru ascundere ACL globală |
| API direct fără drept | Angajat/admin: **403** pe API-ul delegat, manager: **200** | **PASS** |
| Formularul final Employee Management | Nu a fost construit | **NOT TESTED**; ar depăși fezabilitatea cerută |

Nu am modificat `#Admin`. Atenție: în copia extensiilor actuale acea rută este **deja** suprascrisă de Attendance, conform P6. La navigarea managerului spre #Admin s-a observat păstrarea view-ului anterior, nu o dovadă clară a accesului la Admin nativ. Testul nu este declarat PASS pentru restaurarea rutei globale; aceasta rămâne de verificat după eliminarea override-urilor în implementarea autorizată.

`accessDataList` nu garantează excluderea unui administrator, deoarece ACL-ul său poate permite scope-ul. Admin a fost refuzat înainte de randarea workspace-ului. Numărul zero de linkuri pe pagina sa 403 nu demonstrează o politică nativă „hide for admin”.

**Reutilizare posibilă:** navbar, router, field views, layouts, liste și record views, modaluri, validare, componente de formular și ConfigWriter. **Custom minim necesar:** controller/rută delegată cu gate business, compunerea secțiunilor extensiei și API-uri allowlist care verifică aceleași capabilități. Nu sunt necesare pagini de WTC, Roles sau permisiuni globale în workspace și nu este necesar acces la `/Settings` global pentru regulari.

## 4. Jobs native și generarea documentelor

### 4.1. Ce s-a executat

În laborator au fost folosite efectiv:

- `Espo\Core\Job\JobSchedulerFactory`, `JobScheduler::setClassName`, `setGroup`, `setData`, `schedule`;
- `Espo\Core\Job\Job`, `Job\Data`, entitatea Job și daemon-ul nativ, fără runner propriu;
- tranzacții ORM și `forUpdate` pentru finalizarea unică a operației-probă;
- **generatorul DOCX original HolidayDocumentGenerator**, template-ul original și valori sintetice;
- entitatea `Attachment`, `Espo\Core\FileStorage\Manager` și download-ul nativ;
- o stare persistentă a operației-probă, separată de statutul tehnic Job.

Jobul diagnostic nu schimbă solduri sau cereri Holiday. Contorul său `effectCount` reprezintă un efect unic de test, **nu** un ledger Employee Management implementat.

### 4.2. Rezultate și timpi observați

| ID | Test executat | Rezultat | Verdict |
| --- | --- | --- | --- |
| J01 | Programare, apoi execuție prin daemon | 21:48:59 programare → 21:49:00 Success; operația Completed, un DOCX | **PASS** |
| J02 | Programare în daemon deja activ | Exemple la 21:49:26 → execuție 21:49:30, circa 4 secunde | **PASS** pentru observație; nu este SLA garantat |
| J03 | Eșec intenționat înaintea documentului | Job Failed și operație Failed; fără document și fără efect final | **PASS** — eșec detectabil |
| J04 | Retry controlat printr-un Job nou pe aceeași operație | Operația a ajuns Completed, contorul final a rămas **1** | **PASS** |
| J05 | Programarea repetată a unei operații deja finalizate | Au existat **trei Job-uri Success** pentru operația `normal`, dar un singur document final și `effectCount=1` | **PASS** pentru idempotency explicită; **RISK** dacă se presupune deduplicare de business nativă |
| J06 | JobScheduler în tranzacție urmată de excepție | Număr Job înainte/după: **0→0** | **PASS** — programarea a participat la rollback în configurația testată |
| J07 | Oprire forțată worker în timpul operației lente | După restart, Job-ul era Running, operația Processing, fără document și `effectCount=0` | **PASS** persistență; recuperarea imediată automată este **FAIL** dacă aceasta era presupusă |
| J08 | Recuperare nativă a Job-ului rămas Running | După pragul implicit de aproximativ 300 secunde, a fost observat Failed; operația a rămas Processing | **PASS** detecție tehnică; **RISK** fără reconciliere/stare recuperabilă în aplicație |
| J09 | Retry explicit după întrerupere | Programat 21:55:35, Success 21:55:40; operația Completed, două încercări totale, un efect și un DOCX | **PASS** |
| J10 | Job Pending la momentul restartului | A supraviețuit restartului și s-a finalizat; testul avea intenționat 25 secunde de lucru | **PASS** |
| J11 | Download imediat după detectarea Completed | Așteptare de observare **1,822 s**; apoi HTTP **200** în **267,61 ms**, DOCX de **16.333 bytes**, semnătură ZIP `PK` | **PASS** pentru disponibilitate HTTP; UI push/refresh este **NOT TESTED** |
| J12 | Download proprietar vs coleg | Proprietar autorizat **200**; coleg fără read **403** | **PASS** pentru aceste căi native de download |

Rezoluția timestamp-urilor DB este de o secundă; valorile subsecundă din J11 sunt măsurători ale probei PHP. Nu reprezintă percentile de performanță. Rezultatele de aproximativ 1–4 secunde au fost observate cu daemonInterval 10; sarcina, grupurile și timpii de pregătire pot modifica latența.

### 4.3. Ce a demonstrat întreruperea

Worker-ul laboratorului a fost oprit forțat la aproximativ 21:49:39, în timpul Job-ului început la 21:49:30. Nu a fost oprit niciun worker existent al companiei.

După restart, DB păstra Running/Processing. Ulterior, mecanismul nativ de verificare a proceselor inexistente a marcat Job-ul Failed, cu attempts ajuns la zero. Statutul operației de business **nu** s-a schimbat automat. Pentru eșecul obișnuit s-a observat și o încercare automată suplimentară, la zece secunde, înainte de retry-ul controlat. Deci politica de attempts trebuie cunoscută; nu se poate presupune „fără retry” sau „retry infinit”.

Retry-ul explicit a finalizat operația întreruptă o singură dată. Aceasta susține folosirea Jobs native, dar nu justifică presupunerea exact-once. Grupurile serializează execuțiile grupate; unicitatea efectului este în continuare responsabilitatea datelor și tranzacției de business.

Nu am simulat defectarea volumului DB, epuizarea discului, pierderea storage-ului, două worker-e independente în competiție sau crash-ul precis dintre `putContents` și commit. Aceste situații sunt **NOT TESTED / RISK**, nu PASS implicit.

### 4.4. Fluxul solicitat: Approved numai după document

**Fezabil pe infrastructura nativă, dar neimplementat în Holiday original.** Sonda a demonstrat ordinea Processing → generare/persistare Attachment → Completed; nu a introdus noul flux în Holiday.

Arhitectura aprobată descrie încă, în §6.6, aprobarea contabilă urmată de generarea asincronă. Cerința din această etapă este mai strictă: **Approved definitiv numai după salvarea documentului**. Raportul consemnează diferența; documentul arhitectural nu a fost modificat.

Organizarea minimă necesară, de aprobat înainte de implementare:

1. Managerul regular autorizat inițiază decizia. Se salvează snapshot-ul deciziei, actorul, revizia și o stare intermediară explicită, de exemplu `ApprovalProcessing`; se programează Job-ul nativ în aceeași tranzacție.
2. Rezervarea făcută la crearea cererii rămâne deținută. Nu se mai debitează soldul o dată la inițiere sau la retry. Cererea continuă să blocheze suprapunerile, dar nu este publicată drept concediu definitiv Approved.
3. Job-ul generează din snapshot și persistă documentul prin mecanismele native.
4. Sub lock și verificarea reviziei curente, o tranzacție scurtă salvează legătura documentului, tranziția finală Approved, starea operației și proiecția publică necesară. Actorul deciziei rămâne managerul, nu system user-ul runner-ului.
5. La eșec, cererea nu devine Approved; starea intermediară și eroarea sunt vizibile și recuperabile. Retry-ul autorizat programează Job nativ, nu îi dă managerului acces global la administrarea Jobs.
6. Un retry după finalizarea efectului returnează rezultatul existent. Cheia operației/reviziei și verificarea tranzacțională împiedică a doua modificare de sold/ledger sau publicarea unui document pentru o revizie depășită.

Starea intermediară trebuie tratată explicit în queue, overlap, retragere/anulare și în lipsa aprobării definitive; nu poate fi introdusă doar în enum fără reguli. Acesta este un necesar al cerinței noi, nu o invitație la un motor de workflow propriu.

DB și FileStorage nu devin o tranzacție comună. Un crash după scrierea fișierului și înainte de commit poate necesita cleanup compensator. Tranziția vizibilă trebuie să fie ultima parte a finalizării, iar eroarea nu trebuie ascunsă în statutul tehnic Job. Ledger-ul Holiday real în acest flux asincron rămâne **NOT TESTED**, deoarece fluxul nu a fost implementat.

## 5. Compatibilitate și lifecycle

Testele de instalare și lifecycle au fost executate prin comenzile native EspoCRM, în laborator, nu prin copiere manuală în directoarele aplicației.

| ID | Mecanism/test | Rezultat observat | Verdict |
| --- | --- | --- | --- |
| L01 | `bin/command extension --file=...` pentru cele trei arhive originale | ZileSarbatoare 0.9.5, Holiday 1.9.4, Attendance 1.11.0 instalate | **PASS** pe 10.0.1; nu implică certificarea tuturor funcțiilor lor |
| L02 | Manifest cu dependență inexistentă | Installer-ul a refuzat pachetul; **0** extensii instalate cu numele sondei de dependență lipsă | **PASS** |
| L03 | Dependență reală `Zile Sărbătoare >=0.9.5` | Sonda s-a instalat | **PASS** — identificarea după numele real, inclusiv diacritice, funcționează |
| L04 | Înregistrare entityDefs/scopes/recordDefs și controller | Tabel creat, CRUD HTTP funcțional, ACL și hook executate | **PASS** |
| L05 | Upgrade sondă 0.0.1→0.0.2→0.0.3 | Hook BeforeUninstall vechi executat; AfterInstall cu isUpgrade=true; fără AfterUninstall în upgrade | **PASS**, confirmă particularitatea din arhitectură |
| L06 | Date și setare la upgrade | La proba 0.0.2→0.0.3: **10→10** rânduri; `manager-saved` păstrată | **PASS** |
| L07 | Rebuild și clear-cache, apoi API/metadata | Comenzi reușite; metadata, guard-ul și datele au rămas funcționale | **PASS** |
| L08 | Uninstall complet numai al sondei | Codul modulului dispărut; tabela avea în continuare **13 rânduri**, setarea era `manager-saved`; BeforeUninstall și AfterUninstall executate | **PASS** pentru retenția proiectată în acea sondă |
| L09 | Reinstall sondă și restart aplicație laborator | **13 rânduri**, setare păstrată; `app-check` OK; metadata reînregistrată | **PASS** |
| L10 | Upload prin interfața web Extension Manager | Nu a fost executat separat; s-a folosit CLI nativ al aceleiași infrastructuri | **NOT TESTED** ca UI |
| L11 | Upgrade core 10.0.1→10.0.10 | Nu a fost executat | **NOT TESTED** |
| L12 | Upgrade eșuat cu recuperare completă fișiere/config/DB | Nu a fost simulat | **NOT TESTED / RISK** |

Numărul 13 din L08/L09 este numărul rândurilor **entității-probă**, nu numărul angajaților. Creșterea de la 10 la 13 între L06 și L08 provine din probe HTTP și Jobs suplimentare, nu din duplicarea datelor la upgrade.

Retenția observată depinde de hooks care nu șterg date: installer-ul nu poate proteja soldurile de un BeforeUninstall custom distructiv. Nu recomand ștergerea datelor în acele hooks și nu presupun rollback atomic fișiere+DB+config.

Sonda a avut și două corecții de pregătire: a fost completată înregistrarea controllerului Record necesar, iar contractul-interface ZileSarbatoare a fost consumat prin serviciul concret cu dependențe injectate, nu prin `InjectableFactory::create(interface)`. Rezultatele incomplete ale acelor rulări inițiale **nu** sunt considerate FAIL-uri ale ACL sau WTC. Inițializarea imaginii de laborator a necesitat și username-ul admin așteptat de scriptul local al imaginii; nu a fost reparat sau modificat scriptul instalației existente.

**Compatibilitate de decis:** manifestul recomandat în arhitectură ar începe la 10.0.10 și nu ar accepta instalația reală 10.0.1. Înainte de release trebuie aleasă explicit fie certificarea 10.0.1 în plaja produsului, fie un upgrade core separat și autorizat, urmat de repetarea probelor pe versiunea țintă. Acest raport nu autorizează niciuna automat.

## 6. Reutilizarea codului existent

Clasificarea de mai jos rezultă din audit, revizuirea surselor și probele de integrare indicate. Nu înseamnă că toate exporturile sau toate regulile au fost retestate end-to-end în această sesiune.

| Categorie | Componente existente | Tratament justificat |
| --- | --- | --- |
| **Păstrate fără modificări funcționale** | `HolidayBalance/BalanceMath.php` | Aritmetica soldurilor nu este înlocuită de WTC sau Jobs |
| **Păstrate fără modificări funcționale** | `HolidayRequest/BookingDatePolicy.php` și validările de perioadă încă aplicabile | Separarea actorilor nu justifică schimbarea regulilor de rezervare aprobate |
| **Păstrate fără modificări funcționale** | `HolidayDocumentGenerator.php` și `Resources/templates/holiday-request.docx` | Generatorul original a produs efectiv DOCX în Job; se schimbă coordonarea, nu template-ul sau renderer-ul fără motiv |
| **Păstrate fără modificări funcționale** | `AttendanceXlsxGenerator`, `AttendancePdfGenerator`, regulile de paginare și escapare | WTC schimbă sursa normei, nu cerința documentului matricial; output-ul complet XLSX/PDF nu a fost retestat aici |
| **Adaptate pentru namespace** | Clasele păstrate, API actions subțiri, traducerile, layouts și field views utile | Schimbarea PHP namespace/AMD IDs/paths este mecanică; nu este motiv de rescriere a logicii |
| **Adaptate pentru namespace** | Jobul `ProcessHolidayResets` și fațada sa de apel | Runner-ul rămâne nativ; filtrarea beneficiarilor și autorizarea operațiilor interactive se revizuiesc distinct |
| **Refactorizate pentru mecanisme native** | Cele două `NonWorkingDayProvider` | Un singur consum al contractului public ZileSarbatoare; nu queries duplicate după schema internă |
| **Refactorizate pentru mecanisme native** | `ApprovedHolidayProvider`, overview-urile Attendance/Holiday | Semantica Approved/CancellationPending coerentă, selecție regular, citiri în lot; WTC nu înlocuiește sursa absenței |
| **Refactorizate pentru mecanisme native** | `AttendanceService`, `AttendanceOverviewService`, configurarea programului | Normă din WTC, păstrând marcajele, remote/CIC/CCC și regulile de completitudine; fără editor de ore concurent |
| **Refactorizate pentru mecanisme native** | `HolidayBalanceService` și hooks de rezervare/ajustare/anulare | Păstrarea ledger-ului și a idempotency; separare limitată a responsabilităților, stare curentă sub lock și un singur owner al efectelor |
| **Refactorizate pentru mecanisme native** | Controllerul HolidayRequest care forțează lista proprie | ACL/Select native pentru toate căile de citire; lista „ale mele” nu este graniță de securitate |
| **Refactorizate pentru mecanisme native** | API-urile și view-urile de settings/profiles | Workspace propriu, actor regular, capabilități native, allowlist ConfigWriter; fără WTC/Roles globale |
| **Refactorizate pentru mecanisme native** | `HolidayApprovalDocumentService` | Snapshot și orchestrare Job/stare intermediară; renderer-ul și Attachment/FileStorage rămân reutilizabile |
| **Refactorizate pentru mecanisme native** | AfterInstall/BeforeUninstall și configurarea navigării | Un lifecycle de pachet, păstrarea datelor/config și tratarea grupurilor native |
| **Eliminate ca redundante** | Editorul/validatorii/setările pentru start/end normativ Attendance | WTC deține exclusiv norma; nu eliminăm metadatele remote/CIC/CCC odată cu editorul |
| **Eliminate ca redundante** | Calculatorul L–V duplicat ca sursă normativă, după adoptarea WTC | Calculul normei devine nativ; păstrăm limitele de business, de exemplu perioadă maximă și lipsa zilelor eligibile |
| **Eliminate ca necorespunzătoare** | Granturi mandatory Holiday/Calendar, chei CRUD fără efect în aclDefs, override-urile Admin și injecțiile globale de meniu | Audit și probele ACL/meniu arată că nu sunt necesare și pot expune date |
| **Eliminate dacă nu mai sunt necesare** | Override-ul Holiday al calendarView | Preferăm Event/record metadata; orice extensie finală de view trebuie compusă explicit cu ZileSarbatoare, nu să o înlocuiască accidental |
| **Rescrise numai justificat** | Secțiunile concurente bazate pe fetched state și coordonarea aprobării înainte de document | Necesită schimbare punctuală pentru corectitudine și cerința nouă Approved-after-document; nu justifică rescrierea întregii contabilități |

Nu este justificată înlocuirea ledger-ului cu un sistem nou, rescrierea exporturilor în numele unificării sau introducerea unui broker de mesaje, event bus, workflow engine ori strat repository universal.

## 7. Probleme tehnice confirmate și relația cu auditul P1–P11

### 7.1. Probleme/limite confirmate în această sesiune

| Problemă | Dovezi | Impact real |
| --- | --- | --- |
| Baseline diferit | Instalație 10.0.1, arhitectură bazată pe 10.0.10 | Plaja manifest și versiunea de certificare trebuie decise explicit |
| WTC curent nu păstrează norma istorică | Septembrie: 88→132 ore după editare | Nu poate fi sursa unică pentru recalcularea lunilor înghețate fără rezultate istorice persistate |
| Normă vs disponibilitate personală | UserCalendar exclude ziua personală; SpecificCalendar nu | Risc de debit Holiday calculat greșit dacă se folosește disponibilitatea drept normă |
| ZileLibere nu este consumată automat de WTC | 30 noiembrie era lucrătoare în WTC, deși prezentă în contractul ZileSarbatoare | Necesită compoziție explicită de citire, nu a doua configurație de program |
| Semantica limitelor diferă între metode | Aceeași limită la miezul nopții: două zile, opt ore | Normalizează datele inclusive și intervalele de ore în mod explicit |
| P2: read all Holiday poate expune nota privată | Coleg → GET cerere → HTTP200 cu nota sintetică | Calendarul comun nu trebuie rezolvat acordând tuturor citirea cererii complete |
| Admin trece ACL global | ACL manage/workspace/read true, dar hook/action pot refuza 403 | Condiția regular/non-admin trebuie să fie business validation, nu presupusă din Role |
| Jobs nu oferă exact-once de business | Trei Job-uri au executat aceeași operație finalizată | Cheie/revizie și idempotency tranzacțională obligatorii |
| Restart nu finalizează automat starea aplicației | Job devenit Failed; operație rămasă Processing până la retry explicit | Stare recuperabilă, observabilitate și retry controlat necesare |
| P11 persistă în metadata actuală | calendarView efectiv: `holiday-management:views/calendar/calendar` | ZileSarbatoare nu câștigă automat decorarea când ambele extensii sunt instalate |
| #Admin este deja interceptată de extensiile originale | Observație browser + definițiile auditate | Proba rutei delegate nu certifică restaurarea Admin în produsul viitor |

### 7.2. Ce NU închide acest raport din audit

| Audit | Acoperire în fezabilitate |
| --- | --- |
| P1 | Hooks/ACL/guards demonstrate; audit complet CRUD/link/mass actions al produsului viitor **NOT TESTED** |
| P2 | Expunere privată reprodusă; separarea Event minimal vs record privat demonstrată; sursa originală nu a fost corectată |
| P3 | Tranzacție Job și finalizare unică demonstrată; cursele reale edit/delete/approve asupra Holiday **NOT TESTED** |
| P4 | Regula de stări nu a fost modificată; ciclul complet anulare→Attendance **NOT TESTED** |
| P5 | Rezervarea după Cancelled **NOT TESTED** în această etapă |
| P6 | Meniu/rută proprie demonstrate; override-urile originale nu au fost eliminate |
| P7 | Autorii modificați de ORM sunt vizibili în probe; auditul complet de câmp și istoricul normei/freeze **NOT TESTED** |
| P8 | DOCX în Jobs, rollback programare, retry și download demonstrate; crash între storage și commit și noul ledger asincron **NOT TESTED** |
| P9 | Nu a fost făcut query profiling al produsului unificat; volumele mici de test nu sunt benchmark |
| P10 | Lifecycle nativ și retenție demonstrate pe sondă; toate cazurile de grupuri/navbar ale viitorului pachet **NOT TESTED** |
| P11 | Owner-ul scalar confirmat; compunerea vizuală finală și toate modurile Calendar **NOT TESTED** |

## 8. Modificările necesare arhitecturii propuse

Această secțiune este lista de ajustări de supus aprobării. **Nu am modificat `UNIFIED-EXTENSION-ARCHITECTURE.md`.**

1. **Versiune de certificare:** consemnarea versiunii instalate 10.0.1 și alegerea plajei suportate. Nu declarăm rezultatele 10.0.1 drept verificare de runtime pentru 10.0.10 și nu actualizăm producția implicit.
2. **WTC:** confirmarea folosirii exclusive a normei native, cu calendar explicit atribuit tehnic. Separarea SpecificCalendar normativ de UserCalendar cu indisponibilități și compunerea cu ZileSarbatoare trebuie făcute explicit.
3. **Istoric:** adoptarea unei persistențe read-only a normei aplicabile perioadelor înghețate și calculelor Holiday deja fixate. Nu un editor/scheduler paralel și nu un istoric presupus existent în relația User curentă.
4. **Documente:** înlocuirea semanticii „Approved, apoi document asincron” cu o stare intermediară recuperabilă și **Approved definitiv numai după document persistat**, conform cerinței curente. Rolurile, autoaprobarea, soldul rezervat și regulile concediului rămân cele aprobate.
5. **Jobs:** definirea retry-ului de business, a modului de afișare a erorii și a tratamentului operațiilor Processing când Job-ul tehnic e Failed. Fără acces global Jobs pentru manager și fără exact-once presupus.
6. **Securitate:** actor regular/activ și beneficiar eligibil sunt condiții distincte, suplimentare ACL. Confirmarea practică a actorului nu elimină necesitatea testării tuturor căilor CRUD/link/bulk și a joburilor interne.
7. **Calendar:** păstrarea dataset-ului public minimal și a cererii private; un query Calendar îngust cu strict ACL poate fi necesar pentru markerii tuturor colegilor. Restaurarea #Admin și compoziția Calendar trebuie verificate după scoaterea codului vechi din viitorul pachet.
8. **Timezone:** instanța actuală folosește UTC, probele normative au folosit Europe/Bucharest. Timezone-ul de business și conversia date/calendar trebuie confirmate explicit înainte de go-live; nu a fost schimbată configurația existentă.

Nu este necesară schimbarea ledger-ului, a renderer-ului DOCX, a formatelor XLSX/PDF, a regulilor CIC/CCC, a autoaprobării permise sau a dreptului managerului de corecție în luni înghețate pentru a adopta aceste mecanisme.

## 9. Verdict privind pregătirea pentru implementare

### WTC: **CONDITIONAL**

**Condiție exactă:** norma istorică necesară lunilor înghețate și cererilor deja contabilizate trebuie fixată read-only, fără a fi recalculată din WTC sau atribuirea User curente; în plus, consumatorii trebuie să folosească norma explicită, nu disponibilitatea personală, și să normalizeze limitele de interval. Calculul nativ pentru programele 4/6/8 ore și patru modele fixe a trecut probele.

WTC poate elimina un mecanism custom real și simplifică administrarea programelor. Nu îl recomand dacă înlocuirea ar păstra și editorul de ore Attendance ori ar presupune că WTC rezolvă singur istoricul. Dacă snapshot-urile read-only nu sunt acceptate, verdictul nu se transformă automat în ADOPT WTC sau KEEP EXISTING; este necesară o decizie de produs asupra istoricului.

### Arhitectură generală: **fezabilă condiționat; nu încă validată integral pentru producție**

Avem dovezi reale pentru infrastructura nativă: program, ACL, hook/action guard, rută delegată cu UI nativ, Event minimal, generatorul DOCX reutilizat, Jobs, retry, persistență și lifecycle. Nu este justificat un framework paralel sau rescrierea completă a celor două extensii.

Înainte de autorizarea implementării trebuie închise explicit trei decizii:

- versiunea suportată: instalația 10.0.1 versus baseline-ul propus 10.0.10;
- persistența normei istorice fără o a doua sursă editabilă;
- starea intermediară și finalizarea Approved după document, inclusiv recuperarea Jobs și rezervarea soldului o singură dată.

După aceste decizii, se poate implementa incremental pe mecanismele demonstrate. Validarea produsului final va necesita separat cursele concurente Holiday, snapshot/freeze, toate căile de autorizare, compoziția Calendar/temă și failure recovery la frontiera DB–storage. Nu au fost inventate PASS-uri pentru aceste funcționalități încă inexistente.

## Anexa A — Dovezi, reproducere și integritatea mediului

Directorul temporar de probe este `/tmp/employee-feasibility-20261020-yDmmnL`. Conține exclusiv scripturi, pachete și date sintetice pentru acest laborator; nu este un proiect nou livrat în repository.

| Dovadă | Fișier temporar |
| --- | --- |
| Configurație laborator | `compose.yaml` |
| Teste native / fixture-uri / HTTP | `probe.php` |
| Construcția arhivelor-probă | `make-package.php` |
| Probe browser | `browser.cjs`, `browser-results.json`, `browser-*.png` |
| WTC bază, excepții, intervale | `wtc-results.jsonl` |
| ZileSarbatoare, disponibilitate, istoric, ACL | `wtc-tail-results.jsonl` |
| Lună trecută, DST, capete, normă cu sărbători | `wtc-extra-results.jsonl` |
| API, Event comun, download proprietar | `http-final-results.jsonl` |
| Autoaprobare originală, WTC HTTP admin, download refuzat colegului | `self-approval-results.jsonl` |
| Stare imediat după restart | `jobs-after-restart.jsonl` |
| Job Failed vs operație rămasă Processing | `jobs-final.jsonl` |
| Retry reușit și efect unic după întrerupere | `jobs-recovered.jsonl`, `interruption-retry.jsonl` |
| Disponibilitate document măsurată | `immediate-download.jsonl` |
| Ordinea hooks upgrade/uninstall | `lifecycle.log` |

Unele măsurători de inventar/lifecycle sunt în ieșirile comenzilor din conversație, nu într-un fișier separat. Rulările inițiale incomplete au fost păstrate ca trasă de diagnostic, nu folosite pentru concluzii finale.

Comenzile efectiv folosite au inclus `docker ps`, `docker compose ls`, `docker inspect`, `docker top`, SELECT-uri read-only pe instanța existentă, apoi în laborator `bin/command extension --file=...`, `rebuild`, `clear-cache`, `app-check`, `extension -u --name="Feasibility Probe"`, probe PHP/HTTP, daemon și restarturi numai ale containerelor de test. La sfârșit a fost executat `docker compose ... stop` numai cu fișierul laboratorului. Nu s-au folosit prune, ștergeri de volume existente sau operații SQL destructive pe instalația companiei.

Arhitectura aprobată are același SHA-256 înainte/după:

```text
c505011646a4df558503fe1f22e11b3e7f2c0a23f3f4720c2576c7e9319c163f
```

Nu există modificări tracked ale extensiilor în worktree. Fișierul de arhitectură și directorul `tmp/` erau deja untracked înaintea acestei etape. Singurul livrabil nou din workspace este acest raport. Datele temporare ale laboratorului au fost păstrate, nu purjate, astfel încât dovezile să poată fi revizuite fără afectarea datelor existente.
