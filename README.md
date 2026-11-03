# 🪐 Jothidam Astrologer Admin Panel

> **Dedicated Astrologer Administration Workspace** for reviewing customer consultations, validating payments, editing horoscope reports, and exporting printable PDF reports.

---

## 🚀 Features

- 🔐 **Secure Session Authentication**: CSRF-protected admin login with bcrypt password verification.
- 📊 **Consultation Dashboard**: Metrics on total, pending, approved, and rejected bookings, with search and pagination.
- 🔍 **Customer & Horoscope Review**:
  - Full birth details (DOB, TOB, POB, Lat/Lon, question).
  - Pre-calculated horoscope summary (Lagna, Rasi, Nakshatra, Dasha-Bhukti, Doshas).
- 💳 **Payment Verification**:
  - Review UPI reference numbers and uploaded payment screenshots.
  - One-click **Approve** and **Reject** buttons (with automatic slot releasing on rejection).
- ✍️ **Astrological Report Editor**:
  - Write, modify, and save detailed Tamil predictions and remedies before client consultation.
- 📄 **Print & PDF Export**:
  - Professional, clean print layout ready for printing or exporting as PDF reports.
- 🗄️ **Universal Database**:
  - Supports MySQL and automatic local SQLite (`astro.sqlite`) with atomic transaction locking.

---

## 🔑 Default Credentials

- **Username**: `admin`
- **Password**: `astro2025`

---

## 🛠️ Quick Start

### 1. Requirements
- PHP 8.0 or higher
- SQLite3 or MySQL 8.0+
- PDO extension enabled

### 2. Run Locally
```bash
php -S 127.0.0.1:8080
```
Then open:
👉 **http://127.0.0.1:8080/index.php**

---

## 📁 Project Structure
```
astro-admin/
├── index.php         ← Admin Login Page
├── auth.php          ← Session authentication & CSRF protection
├── config.php        ← Database provider (MySQL + SQLite)
├── dashboard.php     ← Bookings overview, metrics & filters
├── review.php        ← Booking verification, report editor & PDF print
├── logout.php        ← Secure session logout
├── db/
│   └── schema.sql    ← Database tables schema
├── uploads/
│   └── payments/     ← Uploaded customer payment receipts
└── README.md
```

