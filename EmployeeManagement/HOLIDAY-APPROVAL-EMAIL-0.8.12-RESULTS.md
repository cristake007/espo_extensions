# Employee Management 0.8.12 — Holiday Approval Email Notifications

## Implementare

Notificarea folosește exclusiv infrastructura EspoCRM 10.0.1: `Espo\Entities\Email`, `Espo\Core\Mail\EmailSender`, contul outbound de sistem și `JobSchedulerFactory`. Nu există client SMTP, tabel de coadă sau worker propriu.

La crearea tranzacțională a unei cereri `Pending`, serviciul persistă câte un Email nativ în starea `Draft` și câte un Job grupat pentru fiecare destinatar eligibil. Identitatea logică este SHA-256 peste evenimentul `pending-created-v1`, ID-ul cererii și ID-ul destinatarului. API retry cu aceeași cheie de idempotency returnează receipt-ul existent și nu mai apelează outbox-ul.

Worker-ul reverifică înainte de transport că cererea este încă `Pending`, utilizatorul este regular și activ, are permisiunile efective `EmployeeManagement` și `EmployeeHolidayApproval`, read-all/FLS pentru coadă și acces la cerere, precum și o adresă primară validă. `AclManager::createUserAcl` include rolurile moștenite prin Teams. Emailul outbox nu este atribuit utilizatorului înainte de livrare.

Stările native sunt `Draft` (queued), `Sending` (claimed/attempted), `Sent` și `Failed`. Un lock pe Email și grupul Job stabil împiedică doi workeri să trimită independent aceeași notificare. Erorile programează explicit maximum două retry-uri, după 1 și 5 minute; Job retries native imediate sunt dezactivate. Logurile includ numai ID-urile tehnice, numărul încercării și clasa excepției, nu adrese, tokenuri sau conținutul cererii.

## Conținut

Subiectul este `Cerere nouă de concediu – {Nume angajat}`. Variantele HTML și plain-text includ angajatul, începutul, sfârșitul, zilele lucrătoare, statusul localizat și linkul construit din `siteUrl` către `#EmployeeHolidayRequest`. Nu includ ID-uri interne, solduri, tokenuri sau documente.

## Validare

Laborator izolat: `employee-feasibility-20261020`, EspoCRM 10.0.1, PHP 8.4.23, date și conturi sintetice. Transportul real a folosit un cont outbound nativ `InboundEmail` temporar și un SMTP sink izolat; nu s-au trimis mesaje în afara laboratorului.

- Contract/package: PASS.
- PHP lint pentru runtime, lifecycle și fixture-uri: PASS.
- Node regressions: 22/22 PASS, inclusiv 13 verificări dedicate emailului, Holiday polling și butoanele Attendance 0.8.11.
- Integrare email: 28/28 PASS.
- Doi aprobatori eligibili au primit exact câte un mesaj: unul prin rol direct, unul exclusiv prin rol de Team.
- Utilizatorul neautorizat, utilizatorul inactiv și aprobatorul cu adresă invalidă nu au primit email/job de livrare.
- Retry create, outbox rollback și Job duplicat în același grup nu au produs duplicate SMTP.
- Eșecul SMTP a produs starea `Failed`, încercările 1/2/3 și niciun attempt 4; cererea a rămas `Pending`, cu aceeași revizie, același sold și același număr de ledger entries.
- Notificarea in-app existentă pentru decizie a rămas idempotentă.
- PDF layout regression: PASS.
- ZIP integrity: PASS.

Testul istoric `tests/regression/navigation-lifecycle.php` eșuează înaintea acestei funcții deoarece apelează metoda eliminată anterior `TabList::install()`. Nu a fost modificat sau suprimat pentru 0.8.12.

## Conservarea datelor

Upgrade-ul nativ 0.8.11 → 0.8.12 și reinstalarea pachetului final 0.8.12 au avut zero diferențe pentru toate numărătorile și hash-urile urmărite:

| Entitate | Număr | SHA-256 înainte/după |
| --- | ---: | --- |
| EmployeeAttendanceRecord | 4488 | `1ec78a990920002c415a8b919156102a3a0d60c7646958a436afb5a99f6fe4ca` |
| EmployeeAttendanceWorkSchedule | 29 | `8f701a2f65cf74f69884101435ac46cf50f32c2b7c2c42ad847935641fea29c0` |
| EmployeeAttendanceMonth | 10 | `1bf0a8297e0d563b6f29111ea4ccfc517dc22f9068ba8ff5106946e3b6c55ffd` |
| EmployeeHolidayRequest | 312 | `f1f0aea9dffbc47fadbc86fbb3dc20fcaef1be61c7f235fa4591cca4d63186d6` |
| EmployeeHolidayProfile | 344 | `80afca10949353326f5fb64a68c593b962815dbf641f37df2e8f6d16de05d3b2` |
| EmployeeHolidayLedger | 895 | `91e56bd0a7e4314453af2b0c017f5268f1bd52c51711b38f75a72d9156b87f13` |
| EmployeeAbsence | 66 | `df903aacec7aba35439d8d492b9d1d30ad08c5f05d26ae3e4f6dab0fd6d710c7` |
| Documente aprobare | 108 | `96444713ff2246fa8e79ba8500eeca74e16982b9920e3fb82c5f343e2638e445` |

Configurațiile Attendance/Holiday, Calendar și `tabList` au rămas identice. Nu există modificări de schemă sau lifecycle și nu se scanează cererile Pending existente.

## Limitări

SMTP nu poate oferi exact-once matematic: un proces poate cădea după acceptarea mesajului de server și înainte de salvarea `Sent`. Identitatea/Message-ID stabil, claim-ul persistent și gruparea Job oferă protecția practică maximă fără infrastructură suplimentară, dar această fereastră nu poate fi eliminată. Livrarea depinde de contul outbound de sistem și de daemonul EspoCRM configurate și active.

Pachet final: `dist/employee-management-0.8.12.zip`, SHA-256 `cb1be2057bbf044f36d24272cdc8a91cf267cf7e08e5fd57c0f85f775eb042e6`.
