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
                    <button type="button" class="document-builder-editor__library-item" data-action="addFlowSection" data-library-type="flow-section" draggable="true">
                        <span class="fas fa-layer-group" aria-hidden="true"></span>
                        {{translate 'Flow Section' category='labels' scope='DocumentBuilderTemplate'}}
                    </button>
                    <button type="button" class="document-builder-editor__library-item" data-action="addFlowContainer" data-library-type="flow-container" draggable="true" aria-disabled="{{#if canAddFlowContainer}}false{{else}}true{{/if}}">
                        <span class="far fa-square" aria-hidden="true"></span>
                        {{translate 'Flow Container' category='labels' scope='DocumentBuilderTemplate'}}
                    </button>
                    <button type="button" class="document-builder-editor__library-item" data-action="addHeading" data-library-type="heading" draggable="true" aria-disabled="{{#if canAddFlowContainer}}false{{else}}true{{/if}}">{{translate 'Heading' category='labels' scope='DocumentBuilderTemplate'}}</button>
                    <button type="button" class="document-builder-editor__library-item" data-action="addStaticText" data-library-type="static-text" draggable="true" aria-disabled="{{#if canAddFlowContainer}}false{{else}}true{{/if}}">{{translate 'Static Text' category='labels' scope='DocumentBuilderTemplate'}}</button>
                    <button type="button" class="document-builder-editor__library-item" data-action="addParagraph" data-library-type="paragraph" draggable="true" aria-disabled="{{#if canAddFlowContainer}}false{{else}}true{{/if}}">{{translate 'Paragraph' category='labels' scope='DocumentBuilderTemplate'}}</button>
                    <button type="button" class="document-builder-editor__library-item" data-action="addDivider" data-library-type="divider" draggable="true" aria-disabled="{{#if canAddFlowContainer}}false{{else}}true{{/if}}">{{translate 'Divider' category='labels' scope='DocumentBuilderTemplate'}}</button>
                    <button type="button" class="document-builder-editor__library-item" data-action="addSpacer" data-library-type="spacer" draggable="true" aria-disabled="{{#if canAddFlowContainer}}false{{else}}true{{/if}}">{{translate 'Spacer' category='labels' scope='DocumentBuilderTemplate'}}</button>
                    <button type="button" class="document-builder-editor__library-item" data-action="addPageBreak" data-library-type="page-break" draggable="true" aria-disabled="{{#if canAddFlowContainer}}false{{else}}true{{/if}}">{{translate 'Page Break' category='labels' scope='DocumentBuilderTemplate'}}</button>
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
                        {{#if hasFlowRows}}
                        <div class="document-builder-editor__flow-tree">
                            {{#each flowRows}}
                            <div class="document-builder-editor__drop" data-flow-drop="before" data-drop-region="{{region}}" data-drop-parent="{{parentId}}" data-drop-index="{{index}}" aria-hidden="true"></div>
                            <article class="document-builder-editor__flow-node {{#if selected}}is-selected{{/if}}" style="{{flowStyle}}" data-node-id="{{id}}" draggable="true">
                                <button type="button" class="document-builder-editor__flow-select" data-action="selectFlowNode" data-node-id="{{id}}" aria-pressed="{{#if selected}}true{{else}}false{{/if}}">
                                    <span class="fas {{#if isSection}}fa-layer-group{{else}}fa-square{{/if}}" aria-hidden="true"></span>
                                    {{translate label category='labels' scope='DocumentBuilderTemplate'}}
                                </button>
                                {{#if isHeading}}<div class="document-builder-editor__content document-builder-editor__content--heading" data-rich-content-id="{{id}}"></div>{{/if}}
                                {{#if isStaticText}}<div class="document-builder-editor__content" data-rich-content-id="{{id}}"></div>{{/if}}
                                {{#if isParagraph}}<div class="document-builder-editor__content document-builder-editor__content--paragraph" data-rich-content-id="{{id}}"></div>{{/if}}
                                {{#if isDivider}}<div class="document-builder-editor__divider document-builder-editor__divider--{{dividerOrientation}}" style="{{dividerStyle}}" aria-hidden="true"></div>{{/if}}
                                {{#if isSpacer}}<div class="document-builder-editor__spacer-marker" aria-label="{{translate 'Spacer' category='labels' scope='DocumentBuilderTemplate'}}"></div>{{/if}}
                                {{#if isPageBreak}}<div class="document-builder-editor__page-break" role="separator"><span>{{translate 'Manual Page Break' category='labels' scope='DocumentBuilderTemplate'}}</span></div>{{/if}}
                                {{#if canContain}}
                                <div class="document-builder-editor__drop document-builder-editor__drop--inside" data-flow-drop="inside" data-drop-parent="{{id}}" data-drop-index="" aria-label="{{translate 'Drop Inside' category='labels' scope='DocumentBuilderTemplate'}}"></div>
                                {{/if}}
                            </article>
                            {{/each}}
                        </div>
                        {{else}}
                        <div class="document-builder-editor__empty" data-flow-drop="root" data-drop-region="sections" data-drop-parent="" data-drop-index="">
                            <span class="far fa-file-alt" aria-hidden="true"></span>
                            <h3>{{translate 'editorEmptyCanvas' category='messages' scope='DocumentBuilderTemplate'}}</h3>
                            <p class="text-muted">{{translate 'editorFlowEmpty' category='messages' scope='DocumentBuilderTemplate'}}</p>
                        </div>
                        {{/if}}
                        <div class="document-builder-editor__drop document-builder-editor__drop--root" data-flow-drop="root" data-drop-region="sections" data-drop-parent="" data-drop-index="" aria-label="{{translate 'Drop Section' category='labels' scope='DocumentBuilderTemplate'}}"></div>
                    </div>
                </div>
            </section>

            <aside class="document-builder-editor__inspector" aria-label="{{translate 'Inspector' category='labels' scope='DocumentBuilderTemplate'}}">
                {{#if selectedFlowNode}}
                <nav class="document-builder-editor__breadcrumbs" aria-label="{{translate 'Hierarchy' category='labels' scope='DocumentBuilderTemplate'}}">
                    {{#each flowBreadcrumbs}}
                    <button type="button" class="btn btn-link btn-xs" data-action="selectBreadcrumb" data-node-id="{{id}}" {{#if current}}aria-current="page"{{/if}}>{{translate label category='labels' scope='DocumentBuilderTemplate'}}</button>
                    {{/each}}
                </nav>
                <h3>{{translate selectedFlowNode.label category='labels' scope='DocumentBuilderTemplate'}}</h3>
                <div class="btn-group btn-group-sm document-builder-editor__flow-actions">
                    <button type="button" class="btn btn-default" data-action="moveFlowUp">{{translate 'Move Up' category='actions' scope='DocumentBuilderTemplate'}}</button>
                    <button type="button" class="btn btn-default" data-action="moveFlowDown">{{translate 'Move Down' category='actions' scope='DocumentBuilderTemplate'}}</button>
                    <button type="button" class="btn btn-danger" data-action="removeFlowNode">{{translate 'Remove'}}</button>
                </div>
                {{#if selectedFlowNode.isBasicFlow}}
                {{#if selectedFlowNode.isDivider}}
                <div class="form-group"><label>{{translate 'Orientation' category='labels' scope='DocumentBuilderTemplate'}}<select class="form-control input-sm" data-basic-flow-setting="orientation"><option value="horizontal" {{#if selectedFlowNode.horizontal}}selected{{/if}}>Horizontal</option><option value="vertical" {{#if selectedFlowNode.vertical}}selected{{/if}}>Vertical</option></select></label></div>
                <div class="form-group"><label>{{translate 'Line Style' category='labels' scope='DocumentBuilderTemplate'}}<select class="form-control input-sm" data-basic-flow-setting="style"><option value="solid" {{#if selectedFlowNode.solid}}selected{{/if}}>Solid</option><option value="dashed" {{#if selectedFlowNode.dashed}}selected{{/if}}>Dashed</option><option value="dotted" {{#if selectedFlowNode.dotted}}selected{{/if}}>Dotted</option><option value="double" {{#if selectedFlowNode.double}}selected{{/if}}>Double</option></select></label></div>
                <div class="form-group"><label>{{translate 'Color' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="color" value="{{selectedFlowNode.color}}" data-basic-flow-setting="color"></label></div>
                <div class="document-builder-editor__settings-grid">
                    <label>{{translate 'Thickness (mm)' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0.1" max="20" step="0.1" value="{{selectedFlowNode.thickness.value}}" data-basic-flow-setting="thickness"></label>
                    <label>{{translate 'Length (mm)' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="1" max="2000" step="0.1" value="{{selectedFlowNode.length.value}}" data-basic-flow-setting="length"></label>
                </div>
                {{/if}}
                {{#if selectedFlowNode.isSpacer}}
                <div class="form-group"><label>{{translate 'Height (mm)' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0.1" max="500" step="0.1" value="{{selectedFlowNode.height.value}}" data-basic-flow-setting="height"></label></div>
                {{/if}}
                {{#if selectedFlowNode.isPageBreak}}<p class="text-muted">{{translate 'Page Break Help' category='messages' scope='DocumentBuilderTemplate'}}</p>{{/if}}
                {{else}}
                {{#if selectedFlowNode.isContent}}
                <div class="form-group"><label>{{translate 'Content' category='labels' scope='DocumentBuilderTemplate'}}<textarea class="form-control" maxlength="10000" rows="8" data-content-setting="text">{{selectedFlowNode.plainText}}</textarea></label></div>
                {{#unless selectedFlowNode.isStaticText}}
                <div class="btn-group btn-group-sm" role="group" aria-label="{{translate 'Formatting' category='labels' scope='DocumentBuilderTemplate'}}">
                    <button type="button" class="btn btn-default" data-rich-mark="bold"><strong>B</strong></button>
                    <button type="button" class="btn btn-default" data-rich-mark="italic"><em>I</em></button>
                    <button type="button" class="btn btn-default" data-rich-mark="underline"><u>U</u></button>
                    <button type="button" class="btn btn-default" data-action="addInlineVariable">{{translate 'Variable' category='labels' scope='DocumentBuilderTemplate'}}</button>
                </div>
                <div class="form-group"><label>{{translate 'Text Color' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="color" value="#000000" data-rich-color></label></div>
                {{/unless}}
                {{#if selectedFlowNode.isHeading}}
                <div class="form-group"><label>{{translate 'Heading Level' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="1" max="6" value="{{selectedFlowNode.level}}" data-content-setting="level"></label></div>
                <div class="checkbox"><label><input type="checkbox" data-content-setting="keepWithNext" {{#if selectedFlowNode.keepWithNext}}checked{{/if}}> {{translate 'Keep With Next' category='labels' scope='DocumentBuilderTemplate'}}</label></div>
                {{/if}}
                {{#if selectedFlowNode.isParagraph}}
                <div class="form-group"><label>{{translate 'Alignment' category='labels' scope='DocumentBuilderTemplate'}}<select class="form-control input-sm" data-content-setting="alignment"><option value="start">Start</option><option value="center">Center</option><option value="end">End</option><option value="justify">Justify</option></select></label></div>
                {{/if}}
                {{else}}
                <fieldset class="document-builder-editor__settings-grid">
                    <legend>{{translate 'Margin (mm)' category='labels' scope='DocumentBuilderTemplate'}}</legend>
                    <label>{{translate 'Top' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.margin.top.value}}" data-flow-setting="marginTop"></label>
                    <label>{{translate 'Right' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.margin.right.value}}" data-flow-setting="marginRight"></label>
                    <label>{{translate 'Bottom' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.margin.bottom.value}}" data-flow-setting="marginBottom"></label>
                    <label>{{translate 'Left' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.margin.left.value}}" data-flow-setting="marginLeft"></label>
                </fieldset>
                <fieldset class="document-builder-editor__settings-grid">
                    <legend>{{translate 'Padding (mm)' category='labels' scope='DocumentBuilderTemplate'}}</legend>
                    <label>{{translate 'Top' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.padding.top.value}}" data-flow-setting="paddingTop"></label>
                    <label>{{translate 'Right' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.padding.right.value}}" data-flow-setting="paddingRight"></label>
                    <label>{{translate 'Bottom' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.padding.bottom.value}}" data-flow-setting="paddingBottom"></label>
                    <label>{{translate 'Left' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.padding.left.value}}" data-flow-setting="paddingLeft"></label>
                </fieldset>
                <div class="form-group"><label>{{translate 'Minimum Height (mm)' category='labels' scope='DocumentBuilderTemplate'}}<input class="form-control input-sm" type="number" min="0" max="2000" step="0.1" value="{{selectedFlowNode.minHeight.value}}" data-flow-setting="minHeight"></label></div>
                <div class="checkbox"><label><input type="checkbox" data-flow-setting="keepTogether" {{#if selectedFlowNode.keepTogether}}checked{{/if}}> {{translate 'Keep Together' category='labels' scope='DocumentBuilderTemplate'}}</label></div>
                {{#if selectedFlowNode.isSection}}<div class="checkbox"><label><input type="checkbox" data-flow-setting="startNewPage" {{#if selectedFlowNode.startNewPage}}checked{{/if}}> {{translate 'Start New Page' category='labels' scope='DocumentBuilderTemplate'}}</label></div>{{/if}}
                {{/if}}
                {{/if}}
                {{else}}
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
                {{/if}}
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
