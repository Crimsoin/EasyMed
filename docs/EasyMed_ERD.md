# EasyMed - Entity Relationship Diagram

## Database Structure Overview

The EasyMed clinic management system uses a SQLite database with the following core entities and relationships:

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              EASYMED DATABASE SCHEMA                                 │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│      USERS       │◄────────┤     PATIENTS     │         │     DOCTORS      │
│                  │         │                  │         │                  │
│ • id (PK)        │         │ • id (PK)        │         │ • id (PK)        │
│ • username       │         │ • user_id (FK)   │         │ • user_id (FK)   │
│ • email          │         │ • phone          │         │ • specialty      │
│ • password       │         │ • date_of_birth  │         │ • license_number │
│ • role           │         │ • gender         │         │ • biography      │
│ • first_name     │         │ • address        │         │ • consultation_fee│
│ • last_name      │         │ • blood_type     │         │ • experience_years│
│ • status         │         │ • allergies      │         │ • education      │
│ • email_verified │         │ • medical_history│         │ • office_address │
│ • created_at     │         │ • emergency_*    │         │ • available_days │
│ • updated_at     │         │ • status         │         │ • schedule_*     │
└──────────────────┘         │ • created_at     │         │ • is_available   │
         │                   └──────────────────┘         │ • created_at     │
         │                            │                   └──────────────────┘
         │                            │                            │
         │                            │                            │
         └────────────────────────────┼────────────────────────────┘
                                      │
                                      ▼
                            ┌──────────────────┐
                            │   APPOINTMENTS   │
                            │                  │
                            │ • id (PK)        │
                            │ • patient_id (FK)│
                            │ • doctor_id (FK) │
                            │ • appointment_date│
                            │ • appointment_time│
                            │ • duration       │
                            │ • reason_for_visit│
                            │ • status         │
                            │ • notes          │
                            │ • patient_info   │
                            │ • created_at     │
                            │ • updated_at     │
                            └──────────────────┘
                                      │
                    ┌─────────────────┼─────────────────┐
                    │                 │                 │
                    ▼                 ▼                 ▼
          ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
          │    REVIEWS       │ │ ACTIVITY_LOGS    │ │ NOTIFICATIONS    │
          │                  │ │                  │ │                  │
          │ • id (PK)        │ │ • id (PK)        │ │ • id (PK)        │
          │ • patient_id (FK)│ │ • user_id (FK)   │ │ • user_id (FK)   │
          │ • doctor_id (FK) │ │ • activity_type  │ │ • title          │
          │ • appointment_id │ │ • description    │ │ • message        │
          │ • rating         │ │ • ip_address     │ │ • type           │
          │ • review_text    │ │ • user_agent     │ │ • is_read        │
          │ • is_anonymous   │ │ • created_at     │ │ • created_at     │
          │ • is_approved    │ └──────────────────┘ └──────────────────┘
          │ • created_at     │
          └──────────────────┘

┌──────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│ DOCTOR_SCHEDULES │         │  DOCTOR_BREAKS   │         │DOCTOR_UNAVAILABLE│
│                  │         │                  │         │                  │
│ • id (PK)        │         │ • id (PK)        │         │ • id (PK)        │
│ • doctor_id (FK) │         │ • doctor_id (FK) │         │ • doctor_id (FK) │
│ • day_of_week    │         │ • break_date     │         │ • unavailable_date│
│ • start_time     │         │ • start_time     │         │ • reason         │
│ • end_time       │         │ • end_time       │         │ • created_at     │
│ • slot_duration  │         │ • reason         │         └──────────────────┘
│ • is_available   │         │ • created_at     │
│ • created_at     │         └──────────────────┘
│ • updated_at     │
└──────────────────┘

┌──────────────────┐         ┌──────────────────┐
│  CLINIC_SETTINGS │         │   LAB_OFFERS     │
│                  │         │                  │
│ • id (PK)        │         │ • id (PK)        │
│ • setting_key    │         │ • name           │
│ • setting_value  │         │ • description    │
│ • description    │         │ • price          │
│ • created_at     │         │ • is_active      │
│ • updated_at     │         │ • created_at     │
└──────────────────┘         └──────────────────┘
                                      │
                                      ▼
                            ┌──────────────────┐
                            │LAB_OFFER_DOCTORS │
                            │                  │
                            │ • id (PK)        │
                            │ • lab_offer_id   │
                            │ • doctor_id      │
                            │ • created_at     │
                            └──────────────────┘
```

## Relationships Description

### Core User Management
- **Users** is the central authentication table
- **Patients** extends Users with medical/personal information (1:1 relationship)
- **Doctors** extends Users with professional information (1:1 relationship)

### Appointment System
- **Appointments** links Patients and Doctors (Many-to-Many through appointments)
- Patient can have multiple appointments with different doctors
- Doctor can have multiple appointments with different patients

### Review System
- **Reviews** allows patients to rate doctors after appointments
- Links to specific appointments for traceability

### Schedule Management
- **Doctor_Schedules** defines weekly availability patterns
- **Doctor_Breaks** handles temporary breaks during working hours
- **Doctor_Unavailable** manages full-day unavailability

### System Features
- **Activity_Logs** tracks all user actions for audit purposes
- **Notifications** manages system alerts and messages
- **Clinic_Settings** stores system-wide configuration

### Lab Services
- **Lab_Offers** defines available laboratory tests
- **Lab_Offer_Doctors** links which doctors can order which tests

## Key Constraints & Rules

### Status Enumerations
```sql
-- User roles
role: 'admin', 'doctor', 'patient'

-- User status
status: 'active', 'inactive', 'pending'

-- Appointment status
status: 'pending', 'rescheduled', 'scheduled', 'completed', 'cancelled', 'no_show'

-- Notification types
type: 'info', 'success', 'warning', 'error'
```

### Foreign Key Relationships
```sql
patients.user_id → users.id
doctors.user_id → users.id
appointments.patient_id → patients.id
appointments.doctor_id → doctors.id (references doctors table, not users)
reviews.patient_id → users.id
reviews.doctor_id → doctors.id
reviews.appointment_id → appointments.id
activity_logs.user_id → users.id
notifications.user_id → users.id
doctor_schedules.doctor_id → users.id
doctor_breaks.doctor_id → users.id
doctor_unavailable.doctor_id → users.id
lab_offer_doctors.doctor_id → doctors.id
lab_offer_doctors.lab_offer_id → lab_offers.id
```

## Database Features

### SQLite Specific
- Uses INTEGER PRIMARY KEY AUTOINCREMENT
- PRAGMA foreign_keys = ON for referential integrity
- Date functions: date('now'), datetime(), etc.
- CHECK constraints for enum-like behavior

### Indexing Strategy
```sql
-- Performance indexes
appointments: (patient_id, appointment_date), (doctor_id, appointment_date)
activity_logs: (user_id, activity_type), (created_at)
notifications: (user_id, is_read)
doctor_schedules: (doctor_id, day_of_week)
```

### Audit Trail
- All major tables include created_at timestamps
- Activity_logs captures user actions with IP and user agent
- Soft deletes possible through status fields

## Data Flow Examples

### Patient Books Appointment
1. Patient (users.role='patient') → patients table
2. Select doctor from doctors → users (role='doctor')
3. Create appointment record linking patient_id and doctor_id
4. Log activity in activity_logs
5. Send notification to doctor

### Doctor Reviews Appointments
1. Doctor logs in (users.role='doctor')
2. Find doctor record in doctors table by user_id
3. Query appointments by doctor_id
4. Join with patients and users for patient information
5. Display appointment details with patient info

This ERD represents a comprehensive clinic management system supporting multi-role users, appointment scheduling, review system, and complete audit capabilities.