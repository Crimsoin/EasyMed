<?php
require_once '../includes/database.php';

$db = Database::getInstance();

// Get the appointment with reference APT-2025-5633
$appointment = $db->fetch("
    SELECT a.id, a.doctor_id,
           JSON_EXTRACT(a.patient_info, '$.reference_number') as ref_num,
           JSON_EXTRACT(a.patient_info, '$.purpose') as purpose,
           JSON_EXTRACT(a.patient_info, '$.laboratory') as laboratory,
           d.consultation_fee
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE JSON_EXTRACT(a.patient_info, '$.reference_number') LIKE '%5633%'
");

echo "<pre>";
echo "Appointment Details:\n";
print_r($appointment);

if ($appointment) {
    $purpose = trim($appointment['purpose'] ?? '', '"');
    $laboratory = trim($appointment['laboratory'] ?? '', '"');
    
    echo "\n\nPurpose: $purpose\n";
    echo "Laboratory: $laboratory\n";
    echo "Consultation Fee: {$appointment['consultation_fee']}\n";
    
    if ($purpose === 'laboratory' && !empty($laboratory)) {
        $lab_offer = $db->fetch("
            SELECT lo.price, lo.title, lo.id
            FROM lab_offers lo
            JOIN lab_offer_doctors lod ON lo.id = lod.lab_offer_id
            WHERE lo.title = ? AND lod.doctor_id = ? AND lo.is_active = 1
        ", [$laboratory, $appointment['doctor_id']]);
        
        echo "\n\nLab Offer Query Result:\n";
        print_r($lab_offer);
    }
    
    echo "\n\nCorrect URL: payment-gateway.php?appointment_id={$appointment['id']}";
}
echo "</pre>";
?>
