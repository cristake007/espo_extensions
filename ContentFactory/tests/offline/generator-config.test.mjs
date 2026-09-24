import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL(
    '../../files/client/custom/modules/content-factory/src/generator-config.js',
    import.meta.url
), 'utf8');
const metadata = JSON.parse(readFileSync(new URL(
    '../../files/custom/Espo/Modules/ContentFactory/Resources/metadata/app/contentFactory.json',
    import.meta.url
), 'utf8'));
let factory;

vm.runInNewContext(source, {
    define: callback => {
        factory = callback();
    },
});

const config = factory.create(metadata.generator);

test('platform and format matrix matches the approved generator contract', () => {
    assert.deepEqual(
        JSON.parse(JSON.stringify(config.platforms)),
        [
            {
                id: 'linkedin',
                label: 'LinkedIn',
                entityValue: 'LinkedIn',
                defaultFormat: 'post',
                formats: ['post', 'video'],
            },
            {
                id: 'facebook',
                label: 'Facebook',
                entityValue: 'Facebook',
                defaultFormat: 'post',
                formats: ['post', 'reel', 'video', 'post-reel'],
            },
            {
                id: 'instagram',
                label: 'Instagram',
                entityValue: 'Instagram',
                defaultFormat: 'post',
                formats: ['post', 'reel', 'carousel', 'post-reel'],
            },
            {
                id: 'youtube',
                label: 'YouTube',
                entityValue: 'YouTube',
                defaultFormat: 'video',
                formats: ['video', 'short', 'video-short'],
            },
        ]
    );
});

test('media profiles are defined once in canonical Espo metadata', () => {
    assert.deepEqual(metadata.generator.mediaProfiles.image, {
        aspectRatio: '4:5',
        width: 1080,
        height: 1350,
    });
    assert.deepEqual(metadata.generator.mediaProfiles.video['instagram.reel'], {
        aspectRatio: '9:16',
        width: 1080,
        height: 1920,
    });
    assert.equal(source.includes("linkedin: {"), false);
});

test('platform changes preserve a supported format and otherwise use the default', () => {
    assert.deepEqual(
        JSON.parse(JSON.stringify(config.selectPlatform(
            {platform: 'facebook', format: 'video'},
            'youtube'
        ))),
        {platform: 'youtube', format: 'video'}
    );
    assert.deepEqual(
        JSON.parse(JSON.stringify(config.selectPlatform(
            {platform: 'instagram', format: 'carousel'},
            'linkedin'
        ))),
        {platform: 'linkedin', format: 'post'}
    );
});

test('invalid formats are rejected and combined formats expand into two previews', () => {
    assert.deepEqual(
        JSON.parse(JSON.stringify(config.selectFormat(
            {platform: 'linkedin', format: 'post'},
            'reel'
        ))),
        {platform: 'linkedin', format: 'post'}
    );
    assert.deepEqual(Array.from(config.formatsForPreview('post-reel')), ['post', 'reel']);
    assert.deepEqual(Array.from(config.formatsForPreview('video-short')), ['video', 'short']);
});
