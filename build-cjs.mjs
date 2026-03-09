import { build } from 'esbuild';

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
});

// Write package.json for CJS context
import { writeFileSync } from 'node:fs';
writeFileSync('packages/typescript/dist/cjs/package.json', '{"type":"commonjs"}\n');

console.log('CJS build complete');
