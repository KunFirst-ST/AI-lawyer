# ทนายคู่ดี

PHP 8 + MySQL + Bootstrap 5 MVP สำหรับถามปัญหากฎหมายกับ AI และ Match ทนายหลังผู้ใช้ยินยอมเท่านั้น

## Setup

1. Copy `.env.example` เป็น `.env` ถ้าต้องการตั้งค่า DB/OpenAI แบบไม่แก้โค้ด
2. สร้างฐานข้อมูลด้วย `database/schema.sql` ผ่าน phpMyAdmin หรือ MySQL CLI
3. ตั้งค่า `OPENAI_API_KEY` ถ้าต้องการใช้ OpenAI API จริง ถ้าไม่ตั้ง ระบบจะใช้ fallback analyzer แบบ rule-based เพื่อให้ MVP รันได้
4. รัน dev server:

```bash
php -S localhost:8000 -t ai-lawyer-platform
```

เปิด `http://localhost:8000/public/index.php`

ตรวจสุขภาพระบบได้ที่ `http://localhost:8000/public/health.php`

เมื่ออัปเดตฐานข้อมูลเดิม ให้รัน `php database/migrate_message_media.php` เพื่อเพิ่มคอลัมน์สำหรับแชตและห้องโทรโดยไม่ล้างข้อมูล

## Demo Accounts

- Admin: `admin@example.com` / `Admin@1234`
- User: `user@example.com` / `User@1234`
- Lawyer: `criminal.lawyer@example.com` / `Lawyer@1234`

## Important Rule

AI Chat จะไม่เรียก `MatchService` หลังวิเคราะห์ทันที ระบบจะเรียก Match เฉพาะเมื่อผู้ใช้กด “ต้องการหาทนาย” และข้อมูลขั้นต่ำครบเท่านั้น

อ่านขั้นตอนนำขึ้นใช้งานจริงเพิ่มเติมใน `DEPLOYMENT.md`

## Registration API

ระบบสมัครสมาชิกผู้ใช้ทั่วไปมีทั้งหน้าเว็บและ API:

- หน้าเว็บ: `/public/register.php`
- เข้าสู่ระบบผู้ใช้: `/user/login.php`
- สมัครสมาชิกแบบ JSON/API: `POST /api/register.php`
- ตรวจอีเมลว่าว่างหรือไม่: `GET /api/email-check.php?email=name@example.com`

ตัวอย่าง body สำหรับ `POST /api/register.php`:

```text
name=User Name
email=user@example.com
phone=0812345678
password=Userpass123
password_confirm=Userpass123
accepted_terms=1
```

ต้องส่ง CSRF token ผ่าน `csrf_token` ใน form หรือ header `X-CSRF-Token`

ค่าที่เกี่ยวข้องใน `.env`:

```env
REGISTRATION_ENABLED=true
REGISTRATION_RATE_LIMIT=10
PASSWORD_MIN_LENGTH=8
AUTO_LOGIN_AFTER_REGISTER=false
REQUIRE_TERMS_ACCEPTANCE=true
LAWYER_REGISTRATION_ENABLED=true
```

## Social Login

User accounts can sign in with Google OAuth. Lawyer and admin portals still use their separated login forms.

Set these values in `.env` after creating OAuth apps:

```env
GOOGLE_LOGIN_ENABLED=true
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
```

Callback URLs:

- Google: `${APP_URL}/public/oauth-callback.php?provider=google`

## Separated Portals

ระบบแยกพื้นที่ใช้งานตาม role แล้ว:

- ผู้ใช้: `/user/login.php`, `/user/dashboard.php`, สมัครที่ `/public/register.php`
- ทนาย: `/lawyer/login.php`, `/lawyer/dashboard.php`, สมัครที่ `/lawyer/register-lawyer.php`
- แอดมิน: `/admin/login.php`, `/admin/dashboard.php`
- หน้าเลือกพอร์ทัลกลาง: `/public/portals.php`

API สมัครทนาย:

- `POST /api/lawyer-register.php`
- ใช้ข้อมูล form เดียวกับ `/lawyer/register-lawyer.php`
- ต้องส่ง CSRF token และเลือก `categories` อย่างน้อย 1 หมวด
