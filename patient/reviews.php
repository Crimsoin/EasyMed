<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$additional_css = ['patient/sidebar-patient.css', 'patient/dashboard-patient.css'];

// Require login as patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$db = Database::getInstance();
$patient_user_id = $_SESSION['user_id'];

// Fetch list of doctors for the review form
$doctors = $db->fetchAll("SELECT d.id, u.first_name, u.last_name, d.specialty FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.last_name, u.first_name");

// Fetch current patient's reviews (by users.id)
$reviews = $db->fetchAll(
    "SELECT r.*, d.id as doctor_id, u.first_name as doctor_first_name, u.last_name as doctor_last_name
     FROM reviews r
     LEFT JOIN doctors d ON r.doctor_id = d.id
     LEFT JOIN users u ON d.user_id = u.id
     WHERE r.patient_id = ?
     ORDER BY r.created_at DESC",
    [$patient_user_id]
);

// Pull flash messages
$success = $_SESSION['review_success'] ?? null;
$errors = $_SESSION['review_errors'] ?? null;
unset($_SESSION['review_success'], $_SESSION['review_errors']);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Reviews - EasyMed</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        /* Container & Grid */
        .reviews-container {
            /* occupy full width of the content column so it aligns with .content-header */
            width: 100%;
            max-width: none;
            margin: 0; /* layout spacing follows surrounding content */
            padding: 24px;
        }
        .reviews-grid { 
            display: grid; 
            grid-template-columns: 1fr 420px; 
            gap: 32px; 
        }
        
        /* Reviews List */
        .reviews-list { 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
        }
        .review-card { 
            background: #fff; 
            padding: 24px; 
            border-radius: 16px; 
            box-shadow: 0 8px 32px rgba(0,0,0,0.08); 
            border: 1px solid rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }
        .review-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }
        .review-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 12px; 
        }
        .doctor-name { 
            font-weight: 700; 
            font-size: 18px;
            color: #1a1a1a;
        }
        .rating { 
            font-weight: 800; 
            color: #ffb400; 
            font-size: 16px;
        }
        .review-text { 
            color: #333; 
            margin-top: 12px; 
            line-height: 1.6;
        }
        .review-meta { 
            color: #777; 
            font-size: 13px; 
            margin-top: 16px; 
            padding-top: 12px;
            border-top: 1px solid rgba(0,0,0,0.06);
        }

        /* Review Form */
        .review-form { 
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); 
            padding: 28px; 
            border-radius: 20px; 
            box-shadow: 0 12px 40px rgba(0,0,0,0.1); 
            border: 1px solid rgba(0,0,0,0.06);
            position: sticky;
            top: 20px;
        }
        .review-form h3 {
            margin: 0 0 24px 0;
            color: #1a1a1a;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            color: #2c3e50; 
            font-weight: 600; 
            font-size: 14px;
        }
        
        /* Form Controls */
        .form-group select, 
        .form-group textarea, 
        .form-group input[type="number"] { 
            width: 100%; 
            padding: 14px 16px; 
            border: 2px solid #e9ecef; 
            border-radius: 12px; 
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fff;
            box-sizing: border-box;
        }
        .form-group select:focus, 
        .form-group textarea:focus, 
        .form-group input[type="number"]:focus { 
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-group textarea { 
            min-height: 120px; 
            resize: vertical; 
            font-family: inherit;
        }

        /* Star Rating */
        .star-rating {
            display: flex;
            gap: 4px;
            margin: 8px 0;
        }
        .star {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .star:hover,
        .star.active {
            color: #ffb400;
        }
        .star:hover ~ .star {
            color: #ddd;
        }
        
        /* Hidden number input for rating */
        .form-group input[type="number"].rating-input {
            display: none;
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 16px 0;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-size: 14px;
            color: #495057;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn { 
            display: inline-block; 
            padding: 14px 24px; 
            border-radius: 12px; 
            font-weight: 600;
            font-size: 14px;
            text-decoration: none; 
            border: none; 
            cursor: pointer; 
            transition: all 0.3s ease;
            text-align: center;
            flex: 1;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(0, 123, 255, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }
        .btn-secondary { 
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: #fff;
        }
        .btn-secondary:hover {
            transform: translateY(-1px);
        }

        /* Messages */
        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #c3e6cb;
            box-shadow: 0 4px 12px rgba(21, 87, 36, 0.1);
        }
        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #f5c6cb;
            box-shadow: 0 4px 12px rgba(114, 28, 36, 0.1);
        }

        /* No appointments message */
        .no-appointments {
            text-align: center;
            padding: 60px 40px;
            background: #f8f9fa;
            border-radius: 16px;
            color: #6c757d;
        }
        .no-appointments h3 {
            color: #495057;
            margin-bottom: 12px;
        }

        /* Centering when there are no reviews */
        .reviews-grid.single-center {
            grid-template-columns: 1fr; /* single column */
            justify-items: center; /* center aside/form */
        }

        .reviews-grid.single-center .review-form {
            position: static; /* don't stick */
            max-width: 700px;
            width: 100%;
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .reviews-grid { 
                grid-template-columns: 1fr; 
            }
            .review-form {
                position: static;
            }
        }
        @media (max-width: 600px) {
            .reviews-container {
                padding: 16px;
            }
            .review-form {
                padding: 20px;
            }
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../includes/header.php'; ?>

    <div class="patient-container">
        <div class="patient-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-user"></i> Patient Portal</h3>
                <p style="margin: 0.5rem 0 0 0; color: #ffffffff; font-size: 0.9rem; font-weight: 500;">
                    <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
                </p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_patients.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="book-appointment.php" class="nav-item">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
                <a href="reviews.php" class="nav-item active">
                    <i class="fas fa-star"></i> Reviews
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i> My Profile
                </a>
            </nav>
        </div>

        <div class="patient-content">
            <div class="content-header">
                <h1>My Reviews</h1>
                <p>Manage and submit reviews for doctors you've visited.</p>
            </div>

            <div class="reviews-container">
                <?php if ($success): ?>
                    <div class="success-message"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?>
                    </div>
                <?php endif; ?>

                <div class="reviews-grid<?php if (empty($reviews)) echo ' single-center'; ?>">
                    <?php if (!empty($reviews)): ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $r): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="doctor-name">Dr. <?= htmlspecialchars($r['doctor_first_name'] . ' ' . $r['doctor_last_name']) ?></div>
                                    <div class="rating"><?= intval($r['rating']) ?> / 5</div>
                                </div>
                                <div class="review-text"><?= nl2br(htmlspecialchars($r['review_text'])) ?></div>
                                <div class="review-meta">Submitted: <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?> <?php if ($r['is_anonymous']) echo '• Anonymous'; ?> <?php if ($r['is_approved'] == 0) echo '• Pending approval'; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <aside>
                        <div class="review-form">
                            <h3>✨ Submit a Review</h3>
                            <form action="process_review.php" method="post">
                                <div class="form-group">
                                    <label for="doctor_id">Select Doctor</label>
                                    <select name="doctor_id" id="doctor_id" required>
                                        <option value="">Choose a doctor to review...</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?= htmlspecialchars($doc['id']) ?>">Dr. <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?> — <?= htmlspecialchars($doc['specialty']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Rating</label>
                                    <div class="star-rating" id="starRating">
                                        <span class="star" data-rating="1">★</span>
                                        <span class="star" data-rating="2">★</span>
                                        <span class="star" data-rating="3">★</span>
                                        <span class="star" data-rating="4">★</span>
                                        <span class="star" data-rating="5">★</span>
                                    </div>
                                    <input type="number" name="rating" id="rating" min="1" max="5" value="5" class="rating-input" required>
                                </div>

                                <div class="form-group">
                                    <label for="review_text">Your Experience</label>
                                    <textarea name="review_text" id="review_text" placeholder="Share your experience with this doctor. What did you like? How was the service? Any recommendations for future patients?"></textarea>
                                </div>

                                <div class="checkbox-group">
                                    <input type="checkbox" name="is_anonymous" id="is_anonymous" value="1">
                                    <label for="is_anonymous">Submit anonymously</label>
                                </div>

                                <div class="form-actions">
                                    <button class="btn btn-primary" type="submit">Submit Review</button>
                                    <a href="reviews.php" class="btn btn-secondary">Clear Form</a>
                                </div>
                            </form>
                        </div>
                    </aside>
                </div>
            </div>

        </div>
    </div>

    <?php require_once '../includes/footer.php'; ?>

    <script>
        // Interactive star rating
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            const ratingInput = document.getElementById('rating');
            
            // Set initial rating (5 stars)
            updateStars(5);
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    ratingInput.value = rating;
                    updateStars(rating);
                });
                
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    highlightStars(rating);
                });
            });
            
            document.querySelector('.star-rating').addEventListener('mouseleave', function() {
                updateStars(parseInt(ratingInput.value));
            });
            
            function updateStars(rating) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }
            
            function highlightStars(rating) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.style.color = '#ffb400';
                    } else {
                        star.style.color = '#ddd';
                    }
                });
            }
        });
    </script>
</body>
</html>
