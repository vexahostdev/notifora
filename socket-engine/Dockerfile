FROM node:20-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY package*.json ./
RUN npm install --omit=dev --no-audit --no-fund
COPY server.js ./
USER node
EXPOSE 8042
HEALTHCHECK --interval=30s --timeout=3s CMD wget -qO- http://127.0.0.1:8042/health || exit 1
CMD ["node", "server.js"]
