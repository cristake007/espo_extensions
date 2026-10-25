<div class="setari-condica-angajat-identitate">
    <span class="setari-condica-avatar" aria-hidden="true"><span class="fas fa-user-clock"></span></span>
    <span>
        <small>{{etichetaAngajat}}</small>
        <strong>{{numeAngajat}}</strong>
        <em class="{{#if dataAngajareLipsa}}text-danger{{else}}text-muted{{/if}}">{{dataAngajareEticheta}}: {{dataAngajareAfisata}}</em>
        {{#unless areCalendarLucru}}<em class="text-warning">{{faraCalendar}}</em>{{/unless}}
    </span>
</div>

<div class="setari-condica-optiune" data-camp-bool="remoteActiv">
    <div class="setari-condica-optiune-antet" data-role="comutator-optiune">
        <span class="fas fa-home" aria-hidden="true"></span>
        <div><strong>{{remote}}</strong><small>{{explicatieRemote}}</small></div>
        <div class="field setari-condica-bifa" data-name="remoteActiv">{{{remoteActiv}}}</div>
    </div>
    <div class="setari-condica-date{{#unless modRemoteInterval}} hidden{{/unless}}" data-role="campuri-remote-interval">
        <label><span>{{dataInceput}}</span><span class="field" data-name="dataInceputRemote">{{{dataInceputRemote}}}</span></label>
        <label><span>{{dataSfarsit}}</span><span class="field" data-name="dataSfarsitRemote">{{{dataSfarsitRemote}}}</span></label>
    </div>
</div>

<div class="setari-condica-optiune" data-camp-bool="cicActiv">
    <div class="setari-condica-optiune-antet" data-role="comutator-optiune">
        <span class="fas fa-child" aria-hidden="true"></span>
        <div><strong>{{cic}}</strong><small>{{explicatieCic}}</small></div>
        <div class="field setari-condica-bifa" data-name="cicActiv">{{{cicActiv}}}</div>
    </div>
    <div class="setari-condica-date">
        <label><span>{{dataInceput}}</span><span class="field" data-name="dataInceputCic">{{{dataInceputCic}}}</span></label>
        <label><span>{{dataSfarsit}}</span><span class="field" data-name="dataSfarsitCic">{{{dataSfarsitCic}}}</span></label>
    </div>
</div>
