/**
 * Smoke test: verifica che il build CJS sia caricabile con require()
 * e che tutti gli export principali siano presenti.
 *
 * Questo test intercetta errori come ERR_REQUIRE_ESM che emergono
 * solo quando un consumer CommonJS carica il pacchetto.
 */
'use strict';

const assert = require('node:assert');
const path = require('node:path');

const cjsPath = path.resolve(__dirname, '../packages/typescript/dist/cjs/index.js');

let pkg;
try {
  pkg = require(cjsPath);
} catch (err) {
  console.error('CJS smoke test FAILED — require() error:');
  console.error(err.message);
  process.exit(1);
}

// Verify all public exports
const expectedFunctions = [
  'createFirstanceLogger',
  'loadConfig',
  'OTelLogFormatter',
  'createTracer',
  'createMetrics',
  'createMiddlewareChain',
  'middy',
];

const expectedTypes = [
  'configSchema',
];

for (const name of expectedFunctions) {
  assert.ok(typeof pkg[name] === 'function', `${name} should be exported as a function`);
}

for (const name of expectedTypes) {
  assert.ok(pkg[name] !== undefined, `${name} should be exported`);
}

console.log('CJS smoke test PASSED — all exports verified');
