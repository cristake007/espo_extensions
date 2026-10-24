# ManagementAngajati / EmployeeManagement — analiză inițială clean-room

Data analizei: 22 octombrie 2026  
Țintă tehnică: EspoCRM 10.0.10  
Stadiu: analiză funcțională și arhitecturală; fără implementare, build, instalare sau modificări de bază de date

## 1. Scop, metodă și limite

Acest document inventariază comportamentul extensiei vechi `EmployeeManagement` și îl raportează la mecanismele native din EspoCRM 10.0.10, fără a copia implementarea veche și fără a considera automat corecte concluziile din `REFACTORING-PLAN.md`.

Sursele analizate au fost:

- extensia livrată în `/opt/espo_extensions/EmployeeManagement`, în special cele 200 de fișiere runtime din `files/`, manifestul 0.8.16, metadatele, rutele, serviciile și template-ul DOCX;
- `REFACTORING-PLAN.md` și inventarele istorice, utilizate numai ca surse secundare și confruntate cu runtime-ul;
- scaffold-ul oficial din `/opt/espocrm-extension-dev` și instanța-sursă EspoCRM 10.0.10 din `site/`;
- documentația oficială EspoCRM indicată în §15.

Nu au fost inspectate sau modificate în această etapă datele unei instanțe de producție. În consecință, secțiunea de migrare definește **ce trebuie extras și păstrat**, nu afirmă volume, ID-uri sau consistența efectivă a unei baze de date încă neauditate.

Convenții de certitudine:

- **Confirmat**: există în pachetul runtime 0.8.16 și traseul UI/API/backend poate fi demonstrat din surse.
- **Derivat/reconstruibil**: este persistat, dar poate fi recreat determinist dintr-o sursă autoritativă.
- **Păstrat în surse, neexpus**: există în `pending/`, documente sau cod auxiliar, dar nu este funcție livrată de pachetul runtime analizat.
- **Decizie necesară**: comportamentul vechi este ambiguu, contradictoriu sau există o alternativă nativă care trebuie aleasă explicit.

## 2. Concluzii executive

1. Extensia veche este, funcțional, un produs cu trei domenii strâns cuplate: **concedii**, **pontaj** și **administrare delegată**. Documentele DOCX, Calendarul, notificările și exporturile sunt funcții suport ale acestor domenii, nu produse independente.
2. EspoCRM 10.0.10 oferă nativ infrastructura necesară — entități/ORM, relații, ACL/FLS/Roles/Teams, Working Time Calendar, Event/Calendar, Attachment/FileStorage, audit, Email, Notification, Jobs și Scheduled Jobs. Nu oferă însă nativ contabilitatea soldului de concediu, registrul zilnic de pontaj, tranzițiile specifice de aprobare/anulare, înghețarea normei lunare sau generarea/verificarea DOCX. Acestea rămân logică de domeniu personalizată.
3. Datele autoritative care nu pot fi recalculate în siguranță sunt: profilurile și soldurile, ledger-ul, cererile cu toate snapshot-urile/reviziile, pontajele zilnice și motivele de corecție, normele lunare istorice, setările cu efect istoric, DOCX-urile și metadatele registrului de documente. Proiecția `EmployeeAbsence` este reconstruibilă.
4. Extensia veche rezervă soldul **la crearea cererii Pending**, nu la aprobare. Respingerea, retragerea și anularea restituie soldul. Aceasta este o regulă fundamentală de business și trebuie ori păstrată explicit, ori schimbată numai prin decizie de produs și plan de migrare contabilă.
5. Extensia veche refuză explicit administratorii prin condiții de tip `isRegular()`. Acest comportament nu este o regulă legitimă de business și **nu trebuie reconstruit**. EspoCRM 10.0.10 acordă nativ administratorului nivelurile ACL maxime. Validările de stare și integritate rămân valabile, dar nu se va introduce o poartă custom care diminuează administratorul.
6. Dependența de extensia „Zile Sărbătoare” 0.9.5 există efectiv: zilele nelucrătoare sunt citite din entitatea `ZileLibere`, țara `RO`. EspoCRM are nativ Working Time Exceptions; trebuie decis dacă noua extensie continuă să consume `ZileLibere` sau migrează responsabilitatea sărbătorilor în Working Time Calendar. Nu trebuie menținute două calendare normative concurente.
7. `REFACTORING-PLAN.md` conține observații valide, dar și propuneri care nu sunt aprobate prin această analiză (de exemplu schema țintă de 17 scopes, bundle-ul JS unic, activarea Teams, migrarea automată a Roles sau configurabilitatea Entity Manager). Acestea rămân opțiuni, nu cerințe.

## 3. Starea proiectului nou

Proiectul `ManagementAngajati` este un scaffold oficial `ext-template`/`espo-extension-tools`, configurat să folosească sursa EspoCRM 10.0.10. În `src/files` există doar scheletul modulului `ManagementAngajati`, o listă de rute goală, metadate minime client și fișiere `.dummy`; scripturile `AfterInstall`/`AfterUninstall` nu au efecte. Nu există încă model de date sau implementare funcțională de reutilizat.

Aspecte ce trebuie corectate mai târziu, nu în această etapă:

- `extension.json` declară momentan `acceptableVersions: >=9.3.0`, mai larg decât ținta explicită 10.0.10;
- metadatele client trimit la `lib/init.js`, dar fișierul nu există încă;
- repository-ul conține modificări locale preexistente, care nu au fost alterate de această analiză.

Scaffold-ul trebuie să rămână sursa structurii de pachet. Conform documentației oficiale, extensia finală trebuie să conțină `manifest.json`, `files/` și, numai dacă sunt necesare, `scripts/`; modulele backend și frontend trebuie plasate în directoarele native de modul.

## 4. Inventarul funcțional confirmat

### 4.1 Spații de lucru și navigație

| Funcționalitate confirmată | Comportament observat | Mecanism nativ disponibil | Logică personalizată necesară |
|---|---|---|---|
| Workspace Employee Management | Punct de intrare care raportează capabilități și direcționează către concedii, pontaj și administrare | scopes/tab-uri, client routes, controllers și views Espo | compunerea statusului/capabilităților numai dacă UX-ul cere un hub |
| Concedii personale | Sold, drept anual, cereri proprii, creare/editare Pending, retragere, cerere de anulare și documente | list/detail views, record models, modaluri, ACL own, attachmentMultiple | agregatul de sold și comenzile de stare |
| Gestionare concedii | Coadă Pending/Processing/CancellationPending, aprobări, respingeri, anulări, rezervare pentru alt angajat, verificare DOCX | ACL target, list views, Roles, routes | state machine și comenzi tranzacționale |
| Pontaj personal | Calendar lunar propriu și marcarea zilelor eligibile | views, Working Time Calendar, ACL own | registrul zilnic și validările specifice |
| Centralizator pontaj | Matrice lunară pentru angajați, corecții, remindere, export PDF/XLSX | list/custom view, ACL team/all, Notifications, Export permission | agregarea matricei, completitudinea și formatele lunare |
| Administrare delegată | Politici pontaj, politici concedii, profiluri/solduri, Remote/CIC | Config/ConfigWriter, Roles/FLS, standard field views | formularul delegat și comenzile de domeniu |

Navigația și vizibilitatea butoanelor nu reprezintă securitate. Orice reconstrucție trebuie să aplice autorizarea în backend prin ACL/FLS nativ și acces la înregistrarea țintă.

### 4.2 Angajați eligibili și identitate

Funcțiile vechi tratează drept angajat eligibil numai un `User` activ de tip `regular`. Profilurile, cererile, pontajele și metadatele Remote/CIC sunt legate de `User` prin `assignedUser`.

Reguli confirmate:

- un profil de concediu este unic per utilizator (`assignedUserId` unic);
- un pontaj zilnic este unic per utilizator și dată;
- o configurație Remote/CIC este identificată prin utilizator și `effectiveFrom`;
- utilizatorii inactivi sau de alt tip sunt eliminați din listele operaționale;
- actorul comenzii și beneficiarul sunt concepte distincte;
- aprobatorii sunt derivați din permisiunea efectivă `EmployeeHolidayApproval` și accesul la cerere, nu din numele rolului.

Mecanism nativ recomandat: `User`, `assignedUser`, Roles, Teams și verificările `Acl::checkEntity`/FLS. Nu trebuie creată o entitate paralelă „Angajat” decât dacă apar date HR care nu aparțin contului de utilizator; această necesitate nu este demonstrată de extensia veche.

### 4.3 Profiluri și solduri de concediu

Entitatea veche `EmployeeHolidayProfile` păstrează:

- dreptul anual (`annualEntitlement`);
- soldul curent (`balance`);
- următoarea dată de acordare/reset (`nextResetDate`);
- culoarea de Calendar;
- starea de inițializare;
- câmpuri tehnice pentru reset în curs/pending.

Funcționalități confirmate:

1. listarea angajaților regular activi și a profilurilor lor;
2. inițializare în lot cu drept anual, sold de deschidere, data următorului reset și culoare;
3. actualizarea parametrilor profilului;
4. „Complete Initial Setup”, disponibil doar când profilul este inițializat, soldul este exact zero, nu există cereri și ledger-ul conține exclusiv inițializare/bulk update cu delta zero;
5. corecție manuală de sold cu delta nenulă, motiv obligatoriu, sold așteptat opțional și cheie de idempotency;
6. istoric al corecțiilor;
7. acordare anuală manuală/internă și procesare automată opt-in;
8. notificări când soldul rezultat depășește pragul configurat.

Regula matematică veche a acordării anuale este `sold nou = sold vechi + drept anual`. Setarea numită `carryOverLimitDays` este folosită în runtime ca **prag de avertizare**, nu ca plafon care taie zile. Nicio zi acumulată nu este eliminată de formulă.

Mecanisme native: entitate custom, relație belongs-to cu User, ORM și tranzacții, câmpuri auditate, Notification, Scheduled Job. Calculul soldului, condițiile de inițializare și idempotency sunt personalizate.

### 4.4 Ledger-ul de concedii

`EmployeeHolidayLedger` este jurnalul contabil append-only. Tipurile istorice definite sunt:

`initialization`, `openingBalanceSetup`, `bulkUpdate`, `correction`, `annualGrant`, `resetPending`, `resetOverride`, `automaticReset`, `holidayBooked`, `holidayAdjusted`, `holidayCancelled`, `holidayRejected`.

Fiecare intrare păstrează:

- profilul și, unde este cazul, cererea;
- delta;
- soldul, dreptul anual și data reset înainte/după;
- actorul, motivul și data efectivă;
- `idempotencyKey` unic și `operationHash`;
- owner-ul (`assignedUser`) și timestamp-urile native.

Ledger-ul este sursă istorică autoritativă și nu trebuie înlocuit cu Audit Log. Audit Log poate demonstra modificarea unor câmpuri, dar nu exprimă suficient semantica contabilă, idempotency sau snapshot-ul înainte/după.

Mecanisme native: entitate custom read-oriented, ORM, ACL own/team/all, audit. Logica append-only și semantica tranzacțiilor sunt personalizate.

### 4.5 Cererea de concediu și rezervarea soldului

`EmployeeHolidayRequest` păstrează intervalul, numărul de zile, statusul, beneficiarul, profilul, descrierea, actorii și momentele deciziilor, motivul anulării, revizia contabilă, snapshot-urile, documentele și starea jobului de aprobare.

Statusurile confirmate sunt:

```text
Pending
  ├─ update Pending ───────────────> Pending
  ├─ withdraw ─────────────────────> Cancelled
  ├─ reject (aprobator) ───────────> Rejected
  └─ approve ──────────────────────> ApprovalProcessing
                                          ├─ worker finalizat ─> Approved
                                          └─ eșec ─────────────> ApprovalProcessing + approvalState=Failed

Approved
  ├─ requestCancellation (proprietar) ───> CancellationPending
  └─ cancel (aprobator) ─────────────────> Cancelled

CancellationPending
  ├─ acceptare anulare ──────────────────> Cancelled
  └─ refuz anulare, document existent ──> Approved
```

Reguli confirmate:

- la **creare**, numărul de zile se calculează și soldul se debitează imediat; se scrie ledger `holidayBooked`;
- editarea este permisă numai în Pending; modificarea intervalului recalculează zilele și scrie `holidayAdjusted` pentru diferență;
- retragerea, respingerea și anularea restituie zilele o singură dată și scriu ledger;
- soldul după debit nu poate coborî sub limita negativă configurată (implicit `-21` în codul vechi);
- cererile active (`Pending`, `ApprovalProcessing`, `Approved`, `CancellationPending`) nu se pot suprapune pentru același beneficiar;
- pentru cereri proprii, un interval nou nu poate începe în trecut; managerul poate introduce perioade din luna curentă, dar nu din luni anterioare;
- perioada este inclusivă, data finală nu poate preceda data inițială, durata maximă este 367 zile și trebuie să conțină cel puțin o zi lucrătoare;
- orice comandă mutabilă cere revizia curentă și cheie de idempotency; repetarea aceleiași comenzi returnează receipt-ul, iar reutilizarea cheii cu alt payload produce conflict;
- actorii și datele deciziilor se iau din contextul serverului, nu din payload;
- o cerere proprie de anulare este refuzată dacă concediul a început deja; anularea directă de către aprobator rămâne posibilă;
- refuzul anulării readuce cererea în Approved numai dacă documentul de aprobare există fizic.

Mecanisme native: entitate custom de tip Event sau Base, câmp status, relationships, transactional save, audit, ACL/FLS, optimistic concurrency unde se potrivește. State machine, rezervarea soldului, receipts și blocările concurente sunt personalizate.

### 4.6 Calculul zilelor și norma de lucru

Extensia nu folosește o normă fixă luni–vineri în runtime. Ea cere un Working Time Calendar atribuit explicit utilizatorului și folosește clasele native `CalendarFactory`, `CalendarUtilityFactory` și `Extractor` pentru:

- zile lucrătoare;
- ore normative pe zi;
- intervalele orare;
- fusul orar al calendarului.

Din rezultat sunt excluse suplimentar datele din `ZileLibere` pentru `countryCode=RO`. Snapshot-ul păstrează calendarul, fusul orar, zilele/orele, intervalele, totalul pe luni și sărbătorile excluse.

EspoCRM 10.0.10 oferă nativ Working Time Calendar și Working Time Exceptions, inclusiv calendare per sistem, Team sau User și excepții nelucrătoare. Prin urmare:

- reutilizarea WTC este obligatorie;
- nu trebuie creat un editor paralel de zile/ore;
- dependența `ZileLibere` versus Working Time Exceptions este o decizie de integrare (§12, D-03);
- snapshot-ul istoric rămâne necesar chiar dacă sursa normativă este nativă, deoarece WTC se poate modifica ulterior.

### 4.7 Aprobarea asincronă

Aprobarea nu finalizează sincron. Comanda trece cererea în `ApprovalProcessing`, captează un snapshot imuabil și programează un Job nativ grupat per cerere. Worker-ul:

1. marchează încercarea ca Processing;
2. generează câte un DOCX pentru fiecare lună intersectată;
3. creează mai întâi Attachment-ul în staging și apoi scrie fișierul;
4. validează hash-ul fișierului;
5. reia lock-urile și reverifică revizia, snapshot-ul de calcul, conflictul cu pontajul, autorizarea aprobatorului și accesul beneficiarului;
6. publică atașamentele, înregistrează documentele, trece cererea în Approved și notifică beneficiarul.

Retry-ul este permis numai când Job-ul nativ nu mai este Pending/Ready/Running. Un token nou protejează împotriva worker-ilor vechi. Job-urile cu același group sunt serializate nativ; fencing-ul, manifestul și verificările de domeniu sunt custom.

Mecanisme native: `JobSchedulerFactory`, `Job`, group, target, status/failed detection. Orchestrarea specifică aprobării și finalizarea tranzacțională sunt personalizate.

### 4.8 Notificări și email

Funcții confirmate:

- la creare Pending, câte un Email nativ și Job nativ per aprobator eligibil cu adresă validă;
- livrare cu maximum trei încercări, întârzieri de 1 și 5 minute pentru încercările 2 și 3 și `messageId` determinist;
- anularea livrării dacă cererea nu mai este Pending sau destinatarul nu mai este autorizat;
- Notification nativ către angajat la Approved/Rejected, deduplicat prin cerere/status/revizie;
- Notification nativ pentru pragul de sold către angajat și aprobatorii care pot vedea profilul/soldul;
- Notification nativ de reminder pentru zilele de pontaj lipsă.

Mecanisme native: Email, EmailSender, Notification și Jobs. Alegerea destinatarilor, conținutul, deduplicarea și politica retry sunt personalizate.

### 4.9 Documente DOCX

Funcționalități confirmate:

- template DOCX românesc inclus în extensie;
- un document separat pentru fiecare lună din cererea aprobată;
- valori capturate la momentul deciziei: nume, interval, zile lunare, drept anual, sold înainte/după, report, dată, semnatari și aprobator;
- hash al template-ului în snapshot; retry-ul refuză randarea dacă template-ul s-a schimbat;
- Attachment nativ în `approvalStaging`, apoi mutat logic în `approvalDocuments` numai după validare;
- cod de verificare de 32 caractere hexazecimale, hash al codului și SHA-256 al fișierului;
- registru versionat `EmployeeHolidayDocument` cu stări Prepared/Issued/Superseded și tipuri Approval/Reissue/Legacy;
- verificare după cod și, opțional, după bytes DOCX încărcați; rezultat Valid/Revoked/CancellationPending/NotEffective/Superseded și Match/Modified;
- jurnal al verificărilor cu actor, fingerprint de cod, rezultat și indicator că a fost furnizat fișier;
- rate limit: 20 verificări/actor/15 minute; fișier maxim 10 MB;
- reemitere controlată: dry-run, confirmarea Attachment-ului original, motiv, hash înainte/după, versiune nouă, mutarea originalului în `supersededApprovalDocuments` și legătură către documentul înlocuitor.

Mecanisme native: `Attachment`, câmp `attachmentMultiple`, `FileStorage\Manager` și ACL pentru attachment. EspoCRM nu oferă în core un generator DOCX pentru acest formular și nici registrul/verificarea criptografică descrise; acestea rămân custom.

### 4.10 Pontaj personal

Statusurile persistate sunt `AtWork`, `Holiday`, `BusinessTrip`, `MedicalLeave`, `ChildCareLeave`. Înregistrările scrise interactiv acceptă însă numai `AtWork`, `BusinessTrip` și `MedicalLeave`; Holiday și ChildCareLeave sunt proiecții determinate de concediul aprobat, respectiv perioada CIC.

Reguli confirmate:

- marcarea este permisă numai pentru o zi normativă WTC, care nu este în viitor și nu precede arhiva vizibilă;
- angajatul își marchează propria zi; managerul poate marca pentru alt utilizator;
- sursa persistată este Self sau Manager;
- revizia zilnică protejează de update concurent;
- un concediu Approved/CancellationPending are prioritate și blochează marcarea;
- în CIC, numai MedicalLeave poate suprascrie proiecția ChildCareLeave;
- o corecție managerială excepțională cere motiv; o marcare managerială de rutină din lunile editabile nu cere motiv;
- corecția excepțională managerială poate opera și după înghețarea lunii, în timp ce marcarea proprie/de rutină nu poate.

Mecanisme native: entitate custom cu assignedUser, audited fields, ACL own/team/all și WTC. Regulile de prioritate și mutațiile sunt custom.

### 4.11 Norma lunară istorică și înghețarea

`EmployeeAttendanceMonth` păstrează pentru fiecare lună:

- snapshot-ul normei WTC per utilizator (`norms`);
- snapshot-ul politicilor Remote/CIC la înghețare (`policies`);
- `frozen`, momentul și actorul înghețării;
- revizia lunii.

Prima utilizare a lunii capturează norma completă a utilizatorilor activi cu WTC explicit. După capturare, schimbarea WTC sau a sărbătorilor nu rescrie norma. După înghețare nu se mai adaugă utilizatori noi în populația lunii.

O lună se îngheață automat când:

- există date de export;
- există cel puțin o zi semnată;
- nu există zile normative lipsă;
- toți angajații incluși au normă configurată;
- nu există zile normative viitoare în lună.

Corecțiile manageriale ulterioare nu schimbă norma și nu dezgheață luna. Această funcție nu este oferită nativ de EspoCRM; entitatea snapshot și algoritmul sunt custom, deși calculul inițial folosește WTC nativ.

### 4.12 Centralizator, completitudine, remindere și exporturi

Centralizatorul produce o matrice lunară cu:

- angajați regular activi (sau populația snapshot dacă luna este înghețată);
- zile normative, ore și intervale;
- statusul efectiv cu prioritățile Holiday/CIC;
- Remote;
- număr de zile semnate/lipsă/viitoare și angajații cu lipsuri;
- capabilități per celulă și stare frozen.

Reminderele se trimit numai pentru lipsuri reale, într-o lună editabilă. Exporturile PDF și XLSX sunt regenerate din agregatul curent. În mod normal sunt disponibile doar pentru registru complet; politica `allowIncompleteExports` poate permite exportul incomplet. Permisiunea nativă Export este verificată; vechiul cod permite PDF managerului operațional și printr-o cale separată.

EspoCRM are export generic și permisiune Export, dar nu oferă matricea lunară sau layout-ul PDF/XLSX cerut. Generatoarele și agregarea rămân custom; autorizarea și mecanismul de download trebuie să fie native unde este posibil.

### 4.13 Remote și CIC/CCC

`EmployeeAttendanceWorkSchedule` păstrează Remote, CIC, începutul/sfârșitul CIC și `effectiveFrom`. Configurația nu definește ore sau zile normative.

Reguli confirmate:

- CIC necesită ambele date și interval valid;
- dezactivarea CIC golește datele;
- Holiday efectiv are prioritate față de CIC;
- CIC produce ChildCareLeave pentru zilele normative;
- în CIC este permisă doar marcarea MedicalLeave;
- Remote este atribut de afișare/export, nu sursă de normă.

Working Time Exceptions pot modela nativ o indisponibilitate personală, însă nu acoperă automat eticheta CIC, prioritatea față de medical și atributul Remote. Modelul final necesită decizia D-04.

### 4.14 Calendarul comun

Extensia menține două surse Calendar:

- `EmployeeHolidayRequest` pentru vizualizarea privată autorizată;
- `EmployeeAbsence`, proiecție minimală read-only pentru concedii Approved/CancellationPending, cu nume, interval și owner, fără motiv, sold sau documente.

`EmployeeAbsence` este sincronizat din cerere și poate fi reconstruit integral din cererile efective. EspoCRM suportă nativ entități Event în Calendar, assigned user, shared/team calendars și ACL. Este necesară decizia dacă noul model folosește direct cererea ca Event cu filtru de status/vizibilitate sau păstrează o proiecție minimală pentru separarea datelor (§12, D-05). Nu se va crea o a treia sursă Calendar.

### 4.15 Setări administrative

Setări pontaj confirmate:

- numărul de luni anterioare editabile: 0–120, implicit 1;
- permiterea exporturilor incomplete: bool, implicit false;
- începutul arhivei vizibile: `YYYY-MM`, inițializabil la luna curentă.

Setări concedii confirmate:

- drept anual implicit: întreg pozitiv;
- data anuală de acordare/reset: dată completă;
- acordare anuală automată: bool, implicit false;
- prag avertizare sold acumulat: întreg >= 0, implicit 90;
- limită sold negativ: întreg <= 0, implicit -21;
- două blocuri de semnătură, titlu și nume, maximum 150 caractere.

Configurarea este păstrată prin `ConfigWriter`, mecanism nativ. Ecranul delegat și validarea specifică sunt custom. Aprobatorii nu sunt o listă configurată separat, ci se descoperă din Roles efective.

### 4.16 Resetări de date de test

Există două operații distructive, expuse cu preview și confirmare:

- reset Holiday: cereri, ledger, documente staging/current, proiecții Calendar, joburi de aprobare, notificări și reinițializarea profilurilor; refuză dacă acordarea automată este activă sau există joburi de aprobare active;
- reset Attendance: șterge pontajele și reminderele, dar păstrează WorkSchedule și normele lunare; golește starea operațională frozen și mută începutul arhivei la luna curentă.

Acestea sunt unelte de laborator, nu funcționalități HR normale. Pentru reconstrucția de producție trebuie decis dacă sunt eliminate complet sau livrate doar într-un mod de test explicit. Nu trebuie confundate cu resetarea/acordarea anuală a soldului.

## 5. Funcții prezente în surse, dar neconfirmate ca runtime expus

Următoarele nu trebuie tratate automat ca cerințe ale noului produs:

- overview/dashlet Holiday complet din sursele `pending/`;
- comanda UI pentru reset anual forțat al unui singur profil (metoda backend există, dar README o declară neexpusă);
- vechile override-uri globale `#Admin`, Calendar sau meniuri din extensiile sursă Attendance/Holiday;
- un editor propriu al normei săptămânale; runtime-ul actual folosește WTC;
- hooks contabile vechi care ar dubla serviciul de comenzi;
- orice artefact aflat numai în `pending/`, testele istorice sau planurile pe etape.

Ele pot fi reanalizate ca cerințe noi, nu copiate din inerție.

## 6. Reguli de business transversale

### 6.1 Autoritatea datelor

| Domeniu | Sursă autoritativă | Derivate/consumatori |
|---|---|---|
| identitate și drepturi | User + Roles/Teams/ACL/FLS native | liste, butoane, destinatari |
| normă curentă | Working Time Calendar + sursa aleasă pentru sărbători | snapshot lunar |
| normă istorică | EmployeeAttendanceMonth.norms | pontaj, concediu, export, DOCX |
| sold concediu | Profile + Ledger | balance card, documente, avertizări |
| cerere/stare | EmployeeHolidayRequest | Calendar, pontaj, documente, notificări |
| pontaj zilnic | EmployeeAttendanceRecord | matrice, freeze, export, conflict concediu |
| document emis | Attachment bytes + EmployeeHolidayDocument | download, verificare, reemitere |

### 6.2 Ordine și concurență

Codul vechi încearcă să serializeze operațiile în ordinea User → Profile → Request/Month → recorduri/attachments. Cererile și pontajele folosesc revizii, iar operațiile contabile folosesc chei de idempotency. Aceste mecanisme protejează împotriva:

- dublului debit/restituire;
- aprobării simultane cu modificarea pontajului;
- retry-urilor de Job care publică documente vechi;
- corecțiilor făcute pe un sold văzut anterior;
- marcărilor concurente pe aceeași zi.

EspoCRM oferă tranzacții ORM și `transactionalSave`, dar ordinea lock-urilor, idempotency și fencing-ul de Job sunt reguli custom care trebuie proiectate și testate explicit.

### 6.3 Priorități între concediu și pontaj

Ordinea efectivă este:

1. o cerere Approved/CancellationPending controlează ziua ca Holiday;
2. altfel, CIC controlează ziua ca ChildCareLeave;
3. MedicalLeave poate fi înregistrat în CIC;
4. AtWork și BusinessTrip intră în conflict cu rezervarea/aprobarea unui concediu;
5. zilele nenormative și viitoare nu cer pontaj.

O anulare nu fabrică și nu șterge pontaje. Dacă produce lipsuri într-o lună înghețată, managerul le corectează explicit și auditabil.

### 6.4 Confidențialitate

Cererea, soldul, documentele și motivele sunt private. Calendarul comun expune intenționat un set minimal. Verificarea DOCX nu trebuie să confirme existența unui cod pentru o cerere la care actorul nu are acces. Aceste reguli se pot implementa cu ACL/FLS/record access native și DTO-uri minimale; nu este necesar un registru paralel de permisiuni.

## 7. Harta dependențelor funcționale

```text
User + Roles/Teams + FLS
├── Working Time Calendar ──┬── Snapshot normă lunară ──┬── Pontaj personal
│                           │                            ├── Centralizator/export
│                           │                            └── Calcul zile concediu
│                           └── Sărbători (o singură sursă)
├── Profil concediu ──> Ledger ──> Sold curent
│                         ▲
│                         └── Cerere Pending rezervă soldul
├── Cerere concediu ──┬── Aprobarea asincronă ──> DOCX/registru/verificare
│                     ├── Calendar/absență
│                     ├── Notificări/email
│                     └── Pontaj (prioritate/conflicte)
└── Config/politici ───────> profiluri, concedii, pontaj, documente, exports
```

Dependențe externe/runtime confirmate:

- EspoCRM 10.0.10 și PHP conform manifestului vechi;
- extensia „Zile Sărbătoare” 0.9.5 prin entitatea `ZileLibere`;
- cron/daemon Espo pentru Jobs, email și Scheduled Jobs;
- un Working Time Calendar atribuit explicit fiecărui angajat procesat;
- FileStorage configurat și bytes existenți pentru DOCX;
- SMTP pentru emailurile către aprobatori.

## 8. Mecanisme native EspoCRM 10.0.10 verificate

| Nevoie | Mecanism nativ verificat | Utilizare propusă | Limită |
|---|---|---|---|
| structură extensie | ext-template, `files/`, `scripts/`, modules | baza exclusivă a pachetului nou | n/a |
| model de date | entityDefs, scopes, layouts, relationships, indexes, ORM | toate entitățile de domeniu | schema concretă rămâne de proiectat |
| tranzacții | ORM transaction manager; `transactionalSave` disponibil din v10 | comenzi atomice | nu înlocuiește lock order/idempotency |
| acces | Roles, levels own/team/all/no, assignedUser, teams, FLS, `checkEntity` | populație și câmpuri | operațiile speciale cer validare de domeniu |
| administrator | ACL table nativ aplică nivel maxim adminului | admin fără restricții custom | `adminMandatory` nativ rămâne autoritatea platformei |
| program de lucru | Working Time Calendar per system/team/user și Exceptions | normă și zile/ore | snapshot istoric rămâne necesar |
| Calendar | entitate Event, assigned user, shared/team calendar | concedii efective | filtrarea status/confidențialitate poate necesita query service |
| fișiere | Attachment, attachmentMultiple, FileStorage Manager | DOCX și storage agnostic | generarea/registrul sunt custom |
| audit | audited fields, Audit Log, preserveAuditLog | schimbări operaționale | nu înlocuiește ledger-ul contabil |
| fundal | JobSchedulerFactory, Job, groups, queues | aprobare, documente, email | fencing/revalidare custom |
| recurență | scheduledJobs metadata și Scheduled Jobs | acordare anuală | formula și idempotency custom |
| mesaje | Notification | decizii, remindere, prag sold | selecția și deduplicarea custom |
| email | Email, EmailSender, Jobs | notificarea aprobatorilor | conținut/retry custom |
| API | module routes + Action classes | comenzi explicite | CRUD generic nu exprimă state machine |
| export | permisiunea Export și infrastructura download | autorizare | matricea PDF/XLSX este custom |
| UI | controllers, routes, record/list/custom views, modals și native field views | experiența utilizatorului | agregatele speciale cer views proprii |

Observație critică despre administrator: în sursa 10.0.10, `DefaultTable` construiește pentru admin nivelurile maxime și nu combină Roles obișnuite. Orice verificare preliminară `isRegular()` sau cerință exactă `getLevel(...) === 'all'` aplicată înainte de ACL poate anula comportamentul nativ. Noul cod nu trebuie să facă acest lucru.

Observație despre Teams: EspoCRM poate aplica Roles prin Team și poate filtra recorduri la nivel `team`, dar numai dacă entitățile au ownership/relations native corespunzătoare. Simpla asociere a unui Role la Team nu atribuie automat recordurile acelei Team.

## 9. Logică personalizată care rămâne justificată

Următoarele nu au echivalent complet în core și justifică servicii custom mici, orientate pe domeniu:

1. contabilitatea soldului de concediu și ledger-ul append-only;
2. rezervarea soldului în Pending și compensările exacte;
3. state machine pentru aprobare, respingere, retragere și anulare;
4. idempotency, receipts, revizii și ordinea lock-urilor;
5. snapshot-ul normei lunare și înghețarea registrului;
6. agregatul de pontaj, prioritățile Holiday/CIC/Medical și detectarea lipsurilor;
7. conflictul bidirecțional concediu–AtWork/BusinessTrip;
8. exporturile lunare PDF/XLSX cu forma cerută;
9. generarea DOCX lunar, snapshot-ul de decizie, hash-ul template-ului;
10. registrul documentelor, codul de verificare, integritatea și reemiterea;
11. alegerea destinatarilor și mesajele business;
12. validările Remote/CIC care nu pot fi exprimate doar prin WTC;
13. proiecția Calendar numai dacă separarea minimă nu poate fi obținută direct din Event + ACL.

Logica custom nu trebuie să reproducă:

- calculul general al zilelor/orelor WTC;
- stocarea fișierelor;
- coada de joburi;
- roluri, teams sau FLS;
- email/notification transport;
- un audit generic;
- un editor paralel de Users/Roles/WTC/Jobs.

## 10. Acces și administrator

### 10.1 Comportamentul vechi care nu se păstrează

Mai multe servicii vechi cer simultan `User::isRegular()` și `isActive()`, ceea ce produce 403 pentru administrator chiar dacă ACL-ul Espo îi acordă acces maxim. Acest gate apare în concedii, pontaj, workspace, aprobări și verificare DOCX. Este o restricție tehnică accidentală, nu o condiție asupra beneficiarului.

### 10.2 Regula pentru reconstrucție

- Actorul se autorizează prin ACL/FLS nativ și acces la target.
- Administratorul EspoCRM nu primește un Role custom, un flag special sau un bypass implementat de extensie.
- Extensia nu va refuza administratorul pe baza tipului de user.
- Beneficiarul unei cereri/profil/pontaj poate rămâne limitat la User regular activ; aceasta validează **obiectul operației**, nu restrânge administratorul actor.
- Invariantele business (status valid, document existent, sold, interval, integritate) se aplică tuturor actorilor, inclusiv adminului.
- Entitățile interne pot fi ascunse din navigație și operațiile normale pot fi modelate ca Actions, dar nu se va adăuga un access checker custom care contrazice privilegiile native ale adminului.

Numărul și numele scopes finale nu sunt decise în această etapă. Schema veche are 22 scopes (9 entități și 13 capabilități/pagini); unele sunt redundante, dar consolidarea necesită o matrice de acces aprobată și migrarea Roles reale.

## 11. Informațiile istorice obligatorii pentru migrare

### 11.1 Identificatori și relații

Trebuie păstrate ID-urile vechi sau un mapping bijectiv auditat pentru:

- User beneficiar/actor/aprobator;
- EmployeeHolidayProfile;
- EmployeeHolidayRequest;
- EmployeeHolidayLedger;
- EmployeeAttendanceRecord;
- EmployeeAttendanceMonth;
- EmployeeAttendanceWorkSchedule;
- Attachment;
- EmployeeHolidayDocument;
- EmployeeHolidayDocumentVerificationLog;
- Job/Email/Notification numai dacă se decide migrarea istoricului tehnic.

Relații care nu pot fi pierdute:

- Profile → assignedUser;
- Ledger → Profile, Request opțional, actor și assignedUser;
- Request → assignedUser, Profile, decidedBy, cancellationRequestedBy, cancellationDecidedBy;
- AttendanceRecord/WorkSchedule → assignedUser, createdBy, modifiedBy;
- AttendanceMonth → frozenBy;
- Attachment → `parentType=EmployeeHolidayRequest`, `parentId=requestId`, `field`;
- HolidayDocument → Request, Attachment, approvedBy, supersededByDocument;
- VerificationLog → actor, document și request;
- Email/Notification/Job → request prin parent/related/target, dacă sunt păstrate.

### 11.2 Profil și ledger

Se păstrează integral, fără recalculare:

- toate valorile profilului, inclusiv `balance`, `annualEntitlement`, `nextResetDate`, culoare și flagurile de inițializare/reset;
- fiecare ledger row, tip, delta, înainte/după, date de reset, actor, motiv, dată efectivă;
- `idempotencyKey` și `operationHash`;
- timestamp-uri, owner și referința la cerere;
- ordinea cronologică și unicitatea.

Migrarea nu va „reconstrui” soldul doar prin suma ledger-ului fără un raport de reconciliere. Profilul curent și ledger-ul trebuie importate ambele, apoi comparate. Orice discrepanță este incident de date, nu motiv pentru corecție automată.

### 11.3 Cereri de concediu

Se păstrează:

- intervalele date și câmpurile datetime legacy dacă există;
- `days`, status, descriere și toate datele/actorii deciziilor/anulării;
- `accountingKey`, `accountingRevision`, `creationHash`;
- `calculationSnapshot`, inclusiv calendarId, timezone, zile/ore, intervale, luni, sărbători excluse și normSources;
- `operationReceipts` pentru a preveni repetarea operațiilor după migrare;
- `approvalSnapshot`, `approvalManifest`, operation key, token, Job ID, approval revision/attempts/state/error;
- referințele la documente curente, înlocuite și staging.

Nu se reexecută aprobări și nu se recalculează zilele istorice din WTC-ul curent.

### 11.4 Pontaj și normă istorică

Se păstrează:

- fiecare pontaj: dată, status, source, markedAt, revision, correctionReason, assigned/created/modified users și timestamp-uri;
- fiecare lună: `month`, `norms`, `policies`, frozen/frozenAt/frozenBy/revision;
- fiecare WorkSchedule: Remote/CIC, datele CIC, effectiveFrom, owner și audit;
- începutul arhivei și politicile care determinau editabilitatea/exportul.

`EmployeeAttendanceMonth.norms` este probă istorică a normei folosite de concedii și pontaj. Nu trebuie regenerată din WTC-ul actual.

### 11.5 DOCX și relațiile lor

Pentru fiecare Attachment DOCX trebuie extras un manifest care conține cel puțin:

- Attachment ID, nume, MIME type, size, role, storage provider și orice `sourceId`;
- `parentType`, `parentId` și `field` exact;
- existența fizică, dimensiunea citită și SHA-256 calculat din bytes;
- request ID și luna dedusă din registru/snapshot, nu numai din numele fișierului;
- clasificarea: `approvalStaging`, `approvalDocuments` sau `supersededApprovalDocuments`;
- pentru documentele înregistrate: document ID, issueKey, verification code/hash, SHA-256, lună, versiune, status, issueType, valori tipărite, approved/issued/superseded timestamps, reason/operatorContext și lanțul `supersededByDocument`;
- pentru documente legacy fără registru: legătura unică la request, filename-ul din approvalSnapshot, luna și hash-ul calculat; nu se inventează un cod de verificare retroactiv;
- pentru staging: Job/manifest/token/state care explică dacă fișierul este recuperabil sau orfan.

Template-ul istoric livrat are SHA-256:

`737e695378896bacf26cf93187d371f4a064704a3f2e99eddbecbd798d1f9c0e`

Acest template trebuie arhivat versionat alături de migrare. `approvalSnapshot.templateHash` trebuie comparat cu setul de template-uri arhivate. Un snapshot al cărui hash nu are template corespondent nu poate fi rerandat sigur și trebuie raportat.

Regula relațiilor Attachment:

```text
EmployeeHolidayRequest.id
  ├── Attachment(parent=request, field=approvalDocuments)           document curent
  ├── Attachment(parent=request, field=supersededApprovalDocuments) istoric înlocuit
  └── Attachment(parent=request, field=approvalStaging)             emitere incompletă

EmployeeHolidayDocument
  ├── requestId    ──> EmployeeHolidayRequest.id
  ├── attachmentId ──> Attachment.id
  └── supersededByDocumentId ──> versiunea următoare
```

Nu este suficientă copierea fișierelor din storage. Fără rândurile Attachment și câmpurile `parent/field`, câmpul attachmentMultiple, ACL-ul și istoricul de reemitere se pierd.

### 11.6 Setări și configurație

Trebuie inventariate și versionate valorile:

- toate cheile `employeeHoliday*` și `employeeAttendance*` active;
- `calendarEntityList` și tab/navigation numai ca stare de referință, nu pentru copiere oarbă;
- ScheduledJob `ProcessEmployeeHolidayResets`, status și scheduling;
- WTC ID per User, timezone, program și excepțiile relevante;
- records `ZileLibere` folosite de snapshot-urile perioadei de migrare;
- Roles: `data`, `fieldData`, Roles directe și prin Teams pentru toate scopes vechi;
- membership și default Team, dacă modelul final folosește team ownership;
- default file storage și storage-ul fiecărui Attachment.

### 11.7 Artefacte derivate versus istorice

| Artefact | Tratament |
|---|---|
| EmployeeAbsence | poate fi reconstruit din requests Approved/CancellationPending; se migrează doar pentru comparație, nu ca autoritate |
| Notification reminder/decision | opțional ca istoric UX; nu se folosește pentru refacerea stării |
| Email de aprobare | opțional ca istoric de comunicare; nu se retrimite |
| Job Success/Failed | se arhivează în raport dacă explică ApprovalProcessing/staging; joburile vechi nu se repornesc automat |
| cache, tab list derivat, client state | nu se migrează ca date business |

### 11.8 Verificări de migrare obligatorii

Înaintea oricărei scrieri viitoare:

1. count și checksum logic per entitate;
2. orfani pe toate foreign keys logice;
3. unicitate profile/user, attendance/user/date, month, accountingKey, idempotencyKey, issueKey, verificationCodeHash și request/month/version;
4. reconciliere sold profil versus ledger, fără corecție automată;
5. toate cererile active au profil, snapshot și ledger de rezervare;
6. toate documentele current/superseded există fizic și hash-ul corespunde registrului;
7. fiecare document Issued este legat de Attachment cu field corect;
8. fiecare Superseded are lanț coerent, fără cicluri;
9. ApprovalProcessing/staging este clasificat manual: completabil, eșuat sau orfan;
10. snapshot-urile lunare referite de cereri există și nu sunt regenerate;
11. export read-only al ACL efectiv pentru utilizatori reprezentativi înainte/după;
12. raport separat pentru date personale și perioade de retenție.

## 12. Aspecte care necesită decizii

### D-01 — Numele tehnic și compatibilitatea entităților

Trebuie ales dacă noul modul `ManagementAngajati` păstrează entity type-urile istorice `EmployeeHolidayRequest`, `EmployeeHolidayProfile` etc. sau introduce nume noi. Păstrarea numelor simplifică migrarea și relațiile Attachment, dar necesită controlul conflictului cu extensia veche. Nume noi cer mapping complet și schimbarea `parentType`, relatedType/targetType, ACL, layouts și link-uri. Recomandare de analiză: păstrarea entity type-urilor de business, dar sub modulul nou, dacă Espo permite tranziția controlată fără coexistență.

### D-02 — Momentul debitării soldului

Comportamentul confirmat este debit la Pending. Trebuie aprobat explicit dacă acesta rămâne contractul. Mutarea debitului la Approved ar schimba `availableDays`, concurența între cereri, ledger-ul și toate snapshot-urile/documentele.

### D-03 — Sursa unică a sărbătorilor

Opțiuni:

- păstrarea dependenței de `ZileLibere` ca provider extern;
- folosirea exclusivă a Working Time Exceptions native și migrarea sărbătorilor;
- integrare controlată în care `ZileLibere` alimentează WTC, cu un singur rezultat normativ.

Este interzisă scăderea aceleiași sărbători din două surse sau folosirea de surse diferite între concediu și pontaj.

### D-04 — Modelul Remote/CIC

Trebuie stabilit dacă CIC devine Working Time Exception nativă, rămâne metadata custom sau este un model hibrid. Remote nu este normă nelucrătoare. Orice alegere trebuie să păstreze istoricul effectiveFrom și prioritățile Holiday/CIC/Medical.

### D-05 — Calendar direct sau proiecție minimală

Folosirea directă a cererii ca Event elimină duplicarea, dar trebuie demonstrat că Calendarul poate afișa numai statusurile efective și numai câmpurile permise fără a divulga Pending/motive/documente. Dacă nu, `EmployeeAbsence` rămâne o proiecție justificată, derivată și read-only.

### D-06 — Modelul ACL și Teams

Nu este aprobată schema de 17 scopes din auditul anterior. Trebuie definită mai întâi matricea Angajat/Manager/Team/Admin pentru fiecare operație și target, apoi minimizat numărul de scopes. Activarea nivelului `team` cere relationships `teams`, regulă de atribuire și backfill; nu se activează doar în metadata.

### D-07 — Administrator și entități interne

Regula obligatorie este fără restricții custom asupra adminului. Trebuie ales cum sunt prezentate entitățile interne (ledger, month, document registry, verification log): fără tab, read-only în UI sau administrabile prin standard UI. Nu se va folosi un checker custom care returnează false adminului.

### D-08 — Workflow custom versus funcții comerciale

Core 10.0.10 verificat nu include un workflow de aprobare suficient pentru acest domeniu. Dacă instanța țintă are Advanced Pack/BPM, capabilitățile lui trebuie evaluate înainte de implementare. Nu se introduce o dependență comercială presupusă și nu se dublează un BPM existent.

### D-09 — Politica de documente

Trebuie aprobate: forma DOCX, numărul de semnături, păstrarea codului de verificare, accesul la reemitere, retenția documentelor/staging și dacă verificarea rămâne numai autentificată. Template-ul vechi este istoric, nu automat template-ul final.

### D-10 — Resetările de laborator

Trebuie decis dacă reseturile de test sunt excluse din pachetul de producție. Recomandare: nu se livrează implicit; datele reale nu trebuie să poată fi șterse de o funcție prezentată drept administrare normală.

### D-11 — Exporturi și formate

Trebuie validate cu utilizatorii coloanele, limba, anteturile, normele orare, Remote/CIC și semnăturile PDF/XLSX. Exportul generic Espo nu înlocuiește automat aceste rapoarte.

### D-12 — Acordarea anuală

Trebuie clarificat vocabularul: funcția veche adaugă dreptul anual și doar avertizează peste pragul de report; nu aplică limită de report. Se aprobă formula, data, catch-up-ul pentru ani restanți, fusul schedulerului și destinatarii avertizării.

### D-13 — Entity Manager

Nu s-a aprobat permiterea customizării entităților critice. Câmpurile adăugate prin Entity Manager pot lipsi din comenzi, snapshot-uri, exporturi și documente. Orice configurabilitate trebuie definită pe entitate și câmp, nu activată global.

### D-14 — Date personale și retenție

DOCX-urile, motivele de anulare/corecție, statusurile Medical/CIC, emailurile și jurnalele de verificare conțin date personale sau sensibile. Trebuie stabilite retenția, drepturile de audit/export și politica la dezactivarea utilizatorului înainte de migrare.

## 13. Recomandări din auditul anterior care nu sunt aprobate aici

Următoarele rămân ipoteze de proiectare:

- exact 17 scopes finale și mapping-ul automat al celor 22 vechi;
- două scopes noi pentru reseturile de date;
- activarea Teams și backfill-ul ownership;
- un singur bundle JavaScript și un singur CSS;
- paleta sau tema TUVTK;
- expunerea controlată prin Entity Manager;
- păstrarea tuturor endpoint-urilor vechi ca API compatibil;
- reguli de packaging și release preluate din harness-ul vechi;
- generic CRUD blocat inclusiv pentru admin pe entități interne.

Ultimul punct intră în tensiune directă cu regula curentă „nu introduce restricții personalizate asupra administratorului”. Protejarea integrității trebuie făcută prin modelarea comenzilor, validări și UI native, fără anularea ACL-ului adminului printr-un checker custom.

## 14. Ordinea recomandată pentru proiectarea ulterioară

Aceasta nu este implementare, ci ordinea în care trebuie închise deciziile:

1. aprobarea regulilor de business din §§4–6, în special debitarea Pending, formulele de sold, prioritățile și anularea;
2. deciziile D-01–D-14;
3. schema conceptuală minimă și sursele autoritative;
4. matricea ACL/FLS/ownership cu administrator nativ;
5. contractul de migrare și extractor read-only, inclusiv manifestul DOCX;
6. teste de caracterizare pe o copie anonimizată a datelor vechi;
7. abia apoi metadata/entityDefs și servicii noi în scaffold;
8. migrare dry-run, raport și reconciliere înainte de orice import.

## 15. Surse oficiale verificate

- [Making extension package](https://docs.espocrm.com/development/extension-packages/) — structura pachetului și recomandarea ext-template.
- [Modules](https://docs.espocrm.com/development/modules/) — directoare de modul, metadata, routes și module JS.
- [API actions](https://docs.espocrm.com/development/api-action/) — rute și Action classes.
- [Entity Manager](https://docs.espocrm.com/administration/entity-manager/) — tipuri de entități, Event, relationships, audit, transactional save și limite de customizare.
- [Role Management](https://docs.espocrm.com/administration/roles-management/) — own/team/all/no, Roles prin Teams, FLS și permisiunea Export.
- [Users management](https://docs.espocrm.com/administration/users-management/) — tipuri de utilizator și Teams.
- [Working Time Calendar](https://docs.espocrm.com/user-guide/working-time-calendar/) — calendare system/team/user, program și exceptions.
- [Activities & Calendar](https://docs.espocrm.com/user-guide/activities-and-calendar/) — entități Event, assigned user și shared calendar.
- [Jobs — development](https://docs.espocrm.com/development/jobs/) și [Jobs — administration](https://docs.espocrm.com/administration/jobs/) — scheduling, groups, queues și worker/cron.
- [Scheduled jobs](https://docs.espocrm.com/development/scheduled-job/) — metadata și JobDataLess.
- [Attachments](https://docs.espocrm.com/development/attachments/) și [File storage](https://docs.espocrm.com/administration/file-storage/) — relațiile Attachment și storage abstraction.
- [recordDefs](https://docs.espocrm.com/development/metadata/record-defs/) — hooks și controale record-native.

Surse locale principale folosite ca dovezi:

- `files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/entityDefs/*.json`;
- `Resources/metadata/scopes`, `aclDefs`, `recordDefs`, `app` și `routes.json`;
- `Tools/HolidayBalance`, `Tools/HolidayDocument`, `Tools/Attendance`, `Tools/EmployeeHolidayRequest` și `Tools/Calendar`;
- `Resources/templates/holiday-request.docx`;
- sursa EspoCRM 10.0.10 din `site/application/Espo`, în special ACL, WorkingTime, Job și FileStorage.

## 16. Criteriul de ieșire din etapa de analiză

Analiza inițială este suficientă pentru a începe proiectarea numai după ce deciziile D-01–D-14 au owner și răspuns. Nicio recomandare de refactorizare, inclusiv cele din documentul anterior, nu devine cerință implicită. Reconstrucția trebuie să pornească din mecanismele native EspoCRM 10.0.10 și din regulile de business aprobate, iar extensia veche rămâne exclusiv probă de comportament și sursă pentru migrarea datelor istorice.
