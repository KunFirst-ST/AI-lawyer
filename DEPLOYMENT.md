# Deployment Guide

แนวทางนี้สำหรับนำทนายคู่ดีไปใช้จริงบน XAMPP, VPS, shared hosting หรือ internal server

## 1. Requirements

- PHP 8.1+
- MySQL/MariaDB
- PDO MySQL extension
- Apache with `mod_rewrite`/`.htaccess` support, or Nginx with equivalent deny rules
- HTTPS สำหรับ production

## 2. Environment

Copy `.env.example` เป็น `.env` แล้วตั้งค่า:

```env
APP_URL=https://your-domain.com
APP_ENV=production
APP_DEBUG=false
SHOW_DEMO_ACCOUNTS=false
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ai_lawyer_platform
DB_USERNAME=your_db_user
DB_PASSWORD=your_strong_password
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
TURN_URL=turn:turn.example.com:3478
TURN_USERNAME=your_turn_username
TURN_CREDENTIAL=your_turn_password
```

อย่า commit `.env` ขึ้น Git

## 3. Database

Import `database/schema.sql` ผ่าน phpMyAdmin หรือ MySQL CLI ในครั้งแรก

```bash
mysql -u your_db_user -p --default-character-set=utf8mb4 < database/schema.sql
```

หลังอัปเดตระบบแชตบนฐานข้อมูลเดิม ให้รัน migration แบบไม่ล้างข้อมูล:

```bash
php database/migrate_message_media.php
```

ไฟล์ schema ปัจจุบันเป็นแบบ `CREATE TABLE IF NOT EXISTS` และ seed เป็น `INSERT IGNORE` เพื่อลดความเสี่ยงล้างข้อมูลโดยไม่ตั้งใจ แต่ควร backup database ก่อน import ทุกครั้ง

## 4. Web Root

ถ้าเลือกได้ ให้ตั้ง document root ไปที่โฟลเดอร์โปรเจกต์นี้ และเข้าผ่าน `/public/index.php`

โฟลเดอร์สำคัญอย่าง `config`, `services`, `database`, `storage`, `uploads` มี `.htaccess` สำหรับ Apache แล้ว ถ้าใช้ Nginx ต้องตั้ง deny rule เพิ่มเอง

## 5. Uploads

ระบบบันทึกไฟล์ใน `uploads/*` ด้วยชื่อสุ่มและตรวจ MIME/ขนาดไฟล์แล้ว การเปิดไฟล์ควรผ่าน:

```text
/public/file.php?document_id=...
/public/file.php?payment_id=...
/public/file.php?message_id=...
```

อย่าเปิด directory listing และอย่าให้ execute PHP ใน `uploads`

## 6. AI Behavior

AI ถูกออกแบบให้:

- วิเคราะห์เบื้องต้น ไม่ใช่ทนาย
- ระบุมาตราที่อาจเกี่ยวข้องแบบไม่ฟันธง
- จำบริบทว่าผู้ใช้กำลังถามต่อหรือตอบคำถามก่อนหน้า
- ไม่ Match ทนายจนกว่าผู้ใช้กด consent

ถ้าไม่มี `OPENAI_API_KEY` ระบบจะใช้ fallback analyzer แบบ rule-based เพื่อให้ระบบยังใช้งานได้ใน demo/local

## 7. WebRTC Calls

สำหรับโทรเสียงและวิดีโอ ระบบใช้ WebRTC พร้อม STUN เป็นค่าเริ่มต้น หากเปิดใช้งานจริงกับผู้ใช้นอกเครือข่ายเดียวกัน ควรตั้งค่า TURN server ผ่าน `TURN_URL`, `TURN_USERNAME`, `TURN_CREDENTIAL` เพื่อรองรับเครือข่ายที่เชื่อมต่อแบบ peer-to-peer โดยตรงไม่ได้

## 8. Health Check

เปิด:

```text
/public/health.php
```

ควรได้สถานะ `200` และ `database`, `uploads_writable`, `sessions_writable` เป็น `true`

## 9. Production Checklist

- เปลี่ยนรหัสผ่าน demo accounts ทั้งหมด
- เปิด HTTPS
- ตั้ง `APP_DEBUG=false`
- ตั้ง DB user เฉพาะฐานข้อมูลนี้
- ตั้ง backup database อัตโนมัติ
- ตรวจสิทธิ์โฟลเดอร์ `uploads`, `storage/sessions`, `storage/logs`
- ทดสอบ flow: register, login, AI chat, consent, match, booking, slip approval
- ตรวจข้อความ legal disclaimer ให้ตรงกับนโยบายธุรกิจจริง
