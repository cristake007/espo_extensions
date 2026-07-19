<div class="document-builder-editor" data-state="{{#if isLoading}}loading{{else}}{{#if isError}}error{{else}}ready{{/if}}{{/if}}">
    {{#if isLoading}}
    <section class="document-builder-editor__state document-builder-editor__state--loading" role="status" aria-live="polite">
        <span class="fas fa-spinner fa-spin" aria-hidden="true"></span>
        <span>{{translate 'editorLoading' category='messages' scope='DocumentBuilderTemplate'}}</span>
    </section>
    {{else}}
        {{#if isError}}
        <section class="document-builder-editor__state document-builder-editor__state--error" role="alert">
            <h2 class="document-builder-editor__focus-target" tabindex="-1">
                {{translate 'editorUnavailable' category='labels' scope='DocumentBuilderTemplate'}}
            </h2>
            <p>{{translate errorMessage category='messages' scope='DocumentBuilderTemplate'}}</p>
            <div class="btn-group">
                {{#if canRetry}}
                <button type="button" class="btn btn-primary" data-action="retry">
                    {{translate 'Retry'}}
                </button>
                {{/if}}
                <button type="button" class="btn btn-default" data-action="backToTemplate">
                    {{translate 'Back' category='actions' scope='DocumentBuilderTemplate'}}
                </button>
            </div>
        </section>
        {{else}}
        <header class="document-builder-editor__toolbar" aria-label="{{translate 'editorToolbar' category='labels' scope='DocumentBuilderTemplate'}}">
            <div class="document-builder-editor__identity">
                <button type="button" class="btn btn-default btn-sm" data-action="backToTemplate">
                    <span class="fas fa-arrow-left" aria-hidden="true"></span>
                    <span class="sr-only">{{translate 'Back' category='actions' scope='DocumentBuilderTemplate'}}</span>
                </button>
                <h2 class="document-builder-editor__focus-target" tabindex="-1">{{templateName}}</h2>
            </div>
            <div class="btn-group" role="group" aria-label="{{translate 'editorActions' category='labels' scope='DocumentBuilderTemplate'}}">
                <button type="button" class="btn btn-default btn-sm" data-action="undo" {{#unless canUndo}}disabled{{/unless}}>{{translate 'Undo' category='actions' scope='DocumentBuilderTemplate'}}</button>
                <button type="button" class="btn btn-default btn-sm" data-action="redo" {{#unless canRedo}}disabled{{/unless}}>{{translate 'Redo' category='actions' scope='DocumentBuilderTemplate'}}</button>
                <button type="button" class="btn btn-default btn-sm" disabled>{{translate 'Preview' category='actions' scope='DocumentBuilderTemplate'}}</button>
                <button type="button" class="btn btn-primary btn-sm" data-action="save" aria-keyshortcuts="Control+S Meta+S" {{#unless canSave}}disabled{{/unless}}>
                    {{#if isSaving}}
                        <span class="fas fa-spinner fa-spin" aria-hidden="true"></span>
                        {{translate 'Saving' category='labels' scope='DocumentBuilderTemplate'}}
                    {{else}}
                        {{translate 'Save'}}
                    {{/if}}
                </button>
                <button type="button" class="btn btn-success btn-sm" disabled>{{translate 'Publish' category='actions' scope='DocumentBuilderTemplate'}}</button>
            </div>
        </header>

        {{#if saveError}}
        <div class="alert alert-danger document-builder-editor__save-error" role="alert">
            {{translate saveError category='messages' scope='DocumentBuilderTemplate'}}
        </div>
        {{/if}}

        <main class="document-builder-editor__workspace">
            <aside class="document-builder-editor__left" aria-label="{{translate 'editorLibrary' category='labels' scope='DocumentBuilderTemplate'}}">
                <section class="document-builder-editor__panel">
                    <h3>{{translate 'Elements' category='labels' scope='DocumentBuilderTemplate'}}</h3>
                    <p class="text-muted">{{translate 'editorElementsPlaceholder' category='messages' scope='DocumentBuilderTemplate'}}</p>
                </section>
                <section class="document-builder-editor__panel">
                    <h3>{{translate 'Variables' category='labels' scope='DocumentBuilderTemplate'}}</h3>
                    <p class="text-muted">{{translate 'editorVariablesPlaceholder' category='messages' scope='DocumentBuilderTemplate'}}</p>
                </section>
            </aside>

            <section class="document-builder-editor__canvas-host" aria-label="{{translate 'Canvas' category='labels' scope='DocumentBuilderTemplate'}}">
                <div class="document-builder-editor__canvas-toolbar" role="group" aria-label="{{translate 'Zoom' category='labels' scope='DocumentBuilderTemplate'}}">
                    <button type="button" class="btn btn-default btn-sm" data-action="zoomOut" aria-label="{{translate 'Zoom Out' category='actions' scope='DocumentBuilderTemplate'}}">−</button>
                    <output>{{zoom}}%</output>
                    <button type="button" class="btn btn-default btn-sm" data-action="zoomIn" aria-label="{{translate 'Zoom In' category='actions' scope='DocumentBuilderTemplate'}}">+</button>
                    <button type="button" class="btn btn-default btn-sm" data-action="fitWidth">{{translate 'Fit Width' category='actions' scope='DocumentBuilderTemplate'}}</button>
                    <button type="button" class="btn btn-default btn-sm" data-action="fitPage">{{translate 'Fit Page' category='actions' scope='DocumentBuilderTemplate'}}</button>
                </div>
                <div class="document-builder-editor__canvas-scroll">
                    <div class="document-builder-editor__page" style="{{pageFrameStyle}}">
                        <div class="document-builder-editor__empty">
                            <span class="far fa-file-alt" aria-hidden="true"></span>
                            <h3>{{translate 'editorEmptyCanvas' category='messages' scope='DocumentBuilderTemplate'}}</h3>
                            <p class="text-muted">{{translate 'editorMechanicsPending' category='messages' scope='DocumentBuilderTemplate'}}</p>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="document-builder-editor__inspector" aria-label="{{translate 'Inspector' category='labels' scope='DocumentBuilderTemplate'}}">
                <h3>{{translate 'Page Settings' category='labels' scope='DocumentBuilderTemplate'}}</h3>
                <div class="form-group">
                    <label>{{translate 'Page Size' category='labels' scope='DocumentBuilderTemplate'}}</label>
                    <select class="form-control input-sm" data-page-setting="size">
                        {{#each pageSettings.pageSizeList}}
                        <option value="{{id}}" {{#if selected}}selected{{/if}}>{{label}}</option>
                        {{/each}}
                    </select>
                </div>
                <div class="form-group">
                    <label>{{translate 'Orientation' category='labels' scope='DocumentBuilderTemplate'}}</label>
                    <select class="form-control input-sm" data-page-setting="orientation">
                        <option value="portrait" {{#if pageSettings.portrait}}selected{{/if}}>{{translate 'Portrait' category='labels' scope='DocumentBuilderTemplate'}}</option>
                        <option value="landscape" {{#if pageSettings.landscape}}selected{{/if}}>{{translate 'Landscape' category='labels' scope='DocumentBuilderTemplate'}}</option>
                    </select>
                </div>
                <fieldset class="document-builder-editor__settings-grid">
                    <legend>{{translate 'Margins (mm)' category='labels' scope='DocumentBuilderTemplate'}}</legend>
                    <label>{{translate 'Top' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{pageSettings.page.margins.top.value}}" data-page-setting="marginTop" data-value-type="number"></label>
                    <label>{{translate 'Right' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{pageSettings.page.margins.right.value}}" data-page-setting="marginRight" data-value-type="number"></label>
                    <label>{{translate 'Bottom' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{pageSettings.page.margins.bottom.value}}" data-page-setting="marginBottom" data-value-type="number"></label>
                    <label>{{translate 'Left' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{pageSettings.page.margins.left.value}}" data-page-setting="marginLeft" data-value-type="number"></label>
                </fieldset>
                <div class="form-group"><label>{{translate 'Default Font' category='labels' scope='DocumentBuilderTemplate'}}<select class="form-control input-sm" data-page-setting="fontFamily">{{#each pageSettings.fontList}}<option value="{{name}}" {{#if selected}}selected{{/if}}>{{name}}</option>{{/each}}</select></label></div>
                <div class="document-builder-editor__settings-grid">
                    <label>{{translate 'Font Size' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="512" step="0.1" value="{{pageSettings.defaults.fontSize.value}}" data-page-setting="fontSize" data-value-type="number"></label>
                    <label>{{translate 'Line Height' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0.5" max="5" step="0.1" value="{{pageSettings.defaults.lineHeight}}" data-page-setting="lineHeight" data-value-type="number"></label>
                </div>
                <div class="form-group"><label>{{translate 'Text Color' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="color" value="{{pageSettings.defaults.color}}" data-page-setting="color"></label></div>
                <div class="document-builder-editor__settings-grid">
                    <label>{{translate 'Locale' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" value="{{pageSettings.defaults.locale}}" data-page-setting="locale"></label>
                    <label>{{translate 'Timezone' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" value="{{pageSettings.defaults.timezone}}" data-page-setting="timezone"></label>
                </div>
                <div class="form-group"><label>{{translate 'PDF Title Pattern' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" maxlength="255" value="{{pageSettings.titlePattern}}" data-page-setting="titlePattern"></label></div>
                <div class="form-group"><label>{{translate 'Filename Pattern' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" maxlength="255" value="{{pageSettings.filenamePattern}}" data-page-setting="filenamePattern"></label></div>
            </aside>
        </main>

        <footer class="document-builder-editor__status" role="status" aria-live="polite">
            <span>{{translateOption 'Draft' field='status' scope='DocumentBuilderTemplate'}}</span>
            <span>{{translate 'Revision' category='labels' scope='DocumentBuilderTemplate'}}: {{revision}}</span>
            <span>
                {{#if isSaving}}
                    {{translate 'editorSaving' category='messages' scope='DocumentBuilderTemplate'}}
                {{else}}
                    {{#if isReloading}}
                        {{translate 'editorReloading' category='messages' scope='DocumentBuilderTemplate'}}
                    {{else}}
                        {{#if isSaved}}
                            {{translate 'editorSaved' category='messages' scope='DocumentBuilderTemplate'}}
                        {{else}}
                            {{#if isDirty}}
                                {{translate 'editorUnsavedChanges' category='messages' scope='DocumentBuilderTemplate'}}
                            {{else}}
                                {{translate 'editorReady' category='messages' scope='DocumentBuilderTemplate'}}
                            {{/if}}
                        {{/if}}
                    {{/if}}
                {{/if}}
            </span>
        </footer>
        {{/if}}
    {{/if}}
</div>
