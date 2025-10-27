# 📊 EasyMed Admin Dashboard Statistics

Complete overview of all statistics and analytics available in the admin panel.

---

## 🏠 Main Admin Dashboard (`/admin/Dashboard/dashboard.php`)

### Overview Statistics Cards

#### 1. **Total Users**
- **Count:** All active users (admin + doctor + patient)
- **Query:** Counts users where `is_active = 1`
- **Display:** Total number with users icon

#### 2. **Doctors**
- **Count:** Active doctors only
- **Query:** `WHERE role = 'doctor' AND is_active = 1`
- **Display:** Doctor count with medical icon

#### 3. **Patients**
- **Count:** Active patients only
- **Query:** `WHERE role = 'patient' AND is_active = 1`
- **Display:** Patient count with user icon

#### 4. **Total Appointments**
- **Count:** All appointments regardless of status
- **Query:** `SELECT COUNT(*) FROM appointments`
- **Display:** Total appointment count

#### 5. **System Logs**
- **Count:** Total system log entries
- **Query:** `SELECT COUNT(*) FROM system_logs`
- **Display:** Log count (0 if table doesn't exist)

### System Overview Section

#### Additional Metrics:
- **Pending Appointments:** Appointments with status = 'pending'
- **Today's Appointments:** Appointments scheduled for current date
- **System Administrators:** Count of users with role = 'admin'

### Recent Data Tables

#### Recent Users Table:
Shows last 5 users with:
- ID
- Full Name
- Email
- Role (with colored badge)
- Created Date
- Quick Actions (View/Edit)

### Quick Actions Cards

Access to:
- Add New User
- Manage Users
- Manage Doctors
- View Appointments

### Real-Time Features

- **Live Date & Time Display:** Updates every second
- Shows current date in full format
- Shows time in 12-hour format with AM/PM

---

## 📈 Reports & Analytics (`/admin/Report and Analytics/reports.php`)

### Key Performance Indicators (KPIs)

#### 1. **Total Appointments Card**
- **Primary:** Total appointments in date range
- **Secondary:** Completion rate percentage
- **Filterable:** By custom date range

#### 2. **Completed Appointments Card**
- **Primary:** Completed appointments count
- **Secondary:** Scheduled appointments count
- **Color:** Green indicator

#### 3. **Active Doctors Card**
- **Primary:** Number of active doctors
- **Secondary:** Total doctor count
- **Query:** Active vs total doctors

#### 4. **New Patients Card**
- **Primary:** New patient registrations in period
- **Secondary:** Total patient count
- **Growth Metric:** Patient acquisition

#### 5. **System Logs Card**
- **Primary:** Activity logs in date range
- **Secondary:** "Account activity tracking"
- **Feature:** Includes clear logs button

---

### 📅 Appointments Overview (Dynamic)

**Interactive Period Filters:**
- **Daily:** Today's statistics
- **Weekly:** Current week statistics
- **Monthly:** Current month statistics

**Real-time Statistics:**

1. **Total Appointments**
   - Current period count
   - Percentage change vs previous period
   - Arrow indicator (up/down/neutral)

2. **Completed Appointments**
   - Completed count for period
   - Change percentage
   - Positive indicator

3. **Pending/Scheduled**
   - Combined pending + scheduled
   - Change tracking
   - Alert indicator if high

4. **Cancelled Appointments**
   - Cancellation count
   - Cancellation rate
   - Warning indicator

**Features:**
- AJAX-powered live updates
- No page reload needed
- Smooth transitions
- Color-coded indicators

---

### 📊 Appointment Performance Metrics

#### Appointment Statistics:
- **Total:** All appointments in date range
- **Completed:** Successfully finished appointments
- **Cancelled:** Cancelled appointments count
- **Scheduled:** Confirmed upcoming appointments
- **Pending:** Awaiting confirmation

#### Calculated Rates:
- **Completion Rate:** (Completed / Total) × 100
- **Cancellation Rate:** (Cancelled / Total) × 100

---

### 📈 Daily Appointment Trends Table

Shows day-by-day breakdown:
- **Date:** Each day in selected range
- **Total:** Daily appointment count
- **Completed:** Daily completed count
- **Scheduled:** Daily scheduled count
- **Cancelled:** Daily cancellation count
- **Completion Rate:** Daily completion percentage

---

### 👨‍⚕️ Doctor Performance Table

Per-doctor analytics showing:

1. **Doctor Name:** Full name with "Dr." prefix
2. **Specialty:** Medical specialization badge
3. **Total Appointments:** All appointments for doctor
4. **Completed:** Successfully completed appointments
5. **Cancelled:** Cancelled appointments
6. **Completion Rate:** Doctor's success rate
   - **Good:** ≥80% (green)
   - **Average:** 60-79% (yellow)
   - **Poor:** <60% (red)

**Sorting:** By total appointments (descending)

---

### 🕐 Hourly Appointment Distribution

**Visual Grid Display:**
- Shows appointments by hour (00:00 to 23:00)
- Each card displays:
  - Hour time slot
  - Total appointments in that hour
  - Cancellations in that hour
- **Purpose:** Identify peak hours and capacity planning

---

### 📋 System Logs - Account Activity

**Detailed Activity Tracking:**

#### Columns:
1. **Date & Time:** Timestamp of activity
2. **User:** Who performed the action
   - Full name
   - Username
   - Role badge
3. **Role:** User role (Admin/Doctor/Patient/System)
4. **Action:** Type of activity with icon
5. **Description:** Detailed activity description

#### Tracked Actions:
- 🔐 **Login:** User sign-in
- 🚪 **Logout:** User sign-out
- ➕ **Register:** New account creation
- ✏️ **Update Profile:** Profile modifications
- 📅 **Book Appointment:** New booking
- ❌ **Cancel Appointment:** Booking cancellation
- 👁️ **View Profile:** Profile access
- ⚙️ **Other:** Various system actions

#### Features:
- **Limit:** Shows last 50 logs in date range
- **Clear Function:** Admin can clear all logs
- **Filterable:** By date range
- **Role Color Coding:**
  - Admin: Red badge
  - Doctor: Green badge
  - Patient: Blue badge
  - System: Gray badge

---

### 💡 Key Insights Summary

#### Appointment Performance Insights:
- ✅ Overall completion rate percentage
- ❌ Overall cancellation rate percentage
- 📊 Total appointments in selected period

#### Practice Growth Insights:
- 👥 New patient count
- 👨‍⚕️ Active doctor count
- 📈 Total patient base

---

## 🎯 Statistical Calculations

### Completion Rate Formula:
```
Completion Rate = (Completed Appointments / Total Appointments) × 100
```

### Cancellation Rate Formula:
```
Cancellation Rate = (Cancelled Appointments / Total Appointments) × 100
```

### Period Change Calculation:
```
Change % = ((Current Period - Previous Period) / Previous Period) × 100
```

### Doctor Performance Score:
```
Doctor Score = (Completed / Total Appointments) × 100
```

---

## 📊 Data Visualization Features

### Visual Elements:

1. **Stat Cards:** Large number displays with icons
2. **Color Indicators:**
   - 🔵 Primary Cyan: Main metrics
   - 🟢 Green: Positive/Completed
   - 🔴 Red: Negative/Cancelled
   - 🟡 Yellow: Warning/Pending
   - ⚪ Gray: Neutral/Inactive

3. **Progress Indicators:**
   - Arrow up: Positive growth
   - Arrow down: Negative growth
   - Dash: No change

4. **Badge System:**
   - Role badges (Admin/Doctor/Patient)
   - Specialty badges
   - Status badges (Active/Inactive)

5. **Interactive Tables:**
   - Sortable columns
   - Hover effects
   - Row highlighting
   - Action buttons

---

## 🔍 Filtering & Date Range

### Available Filters:

1. **Date Range Selection:**
   - Start Date
   - End Date
   - Default: Last 30 days

2. **Period Filters (Appointments Overview):**
   - Daily (today)
   - Weekly (current week)
   - Monthly (current month)

3. **Report Types:**
   - Overview (default)
   - Custom range

### Date Validation:
- Ensures start ≤ end date
- Auto-corrects invalid dates
- Default fallback to last 30 days

---

## 🎨 Visual Dashboard Components

### Dashboard Layout:

```
┌─────────────────────────────────────────────────┐
│  Header: Welcome + Current Date/Time            │
├─────────────────────────────────────────────────┤
│  [Stats Cards Row]                              │
│  Users | Doctors | Patients | Appts | Logs     │
├─────────────────────────────────────────────────┤
│  Recent Users Table                              │
├─────────────────────────────────────────────────┤
│  Quick Actions Grid                              │
├─────────────────────────────────────────────────┤
│  System Overview                                 │
│  Pending | Today | Admins                       │
└─────────────────────────────────────────────────┘
```

### Reports Layout:

```
┌─────────────────────────────────────────────────┐
│  Header: Reports & Analytics + Date Range       │
├─────────────────────────────────────────────────┤
│  [KPI Cards Row]                                │
│  Total | Completed | Doctors | Patients | Logs │
├─────────────────────────────────────────────────┤
│  Appointments Overview (Filterable)             │
│  [Daily | Weekly | Monthly]                     │
│  Total | Completed | Pending | Cancelled        │
├─────────────────────────────────────────────────┤
│  Daily Trends Table                             │
├─────────────────────────────────────────────────┤
│  Doctor Performance Table                       │
├─────────────────────────────────────────────────┤
│  Hourly Distribution Grid                       │
├─────────────────────────────────────────────────┤
│  System Logs (with Clear button)               │
├─────────────────────────────────────────────────┤
│  Key Insights Summary                           │
└─────────────────────────────────────────────────┘
```

---

## 📱 Responsive Features

### Mobile Optimization:
- Grid layouts adjust for small screens
- Tables become scrollable
- Cards stack vertically
- Touch-friendly buttons
- Optimized font sizes

---

## 🚀 Real-Time Updates

### Live Features:

1. **Clock Widget:**
   - Updates every second
   - Shows full date + time
   - Philippine timezone (Asia/Manila)

2. **AJAX Statistics:**
   - Appointments overview updates without reload
   - Smooth data transitions
   - Loading indicators
   - Error handling

---

## 💾 Database Tables Used

### Primary Tables:
- `users` - User accounts
- `doctors` - Doctor profiles
- `patients` - Patient profiles
- `appointments` - Booking records
- `activity_logs` - System activity
- `system_logs` - System events (if exists)

### Key Relationships:
```sql
users (1) ──→ (1) doctors
users (1) ──→ (1) patients
doctors (1) ──→ (M) appointments
patients (1) ──→ (M) appointments
users (1) ──→ (M) activity_logs
```

---

## 🎯 Current Statistics Summary

Based on your database scan:

### Your System Statistics:
- **Total Users:** 7
  - **Admin:** 1 (admin@easymed.com)
  - **Doctors:** 2
    - Dr. Iriz Debuton (Iriz.Debuton.EM@gmail.com)
    - Dr. Eulogio Condeza (Eulogio.Condeza.EM@gmail.com)
  - **Patients:** 4
    - Juan Dela Cruz
    - Angela Ramirez
    - Jim Tan
    - James Paul Tan

### Available Analytics:
✅ User management statistics  
✅ Appointment tracking  
✅ Doctor performance metrics  
✅ Patient growth analytics  
✅ System activity logs  
✅ Hourly distribution analysis  
✅ Completion rate calculations  
✅ Cancellation rate tracking  

---

## 📞 Accessing Statistics

### Dashboard:
```
URL: http://localhost/Project_EasyMed/admin/Dashboard/dashboard.php
Login: admin@easymed.com
```

### Reports:
```
URL: http://localhost/Project_EasyMed/admin/Report and Analytics/reports.php
Login: admin@easymed.com
```

---

**All statistics update in real-time based on your database data!** 🎉