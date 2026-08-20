<div class="button-container">
    <div class="btn-group">
        <button class="btn btn-default btn-xs-wide" data-action="save">{{translate 'Save'}}</button>
        <button class="btn btn-default btn-xs-wide" data-action="cancel">{{translate 'Cancel'}}</button>
    </div>
    <div class="btn-group pull-right">
        <button class="btn btn-default" data-action="addManualHoliday">
            <span class="fas fa-plus fa-sm"></span>
            {{translate 'addManualHoliday' scope='Integration' category='labels'}}
        </button>
        <button class="btn btn-default" data-action="manageHolidays">
            <span class="fas fa-calendar-day fa-sm"></span>
            {{translate 'manageHolidays' scope='Integration' category='labels'}}
        </button>
        <button class="btn btn-primary" data-action="synchronize">
            <span class="fas fa-sync-alt fa-sm"></span>
            <span class="synchronize-label">{{translate 'synchronizeNow' scope='Integration' category='labels'}}</span>
        </button>
    </div>
</div>

<div class="row">
    <div class="col-sm-6">
        <div class="panel panel-default">
            <div class="panel-body panel-body-form">
                <div class="cell form-group" data-name="enabled">
                    <label class="control-label" data-name="enabled">
                        {{translate 'enabled' scope='Integration' category='fields'}}
                    </label>
                    <div class="field" data-name="enabled">{{{enabled}}}</div>
                </div>
                {{#each fieldDataList}}
                    <div class="cell form-group" data-name="{{name}}">
                        <label class="control-label" data-name="{{name}}">{{label}}</label>
                        <div class="field" data-name="{{name}}">{{{var name ../this}}}</div>
                    </div>
                {{/each}}
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        {{#if helpText}}
        <div class="well">
            {{complexText helpText}}
        </div>
        {{/if}}
    </div>
</div>
