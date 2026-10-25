<main class="management-angajati prezenta-personala-page">
    <header class="prezenta-personala-antet">
        <div class="prezenta-personala-antet-identitate">
            <span class="prezenta-personala-icon prezenta-personala-icon-mare" aria-hidden="true"><span class="fas fa-clipboard-check"></span></span>
            <div>
                <div class="prezenta-personala-spratitlu">{{spratitlu}}</div>
                <h2>{{titlu}}</h2>
                <p>{{descriere}}</p>
            </div>
        </div>
    </header>

    {{#if esteCicActiv}}
    <section class="stare-cic-panou" aria-labelledby="stare-cic-prezenta-titlu">
        <div class="stare-cic-icon" aria-hidden="true"><span class="fas fa-child"></span></div>
        <div class="stare-cic-continut">
            <span class="label label-info">{{cicStare}}</span>
            <h3 id="stare-cic-prezenta-titlu">{{cicTitlu}}</h3>
            <p>{{cicExplicatie}}</p>
            <dl class="stare-cic-perioada">
                <dt>{{cicPerioada}}</dt>
                <dd>{{cicDataInceput}} – {{cicDataSfarsit}}</dd>
            </dl>
            <div class="stare-cic-mesaj">
                <span class="fas fa-check-circle" aria-hidden="true"></span>
                <span><strong>{{cicFaraActiune}}</strong><small>{{cicContactManager}}</small></span>
            </div>
        </div>
    </section>
    {{else}}
    <section class="prezenta-astazi-panou" data-zi="{{dataAstazi}}">
        <div class="prezenta-personala-panou-antet">
            <div class="prezenta-personala-sectiune-identitate">
                <span class="prezenta-personala-icon prezenta-personala-icon-succes" aria-hidden="true"><span class="far fa-calendar-check"></span></span>
                <div>
                    <h4>{{prezentaAstazi}}</h4>
                    <p>{{descrierePrezentaAstazi}}</p>
                </div>
            </div>
            <span class="prezenta-astazi-data">{{dataAstazi}}</span>
        </div>
        <div class="prezenta-astazi-continut">
            <div class="prezenta-astazi-stare">
                <span class="prezenta-rezumat-eticheta">{{stareCurenta}}</span>
                <span class="prezenta-status prezenta-status-mare {{clasaAstazi}}">
                    <span class="fas {{iconAstazi}}" aria-hidden="true"></span>
                    <span>{{etichetaAstazi}}</span>
                </span>
                <p>{{stareAstazi}}</p>
            </div>
            <div class="prezenta-astazi-actiuni">
                {{#if poateMarcaAstazi}}
                <div class="prezenta-alegere-titlu"><span class="fas fa-hand-pointer" aria-hidden="true"></span><strong>{{alegePrezenta}}</strong></div>
                <div class="prezenta-actiuni-carduri">
                    {{#each optiuniAstazi}}
                    <button type="button" class="btn prezenta-actiune-card {{butonClasa}} {{#if activ}}este-activa{{/if}}" data-action="marcheazaPrezenta" data-data="{{../dataAstazi}}" data-cod="{{cod}}" aria-pressed="{{#if activ}}true{{else}}false{{/if}}" {{#unless poateMarca}}disabled{{/unless}}>
                        <span class="prezenta-actiune-icon fas {{icon}}" aria-hidden="true"></span>
                        <span class="prezenta-actiune-copie"><strong>{{eticheta}}</strong><small>{{ajutor}}</small></span>
                        <span class="prezenta-actiune-bifat fas fa-check-circle" aria-hidden="true"></span>
                    </button>
                    {{/each}}
                </div>
                {{else}}
                <div class="prezenta-fara-actiune">
                    <span class="prezenta-fara-actiune-icon fas fa-check" aria-hidden="true"></span>
                    <span><strong>{{nicioActiuneNecesara}}</strong><small>{{stareAstazi}}</small></span>
                </div>
                {{/if}}
            </div>
        </div>
    </section>

    <section class="prezenta-personala-panou">
        <div class="prezenta-personala-panou-antet prezenta-luna-antet">
            <div class="prezenta-personala-sectiune-identitate">
                <span class="prezenta-personala-icon" aria-hidden="true"><span class="far fa-calendar-alt"></span></span>
                <div><h4>{{registruLunar}}</h4><p>{{descriereLuna}}</p></div>
            </div>
            <div class="prezenta-personala-selector">
                <div class="prezenta-personala-luna">{{lunaAfisata}} <span class="label {{stareLunaClasa}}">{{stareLunaAfisata}}</span></div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-default" data-action="lunaAnterioara" title="{{anterior}}" {{#unless poateMergeAnterior}}disabled{{/unless}}><span class="fas fa-chevron-left"></span></button>
                    <button type="button" class="btn btn-default" data-action="lunaCurenta">{{lunaCurenta}}</button>
                    <button type="button" class="btn btn-default" data-action="lunaUrmatoare" title="{{urmator}}"><span class="fas fa-chevron-right"></span></button>
                </div>
            </div>
        </div>

        <div class="prezenta-indicatori-lunari">
            <div class="prezenta-indicator este-succes"><span class="prezenta-indicator-icon fas fa-check-circle" aria-hidden="true"></span><span><small>{{zileCompletate}}</small><strong>{{numarZileCompletate}}</strong></span></div>
            <div class="prezenta-indicator este-pericol"><span class="prezenta-indicator-icon fas fa-exclamation-circle" aria-hidden="true"></span><span><small>{{zileNecompletate}}</small><strong>{{numarZileNecompletate}}</strong></span></div>
            <div class="prezenta-indicator este-info"><span class="prezenta-indicator-icon fas fa-clock" aria-hidden="true"></span><span><small>{{zileViitoare}}</small><strong>{{numarZileViitoare}}</strong></span></div>
        </div>

        {{#if areZile}}
        <div class="table-responsive prezenta-personala-tabel-container">
            <table class="table prezenta-personala-tabel">
                <thead><tr><th>{{data}}</th><th>{{stareCurenta}}</th><th>{{alegePrezenta}}</th></tr></thead>
                <tbody>
                    {{#each zile}}
                    <tr class="{{randClasa}}" data-zi="{{data}}">
                        <td class="prezenta-personala-data"><strong>{{dataAfisata}}</strong><span>{{numeZi}}</span></td>
                        <td class="prezenta-personala-stare"><span class="prezenta-status {{codClasa}}"><span class="fas {{codIcon}}" aria-hidden="true"></span><span>{{codEticheta}}</span></span></td>
                        <td>
                            {{#if poateFiCompletata}}
                            <div class="prezenta-optiuni-rand" role="group">
                                {{#each optiuni}}
                                <button type="button" class="btn btn-sm prezenta-buton-rand {{actiuneClasa}} {{#if activ}}este-activa{{/if}}" data-action="marcheazaPrezenta" data-data="{{../data}}" data-cod="{{cod}}" aria-pressed="{{#if activ}}true{{else}}false{{/if}}" title="{{ajutor}}"><span class="fas {{icon}}" aria-hidden="true"></span> <span>{{eticheta}}</span></button>
                                {{/each}}
                            </div>
                            {{else}}
                            <span class="prezenta-actiune-blocata"><span class="fas fa-lock" aria-hidden="true"></span> {{stare}}</span>
                            {{/if}}
                        </td>
                    </tr>
                    {{/each}}
                </tbody>
            </table>
        </div>
        {{else}}
        <div class="prezenta-personala-gol"><span class="fas fa-calendar-times" aria-hidden="true"></span><h4>{{faraZile}}</h4><p>{{faraZileExplicatie}}</p></div>
        {{/if}}
    </section>
    {{/if}}
</main>
