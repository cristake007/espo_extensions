<div class="management-angajati profiluri-angajati-page">
    <header class="profiluri-angajati-antet">
        <a class="profiluri-angajati-inapoi" href="#GestionareAngajati" title="{{inapoiAdministrare}}">
            <span class="fas fa-arrow-left" aria-hidden="true"></span>
        </a>
        <span class="profiluri-angajati-icon profiluri-angajati-icon-mare" aria-hidden="true">
            <span class="fas fa-id-card"></span>
        </span>
        <div>
            <div class="profiluri-angajati-spratitlu">{{configurareProfiluri}}</div>
            <h2>{{titlu}}</h2>
            <p>{{explicatie}}</p>
        </div>
    </header>

    <section class="profiluri-angajati-panou profiluri-angajati-ghid-panou">
        <div class="profiluri-angajati-panou-antet">
            <span class="profiluri-angajati-icon" aria-hidden="true"><span class="fas fa-info"></span></span>
            <div><h4>{{cumFunctioneaza}}</h4><p>{{explicatieConfigurare}}</p></div>
        </div>
        <div class="profiluri-angajati-ghid">
            <div class="profiluri-angajati-ghid-card">
                <span class="profiluri-angajati-icon" aria-hidden="true"><span class="fas fa-calendar-day"></span></span>
                <span><strong>{{dataAngajare}}</strong><small>{{explicatieDataAngajare}}</small></span>
            </div>
            <div class="profiluri-angajati-ghid-card">
                <span class="profiluri-angajati-icon" aria-hidden="true"><span class="fas fa-business-time"></span></span>
                <span><strong>{{calendarLucru}}</strong><small>{{explicatieCalendarLucru}}</small></span>
            </div>
        </div>
    </section>

    <section class="profiluri-angajati-panou">
        <div class="profiluri-angajati-panou-antet profiluri-angajati-lista-antet">
            <span class="profiluri-angajati-icon" aria-hidden="true"><span class="fas fa-users"></span></span>
            <div><h4>{{angajatiEticheta}}</h4><p>{{explicatieLista}}</p></div>
            <span class="profiluri-angajati-numar">{{numarAngajati}}</span>
        </div>
        <div class="profiluri-angajati-lista" data-role="randuri-angajati"></div>
        <div class="profiluri-angajati-salvare">
            <span>{{explicatieSalvare}}</span>
            <button class="btn btn-primary" data-action="salveaza" disabled>
                <span class="fas fa-save" aria-hidden="true"></span> {{salvare}}
            </button>
        </div>
    </section>
</div>
