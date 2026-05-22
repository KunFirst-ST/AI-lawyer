# Deploy to Render

Render can run this PHP project with the included Dockerfile. Render does not provide free MySQL for this app, so you need an external MySQL/MariaDB database.

## 1. Prepare Database

Create a MySQL/MariaDB database on your provider and import:

```text
database/schema.sql
```

## 2. Create Render Service

1. Log in to Render.
2. Create a new Blueprint or Web Service from `https://github.com/KunFirst-ST/AI-lawyer`.
3. Use the included `render.yaml` or select Docker runtime manually.
4. Fill the secret environment variables when prompted:
   - `APP_URL`
   - `DB_HOST`
   - `DB_PORT`
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `OPENAI_API_KEY` if you want real AI replies

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
- Uploads stored inside the container can disappear on redeploy unless you configure persistent storage.
- For this app, InfinityFree is usually easier if you need free PHP plus MySQL in one place.
