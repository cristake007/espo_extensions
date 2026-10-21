# Employee Management 0.7.3 — restaurarea paginii personale Holiday

## Domeniu

Modificările sunt limitate la pagina personală de concedii, cele două rute de intrare și textele aferente. Nu au fost modificate extensiile Holiday Management 1.9.4 / Attendance Management 1.11.0, regulile de calcul, soldurile, ledger-ul, serviciile de comenzi, infrastructura de build sau instanța companiei.

## Comparație funcțională

| Funcție Holiday Management 1.9.4 | Situație în Employee Management 0.7.2 | Employee Management 0.7.3 |
| --- | --- | --- |
| Zile disponibile | Backend prezent, dar pagina nu afișa valoarea | Afișată din `EmployeeManagement/holiday/balance` |
| Zile rezervate Pending | Backend prezent, dar pagina nu afișa valoarea | Afișate și actualizate după comandă |
| Drept anual | Backend prezent, dar pagina nu afișa valoarea | Afișat |
| Următoarea resetare | Backend prezent, dar pagina nu afișa valoarea | Afișată în formatul local |
| Sold curent | Pagina afișa doar această valoare pe un singur rând | Păstrat în cardul complet cerut |
| Profil neinițializat / eroare de încărcare | Informația se pierdea în reprezentarea simplificată | Mesaje distincte restaurate |
| Lista cererilor proprii și statusurile | Exista în pagina workspace | Păstrată; are și legătură la detaliul nativ read-only |
| Creare cerere proprie | Comanda contabilă exista; ruta de entitate putea ajunge în CRUD generic și returna `No create access` | Ambele intrări UI folosesc formularul existent și comanda contabilă |
| Editare / retragere Pending | Implementate prin comenzile existente | Păstrate |
| Solicitare anulare pentru concediu aprobat viitor | Implementată în lista privată | Păstrată |
| Document aprobat | Link privat existent în listă | Păstrat |
| Rezervare managerială și coadă de aprobare | Amestecate în bara paginii personale | Păstrate, dar delimitate într-un panou separat și ascunse prin ACL angajatului obișnuit |
| Filtrele și paginarea listei CRUD originale | Nu făceau parte din pagina workspace Employee Management | Nu au fost reconstruite; serviciul personal returnează întregul istoric propriu. Nu afectează regulile sau operațiile de concediu. |

## Rolul rutelor

- `#EmployeeManagement/holiday` este intrarea canonică a workspace-ului Employee Management.
- `#EmployeeHolidayRequest` este o intrare compatibilă către exact aceeași pagină personală; nu există o a doua implementare.
- `#EmployeeHolidayRequest/create` deschide formularul existent care apelează `POST EmployeeManagement/holiday/create`.
- `#EmployeeHolidayRequest/view/:id` rămâne detaliul nativ read-only, supus ACL/FLS.
- `POST EmployeeHolidayRequest` rămâne intenționat refuzat. Accounting-ul poate fi modificat numai prin serviciul de comenzi, nu prin CRUD generic.

## Probă în browser — laborator izolat

Utilizator testat: regular activ, NON-ADMIN, profil inițializat, WTC atribuit, sold inițial 39.

- Cerere creată prin `#EmployeeHolidayRequest/create`: 07.12.2026–11.12.2026.
- Răspuns comandă: HTTP 200, 5 zile, status Pending.
- După creare: sold curent 34, Pending rezervat 5, zile disponibile 39, drept anual 21.
- Cererea este vizibilă prin ambele rute, cu Edit și Remove pentru proprietar.
- Panoul managerial nu este randat pentru angajat.
- Detaliul nativ propriu este accesibil; butoanele CRUD generice de editare nu sunt expuse.
- 11/11 aserțiuni browser au trecut, fără erori JavaScript.

Dovezi: `tests/evidence/personal-holiday-browser.json` și `tests/evidence/personal-holiday-non-admin.png`.

## Pachet

`dist/employee-management-0.7.3.zip`

SHA-256: `12dfcc92c04cf1649c9036fe709ffc424f73af7922ebc0ce4c89c44675c98a7a`
