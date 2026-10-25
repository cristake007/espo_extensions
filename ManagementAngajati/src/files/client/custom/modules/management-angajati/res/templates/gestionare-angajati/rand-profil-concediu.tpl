<header class="profil-concediu-antet">
    <div class="profil-concediu-identitate">
        <span class="profil-concediu-avatar" aria-hidden="true"><span class="fas fa-user"></span></span>
        <span><strong>{{numeAngajat}}</strong><small><span class="far fa-calendar-alt" aria-hidden="true"></span> {{dataAngajareEticheta}}: {{dataAngajareAfisata}}</small></span>
    </div>
    <span class="profil-concediu-stare{{#if initializat}} este-initializat{{else}} necesita-initializare{{/if}}">{{#if initializat}}<span class="fas fa-check-circle" aria-hidden="true"></span> {{profilInitializat}}{{else}}<span class="fas fa-exclamation-circle" aria-hidden="true"></span> {{profilNeinitializat}}{{/if}}</span>
</header>

<div class="profil-concediu-explicatie"><span class="fas fa-info-circle" aria-hidden="true"></span><span>{{#if initializat}}{{explicatieProfilInitializat}}{{else}}{{explicatieProfilNeinitializat}}{{/if}}</span></div>

<div class="profil-concediu-rezumat">
    <div class="profil-concediu-camp">
        <label>{{zileAnualeEticheta}}</label>
        <div class="field" data-name="zileAnuale">{{{zileAnuale}}}</div>
    </div>
    <div class="profil-concediu-camp">
        <label>{{disponibileInitialEticheta}}</label>
        {{#unless initializat}}<div class="field" data-name="zileDisponibileAnCurent">{{{zileDisponibileAnCurent}}}</div>{{else}}<strong class="profil-concediu-valoare"><span>{{disponibileAnCurent}}</span><small>{{zile}}</small></strong>{{/unless}}
    </div>
    <div class="profil-concediu-camp">
        <label>{{reportateInitialEticheta}}</label>
        {{#unless initializat}}<div class="field" data-name="zileReportateAnAnterior">{{{zileReportateAnAnterior}}}</div>{{else}}<strong class="profil-concediu-valoare"><span>{{disponibileReportate}}</span><small>{{zile}}</small></strong>{{/unless}}
    </div>
    <div class="profil-concediu-camp">
        <label>{{dataAcordareEticheta}}</label>
        {{#if initializat}}<strong class="profil-concediu-valoare">{{dataAcordareAfisata}}</strong>{{else}}<div class="field" data-name="dataUrmatoareiAcordari">{{{dataUrmatoareiAcordari}}}</div>{{/if}}
    </div>
    <div class="profil-concediu-actiune-principala">
        <button class="btn btn-primary" data-action="salveaza">{{#if initializat}}<span class="fas fa-save" aria-hidden="true"></span> {{salveazaDreptAnual}}{{else}}<span class="fas fa-play" aria-hidden="true"></span> {{initializare}}{{/if}}</button>
    </div>
</div>

{{#if initializat}}
<details class="profil-concediu-corectii">
    <summary><span class="profil-concediu-corectii-icon"><span class="fas fa-tools" aria-hidden="true"></span></span><span><strong>{{corectiiAdministrative}}</strong><small>{{explicatieCorectiiAdministrative}}</small></span><span class="fas fa-chevron-down profil-concediu-corectii-sageata" aria-hidden="true"></span></summary>
    <div class="profil-concediu-corectii-continut">
        <section class="profil-concediu-corectie">
            <div class="profil-concediu-corectie-antet"><span class="fas fa-calendar-alt" aria-hidden="true"></span><span><strong>{{corecteazaProgramAcordareEticheta}}</strong><small>{{explicatieCorectieProgram}}</small></span></div>
            <div class="profil-concediu-corectie-formular">
                <div class="profil-concediu-corectie-camp"><label>{{dataNouaAcordareEticheta}}</label><div class="field" data-name="dataCorectieAcordare">{{{dataUrmatoareiAcordari}}}</div></div>
                <div class="profil-concediu-corectie-camp"><label>{{motivProgramAcordareEticheta}}</label><input type="text" class="form-control" data-camp="motivProgramAcordare"></div>
                <button class="btn btn-default" data-action="corecteazaProgramAcordare">{{aplicaEticheta}}</button>
            </div>
        </section>
        <section class="profil-concediu-corectie">
            <div class="profil-concediu-corectie-antet"><span class="fas fa-balance-scale" aria-hidden="true"></span><span><strong>{{corecteazaEticheta}}</strong><small>{{explicatieCorectieSold}}</small></span></div>
            <div class="profil-concediu-corectie-formular">
                <div class="profil-concediu-corectie-camp"><label>{{diferentaEticheta}}</label><div class="field" data-name="diferentaCorectie">{{{diferentaCorectie}}}</div></div>
                <div class="profil-concediu-corectie-camp"><label>{{motivEticheta}}</label><input type="text" class="form-control" data-camp="motivCorectie"></div>
                <button class="btn btn-default" data-action="corecteaza">{{aplicaEticheta}}</button>
            </div>
        </section>
    </div>
</details>
{{/if}}
