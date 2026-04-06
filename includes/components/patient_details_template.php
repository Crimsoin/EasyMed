<?php
/**
 * Shared Patient Details Template
 * 
 * Variables expected:
 * @var array $user - The user data
 * @var array $stats - The statistics data
 * @var array $recentActivity - The recent appointments data
 * @var string $viewMode - 'admin' or 'patient'
 * @var string $baseUrl - Base URL for links (calculated based on viewMode usually)
 */

$is_admin = ($viewMode === 'admin');
$profile_user_id = $user['id'];
?>

<!-- Profile Header -->
<div class="content-header">
    <div class="profile-avatar">
        <?php if (isset($user['avatar']) && $user['avatar']): ?>
            <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Patient Avatar">
        <?php else: ?>
            <div class="avatar-placeholder">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="profile-info">
        <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
        <p><?php echo ucfirst($user['role']); ?> Profile</p>
        
        <div class="profile-badges">
            <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                <i class="fas fa-<?php echo $user['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
            </span>
            <span class="role-badge role-<?php echo $user['role']; ?>">
                <i class="fas fa-<?php echo $user['role'] === 'patient' ? 'user' : ($user['role'] === 'doctor' ? 'user-md' : 'user-shield'); ?>"></i>
                <?php echo ucfirst($user['role']); ?>
            </span>
        </div>
    </div>
    
    <div class="header-actions">
        <?php if ($is_admin): ?>
            <a href="patients.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        <?php else: ?>
            <button type="button" onclick="openEditProfileModal()" class="btn-secondary">
                <i class="fas fa-edit"></i> Edit Profile
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Profile Content -->
<div class="profile-content" style="<?php echo !$is_admin ? 'display: block;' : ''; ?>">
    <!-- User Information Section -->
    <div class="info-section">
        <div class="section-header" style="margin-bottom: 1.5rem; padding: 0.5rem 0; border-bottom: 2px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; border-radius: 0; background: transparent;">
            <h2 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;"><i class="fas fa-user" style="color: #2563eb; margin-right: 0.5rem;"></i> User Information</h2>
        </div>
        
        <div class="info-grid" style="<?php echo !$is_admin ? 'grid-template-columns: repeat(4, 1fr);' : ''; ?>">
            <div class="info-item">
                <label>User ID</label>
                <span><?php echo $user['id']; ?></span>
            </div>
            <div class="info-item">
                <label>Username</label>
                <span><?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <div class="info-item">
                <label>First Name</label>
                <span><?php echo htmlspecialchars($user['first_name']); ?></span>
            </div>
            <div class="info-item">
                <label>Last Name</label>
                <span><?php echo htmlspecialchars($user['last_name']); ?></span>
            </div>
            <div class="info-item">
                <label>Email Address</label>
                <span><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <div class="info-item">
                <label>Phone Number</label>
                <span><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></span>
            </div>
            <?php if ($user['role'] === 'patient'): ?>
            <div class="info-item">
                <label>Date of Birth</label>
                <span><?php echo $user['date_of_birth'] ? date('M j, Y', strtotime($user['date_of_birth'])) : 'Not provided'; ?></span>
            </div>
            <div class="info-item">
                <label>Gender</label>
                <span><?php echo $user['gender'] ? ucfirst($user['gender']) : 'Not provided'; ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <label>Member Since</label>
                <span><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
            </div>
            <?php if ($user['role'] === 'patient'): ?>
            <div class="info-item full-width">
                <label>Address</label>
                <span><?php echo htmlspecialchars($user['address'] ?: 'Not provided'); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions Sidebar (Admin Only) -->
    <?php if ($is_admin): ?>
    <div class="info-section">
        <div class="section-header" style="margin-bottom: 1.5rem; padding: 0.5rem 0; border-bottom: 2px solid #f3f4f6; border-radius: 0; background: transparent;">
            <h2 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;"><i class="fas fa-bolt" style="color: #2563eb; margin-right: 0.5rem;"></i> Quick Actions</h2>
        </div>
        
        <div class="quick-actions">
            <?php if ($user['role'] === 'patient'): ?>
            <a href="../Appointment/book-appointment.php?patient_id=<?php echo $user['id']; ?>" class="action-btn">
                <i class="fas fa-calendar-plus"></i>
                Book Appointment
            </a>
            <?php elseif ($user['role'] === 'doctor'): ?>
            <a href="../Doctor Management/doctor-schedule.php?id=<?php echo $user['id']; ?>" class="action-btn">
                <i class="fas fa-calendar"></i>
                View Schedule
            </a>
            <?php endif; ?>
            <a href="#" class="action-btn" onclick="toggleUserStatus(<?php echo $user['id']; ?>)">
                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User
            </a>
            <a href="patients.php" class="action-btn">
                <i class="fas fa-list"></i>
                All Patients
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-total">
            <i class="fas fa-calendar"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_appointments'] ?? 0; ?></h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-completed">
            <i class="fas fa-check"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['completed_appointments'] ?? 0; ?></h3>
            <p>Completed Appointments</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-pending">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['pending_appointments'] ?? $stats['upcoming_appointments'] ?? 0; ?></h3>
            <p>Upcoming Appointments</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-cancelled">
            <i class="fas fa-times"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['cancelled_appointments'] ?? 0; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<!-- Appointments History -->
<div class="info-section history-section" style="padding: 0 !important; overflow: hidden; background: white; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
    <div class="section-header" style="background: transparent; padding: 1.5rem 2rem; margin-bottom: 0; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; border-radius: 0;">
        <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Patient History</h2>
        <a href="<?php echo $is_admin ? '../Dashboard/dashboard.php' : 'appointments.php'; ?>" class="view-all-btn">
            <i class="fas fa-list-ul"></i> View All
        </a>
    </div>
    
    <?php if (!empty($recentActivity)): ?>
    <div class="appointments-list" style="padding: 1.5rem 2rem; display: flex; flex-direction: column; gap: 1.25rem;">
        <?php foreach ($recentActivity as $activity): ?>
        <div class="appointment-item clickable" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2rem; background: white; border: 1px solid #f1f5f9; border-radius: 16px; transition: all 0.2s ease; cursor: pointer; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);" onclick='viewAppointment(<?php echo htmlspecialchars(json_encode($activity), ENT_QUOTES, "UTF-8"); ?>)'>
            <div class="appointment-main" style="display: flex; align-items: center; gap: 4rem; flex: 1;">
                <div class="doctor-brief" style="min-width: 200px;">
                    <?php if ($user['role'] === 'patient'): ?>
                    <h4 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #2563eb;">Dr. <?php echo htmlspecialchars($activity['doctor_first_name'] . ' ' . $activity['doctor_last_name']); ?></h4>
                    <span class="specialty" style="font-size: 0.85rem; color: #94a3b8; font-weight: 500;"><?php echo htmlspecialchars($activity['specialty'] ?? 'Medical Practitioner'); ?></span>
                    <?php else: ?>
                    <h4 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #2563eb;"><?php echo htmlspecialchars($activity['patient_first_name'] . ' ' . $activity['patient_last_name']); ?></h4>
                    <span class="specialty" style="font-size: 0.85rem; color: #94a3b8; font-weight: 500;">Patient</span>
                    <?php endif; ?>
                </div>
                
                <div class="appointment-meta" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div class="meta-item" style="display: flex; align-items: center; gap: 0.75rem; color: #475569; font-size: 0.95rem; font-weight: 500;">
                        <i class="fas fa-calendar-alt" style="width: 20px; color: #2563eb; font-size: 1rem; opacity: 0.9;"></i>
                        <span><?php echo date('F j, Y', strtotime($activity['appointment_date'])) . ' at ' . date('g:i A', strtotime($activity['appointment_time'])); ?></span>
                    </div>
                    <div class="meta-item" style="display: flex; align-items: center; gap: 0.75rem; color: #475569; font-size: 0.95rem; font-weight: 500;">
                        <i class="fas fa-clipboard-list" style="width: 20px; color: #2563eb; font-size: 1rem; opacity: 0.9;"></i>
                        <span><?php echo htmlspecialchars(!empty($activity['illness']) ? $activity['illness'] : ($activity['reason_for_visit'] ?: 'Consultation Only')); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="appointment-side">
                <span class="status-badge status-<?php echo strtolower($activity['status']); ?>" style="padding: 0.5rem 1rem; border-radius: 100px; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">
                    <?php echo strtoupper($activity['status']); ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="no-appointments" style="text-align: center; padding: 4rem 2rem; color: #64748b;">
        <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1.5rem; opacity: 0.3; display: block;"></i>
        <p>No recent appointments found.</p>
    </div>
    <?php endif; ?>
</div>
