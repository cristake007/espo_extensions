# Employee Management 0.7.5 — eliminarea sidenav-ului automat

Installer-ul nu mai adaugă scope-urile Employee Management în `tabList`.

La upgrade sunt eliminate intrările introduse de versiunile anterioare:

- `EmployeeManagement`
- `EmployeeAttendance`
- `EmployeeAttendanceOverview`
- `EmployeeAdministration`

Curățarea funcționează și pentru intrări aflate în grupuri. Nu sunt modificate celelalte taburi ale instalației. Scope-urile păstrează `tab=true`, astfel încât un administrator le poate adăuga ulterior manual.

În laboratorul izolat, toate cele patru intrări existau înainte de upgrade și niciuna nu a rămas după instalarea 0.7.5. În browser:

- cele patru contoare sidenav sunt `0`;
- flyout-ul nu conține intrările redundante;
- `#EmployeeManagement` păstrează linkurile către Attendance, Daily overview și Holiday;
- nu au existat erori JavaScript.

Dovezi:

- `tests/evidence/navigation-0.7.5.json`
- `tests/evidence/navigation-0.7.5-no-automatic-sidenav.png`

Pachet: `dist/employee-management-0.7.5.zip`

SHA-256: `ef6418b3c089f6f4b4cfcca0b3d7a6486f3b1db14b7a60f18df1108d39cac0cb`
