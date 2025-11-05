<?php
$page_title = "My Appointments";
$additional_css = ['base.css', 'doctor/sidebar-doctor.css', 'doctor/dashboard-doctor.css'];
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/email.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}


$db = Database::getInstance();
// Get doctor record ID from doctors table
$doctor_user_id = $_SESSION['user_id'];
$doctor_record = $db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$doctor_user_id]);
if (!$doctor_record) {
    die("Doctor profile not found.");
}
$doctor_id = $doctor_record['id'];

// Handle appointment status updates
if ($_POST && isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];
    
    // Get appointment and patient details before updating
    $appointment_details = $db->fetch("
        SELECT a.*, 
               pu.first_name as patient_first_name, pu.last_name as patient_last_name, pu.email as patient_email,
               du.first_name as doctor_first_name, du.last_name as doctor_last_name,
               d.specialty, d.consultation_fee
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users pu ON p.user_id = pu.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users du ON d.user_id = du.id
        WHERE a.id = ? AND a.doctor_id = ?
    ", [$appointment_id, $doctor_id]);
    
    if ($appointment_details) {
        $emailService = new EmailService();
        $patient_email = $appointment_details['patient_email'];
        $patient_name = $appointment_details['patient_first_name'] . ' ' . $appointment_details['patient_last_name'];
        $doctor_name = 'Dr. ' . $appointment_details['doctor_first_name'] . ' ' . $appointment_details['doctor_last_name'];
        
        $appointment_data = [
            'appointment_id' => $appointment_id,
            'patient_name' => $patient_name,
            'doctor_name' => $doctor_name,
            'specialty' => $appointment_details['specialty'],
            'appointment_date' => formatDate($appointment_details['appointment_date']),
            'appointment_time' => formatTime($appointment_details['appointment_time']),
            'reason' => $appointment_details['reason_for_visit'] ?? 'General consultation',
            'fee' => number_format($appointment_details['consultation_fee'], 2)
        ];
        
        switch ($action) {
            case 'no_show':
                $db->update('appointments', ['status' => 'no_show'], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as no show.";
                // Note: We might not want to send email for no-show, or send a different type
                break;
            case 'complete':
                $db->update('appointments', ['status' => 'completed'], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as completed.";
                // Send completion confirmation email (optional)
                try {
                    // You can create a completion email template or use existing one
                    if ($patient_email) {
                        // For now, we'll skip completion emails, but you can add this later
                        // $emailService->sendAppointmentCompleted($patient_email, $patient_name, $appointment_data);
                    }
                } catch (Exception $e) {
                    error_log("Email notification error for completed appointment #$appointment_id: " . $e->getMessage());
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query conditions
$conditions = ["a.doctor_id = ?"];
$params = [$doctor_id];

if ($status_filter !== 'all') {
    $conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'today':
            $conditions[] = "DATE(a.appointment_date) = date('now')";
            break;
        case 'tomorrow':
            $conditions[] = "DATE(a.appointment_date) = date('now', '+1 day')";
            break;
        case 'this_week':
            $conditions[] = "a.appointment_date BETWEEN date('now') AND date('now', '+7 days')";
            break;
        case 'next_week':
            $conditions[] = "a.appointment_date BETWEEN date('now', '+7 days') AND date('now', '+14 days')";
            break;
        case 'past':
            $conditions[] = "a.appointment_date < date('now')";
            break;
    }
}

if (!empty($search)) {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR a.reason LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $conditions);

// Get appointments with pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$appointments = $db->fetchAll("
    SELECT a.*, 
           u.first_name as patient_first_name, 
           u.last_name as patient_last_name, 
           u.email as patient_email,
           p.phone as patient_phone,
           p.gender as patient_gender
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE {$where_clause}
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT {$per_page} OFFSET {$offset}
", $params);

// Get total count for pagination
$total_appointments = $db->fetch("
    SELECT COUNT(*) as count
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE {$where_clause}
", $params)['count'];

$total_pages = ceil($total_appointments / $per_page);

// Get quick stats
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?", [$doctor_id])['count'],
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'pending'", [$doctor_id])['count'],
    'scheduled' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'scheduled'", [$doctor_id])['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed'", [$doctor_id])['count'],
    'no_show' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'no_show'", [$doctor_id])['count'],
    'today' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = date('now')", [$doctor_id])['count']
];

require_once '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-md"></i> Doctor Portal</h3>
            <p>Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_doctor.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="appointments.php" class="nav-item active">
                <i class="fas fa-calendar-alt"></i> My Appointments
            </a>
            <a href="schedule.php" class="nav-item">
                <i class="fas fa-clock"></i> Schedule
            </a>
            <a href="patients.php" class="nav-item">
                <i class="fas fa-users"></i> My Patients
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-cog"></i> Profile
            </a>
        </nav>
    </div>

    <div class="doctor-content">
        <div class="content-header">
            <h1><i class="fas fa-calendar-alt"></i> My Appointments</h1>
            <p>Manage and view all your patient appointments</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="content-section">
            <div class="section-header">
                <h2>Appointment Overview</h2>
            </div>
            <div class="section-content stats-content">
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['scheduled']; ?></div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['completed']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['no_show']; ?></div>
                        <div class="stat-label">No Show</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="content-section">
            <div class="section-header">
                <h2>Filter Appointments</h2>
            </div>
            <div class="section-content">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="status">Status:</label>
                            <select name="status" id="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date">Date Range:</label>
                            <select name="date" id="date">
                                <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Dates</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="tomorrow" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                                <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="next_week" <?php echo $date_filter === 'next_week' ? 'selected' : ''; ?>>Next Week</option>
                                <option value="past" <?php echo $date_filter === 'past' ? 'selected' : ''; ?>>Past Appointments</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Search:</label>
                            <input type="text" name="search" id="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Patient name or reason...">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="content-section">
            <div class="section-header">
                <h2>Appointments (<?php echo $total_appointments; ?> total)</h2>
            </div>
            <div class="section-content">
                <?php if (empty($appointments)): ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No appointments found</h3>
                        <p>No appointments match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="appointments-table-container">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Patient</th>
                                    <th>Contact</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr class="appointment-row status-<?php echo $appointment['status']; ?>">
                                        <td class="date-time">
                                            <div class="appointment-date">
                                                <?php echo formatDate($appointment['appointment_date']); ?>
                                            </div>
                                            <div class="appointment-time">
                                                <?php echo formatTime($appointment['appointment_time']); ?>
                                            </div>
                                        </td>
                                        <td class="patient-info">
                                            <div class="patient-name">
                                                <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                            </div>
                                            <?php if (!empty($appointment['date_of_birth'])): ?>
                                                <div class="patient-age">
                                                    Age: <?php echo calculateAge($appointment['date_of_birth']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="contact-info">
                                            <?php if ($appointment['patient_email']): ?>
                                                <div class="email">
                                                    <i class="fas fa-envelope"></i>
                                                    <a href="mailto:<?php echo htmlspecialchars($appointment['patient_email']); ?>">
                                                        <?php echo htmlspecialchars($appointment['patient_email']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($appointment['patient_phone']): ?>
                                                <div class="phone">
                                                    <i class="fas fa-phone"></i>
                                                    <a href="tel:<?php echo htmlspecialchars($appointment['patient_phone']); ?>">
                                                        <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="reason">
                                            <?php 
                                            // Use reason_for_visit, fallback to patient_info['laboratory'] if available
                                            $patient_info = [];
                                            if (!empty($appointment['patient_info'])) {
                                                $patient_info = json_decode($appointment['patient_info'], true) ?? [];
                                            }
                                            $reason = $appointment['reason_for_visit'] ?? ($patient_info['laboratory'] ?? '');
                                            echo htmlspecialchars($reason);
                                            ?>
                                        </td>

                                        <td class="status">
                                            <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <div class="action-buttons">
                                                <?php 
                                                $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                                                $is_past = $appointment_datetime < time();
                                                $is_today = date('Y-m-d', $appointment_datetime) === date('Y-m-d');
                                                ?>
                                                
                                                <?php if ($appointment['status'] === 'scheduled'): ?>
                                                    <?php if ($is_past || $is_today): ?>
                                                        <form method="POST" style="display: inline;"
                                                              onsubmit="return confirm('Are you sure you want to mark this appointment as completed?')">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn btn-sm btn-primary" title="Mark as Completed">
                                                                <i class="fas fa-check-double"></i>
                                                            </button>
                                                        </form>

                                                        <form method="POST" style="display: inline;"
                                                              onsubmit="return confirm('Are you sure you want to mark this appointment as no show?')">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="action" value="no_show">
                                                            <button type="submit" class="btn btn-sm btn-warning" title="Mark as No Show">
                                                                <i class="fas fa-user-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <!-- Future appointments - no action buttons available -->
                                                        <span class="text-muted">Appointment scheduled for future</span>
                                                    <?php endif; ?>
                                                <?php elseif (in_array($appointment['status'], ['pending', 'rescheduled'])): ?>
                                                    <?php if ($is_past): ?>
                                                        <!-- Past pending/rescheduled appointments - allow completion or no-show -->
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to mark this appointment as completed?')">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn btn-sm btn-primary" title="Mark as Completed">
                                                                <i class="fas fa-check-double"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to mark this appointment as no show?')">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="action" value="no_show">
                                                            <button type="submit" class="btn btn-sm btn-warning" title="Mark as No Show">
                                                                <i class="fas fa-user-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($is_today): ?>
                                                        <!-- Today's pending/rescheduled appointments - allow completion or no-show -->
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to mark this appointment as completed?')">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn btn-sm btn-primary" title="Mark as Completed">
                                                                <i class="fas fa-check-double"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to mark this appointment as no show? This should only be used if the patient confirmed they will not attend.')">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="action" value="no_show">
                                                            <button type="submit" class="btn btn-sm btn-warning" title="Mark as No Show">
                                                                <i class="fas fa-user-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <!-- Future pending/rescheduled appointments - only allow no-show with warning -->
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to mark this appointment as no show? This should only be used if the patient confirmed they will not attend.')">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="action" value="no_show">
                                                            <button type="submit" class="btn btn-sm btn-warning" title="Mark as No Show">
                                                                <i class="fas fa-user-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                       class="pagination-link">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                       class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                       class="pagination-link">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="pagination-info">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_appointments); ?> of <?php echo $total_appointments; ?> appointments
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('#status, #date');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Add search functionality with debounce
    const searchInput = document.getElementById('search');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
