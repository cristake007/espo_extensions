<div class="page-header">
    <h3>{{titlu}}</h3>
    <p class="text-muted">{{explicatie}}</p>
</div>

<div class="panel panel-default">
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>{{angajat}}</th>
                    <th>{{dataAngajare}}</th>
                    <th>{{calendarLucru}}</th>
                </tr>
            </thead>
            <tbody data-role="randuri-angajati"></tbody>
        </table>
    </div>
</div>

<div class="text-right">
    <button class="btn btn-primary" data-action="salveaza" disabled>
        <span class="fas fa-save"></span> {{salvare}}
    </button>
</div>
