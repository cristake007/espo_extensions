<div class="management-angajati setari-concedii-page">
    <header class="setari-concedii-antet">
        <a class="setari-concedii-inapoi" href="#GestionareAngajati" title="{{inapoiAdministrare}}"><span class="fas fa-arrow-left" aria-hidden="true"></span></a>
        <span class="setari-concedii-icon setari-concedii-icon-mare" aria-hidden="true"><span class="fas fa-umbrella-beach"></span></span>
        <div><div class="setari-concedii-spratitlu">{{configurareConcedii}}</div><h2>{{titlu}}</h2><p>{{explicatie}}</p></div>
    </header>

    <section class="setari-concedii-panou">
        <div class="setari-concedii-panou-antet">
            <span class="setari-concedii-icon" aria-hidden="true"><span class="fas fa-user-check"></span></span>
            <div><h4>{{reguliAprobare}}</h4><p>{{explicatieReguliAprobare}}</p></div>
        </div>
        <div class="setari-concedii-reguli">
            <div class="setari-concedii-regula setari-concedii-regula-aprobatori">
                <div class="setari-concedii-regula-identitate"><span class="fas fa-users" aria-hidden="true"></span><span><strong>{{aprobatoriEticheta}}</strong><small>{{explicatieAprobatori}}</small></span></div>
                <div class="field" data-name="aprobatoriIds">{{{aprobatoriIds}}}</div>
            </div>
            <div class="setari-concedii-regula">
                <div class="setari-concedii-regula-identitate"><span class="fas fa-user-friends" aria-hidden="true"></span><span><strong>{{ambiiEticheta}}</strong><small>{{explicatieAmbiiManageri}}</small></span></div>
                <div class="field setari-concedii-bifa" data-name="necesitaAmbiiManageri">{{{necesitaAmbiiManageri}}}</div>
            </div>
            <div class="setari-concedii-regula">
                <div class="setari-concedii-regula-identitate"><span class="fas fa-calendar-plus" aria-hidden="true"></span><span><strong>{{acordareAutomataEticheta}}</strong><small>{{acordareAutomataExplicatie}}</small></span></div>
                <div class="field setari-concedii-bifa" data-name="acordareAnualaAutomata">{{{acordareAnualaAutomata}}}</div>
            </div>
        </div>
        <div class="setari-concedii-salvare-setari">
            <span>{{explicatieSalvareSetari}}</span>
            <button class="btn btn-primary" data-action="salveazaSetari"><span class="fas fa-save" aria-hidden="true"></span> {{salvare}}</button>
        </div>
    </section>

    <section class="setari-concedii-panou">
        <div class="setari-concedii-panou-antet">
            <span class="setari-concedii-icon" aria-hidden="true"><span class="fas fa-id-card"></span></span>
            <div><h4>{{profiluriEticheta}}</h4><p>{{explicatieInitializare}}</p></div>
        </div>
        <div class="setari-concedii-ghid">
            <div><span class="fas fa-calendar-check" aria-hidden="true"></span><span><strong>{{zileAnuale}}</strong><small>{{explicatieDreptAnual}}</small></span></div>
            <div><span class="fas fa-wallet" aria-hidden="true"></span><span><strong>{{soldCurent}}</strong><small>{{explicatieSoldCurent}}</small></span></div>
            <div><span class="fas fa-history" aria-hidden="true"></span><span><strong>{{soldReportat}}</strong><small>{{explicatieSoldReportat}}</small></span></div>
            <div><span class="fas fa-calendar-plus" aria-hidden="true"></span><span><strong>{{dataAcordare}}</strong><small>{{explicatieDataAcordare}}</small></span></div>
        </div>
        <div class="setari-concedii-profiluri" data-role="profiluri"></div>
    </section>

    <section class="setari-concedii-panou setari-concedii-resetare">
        <div class="setari-concedii-panou-antet">
            <span class="setari-concedii-icon" aria-hidden="true"><span class="fas fa-tools"></span></span>
            <div><h4>{{resetareTitlu}}</h4><p>{{explicatieResetare}}</p></div>
        </div>
        <div class="setari-concedii-resetare-corp">
            <div class="alert alert-danger"><strong>{{avertismentResetare}}</strong><div>{{resetareExplicatie}}</div></div>
            <dl class="setari-concedii-resetare-statistici">
                <div><dt>{{profiluriEticheta}}</dt><dd>{{resetare.profiluri}}</dd></div>
                <div><dt>{{cereriEticheta}}</dt><dd>{{resetare.cereri}}</dd></div>
                <div><dt>{{registruEticheta}}</dt><dd>{{resetare.registru}}</dd></div>
            </dl>
            <div class="setari-concedii-confirmare-resetare">
                <label class="control-label">{{confirmareReset}}</label>
                <input class="form-control" data-camp="confirmareResetConcedii" autocomplete="off" placeholder="RESET DATE CONCEDII">
                <button class="btn btn-danger" data-action="reseteazaDateTest"><span class="fas fa-trash-alt" aria-hidden="true"></span> {{resetareTitlu}}</button>
            </div>
        </div>
    </section>
</div>
