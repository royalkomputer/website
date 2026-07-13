# ── Stage 1: Build Vite SPA ──
FROM node:20-alpine AS builder

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ── Stage 2: Nginx production server ──
FROM nginx:alpine

# Nginx config
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copy Vite build output
COPY --from=builder /build/dist /usr/share/nginx/html

# Brand assets (logo, etc.)
COPY logo /usr/share/nginx/html/logo

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
