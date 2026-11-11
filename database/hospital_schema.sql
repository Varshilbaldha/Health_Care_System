

CREATE TABLE IF NOT EXISTS departments
(
    department_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    department_name VARCHAR(100) NOT NULL,

    description TEXT,

    location VARCHAR(150),

    head_doctor_id INT UNSIGNED NULL,

    status ENUM('Active', 'Inactive')
        NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE (department_name)
);


CREATE TABLE IF NOT EXISTS doctors
(
    doctor_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    department_id INT UNSIGNED NOT NULL,

    doctor_name VARCHAR(100) NOT NULL,

    gender ENUM(
        'Male',
        'Female',
        'Other'
    ) NOT NULL,

    date_of_birth DATE NULL,

    email VARCHAR(150) NOT NULL,

    phone VARCHAR(20) NOT NULL,

    qualification VARCHAR(150) NOT NULL,

    specialization VARCHAR(150) NOT NULL,

    medical_license_no VARCHAR(100) NOT NULL,

    experience_years SMALLINT UNSIGNED DEFAULT 0,

    consultation_fee DECIMAL(10,2) DEFAULT 0.00,

    profile_photo VARCHAR(255) NULL,

    status ENUM(
        'Active',
        'Inactive'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_doctor_email
        UNIQUE (email),

    CONSTRAINT uq_doctor_license
        UNIQUE (medical_license_no),

    CONSTRAINT fk_doctor_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_doctor_department (department_id),

    INDEX idx_doctor_name (doctor_name),

    INDEX idx_doctor_status (status)
);
CREATE TABLE IF NOT EXISTS staff
(
    staff_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    department_id INT UNSIGNED NOT NULL,

    staff_name VARCHAR(100) NOT NULL,

    gender ENUM(
        'Male',
        'Female',
        'Other'
    ) NOT NULL,

    date_of_birth DATE NULL,

    email VARCHAR(150) NOT NULL,

    phone VARCHAR(20) NOT NULL,

    designation VARCHAR(100) NOT NULL,

    joining_date DATE NOT NULL,

    salary DECIMAL(10,2) DEFAULT 0.00,

    profile_photo VARCHAR(255) NULL,

    status ENUM(
        'Active',
        'Inactive'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_staff_email
        UNIQUE(email),

    CONSTRAINT fk_staff_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_staff_department (department_id),

    INDEX idx_staff_name (staff_name),

    INDEX idx_staff_status (status)
);
CREATE TABLE IF NOT EXISTS services
(
    service_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    department_id INT UNSIGNED NOT NULL,

    service_name VARCHAR(150) NOT NULL,

    service_code VARCHAR(30) NOT NULL,

    description TEXT DEFAULT NULL,

    service_type ENUM
    (
        'Consultation',
        'Diagnostic',
        'Laboratory',
        'Radiology',
        'Procedure',
        'Surgery',
        'Therapy',
        'Emergency',
        'Vaccination',
        'Other'
    ) NOT NULL,

    consultation_mode ENUM
    (
        'In-Person',
        'Video',
        'Both'
    ) NOT NULL DEFAULT 'In-Person',

    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,

    service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    preparation_instructions TEXT DEFAULT NULL,

    status ENUM
    (
        'Active',
        'Inactive'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_service_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    UNIQUE KEY uq_service_code (service_code),

    UNIQUE KEY uq_department_service
    (
        department_id,
        service_name
    ),

    INDEX idx_department (department_id),

    INDEX idx_service_type (service_type),

    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS doctor_services
(
    doctor_service_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    doctor_id INT UNSIGNED NOT NULL,

    service_id INT UNSIGNED NOT NULL,

    consultation_fee DECIMAL(10,2) DEFAULT NULL,

    consultation_duration SMALLINT UNSIGNED DEFAULT NULL,

    status ENUM
    (
        'Active',
        'Inactive'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_doctor_service_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctors(doctor_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_doctor_service_service
        FOREIGN KEY (service_id)
        REFERENCES services(service_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_doctor_service
    (
        doctor_id,
        service_id
    ),

    INDEX idx_doctor (doctor_id),

    INDEX idx_service (service_id),

    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS doctor_availability
(
    availability_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    doctor_id INT UNSIGNED NOT NULL,

    day_of_week ENUM
    (
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    ) NOT NULL,

    start_time TIME NOT NULL,

    end_time TIME NOT NULL,

    slot_duration_minutes SMALLINT UNSIGNED
        NOT NULL DEFAULT 15,

    max_patients INT UNSIGNED
        NOT NULL DEFAULT 0,

    consultation_mode ENUM
    (
        'In-Person',
        'Video',
        'Both'
    ) NOT NULL DEFAULT 'In-Person',

    status ENUM
    (
        'Active',
        'Inactive'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_availability_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctors(doctor_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_doctor_schedule
    (
        doctor_id,
        day_of_week,
        start_time,
        end_time
    ),

    INDEX idx_doctor (doctor_id),

    INDEX idx_day (day_of_week),

    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS appointments
(
    appointment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    mapping_id INT UNSIGNED NOT NULL,

    doctor_id INT UNSIGNED NOT NULL,

    service_id INT UNSIGNED NOT NULL,

    appointment_no VARCHAR(30) NOT NULL,

    appointment_date DATE NOT NULL,

    appointment_time TIME NOT NULL,

    appointment_type ENUM
    (
        'Walk-In',
        'Online'
    ) NOT NULL DEFAULT 'Online',

    consultation_mode ENUM
    (
        'In-Person',
        'Video'
    ) NOT NULL DEFAULT 'In-Person',

    token_number SMALLINT UNSIGNED DEFAULT NULL,

    appointment_status ENUM
    (
        'Scheduled',
        'Checked-In',
        'In-Progress',
        'Completed',
        'Cancelled',
        'No-Show'
    ) NOT NULL DEFAULT 'Scheduled',

    symptoms TEXT DEFAULT NULL,

    notes TEXT DEFAULT NULL,

    cancelled_by ENUM
    (
        'Patient',
        'Hospital',
        'Doctor',
        'System'
    ) DEFAULT NULL,

    cancellation_reason VARCHAR(255) DEFAULT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_appointment_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctors(doctor_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_appointment_service
        FOREIGN KEY (service_id)
        REFERENCES services(service_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    UNIQUE KEY uq_appointment_no
    (
        appointment_no
    ),

    INDEX idx_mapping (mapping_id),

    INDEX idx_doctor (doctor_id),

    INDEX idx_service (service_id),

    INDEX idx_date (appointment_date),

    INDEX idx_status (appointment_status)
);
CREATE TABLE IF NOT EXISTS medical_records
(
    medical_record_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    appointment_id INT UNSIGNED NOT NULL,

    chief_complaint TEXT DEFAULT NULL,

    present_illness TEXT DEFAULT NULL,

    past_medical_history TEXT DEFAULT NULL,

    family_history TEXT DEFAULT NULL,

    allergies TEXT DEFAULT NULL,

    clinical_notes TEXT DEFAULT NULL,

    diagnosis_summary TEXT DEFAULT NULL,

    follow_up_date DATE DEFAULT NULL,

    follow_up_notes TEXT DEFAULT NULL,

    record_status ENUM
    (
        'Open',
        'Completed',
        'Archived'
    ) NOT NULL DEFAULT 'Open',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_medical_record_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(appointment_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    UNIQUE KEY uq_appointment_record
    (
        appointment_id
    ),

    INDEX idx_appointment (appointment_id),

    INDEX idx_status (record_status)
);
CREATE TABLE IF NOT EXISTS prescriptions
(
    prescription_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    medical_record_id INT UNSIGNED NOT NULL,

    prescription_no VARCHAR(30) NOT NULL,

    advice TEXT DEFAULT NULL,

    follow_up_date DATE DEFAULT NULL,

    status ENUM
    (
        'Active',
        'Completed',
        'Cancelled'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_prescription_record
        FOREIGN KEY (medical_record_id)
        REFERENCES medical_records(medical_record_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_prescription_no
    (
        prescription_no
    ),

    UNIQUE KEY uq_record_prescription
    (
        medical_record_id
    ),

    INDEX idx_record
    (
        medical_record_id
    ),

    INDEX idx_status
    (
        status
    )
);
CREATE TABLE IF NOT EXISTS prescription_items
(
    prescription_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prescription_id INT UNSIGNED NOT NULL,

    medicine_name VARCHAR(200) NOT NULL,

    dosage VARCHAR(100) NOT NULL,

    frequency VARCHAR(100) NOT NULL,

    duration VARCHAR(100) NOT NULL,

    route ENUM
    (
        'Oral',
        'Injection',
        'Topical',
        'Inhalation',
        'Other'
    ) DEFAULT 'Oral',

    instructions TEXT DEFAULT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_prescription_item
        FOREIGN KEY (prescription_id)
        REFERENCES prescriptions(prescription_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_prescription
    (
        prescription_id
    )
);

CREATE TABLE IF NOT EXISTS lab_tests
(
    lab_test_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    medical_record_id INT UNSIGNED NOT NULL,

    test_name VARCHAR(150) NOT NULL,

    test_category VARCHAR(100) DEFAULT NULL,

    test_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    ordered_by_doctor_id INT UNSIGNED NOT NULL,

    lab_staff_id INT UNSIGNED DEFAULT NULL,

    sample_type ENUM
    (
        'Blood',
        'Urine',
        'Stool',
        'Saliva',
        'Sputum',
        'Other'
    ) DEFAULT 'Blood',

    test_result TEXT DEFAULT NULL,

    normal_range VARCHAR(100) DEFAULT NULL,

    report_file VARCHAR(255) DEFAULT NULL,

    test_status ENUM
    (
        'Ordered',
        'Sample Collected',
        'Processing',
        'Completed',
        'Cancelled'
    ) NOT NULL DEFAULT 'Ordered',

    test_date DATE DEFAULT NULL,

    completed_at DATETIME DEFAULT NULL,

    remarks TEXT DEFAULT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_lab_record
        FOREIGN KEY (medical_record_id)
        REFERENCES medical_records(medical_record_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_lab_doctor
        FOREIGN KEY (ordered_by_doctor_id)
        REFERENCES doctors(doctor_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_lab_staff
        FOREIGN KEY (lab_staff_id)
        REFERENCES staff(staff_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_record (medical_record_id),

    INDEX idx_status (test_status),

    INDEX idx_doctor (ordered_by_doctor_id)
);

CREATE TABLE IF NOT EXISTS billing
(
    bill_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    appointment_id INT UNSIGNED NOT NULL,

    bill_no VARCHAR(30) NOT NULL,

    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    payable_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    bill_status ENUM
    (
        'Pending',
        'Partially Paid',
        'Paid',
        'Cancelled'
    ) NOT NULL DEFAULT 'Pending',

    remarks TEXT DEFAULT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_billing_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(appointment_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    UNIQUE KEY uq_bill_no
    (
        bill_no
    ),

    UNIQUE KEY uq_bill_appointment
    (
        appointment_id
    ),

    INDEX idx_status
    (
        bill_status
    )
);CREATE TABLE IF NOT EXISTS bill_items
(
    bill_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    bill_id INT UNSIGNED NOT NULL,

    item_type ENUM
    (
        'Service',
        'Lab Test',
        'Medicine',
        'Room Charge',
        'Other'
    ) NOT NULL,

    item_name VARCHAR(200) NOT NULL,

    quantity DECIMAL(10,2)
        NOT NULL DEFAULT 1.00,

    unit_price DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    discount_amount DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    tax_amount DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    total_amount DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    remarks VARCHAR(255) DEFAULT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bill_item_bill
        FOREIGN KEY (bill_id)
        REFERENCES billing(bill_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_bill (bill_id),

    INDEX idx_item_type (item_type)
);
CREATE TABLE IF NOT EXISTS payments
(
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    bill_id INT UNSIGNED NOT NULL,

    payment_no VARCHAR(30) NOT NULL,

    payment_method ENUM
    (
        'Cash',
        'Card',
        'UPI',
        'Net Banking',
        'Insurance',
        'Other'
    ) NOT NULL,

    amount DECIMAL(10,2)
        NOT NULL,

    transaction_reference VARCHAR(100)
        DEFAULT NULL,

    payment_status ENUM
    (
        'Pending',
        'Success',
        'Failed',
        'Refunded'
    ) NOT NULL DEFAULT 'Success',

    remarks VARCHAR(255)
        DEFAULT NULL,

    paid_at DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_payment_bill
        FOREIGN KEY (bill_id)
        REFERENCES billing(bill_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_payment_no
    (
        payment_no
    ),

    INDEX idx_bill (bill_id),

    INDEX idx_method (payment_method),

    INDEX idx_status (payment_status)
);

CREATE TABLE IF NOT EXISTS rooms
(
    room_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    room_number VARCHAR(20) NOT NULL,

    room_type ENUM
    (
        'General Ward',
        'Semi Private',
        'Private',
        'ICU',
        'NICU',
        'Operation Theatre',
        'Emergency'
    ) NOT NULL,

    floor_number VARCHAR(20) DEFAULT NULL,

    room_charge DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    status ENUM
    (
        'Available',
        'Maintenance',
        'Inactive'
    ) NOT NULL DEFAULT 'Available',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_room_number
    (
        room_number
    ),

    INDEX idx_room_type(room_type),

    INDEX idx_status(status)
);  CREATE TABLE IF NOT EXISTS beds
(
    bed_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    room_id INT UNSIGNED NOT NULL,

    bed_number VARCHAR(20) NOT NULL,

    bed_charge DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    status ENUM
    (
        'Available',
        'Occupied',
        'Reserved',
        'Maintenance'
    ) NOT NULL DEFAULT 'Available',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_bed_room
        FOREIGN KEY (room_id)
        REFERENCES rooms(room_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_room_bed
    (
        room_id,
        bed_number
    ),

    INDEX idx_room(room_id),

    INDEX idx_status(status)
);
CREATE TABLE IF NOT EXISTS admissions
(
    admission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    mapping_id INT UNSIGNED NOT NULL,

    appointment_id INT UNSIGNED DEFAULT NULL,

    bed_id INT UNSIGNED NOT NULL,

    admitted_by_doctor_id INT UNSIGNED NOT NULL,

    admission_no VARCHAR(30) NOT NULL,

    admission_date DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    expected_discharge_date DATE DEFAULT NULL,

    actual_discharge_date DATETIME DEFAULT NULL,

    admission_reason TEXT DEFAULT NULL,

    discharge_summary TEXT DEFAULT NULL,

    admission_status ENUM
    (
        'Admitted',
        'Discharged',
        'Transferred',
        'Cancelled'
    ) NOT NULL DEFAULT 'Admitted',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_admission_bed
        FOREIGN KEY (bed_id)
        REFERENCES beds(bed_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_admission_doctor
        FOREIGN KEY (admitted_by_doctor_id)
        REFERENCES doctors(doctor_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_admission_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(appointment_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    UNIQUE KEY uq_admission_no
    (
        admission_no
    ),

    INDEX idx_mapping(mapping_id),

    INDEX idx_bed(bed_id),

    INDEX idx_status(admission_status)
);