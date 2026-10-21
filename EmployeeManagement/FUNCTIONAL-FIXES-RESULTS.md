# Employee Management 0.7.0 — corecții funcționale

> Livrarea curentă este **0.7.2**, în `dist/employee-management-0.7.2.zip` din rădăcina repository-ului. **Corecția finală a butonului PDF și permisiunile necesare:** [MONTHLY-PDF-FIX-RESULTS.md](MONTHLY-PDF-FIX-RESULTS.md). Cerința de Export global pentru PDF descrisă mai jos este istorică și a fost înlocuită pentru managerii de pontaj. Rezultatele de mai jos aparțin execuțiilor anterioare; nu sunt prezentate ca rerulate pe 0.7.2.

Data: 20 octombrie 2026. Domeniu: exclusiv cele patru probleme solicitate. Etapa 8 nu a fost începută. Nu s-au reproiectat navigația, dashlet-urile sau interfața generală.

## 1. PDF: buton dezactivat și generare

**Cauza butonului dezactivat:** `Allow incomplete exports` elimină numai condiția de completare a pontajului. Nu acordă permisiunea nativă EspoCRM **Export**. Toate scope-urile de management pot fi activate, în timp ce Export rămâne refuzat; backend-ul `AttendanceAccessChecker::assertExport` refuză corect acel utilizator. Scenariul a fost reprodus în browser cu un manager NON-ADMIN având toate scope-urile extensiei și Export=no. Nu am inspectat rolul din instalația companiei; această reproducere nu pretinde că dovedește configurația exactă a acelui cont.

**Corecție:** butonul păstrează autorizarea backend și explică prin tooltip permisiunea Export lipsă sau dezactivarea globală. Starea de după descărcare păstrează și `exportAllowed`, nu numai `downloadReady`. Pentru descărcare trebuie configurat **Export=yes în rolul nativ al managerului** și exportul global trebuie permis. Installer-ul nu acordă automat permisiuni și nu transformă managerul în administrator.

**Un defect suplimentar reprodus la generare:** într-un laborator acumulat cu 439 de angajați activi, randarea întregului HTML prin Dompdf depășea limita PHP de 256 MB, producând HTTP 500. Acesta este separat de butonul dezactivat; nu afirm că volumul datelor companiei este identic. Generatorul reutilizat din **Attendance Management 1.11.0** păstrează pagina originală cu șapte angajați, stilurile, tabelul și numerotarea. Randează succesiv paginile în același canvas CPDF și eliberează arborii intermediari. Proba de 439 angajați a produs 63 pagini folosind aproximativ 61 MB, în aproximativ 9 secunde. Nu s-a introdus alt motor PDF.

**Dovadă funcțională:** Chromium/Playwright, manager regular activ cu Export=yes: click pe buton, eveniment de download, fișier salvat local, semnătură și EOF PDF. Descărcarea XLSX este verificată separat. Documentul PDF descărcat este deschis cu un parser independent și verificat pentru pagini, A4, titluri, intervale native și numerotare. Nu este numai un test API.

## 2. WTC: intervale reale

**Cauza:** antetul frontend afișa exclusiv `normHours + h / WTC`, deși norma lunară capturată conținea deja intervalele zilnice provenite din calendarul nativ atribuit.

**Corecție:** `AttendanceOverviewService` expune grupări ale intervalelor capturate, iar antetul afișează `11:00–17:00`, intervale separate sau variații. Tooltip-ul asociază variantele cu datele lor. Sunt folosite datele native din WorkingTimeCalendar/WorkingTimeRange prin calculatorul existent; nu există un configurator WTC nou. O lună deja inițializată păstrează snapshot-ul istoric, conform regulii existente.

**Probe:** WTC atribuit 11–17, program separat 08–12 / 13–17 și excepție nativă 10–16. Verificate în API, în browser și în PDF. Captură: `tests/evidence/functional-wtc.png`.

## 3. Creare concediu: „No create access.”

**Cauza exactă:** ruta nativă `#EmployeeHolidayRequest/create` ajungea în formularul CRUD generic. În EspoCRM 10.0.1, `Espo/Core/Record/Service.php` respinge crearea când checker-ul instalat `ReadOnlyAccessChecker::checkCreate` întoarce false. Rezultatul este 403, `No create access.`. Soldul și inițializarea profilului nu schimbă acest checker. Entitatea este intenționat read-only prin CRUD pentru a proteja rezervarea și ledger-ul.

**Corecție:** ruta de creare este direcționată către formularul de rezervare existent și comanda existentă `EmployeeManagement/holiday/create`. Nu se deschide CRUD-ul generic. Se păstrează utilizatorul regular activ, EmployeeManagement, EmployeeHolidayBooking, ACL read own/all, FLS, profilul, WTC, limita negativă, lock-urile, reviziile și idempotency. Managerul și angajatul sunt verificați separat.

**Probe în browser:** manager NON-ADMIN creează cerere proprie din URL-ul nativ, o vede după reîncărcare și o autoaprobă. Angajat NON-ADMIN creează din workspace, vede cererea salvată, modifică descrierea și verifică persistența după reîncărcare. Managerul aprobă cererea angajatului, worker-ul nativ finalizează, iar angajatul descarcă efectiv DOCX-ul privat. Conturile fără Booking sau cu restricție FLS sunt refuzate; angajatul nu primește rezervare managerială/aprobare. CRUD generic rămâne 403 pentru manager și angajat.

Aprobarea și apariția documentului sunt verificate după finalizarea worker-ului și reîncărcarea paginii; aceste probe nu certifică actualizarea automată a tuturor view-urilor SPA fără reîncărcare.

## 4. Aprobatori determinați automat

**Cauza:** pe lângă permisiunea nativă de aprobare, serviciile cereau și prezența într-o listă `employeeHolidayApproversIds`. Lista putea fi goală sau nealiniată cu Roles, blocând un manager altfel autorizat.

**Corecție:** `HolidayApproverProvider` identifică utilizatorii regular activi cu drepturile efective EmployeeManagement și EmployeeHolidayApproval, prin AclManager nativ, inclusiv roluri moștenite din Teams. Aceeași sursă este folosită la decizie, reverificarea worker-ului și notificări. ACL/FLS pe cerere/profil se aplică suplimentar. Câmpul redundant a fost scos din formular și API-ul de configurare; valorile istorice ascunse sunt ignorate. Payload-ul vechi care încearcă să configureze lista este refuzat.

„Holiday Manager” este rolul care acordă dreptul nativ de aprobare; identificarea nu depinde de numele textual al rolului. Autoaprobarea rămâne permisă. Nu s-a creat un sistem paralel de permisiuni.

## Mediu și validare

Exclusiv Docker izolat `employee-feasibility-20261020`, EspoCRM **10.0.1**, PHP **8.4.23**, Zile Sărbătoare **0.9.5**; conturi și date sintetice. Extensiile originale și instalația companiei nu au fost modificate. Browser Chromium cu sesiuni separate pentru manager, angajat și conturi cu acces limitat.

Rezultatele finale și numărul exact de verificări sunt centralizate în `tests/evidence/functional-final-environment.json`. Fișierele `functional-before*` arată reproducerea înainte de corecție; `functional-export-browser.json` și `functional-booking-browser.json` sunt probele funcționale finale. PDF/XLSX/DOCX și capturile sunt păstrate în același director.

Regresiile existente sunt rulate pentru ACL, administrare, comenzi/ledger Holiday și Attendance. Copiile `functional-regression-*` schimbă numai așteptările devenite intenționat obsolete privind lista manuală de aprobatori; verificările de securitate și contabilitate sunt păstrate. Timeout-ul clientului testului Holiday este 120 secunde pentru proba unei cereri pe șapte luni în laboratorul acumulat. Nu reprezintă o schimbare a limitei de execuție din produs.

Au existat execuții intermediare nereușite: instalare concurentă cu testarea, două suite care modificau aceeași normă lunară, timeout-ul clientului testului și o așteptare incorectă a mesajului FLS în browser. Aceste execuții nu sunt numărate ca PASS. Probele finale rulează secvențial pe datele de test relevante. Rezultatele istorice ale etapelor precedente sunt păstrate ca istoric și nu sunt prezentate ca rerulate integral acum.

Confirmarea de regresie se referă la fluxurile și verificările enumerate în dovezile finale: drepturi native, persistență, sold/ledger, pontare, corecții, exporturi și generatoarele reutilizate. Nu reprezintă certificarea tuturor combinațiilor posibile de configurație în producție.


### Rezultate finale

| Suită | Verificări trecute | Eșecuri finale |
| --- | ---: | ---: |
| ACL existent | 126 | 0 |
| Administrare delegată existentă | 120 | 0 |
| Holiday / sold / ledger existent | 126 | 0 |
| Attendance existent + suplimentar | 94 | 0 |
| Attendance existent în browser | 26 | 0 |
| Generatoare și reguli originale reutilizate | 13 | 0 |
| Cele patru corecții — API / securitate | 21 | 0 |
| Cele patru corecții — browser export/configurări/WTC | 16 | 0 |
| Cele patru corecții — browser concedii/aprobare | 20 | 0 |
| PDF descărcat — parser independent | 8 | 0 |
| **Total rezultate structurate finale** | **570** | **0** |

Separat: contractul ZIP/surse/proveniență a trecut; regresia câmpului de dată (2), lifecycle-ul navigației (5) și layout-ul PDF au trecut. Cele 62 verificări în browser includ probe funcționale și refuzuri de acces. PDF-ul final descărcat are **77 pagini / 1.046.245 octeți**; parserul verifică structura tuturor paginilor și textul unui eșantion explicit de nouă pagini, inclusiv prima și ultima. O descărcare anterioară cu același cod, de 65 pagini, a fost verificată textual integral. XLSX-ul și DOCX-ul sunt de asemenea fișiere descărcate efectiv, păstrate ca dovezi.

## ZIP

`dist/employee-management-0.7.0.zip`, versiune păstrată **0.7.0**, upgrade prin installer-ul nativ. Manifestul este la rădăcină, împreună cu README, `files/` și scripturile lifecycle proprii. Pachetul nu include teste, date sintetice, dovezi, inventare, `pending/` sau un director wrapper. CRC-ul arhivei și egalitatea fișierelor instalabile cu sursele sunt verificate. SHA-256: `66632b66dfee1c7ab07f7e3996bd0e2a77fa3f78e75b4fc65028ab20595dfc2d`, disponibil și în `dist/employee-management-0.7.0.zip.sha256` și în dovada finală. Cele 166 fișiere runtime din ZIP au fost comparate cu instalarea efectiv testată: zero diferențe.

După upgrade: reîncărcare completă a browserului. Verificați explicit Export=yes în rolul nativ pentru managerii care trebuie să descarce pontajul. Nu este necesar acces global de administrator.
