<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate QR data
    $qrData = json_decode($data['qr_data'], true);
    
    if(!$qrData || !isset($qrData['user_id'])) {
        throw new Exception("Invalid QR code");
    }

    $user_id = $qrData['user_id'];
    $current_time = date('Y-m-d H:i:s');

    // Check if already checked in today
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE user_id = ? 
        AND DATE(check_in) = CURDATE()
        ORDER BY check_in DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $lastEntry = $stmt->fetch();

    if($lastEntry && !$lastEntry['check_out']) {
        // Update check-out time
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET check_out = ? 
            WHERE id = ?
        ");
        $stmt->execute([$current_time, $lastEntry['id']]);
        $message = "Checked out successfully at " . $current_time;
    } else {
        // Create new check-in
        $stmt = $pdo->prepare("
            INSERT INTO attendance (user_id, check_in)
            VALUES (?, ?)
        ");
        $stmt->execute([$user_id, $current_time]);
        $message = "Checked in successfully at " . $current_time;
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>