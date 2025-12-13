<?php
$page_title = "My Patients";
$additional_css = ['base.css', 'doctor/sidebar-doctor.css', 'doctor/patients-doctor.css'];
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../index.php');
    exit();
}

// Get database connection
$db = Database::getInstance();

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['name'] ?? 'Doctor';

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'name';
$page = (int)($_GET['page'] ?? 1);
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_patient_details':
            $patient_id = (int)($_GET['patient_id'] ?? 0);
            $patient = getPatientDetails($db, $patient_id, $doctor_id);
            echo json_encode($patient);
            exit;
            
        case 'update_patient_status':
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $result = updatePatientStatus($db, $patient_id, $status, $doctor_id);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// Build search query
$where_conditions = ["a.doctor_id = (SELECT id FROM doctors WHERE user_id = :doctor_id)"];
$params = ['doctor_id' => $doctor_id];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name || ' ' || u.last_name LIKE :search OR u.email LIKE :search OR p.phone LIKE :search)";
    $params['search'] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "p.status = :status";
    $params['status'] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Order by clause
$order_by = match($sort_by) {
    'name' => 'u.first_name ASC, u.last_name ASC',
    'email' => 'u.email ASC',
    'date' => 'p.created_at DESC',
    'appointments' => 'appointment_count DESC',
    default => 'u.first_name ASC, u.last_name ASC'
};

// Get patients count for pagination
$count_sql = "
    SELECT COUNT(DISTINCT p.id) as total
    FROM patients p
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON a.patient_id = p.id
    WHERE $where_clause
";

$count_result = $db->fetch($count_sql, $params);
$total_patients = $count_result['total'];
$total_pages = ceil($total_patients / $per_page);

// Get patients list
$patients_sql = "
    SELECT DISTINCT 
        p.id,
        p.status,
        p.created_at,
        u.first_name || ' ' || u.last_name as name,
        u.email,
        p.phone,
        p.date_of_birth,
        p.gender,
        COUNT(a.id) as appointment_count,
        MAX(a.appointment_date) as last_appointment,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
        SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments
    FROM patients p
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON a.patient_id = p.id
    WHERE $where_clause
    GROUP BY p.id, p.status, p.created_at, u.first_name, u.last_name, u.email, p.phone, p.date_of_birth, p.gender
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";

$patients = $db->fetchAll($patients_sql, $params);

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(DISTINCT p.id) as total_patients,
        SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) as active_patients,
        COUNT(DISTINCT CASE WHEN a.appointment_date >= date('now') THEN p.id END) as upcoming_patients,
        COUNT(DISTINCT CASE WHEN a.appointment_date >= date('now', '-30 days') THEN p.id END) as recent_patients
    FROM patients p
    JOIN appointments a ON a.patient_id = p.user_id
    WHERE a.doctor_id = (SELECT id FROM doctors WHERE user_id = :doctor_id)
";

$stats = $db->fetch($stats_sql, ['doctor_id' => $doctor_id]);

// Helper functions
function getPatientDetails($db, $patient_id, $doctor_id) {
    $sql = "
        SELECT 
            p.*,
            u.first_name || ' ' || u.last_name as name,
            u.email,
            p.phone,
            p.gender,
            COUNT(a.id) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
            MAX(a.appointment_date) as last_appointment
        FROM patients p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN appointments a ON a.patient_id = u.id AND a.doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
        WHERE p.id = ?
        GROUP BY p.id
    ";
    
    return $db->fetch($sql, [$doctor_id, $patient_id]);
}

function updatePatientStatus($db, $patient_id, $status, $doctor_id) {
    // Verify patient belongs to this doctor
    $verify_sql = "
        SELECT COUNT(*) as count 
        FROM patients p
        JOIN appointments a ON a.patient_id = p.user_id
        WHERE p.id = ? AND a.doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
    ";
    
    $verify_result = $db->fetch($verify_sql, [$patient_id, $doctor_id]);
    
    if ($verify_result['count'] > 0) {
        $update_sql = "UPDATE patients SET status = ? WHERE id = ?";
        $result = $db->query($update_sql, [$status, $patient_id]);
        return $result->rowCount() > 0;
    }
    
    return false;
}

function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - EasyMed Doctor Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Additional CSS for specific pages -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="doctor-container">
        <div class="doctor-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-user-md"></i> Doctor Portal</h3>
                <p>Dr. <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? 'Doctor')); ?></p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_doctor.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
                <a href="schedule.php" class="nav-item">
                    <i class="fas fa-clock"></i> Schedule
                </a>
                <a href="patients.php" class="nav-item active">
                    <i class="fas fa-users"></i> My Patients
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i> Profile
                </a>
            </nav>
        </div>
        
        <main class="doctor-content">
            <!-- Page Header -->
            <div class="content-header">
                <h1><i class="fas fa-users"></i> My Patients</h1>
                <p>Manage and monitor your patient information and medical history</p>
            </div>

            <!-- Statistics Overview -->
            <div class="patients-overview">
                <div class="patients-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_patients'] ?? 0) ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>

                <div class="patients-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['active_patients'] ?? 0) ?></div>
                    <div class="stat-label">Active Patients</div>
                </div>

                <div class="patients-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['upcoming_patients'] ?? 0) ?></div>
                    <div class="stat-label">Upcoming Patients</div>
                </div>

                <div class="patients-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['recent_patients'] ?? 0) ?></div>
                    <div class="stat-label">Recent Patients</div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="patients-controls">
                <form method="GET" class="controls-row">
                    <div class="search-group">
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search patients by name, email, or phone..."
                               class="search-input">
                        <i class="fas fa-search search-icon"></i>
                    </div>

                    <select name="status" class="filter-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>

                    <select name="sort" class="filter-select">
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Sort by Name</option>
                        <option value="email" <?= $sort_by === 'email' ? 'selected' : '' ?>>Sort by Email</option>
                        <option value="date" <?= $sort_by === 'date' ? 'selected' : '' ?>>Newest First</option>
                        <option value="appointments" <?= $sort_by === 'appointments' ? 'selected' : '' ?>>Most Appointments</option>
                    </select>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </form>
            </div>

            <!-- Patients Grid -->
            <?php if (empty($patients)): ?>
                <div class="content-section">
                    <div class="section-content" style="padding: 3rem; text-align: center;">
                        <i class="fas fa-users" style="font-size: 4rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--text-dark); margin-bottom: 1rem;">No Patients Found</h3>
                        <p style="color: var(--text-light);">
                            <?= !empty($search) ? 'No patients match your search criteria.' : 'You don\'t have any patients yet.' ?>
                        </p>
                        <?php if (!empty($search)): ?>
                            <a href="patients.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-times"></i> Clear Search
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="patients-grid">
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-card" data-patient-id="<?= $patient['id'] ?>">
                            <div class="patient-header">
                                <div class="patient-avatar">
                                    <?= getInitials($patient['name']) ?>
                                </div>
                                <div class="patient-basic-info">
                                    <h3 class="patient-name"><?= htmlspecialchars($patient['name']) ?></h3>
                                    <p class="patient-id">ID: #<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT) ?></p>
                                </div>
                                <span class="patient-status <?= $patient['status'] ?>">
                                    <?= ucfirst($patient['status']) ?>
                                </span>
                            </div>

                            <div class="patient-body">
                                <div class="patient-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Age</div>
                                        <div class="detail-value"><?php echo !empty($patient['date_of_birth']) ? calculateAge($patient['date_of_birth']) : 'N/A'; ?> years</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Gender</div>
                                        <div class="detail-value"><?= ucfirst($patient['gender'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Email</div>
                                        <a href="mailto:<?= htmlspecialchars($patient['email']) ?>" class="detail-value email">
                                            <?= htmlspecialchars($patient['email']) ?>
                                        </a>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Phone</div>
                                        <a href="tel:<?= htmlspecialchars($patient['phone']) ?>" class="detail-value phone">
                                            <?= htmlspecialchars($patient['phone']) ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="patient-appointments">
                                    <div class="appointments-summary">
                                        <div class="appointment-stat">
                                            <div class="appointment-stat-number"><?= $patient['appointment_count'] ?></div>
                                            <div class="appointment-stat-label">Total</div>
                                        </div>
                                        <div class="appointment-stat">
                                            <div class="appointment-stat-number"><?= $patient['completed_appointments'] ?></div>
                                            <div class="appointment-stat-label">Completed</div>
                                        </div>
                                        <div class="appointment-stat">
                                            <div class="appointment-stat-number"><?= $patient['pending_appointments'] ?></div>
                                            <div class="appointment-stat-label">Pending</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="patient-actions">
                                    <a href="appointments.php?patient_id=<?= $patient['id'] ?>" class="action-btn primary">
                                        <i class="fas fa-calendar"></i> Appointments
                                    </a>
                                    <button class="action-btn secondary" onclick="viewPatientDetails(<?= $patient['id'] ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&sort=<?= $sort_by ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&sort=<?= $sort_by ?>" 
                                   class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&sort=<?= $sort_by ?>" class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info">
                            Showing <?= ($offset + 1) ?> to <?= min($offset + $per_page, $total_patients) ?> of <?= $total_patients ?> patients
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Patient Details Modal -->
    <div id="patientModal" class="schedule-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Patient Details</h3>
                <button class="modal-close" onclick="closePatientModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="patientModalContent">
                    <!-- Patient details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-submit form on filter change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });

        // Patient details modal
        function viewPatientDetails(patientId) {
            const modal = document.getElementById('patientModal');
            const content = document.getElementById('patientModalContent');
            
            // Show loading state
            content.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-cyan);"></i><p style="margin-top: 1rem;">Loading patient details...</p></div>';
            modal.classList.add('active');
            
            // Fetch patient details
            fetch(`patients.php?action=get_patient_details&patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        content.innerHTML = generatePatientDetailsHTML(data);
                    } else {
                        content.innerHTML = '<div style="text-align: center; padding: 2rem;"><p>Patient details not found.</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = '<div style="text-align: center; padding: 2rem;"><p>Error loading patient details.</p></div>';
                });
        }

        function generatePatientDetailsHTML(patient) {
            const age = patient.date_of_birth ? calculateAge(patient.date_of_birth) : 'N/A';
            const initials = getInitials(patient.name);
            
            return `
                <div class="patient-details-view" style="display: block;">
                    <div style="background: var(--white); border-radius: 12px; padding: 2rem; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-cyan), var(--light-cyan)); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 700;">
                                ${initials}
                            </div>
                            <div>
                                <h2 style="margin: 0 0 0.5rem 0; color: var(--text-dark);">${patient.name}</h2>
                                <p style="margin: 0; color: var(--text-light);">Patient ID: #${String(patient.id).padStart(4, '0')}</p>
                                <span class="patient-status ${patient.status}" style="margin-top: 0.5rem; display: inline-block;">
                                    ${patient.status.charAt(0).toUpperCase() + patient.status.slice(1)}
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div class="detail-item">
                                <div class="detail-label">Age</div>
                                <div class="detail-value">${age} years</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Gender</div>
                                <div class="detail-value">${patient.gender ? patient.gender.charAt(0).toUpperCase() + patient.gender.slice(1) : 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><a href="mailto:${patient.email}" style="color: var(--primary-cyan);">${patient.email}</a></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Phone</div>
                                <div class="detail-value"><a href="tel:${patient.phone}" style="color: var(--primary-cyan);">${patient.phone}</a></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Total Appointments</div>
                                <div class="detail-value">${patient.total_appointments || 0}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Completed</div>
                                <div class="detail-value">${patient.completed_appointments || 0}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="appointments.php?patient_id=${patient.id}" class="btn btn-primary">
                            <i class="fas fa-calendar"></i> View Appointments
                        </a>
                        <button class="btn btn-secondary" onclick="closePatientModal()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            `;
        }

        function closePatientModal() {
            document.getElementById('patientModal').classList.remove('active');
        }

        function getInitials(name) {
            const words = name.trim().split(' ');
            if (words.length >= 2) {
                return (words[0].charAt(0) + words[1].charAt(0)).toUpperCase();
            }
            return name.substr(0, 2).toUpperCase();
        }

        function calculateAge(dateOfBirth) {
            const dob = new Date(dateOfBirth);
            const now = new Date();
            const years = now.getFullYear() - dob.getFullYear();
            const months = now.getMonth() - dob.getMonth();
            
            if (months < 0 || (months === 0 && now.getDate() < dob.getDate())) {
                return years - 1;
            }
            return years;
        }

        // Close modal when clicking outside
        document.getElementById('patientModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePatientModal();
            }
        });

        // Real-time search
        let searchTimeout;
        document.querySelector('.search-input').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.closest('form').submit();
            }, 500);
        });
    </script>

</body>
</html>
