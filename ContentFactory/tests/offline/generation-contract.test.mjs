import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const readJson = path => JSON.parse(read(path));

const schema = readJson(
    'files/custom/Espo/Modules/ContentFactory/Resources/schema/content-factory-response.schema.json'
);
const validator = read(
    'files/custom/Espo/Modules/ContentFactory/Tools/ContentFactory/Validation/ResponseValidator.php'
);

test('multi-asset schema requires shared visual direction and local prompts', () => {
    const defs = schema.$defs;

    assert.deepEqual(
        defs.visualDirection.required,
        ['stylePrompt', 'continuityPrompt', 'negativePrompt']
    );
    assert.ok(defs.carousel.required.includes('visualDirection'));
    assert.ok(defs.socialVideo.required.includes('visualDirection'));
    assert.ok(defs.carouselSlide.required.includes('visualPrompt'));
    assert.ok(defs.videoScene.required.includes('visualPrompt'));
    assert.equal(defs.image.required.includes('visualDirection'), false);
});

test('post media contract supports optional hashtags and final image assets', () => {
    const defs = schema.$defs;

    assert.equal(defs.post.required.includes('hashtags'), false);
    assert.ok(defs.post.properties.hashtags);
    assert.equal(defs.image.required.includes('imageUrl'), false);
    assert.equal(defs.image.required.includes('imageDataUrl'), false);
    assert.equal(defs.image.properties.imageUrl.pattern, '^https?://[^\\s]+$');
    assert.match(defs.image.properties.imageDataUrl.pattern, /data:image/);
    assert.ok(schema.$defs.successResponse.properties.assets);
    assert.equal(schema.$defs.generatedAssets.required.includes('images'), true);
    assert.equal(schema.$defs.generatedAssets.properties.images.minItems, 1);
    assert.equal(schema.$defs.generatedAssets.properties.videos.maxItems, 0);
    assert.ok(schema.$defs.generatedImageAsset.oneOf[1].properties.dataBase64);
    assert.match(validator, /normalizePostImageAsset/);
});

test('schema constrains image, carousel, and short-form video media', () => {
    const defs = schema.$defs;

    assert.equal(defs.image.properties.aspectRatio.const, '4:5');
    assert.equal(defs.image.properties.width.const, 1080);
    assert.equal(defs.image.properties.height.const, 1350);
    assert.equal(defs.carousel.properties.aspectRatio.const, '4:5');
    assert.equal(defs.socialVideo.properties.durationSeconds.minimum, 15);
    assert.equal(defs.socialVideo.properties.durationSeconds.maximum, 30);
    assert.equal(defs.videoScene.properties.durationSeconds.minimum, 3);
    assert.equal(defs.videoScene.properties.durationSeconds.maximum, 8);
    assert.equal(defs.socialVideo.properties.scenes.minItems, 3);
    assert.equal(defs.socialVideo.properties.scenes.maxItems, 6);
    assert.equal(defs.verticalSocialVideo.allOf[1].properties.aspectRatio.const, '9:16');
    assert.equal(defs.verticalSocialVideo.allOf[1].properties.width.const, 1080);
    assert.equal(defs.verticalSocialVideo.allOf[1].properties.height.const, 1920);
    assert.equal(defs.portraitSocialVideo.allOf[1].properties.aspectRatio.const, '4:5');
    assert.equal(defs.landscapeSocialVideo.allOf[1].properties.aspectRatio.const, '16:9');
});

test('runtime validator enforces relationships JSON Schema cannot express', () => {
    assert.match(validator, /\$sceneDuration !== \$duration/);
    assert.match(validator, /\(\$scene\['order'\] \?\? null\) !== \$index \+ 1/);
    assert.match(validator, /\$this->registry->videoProfile\(\$platform, \$asset\)/);
    assert.match(validator, /\$this->visualDirection/);
    assert.match(validator, /\$expectedRole/);
});
