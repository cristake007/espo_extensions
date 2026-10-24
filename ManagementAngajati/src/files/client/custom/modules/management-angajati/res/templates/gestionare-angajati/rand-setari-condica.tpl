<td>
    <strong>{{numeAngajat}}</strong>
    <div class="small {{#if dataAngajareLipsa}}text-danger{{else}}text-muted{{/if}}">
        {{dataAngajareEticheta}}: {{dataAngajareAfisata}}
    </div>
    {{#unless areCalendarLucru}}
    <div class="text-warning">{{faraCalendar}}</div>
    {{/unless}}
</td>
<td>
    <div class="field" data-name="remoteActiv">{{{remoteActiv}}}</div>
    <div data-role="campuri-remote-interval"{{#unless modRemoteInterval}} class="hidden"{{/unless}}>
        <div class="form-group">
            <label>{{dataInceput}}</label>
            <div class="field" data-name="dataInceputRemote">{{{dataInceputRemote}}}</div>
        </div>
        <div class="form-group">
            <label>{{dataSfarsit}}</label>
            <div class="field" data-name="dataSfarsitRemote">{{{dataSfarsitRemote}}}</div>
        </div>
    </div>
</td>
<td>
    <div class="field" data-name="cicActiv">{{{cicActiv}}}</div>
    <div class="form-group">
        <label>{{dataInceput}}</label>
        <div class="field" data-name="dataInceputCic">{{{dataInceputCic}}}</div>
    </div>
    <div class="form-group">
        <label>{{dataSfarsit}}</label>
        <div class="field" data-name="dataSfarsitCic">{{{dataSfarsitCic}}}</div>
    </div>
</td>
