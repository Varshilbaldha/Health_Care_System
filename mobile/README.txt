PATIENT MOBILE PORTAL - READY FILES

Files:
1. index.php
2. style.css
3. app.js

Expected location:
patient_portal/mobile/

index.php expects:
../includes/auth_check.php
../includes/config.php

The dashboard reads:
- logged-in patient account_id
- active patient_hospital_mapping
- hospital_registration
- each hospital's appointments
- doctors
- departments
- services
- doctor_services

Hospital database supplied for this project:
if0_41844386_hospital_APP20260817080432856

The code does NOT hard-code appointment records. It uses the
database_name stored in hospital_registration and therefore supports
multiple hospitals.

Required existing pages:
book.php
my_appointments.php
search_hospital.php
ai_chat.php
profile.php

If one of those pages has a different filename, change only its href.