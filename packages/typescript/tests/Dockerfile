FROM node:20-alpine

WORKDIR /app

COPY package.json ./
RUN npm install --ignore-scripts

COPY tsconfig.json tsconfig.build.json tsconfig.cjs.json vitest.config.ts ./
COPY packages/typescript/src/ ./packages/typescript/src/
COPY packages/typescript/tests/ ./packages/typescript/tests/

CMD ["npx", "vitest", "run", "--reporter=verbose"]
