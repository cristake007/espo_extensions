<div class="management-angajati concedii-personale-page">
    <header class="concedii-personale-antet">
        <div class="concedii-personale-antet-identitate">
            <span class="concedii-personale-icon concedii-personale-icon-mare" aria-hidden="true">
                <span class="fas fa-umbrella-beach"></span>
            </span>
            <div>
                <div class="concedii-personale-spratitlu">{{spratitlu}}</div>
                <h2>{{titlu}}</h2>
                <p>{{descrierePagina}}</p>
            </div>
        </div>
    </header>

    {{#unless disponibil}}
    {{#if esteCicActiv}}
    <section class="concedii-personale-panou concedii-personale-indisponibil">
        <div class="concedii-personale-indisponibil-continut">
            <span class="concedii-personale-stare-icon este-info" aria-hidden="true">
                <span class="fas fa-child"></span>
            </span>
            <div>
                <span class="label label-info">{{cicStare}}</span>
                <h3>{{indisponibilTitlu}}</h3>
                <p>{{indisponibilExplicatie}}</p>
                <dl class="concedii-personale-detalii">
                    <dt>{{cicPerioada}}</dt>
                    <dd>{{cicDataInceput}} – {{cicDataSfarsit}}</dd>
                </dl>
                <div class="concedii-personale-nota">
                    <span class="fas fa-info-circle" aria-hidden="true"></span>
                    <span>
                        <strong>{{cicFaraCereri}}</strong>
                        <small>{{cicContactManager}}</small>
                    </span>
                </div>
            </div>
        </div>
    </section>
    {{else}}
    <section class="concedii-personale-panou concedii-personale-indisponibil">
        <div class="concedii-personale-indisponibil-continut">
            <span class="concedii-personale-stare-icon este-atentionare" aria-hidden="true">
                <span class="fas fa-user-clock"></span>
            </span>
            <div>
                <h3>{{indisponibilTitlu}}</h3>
                <p>{{indisponibilExplicatie}}</p>
                {{#if afiseazaIndrumareProfil}}
                <div class="concedii-personale-nota">
                    <span class="fas fa-info-circle" aria-hidden="true"></span>
                    <span>
                        <strong>{{profilNecesitaConfigurare}}</strong>
                        <small>{{profilIndrumare}}</small>
                    </span>
                </div>
                {{/if}}
                {{#if poateAfisaActiune}}
                <a class="btn btn-primary" href="{{urlActiune}}">
                    <span class="fas fa-cog" aria-hidden="true"></span> {{etichetaActiune}}
                </a>
                {{/if}}
            </div>
        </div>
    </section>
    {{/if}}
    {{else}}

    {{#if sold}}
    <section class="concedii-personale-rezumat" aria-label="{{rezumatSold}}">
        <article class="concedii-personale-indicator este-disponibil">
            <span class="concedii-personale-indicator-icon" aria-hidden="true">
                <span class="fas fa-calendar-check"></span>
            </span>
            <span><small>{{soldDisponibil}}</small><strong>{{sold.disponibile}}</strong></span>
        </article>
        <article class="concedii-personale-indicator este-rezervat">
            <span class="concedii-personale-indicator-icon" aria-hidden="true">
                <span class="fas fa-hourglass-half"></span>
            </span>
            <span><small>{{soldRezervat}}</small><strong>{{sold.rezervate}}</strong></span>
        </article>
        <article class="concedii-personale-indicator este-consumat">
            <span class="concedii-personale-indicator-icon" aria-hidden="true">
                <span class="fas fa-calendar-minus"></span>
            </span>
            <span><small>{{soldConsumat}}</small><strong>{{sold.consumate}}</strong></span>
        </article>
    </section>
    {{/if}}

    <section class="concedii-personale-panou concedii-personale-formular">
        <div class="concedii-personale-panou-antet">
            <div class="concedii-personale-sectiune-identitate">
                <span class="concedii-personale-icon" aria-hidden="true">
                    <span class="fas fa-calendar-plus"></span>
                </span>
                <div><h4>{{cerereNoua}}</h4><p>{{cerereNouaDescriere}}</p></div>
            </div>
        </div>
        <div class="concedii-personale-formular-continut">
            <div class="concedii-personale-perioada-solicitata">
                <div class="concedii-personale-subtitlu-formular">
                    <span class="fas fa-calendar-alt" aria-hidden="true"></span>
                    <span>{{perioadaSolicitata}}</span>
                </div>
                <div class="concedii-personale-campuri-perioada">
                    <div class="form-group concedii-personale-camp">
                        <label>{{dataInceputEticheta}}</label>
                        <div class="field" data-name="dataInceput">{{{dataInceput}}}</div>
                    </div>
                    <span class="concedii-personale-separator-perioada fas fa-arrow-right" aria-hidden="true"></span>
                    <div class="form-group concedii-personale-camp">
                        <label>{{dataSfarsitEticheta}}</label>
                        <div class="field" data-name="dataSfarsit">{{{dataSfarsit}}}</div>
                    </div>
                </div>
            </div>
            <aside class="concedii-personale-previzualizare-zile" data-name="previzualizareZile" aria-live="polite">
                <span class="concedii-personale-previzualizare-icon" aria-hidden="true">
                    <span class="fas fa-business-time"></span>
                </span>
                <span class="concedii-personale-previzualizare-text">
                    <small>{{zileEligibile}}</small>
                    <em data-role="explicatie-zile">{{selecteazaPerioadaPentruCalcul}}</em>
                </span>
                <strong class="concedii-personale-previzualizare-numar" data-role="numar-zile">—</strong>
            </aside>
            <div class="concedii-personale-detalii-cerere">
                <div class="form-group concedii-personale-observatii">
                    <label>{{descriereEticheta}}</label>
                    <input class="form-control" data-name="descriere">
                </div>
                <div class="concedii-personale-trimitere">
                    <button class="btn btn-primary" data-action="trimiteCerere"{{#if trimiteDezactivat}} disabled aria-disabled="true"{{/if}}>
                        <span class="fas fa-paper-plane" aria-hidden="true"></span> {{trimite}}
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="concedii-personale-panou">
        <div class="concedii-personale-panou-antet">
            <div class="concedii-personale-sectiune-identitate">
                <span class="concedii-personale-icon" aria-hidden="true">
                    <span class="fas fa-history"></span>
                </span>
                <div><h4>{{istoricCereri}}</h4><p>{{istoricCereriDescriere}}</p></div>
            </div>
            <div class="concedii-personale-contor">{{contorCereri}}</div>
        </div>
        {{#if areCereri}}
        {{#if areIstoricSold}}
        <div class="concedii-personale-istoric-interactiv">
            <div class="concedii-personale-istoric-carduri">
                <article class="concedii-personale-istoric-sold">
                    <span class="concedii-personale-istoric-icon" aria-hidden="true">
                        <span class="fas fa-wallet"></span>
                    </span>
                    <div class="concedii-personale-istoric-sold-jos">
                        <span class="concedii-personale-istoric-sold-valoare">
                            <small data-role="istoric-titlu-sold">{{istoricSelectat.titluSold}}</small>
                            <strong data-role="istoric-sold-disponibil">{{istoricSelectat.soldFinal}}</strong>
                            <em>{{zileDisponibileEticheta}}</em>
                        </span>
                        <span class="concedii-personale-istoric-impact {{istoricSelectat.impactClasa}}" data-role="istoric-impact">
                            <small>{{impactFinalCerere}}</small>
                            <strong data-role="istoric-impact-final">{{istoricSelectat.impactFinalAfisat}}</strong>
                        </span>
                    </div>
                </article>
                <article class="concedii-personale-istoric-detaliu">
                    <div class="concedii-personale-istoric-detaliu-antet">
                        <span class="concedii-personale-istoric-icon" aria-hidden="true">
                            <span class="fas fa-calendar-alt"></span>
                        </span>
                        <span>
                            <small data-role="istoric-titlu-eveniment">{{istoricSelectat.titluEveniment}}</small>
                            <strong data-role="istoric-perioada">{{istoricSelectat.perioadaAfisata}}</strong>
                        </span>
                        <span class="label {{istoricSelectat.stareClasa}} concedii-personale-stare" data-role="istoric-stare">
                            <span class="fas {{istoricSelectat.stareIcon}}" aria-hidden="true"></span>
                            <span data-role="istoric-stare-text">{{istoricSelectat.stareAfisata}}</span>
                        </span>
                    </div>
                    <div class="concedii-personale-istoric-informatii">
                        <span><strong data-role="istoric-numar-zile">{{istoricSelectat.valoareDetaliu}}</strong> <span data-role="istoric-eticheta-zile">{{istoricSelectat.etichetaDetaliu}}</span></span>
                        <span class="concedii-personale-istoric-motiv" data-role="istoric-descriere">{{istoricSelectat.descriereAfisata}}</span>
                    </div>
                </article>
            </div>
            <div class="concedii-personale-istoric-explicatie">
                <span class="concedii-personale-istoric-icon" aria-hidden="true">
                    <span class="fas fa-exchange-alt"></span>
                </span>
                <span>
                    <small>{{evolutieSoldCerere}}</small>
                    <strong data-role="istoric-explicatie-sold">{{istoricSelectat.explicatieSold}}</strong>
                </span>
            </div>
            <div class="concedii-personale-istoric-slider-wrap">
                <div class="concedii-personale-istoric-slider-etichete">
                    <span>{{primaCerere}}</span>
                    <strong data-role="istoric-pozitie">{{istoricPozitie}}</strong>
                    <span>{{ultimaCerere}}</span>
                </div>
                <input type="range"
                       class="concedii-personale-istoric-slider"
                       data-role="istoric-sold-slider"
                       min="0"
                       max="{{istoricMaxim}}"
                       step="1"
                       value="{{istoricMaxim}}"
                       aria-label="{{navigareIstoricSold}}">
            </div>
        </div>
        {{/if}}
        <div class="table-responsive">
            <table class="table concedii-personale-tabel">
                <thead><tr><th>{{perioada}}</th><th>{{zile}}</th><th>{{stare}}</th><th>{{documente}}</th><th class="text-right">{{actiuni}}</th></tr></thead>
                <tbody>
                    {{#each cereri}}
                    <tr>
                        <td class="concedii-personale-perioada">
                            <span class="fas fa-calendar-alt" aria-hidden="true"></span>
                            <strong>{{dataInceputAfisata}} – {{dataSfarsitAfisata}}</strong>
                        </td>
                        <td>
                            <strong class="concedii-personale-zile {{zileClasa}}" title="{{zileExplicatie}}">{{numarZileAfisat}}</strong>
                        </td>
                        <td><span class="label {{stareClasa}} concedii-personale-stare"><span class="fas {{stareIcon}}" aria-hidden="true"></span><span>{{stareAfisata}}</span></span></td>
                        <td class="concedii-personale-documente">
                            {{#if areDocumente}}
                            {{#each documente}}
                            <a class="concedii-personale-document" href="{{url}}" target="_blank" rel="noopener">
                                <span class="fas fa-file-word" aria-hidden="true"></span> {{nume}}
                            </a>
                            {{/each}}
                            {{else}}
                            <span class="text-muted">—</span>
                            {{/if}}
                        </td>
                        <td class="concedii-personale-actiuni text-right">
                            {{#if poateGeneraDocumente}}
                            <button class="btn btn-default btn-sm" data-action="genereazaDocumente" data-id="{{id}}"><span class="fas fa-file-word" aria-hidden="true"></span> {{../genereazaDocumente}}</button>
                            {{/if}}
                            {{#if poateSolicitaAnulare}}
                            <button class="btn btn-sm concedii-personale-actiune-periculoasa" data-action="anuleazaCerere" data-id="{{id}}" data-stare="{{stare}}"><span class="fas fa-undo" aria-hidden="true"></span> {{#if esteInAsteptare}}{{../retrageCerere}}{{else}}{{../solicitaAnulare}}{{/if}}</button>
                            {{/if}}
                            {{#unless areActiuni}}<span class="text-muted">—</span>{{/unless}}
                        </td>
                    </tr>
                    {{/each}}
                </tbody>
            </table>
        </div>
        {{else}}
        <div class="concedii-personale-gol">
            <span class="fas fa-calendar-check" aria-hidden="true"></span>
            <h4>{{faraCereri}}</h4>
            <p>{{faraCereriExplicatie}}</p>
        </div>
        {{/if}}
    </section>
    {{/unless}}
</div>
