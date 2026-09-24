# Content Factory

Native EspoCRM extension providing:

- a social-content generator for LinkedIn, Facebook, Instagram, and YouTube,
  connected to n8n through an authenticated EspoCRM backend endpoint;
- a mock analytics page prepared for a future real data source;
- native EspoCRM history records through the `ContentFactoryContent` entity.

The extension uses EspoCRM authentication, navigation, ACL, translations, and
record views. Its component CSS consumes the variables supplied by the existing
Tuvtk theme; it does not install or modify a theme.

The browser sends generation requests only to EspoCRM. The backend validates
ACL and input, calls n8n, validates the structured response, and creates a
`ContentFactoryContent` history record only after the full response is valid.
The current contract returns copy, media specifications, visual prompts, and
short-form storyboards; it does not generate image or video files.

For classic posts, n8n may also return an optional final image as `imageUrl` or
as a base64 `imageDataUrl` (`png`, `jpeg`, `webp`, or `gif`). When both are
present, preview uses `imageDataUrl` first so the saved history result remains
self-contained, then falls back to `imageUrl`, and finally to the existing
placeholder. `imageUrl` is recommended for normal operation because it keeps
the API response and `generatedContent` record small.

For compatibility with n8n workflows that return generated media separately,
a `post` or `post-reel` response may include `assets.images[0]`. A URL, data URL,
or `{url|imageUrl|dataUrl|imageDataUrl}` object is normalized into
`content.post.image`; raw `{dataBase64|base64|data, mimeType|contentType}` image
payloads are normalized to `imageDataUrl`. The canonical normalized response is what is
validated, previewed, and saved in history.
For the current post-only media flow, the unified n8n asset envelope may also
contain `videos: []`; non-empty video assets remain invalid and are not processed.

Configure the connection from **Administration > Integrations > Content Factory
/ n8n**. Enable the integration after entering the webhook URL, optional bearer
token, and timeouts. The token uses EspoCRM's native Integration `password`
field: it is stored server-side, masked in the native UI, and cleared from API
read responses. No `data/config.php` keys are required.

Configure course search from **Administration > Integrations > Content Factory
/ WordPress**. Enter the WordPress base URL, username, and Application Password,
then enable the integration. The password uses the same native server-side
Integration storage pattern as the n8n token. The browser calls only
`GET /api/v1/ContentFactory/subjects`; the backend searches the existing
WordPress `/wp-json/wp/v2/cursuri` REST collection.

The Generator persists a required content type on every new history record. A
selected course is stored separately in the optional `courseId`, `courseName`,
`courseSlug`, and `courseUrl` fields; those fields remain null for every other
content type. The versioned payload sent by EspoCRM to n8n includes required
`contentType` and `course`; `course` is an `{id, name, slug, url}` object only
for course content and is `null` for every other content type.

HTTPS webhook URLs are required outside EspoCRM developer mode. The response
contract is available at `Resources/schema/content-factory-response.schema.json`
in the installed module.

History uses EspoCRM's native list and search views for the
`ContentFactoryContent` entity. Creating records from the history list is
disabled. Opening a result reloads the generator in read-only mode with the
saved brief, target settings, generated text, and visual preview.

Validate and package from the repository root:

```bash
./build.sh --extension ./ContentFactory --validate
node --test ContentFactory/tests/offline/*.test.mjs
php ContentFactory/tests/offline/response-validator.test.php
php ContentFactory/tests/offline/integration-settings.test.php
php ContentFactory/tests/offline/integration-hook.test.php
php ContentFactory/tests/offline/after-install.test.php
php ContentFactory/tests/offline/n8n-http-transport.test.php
./build.sh --extension ./ContentFactory --zip files scripts
```

After installation or upgrade, run EspoCRM rebuild and clear the browser cache.
