# Employee Management 0.8.13

Etapa 7 plus paginile Holiday, Attendance și centralizatorul echipei: Calendar comun minimal și acordări/resetări anuale opt-in prin Scheduled Jobs native, peste fluxurile Attendance–Holiday și documentele private deja integrate. Versiunile 0.7.3–0.8.13 clarifică spațiile de lucru principale și administrative, păstrează navigația administrată nativ, notifică aprobatorii și adaugă verificarea documentelor Holiday. **Nu production-ready.** Compatibilitate strictă: EspoCRM **10.0.1**, PHP **8.4.23**, Zile Sărbătoare **0.9.5**. Nu actualizează platforma.

## Documente Holiday verificabile — 0.8.13

- Proiecția financiară DOCX tratează acordarea anuală programată dintre segmente fără a modifica profilul, soldul sau ledger-ul. Segmentarea lunară existentă rămâne neschimbată.
- Tabelul păstrează antetele originale. Pentru un segment cu report, celula „Total zile” afișează dreptul anual și reportul pe două rânduri (de exemplu `21 - 2027` și `3 - report 2026`), iar „Deja primite” rămâne numărul de zile folosite din dreptul anului curent.
- Fiecare DOCX nou conține exact linia `Cod de verificare: <32 caractere hexazecimale>` într-un paragraf normal, imediat sub tabelul principal și aliniat la dreapta. Codul provine din 128 biți generați prin `random_bytes()` și rămâne stabil la retry-ul aceleiași emiteri.
- SHA-256 al fișierului final este legat de atașament într-o evidență internă. Managerii cu permisiuni efective de aprobare pot folosi `#EmployeeHolidayDocumentVerification`; backend-ul reverifică ACL/FLS și accesul la cerere.
- Documentele unei cereri `Cancelled` sunt raportate ca revocate, cele înlocuite ca superseded, iar `CancellationPending` este afișat distinct. Fișierele istorice nu sunt șterse sau rescrise.
- Reemiterea este exclusiv CLI și pornește implicit în dry-run. Din rădăcina proiectului Docker:

```bash
docker compose exec -u www-data espocrm php custom/Espo/Modules/EmployeeManagement/Command/reissue-holiday-document.php \
  --request-id=REQUEST_ID --month=2027-01 --dry-run

docker compose exec -u www-data espocrm php custom/Espo/Modules/EmployeeManagement/Command/reissue-holiday-document.php \
  --request-id=REQUEST_ID --month=2027-01 --execute \
  --reason="Corectare sold reportat 2026-2027" --confirm-attachment-id=ATTACHMENT_ID
```

Numele serviciului Compose poate diferi. Execuția cere ID-ul atașamentului raportat de un dry-run proaspăt. Nu reexecută aprobarea și nu schimbă statusul, revizia contabilă, soldul, ledger-ul, Attendance sau WTC.
Pentru snapshot-urile legacy cross-year, dry-run-ul folosește snapshotul imutabil al documentului și ledger-ul rezervării cererii, care consemnează dreptul anual și data de resetare înainte ca grantul viitor să fie executat. Regula automată configurată trebuie să confirme aceeași dată; în caz contrar operația se oprește și cere o confirmare istorică documentată și auditată.

## Notificarea aprobatorilor Holiday — 0.8.12

- O cerere nouă salvată cu status `Pending` creează atomic câte un email nativ și câte un Job nativ pentru fiecare aprobator eligibil. Transportul SMTP se execută exclusiv în worker, prin `EmailSender` și contul outbound configurat în EspoCRM.
- Destinatarii provin din `HolidayApproverProvider`: utilizatori regular activi cu permisiunile efective `EmployeeManagement` și `EmployeeHolidayApproval`, inclusiv din roluri moștenite prin Teams, plus read-all/FLS și acces la cererea din coada de aprobare. Adresa primară trebuie să fie utilizabilă.
- Permisiunile, starea `Pending` și adresa curentă sunt reverificate imediat înainte de expediere. Emailul include numai angajatul, perioada, numărul de zile lucrătoare, statusul și linkul construit din `siteUrl`.
- Identitatea logică este derivată din eveniment, cerere și destinatar. Emailurile native păstrează stările Draft/Sending/Sent/Failed; joburile sunt grupate pe identitate, iar erorile au cel mult două retry-uri explicite, după 1 și 5 minute.
- Retry-ul aceleiași comenzi create nu programează emailuri noi. Upgrade-ul nu scanează cererile Pending existente și nu trimite retroactiv. Nu există buton manual de retrimitere în această versiune.

## Marcarea rapidă Attendance pentru manager — 0.8.11

- Celulele editabile din `#EmployeeAttendanceOverview` afișează direct butoanele compacte P (Prezent), D (Delegație) și CM (Concediu medical), cu statusul curent evidențiat și etichete accesibile complete.
- Marcarea uzuală se salvează cu un singur click, fără dialog și fără motiv inventat; click-ul pe statusul deja activ și click-urile repetate cât timp cererea este în curs nu scriu din nou.
- Operația dedicată `managerMarkRoutine` păstrează ACL-ul, validarea WTC, protecțiile Holiday/CIC, blocarea lunii și revizia optimistă. Corecția managerială excepțională `managerMark` rămâne separată și continuă să ceară motiv.
- Upgrade-ul nu conține migrare de schemă și nu modifică date Attendance/Holiday. Lifecycle-urile existente nu apelează servicii de resetare.

## Actualizare eficientă Holiday — 0.8.10

- Listele personale, aprobările în așteptare și concediile aprobate sunt verificate la 30 de secunde, inclusiv când lista managerială este goală.
- Cererile aflate în `ApprovalProcessing` folosesc temporar intervalul de 5 secunde, pentru publicarea promptă a stării finale și a documentelor.
- Polling-ul este oprit în taburile ascunse, este reluat imediat la revenire și folosește backoff exponențial limitat după erori de rețea.
- Request-urile de listă sunt serializate, refresh-urile concurente sunt reunite, iar răspunsurile unui mod anterior nu pot suprascrie lista curentă.
- Listarea backend selectează numai câmpurile necesare și încarcă documentele publicate într-o singură interogare, păstrând ACL/FLS și răspunsul API existent.

Această optimizare reduce traficul produs de interfața Holiday; nu este prezentată ca remediu pentru erori Cloudflare 522 sau conexiuni WebSocket.

## Calcul și decizii concediu în timp real — 0.8.9

- Formularul de concediu recalculează automat numărul de zile lucrătoare la schimbarea angajatului sau a perioadei, folosind exact același WTC și aceleași zile nelucrătoare ca salvarea.
- Pagina personală verifică automat modificările de stare, astfel încât aprobarea sau respingerea apare fără refresh manual.
- La decizia finală se creează o notificare EspoCRM pentru angajat; aprobarea este notificată numai după ce Job-ul nativ a publicat documentele.
- Resetarea datelor Holiday elimină și notificările de decizie create de extensie.

## Eticheta de prezență — 0.8.8

Statusul tehnic `AtWork` este afișat în limba română ca „Prezent” în pagina personală Attendance, în centralizatorul managerului și în exporturile PDF/XLSX. Datele și statusurile stocate nu sunt migrate sau modificate; actualizarea schimbă numai textul vizibil.

## Anularea concediilor aprobate — 0.8.7

Angajatul poate solicita anularea unui concediu aprobat inclusiv în prima zi a perioadei; cererile pentru perioade deja începute anterior rămân rezervate managerului. Managerul autorizat are în pagina Holiday lista separată „Concedii aprobate”, unde poate anula direct orice cerere aprobată cu motiv obligatoriu, inclusiv pentru ziua curentă. Operația folosește fluxul contabil existent: restituie zilele, scrie ledger-ul și păstrează documentele și istoricul; ștergerea generică rămâne interzisă.

## Resetare Attendance și acțiunea zilnică — 0.8.6

`#EmployeeAdministration/attendance` oferă o resetare distructivă, cu previzualizare și confirmarea exactă `RESET ATTENDANCE DATA`. Sunt eliminate înregistrările de pontaj și notificările de reamintire Attendance, iar lunile blocate sunt redeschise cu revizii noi. Se păstrează identitatea lunilor și normele WTC memorate, configurările Remote/CIC, userii, setările Attendance și datele Holiday. Arhiva vizibilă repornește din luna curentă. Acțiunea necesită drepturile delegate Attendance Settings și Attendance Management, plus acces nativ read-all/delete-all la EmployeeAttendanceRecord. În `#EmployeeAttendance`, titlul „Alege prezența” este afișat în aceeași coloană cu butoanele zilnice.

## Interfața de administrare — 0.8.5

Paginile `#EmployeeAdministration`, `#EmployeeAdministration/attendance`, `#EmployeeAdministration/holiday` și `#EmployeeAdministration/profiles` folosesc acum limbajul vizual comun Employee Management: spațiu de lucru compact, antete și panouri tokenizate, grupare clară a setărilor, stări goale/de eroare, semnalarea modificărilor nesalvate și comportament responsive. Endpoint-urile, payload-urile, ACL-ul și regulile de business nu sunt modificate.

## Configurarea Remote/CIC — 0.8.4

`#EmployeeAdministration/attendanceMetadata` folosește acum același spațiu de lucru de 1180px și aceiași tokeni de temă ca paginile Attendance. Angajații sunt afișați în rânduri compacte, cu opțiunile Remote și CIC explicate, intervalul CIC grupat, numărul total de angajați, stare goală și indicator vizibil pentru modificările nesalvate.

Fiecare angajat se salvează în continuare separat. Endpoint-ul, validarea intervalului CIC și câmpurile native EspoCRM pentru date nu au fost modificate; pagina nu expune editorul WTC eliminat și nu duplică setările globale delegate.

## Centralizator Attendance — 0.8.3

`#EmployeeAttendanceOverview` folosește aceeași lățime de 1180px ca paginile Holiday și Attendance. Luna și starea editabilă/read-only sunt în antet, acțiunile sunt într-o bară compactă, iar cei patru indicatori ai lunii sunt afișați pe un singur rând. Listele complete de angajați și motive rămân disponibile în secțiuni „Detalii” expandabile, fără să domine pagina.

Matricea zilnică păstrează toate informațiile: numărul de angajați și zile, legenda statusurilor, programele, coloana datei și antetul sticky, lipsurile și corecțiile manageriale cu motiv. Pe ecrane mici, cardurile și controalele se reașază, iar matricea folosește scroll intern.

Heading-ul „Situație zilnică” este forțat pe un singur rând și aliniat la stânga pe desktop. Numărul angajaților și zilelor este afișat lizibil în dreapta, fără pill compact; pe mobil coboară aliniat sub titlu. Celulele folosesc aceleași culori semantice ca legenda pentru serviciu, deplasare, CM, concediu, CIC și lipsă; selecturile manageriale păstrează culoarea vizibilă prin fundalul și accentul celulei.

Stilurile folosesc tokenii TUVTK cu fallback EspoCRM și sunt izolate de pagina personală și de administrare. Endpoint-urile de reminder, corecție, XLSX/PDF, condițiile de export, ACL-ul și programele WTC nu au fost modificate.

## Pagina personală Attendance — 0.8.0

`#EmployeeAttendance` folosește acum același limbaj vizual ca pagina Holiday: antet tematic, stare curentă distinctă, trei opțiuni de pontare ușor de identificat, sumar lunar în carduri și rânduri cu status vizual. Sunt folosiți tokenii TUVTK cu fallback pe variabilele native EspoCRM, iar pagina este adaptată pentru ecrane mici.

Endpoint-ul, revizia optimistă, zilele editabile, blocarea lunii și regulile pentru Holiday/CIC/CM nu au fost schimbate. Interfața afișează fiecare acțiune numai când capabilitatea returnată de backend o permite și marchează accesibil opțiunea activă.

## Spațiere Holiday și sidenav stabil — 0.7.9

Pagina personală Holiday are acum spațiu între conținut și bara superioară, inclusiv pe ecrane mici. Instalarea și upgrade-ul nu mai citesc și nu mai rescriu deloc `tabList`: nu adaugă linkuri automat, dar nici nu elimină linkurile configurate manual de administrator. Linkurile eliminate deja de o versiune anterioară trebuie adăugate o singură dată din editorul nativ al sidenav-ului; după aceea, upgrade-urile le păstrează.

## Pagina personală Holiday — 0.7.8

`#EmployeeHolidayRequest` și intrarea canonică `#EmployeeManagement/holiday` folosesc aceeași pagină personală reorganizată. Soldul curent, zilele disponibile, zilele rezervate, dreptul anual și data resetării sunt prezentate distinct, iar acțiunea „Solicită concediu” este disponibilă atât în antet, cât și în starea goală. Cererile au status vizual, perioadă, număr de zile, notă, documente și numai acțiunile permise de starea lor și de ACL.

Stilurile folosesc tokenii temei TUVTK și variabilele native EspoCRM ca fallback, inclusiv pentru stări și mod responsive. Instrumentele manageriale rămân într-o zonă separată și apar numai prin drepturile existente. Nu au fost modificate endpoint-urile, calculele, soldul, ledger-ul, fluxul de aprobare sau navigația generală.

## Resetarea datelor Holiday de test — 0.7.7

Funcția originală din Holiday Management 1.9.4 este disponibilă în `#EmployeeAdministration/holiday`. Afișează o previzualizare și cere confirmarea exactă `RESET HOLIDAY DATA`. Șterge cererile Employee Holiday, ledger-ul, documentele publicate sau în staging, notificările de prag, proiecțiile de absență din Calendar și joburile de aprobare terminale, apoi readuce profilurile la Neinițializat cu sold și drept anual zero. Păstrează utilizatorii, rolurile, setările, WTC, sărbătorile legale și pontajele.

Resetarea este refuzată dacă acordarea anuală automată este activă sau există joburi de aprobare Pending/Ready/Running. Accesul cere cumulativ administrarea Employee Management, configurările Holiday, administrarea profilurilor, gestiunea cererilor, corecțiile de sold și read-all/FLS pentru cereri, profiluri și ledger. Nu acordă acces global la Jobs și nu modifică rolurile.

## Navigație fără sidenav automat — 0.7.5

Installer-ul nu adaugă `EmployeeManagement`, `EmployeeAttendance`, `EmployeeAttendanceOverview` sau `EmployeeAdministration` în `tabList`. Versiunile 0.7.5–0.7.8 eliminau la upgrade intrările vechi, dar această curățare putea elimina și selecții făcute ulterior manual; în 0.7.9 a fost retrasă complet. `#EmployeeManagement` rămâne pagina unică de intrare și conține linkurile funcționale existente. Scope-urile au în continuare metadata `tab=true`, deci administratorul le poate adăuga manual.

## Corecție create și flyout — 0.7.4

Crearea prin endpoint-ul nativ `POST EmployeeHolidayRequest` este acum un adaptor de compatibilitate către același `HolidayRequestService`; nu mai execută CRUD generic. Actorul rămâne utilizatorul curent, iar ACL/FLS, profilul, WTC, calculul, soldul, ledger-ul, suprapunerile și tranzacția sunt validate de serviciul contabil existent. Editarea și ștergerea CRUD rămân refuzate.

Intrările redundante „Employee Management” și „Employee Management — Administration” au fost eliminate din flyout-ul utilizatorului. Începând cu 0.7.5, nici taburile din sidebar nu mai sunt inserate automat; rutele rămân disponibile.

## Pagina personală Holiday — 0.7.3

`#EmployeeManagement/holiday` este intrarea canonică din workspace, iar `#EmployeeHolidayRequest` este o intrare compatibilă către aceeași pagină personală, fără implementare duplicată. `#EmployeeHolidayRequest/view/:id` păstrează detaliul nativ read-only. `#EmployeeHolidayRequest/create` deschide același formular de rezervare și execută comanda contabilă existentă, nu CRUD generic.

Angajatul vede soldul curent, zilele disponibile, zilele rezervate de cererile Pending/ApprovalProcessing, dreptul anual, următoarea resetare și lista cererilor proprii cu status, detaliu și operațiile deja implementate. Mesajele pentru profil neinițializat și eroare de încărcare sunt păstrate. Acțiunile manageriale existente sunt afișate separat și numai prin ACL/Role-urile native.

## Corecții funcționale — 0.7.2

PDF-ul reutilizează generatorul Attendance Management 1.11.0, randând paginile originale succesiv pentru a respecta limita PHP de memorie. **Raportul lunar PDF este autorizat prin EmployeeAttendanceManagement**, împreună cu EmployeeManagement, EmployeeAttendanceOverview și read all/FLS pe datele pontajului. Managerul nu are nevoie de permisiunea globală Export pentru acest PDF. Allow incomplete exports controlează numai completitudinea. Exportul XLSX și accesul PDF al unui viewer fără management păstrează cerința nativă Export=yes. Dezactivarea explicită globală a exporturilor rămâne respectată. Installer-ul nu modifică automat rolurile.

Antetul pontajului arată intervalele WTC native (inclusiv intervale separate și excepții), din norma lunară capturată, păstrând istoricul. Ruta `#EmployeeHolidayRequest/create` folosește formularul și comanda contabilă existente; endpoint-ul nativ alternativ este doar un adaptor către aceeași comandă.

Aprobatorii sunt utilizatorii regular activi cu permisiunea efectivă EmployeeHolidayApproval și acces EmployeeManagement din Roles native, inclusiv prin Teams. Nu se mai configurează o listă separată; vechile valori de rutare sunt ignorate. Accesul la cerere și câmpuri se verifică suplimentar, iar autoaprobarea rămâne permisă. Rolul poate avea orice nume: dreptul nativ este autoritatea, nu textul etichetei.

Corecția finală a butonului PDF și permisiunile exacte: `MONTHLY-PDF-FIX-RESULTS.md`. Cauze și dovezi anterioare: `FUNCTIONAL-FIXES-RESULTS.md` din surse. Etapa 8 nu a fost începută; navigația și designul general nu sunt reproiectate.

## Corecție urgentă de navigație — 0.7.0

În versiunea 0.7.0, sidebarul nativ includea automat Employee Management, Pontajul meu, Centralizator pontaje și Administrare angajați, filtrate prin ACL. Comportamentul actual nu mai adaugă și nu mai elimină linkuri la instalare sau upgrade; administratorul le selectează din editorul nativ de taburi. Dezinstalarea completă elimină numai intrările extensiei. Preferințele personale care înlocuiesc integral meniul global rămân respectate.

„Employee Schedules” deschide `#EmployeeAdministration/attendanceMetadata`. Politicile Attendance sunt în `#EmployeeAdministration/attendance`, configurările Holiday în `#EmployeeAdministration/holiday`, profilurile în `#EmployeeAdministration/profiles`. Salvați prin API-urile delegate; managerul regular nu primește Settings, Roles sau WTC edit. `#Admin/employeeAttendanceSettings` rămâne o rută tehnică refuzată regularilor și nu mai este folosită de extensie.

Pachetul se reinstalează ca upgrade inclusiv peste 0.7.0 prin installer-ul nativ. După upgrade, reîncărcați complet browserul pentru noile module frontend. Cauza și probele sunt documentate în `NAVIGATION-HOTFIX-RESULTS.md` din surse. **Etapa 8 nu a fost începută.**

## Funcții active

- `#EmployeeAdministration`: panouri Attendance, Holiday, Profiles și Employee Schedules (Remote/CIC), filtrate prin Roles native.
- Attendance: luni anterioare editabile și exporturi incomplete; prima lună a arhivei numai citire.
- Holiday: drept anual implicit, reset anual, prag de avertizare, limită negativă, aprobatori determinați din rolurile native și blocurile de semnături. Câmpul original al datei anuale păstrează și forma MM-DD.
- Profiluri: angajați regular activi, inițializare cu sold de deschidere, actualizarea parametrilor și completarea inițializării în condițiile originale stricte. Actualizarea parametrilor nu editează soldul curent.
- Ledger existent și idempotency, batch tranzacțional, lock pe User/profil și fingerprint pentru retry. Actorul provine din contextul serverului, nu din payload.
- Cele patru entități private rămân read-only pentru update/delete generic; singura excepție este adaptorul de creare EmployeeHolidayRequest, care nu salvează CRUD și deleagă serviciului contabil. ACL own/all și protecția Attachment din 0.1.0 sunt păstrate.
- `#EmployeeManagement/holiday`: cereri proprii, formularul original adaptat pentru creare/editare Pending și rezervare imediată; rezervare managerială pentru colegi în limita regulilor originale.
- Retragere păstrată ca Cancelled cu istoric, respingere, cerere/decizie de anulare și anulare managerială. Anulările sunt testate inclusiv după aprobări reale finalizate de worker, păstrând documentele ca istoric privat.
- Corecții de sold și istoricul lor prin modalele originale, cu scope nativ distinct și verificare a soldului afișat în formular.
- WTC explicit atribuit + ZileSarbatoare, fără indisponibilitate personală; snapshot contabil read-only cu zile, ore și distribuție pe luni. Nu există configurator paralel de normă.
- Tranzacții cu lock User→Profile→Request, revizie la tranzițiile de business, retry-uri fără debit/restituire dublă. Unicul owner al contabilizării cererilor este serviciul de comenzi; hook-ul vechi Balance nu este instalat în noul modul.
- `#EmployeeAttendance` / `#EmployeeAttendanceOverview`: paginile originale de pontare și matrice lunară; WTC explicit atribuit tehnic + ZileSarbatoare. Nu se creează programe normative proprii.
- Norma zilelor/orelor/intervalelor este fixată read-only la prima utilizare a lunii. WTC modificat sau reatribuit ulterior nu rescrie perioada deja inițializată. Nu reconstruiește programe istorice care nu au fost capturate.
- Înghețare persistentă la 100% din luna întreagă, fără zile normative viitoare sau lipsă. Managerul poate corecta cu motiv și audit nativ, inclusiv după înghețare; exporturile PDF/XLSX sunt regenerate din datele curente.
- Remote și CIC/CCC se configurează în `#EmployeeAdministration/attendanceMetadata`, fără editor de ore/WTC. CO activ are prioritate; CIC interzice lucrul/deplasarea, dar permite medicalul. Corecțiile explicite ale metadatelor operative păstrează comportamentul original, sunt auditate și nu dezgheață luna/norma.
- Reminderele originale folosesc Notifications native pentru lipsurile reale, fără zile viitoare/admini. Inițializarea originală a vizibilității arhivei este activă în setările Attendance, fără ștergerea datelor.
- Crearea și orice modificare Pending verifică pontajele AtWork/BusinessTrip sub lock pe beneficiar; conflictul este refuzat fără rezervare/ledger parțial. Aprobarea verifică din nou conflictul, atât la inițiere, cât și în finalizarea tranzacțională a Job-ului.
- Rezervările noi folosesc rezultatele normative lunare comune Attendance: prima utilizare capturează WTC/ZileSarbatoare, iar modificarea ulterioară a calendarului nu produce două norme diferite. Snapshot-urile contabile deja rezervate nu sunt rescrise.
- Approved și CancellationPending rămân absențe efective până la anularea definitivă; tranzițiile sunt serializate și cu lectura lunii/reminderele, nu doar cu pontarea. Anularea restituie exact o dată și nu dezgheață foaia; eventualele lipsuri rezultate se corectează managerial cu audit. Nu sunt șterse sau fabricate pontaje.

**Pending → ApprovalProcessing → Approved este activ.** Managerul autorizat inițiază decizia, inclusiv pentru sine. JobSchedulerFactory programează atomic un Job nativ grupat pe cerere. Generatorul/template-ul DOCX originale sunt păstrate; numele, semnăturile, datele și valorile contabile provin din snapshot-ul deciziei, nu din configurația de la retry.

Fiecare Attachment are un ID urmărit înainte de scrierea fișierului. Documentele parțiale folosesc câmpul nativ approvalStaging cu read interzis; numai finalizarea verificată publică approvalDocuments și Approved. Retry-ul reutilizează fișierele valide, nu rezervă și nu debitează iar soldul. Template-ul este verificat prin hash; schimbarea lui în timpul procesării cere restaurarea versiunii înregistrate, nu randare tacită cu alt template.

Erorile sunt vizibile în pagina privată; managerul poate relua aceeași operație. Un Job Pending/Ready/Running nu este suprascris pe baza unui timeout propriu: după un restart se așteaptă detecția nativă Failed (implicit aproximativ 300 secunde), apoi retry-ul autorizat programează un nou Job cu fencing token. Nu este un sistem propriu de Jobs și managerul nu primește acces global la Jobs. UI-ul verifică periodic cererile în procesare; API-ul/descărcarea nativă sunt disponibile imediat după commit-ul final. Cron/daemon sunt responsabilitatea administratorului tehnic.

Administratorul tehnic este refuzat pe workspace și API-urile de business. Roles, WTC și infrastructura rămân în administrarea nativă a platformei. Pachetul nu definește #Admin, calendarView sau câmpuri globale Settings și nu atribuie automat roluri.

## Roles native

Toate scopes noi sunt implicit refuzate. Administratorul configurează explicit:

| Scope / drept | Utilizare |
| --- | --- |
| EmployeeManagement | Workspace de citire |
| EmployeeAdministration | Intrarea delegată; singură nu acordă nicio secțiune |
| EmployeeAttendanceSettings | Politici Attendance |
| EmployeeHolidaySettings | Configurări Holiday și descoperire în citire a aprobatorilor |
| EmployeeHolidayProfileManagement | Inițializare și parametri profiluri |
| EmployeeHolidayProfile.read / EmployeeHolidayLedger.read | all și acces la câmpurile necesare pentru administrarea tuturor profilurilor; own pentru lectura proprie |
| EmployeeHolidayApproval | Aprobare și retry autorizate, inclusiv autoaprobare; împreună cu ACL read al cererii |
| EmployeeHolidayBooking | Cereri proprii și comenzile aferente; necesită și citire nativă a cererii/câmpurilor |
| EmployeeHolidayRequestManagement | Rezervare pentru colegi și gestionare Pending, împreună cu Booking și Request read all |
| EmployeeHolidayBalanceCorrection | Corecții și istoric; necesită și drepturile de administrare a profilurilor |
| EmployeeAttendance | Pagina/pontarea proprie; AttendanceRecord și AttendanceWorkSchedule read own/câmpuri |
| EmployeeAttendanceOverview | Matrice lunară; cele două entități Attendance read all/câmpuri |
| EmployeeAttendanceManagement | Corecții, remindere și metadate Remote/CIC, fără administrare WTC |
| Permisiunea nativă Export | Cerută pentru XLSX și PDF al unui viewer fără AttendanceManagement; managerul poate descărca PDF-ul lunar prin dreptul său operațional, fără grant global |

Respingerea și deciziile de anulare cer EmployeeHolidayApproval, actor regular activ cu dreptul efectiv de aprobare și acces nativ la înregistrare/câmpuri. Lista de așteptare cere read all. Autoaprobarea managerului autorizat folosește același Job și aceleași verificări. Beneficiarul trebuie să aibă read nativ pe cererea proprie și câmpul approvalDocuments; în finalizare se reverifică și autorizarea decidentului.

Controalele se aplică în UI și backend, inclusiv la acces direct. Profile management nu ocolește read own sau restricțiile de câmp. Aprobatorii provin exclusiv din permisiunile efective ale rolurilor native. Nu acordați User edit, Role, Settings, Jobs sau administrare WTC prin rolurile extensiei.

## Calendar și automatizări anuale

- Calendarul nativ citește EmployeeAbsence: numai numele și intervalul Approved/CancellationPending, fără link la cerere, motive, solduri sau documente. Pending apare doar în sursa privată autorizată, nu în cea comună. Proiecția se actualizează tranzacțional; nu este sursa pontajului sau a contabilității.
- Acordați explicit rolurilor **Calendar=yes**, **EmployeeAbsence.read=all**, fără create/edit/delete. Nu se instalează granturi mandatory. Lecturile prin API/ID și Calendar păstrează ACL/FLS; administratorii tehnici/inactivii nu apar ca angajați.
- Nu se livrează view Calendar global. ZileSarbatoare păstrează propriul calendarView/timelineView. Dacă originalul Holiday este încă instalat și suprascrie view-ul, conflictul lui vechi nu este reparat printr-un alt override Employee Management. Testați pachetul într-o instanță separată cu ZileSarbatoare, fără vechile extensii operaționale.
- ScheduledJob **ProcessEmployeeHolidayResets**, expresia originală **5 0 * * *** în fusul orar al scheduler-ului nativ, folosește metoda originală processDueResets/reset/applyResetGrant și BalanceMath: soldul se **adună** cu entitlement, fără pierderea zilelor acumulate; nextResetDate avansează cu un an, inclusiv recuperarea anilor scadenți (limita originală 100 iterații).
- Job-ul nativ este înregistrat la instalare, dar **nu acordă zile** până când managerul autorizat configurează explicit opțiunea din #EmployeeAdministration/holiday. Upgrade-ul nu activează opțiunea și nu resetează alegerea existentă. Administratorul tehnic gestionează cron/daemon/Scheduled Jobs, nu managerul.
- Unicitate per profil/scadență, tranzacție User→Profile→Ledger, notificări originale de prag numai destinatarilor autorizați. Jobs duplicate/reluate nu dublează soldul, ledger-ul sau notificările. O eroare rămâne vizibilă în istoricul nativ tehnic; reluarea folosește același ScheduledJob, fără panou Jobs delegat.

## Funcții păstrate, încă neactivate

Overview Holiday/dashletul complet și comanda manuală de reset forțat a unui singur profil rămân păstrate în inventar pentru validarea finală; nu sunt expuse suplimentar în această etapă. Resetarea globală explicită a datelor de test este activă. Calendarul comun și acordările anuale automate sunt active, acestea din urmă numai după configurare explicită. Attendance citește Approved/CancellationPending în lot, fără duplicarea ledger-ului sau modificarea cererilor Holiday. Finalizarea documentelor este testată prin worker nativ, eșec, retry, concurență și restart real; fixture-urile etapei 5 nu substituie aceste probe.

Aceste funcții nu sunt eliminate: implementările originale rămân în pending și în inventar. Generatoarele PDF/XLSX/DOCX, matematica soldului și politica datelor sunt reutilizate și au teste de regresie. Complete Initial Setup nu este o corecție generală: păstrează condițiile privind soldul zero, ledger-ul și lipsa cererilor.

## API și build

API-uri delegate: GET/PUT `EmployeeManagement/administration/attendance`, GET `.../attendance/testDataResetPreview`, POST `.../attendance/testDataReset`, GET/PUT `.../holiday`, GET `.../approvers`, GET `.../profiles`, POST `.../profiles/bulkInitialize`, POST `.../profiles/completeInitialSetup`, GET `.../holiday/testDataResetPreview` și POST `.../holiday/testDataReset`. Necesită regular activ, intrare și drepturile secțiunii. Resetările cer drepturile cumulative documentate și confirmarea exactă. Payload-urile sunt allowlist; nu există flag de bypass sau acces la Settings global.

Holiday: GET `EmployeeManagement/holiday/{balance|requests|queue|employees}`; POST `.../holiday/create`; POST `.../holiday/{id}/{update|withdraw|reject|approve|retryApproval|requestCancellation|decideCancellation|cancel}`. Mutațiile cer `idempotencyKey`; comenzile pe o cerere cer `revision`. Create folosește `dateStart`, `dateEnd`, opțional description/userId/managed. Update folosește dateStart/dateEnd/description. Anularea cere reason; decideCancellation primește Approved (refuzul anulării existente cu document) sau Cancelled. `approve` inițiază Processing; `retryApproval` cere aceeași revizie de business și un Job tehnic terminal/inexistent. Nu acceptă Job IDs sau status din client.

Corecții: POST `EmployeeManagement/administration/profiles/correct` cu profileId/delta/reason/idempotencyKey și opțional expectedBalance; POST `.../profiles/corrections` cu profileId pentru istoricul original. Nu sunt endpoint-uri CRUD generale.

Attendance: GET `EmployeeManagement/attendance/{myAttendance|overview}` cu month=YYYY-MM, GET `.../schedules`; POST `.../{mark|managerMarkRoutine|managerMark|schedule|remind|xlsx|pdf|initialize}`. Mark primește date/status și opțional revision; managerMarkRoutine adaugă userId și este limitat la lunile editabile, fără reason; managerMark păstrează corecția excepțională cu userId/reason obligatoriu. Schedule acceptă numai userId/remote/cic/cicStartDate/cicEndDate, niciodată ore, WTC sau assignedUser impus pentru o pontare proprie. Exporturile păstrează răspunsul original filename/mediaType/content base64. Initialize păstrează datele, schimbând numai începutul arhivei vizibile la luna curentă.

```bash
bash ./build.sh --extension ./EmployeeManagement --zip 0.8.13 files scripts
python3 EmployeeManagement/tests/contract.py
node EmployeeManagement/tests/regression/reset-date-field.test.mjs
```

ZIP: **dist/employee-management-0.8.13.zip**, generat de harness-ul comun **/opt/espo_extensions/build.sh**. `EmployeeManagement/build.sh` deleagă aceluiași harness. Include numai manifest, README, files și scripturile CLI/lifecycle proprii. Pending, teste, inventare și uneltele de import nu sunt instalate. ZIP-ul este ignorat de Git; scriptul comun nu este modificat.

Installer-ul nu șterge date, nu migrează originalele și nu modifică Roles/WTC. Coexistența cu originalele este un scenariu controlat de laborator, nu recomandare de producție.

## Trasabilitate și teste

- [Notificări email Holiday 0.8.12](HOLIDAY-APPROVAL-EMAIL-0.8.12-RESULTS.md): mecanism nativ, probe SMTP controlate, retry și conservarea datelor la upgrade.
- [Inventar funcțional și modificări](MIGRATION-INVENTORY.md).
- REUSE-MAP.json: importul inițial și hash-urile surselor.
- STAGE-2-REUSE.json: componentele activate și adaptările.
- STAGE-3-REUSE.json: proveniența și adaptările Holiday din această etapă.
- STAGE-4-REUSE.json: proveniența Attendance, inclusiv generatoarele adaptate punctual la intervalele zilnice WTC.
- STAGE-5-REUSE.json: adaptările punctuale de consistență peste serviciile Holiday/Attendance deja reutilizate.
- MIGRATION-INVENTORY.json: toate fișierele runtime/lifecycle originale și hash-urile implementărilor integrate.
- STAGE-6-REUSE.json: documente din snapshot și proiecții locking adaptate justificat.
- STAGE-7-REUSE.json: query Calendar original adaptat, resetări/acordări/notificări și lifecycle reutilizate.
- [Corecția de navigație și acces non-admin](NAVIGATION-HOTFIX-RESULTS.md), capturi și rezultate browser în tests/evidence/navigation-*.
- [Rezultatele etapei 7](STAGE-7-RESULTS.md) și [testare manuală](MANUAL-TEST-STAGE-7.md).
- [Rezultatele etapei 6](STAGE-6-RESULTS.md), limite și dovezi JSON; rapoartele etapelor precedente rămân istorice.

Rețetele de import din tools sunt trasă de adaptare, nu generatoare de rulat peste implementarea întreținută. Build-ul execută numai validarea și împachetarea; migration-inventory.py emite un patch de inventar fără modificarea originalelor.

Fixture-urile se execută exclusiv în laboratorul employee-feasibility-20261020, cu opt-in și parole sintetice în variabile de mediu. Nu se rulează pe datele companiei și nu necesită infrastructură permanentă nouă.
