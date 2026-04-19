FROM node:20-alpine AS builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
RUN npm run build
RUN npx prisma generate

FROM node:20-alpine AS runtime

WORKDIR /app

COPY package.json package-lock.json* ./
COPY --from=builder /app/node_modules ./node_modules

COPY --from=builder /app/.next ./.next
COPY --from=builder /app/public ./public
COPY prisma ./prisma

EXPOSE 3000

ENV NODE_ENV=production

CMD ["npm", "start"]
