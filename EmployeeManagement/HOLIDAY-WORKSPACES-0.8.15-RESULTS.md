# Employee Management 0.8.15 — separarea workspace-urilor Holiday

## Rezultat

Pagina `#EmployeeHolidayRequest` este exclusiv personală pentru orice angajat, inclusiv pentru manageri. Funcțiile manageriale existente sunt disponibile separat în `#EmployeeHolidayManagement`, prin rute și view-uri native EspoCRM. Versiunea de livrare este **0.8.15**; backend-ul Holiday, endpoint-urile, contabilitatea și schema nu au fost modificate.

## Arhitectură și permisiuni

- View-ul managerial extinde view-ul personal pentru a reutiliza transformarea rândurilor, statusurile, documentele, comenzile, modalele și coordonarea polling/refresh. Nu există un al doilea API sau un motor de autorizare/filtering.
- `approvals` și `cancellations` folosesc același `GET EmployeeManagement/holiday/queue`. Filtrarea locală păstrează `Pending` + `ApprovalProcessing` în aprobări și `CancellationPending` în anulări. `approved` folosește endpoint-ul existent.
- `Book for Employee` deschide `employee-management:views/modals/managed-booking` cu `managed:true`; verificarea deschide ruta existentă `#EmployeeHolidayDocumentVerification`.
- Controllerul cere utilizator regular activ, `EmployeeManagement` și Request read-all înainte de a deschide manager workspace. Secțiunile de aprobare cer `EmployeeHolidayApproval`; rezervarea managerială cere `EmployeeHolidayBooking`, `EmployeeHolidayRequestManagement` și Profile read-all. Toate operațiile sunt reverificate de serviciile backend existente.
- Noul scope non-entity `EmployeeHolidayManagement` are `tab:true`, ACL boolean și icon nativ `fas fa-user-shield`. El controlează numai disponibilitatea tabului; nu acordă acces la coadă, profiluri sau comenzi.

Configurarea Rolurilor este manuală. Pentru managerii care trebuie să vadă tabul, administratorul acordă `EmployeeHolidayManagement: Yes` și adaugă scope-ul în navigație prin editorul nativ. Apoi acordă separat numai drepturile operaționale necesare. Installer-ul nu schimbă Roles, `tabList` sau preferințele utilizatorilor.

## Fișiere runtime modificate

- `files/client/custom/modules/employee-management/src/views/holiday-request/index.js` — elimină complet UI-ul și acțiunile manageriale din pagina personală; păstrează operațiile personale și comportamentul de polling.
- `files/client/custom/modules/employee-management/src/views/holiday-management/index.js` — noul workspace managerial cu cinci opțiuni persistente și reutilizarea listelor/comenzilor existente.
- `files/client/custom/modules/employee-management/src/controllers/workspace.js` — rute și autorizare client distinctă, aliniată condițiilor backend.
- `files/client/custom/modules/employee-management/src/views/holiday-document/verification.js` — linkul de întoarcere conduce la workspace-ul managerial; logica de verificare nu este schimbată.
- `files/client/custom/modules/employee-management/css/holiday.css` — stiluri responsive izolate sub `.employee-holiday-management-page`.
- `Resources/metadata/app/clientRoutes.json` — `EmployeeHolidayManagement` și `EmployeeHolidayManagement/:section`.
- `Resources/metadata/scopes/EmployeeHolidayManagement.json` și `Resources/metadata/clientDefs/EmployeeHolidayManagement.json` — tab nativ și icon.
- `Resources/metadata/app/acl.json` — noul scope rămâne implicit refuzat.
- `Resources/i18n/{ro_RO,en_US}/{Global,EmployeeHolidayRequest}.json` — denumiri și mesaje native.
- `README.md` — rute, permisiuni și configurare.

Fișierele de test actualizate/adăugate sunt `tests/contract.py`, `tests/regression/holiday-workspaces.test.mjs`, `tests/browser/holiday-workspaces.cjs`, `tests/browser/holiday-polling.cjs` și `tests/integration/holiday-workspaces-runtime.php`.

## Teste

| Verificare | Rezultat |
| --- | --- |
| JavaScript separation + polling | **PASS**, 17/17 teste focalizate |
| Toate regresiile Node (Attendance, email, DOCX, Ledger, polling, workspace) | **PASS**, 29/29 |
| Chromium real — workspace-uri, ACL, combinații de permisiuni, responsive, i18n | **PASS**, 39/39 |
| Polling Chromium — 5 s processing, 30 s normal, backoff, visibility, cleanup | **PASS** |
| DOCX financiar/verificare/integritate | **PASS**, toate probele existente |
| PHP lint | **PASS**, 60 fișiere |
| JSON metadata/i18n | **PASS** |
| Contract pachet și egalitate ZIP–surse | **PASS** |
| Integritate ZIP | **PASS** |
| Upgrade 0.8.14 → 0.8.15 + rebuild + clear-cache | **PASS** |

Chromium a confirmat pe datele sintetice: 99 cereri `Pending`/`ApprovalProcessing`, 21 `CancellationPending` și 28 `Approved`, exact cât răspunsurile endpoint-urilor existente. Opțiunile au rămas vizibile în toate secțiunile; Back/Forward a restaurat secțiunea; pagina mobilă a avut `scrollWidth=350`, `clientWidth=350`; nu au existat erori JavaScript.

Combinațiile de Rol au fost verificate separat:

- aprobator fără management booking: vede aprobări, anulări, aprobate și verificare, fără rezervare;
- manager booking fără approval: vede numai rezervarea și primește 403 la coada de aprobare;
- angajat personal: primește 403 la ruta managerială și la API-ul queue.

## Capturi Chromium

- [Angajat — pagina personală](tests/evidence/holiday-workspaces-employee-personal-desktop.png)
- [Manager — pagina personală fără acțiuni manageriale](tests/evidence/holiday-workspaces-manager-personal-desktop.png)
- [Manager — landing management](tests/evidence/holiday-workspaces-manager-landing-desktop.png)
- [Manager — landing în română](tests/evidence/holiday-workspaces-manager-landing-ro-desktop.png)
- [Manager — aprobări](tests/evidence/holiday-workspaces-manager-approvals-desktop.png)
- [Manager — anulări](tests/evidence/holiday-workspaces-manager-cancellations-desktop.png)
- [Manager — concedii aprobate](tests/evidence/holiday-workspaces-manager-approved-desktop.png)
- [Manager — mobil](tests/evidence/holiday-workspaces-manager-mobile.png)
- [Sidebar și icon nativ](tests/evidence/holiday-workspaces-sidebar-icon.png)

## Conservarea datelor

Upgrade-ul pachetului 0.8.14 → 0.8.15 nu a produs diferențe înainte/după:

| Entitate | Număr | SHA-256 |
| --- | ---: | --- |
| EmployeeHolidayLedger | 902 | `5c262fe421f0d3d11d49ed04fbc8c4b2f6b283bc7f8d33b058865eed144d6aa5` |
| EmployeeHolidayRequest | 315 | `cbe638d328788925575a83feb523431a6b14841bc2ee6f07c9ccef56cfdd861f` |
| EmployeeHolidayProfile | 349 | `2dbfca504ad215ed592afc7f1368b0350083d80ce98f99b4f49adc02842679ad` |
| EmployeeHolidayDocument | 4 | `54cadb3aadb1ec97c4fdcecc2adda6f41e86a6bf00d4aaae05e1176df9602526` |
| Holiday attachments | 113 | `a9ce0793181f320c4b2d561e19716bdbf23a6eae01171d839d84ae176da8fc65` |
| Role | 507 | `9867a2784bdf7edaf4ff9aab54199a69408490b8ae424dab3818ca86ac6767f2` |

Rolurile și tabul temporare pentru testarea ACL au fost create numai după comparația de upgrade și apoi restaurate. Verificarea finală a reconfirmat aceleași numere și hash-uri. Producția nu a fost accesată.

## Deploy pentru o instanță cu 0.8.14 instalat

1. Faceți backup și folosiți o fereastră de mentenanță.
2. Instalați `employee-management-0.8.15.zip` prin Extension Manager nativ; acesta este un upgrade normal de la 0.8.14, fără bypass de versiune.
3. Din rădăcina EspoCRM rulați comenzile native `php command.php rebuild` și `php command.php clear-cache` dacă procedura organizației nu le execută deja.
4. Acordați manual scope-ul de navigație `EmployeeHolidayManagement` Rolurilor manageriale și adăugați tabul prin editorul nativ. Nu modificați Rolurile angajaților obișnuiți.
5. Cereți utilizatorilor reload fără cache și validați pagina personală, ruta managerială și iconul.

Nu există migrare, backfill, regenerare de documente sau schimbare a datelor de business.

## Pachet

- `dist/employee-management-0.8.15.zip`
- SHA-256: `f0051b3145150b53180e99fcd97e9f2efde59e02ed4ccd58a2f0606dcd2a6a30`
