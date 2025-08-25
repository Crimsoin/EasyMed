<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$db = Database::getInstance();

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = intval($_POST['payment_id']);
    $action = $_POST['action'];
    
    if ($action === 'verify' || $action === 'reject') {
        $status = ($action === 'verify') ? 'verified' : 'rejected';
        
        $db->update('payments', [
            'status' => $status,
            'verified_by' => $_SESSION['user_id'],
            'verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $payment_id]);
        
        // Update appointment payment status
        if ($status === 'verified') {
            $payment = $db->fetch("SELECT appointment_id FROM payments WHERE id = ?", [$payment_id]);
            if ($payment) {
                $db->update('appointments', [
                    'payment_status' => 'verified',
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $payment['appointment_id']]);
            }
        }
        
        $_SESSION['admin_message'] = "Payment {$action}d successfully!";
        header('Location: payment-management.php');
        exit();
    }
}

// Get all payments with details
$payments = $db->fetchAll("
    SELECT 
        p.*, a.appointment_date, a.appointment_time,
        u_patient.first_name as patient_first_name, u_patient.last_name as patient_last_name,
        u_doctor.first_name as doctor_first_name, u_doctor.last_name as doctor_last_name,
        u_verified.first_name as verified_by_first_name, u_verified.last_name as verified_by_last_name,
        JSON_EXTRACT(a.patient_info, '$.reference_number') as reference_number
    FROM payments p
    JOIN appointments a ON p.appointment_id = a.id
    JOIN patients pt ON p.patient_id = pt.id
    JOIN users u_patient ON pt.user_id = u_patient.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_doctor ON d.user_id = u_doctor.id
    LEFT JOIN users u_verified ON p.verified_by = u_verified.id
    ORDER BY p.submitted_at DESC
");

$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - EasyMed Admin</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .payment-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .payment-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .payment-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        .status-pending_verification {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-verified {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-group {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            color: #212529;
        }
        
        .payment-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .receipt-link {
            color: #007bff;
            text-decoration: none;
        }
        
        .receipt-link:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .alert-success {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #b6d4ea;
        }
    </style>
</head>
<body>
    <?php require_once '../includes/header.php'; ?>

    <div class="admin-container">
        <h1><i class="fas fa-credit-card"></i> Payment Management</h1>
        <p>Review and verify patient payment submissions</p>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="payment-grid">
            <?php if (empty($payments)): ?>
                <div class="payment-card" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <h3 style="color: #666;">No Payment Submissions</h3>
                    <p style="color: #999;">Payment submissions will appear here for review.</p>
                </div>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <div class="payment-card">
                        <div class="payment-header">
                            <div>
                                <h3 style="margin: 0; color: #333;">
                                    Payment #<?= $payment['id'] ?>
                                </h3>
                                <p style="margin: 0.5rem 0 0 0; color: #666;">
                                    Reference: <?= htmlspecialchars(trim($payment['reference_number'], '"')) ?>
                                </p>
                            </div>
                            <span class="payment-status status-<?= $payment['status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $payment['status'])) ?>
                            </span>
                        </div>
                        
                        <div class="payment-details">
                            <div class="detail-group">
                                <div class="detail-label">Patient</div>
                                <div class="detail-value">
                                    <?= htmlspecialchars($payment['patient_first_name'] . ' ' . $payment['patient_last_name']) ?>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Doctor</div>
                                <div class="detail-value">
                                    Dr. <?= htmlspecialchars($payment['doctor_first_name'] . ' ' . $payment['doctor_last_name']) ?>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Appointment</div>
                                <div class="detail-value">
                                    <?= date('M j, Y', strtotime($payment['appointment_date'])) ?><br>
                                    <?= date('g:i A', strtotime($payment['appointment_time'])) ?>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Amount</div>
                                <div class="detail-value" style="font-size: 1.2rem; font-weight: bold; color: #007bff;">
                                    ₱<?= number_format($payment['amount'], 2) ?>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">GCash Reference</div>
                                <div class="detail-value">
                                    <?= htmlspecialchars($payment['gcash_reference']) ?>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Submitted</div>
                                <div class="detail-value">
                                    <?= date('M j, Y g:i A', strtotime($payment['submitted_at'])) ?>
                                </div>
                            </div>
                            
                            <?php if ($payment['receipt_file']): ?>
                            <div class="detail-group">
                                <div class="detail-label">Receipt</div>
                                <div class="detail-value">
                                    <a href="<?= SITE_URL ?>/assets/uploads/payment_receipts/<?= htmlspecialchars($payment['receipt_file']) ?>" 
                                       target="_blank" class="receipt-link">
                                        <i class="fas fa-file-image"></i> View Receipt
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($payment['payment_notes']): ?>
                            <div class="detail-group">
                                <div class="detail-label">Notes</div>
                                <div class="detail-value">
                                    <?= htmlspecialchars($payment['payment_notes']) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($payment['verified_by']): ?>
                            <div class="detail-group">
                                <div class="detail-label">Verified By</div>
                                <div class="detail-value">
                                    <?= htmlspecialchars($payment['verified_by_first_name'] . ' ' . $payment['verified_by_last_name']) ?><br>
                                    <small><?= date('M j, Y g:i A', strtotime($payment['verified_at'])) ?></small>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($payment['status'] === 'pending_verification'): ?>
                        <div class="payment-actions">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger" 
                                        onclick="return confirm('Are you sure you want to reject this payment?')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                <input type="hidden" name="action" value="verify">
                                <button type="submit" class="btn btn-success" 
                                        onclick="return confirm('Are you sure you want to verify this payment?')">
                                    <i class="fas fa-check"></i> Verify Payment
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once '../includes/footer.php'; ?>
</body>
</html>
