# Employee Management — reguli vizuale pentru frontend

Acest document fixează limbajul vizual folosit în paginile Employee Management deja refăcute. Se aplică următoarelor lucrări de frontend, fără a modifica regulile de business, endpoint-urile, ACL-ul sau componentele native EspoCRM.

Pagini de referință:

- `#EmployeeHolidayRequest`
- `#EmployeeAttendance`
- `#EmployeeAttendanceOverview`
- `#EmployeeAdministration/attendanceMetadata`

## 1. Principii

- Informația și operațiunile existente se păstrează integral. Structura vizuală poate fi reorganizată numai pentru claritate.
- Se folosesc controalele native EspoCRM ori de câte ori există: date, selecturi, butoane, modale și mesaje.
- Nu se creează navigație paralelă și nu se duplică pagini sau acțiuni existente.
- Interfața trebuie să fie ușor de înțeles fără explicații externe: titlu clar, descriere scurtă, stare curentă și acțiune evidentă.
- Informațiile administrative și cele personale rămân separate.
- Designul este compact. Evităm cardurile supradimensionate, spațiile goale inutile și textele centrate fără motiv.

## 2. Izolarea stilurilor

Fiecare pagină primește o clasă rădăcină proprie. Toate stilurile noi trebuie limitate la acea clasă, pentru a nu afecta EspoCRM sau celelalte pagini:

- `.employee-holiday-page`
- `.employee-attendance-page`
- `.employee-attendance-overview-page`
- `.employee-attendance-metadata-page`

Nu se suprascriu global clase precum `.panel`, `.btn`, `.table`, `.field` sau `.form-control`. Acestea pot fi ajustate numai sub clasa rădăcină a paginii.

## 3. Tokenii temei

Se folosesc tokenii TUVTK cu fallback pe variabilele native EspoCRM:

```css
--page-primary: var(--tuvtk-primary, var(--brand-primary));
--page-secondary: var(--tuvtk-secondary, var(--brand-danger));
--page-surface: var(--tuvtk-surface, var(--panel-bg));
--page-border: var(--tuvtk-border, var(--panel-default-border));
--page-border-soft: var(--tuvtk-border-soft, var(--panel-default-border));
--page-hover: var(--tuvtk-hover, var(--state-primary-bg));
--page-text: var(--tuvtk-text, var(--text-color));
--page-muted: var(--tuvtk-muted, var(--text-muted-color));
```

Prefixul variabilelor poate fi specific paginii. Nu se introduc culori fixe dacă există un token semantic sau o variabilă nativă potrivită.

## 4. Spațiul de lucru

Conținutul principal folosește aceeași lățime pe toate paginile:

```css
margin: 0 auto;
max-width: 1180px;
padding: 16px 0 32px;
```

Pe ecrane sub `767px`, spațiul de sus devine `12px`. Pagina nu trebuie să fie lipită de topbar și nu trebuie să fie mai lată decât celelalte pagini Employee Management.

## 5. Antetul principal

Antetul paginii este un panou orizontal, aliniat la stânga:

- suprafață din tokenul temei;
- border normal și accent de `4px` în stânga;
- `border-radius` și `box-shadow` native;
- padding desktop `22px 24px`;
- icon rotund de `52px`;
- eyebrow de `11px`, uppercase, cu tracking discret;
- titlu de `24px`, fără margini inutile;
- descriere scurtă, maximum aproximativ `760–820px`;
- spațiu de `20px` până la următorul panou.

Heading-urile principale nu se centrează. Dacă antetul conține un selector sau o stare, acestea se așază în dreapta pe desktop și sub text pe mobil.

## 6. Panouri și heading-uri de secțiune

Secțiunile funcționale sunt panouri cu:

- suprafață, border, radius și shadow identice cu antetul;
- `overflow: hidden` când secțiunea conține tabel sau listă;
- distanță de `20px` între panouri;
- heading intern cu fundalul nativ `--default-heading-bg-color`;
- padding de regulă `15px 18px`;
- icon rotund de `38px`;
- titlu aliniat la stânga și descriere de `12px` dedesubt.

Un contor precum „11 angajați · 22 zile” este text normal, lizibil, în dreapta heading-ului. Nu se folosește un pill foarte mic. Pe mobil, contorul coboară sub titlu și rămâne aliniat cu textul secțiunii.

## 7. Liste, rânduri și carduri

- Rândurile trebuie să rămână compacte și scanabile pentru aproximativ 15 angajați.
- Identitatea angajatului se află în stânga, cu icon și nume evident.
- Controalele conexe se grupează vizual, dar nu se ascund informații existente.
- Acțiunea principală a rândului se află în dreapta pe desktop și ocupă lățimea completă pe mobil.
- Dacă fiecare rând se salvează separat, modificările nesalvate trebuie semnalate pe acel rând.
- Hover-ul poate evidenția discret border-ul sau fundalul, folosind tokenii temei.
- Nu se transformă fiecare valoare mică într-un pill.

## 8. Tabele și culori semantice

Legenda trebuie să corespundă vizual datelor reale din tabel. Dacă un status apare în legendă cu o culoare, celula aferentă trebuie să folosească aceeași familie semantică.

Pentru Attendance:

- serviciu: `success`;
- deplasare: `warning`;
- concediu medical: `danger`;
- concediu și CIC: `info`;
- înregistrare lipsă: `danger`, distinctă și ușor de observat;
- viitor/read-only: fundal neutru și text muted.

Tabelele late folosesc scroll intern. Antetul și coloana de identificare pot rămâne sticky. Selecturile native trebuie să rămână lizibile peste fundalul semantic al celulei.

## 9. Stări și feedback

Orice pagină care încarcă date trebuie să trateze explicit:

- loading;
- date disponibile;
- stare goală;
- eroare prin mecanismul nativ `Espo.Ui.error`;
- succes prin `Espo.Ui.success`;
- modificări nesalvate, unde este cazul;
- read-only/locked, unde este cazul.

Starea goală conține un icon, un titlu scurt și o explicație. Nu se lasă un panou alb aparent defect.

Butoanele se dezactivează în timpul salvării. Acțiunile care nu sunt permise de capabilitățile returnate de backend nu se afișează.

## 10. Responsive

Breakpoints folosite în mod obișnuit:

- `1100px`: barele complexe și comenzile se pot reașeza;
- `991px`: rândurile administrative trec la două niveluri;
- `767px`: layout pe o singură coloană, butoane full-width și padding redus.

Pe mobil:

- iconul și textul antetului rămân aliniate sus;
- heading-urile de secțiune pot deveni verticale;
- controalele nu trebuie să iasă din container;
- datele și selecturile native folosesc lățimea disponibilă;
- matricile și tabelele păstrează scroll, nu sunt comprimate până devin ilizibile.

## 11. Text și traduceri

- Orice text vizibil este definit în fișierele i18n `ro_RO` și `en_US`.
- Titlurile sunt scurte; descrierile spun ce poate face utilizatorul.
- Terminologia trebuie să fie consecventă: Attendance, Remote, CIC, Holiday și denumirile statusurilor nu se reinventează de la o pagină la alta.
- Textele românești folosesc diacritice.

## 12. Limite tehnice

O modificare vizuală nu trebuie să schimbe:

- endpoint-urile și payload-urile;
- EntityDefs, ACL, Roles sau ownership, dacă sarcina este exclusiv frontend;
- calculele, soldurile, ledger-ul, resetările sau aprobările;
- condițiile de editare și capabilitățile furnizate de backend;
- componentele native EspoCRM care funcționează deja.

Înainte de livrare se verifică cel puțin:

1. sintaxa JavaScript și JSON;
2. izolarea CSS și echilibrul acoladelor;
3. afișarea desktop și mobil;
4. loading, empty, success, error și read-only;
5. operațiunea reală principală a paginii;
6. contractul extensiei și integritatea ZIP-ului, dacă se livrează o versiune nouă.

## 13. Direcția pentru paginile următoare

La continuarea lucrului, se pornește de la informația și operațiunile existente, apoi se aplică această ordine:

1. spațiu de lucru de 1180px;
2. antet principal coerent;
3. gruparea informației în maximum câteva panouri clare;
4. acțiuni vizibile lângă contextul lor;
5. culori semantice reale, nu legendă decorativă;
6. stări complete și responsive;
7. verificarea că nicio regulă de business nu a fost mutată în frontend.

Acest document este reperul pentru frontend-ul Employee Management până la stabilirea unui design system separat.
