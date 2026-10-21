# Cod reutilizat, încă neexpus

Aceste fișiere sunt copii adaptate mecanic din cele două extensii, nu o nouă implementare. Proveniența și hash-urile se găsesc în `../REUSE-MAP.json`.

Directorul nu intră în ZIP și nu este autoloadat. Nu se copiează integral în `files/`: ar reactiva comportamente încă necorectate.

Stare 0.3.0: subseturile de administrare/profiluri din etapa 2 și cereri/rezervări/anulări/corecții Holiday din etapa 3 sunt integrate prin adaptări punctuale în files. Copiile de aici rămân integrale pentru proveniență și funcțiile încă neintegrate. MIGRATION-INVENTORY.md și STAGE-3-REUSE.json precizează exact metodele/destinațiile; nu se interpretează întregul director ca funcționalitate lipsă și nu se activează global aprobarea veche sincronă.

Actualizare 0.4.0: AttendanceService, AttendanceOverviewService, proiecția concediilor, paginile my/overview/schedules, controllerele/CSS și orchestrarea PDF/XLSX sunt integrate în files, cu sursele și adaptările enumerate în STAGE-4-REUSE.json și MIGRATION-INVENTORY.md. Norma provine exclusiv din WTC/ZileSarbatoare și rezultatul lunar read-only; Remote/CIC rămân metadate operative. Înghețarea, corecțiile auditate și reminderele sunt active. Copiile pending rămân păstrate, nu sunt încărcate în paralel. Consistența completă la tranzițiile Holiday, Jobs și Calendar rămân pentru etapele următoare.

| Grup | Ce se păstrează | Condiția de activare |
| --- | --- | --- |
| Attendance/Tools/Attendance | Marcaje, matrice, export orchestration, CIC/CCC, înghețare și remindere | ACL regular + scopes; ownership assignedUser consecvent; normă WTC + istoric read-only; P1/P4/P7/P9 |
| Holiday/Tools/HolidayBalance | Sold, rezervări, ledger, reset/corecții | Adaptarea user→assignedUser în queries și payload-uri interne; ACL și eligibilitate; tranzacții/revizii/idempotency P1/P3/P5 |
| Holiday/Tools/EmployeeHolidayRequest | Politici și operații de cerere/aprobare/anulare | Roluri native, fără allowlist de approvers ca sistem de autorizare paralel; Pending→ApprovalProcessing→Approved numai după document; reguli de suprapunere și autoaprobare aprobate |
| Holiday/Tools/HolidayDocument | Persistarea documentelor din generatorul existent | Snapshot stabil, Job nativ, retry/recuperare, protecția DB–storage și documentelor P8 |
| Hooks/Jobs/Services/Controllers | Puncte de integrare și efecte de business existente | Eliminarea fetched-state races și a dublării efectelor; niciun scheduled job vechi înregistrat prematur |
| UI/metadata/layouts | Matrice, formulare, overview-uri și modale utile | Referințe noi, backend securizat, niciun editor paralel WTC, niciun override global Admin/Calendar |

`AttendanceWorkSchedule` din această copie include încă modelul vechi al programului. Acesta nu este înregistrat de pachetul 0.1.0 și nu va fi activat ca sursă de ore: rămân numai datele Attendance justificate (remote/CIC/CCC) și rezultatele istorice derivate. Nu rescriem regulile CIC/CCC sau generatoarele pentru a schimba sursa normativă.

Cheile config sunt prefixate `employeeAttendance`/`employeeHoliday`, dar viitoarea administrare trebuie să expună numai allowlist-ul justificat. Reset-ul distructiv de date de test, granturile mandatory și hooks/rutele vechi nu se activează automat. Nu considerăm P1–P11 rezolvate prin schimbarea namespace-urilor.
