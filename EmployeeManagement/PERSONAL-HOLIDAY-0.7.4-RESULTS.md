# Employee Management 0.7.4 — create access și flyout

## Corecții

- `POST EmployeeHolidayRequest` nu mai intră în create-ul CRUD protejat. Controllerul nativ îl adaptează la `HolidayRequestService::create`, cu utilizatorul autentificat drept beneficiar.
- `#EmployeeHolidayRequest/create` continuă să folosească formularul personal și endpoint-ul de comandă `EmployeeManagement/holiday/create`.
- Generic update/delete rămân refuzate.
- Cele două intrări redundante `Employee Management` și `Employee Management — Administration` au fost eliminate din flyout-ul utilizatorului prin metadata `clientNavbar`.
- Taburile native autorizate din sidebar au fost păstrate.

## Verificare izolată

Cu un utilizator regular NON-ADMIN, profil inițializat și WTC atribuit:

1. Apelul de compatibilitate `POST EmployeeHolidayRequest` a creat o cerere de 5 zile, HTTP 200, Pending.
2. În browser, `#EmployeeHolidayRequest/create` a creat o altă cerere de 5 zile, HTTP 200, Pending, fără header/reason `No create access` și fără erori JavaScript.
3. Flyout-ul unui manager regular nu mai conține niciuna dintre cele două intrări redundante; cele două taburi permise rămân în sidebar.

Dovezi:

- `tests/evidence/personal-holiday-0.7.4.json`
- `tests/evidence/personal-holiday-0.7.4-non-admin-create.png`
- `tests/evidence/personal-holiday-0.7.4-manager-flyout.png`

## Pachet

`dist/employee-management-0.7.4.zip`

SHA-256: `5d756316d6dbfd575fdc4010dfb9bc4c1a75dde46a069b8ea95b18b835b1354d`
