<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    if (!isset($_SESSION['scan_attempt'])) {
        throw new Exception('No active scan session');
    }
    
    $response = [
        'processed' => $_SESSION['scan_attempt']['processed'],
        'session_id' => session_id(),
        'scan_id' => $_SESSION['scan_attempt']['id']
    ];
    
    // Cleanup old sessions
    if (time() - $_SESSION['scan_attempt']['generated_at'] > 120) {
        unset($_SESSION['scan_attempt']);
    }
    
} catch(Exception $e) {
    $response = [
        'error' => $e->getMessage(),
        'processed' => false
    ];
}

echo json_encode($response);
exit();
?>