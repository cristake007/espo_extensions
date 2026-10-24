<div class="page-header">
    <h3>{{titlu}}</h3>
    <p class="text-muted">{{explicatie}}</p>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>{{luniEditabile}}</strong></div>
    <div class="panel-body">
        <div class="form-group">
            <input
                class="form-control"
                type="number"
                min="0"
                max="120"
                step="1"
                value="{{numarLuniAnterioareEditabile}}"
                data-camp="numarLuniAnterioareEditabile"
            >
            <p class="help-block">{{explicatieLuniEditabile}}</p>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>{{modRemote}}</strong></div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-12">
                <select class="form-control" data-camp="modAplicareRemote">
                    <option value="curent"{{#ifEqual modAplicareRemote 'curent'}} selected{{/ifEqual}}>{{modCurent}}</option>
                    <option value="interval"{{#ifEqual modAplicareRemote 'interval'}} selected{{/ifEqual}}>{{modInterval}}</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>{{angajat}}</strong></div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>{{angajat}}</th>
                    <th>{{remote}}</th>
                    <th>{{cic}}</th>
                </tr>
            </thead>
            <tbody data-role="randuri-angajati"></tbody>
        </table>
    </div>
</div>

<div class="panel panel-danger">
    <div class="panel-heading"><strong>{{resetareTitlu}}</strong></div>
    <div class="panel-body">
        <div class="alert alert-danger">
            <strong>{{resetareAvertisment}}</strong>
            <div>{{resetareExplicatie}}</div>
        </div>
        {{#if previzualizareReset}}
        <dl class="dl-horizontal">
            <dt>{{eticheteReset.prezente}}</dt>
            <dd>{{previzualizareReset.prezente}}</dd>
            <dt>{{eticheteReset.stariLuna}}</dt>
            <dd>{{previzualizareReset.stariLuna}}</dd>
            <dt>{{eticheteReset.remindere}}</dt>
            <dd>{{previzualizareReset.remindere}}</dd>
            <dt>{{eticheteReset.configuratii}}</dt>
            <dd>{{previzualizareReset.configuratiiCicRemote}}</dd>
            <dt>{{eticheteReset.dateAngajare}}</dt>
            <dd>{{previzualizareReset.dateAngajare}}</dd>
            <dt>{{eticheteReset.calendare}}</dt>
            <dd>{{previzualizareReset.calendareLucruPastrate}}</dd>
        </dl>
        {{/if}}
        <div class="form-group">
            <label class="control-label">{{confirmareReset}}</label>
            <input
                type="text"
                class="form-control"
                data-camp="confirmareReset"
                autocomplete="off"
                placeholder="{{expresieConfirmare}}"
            >
        </div>
        <button type="button" class="btn btn-danger" data-action="reseteazaDateTest" disabled>
            <span class="fas fa-trash-alt"></span> {{resetareTitlu}}
        </button>
    </div>
</div>

<div class="text-right">
    <button class="btn btn-primary" data-action="salveaza" disabled>
        <span class="fas fa-save"></span> {{salvare}}
    </button>
</div>
