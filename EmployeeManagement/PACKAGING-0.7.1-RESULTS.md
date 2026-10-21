# Employee Management 0.7.1 — împachetare

ZIP: [employee-management-0.7.1.zip](../dist/employee-management-0.7.1.zip).

Comanda executată din `/opt/espo_extensions`:

```bash
bash ./build.sh --extension ./EmployeeManagement --zip 0.7.1 files scripts
```

Este folosit direct harness-ul comun `build.sh`, nemodificat. Wrapper-ul `EmployeeManagement/build.sh` deleagă aceluiași harness; packager-ul PHP separat a fost eliminat.

- Manifest și versiunea raportată de workspace: **0.7.1**.
- Rădăcină ZIP: `manifest.json`, `README.md`, `files/`, `scripts/`; fără director wrapper și fără teste/date/pending.
- 170 fișiere: CRC valid și egalitate completă cu sursele.
- Validarea JSON/PHP/module a harness-ului și `tests/contract.py`: **PASS**.
- Comparat cu ZIP-ul 0.7.0 testat: diferă numai README, manifestul și numărul versiunii din GetStatus. Logica funcțională este identică.
- Testele funcționale din raportul precedent nu au fost rerulate pentru această schimbare de versiune și împachetare. Etapa 8 rămâne neîncepută.

SHA-256: `a057fc6cb3a0236eda4e5a1d0ed4dbafccbc61bcd21169101ce28369bda548da`.

Dovadă structurată: `tests/evidence/packaging-0.7.1.json`.
