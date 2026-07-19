# Phase 00: EspoCRM 10.0.0 runtime contract

## Status and scope

This reference is the source contract for Document Builder integration work. It records what is available in the verified EspoCRM 10.0.0 baseline; it does not add application functionality or certify another EspoCRM release.

The implementation plan supersedes the PRD's older EspoCRM 10.3 references. Verified baseline: **EspoCRM 10.0.0**. The product owner approved manifest constraint `>=10.0.0`; later releases admitted by that unbounded constraint still require separate source and runtime validation before they are certified.

## Pinned upstream source

| Item | Pinned value |
|---|---|
| Repository | [`espocrm/espocrm`](https://github.com/espocrm/espocrm) |
| Release tag | [`10.0.0`](https://github.com/espocrm/espocrm/releases/tag/10.0.0) |
| Annotated tag object | `debc6b75bd9177259fb14b99cef93d8cd5d88c5b` |
| Peeled source commit | [`2cde9d980f84cfc3caa1adf3275a0817e1e49bfa`](https://github.com/espocrm/espocrm/commit/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa) |
| Version declaration | [`package.json`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/package.json#L1-L5) |
| PHP runtime range | `>=8.3.0 <8.6.0`, from [`composer.json`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/composer.json#L6-L10) |

Every GitHub source link below is pinned to the peeled commit, not a moving branch. EspoCRM documentation pages are not versioned; they are explanatory references only. If documentation and pinned source disagree, the pinned 10.0.0 source wins.

## Non-production runtime gate

`/opt/crm.cursurituv.ro` is production. Development installation, extension upgrade or uninstall, rebuild, cache clearing, test-record or fixture creation, experimental API/PDF/job execution, and cleanup are **PROHIBITED** there.

Runtime validation may occur only after the product owner explicitly supplies a separate non-production EspoCRM instance. Before any runtime check, confirm that the instance reports core version `10.0.0`, record its path and URL, and confirm that its data is disposable or backed up. Until then, all install, rebuild, API, ACL, storage, queue, and PDF smoke checks remain pending.

The official commands are `bin/command app-info --core-version`, `php rebuild.php`, and `php clear_cache.php`; recording them here is not authorization to run them. See [console commands](https://docs.espocrm.com/administration/commands/) and [extension administration](https://docs.espocrm.com/administration/extensions/).

## Integration contracts

### 1. Module and client loading

- The package copies files relative to the EspoCRM root. Backend module code belongs under `custom/Espo/Modules/DocumentBuilder/`; Composer maps `Espo\Modules\` to `custom/Espo/Modules/` in [`composer.json`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/composer.json#L78-L86).
- Module discovery enumerates `custom/Espo/Modules/*` and reads optional module parameters only from `Resources/module.json`; see [`Module`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Utils/Module.php#L39-L54) and its [custom-module discovery](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Utils/Module.php#L150-L169). `Resources/metadata/app/module.json` is not the module descriptor.
- The backend directory, namespace segment, and module name are case-sensitive and must all be `DocumentBuilder`. The frontend module remains kebab-case at `client/custom/modules/document-builder/` and uses IDs such as `document-builder:views/...`.
- The client loader maps external module IDs to `client/custom/modules/{module}/src/...`. When `Resources/module.json` declares `"jsTranspiled": true`, it instead maps to `lib/transpiled/src/...`; see the [`client loader`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/client/src/loader.js#L237-L264) and [`ClientManager`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Utils/ClientManager.php#L439-L452).

Supporting documentation: [modules](https://docs.espocrm.com/development/modules/) and [getting started](https://docs.espocrm.com/development/how-to-start/).

### 2. Routes and API actions

- Module routes belong in `custom/Espo/Modules/DocumentBuilder/Resources/routes.json`. The route loader reads module `Resources/routes.json` files and caches the unified result; see [`Route::unify`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Utils/Route.php#L92-L145).
- New endpoints use `actionClassName`, not a custom controller. The action implements `Espo\Core\Api\Action::process(Request): Response`; see [`Action`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Api/Action.php#L38-L55).
- Actions are constructed through `InjectableFactory`. Authentication is required unless a route explicitly sets `noAuth`; see [`RouteProcessor`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Api/RouteProcessor.php#L96-L126) and [action construction](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Api/RouteProcessor.php#L142-L176). Document Builder has no approved unauthenticated action, so `noAuth` must not be used without a later explicit product decision.
- Route changes require cache clearing or rebuild before they become authoritative in a runtime.

Supporting documentation: [API actions](https://docs.espocrm.com/development/api-action/) and [API overview](https://docs.espocrm.com/development/api/).

### 3. Dependency injection and bindings

- Constructor injection is the default. `InjectableFactory` resolves explicit bindings first, then a matching container service by parameter name and type, then a constructible class; see [`InjectableFactory`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/InjectableFactory.php#L46-L69) and its [resolution order](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/InjectableFactory.php#L211-L277).
- Ordinary Document Builder services remain regular injectable classes. A container service is added only when shared singleton lifetime is actually required; definitions belong in `Resources/metadata/app/containerServices.json`.
- Interface bindings belong in `Espo\Modules\DocumentBuilder\Binding`. Module bindings are loaded in module order by [`EspoBindingLoader`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Binding/EspoBindingLoader.php#L35-L76).
- Product code must request concrete dependencies in constructors rather than using `Container` as a service locator.

Supporting documentation: [backend dependency injection](https://docs.espocrm.com/development/di/).

### 4. Metadata, resources, and rebuild

- Module metadata belongs below `Resources/metadata/{section}/{name}.json`; layouts, i18n, fonts, and routes remain in their corresponding `Resources` directories.
- Metadata is merged from core, ordered modules, then `custom/Espo/Custom`; see [`Unifier`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Utils/File/Unifier.php#L74-L100). Array paths declared by the metadata builder are append-merged; see [`Metadata\Builder`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Utils/Metadata/Builder.php#L37-L60).
- Document Builder-owned entity metadata belongs in the module. Overrides of native EspoCRM entities belong under `custom/Espo/Custom/Resources/...` only when a supported module-local extension point cannot express the requirement.
- Metadata and routes are cached. Packaging installs run rebuild automatically, while source-only development changes require the appropriate rebuild or cache clear on the approved test instance.

Supporting documentation: [metadata](https://docs.espocrm.com/development/metadata/), [entity definitions](https://docs.espocrm.com/development/metadata/entity-defs/), and [client definitions](https://docs.espocrm.com/development/metadata/client-defs/).

### 5. ACL composition

- Inject `Espo\Core\Acl`, which is the current-user access-checking facade; do not instantiate an ACL manager. Use `checkScope`, `checkEntity`, `checkField`, and `checkLink` for their corresponding boundaries. The 10.0.0 signatures are pinned in [`Acl`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Acl.php#L111-L167) and its [field/link checks](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Acl.php#L229-L309).
- `checkLink` is available from EspoCRM 10.0.0, so no compatibility shim is needed for the pinned target.
- A successful Document Builder action-permission check never replaces source scope, record, field, or link ACL. All applicable checks compose, and a denial must not disclose the forbidden name or value.
- Extension entity ACL configuration belongs in module `Resources/metadata/aclDefs` and `Resources/metadata/entityAcl` as required by the entity contract. Data retrieval must use Espo ORM/record/select services with ACL-aware policy code, never direct SQL.

Supporting documentation: [ACL](https://docs.espocrm.com/development/acl/), [ACL definitions](https://docs.espocrm.com/development/metadata/acl-defs/), and [entity ACL](https://docs.espocrm.com/development/metadata/entity-acl/).

### 6. Attachments and file storage

- Persist files as `Espo\Entities\Attachment` records and use Espo's configured storage abstraction. `FileStorage\Manager` provides stream/content read, write, size, existence, local-path, and unlink operations; see [`Manager`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/FileStorage/Manager.php#L40-L145).
- For generated bytes, create an Attachment through the Attachment repository, set its name, MIME type, parent/related ownership fields, role, and `contents`, then save it. The repository selects the configured default storage, derives size, and writes contents through `FileStorage\Manager`; see [`Repositories\Attachment`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Repositories/Attachment.php#L43-L85). Do not write directly to `data/upload`.
- Download/read access uses the record service and Attachment access rules; see [`Attachment\Service`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Tools/Attachment/Service.php#L40-L78).
- Removing the last Attachment reference removes the stored file through an after-remove hook; see [`RemoveFile`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Hooks/Attachment/RemoveFile.php#L41-L76). Therefore uninstall and retention code must preserve the Attachment records backing generated PDFs, media, and snapshots.

Supporting documentation: [attachments and file storage](https://docs.espocrm.com/development/attachments/).

### 7. Queued jobs

- A queued Document Builder job implements `Espo\Core\Job\Job` and `run(Job\Data $data): void`; see [`Job`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Job/Job.php#L34-L43). Constructor dependencies are injected when the job runs.
- Schedule one-off work through an injected `JobSchedulerFactory`: call `create()`, `setClassName(...)`, optionally `setData(...)`, choose at most one of `setQueue(...)` or `setGroup(...)`, then `schedule()`. The enforced contract is in [`JobScheduler`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Job/JobScheduler.php#L43-L103) and [`schedule`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Job/JobScheduler.php#L139-L192).
- Put durable batch state, progress, cancellation, retry, and idempotency in Document Builder entities/services. Job payloads contain identifiers and bounded scalar data, not rendered documents or unbounded row sets.
- Actual execution depends on a correctly configured EspoCRM cron/daemon. That operational check remains gated on the non-production runtime.

Supporting documentation: [jobs](https://docs.espocrm.com/development/jobs/).

### 8. PDF services

- EspoCRM 10.0.0 requires `dompdf/dompdf ^3.1` and locks Dompdf `v3.1.5`; see [`composer.json`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/composer.json#L37-L49) and [`composer.lock`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/composer.lock#L1139-L1198). Document Builder adds no PDF dependency.
- Espo's native `Pdf\Builder` requires an Espo PDF `Template` and engine name and returns a native `PrinterController`; it is not a generic arbitrary-HTML renderer. See [`Pdf\Builder`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Tools/Pdf/Builder.php#L35-L73).
- The Dompdf implementation disables JavaScript, configures font caches/chroots, and sets paper size/orientation in [`DompdfInitializer`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Tools/Pdf/Dompdf/DompdfInitializer.php#L43-L104). Its public initializer is also coupled to the native Template abstraction.
- Document Builder therefore keeps its approved renderer adapter boundary. Phase 04 must prove whether the adapter can reuse the initializer safely or must construct Dompdf with equivalent validated restrictions. Remote resources remain disabled either way. The adapter returns PDF bytes/stream and never queries the database.

Supporting documentation: [PDF engine metadata](https://docs.espocrm.com/development/metadata/app-pdf-engines/) and [printing to PDF](https://docs.espocrm.com/user-guide/printing-to-pdf/).

### 9. Client library loading

- Libraries registered in `metadata > app > jsLibs` are exposed to the client loader. Load only registered IDs through imports/AMD or `Espo.loader.requirePromise('lib!name')`; the alias map is built by [`LoaderParamsProvider`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Utils/Client/LoaderParamsProvider.php#L34-L65), and `lib!` resolution is implemented by the [`client loader`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/client/src/loader.js#L562-L595).
- The pinned registry includes `dompurify`, `summernote`, `jquery-ui`, `@shopify/draggable`, `gridstack`, `jsbarcode`, and `qrcodejs`; see [`app/jsLibs`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Resources/metadata/app/jsLibs.json#L18-L21) and the [interaction/barcode entries](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Resources/metadata/app/jsLibs.json#L43-L135).
- Presence is not approval. Phase 03 must still verify the supported load form, behavior, and license for every library selected by a feature phase.

Supporting documentation: [`app > jsLibs`](https://docs.espocrm.com/development/metadata/app-js-libs/) and [frontend dependency injection](https://docs.espocrm.com/development/frontend/dependency-injection/).

### 10. Install, upgrade, rebuild, and uninstall

- An extension ZIP contains `manifest.json`, `files/`, and optional `scripts/`. Supported lifecycle script names are `BeforeInstall.php`, `AfterInstall.php`, `BeforeUninstall.php`, and `AfterUninstall.php`; see [`ExtensionManager`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Upgrades/ExtensionManager.php#L32-L50). A script class exposes `run(Container $container, array $params = []): void`; scripts must be minimal and idempotent.
- The installer checks `acceptableVersions` with Composer Semver; see [`Base::isAcceptable`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Upgrades/Actions/Base.php#L250-L287). Phase 01 uses the product-owner-approved EspoCRM constraint `>=10.0.0` and PHP constraint `>=8.3.0 <8.6.0`; only EspoCRM 10.0.0 is source-verified by this contract.
- Install copies package files and performs rebuilds before and after lifecycle scripts as needed; see [`Base\Install::run`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Upgrades/Actions/Base/Install.php#L37-L80).
- Installing a newer package with the same manifest name is the extension upgrade path. EspoCRM first invokes uninstall of the installed extension with rebuild and `AfterUninstall` skipped, but `BeforeUninstall` is not skipped; then it installs the new files. `AfterInstall` receives `isUpgrade = true`. There is no extension `AfterUpgrade.php` hook. See [`Extension\Install`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Upgrades/Actions/Extension/Install.php#L40-L69) and its [upgrade uninstall call](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Upgrades/Actions/Extension/Install.php#L197-L215). Consequently, `BeforeUninstall` must be upgrade-safe.
- Normal uninstall runs `BeforeUninstall`, restores overwritten files/removes extension-installed files, rebuilds, then runs `AfterUninstall`; see [`Base\Uninstall::run`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Core/Upgrades/Actions/Base/Uninstall.php#L38-L107).
- Document Builder uninstall scripts must never delete template/version/snapshot/history/media/generated-document records or their Attachment records/files. They may only remove reversible module registration or configuration owned by the extension. Espo's extension documentation confirms that uninstall is non-destructive unless an extension deliberately adds deletion logic; a later hard rebuild can remove unused custom columns but not custom tables.

Supporting documentation: [making an extension package](https://docs.espocrm.com/development/extension-packages/) and [managing extensions](https://docs.espocrm.com/administration/extensions/).

## Phase hand-off rules

- Phase 01 normalizes `Documentbuilder` to `DocumentBuilder` and moves module parameters to `Resources/module.json`; Phase 00 intentionally does not alter the scaffold.
- Phase 02 implements lifecycle scripts only where required and tests their idempotency and preservation behavior.
- Phase 03 verifies bundled dependency versions, licenses, and client load forms. Library presence in this contract is not a selection decision.
- Phase 04 owns renderer feasibility and the Dompdf initialization choice. No visual or PDF compatibility claim is made here.
- Any later API choice not covered by this document must be verified against the pinned commit and added here before use. Runtime-only behavior remains visibly pending until an approved non-production instance exists.
