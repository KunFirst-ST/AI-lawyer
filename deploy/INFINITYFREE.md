# Deploy to InfinityFree

InfinityFree is the simplest free option for this project because it includes PHP, MySQL/MariaDB, free subdomains, SSL, and `.htaccess` support.

## 1. Create Hosting

1. Sign up at `https://www.infinityfree.com/`.
2. Create a free hosting account and choose a free subdomain.
3. In the control panel, create a MySQL database.
4. Open phpMyAdmin for that database and import `database/schema.sql`.

## 2. Upload Files

Upload the contents of this project folder into InfinityFree's `htdocs` folder.

Do not upload:

- `.git/`
- `.env`
- `storage/logs/*.log`
- `storage/sessions/sess_*`
- uploaded user files from `uploads/*`

The committed `.htaccess` files protect `config`, `services`, `database`, `storage`, and `uploads` on Apache hosting.

## 3. Create `.env`

Create a `.env` file in `htdocs` using the values from InfinityFree:

```env
APP_URL=https://your-free-domain.example
APP_ENV=production
APP_DEBUG=false
DB_HOST=your_mysql_host
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
OPENAI_ENDPOINT=https://api.openai.com/v1/chat/completions
REGISTRATION_ENABLED=true
REGISTRATION_RATE_LIMIT=10
PASSWORD_MIN_LENGTH=8
AUTO_LOGIN_AFTER_REGISTER=false
REQUIRE_TERMS_ACCEPTANCE=true
LAWYER_REGISTRATION_ENABLED=true
```

## 4. Check

Open:

```text
https://your-free-domain.example/public/health.php
```

Expected result:

- `database: true`
- `uploads_writable: true`
- `sessions_writable: true`

Then open:

```text
https://your-free-domain.example/public/index.php
```

## Notes

- Free hosting is fine for demos, student projects, and testing. It is not ideal for production legal services with sensitive user files.
- Change all demo account passwords after deploy.
- Keep `.env` out of GitHub.
