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
                <div class="document-builder-editor__empty">
                    <span class="far fa-file-alt" aria-hidden="true"></span>
                    <h3>{{translate 'editorEmptyCanvas' category='messages' scope='DocumentBuilderTemplate'}}</h3>
                    <p class="text-muted">{{translate 'editorMechanicsPending' category='messages' scope='DocumentBuilderTemplate'}}</p>
                </div>
            </section>

            <aside class="document-builder-editor__inspector" aria-label="{{translate 'Inspector' category='labels' scope='DocumentBuilderTemplate'}}">
                <h3>{{translate 'Inspector' category='labels' scope='DocumentBuilderTemplate'}}</h3>
                <p class="text-muted">{{translate 'editorInspectorPlaceholder' category='messages' scope='DocumentBuilderTemplate'}}</p>
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
