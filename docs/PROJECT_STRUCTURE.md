# Project structure

```
Hospital_Management_System/
├── index.php                 public landing page (entry point)
├── config/database.php       ONE database connection file (host, user, password, db name)
├── includes/                 shared helpers (config.php, functions, mail, validation, AI/geo)
├── auth/                     hospital-admin login, registration, OTP, forgot/reset password
│   ├── css/  js/
├── admin/                    hospital-admin panel (dashboard, doctors, staff, billing, ...)
│   ├── css/  js/  uploads/{doctors,staff}/
├── patient_portal/           patient website (unchanged layout)
├── mobile/                   mobile web app (URLs unchanged - the Android app uses them)
│   ├── css/common.css        shared mobile UI (colours, header, buttons, bottom bar)
├── database/hospital_schema.sql
├── assets/                   css/index.css, images/
├── uploads/                  hospital licence documents
├── PHPMailer/
├── CareYourHealth.apk, qr_mobile.png, qr_mobile_app.html   (kept in root: QR/app use these URLs)
└── docs/
```

## Database
Edit credentials only in `config/database.php`.
- `db_connect()`            central database (hospital_management)
- `db_connect($hospital_db)` a hospital database
- `db_connect(null)`        server connection without a database
