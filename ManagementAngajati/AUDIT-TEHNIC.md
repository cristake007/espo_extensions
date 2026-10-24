# Audit tehnic — ManagementAngajati

Data auditului: 2026-10-22  
Versiune EspoCRM verificată: 10.0.10, din `site/`  
Domeniu: codul runtime PHP, metadata, JavaScript și template-urile extensiei din `src/files/`.

## Rezumat executiv

Extensia folosește deja corect o parte importantă din infrastructura EspoCRM: entități și metadata, ACL pe scope, `mandatory.scopeLevel`, Record API pentru pagina personală, `SelectBuilder` cu strict ACL, Working Time Calendar, hooks Record, tranzacții, blocări de rând, field views native pentru date, colecții și modele frontend, traduceri și notificări native.

Auditul a identificat trei priorități reale de reducere a codului paralel:

1. validarea tipurilor `date` și `enum` este ocolită pe comenzile manageriale care salvează direct prin ORM; listele `P/D/CM` sunt duplicate în PHP și JavaScript;
2. `AccesAngajatiCondica` reproduce manual Own/Team/All, deși EspoCRM are evaluare nativă pe entitate; înaintea înlocuirii trebuie însă aliniată proprietatea `teams` a înregistrărilor `Prezenta`;
3. validarea `ConfiguratieCondicaAngajat` este conectată de două ori prin Record hooks și este apelată încă o dată manual în salvarea bulk; un hook ORM nativ poate acoperi toate căile de salvare.

Mai există două simplificări frontend cu risc mic: `checkModel` pentru permisiunea unei înregistrări existente și field views native `bool` pentru opțiunile Remote/CIC.

Nu există CSS custom în extensie la data auditului. Nu recomand înlocuirea proiecțiilor lunare, a înghețării, a corecțiilor auditate, a integrării CIC/Remote sau a endpointurilor agregate: EspoCRM 10.0.10 nu oferă un mecanism nativ funcțional echivalent.

### Priorități

- **P1** — corectitudine sau risc ACL/validare; recomandat înaintea extinderii funcționalității.
- **P2** — reducere clară de duplicare, cu risc controlabil.
- **P3** — curățare internă; beneficiu redus și fără urgență funcțională.

## Backend PHP

### B1 — Validarea nativă a câmpurilor este ocolită de salvările manageriale

**Prioritate:** P1  
**Beneficiu:** mare  
**Risc al modificării:** redus–mediu

#### Cod relevant

- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/MarcatorPrezentaCondica.php:17,29-41,105-116`
- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/CorectorLunaCondica.php:19,40-48,132-145`
- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/ReguliConfiguratieCondicaAngajat.php:39-42,84-87,117-122`
- `src/files/custom/Espo/Modules/ManagementAngajati/Resources/metadata/entityDefs/Prezenta.json:12-33`

`P`, `D` și `CM` sunt declarate corect în metadata câmpului `codPrezenta`, dar sunt repetate în două constante PHP. Endpointul managerial salvează cu `EntityManager::saveEntity`, deci nu trece prin validarea executată automat de Record Service. Același lucru este valabil pentru formatul câmpurilor de tip `date`.

Un efect de margine concret: `MarcheazaPrezentaCondica` verifică dacă data este șir, dar nu verifică formatul nativ al datei înainte ca `ValidatorPrezenta.php:73` să construiască un `DateTimeImmutable`. Un payload invalid poate ajunge la o eroare generică în locul unui răspuns de validare 400.

#### Mecanism nativ verificat

- `site/application/Espo/Core/Record/Service.php:672,758` apelează validarea nativă la create/update.
- `site/application/Espo/Core/FieldValidation/FieldValidationManager.php:65-99,164-207` oferă `process` și `check` și poate fi folosit și dintr-o comandă custom.
- `site/application/Espo/Resources/metadata/fields/enum.json:1-35` declară validarea obligatorie `valid` pentru enum.
- `site/application/Espo/Classes/FieldValidators/EnumType.php:55-97` citește opțiunile direct din `entityDefs`/`Defs`.
- `site/application/Espo/Classes/FieldValidators/DateType.php:49-65` verifică formatul de dată cu tipul nativ Espo.

#### Înlocuire sigură

Comenzile care salvează direct prin ORM trebuie să invoce `FieldValidationManager` pentru validările structurale înaintea regulilor de business. Pentru păstrarea mesajelor actuale, rezultatul lui `check` poate fi mapat la aceleași excepții traduse; nu este obligatorie schimbarea contractului HTTP.

Regulile custom rămân necesare:

- fără viitor;
- lună editabilă/închisă;
- zi lucrătoare WTC;
- sărbătoare legală;
- CIC;
- conflict cu o prezență existentă;
- motiv obligatoriu pentru corecția lunii închise.

#### Echivalență și cazuri-limită

- Record API personal beneficiază deja de validarea nativă; aceasta nu trebuie apelată încă o dată în Record hooks.
- Comenzile manageriale trebuie testate cu dată imposibilă, format greșit, cod necunoscut, valoare `null`, câmp absent și cod valid.
- `CorectorLunaCondica` trebuie să păstreze mesajul și regula sa agregată de corecție, chiar dacă validitatea enum vine din metadata.
- Eliminarea constantelor este sigură numai după ce ambele căi ORM folosesc metadata/validatorul nativ.

### B2 — ACL Own/Team/All este reimplementat manual și nu are exact semantica ACL a entității

**Prioritate:** P1  
**Beneficiu:** foarte mare — elimină un sistem ACL paralel și repară comportamentul latent pentru Team  
**Risc al modificării:** mare; necesită migrare controlată și teste de integrare

#### Cod relevant

- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/AccesAngajatiCondica.php:19-49`
- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/CondicaPrezentaLunara.php:357-375,382-415,437-458`
- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/MarcatorPrezentaCondica.php:47-77,105-116`
- `src/files/custom/Espo/Modules/ManagementAngajati/Resources/metadata/entityDefs/Prezenta.json:81-108`

Helper-ul citește nivelul ACL și consideră Team atunci când managerul și utilizatorul-angajat au cel puțin o echipă comună. ACL-ul nativ Espo nu evaluează echipele utilizatorului țintă; el evaluează `teamsIds` ale înregistrării `Prezenta`.

În plus, înregistrările create prin `MarcatorPrezentaCondica` și `CorectorLunaCondica` setează `assignedUserId`, dar nu setează `teams`. `CondicaPrezentaLunara::obtinePrezente` folosește corect `withStrictAccessControl`; pentru un rol Team, înregistrările fără `teams` pot fi eliminate de query chiar dacă helper-ul custom a permis afișarea angajatului. Cu rolurile actuale All problema este mascată.

#### Mecanism nativ verificat

- `site/application/Espo/Core/AclManager.php:299-358` oferă `checkEntity`, `checkEntityRead`, `checkEntityCreate` și `checkEntityEdit`.
- `site/application/Espo/Core/Acl/DefaultAccessChecker.php:75-90,147-160` aplică nivelurile scope pe o entitate și delegă ownership-ul.
- `site/application/Espo/Core/Acl/DefaultOwnershipChecker.php:53-113` verifică Own prin `assignedUserId` și Team prin `teamsIds` ale entității.
- `site/application/Espo/Core/Record/Defaults/DefaultPopulator.php:75-76,111-153,196-207` poate seta utilizatorul curent și echipa sa implicită pe fluxul Record. Acesta este și motivul pentru care Record Service nu poate fi folosit nemodificat pentru crearea unei prezențe în numele altui angajat.
- `CondicaPrezentaLunara.php:395` folosește deja mecanismul nativ corect pentru query-urile de înregistrări existente: `withStrictAccessControl()`.

#### Înlocuire condiționată, funcțional echivalentă

Nu este sigur să ștergem direct `AccesAngajatiCondica`. Ordinea corectă este:

1. definirea proprietății native a unei prezențe: `assignedUserId = angajat`, iar `teams` trebuie să reflecte echipele pe baza cărora se acordă acces Team;
2. popularea consecventă a `teams` la toate căile de creare, inclusiv endpointul managerial și corecția lunii închise;
3. alinierea înregistrărilor deja create;
4. pentru o înregistrare existentă, folosirea exclusivă a `Acl::checkEntityRead/Edit`;
5. pentru o celulă încă fără înregistrare, construirea unei entități `Prezenta` nesalvate cu `assignedUserId` și `teamsIds` corecte, apoi delegarea deciziei către `AclManager::checkEntityRead/Edit`; permisiunea Create rămâne verificată separat;
6. eliminarea traducerii manuale Own/Team/All numai după testarea matricei complete.

Nu trebuie folosit ACL-ul entității `User` ca substitut: cerința este ca accesul efectiv la date să fie controlat de `Prezenta`.

#### Matrice minimă de regresie

- niveluri No/Own/Team/All pentru Read și Edit;
- angajatul curent, angajat în aceeași echipă, angajat în altă echipă;
- celulă fără înregistrare și prezență deja existentă;
- înregistrare creată personal, managerial și prin corecție;
- scope `CondicaPrezenta` Yes/No și `GestionareAngajati` Yes/No;
- administrator, care trebuie să păstreze comportamentul ACL nativ;
- query strict și verificare individuală trebuie să întoarcă aceeași decizie.

### B3 — Aceeași validare de configurație este cablată prin Record hooks și apelată manual

**Prioritate:** P2  
**Beneficiu:** mare — un singur punct de aplicare pentru toate salvările  
**Risc al modificării:** mediu

#### Cod relevant

- `src/files/custom/Espo/Modules/ManagementAngajati/Resources/metadata/recordDefs/ConfiguratieCondicaAngajat.json:2-7`
- `src/files/custom/Espo/Modules/ManagementAngajati/Classes/RecordHooks/ConfiguratieCondicaAngajat/InainteDeCreare.php:11-19`
- `src/files/custom/Espo/Modules/ManagementAngajati/Classes/RecordHooks/ConfiguratieCondicaAngajat/InainteDeActualizare.php:11-19`
- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/SalvatorSetariCondica.php:81-109`

Cele două Record hooks sunt wrappers identice, iar salvarea bulk repetă explicit apelul validatorului deoarece `saveEntity` nu execută Record hooks.

#### Mecanism nativ verificat

- `site/application/Espo/Core/HookManager.php:42-80,144-205` descoperă hooks de modul în `Espo\Modules\{Module}\Hooks\{EntityType}`.
- `site/application/Espo/Core/Repositories/Database.php:350-356` execută `beforeSave` prin HookManager pentru salvările repository/ORM.
- Interfața oficială este `site/application/Espo/Core/Hook/Hook/BeforeSave.php:37-50`.

#### Înlocuire sigură

Un singur hook ORM `beforeSave` pentru `ConfiguratieCondicaAngajat` poate apela validatorul existent. El rulează atât pentru Record API, cât și pentru `EntityManager::saveEntity`, astfel încât cele două Record hooks și apelul manual devin redundante.

#### Echivalență și cazuri-limită

- Validatorul de business rămâne neschimbat; se schimbă doar punctul de integrare.
- Excepțiile traduse BadRequest/Conflict propagate de validator rămân aceleași.
- Hook-ul trebuie executat o singură dată; migrarea trebuie făcută atomic pentru a evita dublarea validării/query-urilor.
- Se testează create/update prin Record API, salvarea bulk, dezactivarea ambelor opțiuni, unicitatea per angajat, CIC în trecut și conflictul cu prezențe existente.
- ACL rămâne în controller/API și în Record Service; hook-ul ORM nu trebuie să introducă verificări ACL proprii.

### Observație ACL — Citirea și scrierea condicii nu cer aceeași combinație de scopes

**Prioritate:** P1 pentru clarificare și testare  
**Tip:** inconsistență de politică, nu înlocuire de cod custom

- citirea verifică `CondicaPrezenta` + Read pe `Prezenta`: `Api/ObtineCondicaPrezentaLunara.php:21-31`;
- controllerul frontend verifică aceeași combinație: `controllers/condica-prezenta.js:6-16`;
- marcarea, corecția și închiderea verifică suplimentar `GestionareAngajati`: `Api/MarcheazaPrezentaCondica.php:22-28`, `CorecteazaLunaCondica.php:22-28`, `InchideLunaCondica.php:23-31`.

Un utilizator cu `CondicaPrezenta: Yes`, acces adecvat la `Prezenta`, dar `GestionareAngajati: No` poate deschide matricea și poate primi Forbidden numai la acțiune. Acest lucru trebuie decis explicit înaintea refactorului ACL:

- dacă `GestionareAngajati` rămâne permisiunea managerială comună, ea trebuie aplicată consecvent și la citire/frontend; mecanismul oficial `app.acl.mandatory.scopeLevel` poate exprima moștenirea, dar ar elimina independența setării `CondicaPrezenta` dacă este aplicat direct acelui scope;
- dacă cele două meniuri trebuie să rămână permisiuni independente, operațiile condicii nu trebuie să depindă accidental de permisiunea paginii de setări.

Nu recomand o modificare automată în audit deoarece cele două variante nu sunt funcțional echivalente din perspectiva Roles. În ambele variante trebuie folosite numai `Acl::checkScope`, metadata ACL și verificările de entitate native, fără verificarea numelui rolului.

### B4 — Calcularea numelui este duplicată deoarece Record hook-ul nu acoperă salvările ORM

**Prioritate:** P2  
**Beneficiu:** mediu  
**Risc al modificării:** mediu

#### Cod relevant

- `src/files/custom/Espo/Modules/ManagementAngajati/Classes/RecordHooks/Prezenta/StabilesteNumele.php:12-21`
- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/MarcatorPrezentaCondica.php:105-116`
- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/CorectorLunaCondica.php:132-145`

Aceeași regulă „nume angajat · dată” apare în trei căi de creare.

#### Mecanism nativ verificat

Hook-ul ORM `beforeSave` descris la B3 rulează și la salvările directe. Formula nu este un substitut sigur aici: `site/application/Espo/Core/Record/Formula/Processor.php:46-48` precizează că procesarea este pentru cereri API Record, iar apelurile sunt făcute din `Record/Service.php:1636-1655`; căile ORM manageriale ar rămâne neacoperite.

#### Înlocuire sigură

Regula de nume poate fi mutată într-un singur hook ORM pentru `Prezenta`, limitat la entități noi sau la situația în care `name` lipsește. Codul de business pentru compunerea numelui rămâne custom, dar duplicarea dispare.

#### Echivalență și cazuri-limită

- trebuie rezolvat numele utilizatorului când `assignedUserName` nu este încă prezent pe entitatea creată direct;
- fallback-ul la ID trebuie păstrat;
- update-urile nu trebuie să rescrie retroactiv numele;
- se testează creare personală, creare managerială și creare prin corecție.

### B5 — Endpointul managerial de marcare seamănă cu CRUD Record, dar nu poate fi înlocuit acum fără pierdere funcțională

**Decizie:** se păstrează  
**Prioritate de revizuire:** P2, după B1 și B2

#### Cod relevant

- `src/files/custom/Espo/Modules/ManagementAngajati/Api/MarcheazaPrezentaCondica.php:22-46`
- `src/files/custom/Espo/Modules/ManagementAngajati/Instrumente/Prezenta/MarcatorPrezentaCondica.php:27-84`

Record Service ar furniza nativ field ACL, validare, defaults, assignment/link checks, audit și hooks. Totuși, `DefaultPopulator.php:196-207` suprascrie `assignedUserId` cu utilizatorul curent atunci când acesta nu are permisiunea globală Assignment sau editarea câmpului. Acesta este exact cazul care a produs anterior salvarea pe manager în locul angajatului selectat.

Prin urmare, înlocuirea endpointului cu `POST /Prezenta` ar compromite comportamentul actual. Endpointul custom este justificat până când modelul de ownership și permisiunile de assignment sunt reproiectate. B1 trebuie să adauge validarea nativă lipsă, iar B2 trebuie să elimine numai algoritmul ACL paralel, nu comanda managerială în sine.

### B6 — Verificarea explicită a duplicatelor nu trebuie eliminată în favoarea indexului unic

**Decizie:** se păstrează  
**Risc existent:** cursă concurentă reziduală

- verificare: `ValidatorPrezenta.php:139-147`;
- index nativ/DB: `entityDefs/Prezenta.json:111-118`;
- blocarea în comanda managerială: `MarcatorPrezentaCondica.php:51-58`.

Indexul unic este garanția finală, dar nu oferă singur mesajul tradus Conflict folosit de aplicație. Verificarea anticipată păstrează UX-ul. Pentru concurență perfectă, eroarea de unicitate de la DB trebuie mapată la același Conflict; eliminarea verificării nu ar fi echivalentă funcțional.

### Backend custom justificat, fără echivalent nativ complet

| Componentă | Fișiere/linii principale | De ce rămâne custom |
|---|---|---|
| Calendar personal lunar | `CalendarPrezentaPersonala.php:68-166,170-261` | Working Time furnizează calendarul și orele, dar nu combină sărbători din extensia externă, CIC, Remote, interval editabil și snapshot lunar. Implementarea folosește deja `CalendarFactory` și `CalendarUtilityFactory`. |
| Proiecție managerială și statistici | `CondicaPrezentaLunara.php:36-214,246-355` | O listă Record nu poate reprezenta angajați fără înregistrări, celule virtuale WTC/CIC, completitudine și structură înghețată. |
| Ciclu lunar | `CicluLunarCondica.php:28-149` | EspoCRM nu oferă stare de lună deschisă/închisă, limită de luni editabile sau snapshot pentru condică. |
| Închidere și snapshot | `InchizatorLunaCondica.php:24-96` | Cerința de 100%, tranzacția, blocarea și imposibilitatea regenerării sunt reguli de domeniu. |
| Corecție managerială auditată | `CorectorLunaCondica.php:30-180` | Auditul nativ al câmpurilor nu solicită și nu stochează motivul operațional și nu actualizează revizia snapshotului. |
| CIC/Remote și conflicte | `ConfiguratiiCondicaAngajati.php:38-181`, `ValidatorConfiguratieCondicaAngajat.php:27-163` | Sunt reguli specifice extensiei; field validation poate prelua doar tipul/formatul, nu semantica. |
| Salvare bulk a setărilor | `SalvatorSetariCondica.php:27-135` | Record API nu oferă o comandă atomică pentru configurația globală plus toate configurațiile angajaților. `ConfigWriter` este deja serviciul nativ utilizat. |
| API-uri agregate | `Api/*.php`, `Resources/routes.json:1-37` | Clasele implementează interfața nativă `Action`; endpointurile nu sunt CRUD echivalent. Verificările scope din API sunt necesare, nu doar ascunderea paginii. |
| Controllers PHP goale | `Controllers/Prezenta.php:5-9`, `Controllers/ConfiguratieCondicaAngajat.php:5-7` | Routerul CRUD caută controllerul după scope; `ControllerActionProcessor.php:184-206` și `ClassFinder.php:34-68` nu fac fallback automat la `Record`. Clasele sunt glue necesar. |
| Administrator fără prezență personală | controller/view frontend aferent | Este o cerință explicită de domeniu, nu o restricție asupra ACL-ului administratorului. |

## Frontend JavaScript

### F1 — Permisiunea Edit este verificată la nivel de scope, nu pe modelul existent

**Prioritate:** P1  
**Beneficiu:** mare pentru corectitudinea Own/Team  
**Risc al modificării:** redus

#### Cod relevant

- `src/files/client/custom/modules/management-angajati/src/views/prezenta/lista-lunara.js:68-89,104-131`

`this.getAcl().check('Prezenta', 'edit')` verifică accesul general la scope. Pentru o înregistrare deja existentă, EspoCRM poate verifica ownership-ul și echipele modelului.

#### Mecanism nativ verificat

- `site/client/src/acl-manager.js:231-264` oferă `checkModel(model, action, precise)` și alege implementarea ACL a scope-ului.
- `site/client/src/acl.js:201-224` calculează `isOwner`, `inTeam` și `isShared` din model.
- View-urile standard îl folosesc, de exemplu `site/client/src/views/record/list-base.ts:3080-3081` și `views/record/detail.ts:2786`.

#### Înlocuire sigură

- pentru model existent: `checkModel(model, 'edit')`;
- pentru o zi fără model: `check('Prezenta', 'create')` rămâne corect;
- backend-ul rămâne autoritatea finală și nu trebuie simplificat pe baza controlului frontend.

Testele trebuie să acopere Own/Team/All, model cu/fără `teamsIds`, manager care își vede pagina personală și administrator.

### F2 — Opțiunile `P/D/CM` sunt duplicate în două view-uri

**Prioritate:** P1  
**Beneficiu:** mare — o singură sursă de adevăr PHP/JS  
**Risc al modificării:** redus

#### Cod relevant

- `views/prezenta/lista-lunara.js:90-99`
- `views/condica-prezenta/lunara.js:116-142`
- sursa canonică existentă: `Resources/metadata/entityDefs/Prezenta.json:19-33`

Ambele view-uri folosesc deja `Language.translateOption`, dar construiesc lista din array hard-coded.

#### Mecanism nativ verificat

- `View#getMetadata()` expune `entityDefs.Prezenta.fields.codPrezenta.options`.
- field view-ul nativ enum citește și traduce aceste opțiuni; vezi `site/client/src/views/fields/enum.ts:180-205,440-462`.
- helperul `translateOption` este în `site/client/src/view-helper.js:613`.

#### Înlocuire sigură

Ambele pagini trebuie să citească opțiunile din metadata și să păstreze aceeași ordine. Etichetele continuă să vină din i18n. Împreună cu B1, adăugarea sau eliminarea unui cod nu mai cere modificări în patru locuri.

Cazuri-limită: metadata absentă trebuie tratată ca eroare de configurare, nu prin fallback tăcut la o listă diferită; `CIC` nu se adaugă în enum deoarece este stare automată, nu cod manual selectabil.

### F3 — Checkboxurile Remote/CIC reimplementează field view-ul nativ `bool`

**Prioritate:** P2  
**Beneficiu:** mediu  
**Risc al modificării:** redus–mediu

#### Cod relevant

- `views/gestionare-angajati/rand-setari-condica.js:19-46,65-94`
- `res/templates/gestionare-angajati/rand-setari-condica.tpl:8-11,24-27`
- metadata câmpurilor există deja în `entityDefs/ConfiguratieCondicaAngajat.json`.

Datele folosesc corect `views/fields/date`, dar cele două booleene sunt citite direct din DOM cu `.prop('checked')`.

#### Mecanism nativ verificat

Aceeași abordare folosită deja pentru date se poate aplica prin `views/fields/bool`: modelul `ConfiguratieCondicaAngajat`, `createView`, `fetchToModel` și validarea/lifecycle-ul field view-ului.

#### Echivalență

- rămâne un singur buton Save și același payload bulk;
- schimbarea checkboxului trebuie să actualizeze modelul rândului, nu să declanșeze save individual;
- toggling-ul CIC/Remote și datele asociate trebuie păstrate;
- stările disabled/readOnly și field ACL pot fi aplicate nativ dacă vor fi necesare ulterior.

Nu recomand momentan aceeași conversie pentru `modAplicareRemote` și `numarLuniAnterioareEditabile`: ele sunt setări globale, nu câmpuri ale unei entități existente. Crearea unei pseudo-entități doar pentru UI ar adăuga complexitate și nu ar reduce-o.

### F4 — Evenimentul `change` este legat manual după render

**Prioritate:** P3  
**Beneficiu:** mic  
**Risc al modificării:** redus

- cod: `views/gestionare-angajati/setari-condica.js:51-55`;
- mecanism nativ: `View#addHandler`, folosit în core la `site/client/src/views/record/search.ts:422` și în numeroase field views.

Înlocuirea cu `addHandler('change', selector, callback)` mută gestiunea evenimentului în lifecycle-ul view-ului. Implementarea actuală nu produce în mod necesar leak deoarece elementul este înlocuit la re-render, deci beneficiul este de consistență, nu o corecție critică.

### Frontend custom justificat, fără echivalent nativ complet

| Componentă | Fișiere/linii principale | Motiv |
|---|---|---|
| Matricea managerului | `views/condica-prezenta/lunara.js:1-326`, template-ul aferent | List view-ul nativ lucrează cu un record pe rând; nu modelează date × angajați, celule inexistente, CIC și statistici. |
| Calendarul personal | `views/prezenta/lista-lunara.js:37-207` | Folosește deja colecția/modelul nativ pentru `Prezenta`; API-ul custom adaugă numai proiecția WTC/CIC/Remote. Filtrul pe utilizatorul curent este necesar și pentru managerul care are Read All, dar își deschide pagina personală. |
| Comenzile lunare | `views/condica-prezenta/lunara.js:147-282` | Închiderea și corecția motivată nu sunt operații CRUD standard. Modalul motivului are un contract specific. |
| Pagina Setări condică | `views/gestionare-angajati/setari-condica.js:1-144` | Un formular standard Record nu poate salva setări globale și mai multe configurații de angajat într-o singură operație. Child views și `clearView` sunt mecanisme native deja folosite. |
| Field date | `rand-setari-condica.js:19-46` | Este deja implementat corect cu `views/fields/date`; nu trebuie revenit la `<input type="date">`. |
| Controllers și routes | `controllers/*.js`, `clientDefs/*.json` | Sunt punctele native de extensie pentru paginile distincte și pentru ACL-ul scope-urilor de navigație. |
| i18n și toast-uri | `Resources/i18n/*`, apelurile `translate`, `Espo.Ui.notifyWait/success/error` | Sunt mecanismele native cerute și trebuie păstrate. |

## Duplicări interne fără înlocuitor nativ direct

Acestea pot reduce complexitatea, dar nu trebuie prezentate ca funcționalități EspoCRM native.

### D1 — Navigarea pe luni este duplicată

- `views/prezenta/lista-lunara.js:177-207`
- `views/condica-prezenta/lunara.js:289-326`

Un helper/mixin comun al extensiei ar elimina calculul și formatarea duplicate. EspoCRM oferă utilitare de afișare a datelor, dar nu un selector de lună cu limitele de business ale condicii. **P2, risc redus.**

### D2 — Recalcularea statisticilor deschise/înghețate este duplicată

- `CondicaPrezentaLunara.php:55-214`
- `CondicaPrezentaLunara.php:246-355`

Un calculator intern comun pentru totaluri ar reduce riscul ca definiția „100% complet” să difere între luna deschisă și cea închisă. Nu există un agregator nativ capabil să calculeze peste celule WTC virtuale. **P2, risc mediu.**

### D3 — Accesul la sărbători este implementat în două locuri

- `CalendarPrezentaPersonala.php:223-261`
- `ValidatorPrezenta.php:115-137`

Un provider intern unic pentru `ZileLibere` ar centraliza verificarea metadata, țara și query-ul. Nu a fost găsită în EspoCRM o interfață nativă care să reprezinte extensia externă `ZileSarbatoare`. **P2, risc redus.**

### D4 — Normalizarea snapshotului prin JSON este repetată

- `CalendarPrezentaPersonala.php:170-181`
- `CondicaPrezentaLunara.php:217-237`
- `CorectorLunaCondica.php:172-180`

`Espo\Core\Utils\Json` (`site/application/Espo/Core/Utils/Json.php:36-59`) poate standardiza encode/decode, dar tot este necesară conversia object→array. Un helper intern tipizat aduce un beneficiu mai mare decât simpla schimbare a apelului. **P3.**

### D5 — Metode publice de salvare globală fără consumator runtime

- `ConfiguratiiCondicaAngajati.php:108-114`
- `ConfiguratiiCondicaAngajati.php:129-138`

Fluxul runtime folosește `salveazaSetariGenerale` de la liniile 141-152. Cele două metode separate apar utile doar testelor și măresc API-ul clasei. Pot fi eliminate după confirmarea tuturor consumatorilor și adaptarea testelor. Nu există un mecanism nativ implicat. **P3.**

## Inventar de mecanisme native deja folosite corect

- entitatea reală `Prezenta` cu ACL Own/Team/All și index unic;
- scopes boolean pentru pagini și `app.acl.mandatory.scopeLevel` pentru entitățile interne;
- `Acl`, field ACL și `SelectBuilder::withStrictAccessControl`;
- Working Time `CalendarFactory` și `CalendarUtilityFactory`;
- modele, colecții și `model.save` pe pagina personală;
- routes cu clase `Action` și răspunsuri `ResponseComposer`;
- tranzacții ORM și `forUpdate` pentru operații concurente;
- field view nativ `date`;
- metadata `clientDefs`, controller/view routing și acțiuni de view;
- i18n `ro_RO`/`en_US`, traduceri de opțiuni și mesaje de eroare traduse;
- notificări `Espo.Ui`;
- auditarea câmpurilor și optimistic concurrency declarate în metadata.

## Ordine recomandată pentru un refactor ulterior

1. **B1 + F2:** o singură sursă metadata pentru coduri și validare structurală pe toate căile ORM.
2. **F1:** `checkModel` pe pagina personală.
3. **B2:** alinierea `teams`, testarea Team și abia apoi eliminarea helper-ului ACL paralel.
4. **B3:** hook ORM unic pentru configurațiile angajaților.
5. **B4:** hook ORM unic pentru numele prezenței.
6. **F3:** field views `bool` păstrând salvarea bulk.
7. **D1–D5:** consolidări interne după stabilizarea comportamentului.

## Criterii de acceptare pentru orice refactor rezultat din audit

- nicio schimbare a scope-urilor sau a setărilor existente din Roles;
- administratorul păstrează comportamentul ACL nativ și nu devine angajat pontabil;
- angajatul vede și modifică numai prezența proprie conform rolului;
- managerul cu Team/All obține aceleași rezultate în listă, în endpoint și la salvare;
- nicio dată viitoare, nelucrătoare, sărbătoare sau CIC nu devine editabilă;
- luna nu se închide sub 100%, iar cea închisă nu se regenerează;
- corecția managerială rămâne motivată și auditată;
- salvarea globală a setărilor rămâne o singură operație din perspectiva utilizatorului;
- toate erorile și confirmările rămân localizate;
- testele unitare și de integrare trebuie extinse pentru Own/Team/All, payloaduri invalide și toate cele trei căi de creare a unei prezențe.

## Limitările auditului

Auditul este static și a verificat direct sursele EspoCRM 10.0.10 din `site/`. Nu s-au modificat fișiere de implementare, baza de date sau configurația instalată; nu s-au executat sync, rebuild, build ori instalări. Conform cerinței, nu s-a aplicat niciuna dintre recomandări și nu s-au rulat teste care ar putea scrie în mediul instalat.
