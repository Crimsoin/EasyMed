<?php
$page_title = "Patient Feedbacks";
$page_description = "Read what our patients say about their experience at EasyMed Private Clinic";
require_once 'includes/header.php';

// Get all reviews
$db = Database::getInstance();
$reviews = $db->fetchAll("
    SELECT r.rating, r.review_text, r.created_at, r.is_anonymous,
           pu.first_name, pu.last_name,
           du.first_name as doctor_first_name, du.last_name as doctor_last_name,
           d.specialty
    FROM reviews r
    LEFT JOIN patients p ON r.patient_id = p.id
    LEFT JOIN users pu ON p.user_id = pu.id
    LEFT JOIN doctors d ON r.doctor_id = d.id
    LEFT JOIN users du ON d.user_id = du.id
    ORDER BY r.created_at DESC
    LIMIT 20
");

// Calculate average rating
$avgRating = $db->fetch("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
    FROM reviews 
");
?>

<!-- Page Header -->
<section class="hero" style="padding: 2rem 0;">
    <div class="container">
        <div class="hero-content">
            <h1><i class="fas fa-star"></i> Patient Feedbacks</h1>
            <p>See what our patients have to say about their experience with EasyMed</p>
            
            <?php if ($avgRating && $avgRating['total_reviews'] > 0): ?>
                <div style="margin-top: 2rem; background-color: rgba(255, 255, 255, 0.2); padding: 1.5rem; border-radius: 10px; display: inline-block;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
                        <div style="text-align: center;">
                            <div style="font-size: 2.5rem; font-weight: bold; color: white;">
                                <?php echo number_format($avgRating['avg_rating'], 1); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.9);">
                                <?php
                                $stars = round($avgRating['avg_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $stars ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.9); text-align: left;">
                            <div>Average Rating</div>
                            <div><?php echo $avgRating['total_reviews']; ?> Feedbacks</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Reviews Section -->
<section class="section">
    <div class="container">
        <?php if (!empty($reviews)): ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem; max-width: 860px; margin: 0 auto;">
                <?php foreach ($reviews as $review):
                    $initial     = $review['is_anonymous'] ? '?' : strtoupper(substr($review['first_name'] ?? 'A', 0, 1));
                    $patientName = $review['is_anonymous']
                        ? 'Anonymous Patient'
                        : htmlspecialchars($review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.');
                    $rating = intval($review['rating']);
                ?>
                    <div class="card" style="display: flex; gap: 1.25rem; align-items: flex-start; padding: 1.5rem;">
                        <!-- Avatar -->
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #dbeafe, #bfdbfe); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; color: #2563eb; flex-shrink: 0;">
                            <?php echo $initial; ?>
                        </div>

                        <!-- Content -->
                        <div style="flex: 1; min-width: 0;">
                            <!-- Top row: name + date -->
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.3rem;">
                                <div style="font-weight: 700; color: var(--text-dark); font-size: 0.97rem;">
                                    <?php echo $patientName; ?>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-light); white-space: nowrap; flex-shrink: 0;">
                                    <?php echo formatDate($review['created_at'], 'M j, Y'); ?>
                                </div>
                            </div>

                            <!-- Stars + doctor -->
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                                <div style="color: #fbbf24; font-size: 0.9rem; line-height: 1;">
                                    <?php for ($i = 1; $i <= 5; $i++):
                                        echo $i <= $rating
                                            ? '<i class="fas fa-star"></i>'
                                            : '<i class="far fa-star" style="color:#d1d5db;"></i>';
                                    endfor; ?>
                                    <span style="font-size: 0.78rem; color: var(--text-light); margin-left: 4px;"><?php echo $rating; ?>/5</span>
                                </div>
                                <?php if (!empty($review['doctor_first_name'])): ?>
                                    <div style="font-size: 0.82rem; color: var(--text-light);">
                                        <i class="fas fa-user-md" style="font-size: 0.75rem;"></i>
                                        Dr. <?php echo htmlspecialchars($review['doctor_first_name'] . ' ' . $review['doctor_last_name']); ?>
                                        <?php if (!empty($review['specialty'])): ?>
                                            &mdash; <?php echo htmlspecialchars($review['specialty']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Review text -->
                            <div style="color: var(--text-dark); line-height: 1.65; font-size: 0.95rem; padding-left: 0.1rem; border-left: 3px solid #bfdbfe; padding-left: 0.85rem;">
                                <?php echo htmlspecialchars($review['review_text']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card text-center">
                <div style="font-size: 4rem; color: var(--light-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-comments"></i>
                </div>
                <h3 style="color: var(--text-light); margin-bottom: 1rem;">No Feedbacks Yet</h3>
                <p style="color: var(--text-light);">
                    Be the first to share your experience with EasyMed Private Clinic!
                </p>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
                    <div style="margin-top: 2rem;">
                        <a href="<?php echo SITE_URL; ?>/patient/feedbacks.php" class="btn btn-primary">
                            <i class="fas fa-pen"></i> Write Feedback
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Call to Action -->
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
            <div class="card" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); color: white; text-align: center; margin-top: 3rem;">
                <h3 style="color: white; margin-bottom: 1rem;">
                    <i class="fas fa-heart"></i> Share Your Experience
                </h3>
                <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 2rem;">
                    Help other patients by sharing your experience with our medical team.
                </p>
                <a href="<?php echo SITE_URL; ?>/patient/feedbacks.php" 
                   class="btn" style="background-color: white; color: var(--primary-cyan);">
                    <i class="fas fa-pen"></i> Write Feedback
                </a>
            </div>
        <?php elseif (!isset($_SESSION['user_id'])): ?>
            <div class="card" style="background: linear-gradient(135deg, var(--light-cyan), var(--primary-cyan)); color: white; text-align: center; margin-top: 3rem;">
                <h3 style="color: white; margin-bottom: 1rem;">
                    <i class="fas fa-user-plus"></i> Become a Patient
                </h3>
                <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 2rem;">
                    Register with EasyMed to book appointments and share your experience.
                </p>
                <button class="btn" onclick="EasyMed.openModal('registerModal')" 
                        style="background-color: white; color: var(--primary-cyan); margin-right: 1rem;">
                    <i class="fas fa-user-plus"></i> Register Now
                </button>
                <button class="btn btn-secondary" onclick="EasyMed.openModal('loginModal')">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
