# Gmail Alerts

Use a Google App Password for SMTP. Do not put a normal Google account password in `.env`.

```env
MAIL_ENABLED=true
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-account@gmail.com
MAIL_PASSWORD=your-16-character-google-app-password
MAIL_FROM_ADDRESS=your-account@gmail.com
MAIL_FROM_NAME="Thanai Khu Dee"
MAIL_SEND_IMMEDIATELY=true
MAIL_NOTIFY_TYPES=account,booking,payment,match,lawyer_status,contact,message
MAIL_MESSAGE_COOLDOWN_SECONDS=300
```

Prepare the outbox table after deployment:

```bash
php database/migrate_email_notifications.php
```

Failed messages remain in the outbox. An admin can retry them from `/admin/email-notifications.php`, or a cron job can process them:

```bash
php scripts/process_email_notifications.php 50
```
