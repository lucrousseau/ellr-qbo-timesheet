# Node 22 dev image for admin and timesheet Vite servers (monorepo workspaces).
FROM node:22-bookworm-slim

WORKDIR /app

COPY package.json package-lock.json ./
COPY apps/admin/package.json apps/admin/
COPY apps/timesheet/package.json apps/timesheet/
COPY packages/api-client/package.json packages/api-client/
COPY packages/ui/package.json packages/ui/
COPY packages/vite-config/package.json packages/vite-config/
COPY packages/test-utils/package.json packages/test-utils/
COPY packages/password-policy/package.json packages/password-policy/

RUN npm ci

COPY docker/node/sync-deps.sh /usr/local/bin/sync-node-deps.sh
COPY docker/node/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/sync-node-deps.sh /usr/local/bin/entrypoint.sh

COPY . .

EXPOSE 5173 5174

ENTRYPOINT ["entrypoint.sh"]
CMD ["npm", "run", "dev", "--workspace=admin", "--", "--host", "0.0.0.0"]
