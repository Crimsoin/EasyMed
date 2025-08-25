<?php
$page_title = "Patient Reviews";
$page_description = "Read what our patients say about their experience at EasyMed Private Clinic";
require_once 'includes/header.php';

// Get approved reviews
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
    WHERE r.is_approved = 1
    ORDER BY r.created_at DESC
    LIMIT 20
");

// Calculate average rating
$avgRating = $db->fetch("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
    FROM reviews 
    WHERE is_approved = 1
");
?>

<!-- Page Header -->
<section class="hero" style="padding: 2rem 0;">
    <div class="container">
        <div class="hero-content">
            <h1><i class="fas fa-star"></i> Patient Reviews</h1>
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
                            <div><?php echo $avgRating['total_reviews']; ?> Reviews</div>
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
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
                <?php foreach ($reviews as $review): ?>
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                            <div>
                                <div style="color: var(--primary-cyan); margin-bottom: 0.5rem;">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </div>
                                <div style="font-weight: 600; color: var(--text-dark);">
                                    <?php if ($review['is_anonymous']): ?>
                                        Anonymous Patient
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.'); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($review['doctor_first_name'])): ?>
                                    <div style="font-size: 0.9rem; color: var(--text-light);">
                                        <i class="fas fa-user-md"></i> 
                                        Dr. <?php echo htmlspecialchars($review['doctor_first_name'] . ' ' . $review['doctor_last_name']); ?>
                                        <?php if (!empty($review['specialty'])): ?>
                                            - <?php echo htmlspecialchars($review['specialty']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-light);">
                                <?php echo formatDate($review['created_at'], 'M j, Y'); ?>
                            </div>
                        </div>
                        
                        <div style="color: var(--text-dark); line-height: 1.6;">
                            <i class="fas fa-quote-left" style="color: var(--light-cyan); margin-right: 0.5rem;"></i>
                            <?php echo htmlspecialchars($review['review_text']); ?>
                            <i class="fas fa-quote-right" style="color: var(--light-cyan); margin-left: 0.5rem;"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card text-center">
                <div style="font-size: 4rem; color: var(--light-cyan); margin-bottom: 1rem;">
                    <i class="fas fa-comments"></i>
                </div>
                <h3 style="color: var(--text-light); margin-bottom: 1rem;">No Reviews Yet</h3>
                <p style="color: var(--text-light);">
                    Be the first to share your experience with EasyMed Private Clinic!
                </p>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
                    <div style="margin-top: 2rem;">
                        <a href="<?php echo SITE_URL; ?>/patient/reviews.php" class="btn btn-primary">
                            <i class="fas fa-pen"></i> Write a Review
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
                <a href="<?php echo SITE_URL; ?>/patient/reviews.php" 
                   class="btn" style="background-color: white; color: var(--primary-cyan);">
                    <i class="fas fa-pen"></i> Write a Review
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

<!-- Statistics Section -->
<?php if (!empty($reviews)): ?>
<section class="section" style="background-color: var(--light-gray);">
    <div class="container">
        <h2 class="section-title">Review Statistics</h2>
        
        <?php
        // Calculate rating distribution
        $ratingCounts = array_fill(1, 5, 0);
        foreach ($reviews as $review) {
            $ratingCounts[$review['rating']]++;
        }
        $totalReviews = count($reviews);
        ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                <?php $count = $ratingCounts[$rating]; ?>
                <?php $percentage = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0; ?>
                
                <div class="card text-center">
                    <div style="font-size: 2rem; color: var(--primary-cyan); margin-bottom: 0.5rem;">
                        <?php echo $rating; ?> <i class="fas fa-star"></i>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--text-dark); margin-bottom: 0.5rem;">
                        <?php echo $count; ?>
                    </div>
                    <div style="background-color: var(--medium-gray); height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 0.5rem;">
                        <div style="background-color: var(--primary-cyan); height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s ease;"></div>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--text-light);">
                        <?php echo number_format($percentage, 1); ?>%
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
