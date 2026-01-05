# VetOps Unified Operations Portal

VetOps is a LAN-first, on-premise operations platform for a multi-location veterinary
hospital group. It unifies equipment rental operations, medical-supply inventory,
internal content publishing, and post-visit service feedback under a single role-based
Vue.js interface backed by Laravel.

## Repository layout

- `repo/` - the runnable project: Laravel API (`repo/backend`), Vue 3 SPA
  (`repo/frontend`), and a Docker Compose stack (`repo/docker-compose.yml`).
- `docs/` - system design and API specifications (added as the project matures).

## Quick start

See `repo/README.md` for the full guide. The stack boots with a single command:

```bash
cd repo
docker compose up -d --build
```
