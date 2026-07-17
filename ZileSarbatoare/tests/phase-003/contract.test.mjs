import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const read = path => readFileSync(resolve(root, path), 'utf8');

const client = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/NagerDateClient.php');
assert.match(client, /private const ORIGIN = 'https:\/\/date\.nager\.at\/api\/v4'/);
assert.match(client, /private const MAXIMUM_RESPONSE_BYTES = 1048576/);
assert.doesNotMatch(client, /function __construct\([^)]*(origin|baseUrl)/s);

const transport = read('files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/EspoHttpTransport.php');
assert.match(transport, /protocols: \[HttpClient\\Protocol::https\]/);
assert.match(transport, /Redirect\(allow: false\)/);
assert.match(transport, /connectTimeout: self::CONNECT_TIMEOUT_SECONDS/);
assert.match(transport, /timeout: self::RESPONSE_TIMEOUT_SECONDS/);
assert.match(transport, /InternalHostRestriction\(restrict: true\)/);
assert.match(transport, /Content-Length/);
assert.match(transport, /while \(!\$stream->eof\(\)\)/);

const binding = read('files/custom/Espo/Modules/ZileSarbatoare/Binding.php');
assert.match(binding, /bindImplementation\(HttpTransport::class, EspoHttpTransport::class\)/);

console.log('PHASE-003 production transport contracts passed.');
