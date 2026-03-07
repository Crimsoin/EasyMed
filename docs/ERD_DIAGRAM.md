# EasyMed - Entity Relationship Diagram (ERD)

## Database Schema Overview

This document describes the complete database structure for the EasyMed Patient Appointment Management System.

---

## Core Entities and Relationships

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          EASYMED DATABASE SCHEMA                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────┐
│       USERS          │ ◄──────────────────┐
├──────────────────────┤                    │
│ PK  id               │                    │
│     username         │                    │ 1
│     email            │                    │
│     password         │                    │
│     first_name       │                    │
│     last_name        │         ┌──────────┴──────────┐
│     phone            │         │      DOCTORS        │
│     date_of_birth    │         ├─────────────────────┤
│     gender           │         │ PK  id              │
│     role             │         │ FK  user_id         │
│     is_active        │         │     specialty       │
│     profile_image    │         │     license_number  │
│     email_verified   │         │     experience_years│
│     created_at       │         │     consultation_fee│
│     updated_at       │         │     biography       │
└──────────────────────┘         │     schedule_days   │
         │                       │     schedule_time_*  │
         │ 1                     │     is_available    │
         │                       │     phone           │
         │                       │     created_at      │
         │                       └─────────────────────┘
         │                                 │
         │                                 │ 1
         │                                 │
         │                                 │
         │                       ┌─────────┴─────────────────┐
         │                       │  DOCTOR_SCHEDULES         │
         │                       ├───────────────────────────┤
         │                       │ PK  id                    │
         │                       │ FK  doctor_id             │
         │                       │     day_of_week           │
         │                       │     start_time            │
         │                       │     end_time              │
         │                       │     slot_duration         │
         │                       │     is_available          │
         │                       │     created_at            │
         │                       └───────────────────────────┘
         │
         │ 1
         │
┌────────┴──────────┐
│     PATIENTS      │
├───────────────────┤
│ PK  id            │
│ FK  user_id       │
│     phone         │
│     date_of_birth │
│     gender        │
│     address       │
│     emergency_*   │
│     blood_type    │
│     status        │
│     created_at    │
└───────────────────┘
         │
         │ 1
         │
         │
         ▼  *
┌───────────────────────┐
│    APPOINTMENTS       │
├───────────────────────┤
│ PK  id                │
│ FK  patient_id        │◄────── References patients.id
│ FK  doctor_id         │◄────── References doctors.id
│     appointment_date  │
│     appointment_time  │
│     status            │
│     reason_for_visit  │
│     patient_info      │ (JSON)
│     notes             │
│     reference_number  │
│     created_at        │
│     updated_at        │
└───────────────────────┘
         │
         │ 1
         │
         ▼  *
┌───────────────────────┐
│      PAYMENTS         │
├───────────────────────┤
│ PK  id                │
│ FK  appointment_id    │
│     amount            │
│     payment_method    │
│     gcash_reference   │
│     receipt_path      │
│     payment_status    │
│     payment_notes     │
│     paid_at           │
│     created_at        │
│     updated_at        │
└───────────────────────┘


┌───────────────────────┐
│       REVIEWS         │
├───────────────────────┤
│ PK  id                │
│ FK  patient_id        │◄────── References patients.id
│ FK  doctor_id         │◄────── References doctors.id
│     rating            │ (1-5)
│     review_text       │
│     is_anonymous      │
│     is_approved       │
│     created_at        │
│     updated_at        │
└───────────────────────┘


┌───────────────────────┐           ┌────────────────────────┐
│     LAB_OFFERS        │           │  LAB_OFFER_DOCTORS     │
├───────────────────────┤           ├────────────────────────┤
│ PK  id                │ 1      * │ PK  id                 │
│     title             │◄─────────┤ FK  lab_offer_id       │
│     description       │           │ FK  doctor_id          │◄─── References doctors.id
│     price             │           │     created_at         │
│     is_active         │         * │     updated_at         │
│     created_at        │ *      1  └────────────────────────┘
│     updated_at        │           
└───────────────────────┘
```

---

## Entity Details

### 1. **USERS** (Core Authentication Table)
**Purpose**: Stores all user accounts (Admin, Doctor, Patient)

| Column          | Type      | Constraints                    | Description                          |
|-----------------|-----------|--------------------------------|--------------------------------------|
| id              | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique user identifier               |
| username        | VARCHAR   | UNIQUE, NOT NULL               | Login username                       |
| email           | VARCHAR   | UNIQUE, NOT NULL               | User email address                   |
| password        | VARCHAR   | NOT NULL                       | Hashed password                      |
| first_name      | VARCHAR   | NOT NULL                       | User's first name                    |
| last_name       | VARCHAR   | NOT NULL                       | User's last name                     |
| phone           | VARCHAR   | NULL                           | Contact phone number                 |
| date_of_birth   | DATE      | NULL                           | Date of birth                        |
| gender          | VARCHAR   | NULL                           | Gender (Male/Female/Other)           |
| role            | VARCHAR   | NOT NULL, DEFAULT 'patient'    | User role (admin/doctor/patient)     |
| is_active       | BOOLEAN   | DEFAULT 1                      | Account active status                |
| profile_image   | VARCHAR   | NULL                           | Profile picture path                 |
| email_verified  | BOOLEAN   | DEFAULT 0                      | Email verification status            |
| created_at      | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Account creation timestamp           |
| updated_at      | DATETIME  | NULL                           | Last update timestamp                |

**Relationships**:
- One-to-One with DOCTORS (when role = 'doctor')
- One-to-One with PATIENTS (when role = 'patient')

---

### 2. **DOCTORS** (Doctor-specific Information)
**Purpose**: Extended profile data for doctors

| Column              | Type      | Constraints                    | Description                          |
|---------------------|-----------|--------------------------------|--------------------------------------|
| id                  | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique doctor identifier             |
| user_id             | INTEGER   | FOREIGN KEY → users.id, UNIQUE | Reference to users table             |
| specialty           | VARCHAR   | NULL                           | Medical specialty                    |
| license_number      | VARCHAR   | NULL                           | Medical license number               |
| experience_years    | INTEGER   | NULL                           | Years of experience                  |
| consultation_fee    | DECIMAL   | DEFAULT 0.00                   | Standard consultation fee            |
| biography           | TEXT      | NULL                           | Doctor's biography                   |
| schedule_days       | VARCHAR   | NULL                           | Available days                       |
| schedule_time_start | TIME      | NULL                           | Work start time                      |
| schedule_time_end   | TIME      | NULL                           | Work end time                        |
| is_available        | BOOLEAN   | DEFAULT 1                      | Currently accepting appointments     |
| phone               | VARCHAR   | NULL                           | Office phone number                  |
| created_at          | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Record creation timestamp            |

**Relationships**:
- Many-to-One with USERS (doctors.user_id → users.id)
- One-to-Many with APPOINTMENTS
- One-to-Many with DOCTOR_SCHEDULES
- Many-to-Many with LAB_OFFERS (through LAB_OFFER_DOCTORS)
- One-to-Many with REVIEWS

---

### 3. **PATIENTS** (Patient-specific Information)
**Purpose**: Extended profile data for patients

| Column              | Type      | Constraints                    | Description                          |
|---------------------|-----------|--------------------------------|--------------------------------------|
| id                  | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique patient identifier            |
| user_id             | INTEGER   | FOREIGN KEY → users.id, UNIQUE | Reference to users table             |
| phone               | VARCHAR   | NULL                           | Contact phone number                 |
| date_of_birth       | DATE      | NULL                           | Date of birth                        |
| gender              | VARCHAR   | NULL                           | Gender                               |
| address             | TEXT      | NULL                           | Home address                         |
| emergency_contact   | VARCHAR   | NULL                           | Emergency contact name               |
| emergency_phone     | VARCHAR   | NULL                           | Emergency contact phone              |
| blood_type          | VARCHAR   | NULL                           | Blood type (A+, B+, O-, etc.)        |
| status              | VARCHAR   | DEFAULT 'active'               | Patient status                       |
| created_at          | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Record creation timestamp            |

**Relationships**:
- Many-to-One with USERS (patients.user_id → users.id)
- One-to-Many with APPOINTMENTS
- One-to-Many with REVIEWS

---

### 4. **APPOINTMENTS** (Appointment Bookings)
**Purpose**: Tracks all appointment bookings between patients and doctors

| Column              | Type      | Constraints                    | Description                          |
|---------------------|-----------|--------------------------------|--------------------------------------|
| id                  | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique appointment identifier        |
| patient_id          | INTEGER   | FOREIGN KEY → patients.id      | Reference to patient                 |
| doctor_id           | INTEGER   | FOREIGN KEY → doctors.id       | Reference to doctor                  |
| appointment_date    | DATE      | NOT NULL                       | Scheduled date                       |
| appointment_time    | TIME      | NOT NULL                       | Scheduled time                       |
| status              | VARCHAR   | DEFAULT 'pending'              | Status (pending/scheduled/completed/ cancelled/no_show/rescheduled) |
| reason_for_visit    | TEXT      | NULL                           | Appointment reason                   |
| patient_info        | JSON      | NULL                           | Additional patient data (purpose, laboratory, etc.) |
| notes               | TEXT      | NULL                           | Doctor's notes                       |
| reference_number    | VARCHAR   | UNIQUE, NULL                   | Unique appointment reference         |
| created_at          | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Booking timestamp                    |
| updated_at          | DATETIME  | NULL                           | Last update timestamp                |

**Relationships**:
- Many-to-One with PATIENTS (appointments.patient_id → patients.id)
- Many-to-One with DOCTORS (appointments.doctor_id → doctors.id)
- One-to-Many with PAYMENTS

**Appointment Statuses**:
- `pending` - Awaiting confirmation
- `scheduled` - Confirmed appointment
- `completed` - Appointment finished
- `cancelled` - Cancelled by patient/doctor
- `no_show` - Patient didn't show up
- `rescheduled` - Appointment was rescheduled

---

### 5. **PAYMENTS** (Payment Tracking)
**Purpose**: Records payment transactions for appointments

| Column              | Type      | Constraints                    | Description                          |
|---------------------|-----------|--------------------------------|--------------------------------------|
| id                  | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique payment identifier            |
| appointment_id      | INTEGER   | FOREIGN KEY → appointments.id  | Reference to appointment             |
| amount              | DECIMAL   | NOT NULL                       | Payment amount                       |
| payment_method      | VARCHAR   | NULL                           | Payment method (GCash, Cash, etc.)   |
| gcash_reference     | VARCHAR   | NULL                           | GCash reference number               |
| receipt_path        | VARCHAR   | NULL                           | Path to receipt file                 |
| payment_status      | VARCHAR   | DEFAULT 'pending'              | Status (pending/verified/rejected)   |
| payment_notes       | TEXT      | NULL                           | Additional payment notes             |
| paid_at             | DATETIME  | NULL                           | Payment timestamp                    |
| created_at          | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Record creation timestamp            |
| updated_at          | DATETIME  | NULL                           | Last update timestamp                |

**Relationships**:
- Many-to-One with APPOINTMENTS (payments.appointment_id → appointments.id)

---

### 6. **REVIEWS** (Doctor Reviews)
**Purpose**: Patient reviews and ratings for doctors

| Column              | Type      | Constraints                    | Description                          |
|---------------------|-----------|--------------------------------|--------------------------------------|
| id                  | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique review identifier             |
| patient_id          | INTEGER   | FOREIGN KEY → patients.id      | Reference to patient                 |
| doctor_id           | INTEGER   | FOREIGN KEY → doctors.id       | Reference to doctor                  |
| rating              | INTEGER   | NOT NULL, CHECK (1-5)          | Rating (1 to 5 stars)                |
| review_text         | TEXT      | NULL                           | Review text                          |
| is_anonymous        | BOOLEAN   | DEFAULT 0                      | Anonymous review flag                |
| is_approved         | BOOLEAN   | DEFAULT 0                      | Admin approval status                |
| created_at          | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Review submission timestamp          |
| updated_at          | DATETIME  | NULL                           | Last update timestamp                |

**Relationships**:
- Many-to-One with PATIENTS (reviews.patient_id → patients.id)
- Many-to-One with DOCTORS (reviews.doctor_id → doctors.id)

---

### 7. **DOCTOR_SCHEDULES** (Weekly Availability)
**Purpose**: Defines doctor's weekly working schedule

| Column              | Type      | Constraints                    | Description                          |
|---------------------|-----------|--------------------------------|--------------------------------------|
| id                  | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique schedule identifier           |
| doctor_id           | INTEGER   | FOREIGN KEY → doctors.id       | Reference to doctor                  |
| day_of_week         | INTEGER   | NOT NULL, CHECK (0-6)          | Day (0=Sunday, 6=Saturday)           |
| start_time          | TIME      | NOT NULL                       | Work start time                      |
| end_time            | TIME      | NOT NULL                       | Work end time                        |
| slot_duration       | INTEGER   | DEFAULT 30                     | Appointment slot duration (minutes)  |
| is_available        | BOOLEAN   | DEFAULT 1                      | Available on this day                |
| created_at          | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Record creation timestamp            |

**Relationships**:
- Many-to-One with DOCTORS (doctor_schedules.doctor_id → doctors.id)

---

### 8. **LAB_OFFERS** (Laboratory Services)
**Purpose**: Available laboratory tests and services

| Column              | Type      | Constraints                    | Description                          |
|---------------------|-----------|--------------------------------|--------------------------------------|
| id                  | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique lab offer identifier          |
| title               | VARCHAR   | NOT NULL                       | Lab test name                        |
| description         | TEXT      | NULL                           | Lab test description                 |
| price               | DECIMAL   | DEFAULT 0.00                   | Lab test price                       |
| is_active           | BOOLEAN   | DEFAULT 1                      | Currently offered                    |
| created_at          | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Record creation timestamp            |
| updated_at          | DATETIME  | NULL                           | Last update timestamp                |

**Relationships**:
- Many-to-Many with DOCTORS (through LAB_OFFER_DOCTORS)

---

### 11. **LAB_OFFER_DOCTORS** (Junction Table)
**Purpose**: Links doctors to laboratory services they offer

| Column              | Type      | Constraints                    | Description                          |
|---------------------|-----------|--------------------------------|--------------------------------------|
| id                  | INTEGER   | PRIMARY KEY, AUTO_INCREMENT    | Unique link identifier               |
| lab_offer_id        | INTEGER   | FOREIGN KEY → lab_offers.id    | Reference to lab offer               |
| doctor_id           | INTEGER   | FOREIGN KEY → doctors.id       | Reference to doctor                  |
| created_at          | DATETIME  | DEFAULT CURRENT_TIMESTAMP      | Record creation timestamp            |
| updated_at          | DATETIME  | NULL                           | Last update timestamp                |

**Relationships**:
- Many-to-One with LAB_OFFERS (lab_offer_doctors.lab_offer_id → lab_offers.id)
- Many-to-One with DOCTORS (lab_offer_doctors.doctor_id → doctors.id)

**Composite Unique Key**: (lab_offer_id, doctor_id)

---

## Key Relationships Summary

### One-to-One Relationships:
1. **USERS → DOCTORS** (user_id)
2. **USERS → PATIENTS** (user_id)

### One-to-Many Relationships:
1. **DOCTORS → APPOINTMENTS** (doctor_id)
2. **PATIENTS → APPOINTMENTS** (patient_id)
3. **DOCTORS → DOCTOR_SCHEDULES** (doctor_id)
4. **DOCTORS → REVIEWS** (doctor_id)
5. **PATIENTS → REVIEWS** (patient_id)
6. **APPOINTMENTS → PAYMENTS** (appointment_id)

### Many-to-Many Relationships:
1. **DOCTORS ↔ LAB_OFFERS** (through LAB_OFFER_DOCTORS junction table)

---

## Database Cardinality Notation

```
1       = One (and only one)
*       = Many (zero or more)
1..*    = One or more
0..1    = Zero or one (optional)
```

---

## Business Rules

1. **User Management**:
   - Every user must have a unique username and email
   - Users can have only one role (admin, doctor, or patient)
   - Passwords must be hashed before storage

2. **Appointments**:
   - An appointment must reference valid patient and doctor IDs
   - Appointment times must not conflict with doctor's breaks or unavailability
   - Each appointment must have a unique reference number

3. **Payments**:
   - A payment must be linked to an existing appointment
   - Payment amounts are determined by consultation fees or lab offer prices

4. **Reviews**:
   - Reviews require admin approval before being displayed publicly
   - Ratings must be between 1 and 5 stars
   - Reviews can be anonymous

5. **Doctor Schedules**:
   - Doctors can have multiple weekly schedules
   - Schedule slots are configurable per doctor

6. **Lab Offers**:
   - Lab offers can be associated with multiple doctors
   - Only active lab offers are displayed to patients
   - Prices can vary by lab offer

---

## Indexes for Performance

Recommended indexes for optimal query performance:

```sql
-- USERS table
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_role ON users(role);

-- DOCTORS table
CREATE INDEX idx_doctors_user_id ON doctors(user_id);
CREATE INDEX idx_doctors_specialty ON doctors(specialty);

-- PATIENTS table
CREATE INDEX idx_patients_user_id ON patients(user_id);

-- APPOINTMENTS table
CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_appointments_doctor ON appointments(doctor_id);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_appointments_reference ON appointments(reference_number);

-- PAYMENTS table
CREATE INDEX idx_payments_appointment ON payments(appointment_id);
CREATE INDEX idx_payments_status ON payments(payment_status);

-- REVIEWS table
CREATE INDEX idx_reviews_doctor ON reviews(doctor_id);
CREATE INDEX idx_reviews_approved ON reviews(is_approved);

-- DOCTOR_SCHEDULES table
CREATE INDEX idx_schedules_doctor ON doctor_schedules(doctor_id);

-- LAB_OFFER_DOCTORS table
CREATE INDEX idx_lab_offer_doctors_lab ON lab_offer_doctors(lab_offer_id);
CREATE INDEX idx_lab_offer_doctors_doc ON lab_offer_doctors(doctor_id);
```

---

## Foreign Key Constraints

All foreign key relationships enforce referential integrity with the following default behavior:
- **ON DELETE**: CASCADE or RESTRICT (depending on relationship)
- **ON UPDATE**: CASCADE

---

## Document Version

- **Created**: December 13, 2025
- **Last Updated**: December 13, 2025
- **Database Type**: SQLite (with MySQL compatibility)
- **System**: EasyMed Patient Appointment Management System

---

## Notes

1. The system uses SQLite by default but supports MySQL
2. Foreign key constraints are enabled in SQLite via `PRAGMA foreign_keys = ON`
3. JSON fields (like `patient_info` in appointments) store flexible data structures
4. All timestamps use datetime format compatible with both SQLite and MySQL
5. The system implements soft deletes via `is_active` flags where appropriate
