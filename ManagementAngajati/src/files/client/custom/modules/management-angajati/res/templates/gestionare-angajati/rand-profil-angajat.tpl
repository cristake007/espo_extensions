<article class="profil-angajat-card">
    <div class="profil-angajat-identitate">
        <span class="profil-angajat-avatar" aria-hidden="true"><span class="fas fa-user"></span></span>
        <span class="profil-angajat-nume"><strong>{{numeAngajat}}</strong><small>{{descriereAngajat}}</small></span>
        <span class="profil-angajat-stare {{clasaStare}}"><span class="fas {{iconStare}}" aria-hidden="true"></span>{{stare}}</span>
    </div>
    <div class="profil-angajat-campuri">
        <div class="profil-angajat-camp">
            <label class="control-label" data-name="dataAngajare">{{dataAngajareEticheta}}</label>
            <div class="field" data-name="dataAngajare">{{{dataAngajare}}}</div>
            <small>{{dataAngajareAjutor}}</small>
        </div>
        <div class="profil-angajat-camp">
            <label class="control-label" data-name="calendarLucruId">{{calendarLucruEticheta}}</label>
            <div class="field" data-name="calendarLucruId">{{{calendarLucruId}}}</div>
            <small>{{calendarLucruAjutor}}</small>
        </div>
    </div>
</article>
