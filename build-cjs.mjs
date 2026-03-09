import { build } from 'esbuild';
import { readFileSync, writeFileSync } from 'node:fs';

const pkg = JSON.parse(readFileSync('package.json', 'utf-8'));

await build({
  entryPoints: ['packages/typescript/src/index.ts'],
  bundle: true,
  format: 'cjs',
  platform: 'node',
  target: 'node20',
  outfile: 'packages/typescript/dist/cjs/index.js',
  // Keep AWS SDK external (provided by Lambda runtime)
  external: ['aws-sdk', '@aws-sdk/*'],
  // Generate sourcemap
  sourcemap: true,
  define: {
    '__SDK_VERSION__': JSON.stringify(pkg.version),
  },
});

// Write package.json for CJS context
writeFileSync('packages/typescript/dist/cjs/package.json', '{"type":"commonjs"}\n');

console.log('CJS build complete');
