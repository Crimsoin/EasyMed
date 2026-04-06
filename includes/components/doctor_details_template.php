<?php
/**
 * Shared Doctor Details Template
 * - Re-designed to match Student (Patient) Profile aesthetics -
 * 
 * Variables expected:
 * @var array $doctor - The doctor data (merged with user data)
 * @var array $stats - The statistics data
 * @var array $recent_appointments - The recent appointments data
 * @var array $lab_offers - The laboratory offers data
 * @var float $avg_rating - Average rating
 * @var int $total_reviews - Total reviews
 * @var string $viewMode - 'admin' or 'doctor'
 */

$is_admin = ($viewMode === 'admin');
$doctor_user_id = $doctor['id'];
$initials = strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1));
?>

<!-- Profile Header (Matching Student/Patient Style) -->
<div class="content-header" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; gap: 2rem; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); position: relative; overflow: hidden; border: none;">
    <div class="header-overlay" style="position: absolute; top: -50%; right: -50%; width: 100%; height: 200%; background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%); pointer-events: none;"></div>
    
    <div class="profile-avatar" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 4px solid rgba(255, 255, 255, 0.3); flex-shrink: 0; background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center;">
        <?php if (!empty($doctor['profile_image'])): ?>
            <img src="<?php echo SITE_URL; ?>/assets/images/profiles/<?php echo htmlspecialchars($doctor['profile_image']); ?>" alt="Doctor Avatar" style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
            <div class="avatar-placeholder" style="font-size: 3.5rem; font-weight: bold; color: white;">
                <?php echo $initials; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="profile-info" style="flex: 1; z-index: 1;">
        <h1 style="margin: 0 0 0.5rem 0; font-size: 2rem; font-weight: 600; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); color: white;">
            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
        </h1>
        <p style="margin: 0 0 1rem 0; font-size: 1.1rem; opacity: 0.9; font-weight: 400; color: white;">
            <?php echo htmlspecialchars($doctor['specialty'] ?? $doctor['specialization'] ?? 'Medical Professional'); ?> Profile
        </p>
        
        <div class="profile-badges" style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <span class="status-badge" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500; background: <?php echo $doctor['is_active'] ? 'rgba(34, 197, 94, 0.2)' : 'rgba(239, 68, 68, 0.2)'; ?>; color: <?php echo $doctor['is_active'] ? '#dcfce7' : '#fecaca'; ?>; backdrop-filter: blur(10px);">
                <i class="fas fa-<?php echo $doctor['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                <?php echo $doctor['is_active'] ? 'Active Member' : 'Inactive'; ?>
            </span>
            <span class="role-badge" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500; background: rgba(6, 182, 212, 0.2); color: #cffafe; backdrop-filter: blur(10px);">
                <i class="fas fa-user-md"></i>
                Doctor
            </span>
            <?php if ($avg_rating > 0): ?>
                <span class="role-badge" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500; background: rgba(245, 158, 11, 0.2); color: #fef3c7; backdrop-filter: blur(10px);">
                    <i class="fas fa-star" style="color: #fbbf24;"></i>
                    <?php echo $avg_rating; ?> <small style="margin-left: 4px; opacity: 0.8;">(<?php echo $total_reviews; ?> reviews)</small>
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="header-actions" style="margin-left: auto; display: flex; gap: 1rem; align-items: center; z-index: 1;">
        <?php if ($is_admin): ?>
            <a href="doctors.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 8px; font-size: 0.95rem; font-weight: 500; text-decoration: none; transition: all 0.2s ease; backdrop-filter: blur(10px);">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="edit-doctor.php?id=<?php echo $doctor_user_id; ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 8px; font-size: 0.95rem; font-weight: 500; text-decoration: none; transition: all 0.2s ease; backdrop-filter: blur(10px);">
                <i class="fas fa-edit"></i> Edit
            </a>
        <?php else: ?>
            <button type="button" onclick="openProfileModal()" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 8px; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; backdrop-filter: blur(10px);">
                <i class="fas fa-edit"></i> Edit Profile
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Grid (Matching Student Style) -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="stat-card" style="background: white; border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
        <div class="stat-icon stat-icon-total" style="width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; background: linear-gradient(135deg, #2563eb, #60a5fa);">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content">
            <h3 style="margin: 0 0 0.25rem 0; font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $stats['total_appointments'] ?? 0; ?></h3>
            <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Total Appts</p>
        </div>
    </div>
    <div class="stat-card" style="background: white; border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
        <div class="stat-icon stat-icon-completed" style="width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; background: linear-gradient(135deg, #22c55e, #16a34a);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3 style="margin: 0 0 0.25rem 0; font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $stats['completed_appointments'] ?? 0; ?></h3>
            <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Completed</p>
        </div>
    </div>
    <div class="stat-card" style="background: white; border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
        <div class="stat-icon stat-icon-pending" style="width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3 style="margin: 0 0 0.25rem 0; font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $stats['pending_appointments'] ?? 0; ?></h3>
            <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Pending</p>
        </div>
    </div>
    <div class="stat-card" style="background: white; border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
        <div class="stat-icon stat-icon-cancelled" style="width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3 style="margin: 0 0 0.25rem 0; font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $stats['cancelled_appointments'] ?? 0; ?></h3>
            <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Cancelled</p>
        </div>
    </div>
</div>

<!-- Profile Layout (Matching Student Style) -->
<div class="profile-layout" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    
    <!-- Left Column -->
    <div class="column-left">
        <!-- Personal & Professional Information Combined -->
        <div class="content-section" style="background: white; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; overflow: hidden;">
            <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1.5rem 1.5rem 0.75rem 1.5rem; border-bottom: 2px solid #f3f4f6; margin-bottom: 1rem;">
                <h2 style="margin: 0; color: #1f2937; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-id-card" style="color: #2563eb;"></i> Professional Details
                </h2>
            </div>
            <div class="section-content" style="padding: 1.5rem;">
                <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div class="info-item" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 600; color: #374151; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem;">Specialty</label>
                        <span style="color: #1f2937; font-size: 1rem; font-weight: 500;"><?php echo htmlspecialchars($doctor['specialty'] ?? $doctor['specialization'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 600; color: #374151; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem;">License Number</label>
                        <span style="color: #1f2937; font-size: 1rem; font-weight: 500;"><?php echo htmlspecialchars($doctor['license_number'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="info-item" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 600; color: #374151; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem;">Experience</label>
                        <span style="color: #1f2937; font-size: 1rem; font-weight: 500;"><?php echo ($doctor['experience_years'] ?? $doctor['years_of_experience'] ?? 0); ?> Years</span>
                    </div>
                    <div class="info-item" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 600; color: #374151; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem;">Email Address</label>
                        <span style="color: #1f2937; font-size: 1rem; font-weight: 500;"><?php echo htmlspecialchars($doctor['email']); ?></span>
                    </div>
                    <div class="info-item" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 600; color: #374151; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem;">Fee per Session</label>
                        <span style="color: #1e3a8a; font-size: 1.1rem; font-weight: 700;">₱<?php echo number_format($doctor['consultation_fee'] ?? 0, 2); ?></span>
                    </div>
                    <div class="info-item" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 600; color: #374151; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem;">Contact Number</label>
                        <span style="color: #1f2937; font-size: 1rem; font-weight: 500;"><?php echo htmlspecialchars($doctor['phone'] ?: 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultation History Section (Matching Student Style) -->
        <div class="content-section history-section" style="background: white; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; overflow: hidden;">
            <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1.5rem 1.5rem 0.75rem 1.5rem; border-bottom: 2px solid #f3f4f6; margin-bottom: 0;">
                <h2 style="margin: 0; color: #1f2937; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-history" style="color: #2563eb;"></i> Consultation History
                </h2>
            </div>
            <div class="appointments-list" style="padding: 1rem; display: flex; flex-direction: column; gap: 1rem;">
                <?php if (!empty($recent_appointments)): ?>
                    <?php foreach ($recent_appointments as $appointment): ?>
                        <div class="appointment-item clickable" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; background: white; border: 1px solid #f1f5f9; border-radius: 12px; transition: all 0.2s ease; cursor: pointer;" onclick='viewAppointment(<?php echo htmlspecialchars(json_encode($appointment), ENT_QUOTES, "UTF-8"); ?>)'>
                            <div class="appointment-main" style="display: flex; align-items: center; gap: 2rem; flex: 1;">
                                <div class="doctor-brief" style="min-width: 180px;">
                                    <h4 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #2563eb;"><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h4>
                                    <span class="specialty" style="font-size: 0.8rem; color: #94a3b8; font-weight: 500;">Patient</span>
                                </div>
                                <div class="appointment-meta" style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <div class="meta-item" style="display: flex; align-items: center; gap: 0.5rem; color: #475569; font-size: 0.9rem; font-weight: 500;">
                                        <i class="fas fa-calendar" style="width: 16px; color: #2563eb;"></i>
                                        <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                    </div>
                                    <div class="meta-item" style="display: flex; align-items: center; gap: 0.5rem; color: #475569; font-size: 0.9rem; font-weight: 500;">
                                        <i class="fas fa-clock" style="width: 16px; color: #2563eb;"></i>
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                </div>
                            </div>
                            <span class="status-badge" style="padding: 0.4rem 0.8rem; border-radius: 100px; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; <?php 
                                echo match(strtolower($appointment['status'])) {
                                    'completed' => 'background: #dcfce7; color: #166534;',
                                    'pending' => 'background: #fef3c7; color: #92400e;',
                                    'cancelled' => 'background: #fee2e2; color: #991b1b;',
                                    default => 'background: #dbeafe; color: #1e40af;'
                                }; ?>">
                                <?php echo htmlspecialchars($appointment['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-appointments" style="text-align: center; padding: 3rem 2rem; color: #64748b;">
                        <i class="fas fa-calendar-times" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p style="margin: 0; font-weight: 500;">No medical records found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="column-right">
        <!-- Schedule Information Card -->
        <div class="content-section" style="background: white; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; overflow: hidden;">
            <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem 0.75rem 1.5rem; border-bottom: 2px solid #f3f4f6; margin-bottom: 1rem;">
                <h2 style="margin: 0; color: #1f2937; font-size: 1.15rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-clock" style="color: #2563eb;"></i> Working Hours
                </h2>
            </div>
            <div class="section-content" style="padding: 1.25rem 1.5rem;">
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; border: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: white; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #2563eb;">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Days</label>
                            <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;"><?php echo htmlspecialchars($doctor['schedule_days'] ?: 'Mon - Fri'); ?></span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: white; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #2563eb;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Time</label>
                            <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">
                                <?php echo ($doctor['schedule_time_start'] && $doctor['schedule_time_end']) ? date('g:i A', strtotime($doctor['schedule_time_start'])) . ' - ' . date('g:i A', strtotime($doctor['schedule_time_end'])) : '8:00 AM - 5:00 PM'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laboratory Offers Card (Matching Student Style List) -->
        <div class="content-section" style="background: white; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; overflow: hidden;">
            <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem 0.75rem 1.5rem; border-bottom: 2px solid #f3f4f6; margin-bottom: 1rem;">
                <h2 style="margin: 0; color: #1f2937; font-size: 1.15rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-flask" style="color: #2563eb;"></i> Laboratory Tests
                </h2>
            </div>
            <div class="section-content" style="padding: 1rem 1.5rem 1.5rem 1.5rem;">
                <div class="quick-actions" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php if (!empty($lab_offers)): ?>
                        <?php foreach ($lab_offers as $offer): ?>
                            <div class="action-btn" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; color: #374151; transition: all 0.2s ease; overflow: hidden;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <i class="fas fa-check-circle" style="color: #2563eb; font-size: 0.9rem;"></i>
                                    <span style="font-weight: 500; font-size: 0.85rem;"><?php echo htmlspecialchars($offer['title']); ?></span>
                                </div>
                                <span style="font-weight: 700; color: #1e3a8a; font-size: 0.85rem;">₱<?php echo number_format($offer['price'] ?: 0, 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 1rem; color: #94a3b8; font-size: 0.85rem;">
                            No lab offers available.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
