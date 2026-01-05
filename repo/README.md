# VetOps Dockerized Fullstack

VetOps runs as a LAN-first on-prem stack using Docker Compose.

- `backend/` - Laravel API (`/api/v1`)
- `frontend/` - Vue 3 + Vite app built and served by Nginx
- `db` service - MySQL 8.4 with persistent named volume

## Prerequisites

- Docker Engine 24+
- Docker Compose v2 (`docker compose`)

## Quick start

From the `repo/` directory:

```bash
docker compose up -d --build
```

This boots the database and backend with defaults from `docker-compose.yml` only,
so no local `.env` editing is required for the first run. The frontend service is
added once the Vue application lands.

## One-command first run

```bash
npm run bootstrap
```

`bootstrap` creates missing `.env` files from the examples, generates `APP_KEY`
when blank, then runs `docker compose up -d --build`.
