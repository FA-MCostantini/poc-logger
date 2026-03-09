/**
 * Smoke test: simula il flusso descritto nel README Quick Start TypeScript
 * in un ambiente isolato (senza accesso a node_modules locale).
 *
 * Crea una directory temporanea che replica la struttura di un consumer:
 *   tmp/
 *   ├── node_modules/poc-logger/  (solo il bundle CJS)
 *   ├── config.yaml               (dal README)
 *   └── handler.cjs               (codice dal README, adattato a CJS)
 *
 * Se il bundle non è self-contained (es. require() verso @middy/core ESM),
 * il test fallisce con lo stesso ERR_REQUIRE_ESM che si vede in Lambda.
 */
'use strict';

const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const cjsBundlePath = path.resolve(__dirname, '../packages/typescript/dist/cjs/index.js');
const cjsPkgJsonPath = path.resolve(__dirname, '../packages/typescript/dist/cjs/package.json');
const rootPkgJsonPath = path.resolve(__dirname, '../package.json');

if (!fs.existsSync(cjsBundlePath)) {
  console.error('CJS smoke test FAILED — dist/cjs/index.js not found. Run npm run build first.');
  process.exit(1);
}

// --- Build isolated consumer directory ---
const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'cjs-smoke-'));

// Simulate node_modules/poc-logger with only the CJS bundle
const pkgDir = path.join(tmpDir, 'node_modules', 'poc-logger', 'packages', 'typescript', 'dist', 'cjs');
fs.mkdirSync(pkgDir, { recursive: true });
fs.copyFileSync(cjsBundlePath, path.join(pkgDir, 'index.js'));
if (fs.existsSync(cjsPkgJsonPath)) {
  fs.copyFileSync(cjsPkgJsonPath, path.join(pkgDir, 'package.json'));
}

// Root package.json so require('poc-logger') resolves via "main"
const rootPkg = JSON.parse(fs.readFileSync(rootPkgJsonPath, 'utf-8'));
fs.writeFileSync(
  path.join(tmpDir, 'node_modules', 'poc-logger', 'package.json'),
  JSON.stringify({
    name: 'poc-logger',
    version: rootPkg.version,
    main: 'packages/typescript/dist/cjs/index.js',
  }) + '\n'
);

// config.yaml from README Quick Start
fs.writeFileSync(path.join(tmpDir, 'config.yaml'), `\
service:
  name: "my-lambda"
  version: "1.0.0"

logger:
  level: "INFO"
  sampleRate: 0.1
  persistentKeys:
    team: "my-team"

tracer:
  enabled: true
  captureHTTPS: true

metrics:
  namespace: "MyNamespace"
  captureColdStart: true
`);

// handler.cjs — the README example adapted to CJS (as Lambda CJS would resolve it)
fs.writeFileSync(path.join(tmpDir, 'handler.cjs'), `\
'use strict';
const assert = require('node:assert');
const { createFirstanceLogger, middy } = require('poc-logger');

// Verify imports exist (same as README usage)
assert.strictEqual(typeof createFirstanceLogger, 'function',
  'createFirstanceLogger should be a function');
assert.strictEqual(typeof middy, 'function',
  'middy should be a function');

// Instantiate like the README shows
const obs = createFirstanceLogger({ configPath: './config.yaml' });
assert.ok(obs, 'createFirstanceLogger should return an observability object');
assert.strictEqual(typeof obs.logger.info, 'function',
  'obs.logger.info should be a function');
assert.strictEqual(typeof obs.middleware, 'function',
  'obs.middleware should be a function');

// Wrap a handler with middy + middleware, like the README
const handler = middy(async (event) => {
  obs.logger.info('Processing event', { eventType: 'test' });
  return { statusCode: 200 };
}).use(obs.middleware());

assert.strictEqual(typeof handler, 'function',
  'handler wrapped with middy should be callable');

console.log('OK');
`);

// --- Run in isolation ---
try {
  const result = execFileSync(process.execPath, ['handler.cjs'], {
    cwd: tmpDir,
    timeout: 10_000,
    encoding: 'utf-8',
    env: {
      PATH: process.env.PATH,
      HOME: os.tmpdir(),
    },
  });

  if (result.trim().endsWith('OK')) {
    console.log('CJS smoke test PASSED — README Quick Start works in isolated CJS environment');
  } else {
    console.error('CJS smoke test FAILED — unexpected output:', result);
    process.exit(1);
  }
} catch (err) {
  console.error('CJS smoke test FAILED — the README usage does not work in a CJS consumer:');
  if (err.stderr) console.error(err.stderr);
  if (err.stdout) console.error(err.stdout);
  console.error(err.message);
  process.exit(1);
} finally {
  fs.rmSync(tmpDir, { recursive: true, force: true });
}
