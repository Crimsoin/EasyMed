<?php
// Simplified test for appointment booking
session_start();

// Set up basic config without requiring files
define('SITE_URL', 'http://localhost/Project_EasyMed');
define('SITE_NAME', 'EasyMed');

// Mock session data
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'patient';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Patient';

// Mock doctor data
$doctors = [
    [
        'user_id' => 1,
        'first_name' => 'John',
        'last_name' => 'Smith',
        'specialty' => 'General Practice',
        'schedule_days' => 'Monday,Tuesday,Wednesday',
        'schedule_time_start' => '09:00:00',
        'schedule_time_end' => '17:00:00',
        'consultation_fee' => 500.00,
        'profile_image' => null
    ]
];

$appointment_errors = [];
$appointment_success = null;
$appointment_data = [];
$current_user = [
    'first_name' => 'Test',
    'last_name' => 'Patient',
    'email' => 'test@example.com',
    'phone' => '123-456-7890'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Book Appointment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .doctor-card { border: 1px solid #ddd; padding: 20px; margin: 10px; border-radius: 8px; }
        .btn { background: #00b4cc; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #0891a5; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
        }
        
        .close {
            float: right;
            font-size: 28px;
            cursor: pointer;
        }
        
        .form-control {
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <h1>Test Book Appointment Page</h1>
    
    <div class="doctors-section">
        <h2>Available Doctors</h2>
        
        <?php foreach ($doctors as $doctor): ?>
            <div class="doctor-card">
                <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                <p><strong>Specialty:</strong> <?php echo htmlspecialchars($doctor['specialty']); ?></p>
                <p><strong>Fee:</strong> ₱<?php echo number_format($doctor['consultation_fee'], 2); ?></p>
                
                <button class="btn" onclick="testButtonClick()">Test Alert</button>
                <button class="btn" onclick="openAppointmentModal(<?php echo htmlspecialchars(json_encode($doctor)); ?>)">
                    <i class="fas fa-calendar-plus"></i> Book with Dr. <?php echo htmlspecialchars($doctor['last_name']); ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Book Appointment</h2>
            
            <div id="modalDoctorPanel">
                <!-- Doctor details will be shown here -->
            </div>
            
            <form>
                <label>First Name:</label>
                <input type="text" id="modal_first_name" class="form-control">
                
                <label>Last Name:</label>
                <input type="text" id="modal_last_name" class="form-control">
                
                <label>Phone:</label>
                <input type="text" id="modal_phone" class="form-control">
                
                <label>Email:</label>
                <input type="email" id="modal_email" class="form-control">
                
                <button type="submit" class="btn">Submit</button>
            </form>
        </div>
    </div>

    <script>
        function testButtonClick() {
            alert('Test button works! The issue might be with the openAppointmentModal function.');
        }
        
        function openAppointmentModal(doctor) {
            console.log('openAppointmentModal called with:', doctor);
            
            try {
                // Fill doctor details
                var panelHtml = '<h3>Dr. ' + doctor.first_name + ' ' + doctor.last_name + '</h3>';
                panelHtml += '<p>Specialty: ' + doctor.specialty + '</p>';
                document.getElementById('modalDoctorPanel').innerHTML = panelHtml;
                
                // Auto-fill patient data
                <?php if (empty($appointment_data)): ?>
                document.getElementById('modal_first_name').value = '<?php echo htmlspecialchars($current_user['first_name'] ?? ''); ?>';
                document.getElementById('modal_last_name').value = '<?php echo htmlspecialchars($current_user['last_name'] ?? ''); ?>';
                document.getElementById('modal_phone').value = '<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>';
                document.getElementById('modal_email').value = '<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>';
                <?php endif; ?>
                
                // Show the modal
                document.getElementById('appointmentModal').style.display = 'block';
                console.log('Modal should be visible now');
                
            } catch (error) {
                console.error('Error in openAppointmentModal:', error);
                alert('Error: ' + error.message);
            }
        }
        
        // Close modal handlers
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('appointmentModal');
            var closeBtn = modal.querySelector('.close');
            
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            }
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        });
        
        console.log('JavaScript loaded successfully');
    </script>
</body>
</html>
