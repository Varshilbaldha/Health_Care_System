# Hospital Management System update

## Patient architecture

Patient identity remains in the central `hospital_management` database (`patient_accounts`, `patient_profiles`, `patient_hospital_mapping`). A hospital database does not receive a duplicate full patient profile. The mapping stores the hospital-specific patient code, status, first/last visit and visit count.

## Added dashboard modules

- Patients: real central patient mapping, add/search/view/deactivate.
- Doctor Availability: existing `doctor_availability` data viewer.
- Admissions: real admissions listing and discharge action.
- Rooms & Beds: basic room and bed creation/listing.
- Medical Records, Prescriptions, Lab Tests: basic operational listings.
- Billing: basic bill creation.
- Payments: manual payment records only; no payment gateway/API is implemented.
- Patient QR: hospital QR entry page; QR uses a small client-side library and does not process payments.

## Existing working modules

Existing Departments, Doctors, Staff, Services, Appointments, Settings, AI/API files and patient booking code were not replaced by this update. They remain in place.

## New hospital databases

`create_hospital_database.php` already executes `hospital_schema.sql` for each new hospital. No duplicate local patient table was added because patient identity is centrally managed and mapped to hospitals.

## Security

Do not commit API keys or `.env` secrets. Rotate any API key that has previously been committed to Git history.
