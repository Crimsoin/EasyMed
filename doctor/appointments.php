<?php
$page_title = "My Appointments";
$additional_css = ['base.css', 'doctor/sidebar-doctor.css', 'doctor/appointments-doctor.css', 'shared-modal.css'];
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
$doctor_record = $db->fetch("SELECT id, specialty FROM doctors WHERE user_id = ?", [$doctor_user_id]);
if (!$doctor_record) {
    die("Doctor profile not found.");
}
$doctor_id = $doctor_record['id'];
$doctor_specialty = $doctor_record['specialty'] ?? 'Medical Practitioner';

// Handle appointment status updates
if ($_POST && isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];
    
    // Get appointment details
    $appointment_details = $db->fetch("SELECT id, status FROM appointments WHERE id = ? AND doctor_id = ?", [$appointment_id, $doctor_id]);
    
    if ($appointment_details) {
        switch ($action) {
            case 'complete':
                $notes = $_POST['notes'] ?? '';
                $db->update('appointments', [
                    'status' => 'completed',
                    'notes' => $notes,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as completed successfully.";
                // Redirect to avoid form resubmission and refresh list
                header("Location: appointments.php?success=" . urlencode($success_message));
                exit();
            case 'no_show':
                $db->update('appointments', ['status' => 'no_show'], 'id = ?', [$appointment_id]);
                $success_message = "Appointment marked as No Show.";
                header("Location: appointments.php?success=" . urlencode($success_message));
                exit();
            case 'update_findings':
                $notes = $_POST['notes'] ?? '';
                $db->update('appointments', [
                    'notes' => $notes,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$appointment_id]);
                $success_message = "Appointment findings updated successfully.";
                header("Location: appointments.php?success=" . urlencode($success_message));
                exit();
        }
    }
}

if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
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
           COALESCE(p.phone, u.phone) as patient_phone, 
           COALESCE(p.gender, u.gender) as patient_gender, 
           COALESCE(p.date_of_birth, u.date_of_birth) as patient_dob, 
           COALESCE(p.address, u.address) as patient_address,
           pay.status as payment_status, pay.amount as payment_amount, pay.gcash_reference, pay.receipt_file
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN payments pay ON a.id = pay.appointment_id
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
            <div class="alert alert-success" style="margin-bottom: 25px; padding: 18px 25px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 14px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); font-weight: 600;">
                <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                <span><?php echo $success_message; ?></span>
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
                                <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
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
                                <?php foreach ($appointments as $appointment): 
                                    $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                                ?>
                                    <tr class="appointment-row status-<?php echo $appointment['status']; ?>">
                                        <td>
                                            <div class="date-time-cell">
                                                <span class="date"><?php echo formatDate($appointment['appointment_date']); ?></span>
                                                <span class="time"><?php echo formatTime($appointment['appointment_time']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="patient-cell">
                                                <span class="name"><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></span>
                                                <span class="meta">
                                                    <?php echo !empty($appointment['patient_dob']) ? '<i class="fas fa-birthday-cake"></i> ' . formatDate($appointment['patient_dob']) : ''; ?>
                                                    <?php echo !empty($appointment['patient_gender']) ? ' • ' . ucfirst($appointment['patient_gender']) : ''; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-cell">
                                                <?php if ($appointment['patient_email']): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars($appointment['patient_email']); ?>" title="Email Patient">
                                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['patient_email']); ?>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($appointment['patient_phone']): ?>
                                                    <a href="tel:<?php echo htmlspecialchars($appointment['patient_phone']); ?>" title="Call Patient">
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="reason-cell">
                                                <?php 
                                                $reason = !empty($appointment['illness']) ? $appointment['illness'] : ($appointment['reason_for_visit'] ?? 'General Consultation');
                                                echo htmlspecialchars($reason);
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php 
                                                    $p_info = !empty($appointment['patient_info']) ? (json_decode($appointment['patient_info'], true) ?? []) : [];
                                                    $receipt_path = !empty($appointment['receipt_file']) ? 'assets/uploads/payment_receipts/' . $appointment['receipt_file'] : null;
                                                ?>
                                                <button type="button" class="action-btn btn-view" title="View Details" 
                                                        onclick="showAppointmentDetails(<?php echo htmlspecialchars(json_encode([
                                                            'name' => ($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''),
                                                            'account_name' => ($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''),
                                                            'date' => formatDate($appointment['appointment_date']),
                                                            'time' => formatTime($appointment['appointment_time']),
                                                            'email' => $appointment['patient_email'] ?? 'N/A',
                                                            'phone' => $appointment['patient_phone'] ?? 'N/A',
                                                            'address' => $appointment['patient_address'] ?? 'N/A',
                                                            'gender' => ucfirst($appointment['patient_gender'] ?? 'N/A'),
                                                            'dob' => !empty($appointment['patient_dob']) ? formatDate($appointment['patient_dob']) : 'N/A',
                                                            'reason' => $appointment['illness'] ?? $appointment['reason_for_visit'] ?? 'Consultation',
                                                            'purpose' => ucfirst($appointment['purpose'] ?? 'Consultation'),
                                                            'relationship' => ucfirst($appointment['relationship'] ?? 'Self'),
                                                             'status' => ucfirst($appointment['status']),
                                                             'id' => $appointment['id'],
                                                             'notes' => $appointment['notes'] ?? '',
                                                             'can_complete' => (strtolower($appointment['status']) === 'scheduled' && $appointment['appointment_date'] <= date('Y-m-d')),
                                                             'can_no_show' => strtolower($appointment['status']) === 'scheduled',
                                                             'can_add_findings' => strtolower($appointment['status']) === 'completed',
                                                             'doctor_first_name' => $_SESSION['first_name'],
                                                             'doctor_last_name' => $_SESSION['last_name'],
                                                            'specialty' => $doctor_specialty,
                                                            'payment_status' => $appointment['payment_status'] ?? 'PENDING',
                                                            'payment_amount' => $appointment['payment_amount'] ?? 0,
                                                            'gcash_reference' => $appointment['gcash_reference'] ?? 'N/A',
                                                            'receipt_path' => $receipt_path,
                                                            'laboratory_image_path' => $p_info['laboratory_image'] ?? null,
                                                            'updated_at' => $appointment['updated_at']
                                                        ]), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
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

<!-- Appointment Details Modal -->
<?php include_once '../includes/shared_appointment_details.php'; ?>

<div id="findingsModal" class="modal" style="display: none; z-index: 10001;">
    <div class="modal-content" style="max-width: 600px; width: 90%; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
        <div class="modal-header" style="background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin:0; display: flex; align-items: center; gap: 10px;"><i class="fas fa-clipboard-check"></i> Final Findings</h3>
            <span class="close-modal" onclick="closeFindingsModal()" style="cursor: pointer; opacity: 0.8; transition: opacity 0.2s;"><i class="fas fa-times"></i></span>
        </div>
        <form method="POST">
            <div class="modal-body" style="padding: 30px; background: white;">
                <input type="hidden" name="action" value="update_findings">
                <input type="hidden" name="appointment_id" id="findingsAptId">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.85rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.05em;">Doctor's Notes & Findings</label>
                    <textarea name="notes" id="findingsNotesArea" style="width: 100%; height: 200px; padding: 15px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; resize: none; focus: border-color #2563eb; outline: none; transition: border-color 0.2s;" placeholder="Enter patient diagnosis, prescriptions, or summary here..." required></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px 30px; background: #f8fafc; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeFindingsModal()" style="padding: 10px 20px; border-radius: 10px; border: 1px solid #e2e8f0; background: white; color: #475569; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-primary" style="padding: 10px 25px; border-radius: 10px; border: none; background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; font-weight: 700; cursor: pointer; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);">Save Findings</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAppointmentDetails(data) {
    showAppointmentOverview(data, 'doctor');
}

function openFindingsModal(id, currentNotes, action = 'complete') {
    document.getElementById('findingsAptId').value = id;
    document.getElementById('findingsNotesArea').value = currentNotes;
    
    // Update action and button text
    const form = document.querySelector('#findingsModal form');
    const actionInput = form.querySelector('input[name="action"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const headerTitle = document.querySelector('#findingsModal .modal-header h3');
    
    actionInput.value = action;
    if (action === 'complete') {
        submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Save Findings';
        headerTitle.innerHTML = '<i class="fas fa-clipboard-check"></i> Save Findings';
    } else {
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Findings';
        headerTitle.innerHTML = '<i class="fas fa-pen"></i> Update Findings';
    }

    document.getElementById('findingsModal').style.display = 'block';
    document.getElementById('appointmentModal').style.zIndex = '999';
}


function closeFindingsModal() {
    document.getElementById('findingsModal').style.display = 'none';
    document.getElementById('appointmentModal').style.zIndex = '1000';
}

function closeModal() {
    closeBaseModal();
}

// Close modals when clicking outside
window.onclick = function(event) {
    const aptModal = document.getElementById('appointmentModal');
    const findModal = document.getElementById('findingsModal');
    if (event.target == aptModal) {
        closeModal();
    }
    if (event.target == findModal) {
        closeFindingsModal();
    }
}

// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('#status, #date');
    if (filterSelects.length > 0) {
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    }
    
    // Add search functionality with debounce
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
});
</script>

</body>
</html>
