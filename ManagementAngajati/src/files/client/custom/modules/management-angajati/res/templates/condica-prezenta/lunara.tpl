<div class="management-angajati condica-prezenta-lunara">
    <header class="condica-antet">
        <div class="condica-antet-identitate">
            <span class="condica-icon condica-icon-mare" aria-hidden="true"><span class="fas fa-users"></span></span>
            <div>
                <div class="condica-spratitlu">{{spratitlu}}</div>
                <h2>{{titlu}}</h2>
                <p>{{descrierePagina}}</p>
            </div>
        </div>
        <div class="condica-selector-luna">
            <small>{{lunaAfisata}}</small>
            <span class="label {{#if cicluLunar.esteInchisa}}label-default{{else}}label-success{{/if}}">{{stareLuna}}</span>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-default" data-action="lunaAnterioara" title="{{anterior}}" {{#unless poateMergeAnterior}}disabled{{/unless}}><span class="fas fa-chevron-left" aria-hidden="true"></span></button>
                <button type="button" class="btn btn-default" data-action="lunaCurenta">{{lunaCurenta}}</button>
                <button type="button" class="btn btn-default" data-action="lunaUrmatoare" title="{{urmator}}"><span class="fas fa-chevron-right" aria-hidden="true"></span></button>
            </div>
        </div>
    </header>

    <section class="condica-actiuni-rapide">
        <div class="condica-actiuni-identitate">
            <span class="fas fa-bolt" aria-hidden="true"></span>
            <span><strong>{{actiuniRapide}}</strong><small>{{actiuniRapideDescriere}}</small></span>
        </div>
        <div class="condica-actiuni-butoane">
            {{#if poateTrimiteRemindere}}<button type="button" class="btn btn-default" data-action="trimiteRemindere"><span class="fas fa-bell" aria-hidden="true"></span> {{trimiteRemindere}}</button>{{/if}}
            {{#if poateExporta}}
            <a class="btn btn-default" href="{{urlExportXlsx}}"><span class="fas fa-file-excel" aria-hidden="true"></span> {{descarcaXlsx}}</a>
            <a class="btn btn-default" href="{{urlExportPdf}}"><span class="fas fa-file-pdf" aria-hidden="true"></span> {{descarcaPdf}}</a>
            {{else}}
            <button type="button" class="btn btn-default" disabled><span class="fas fa-file-excel" aria-hidden="true"></span> {{descarcaXlsx}}</button>
            <button type="button" class="btn btn-default" disabled><span class="fas fa-file-pdf" aria-hidden="true"></span> {{descarcaPdf}}</button>
            {{/if}}
            <button type="button" class="btn btn-warning{{#unless poateInchideLuna}} hidden{{/unless}}" data-action="inchideLuna"><span class="fas fa-lock" aria-hidden="true"></span> {{inchideLuna}}</button>
        </div>
    </section>

    <section class="condica-panou condica-stare-lunara">
        <div class="condica-panou-antet">
            <div class="condica-sectiune-identitate">
                <span class="condica-icon" aria-hidden="true"><span class="fas fa-chart-line"></span></span>
                <div><h4>{{stareLunara}}</h4><p>{{stareLunaraDescriere}}</p></div>
            </div>
        </div>
        <div class="condica-indicatori">
            <article class="condica-indicator este-completare">
                <div class="condica-indicator-rezumat">
                    <span class="condica-indicator-icon"><span class="fas fa-chart-pie"></span></span>
                    <span><small>{{etichete.completare}}</small><strong><span data-role="procent-completare">{{statistici.procentCompletare}}</span>%</strong><em data-role="descriere-completare">{{descriereCompletare}}</em></span>
                </div>
                {{#if detaliiCompletare.length}}
                <details class="condica-indicator-detalii">
                    <summary><span>{{completatPeAngajat}}</span><span class="condica-indicator-contor">{{detaliiCompletare.length}}</span></summary>
                    <div class="condica-mini-tabel">
                        {{#each detaliiCompletare}}<div data-detaliu-completare="{{idAngajat}}"><span>{{nume}}</span><strong>{{valoare}}</strong></div>{{/each}}
                    </div>
                </details>
                {{/if}}
            </article>
            <article class="condica-indicator este-lipsa">
                <div class="condica-indicator-rezumat">
                    <span class="condica-indicator-icon"><span class="fas fa-exclamation-circle"></span></span>
                    <span><small>{{etichete.lipsa}}</small><strong data-role="prezente-lipsa">{{statistici.zileLipsa}}</strong><em data-role="descriere-lipsa">{{descriereLipsa}}</em></span>
                </div>
                {{#if detaliiLipsa.length}}
                <details class="condica-indicator-detalii">
                    <summary><span>{{etichetaAngajatiAfectati}}</span><span class="condica-indicator-contor" data-role="contor-detalii-lipsa">{{angajatiAfectati}}</span></summary>
                    <div class="condica-mini-tabel">
                        {{#each detaliiLipsa}}<div data-detaliu-lipsa="{{idAngajat}}"><span>{{nume}}</span><strong>{{valoare}}</strong></div>{{/each}}
                    </div>
                </details>
                {{/if}}
            </article>
            <article class="condica-indicator este-calendare">
                <div class="condica-indicator-rezumat">
                    <span class="condica-indicator-icon"><span class="fas fa-calendar-check"></span></span>
                    <span><small>{{etichete.calendare}}</small><strong>{{statistici.calendareConfigurate}}/{{statistici.numarAngajati}}</strong><em>{{descriereCalendare}}</em></span>
                </div>
                {{#if detaliiCalendare.length}}
                <details class="condica-indicator-detalii">
                    <summary><span>{{calendareNeconfigurate}}</span><span class="condica-indicator-contor">{{detaliiCalendare.length}}</span></summary>
                    <div class="condica-mini-tabel">
                        {{#each detaliiCalendare}}<div><span>{{nume}}</span></div>{{/each}}
                    </div>
                </details>
                {{/if}}
            </article>
            <article class="condica-indicator este-export">
                <div class="condica-indicator-rezumat">
                    <span class="condica-indicator-icon"><span class="fas fa-file-download"></span></span>
                    <span><small>{{pregatireExport}}</small><strong>{{exportDisponibil}}</strong><em>{{descriereExport}}</em></span>
                </div>
                {{#if detaliiExport.length}}
                <details class="condica-indicator-detalii">
                    <summary><span>{{detalii}}</span><span class="condica-indicator-contor">{{detaliiExport.length}}</span></summary>
                    <div class="condica-mini-tabel">
                        {{#each detaliiExport}}<div><span>{{nume}}</span></div>{{/each}}
                    </div>
                </details>
                {{/if}}
            </article>
        </div>
        <div class="condica-stare-detalii">
            <span class="fas {{#if cicluLunar.esteInchisa}}fa-lock{{else}}fa-info-circle{{/if}}" aria-hidden="true"></span>
            <span><strong data-role="stare-completitudine">{{etichete.stareCompletitudine}}</strong><small data-role="motive-blocare">{{#if etichete.motiveBlocare.length}}{{#each etichete.motiveBlocare}}{{#unless @first}}; {{/unless}}{{this}}{{/each}}{{/if}}</small></span>
        </div>
    </section>

    <section class="condica-panou condica-vedere-zilnica">
        <div class="condica-panou-antet condica-tabel-antet">
            <div class="condica-sectiune-identitate">
                <span class="condica-icon" aria-hidden="true"><span class="fas fa-table"></span></span>
                <div><h4>{{vedereZilnica}}</h4><p>{{vedereZilnicaDescriere}}</p></div>
            </div>
            <div class="condica-contor"><span class="fas fa-th" aria-hidden="true"></span> {{totalAngajatiZile}}</div>
        </div>
        <div class="condica-legenda">
            <strong>{{legendaStari}}</strong>
            <div>{{#each legenda}}<span><i class="{{clasa}}"></i>{{eticheta}}</span>{{/each}}</div>
        </div>
        <div class="condica-prezenta-container">
        <table class="table table-bordered condica-prezenta-tabel">
            <colgroup>
                <col class="condica-prezenta-col-data">
                {{#each angajati}}
                <col class="condica-prezenta-col-angajat">
                {{/each}}
            </colgroup>
            <thead>
                <tr>
                    <th class="condica-prezenta-data">{{data}}</th>
                    {{#each angajati}}
                    <th class="condica-prezenta-angajat">
                        <strong>{{numeAngajat}}</strong>
                        <div class="text-muted small">{{rezumat}}</div>
                    </th>
                    {{/each}}
                </tr>
            </thead>
            <tbody>
                {{#each zile}}
                <tr>
                    <th class="condica-prezenta-data">
                        {{dataAfisata}}
                        <div class="text-muted small">{{numeZi}}</div>
                    </th>
                    {{#each celule}}
                    <td class="condica-prezenta-celula {{clasaVizuala}}{{#if esteLipsa}} danger{{/if}}">
                        <div class="condica-prezenta-stare"><strong>{{stare}}</strong>{{#if esteRemote}} · {{../../etichete.remote}}{{/if}}</div>
                        {{#if poateMarca}}
                        <div class="btn-group btn-group-xs condica-prezenta-actiuni" role="group">
                            {{#each optiuni}}
                            <button
                                type="button"
                                class="btn {{#if activ}}btn-primary{{else}}btn-default{{/if}}"
                                data-action="marcheazaPrezenta"
                                data-cheie-actiune="{{cheieActiune}}"
                                title="{{eticheta}}"
                                {{#unless poateMarca}}disabled{{/unless}}
                            >{{cod}}</button>
                            {{/each}}
                        </div>
                        {{/if}}
                    </td>
                    {{/each}}
                </tr>
                {{/each}}
            </tbody>
        </table>
        </div>
    </section>
    </div>
