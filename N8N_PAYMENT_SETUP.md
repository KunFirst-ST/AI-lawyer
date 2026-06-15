# n8n Payment Verification

เอกสารนี้ใช้ตั้งค่า n8n ให้ตรวจสลิปชำระเงินของระบบทนายคู่ดีแบบอัตโนมัติ

## ค่าในเว็บ

ตั้งค่าในไฟล์ `.env` ของเว็บ:

```env
N8N_PAYMENT_VERIFICATION_ENABLED=true
N8N_PAYMENT_WEBHOOK_URL=https://YOUR_N8N_DOMAIN/webhook/payment-slip-verify
N8N_PAYMENT_WEBHOOK_SECRET=ใส่-secret-ฝั่งรับ-webhook
N8N_PAYMENT_CALLBACK_SECRET=ใส่-secret-ฝั่ง-callback-กลับเว็บ
N8N_TIMEOUT=15
```

บน VPS ตั้งค่า secret ไว้แล้ว แต่ไม่ควรบันทึกค่าจริงลง GitHub
ยังต้องใส่ `N8N_PAYMENT_WEBHOOK_URL` หลังจากสร้าง workflow ใน n8n แล้ว

## ข้อมูลที่เว็บส่งไป n8n

เมื่อผู้ใช้อัปโหลดสลิป เว็บจะส่ง `POST` ไปที่ `N8N_PAYMENT_WEBHOOK_URL`

Header:

```text
Content-Type: application/json
X-N8N-Payment-Secret: ค่าเดียวกับ N8N_PAYMENT_WEBHOOK_SECRET
```

Payload หลัก:

```json
{
  "source": "thanai_khu_dee",
  "event": "payment.slip_uploaded",
  "payment": {
    "id": 123,
    "booking_id": 456,
    "case_id": 789,
    "amount": 1500,
    "currency": "THB",
    "method": "bank_transfer",
    "status": "pending"
  },
  "slip": {
    "file_name": "slip.png",
    "mime_type": "image/png",
    "size_bytes": 123456,
    "base64": "..."
  },
  "callback": {
    "url": "https://157.85.105.29.nip.io/api/n8n-payment-callback.php",
    "method": "POST",
    "secret_header": "X-N8N-Payment-Secret",
    "secret": "ค่าเดียวกับ N8N_PAYMENT_CALLBACK_SECRET",
    "accepted_decisions": ["approved", "rejected", "manual_review"]
  }
}
```

## Callback จาก n8n กลับเว็บ

n8n ต้องส่ง `POST` กลับมาที่:

```text
https://157.85.105.29.nip.io/api/n8n-payment-callback.php
```

Header:

```text
Content-Type: application/json
X-N8N-Payment-Secret: ค่าเดียวกับ N8N_PAYMENT_CALLBACK_SECRET
```

อนุมัติ:

```json
{
  "payment_id": 123,
  "decision": "approved",
  "confidence": 0.94,
  "note": "ยอดเงินและข้อมูลสลิปตรงกับรายการจอง"
}
```

ปฏิเสธ:

```json
{
  "payment_id": 123,
  "decision": "rejected",
  "confidence": 0.91,
  "note": "ยอดเงินในสลิปไม่ตรงกับยอดที่ต้องชำระ"
}
```

ให้แอดมินตรวจต่อ:

```json
{
  "payment_id": 123,
  "decision": "manual_review",
  "confidence": 0.62,
  "note": "อ่านข้อมูลสลิปได้ไม่ชัดเจน"
}
```

## Workflow ที่แนะนำใน n8n

1. สร้าง Webhook node
   - Method: `POST`
   - Path: `payment-slip-verify`
   - Response mode: respond with last node หรือ Respond to Webhook
2. ตรวจ header `X-N8N-Payment-Secret`
   - ถ้าไม่ตรง ให้หยุด workflow
3. ส่งรูปสลิปไป OCR หรือ AI Vision
   - ใช้ `slip.base64` และ `slip.mime_type`
   - ให้ดึงยอดเงิน วันเวลา เลขอ้างอิง ชื่อบัญชี หรือ PromptPay เท่าที่อ่านได้
4. Code node เปรียบเทียบ
   - ยอดในสลิปต้องตรงกับ `payment.amount`
   - สกุลเงินต้องเป็น THB
   - ถ้าข้อมูลไม่ชัด ให้ส่ง `manual_review`
5. HTTP Request node ส่ง callback กลับเว็บ
   - URL: `{{$json.callback.url}}`
   - Header: `X-N8N-Payment-Secret: {{$json.callback.secret}}`
   - Body: `payment_id`, `decision`, `confidence`, `note`

## หมายเหตุความปลอดภัย

- อย่าใส่ secret ลง GitHub
- ถ้า workflow ยังไม่มั่นใจ ให้ส่ง `manual_review` แทนการอนุมัติ
- ควรให้แอดมินตรวจสุ่มรายการที่ n8n อนุมัติในช่วงแรก
