# Employee Management 0.8.13 — Holiday DOCX Corrections & Verification

Data: 2026-10-21
Țintă: EspoCRM 10.0.1 / PHP 8.4.23 / MariaDB
Status: implementare și pachet PASS; fără commit/push și fără acces la producție.

## Cauze identificate

1. `HolidayApprovalDocumentService::capture()` captura un singur drept anual și un singur sold inițial, apoi scădea secvențial zilele fiecărui segment lunar. Nu proiecta resetarea/acordarea anuală programată dintre decembrie și ianuarie.
2. Coloana „Total” folosea `max(annualEntitlement, balanceBefore)`, ceea ce amesteca dreptul anual cu soldul/reportul. Documentul nu putea distinge 21 zile drept 2027 de 3 zile reportate.
3. Reconstrucția soldului anterior adăuga toate cererile Pending/ApprovalProcessing ale angajatului. Aceasta putea elimina artificial efectul altor rezervări. Acum se adaugă înapoi numai zilele cererii documentate.
4. DOCX-urile publicate aveau digest tehnic în manifestul operației de aprobare, dar nu aveau identitate verificabilă, evidență per versiune sau interfață ACL pentru verificare.
5. Nu exista o cale sigură și îngustă pentru reemiterea unui singur segment istoric fără reluarea aprobării.
6. În prima implementare 0.8.13, linia de verificare era introdusă în rândul 10, celula 1 a tabelului principal. Acea celulă conține `<w:vMerge/>` și este continuarea îmbinării verticale a blocului de aprobare; Word putea indexa textul, dar nu îl desena și nu îl includea vizibil în Print Preview.

Acestea sunt probleme de proiecție și trasabilitate a documentului. Contabilitatea Holiday nu a fost modificată.

## Implementare

### Proiecția financiară

- Segmentarea lunară existentă este păstrată.
- Snapshot-ul de aprobare v2 păstrează dreptul anual, soldul anterior cererii, următoarea dată de reset și starea opt-in a acordării automate.
- `HolidayDocumentProjection` aplică doar proiecții de prezentare. Nu salvează profil, sold sau ledger.
- O acordare este proiectată numai dacă era activată și data capturată o face aplicabilă. `nextResetDate` avansat previne dubla numărare a unui grant deja aplicat.
- Dacă o dată de grant cade în interiorul unui segment lunar și rezultatul nu poate fi stabilit neambiguu, generarea eșuează explicit.
- Pentru documentele legacy, reemiterea folosește ledger-ul rezervării cererii pentru dreptul anual și data resetării capturate la rezervare; nu cere un grant viitor deja executat. Regula automată configurată trebuie să confirme aceeași scadență, altfel operația se oprește pentru confirmare istorică documentată și auditată.

Rezultatul verificat pentru exemplul obligatoriu:

| Segment | Drept anual | Deja folosit / report | Disponibil | Cerut | Rest |
|---|---:|---:|---:|---:|---:|
| 2026-12 | 21 | 13 deja folosite | 8 | 5 | 3 |
| 2027-01 | 21 | 3 reportate | 24 | 3 | 21 |

Structura celor cinci coloane este păstrată cu etichetele exacte: „Total zile”, „Deja primite”, „Au rămas”, „Zile cerute”, „Rest zile”. Pentru ianuarie, prima celulă conține două rânduri WordprocessingML (`21 - 2027`, apoi `3 - report 2026`), iar valorile celorlalte coloane sunt `0 / 24 / 3 / 21`.

### Identitate și integritate

- Codul este `strtoupper(bin2hex(random_bytes(16)))`: 128 biți, 32 caractere hexazecimale.
- Fiecare segment lunar primește un cod diferit în snapshot-ul imutabil. Retry-ul aceleiași operații rerandează cu același cod.
- DOCX-ul conține numai linia `Cod de verificare: <cod>` într-un paragraf normal `w:p`, frate imediat următor al primului `w:tbl` din `w:body`. Paragraful este în afara oricărui `w:tc`, are `<w:jc w:val="right"/>`, text negru de 8 pt într-un singur `w:r/w:t` și spațiere minimă.
- Rendererul reutilizează paragraful normal gol care exista deja după tabel; nu adaugă un paragraf suplimentar, nu schimbă marginile/secțiunea și nu atinge niciun `w:vMerge`, rând, celulă sau lățime a tabelului.
- SHA-256 se calculează după randarea finală, inclusiv codul, și se leagă de atașament și de versiunea documentului.
- Identitățile sunt protejate de indecși unici pentru cheia emiterii, hash-ul codului și request/lună/versiune.
- Snapshot-urile v1 aflate deja în procesare rămân compatibile și produc documente legacy; nu sunt rescrise și nu sunt prezentate ca verificate criptografic.

### Verificare managerială

- Rută UI: `#EmployeeHolidayDocumentVerification`.
- API: `POST EmployeeManagement/holiday-document/verify`.
- Accesul cere utilizator regular activ, `EmployeeManagement`, permisiune efectivă `EmployeeHolidayApproval`, read-all și acces ACL/FLS la cererea și atașamentul asociat.
- `HolidayApproverProvider` păstrează rolurile native, inclusiv rolurile moștenite prin Teams.
- Upload-ul DOCX este opțional, limitat la 10 MB, validat și comparat numai în memorie; nu este salvat.
- Sunt înregistrate audituri fără cod în clar (doar fingerprint scurt), iar limita este 20 verificări / 5 minute / utilizator.
- Starea curentă a cererii determină `Valid`, `Revoked`, `CancellationPending` sau `NotEffective`; versiunea documentului poate determina `Superseded`.
- Un fișier cu bytes modificați întoarce întotdeauna `Modified`, chiar dacă referința este validă.

### Reemitere CLI

Script instalat:

`custom/Espo/Modules/EmployeeManagement/Command/reissue-holiday-document.php`

Dry-run:

```bash
docker compose exec -u www-data espocrm php custom/Espo/Modules/EmployeeManagement/Command/reissue-holiday-document.php \
  --request-id=REQUEST_ID --month=2027-01 --dry-run
```

Execute:

```bash
docker compose exec -u www-data espocrm php custom/Espo/Modules/EmployeeManagement/Command/reissue-holiday-document.php \
  --request-id=REQUEST_ID --month=2027-01 --execute \
  --reason="Corectare sold reportat 2026-2027" \
  --confirm-attachment-id=ATTACHMENT_ID_DIN_DRY_RUN
```

- Modul implicit este dry-run.
- Execute cere motiv și confirmarea exactă a atașamentului curent.
- Operatorul tehnic este capturat din utilizatorul OS, UID efectiv, host, director și binarul PHP; un ID de manager din argumente nu este acceptat.
- Starea `Prepared` păstrează codul și digestul peste retry de storage/publicare.
- Publicarea reverifică request-ul, revizia contabilă, ținta și ambele digesturi sub lock.
- Fișierul original trece în `supersededApprovalDocuments`; bytes-urile sale nu sunt modificate. Noua versiune trece în `approvalDocuments`.
- Scriptul nu apelează `HolidayApprovalService::initiate()` și nu modifică status, decizie, sold, ledger, Attendance sau WTC.

## Fișiere runtime modificate/adăugate

- `manifest.json`, `README.md`, `MIGRATION-INVENTORY.json` — versiune și documentație.
- `HolidayRequestService.php` — reconstruiește soldul documentului adăugând numai rezervarea cererii curente.
- `HolidayApprovalDocumentService.php` — snapshot v2, proiecție cross-year, cod stabil, pregătire reemitere.
- `HolidayDocumentProjection.php` — calcul pur, fără scrieri contabile.
- `HolidayDocumentGenerator.php` — antetele originale, două rânduri în „Total zile” prin `w:br` și codul într-un paragraf normal vizibil imediat sub tabel.
- `HolidayApprovalService.php` — înregistrarea identității și digestului în tranzacția finală de publicare.
- `HolidayDocumentRegistry.php` — evidență protejată per versiune.
- `HolidayDocumentVerificationService.php` și `Api/VerifyHolidayDocument.php` — verificare, ACL/FLS, hash, audit și rate limit.
- `HolidayDocumentReissueService.php` și `Command/reissue-holiday-document.php` — dry-run și reemitere tranzacțională.
- `scripts/reissue-holiday-document.php` — wrapper CLI din pachet.
- metadatele `EmployeeHolidayDocument` și `EmployeeHolidayDocumentVerificationLog` — tabele interne append-only, fără backfill.
- `EmployeeHolidayRequest` entity/layout/i18n — legătura read-only pentru documentele înlocuite.
- `routes.json`, `clientRoutes.json`, `workspace.js`, `holiday-request/index.js`, `holiday-document/verification.js`, `holiday.css` — pagina managerială.
- testele și dovezile `holiday-docx-*` și contractul pachetului.

Template-ul binar `holiday-request.docx` nu a fost modificat; ajustările minimale sunt aplicate de renderer peste structura existentă.

## Teste și rezultate

- Validare JSON/PHP pachet: PASS.
- PHP lint pentru toate testele: PASS.
- JavaScript syntax check pentru toate fișierele client: PASS.
- `holiday-docx-verification.php`: PASS, 23 aserțiuni, inclusiv antetele originale, secvența WordprocessingML `text → w:br → text`, valorile ambelor DOCX-uri, retry cu bytes identici, poziția imediat după tabel, alinierea, lipsa proprietăților hidden și respingerea explicită a codului dintr-o continuare `<w:vMerge/>`.
- `holiday-document-verification.test.mjs`: PASS, 18/18.
- Email 0.8.12 regression: PASS, 13/13.
- Attendance manager buttons: PASS, 7/7.
- Holiday polling: PASS, 12/12.
- Alte regresii JS: PASS, 2/2.
- Attendance PDF layout: PASS.
- Contract complet Employee Management: PASS.
- Instalare pachet 0.8.13 peste 0.8.12 în laborator izolat: PASS.
- Runtime verificare/reemitere în EspoCRM 10.0.1: PASS, 16/16.
- API real: manager autorizat 200; angajat neautorizat 403; rate limit 12 răspunsuri 200 urmate de 409 la limita rămasă.
- Captură/randare v2 reală: două luni, coduri distincte, retry cu bytes identici, valori decembrie și ianuarie corecte.
- Duplicate execute: respins sigur deoarece atașamentul confirmat nu mai era versiunea curentă.

### Corecție de prezentare verificată după inspecția DOCX real

- Defectul a fost reprodus înaintea corecției: în documentul ianuarie, codul era descendent al unui `w:tc` din rândul 10, celula 1, iar `w:tcPr` conținea `<w:vMerge/>`.
- După corecție, linia este în paragraful normal imediat următor tabelului în `w:body`, nu este descendent al `w:tc`, este aliniată `right`, neagră, 8 pt, fără `w:vanish`, `w:webHidden`, `w:br`, `w:cr` sau `w:tab`.
- Canonicalizarea XML confirmă că tabelul, proprietățile sale și toate merge-urile sunt identice cu randarea fără cod. Proprietățile paginii sunt identice și nu există page-break explicit; confirmarea efectivă a unei singure pagini rămâne însă o verificare vizuală în Word/LibreOffice.

- Upgrade-ul 0.8.12 → 0.8.13 a fost repetat în laboratorul izolat cu 313 cereri, 345 profiluri, 896 înregistrări ledger și 4.489 pontaje: count-urile și hash-urile au rămas identice.
- Cererea sintetică 24.12.2026–09.01.2027 avea numai ledger-ul rezervării `holidayBooked` (`entitlementBefore=21`, `resetDateBefore=2027-01-01`); nu exista un grant 2027 executat anticipat.
- Dry-run-ul CLI a raportat 21 drept anual, 3 report, 24 disponibile, 3 cerute, 21 rămase și `Accounting changes: NONE`.
- Reemiterea exclusiv pentru `2027-01` a păstrat statusul `Approved`, revizia contabilă 2, soldul 0, singura înregistrare ledger și bytes-urile atașamentului original. Originalul a fost marcat `Superseded`, iar noua versiune a fost publicată cu un cod nou și digest legat de bytes.
- DOCX-ul exportat a păstrat cele cinci antete originale. Prima celulă financiară are secvența efectivă `21 - 2027`, `w:br`, `3 - report 2026`; restul rândului este `0 / 24 / 3 / 21`.
- `dist/validation-docx/Cerere-concediu-decembrie-2026.docx`: regenerat cu codul `BDAC23DAD0433F2BB57BC73E39850DD0`; SHA-256 `b942d40cde24c5cecf8233218ced89d3f781998c9abc684473575810db2c7d53`; valori `21 - 2026 / 13 / 8 / 5 / 3`.
- `dist/validation-docx/Cerere-concediu-ianuarie-2027.docx`: regenerat cu codul `FCB38D2E7D6009C7B023D4814781B1E5`; SHA-256 `252b8f1b2ff437a2f6bc1ac2a8842a0fe76b41bf3a0d5aab7b91f5160bca97a9`; prima celulă conține `21 - 2027`, `w:br`, `3 - report 2026`, urmată de `0 / 24 / 3 / 21`.
- Codurile celor două fișiere sunt distincte. Ambele arhive DOCX trec `unzip -t`, au codul exact o singură dată în paragraful exterior și păstrează tabelul/merge-urile template-ului.
- Mediul nu oferă LibreOffice, soffice, lowriter, pandoc sau AbiWord. Nu declarăm testul vizual ori numărul de pagini ca PASS; fișierele reale sunt exportate pentru confirmare manuală în Microsoft Word și Print Preview.

### Conservarea datelor la upgrade 0.8.12 → 0.8.13

| Entitate | Count înainte/după | SHA-256 înainte/după |
|---|---:|---|
| EmployeeAttendanceRecord | 1 / 1 | `c4adf22f…67eaa` / identic |
| EmployeeHolidayRequest | 1 / 1 | `0c47059e…3a51` / identic |
| EmployeeHolidayProfile | 1 / 1 | `c669050b…e4e8` / identic |
| EmployeeHolidayLedger | 1 / 1 | `424e9ac7…fc9e` / identic |
| EmployeeAbsence | 1 / 1 | `c5bf8aa5…1883` / identic |
| Atașamente Holiday | 1 / 1 | `75b23cc8…432` / identic |

Noile entități interne au avut count 0 după upgrade: nu s-a făcut backfill și niciun document istoric nu a fost rescris. Dovezi complete:

- `tests/evidence/holiday-docx-0.8.13-upgrade.json`
- `tests/evidence/holiday-docx-0.8.13-runtime.json`

## Pachet

- `dist/employee-management-0.8.13.zip`
- SHA-256: `495991746b6e272423b7f96ef559b146d6295ee7b2a2d873807aaddde6297a9a`
- Arhiva trece `unzip -t`, conține rendererul final și nu conține `dist/validation-docx/` sau fișierele DOCX de validare.

## Limitări cunoscute

- Documentele legacy fără cod rămân accesibile, dar nu pot fi declarate criptografic autentice până la o reemitere explicită.
- Reemiterea unui snapshot v1 cross-year fără snapshotul rezervării sau fără confirmarea regulii de resetare aplicabile este refuzată intenționat și indică necesitatea unei confirmări istorice documentate și auditate.
- Un document tipărit permite numai comparație vizuală cu CRM; integritatea exactă necesită DOCX-ul digital.
- În mediul disponibil nu a existat un renderer DOCX grafic. Structura a fost verificată pe DOCX real prin `word/document.xml`, inclusiv paragraful exterior imediat următor tabelului, alinierea, textele, valorile și absența instrucțiunilor suplimentare; vizibilitatea și pagina unică necesită confirmare manuală în Word/Print Preview.
- Pagina a fost validată prin cod, API real 200/403 și regresii JS, dar nu printr-o sesiune browser automatizată în acest laborator efemer.
