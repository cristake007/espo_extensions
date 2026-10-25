<div class="management-angajati setari-condica-page">
    <header class="setari-condica-antet">
        <a class="setari-condica-inapoi" href="#GestionareAngajati" title="{{inapoiAdministrare}}"><span class="fas fa-arrow-left" aria-hidden="true"></span></a>
        <span class="setari-condica-icon setari-condica-icon-mare" aria-hidden="true"><span class="fas fa-business-time"></span></span>
        <div><div class="setari-condica-spratitlu">{{configurareCondica}}</div><h2>{{titlu}}</h2><p>{{explicatie}}</p></div>
    </header>

    <section class="setari-condica-panou">
        <div class="setari-condica-panou-antet">
            <span class="setari-condica-icon" aria-hidden="true"><span class="fas fa-sliders-h"></span></span>
            <div><h4>{{reguliCondica}}</h4><p>{{explicatieReguliCondica}}</p></div>
        </div>
        <div class="setari-condica-reguli">
            <label class="setari-condica-regula">
                <span class="setari-condica-regula-identitate"><strong>{{luniEditabile}}</strong><small>{{explicatieLuniEditabile}}</small></span>
                <input class="form-control" type="number" min="0" max="120" step="1" value="{{numarLuniAnterioareEditabile}}" data-camp="numarLuniAnterioareEditabile">
            </label>
            <label class="setari-condica-regula">
                <span class="setari-condica-regula-identitate"><strong>{{modRemote}}</strong><small>{{explicatieModRemote}}</small></span>
                <select class="form-control" data-camp="modAplicareRemote">
                    <option value="curent"{{#ifEqual modAplicareRemote 'curent'}} selected{{/ifEqual}}>{{modCurent}}</option>
                    <option value="interval"{{#ifEqual modAplicareRemote 'interval'}} selected{{/ifEqual}}>{{modInterval}}</option>
                </select>
            </label>
            <div class="setari-condica-regula">
                <span class="setari-condica-regula-identitate"><strong>{{etichetaExportCondicaIncompleta}}</strong><small>{{explicatieExportCondicaIncompleta}}</small></span>
                <div class="field" data-name="permiteExportCondicaIncompleta">{{{permiteExportCondicaIncompleta}}}</div>
            </div>
        </div>
    </section>

    <section class="setari-condica-panou setari-condica-angajati">
        <div class="setari-condica-panou-antet">
            <span class="setari-condica-icon" aria-hidden="true"><span class="fas fa-users"></span></span>
            <div><h4>{{configurareAngajati}}</h4><p>{{explicatieConfigurareAngajati}}</p></div>
            <span class="setari-condica-contor">{{numarAngajati}}</span>
        </div>
        <div class="setari-condica-lista" data-role="randuri-angajati"></div>
    </section>

    <section class="setari-condica-panou setari-condica-resetare">
        <div class="setari-condica-panou-antet">
            <span class="setari-condica-icon" aria-hidden="true"><span class="fas fa-tools"></span></span>
            <div><h4>{{zonaResetare}}</h4><p>{{explicatieZonaResetare}}</p></div>
        </div>
        <div class="setari-condica-resetare-corp">
            <div class="alert alert-danger setari-condica-avertisment"><strong>{{resetareAvertisment}}</strong><div>{{resetareExplicatie}}</div></div>
            {{#if previzualizareReset}}
            <dl class="setari-condica-resetare-statistici">
                <div><dt>{{eticheteReset.prezente}}</dt><dd>{{previzualizareReset.prezente}}</dd></div>
                <div><dt>{{eticheteReset.stariLuna}}</dt><dd>{{previzualizareReset.stariLuna}}</dd></div>
                <div><dt>{{eticheteReset.remindere}}</dt><dd>{{previzualizareReset.remindere}}</dd></div>
                <div><dt>{{eticheteReset.configuratii}}</dt><dd>{{previzualizareReset.configuratiiCicRemote}}</dd></div>
                <div><dt>{{eticheteReset.dateAngajare}}</dt><dd>{{previzualizareReset.dateAngajare}}</dd></div>
                <div><dt>{{eticheteReset.calendare}}</dt><dd>{{previzualizareReset.calendareLucruPastrate}}</dd></div>
            </dl>
            {{/if}}
            <div class="setari-condica-confirmare-resetare">
                <label class="control-label">{{confirmareReset}}</label>
                <input type="text" class="form-control" data-camp="confirmareReset" autocomplete="off" placeholder="{{expresieConfirmare}}">
                <button type="button" class="btn btn-danger" data-action="reseteazaDateTest" disabled><span class="fas fa-trash-alt"></span> {{resetareTitlu}}</button>
            </div>
        </div>
    </section>

    <footer class="setari-condica-actiuni">
        <span>{{explicatieSalvare}}</span>
        <button class="btn btn-primary" data-action="salveaza" disabled><span class="fas fa-save"></span> {{salvare}}</button>
    </footer>
</div>
