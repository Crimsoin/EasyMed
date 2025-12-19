<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$db = Database::getInstance();

// Get export parameters
$export_type = $_GET['export'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Validate dates
if (!isValidDate($start_date) || !isValidDate($end_date)) {
    header('Location: reports.php?error=invalid_dates');
    exit();
}

// Generate report data
$appointments = $db->fetchAll("
    SELECT 
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.reason,
        a.created_at,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.email as patient_email,
        p.phone as patient_phone,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        doc.specialty,
        doc.consultation_fee
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    JOIN doctors doc ON d.id = doc.user_id
    WHERE a.appointment_date BETWEEN ? AND ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
", [$start_date, $end_date]);

$filename = 'appointments_report_' . $start_date . '_to_' . $end_date;

if ($export_type === 'csv') {
    // Export as CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Appointment ID',
        'Date',
        'Time',
        'Patient Name',
        'Patient Email',
        'Patient Phone',
        'Doctor Name',
        'Specialty',
        'Reason',
        'Status',
        'Consultation Fee',
        'Booked Date'
    ]);
    
    // CSV Data
    foreach ($appointments as $appointment) {
        fputcsv($output, [
            $appointment['id'],
            formatDate($appointment['appointment_date']),
            formatTime($appointment['appointment_time']),
            $appointment['patient_name'],
            $appointment['patient_email'],
            $appointment['patient_phone'],
            $appointment['doctor_name'],
            $appointment['specialty'],
            $appointment['reason'],
            ucfirst($appointment['status']),
            '$' . number_format($appointment['consultation_fee'], 2),
            formatDateTime($appointment['created_at'])
        ]);
    }
    
    fclose($output);
    exit();
    
} elseif ($export_type === 'pdf') {
    // Simple HTML to PDF export (you can integrate with libraries like TCPDF or MPDF for better results)
    
    // Calculate statistics
    $stats = [
        'total' => count($appointments),
        'completed' => count(array_filter($appointments, function($a) { return $a['status'] === 'completed'; })),
        'pending' => count(array_filter($appointments, function($a) { return $a['status'] === 'pending'; })),
        'cancelled' => count(array_filter($appointments, function($a) { return $a['status'] === 'cancelled'; })),
        'total_revenue' => array_sum(array_map(function($a) { 
            return $a['status'] === 'completed' ? $a['consultation_fee'] : 0; 
        }, $appointments))
    ];
    
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Appointments Report</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                color: #333;
            }
            .header { 
                text-align: center; 
                margin-bottom: 30px;
                border-bottom: 2px solid #00bcd4;
                padding-bottom: 20px;
            }
            .header h1 { 
                color: #00bcd4; 
                margin: 0;
            }
            .stats { 
                display: flex; 
                justify-content: space-around; 
                margin-bottom: 30px; 
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
            }
            .stat { 
                text-align: center; 
            }
            .stat h3 { 
                margin: 0; 
                color: #00bcd4;
                font-size: 24px;
            }
            .stat p { 
                margin: 5px 0 0 0; 
                color: #666;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px;
            }
            th, td { 
                border: 1px solid #ddd; 
                padding: 8px; 
                text-align: left;
                font-size: 12px;
            }
            th { 
                background-color: #00bcd4; 
                color: white;
                font-weight: bold;
            }
            tr:nth-child(even) { 
                background-color: #f9f9f9; 
            }
            .status { 
                padding: 2px 8px; 
                border-radius: 4px; 
                font-size: 10px;
                font-weight: bold;
            }
            .status.completed { background: #d4edda; color: #155724; }
            .status.pending { background: #fff3cd; color: #856404; }
            .status.confirmed { background: #d1ecf1; color: #0c5460; }
            .status.cancelled { background: #f8d7da; color: #721c24; }
            .footer { 
                margin-top: 30px; 
                text-align: center; 
                color: #666;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>EasyMed - Appointments Report</h1>
            <p>Period: <?php echo formatDate($start_date); ?> to <?php echo formatDate($end_date); ?></p>
        </div>
        
        <div class="stats">
            <div class="stat">
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Appointments</p>
            </div>
            <div class="stat">
                <h3><?php echo $stats['completed']; ?></h3>
                <p>Completed</p>
            </div>
            <div class="stat">
                <h3><?php echo $stats['pending']; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat">
                <h3>₱<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                <p>Revenue</p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Specialty</th>
                    <th>Status</th>
                    <th>Fee</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?php echo $appointment['id']; ?></td>
                        <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                        <td><?php echo formatTime($appointment['appointment_time']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['specialty']); ?></td>
                        <td>
                            <span class="status <?php echo $appointment['status']; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </td>
                        <td>₱<?php echo number_format($appointment['consultation_fee'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Generated on <?php echo formatDateTime(date('Y-m-d H:i:s')); ?> by EasyMed System</p>
        </div>
        
        <script>
            // Auto-print for PDF
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Invalid export type
header('Location: reports.php?error=invalid_export');
exit();
?>
