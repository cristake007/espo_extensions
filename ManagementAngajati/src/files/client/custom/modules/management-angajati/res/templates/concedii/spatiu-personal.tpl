<div class="page-header"><h3>{{titlu}}</h3></div>
{{#unless disponibil}}
<div class="record">
    <div class="panel panel-default">
        <div class="panel-heading"><h4 class="panel-title">{{indisponibilTitlu}}</h4></div>
        <div class="panel-body">
            <p>{{indisponibilExplicatie}}</p>
            {{#if poateAfisaActiune}}
            <a class="btn btn-primary" href="{{urlActiune}}">
                {{etichetaActiune}}
            </a>
            {{/if}}
        </div>
    </div>
</div>
{{else}}
{{#if sold}}
<div class="row">
    <div class="col-sm-4"><div class="well"><strong>{{soldDisponibil}}</strong><br><span class="h3">{{sold.disponibile}}</span></div></div>
    <div class="col-sm-4"><div class="well"><strong>{{soldRezervat}}</strong><br><span class="h3">{{sold.rezervate}}</span></div></div>
    <div class="col-sm-4"><div class="well"><strong>{{soldConsumat}}</strong><br><span class="h3">{{sold.consumate}}</span></div></div>
</div>
{{/if}}
<div class="panel panel-default">
    <div class="panel-heading"><strong>{{cerereNoua}}</strong></div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-3 form-group"><label>{{dataInceputEticheta}}</label><div class="field" data-name="dataInceput">{{{dataInceput}}}</div></div>
            <div class="col-sm-3 form-group"><label>{{dataSfarsitEticheta}}</label><div class="field" data-name="dataSfarsit">{{{dataSfarsit}}}</div></div>
            <div class="col-sm-4 form-group"><label>{{descriereEticheta}}</label><input class="form-control" data-name="descriere"></div>
            <div class="col-sm-2 form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block" data-action="trimiteCerere"><span class="fas fa-paper-plane"></span> {{trimite}}</button></div>
        </div>
    </div>
</div>
<div class="panel panel-default"><div class="table-responsive"><table class="table table-striped">
<thead><tr><th>{{perioada}}</th><th>{{zile}}</th><th>{{stare}}</th><th></th></tr></thead>
<tbody>{{#each cereri}}<tr><td>{{dataInceputAfisata}} – {{dataSfarsitAfisata}}{{#each documente}}<br><a href="{{url}}" target="_blank"><span class="fas fa-file-word"></span> {{nume}}</a>{{/each}}</td><td>{{numarZile}}</td><td>{{stareAfisata}}</td><td class="text-right">{{#if poateGeneraDocumente}}<button class="btn btn-default btn-sm" data-action="genereazaDocumente" data-id="{{id}}"><span class="fas fa-file-word"></span> {{../genereazaDocumente}}</button> {{/if}}{{#if poateSolicitaAnulare}}<button class="btn btn-default btn-sm" data-action="anuleazaCerere" data-id="{{id}}" data-stare="{{stare}}">{{#if esteInAsteptare}}{{../retrageCerere}}{{else}}{{../solicitaAnulare}}{{/if}}</button>{{/if}}</td></tr>{{/each}}</tbody>
</table></div></div>
{{/unless}}
