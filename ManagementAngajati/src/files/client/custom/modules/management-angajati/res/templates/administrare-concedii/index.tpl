<div class="management-angajati administrare-concedii-page">
    <header class="administrare-concedii-antet">
        <div class="administrare-concedii-antet-identitate">
            <span class="administrare-concedii-icon administrare-concedii-icon-mare" aria-hidden="true">
                <span class="fas fa-user-check"></span>
            </span>
            <div>
                <div class="administrare-concedii-spratitlu">{{spratitlu}}</div>
                <h2>{{titlu}}</h2>
                <p>{{descriere}}</p>
            </div>
        </div>
        <div class="administrare-concedii-rezumat">{{contor}}</div>
    </header>

    <section class="administrare-concedii-panou">
        <div class="administrare-concedii-panou-antet">
            <div class="administrare-concedii-sectiune-identitate">
                <span class="administrare-concedii-icon" aria-hidden="true">
                    <span class="fas fa-calendar-check"></span>
                </span>
                <div>
                    <h4>{{listaCereri}}</h4>
                    <p>{{descriereLista}}</p>
                </div>
            </div>
            <div class="administrare-concedii-contor">{{contor}}</div>
        </div>

        {{#if areCereri}}
        <div class="table-responsive administrare-concedii-tabel-container">
            <table class="table administrare-concedii-tabel">
                <thead>
                    <tr>
                        <th>{{angajat}}</th>
                        <th>{{perioada}}</th>
                        <th>{{zile}}</th>
                        <th>{{stare}}</th>
                        <th>{{documente}}</th>
                        <th class="text-right">{{actiuni}}</th>
                    </tr>
                </thead>
                <tbody>
                    {{#each cereri}}
                    <tr data-id-cerere="{{id}}"{{#if esteSelectata}} class="este-selectata"{{/if}}>
                        <td class="administrare-concedii-angajat">
                            <span class="administrare-concedii-avatar" aria-hidden="true">
                                <span class="fas fa-user"></span>
                            </span>
                            <strong>{{numeAngajat}}</strong>
                        </td>
                        <td class="administrare-concedii-perioada">
                            <span class="fas fa-calendar-alt" aria-hidden="true"></span>
                            {{dataInceputAfisata}} – {{dataSfarsitAfisata}}
                            {{#if motivAnulare}}
                            <div class="administrare-concedii-motiv">
                                <strong>{{../motivAnulare}}:</strong> {{motivAnulare}}
                            </div>
                            {{/if}}
                        </td>
                        <td><strong>{{numarZile}}</strong></td>
                        <td>
                            <span class="label {{stareClasa}}">{{stareAfisata}}</span>
                            <div class="administrare-concedii-progres">{{progresAprobari}}</div>
                        </td>
                        <td class="administrare-concedii-documente">
                            {{#each documente}}
                            <a href="{{url}}" target="_blank" rel="noopener">
                                <span class="fas fa-file-word" aria-hidden="true"></span> {{nume}}
                            </a>
                            {{else}}
                            <span class="text-muted">—</span>
                            {{/each}}
                        </td>
                        <td class="administrare-concedii-actiuni text-right">
                            {{#if poateGeneraDocumente}}
                            <button class="btn btn-default btn-sm" data-action="genereazaDocumente" data-id="{{id}}">
                                <span class="fas fa-file-word" aria-hidden="true"></span> {{../genereazaDocumente}}
                            </button>
                            {{/if}}
                            {{#if poateFiDecisa}}
                            <button class="btn btn-success btn-sm" data-action="aproba" data-id="{{id}}">
                                <span class="fas fa-check" aria-hidden="true"></span> {{../aproba}}
                            </button>
                            <button class="btn btn-danger btn-sm" data-action="respinge" data-id="{{id}}">
                                <span class="fas fa-times" aria-hidden="true"></span> {{../respinge}}
                            </button>
                            {{/if}}
                            {{#if poateDecideAnulare}}
                            <button class="btn btn-success btn-sm" data-action="aprobaAnularea" data-id="{{id}}">
                                <span class="fas fa-check" aria-hidden="true"></span> {{../aprobaAnularea}}
                            </button>
                            <button class="btn btn-danger btn-sm" data-action="respingeAnularea" data-id="{{id}}">
                                <span class="fas fa-times" aria-hidden="true"></span> {{../respingeAnularea}}
                            </button>
                            {{/if}}
                            {{#unless areActiuni}}<span class="text-muted">—</span>{{/unless}}
                        </td>
                    </tr>
                    {{/each}}
                </tbody>
            </table>
        </div>
        {{else}}
        <div class="administrare-concedii-gol">
            <span class="fas fa-calendar-check" aria-hidden="true"></span>
            <h4>{{faraCereri}}</h4>
            <p>{{faraCereriExplicatie}}</p>
        </div>
        {{/if}}
    </section>
</div>
