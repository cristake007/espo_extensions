<div class="management-angajati condica-prezenta-lunara">
<div class="page-header">
    <div class="row">
        <div class="col-sm-5">
            <h3>{{titlu}}</h3>
        </div>
        <div class="col-sm-7 text-right">
            <span class="label {{#if cicluLunar.esteInchisa}}label-default{{else}}label-success{{/if}}">
                {{stareLuna}}
            </span>
            {{#if poateTrimiteRemindere}}
            <button type="button" class="btn btn-default" data-action="trimiteRemindere">
                <span class="fas fa-bell"></span> {{trimiteRemindere}}
            </button>
            {{/if}}
            {{#if poateExporta}}
            <a class="btn btn-default" href="{{urlExportXlsx}}">
                <span class="fas fa-file-excel"></span> {{descarcaXlsx}}
            </a>
            <a class="btn btn-default" href="{{urlExportPdf}}">
                <span class="fas fa-file-pdf"></span> {{descarcaPdf}}
            </a>
            {{/if}}
            <button type="button" class="btn btn-warning{{#unless poateInchideLuna}} hidden{{/unless}}" data-action="inchideLuna">
                <span class="fas fa-lock"></span> {{inchideLuna}}
            </button>
            <div class="btn-group" role="group">
                <button
                    type="button"
                    class="btn btn-default"
                    data-action="lunaAnterioara"
                    title="{{anterior}}"
                >
                    <span class="fas fa-chevron-left"></span>
                </button>
                <button type="button" class="btn btn-default" data-action="lunaCurenta">{{lunaCurenta}}</button>
                <button type="button" class="btn btn-default" data-action="lunaUrmatoare" title="{{urmator}}">
                    <span class="fas fa-chevron-right"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading text-center"><strong>{{lunaAfisata}}</strong></div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-3"><strong>{{etichete.completare}}:</strong> <span data-role="procent-completare">{{statistici.procentCompletare}}</span>%</div>
            <div class="col-sm-3"><strong>{{etichete.lipsa}}:</strong> <span data-role="prezente-lipsa">{{statistici.zileLipsa}}</span></div>
            <div class="col-sm-3"><strong>{{etichete.calendare}}:</strong> {{statistici.calendareConfigurate}}/{{statistici.numarAngajati}}</div>
            <div class="col-sm-3"><strong>{{etichete.angajati}}:</strong> {{statistici.numarAngajati}}</div>
        </div>
        <div class="small text-muted top-space">
            <strong data-role="stare-completitudine">{{etichete.stareCompletitudine}}</strong>
            <span data-role="motive-blocare">{{#if etichete.motiveBlocare.length}}— {{#each etichete.motiveBlocare}}{{#unless @first}}; {{/unless}}{{this}}{{/each}}{{/if}}</span>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="table-responsive condica-prezenta-container">
        <table class="table table-bordered table-hover condica-prezenta-tabel">
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
                        {{numeAngajat}}
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
                    <td class="condica-prezenta-celula{{#if esteLipsa}} danger{{/if}}">
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
</div>
</div>
