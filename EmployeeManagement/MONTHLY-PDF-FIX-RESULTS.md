# Employee Management 0.7.2 — butonul PDF al raportului lunar

## Cauza confirmată

`AttendanceAccessChecker::assertExport()` cerea permisiunea globală EspoCRM Export=yes. `AttendanceOverviewService` transforma refuzul în `exportAllowed=false`, iar frontend-ul folosea aceeași valoare pentru PDF și XLSX. Astfel, un manager NON-ADMIN cu toate scope-urile operaționale și Allow incomplete exports activ vedea PDF-ul dezactivat. API-ul PDF refuza și el operația cu 403.

Problema a fost reprodusă în browser înainte de modificare, atât cu Export neconfigurat în rol, cât și cu Export=no explicit. În ambele cazuri permisiunea efectivă era `no`, managerul avea acces la overview, iar raportul incomplet era eligibil conform politicii. Dovezi: `tests/evidence/monthly-pdf-before.json` și capturile `monthly-pdf-before-*`.

Testul anterior cu Export=yes nu reproducea cazul reclamat. Recomandarea anterioară de a acorda Export global nu reprezenta corectarea acelui caz.

## Corecția

PDF-ul lunar folosește acum o autorizare operațională explicită, `assertPdfDownload()`:

1. Utilizator regular activ, cu EmployeeManagement.
2. EmployeeAttendanceOverview și read all/FLS pe datele raportului, prin checker-ul existent.
3. EmployeeAttendanceManagement **sau**, pentru un viewer fără management, permisiunea nativă Export=yes.
4. Dezactivarea explicită globală `exportDisabled` rămâne respectată.
5. Politica de completitudine este verificată separat, în continuare pe server.

Aceeași autorizare produce `pdfDownloadAllowed` pentru UI și se verifică la POST-ul PDF. Butonul folosește dreptul PDF la afișare, la apăsare și la revenirea după descărcare. XLSX păstrează verificarea sa nativă Export=yes. Nu se modifică Roles, permisiunile generice de export sau privilegiile administratorului. Un manager cu Export=no poate descărca acest raport operațional fără să primească Export global.

Se reutilizează generatorul PDF provenit din Attendance Management 1.11.0, inclusiv corecția de memorie deja livrată. Generatorul și layout-ul nu au fost modificate în această corecție. Nu s-au modificat alte fluxuri, designul general sau etapa 8.

## Permisiunile de aplicat rolului

Pentru managerul care descarcă raportul lunar PDF:

| Scope / entitate | Permisiune |
| --- | --- |
| EmployeeManagement | Activat / Yes |
| EmployeeAttendanceOverview | Activat / Yes |
| EmployeeAttendanceManagement | Activat / Yes |
| EmployeeAttendanceRecord | Read = All |
| EmployeeAttendanceWorkSchedule | Read = All |

Citirea câmpurilor necesare nu trebuie refuzată prin FLS: `assignedUser`, `date`, `status`, `source`, `markedAt` pe pontaj și `remote`, `cic`, `cicStartDate`, `cicEndDate` pe metadatele operaționale. Câmpurile fără restricții explicite de citire își păstrează comportamentul nativ.

Pentru a configura Allow incomplete exports: EmployeeAdministration + EmployeeAttendanceSettings. Pentru un raport incomplet, opțiunea trebuie activată și salvată.

**Dacă utilizatorul are deja aceste drepturi de management și read all, nu se adaugă nicio permisiune pentru PDF: Export global nu este necesar.** Export=yes rămâne necesar pentru XLSX sau pentru un viewer care nu are AttendanceManagement. Nu este necesar cont admin și nu se acordă User edit, Roles, Settings global sau administrare WTC.

## Probe funcționale

Docker izolat `employee-feasibility-20261020`, EspoCRM 10.0.1 / PHP 8.4.23. Date și conturi sintetice. ZIP 0.7.2 instalat prin installer-ul nativ înaintea probelor; toate cele 166 fișiere runtime din ZIP coincid cu instalarea testată.

- **25 verificări în browser, zero eșecuri:** două conturi regular NON-ADMIN, ambele cu Export efectiv `no`; buton PDF activ, click, HTTP 200, eveniment de download și fișier PDF salvat efectiv. Export rămâne `no` și după descărcare. Butonul revine activ. Nicio eroare JavaScript.
- Debifarea și salvarea Allow incomplete exports dezactivează PDF-ul și produce 409 la apel direct. Rebifarea, salvarea și reîncărcarea reactivează descărcarea.
- **19 verificări API/securitate, zero eșecuri:** angajat, read own, FLS refuzat, lipsă overview, viewer fără Export și administrator tehnic — refuzate. Viewer-ul cu Export=yes continuă să funcționeze. Dezactivarea globală explicită este respectată. XLSX fără Export rămâne refuzat.
- **10 verificări independente ale fișierelor, zero eșecuri:** ambele PDF-uri descărcate sunt parsate integral, au două pagini, luna Octombrie 2026, orele native 11:00–17:00, angajații așteptați și numerotarea corectă.

Dovezi: `tests/evidence/monthly-pdf-browser.json`, `monthly-pdf-api.json`, `monthly-pdf-files.json`, capturile `monthly-pdf-after-*` și fișierele `monthly-pdf-managerUnset.pdf` / `monthly-pdf-managerDenied.pdf`. Regresiile relevante și verificarea finală a pachetului sunt centralizate în `tests/evidence/monthly-pdf-final.json`. Regresii existente rerulate pe 0.7.2: **74 API + 20 extra + 26 browser = 120 verificări trecute**, inclusiv pontare, WTC, CIC, înghețare/corecții, XLSX și administrare operațională. Contractul ZIP/surse/proveniență și regresia layout-ului PDF au trecut. Totalul structurat al acestei corecții este **174 verificări, zero eșecuri finale**, plus reproducerea înainte de corecție. Rezultatele istorice de 570 verificări nu sunt prezentate ca rerulate pe 0.7.2.

## Livrare

Construit exclusiv cu harness-ul comun, nemodificat:

```bash
bash ./build.sh --extension ./EmployeeManagement --zip 0.7.2 files scripts
```

ZIP: `dist/employee-management-0.7.2.zip` din rădăcina repository-ului. Manifestul și versiunea raportată de workspace sunt 0.7.2. Verificări CRC, structură și egalitate cu sursele; checksum în `dist/employee-management-0.7.2.zip.sha256`. SHA-256: `95d1de7097e985604ca23870fc109df6750b16862971c9da674c2e2c5014f1b8`.

După instalare, reîncărcați complet browserul pentru noul modul frontend. Instalația companiei și extensiile originale nu au fost modificate. Etapa 8 rămâne neîncepută.
