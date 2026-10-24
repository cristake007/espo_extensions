<div class="page-header"><h3>{{titlu}}</h3></div>
<div class="panel panel-default"><div class="panel-heading"><strong>{{aprobatoriEticheta}}</strong></div><div class="panel-body">
    <div class="form-group"><label>{{aprobatoriEticheta}}</label><div class="field" data-name="aprobatoriIds">{{{aprobatoriIds}}}</div></div>
    <div class="form-group"><label>{{ambiiEticheta}}</label><div class="field" data-name="necesitaAmbiiManageri">{{{necesitaAmbiiManageri}}}</div></div>
    <div class="form-group"><label>{{acordareAutomataEticheta}}</label><div class="field" data-name="acordareAnualaAutomata">{{{acordareAnualaAutomata}}}</div></div>
    <p class="help-block">{{acordareAutomataExplicatie}}</p>
    <button class="btn btn-primary pull-right" data-action="salveazaSetari">{{salvare}}</button>
</div></div>
<div class="panel panel-default"><div class="panel-body"><p class="text-muted">{{explicatieInitializare}}</p></div><div class="table-responsive"><table class="table table-striped">
<thead><tr><th>{{angajat}}</th><th>{{zileAnuale}}</th><th>{{soldCurent}}</th><th>{{soldReportat}}</th><th>{{dataAcordare}}</th><th></th></tr></thead>
<tbody data-role="profiluri"></tbody></table></div></div>
<div class="panel panel-danger">
    <div class="panel-heading"><strong>{{resetareTitlu}}</strong></div>
    <div class="panel-body">
        <p>{{resetareExplicatie}}</p>
        <dl class="dl-horizontal">
            <dt>{{profiluriEticheta}}</dt><dd>{{resetare.profiluri}}</dd>
            <dt>{{cereriEticheta}}</dt><dd>{{resetare.cereri}}</dd>
            <dt>{{registruEticheta}}</dt><dd>{{resetare.registru}}</dd>
        </dl>
        <div class="form-group">
            <label>{{confirmareReset}}</label>
            <input class="form-control" data-camp="confirmareResetConcedii" autocomplete="off">
        </div>
        <button class="btn btn-danger" data-action="reseteazaDateTest">{{resetareTitlu}}</button>
    </div>
</div>
