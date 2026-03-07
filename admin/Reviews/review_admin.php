<?php
$page_title = 'Manage Reviews';
$additional_css = ['admin/sidebar.css', 'admin/review_admin.css'];
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
            <h1>Manage Reviews</h1>
            <p>Approve, delete or review patient feedback.</p>
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
                            <th>Review</th>
                            <th>Anonymous</th>
                            <th>Approved</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td><?php echo $r['patient_user_id'] ? htmlspecialchars($r['patient_first_name'] . ' ' . $r['patient_last_name']) : 'Guest'; ?></td>
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
                            <td><?php echo $r['is_approved'] ? '<span class="pill pill-approved">Approved</span>' : '<span class="pill pill-pending">Pending</span>'; ?></td>
                            <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                            <td>
                                <form method="post" action="process_review_admin.php" style="display:inline-block;">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <?php if (!$r['is_approved']): ?>
                                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this review?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
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
