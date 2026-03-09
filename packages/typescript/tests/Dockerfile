FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY tsconfig.json vitest.config.ts ./
COPY packages/typescript/src/ ./packages/typescript/src/
COPY packages/typescript/tests/ ./packages/typescript/tests/

CMD ["npx", "vitest", "run", "--reporter=verbose"]
