# TODO — prima versiune stabilă ManagementAngajati

Lista este ordonată pentru verificare vizuală după fiecare etapă. Un pas se bifează numai după implementare, teste automate și confirmarea vizuală în EspoCRM.

## 1. Notificări complete pentru concedii

- [x] cerere nouă: notificare pentru managerul sau managerii aprobatori;
- [x] aprobare finală: notificare pentru angajat;
- [x] respingere: notificare pentru angajat;
- [x] aprobare parțială: notificare numai pentru aprobatorii care mai trebuie să decidă;
- [x] verificare automată în limbile română și engleză;
- [x] verificare vizuală cu un manager și cu doi manageri.

## 2. Validarea acordării anuale

- [x] acordare la data configurată;
- [x] recuperarea unei execuții ratate;
- [x] protecție împotriva acordării duble;
- [x] opțiunea de acordare automată dezactivată;
- [x] trecerea dintre ani și reportarea integrală;
- [x] cerere care traversează anul;
- [x] verificare runtime a jobului programat.

## 3. Fluxul cu unul sau doi manageri

- [x] aprobare de către primul manager când este suficientă o singură aprobare;
- [x] aprobarea obligatorie a ambilor manageri când opțiunea este activă;
- [ ] blocarea autoaprobării;
- [ ] respingerea și eliberarea rezervării;
- [ ] anularea și restaurarea exactă a soldului;
- [ ] eliminarea concediului anulat din Calendar;
- [ ] ștergerea documentelor cererii anulate.

### Comportament acceptat pentru prima versiune

- [x] Regula de aprobare și lista aprobatorilor se fixează la crearea cererii.
  Dacă cererea a fost creată când erau obligatorii aprobările ambilor manageri,
  și anularea ei necesită aceiași doi manageri, chiar dacă setarea globală este
  dezactivată ulterior. Păstrăm astfel istoricul și condițiile inițiale ale
  cererii.
- [ ] După prima versiune: reevaluăm dacă anularea trebuie să folosească regula
  curentă din setări în locul regulii memorate la crearea cererii.

## 4. Auditul documentelor DOCX

- [ ] concediu într-o singură lună;
- [ ] concediu între două luni;
- [ ] concediu între doi ani;
- [ ] consumarea zilelor începând cu cele mai vechi reportate;
- [ ] drept anual încă neacordat;
- [ ] regenerare fără modificarea instantaneului istoric;
- [ ] anulare și ștergerea documentelor;
- [ ] verificarea managerilor, perioadelor și calculelor afișate.

### Clarificare pentru manager

- [ ] Confirmare luni: în documentul DOCX aferent segmentului din anul următor
  al unei cereri care traversează anii, trebuie trecut și dreptul anual
  configurat pentru anul următor dacă acesta nu a ajuns încă la data acordării?
  Dacă trebuie afișat, stabilim explicit dacă este doar informație pe document;
  acesta nu trebuie introdus automat în sold și nu poate fi consumat înainte de
  data acordării.

## 5. Matricea rolurilor și ACL

- [ ] angajat;
- [ ] manager;
- [ ] administrator;
- [ ] utilizator fără profil de concediu inițializat;
- [ ] utilizator aflat în CIC;
- [ ] utilizator dezactivat.

## 6. Stilizare unitară

- [ ] Prezență;
- [ ] Condica de prezență;
- [ ] Concediile mele;
- [ ] Administrare concedii;
- [ ] Setări condică;
- [ ] Setări concedii și profiluri angajați;
- [ ] stări goale, mesaje explicative și ecrane înguste.

## 7. Livrarea primei versiuni

- [ ] ascunderea sau eliminarea operațiilor de reset destinate testării;
- [ ] stabilirea versiunii;
- [ ] instalare pe o instanță EspoCRM curată;
- [ ] verificarea dependenței Zile Sărbătoare;
- [ ] test de upgrade și dezinstalare;
- [ ] generarea și validarea ZIP-ului final.
