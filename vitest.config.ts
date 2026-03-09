import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    root: './packages/typescript',
    include: ['tests/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      include: ['src/**/*.ts'],
      exclude: ['src/index.ts'],
      thresholds: {
        statements: 90,
        branches: 85,
      },
    },
  },
});
