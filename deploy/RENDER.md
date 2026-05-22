# Deploy to Render

Render can run this PHP project with the included Dockerfile. The free demo deploy uses SQLite via `DB_CONNECTION=sqlite` because Render free web services do not include MySQL.

## 1. Demo Database

The included `render.yaml` sets:

```env
DB_CONNECTION=sqlite
DB_DATABASE=storage/render.sqlite
```

On first boot, the app creates tables and demo data from `database/sqlite_schema.sql`.

## 2. Create Render Service

1. Log in to Render.
2. Create a new Blueprint or Web Service from `https://github.com/KunFirst-ST/AI-lawyer`.
3. Use the included `render.yaml` or select Docker runtime manually.
4. Fill `OPENAI_API_KEY` if you want real AI replies. Leaving it blank uses the built-in fallback analyzer.

If Render creates a domain like:

```text
https://ai-lawyer.onrender.com
```

set:

```env
APP_URL=https://ai-lawyer.onrender.com
```

## 3. Health Check

After deploy, open:

```text
/public/health.php
```

The app is ready when the database, uploads, and sessions checks are true.

## Free Plan Limits

- The free instance may sleep after inactivity.
- SQLite and uploads are stored inside the container and can disappear on redeploy unless you configure persistent storage or an external database.
- Use MySQL/MariaDB in production by setting `DB_CONNECTION=mysql` and the normal `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values.
- For a free PHP plus MySQL setup in one place, InfinityFree is usually easier.
