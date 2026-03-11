<?php
$page_title = 'Manage Feedbacks';
$additional_css = ['admin/sidebar.css', 'admin/feedback_admin.css'];
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database_helper.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Fetch reviews with patient and doctor names
$reviews = $db->fetchAll("SELECT r.*, p.user_id as patient_user_id, u_p.first_name as patient_first_name, u_p.last_name as patient_last_name, d.user_id as doctor_user_id, u_d.first_name as doctor_first_name, u_d.last_name as doctor_last_name
    FROM reviews r
    LEFT JOIN patients p ON r.patient_id = p.id
    LEFT JOIN users u_p ON p.user_id = u_p.id
    LEFT JOIN doctors d ON r.doctor_id = d.id
    LEFT JOIN users u_d ON d.user_id = u_d.id
    ORDER BY r.created_at DESC");

require_once '../../includes/header.php';
?>

<div class="admin-container">
    <?php include __DIR__ . '/../components/sidebar.php'; ?>

    <div class="admin-content">
        <div class="content-header">
            <h1>Manage Feedbacks</h1>
            <p>View all patient feedback and ratings.</p>
        </div>

        <div class="content-section">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Rating</th>
                            <th>Feedback</th>
                            <th>Anonymous</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td>
                                <?php 
                                if ($r['is_anonymous']) {
                                    echo '<span class="censored-name">Anonymous Patient</span>';
                                } else {
                                    echo $r['patient_user_id'] ? htmlspecialchars($r['patient_first_name'] . ' ' . $r['patient_last_name']) : 'Guest';
                                }
                                ?>
                            </td>
                            <td><?php echo $r['doctor_user_id'] ? htmlspecialchars($r['doctor_first_name'] . ' ' . $r['doctor_last_name']) : 'Unknown'; ?></td>
                            <td>
                                <div class="rating-display">
                                    <?php 
                                    $rating = intval($r['rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rating ? '<i class="fas fa-star star-filled"></i>' : '<i class="far fa-star star-empty"></i>';
                                    }
                                    ?>
                                    <span class="rating-number"><?php echo $rating; ?>/5</span>
                                </div>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($r['review_text'])); ?></td>
                            <td><?php echo $r['is_anonymous'] ? '<span class="pill pill-anonymous">Anonymous</span>' : '<span class="pill pill-public">Public</span>'; ?></td>
                            <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
