<?php
// Generate a simple GCash QR code placeholder
// In a real application, you would integrate with GCash API

header('Content-Type: image/svg+xml');
header('Cache-Control: no-cache');

$gcash_number = '09123456789';
$amount = $_GET['amount'] ?? '1500.00';
$reference = $_GET['ref'] ?? 'APT-2025-0001';

// SVG QR Code placeholder
echo '<?xml version="1.0" encoding="UTF-8"?>
<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
  <rect width="200" height="200" fill="#ffffff" stroke="#cccccc" stroke-width="1"/>
  
  <!-- QR Code pattern (simplified) -->
  <g fill="#000000">
    <!-- Corner squares -->
    <rect x="10" y="10" width="50" height="50"/>
    <rect x="140" y="10" width="50" height="50"/>
    <rect x="10" y="140" width="50" height="50"/>
    
    <!-- Inner corner squares -->
    <rect x="20" y="20" width="30" height="30" fill="#ffffff"/>
    <rect x="150" y="20" width="30" height="30" fill="#ffffff"/>
    <rect x="20" y="150" width="30" height="30" fill="#ffffff"/>
    
    <!-- Center squares -->
    <rect x="30" y="30" width="10" height="10"/>
    <rect x="160" y="30" width="10" height="10"/>
    <rect x="30" y="160" width="10" height="10"/>
    
    <!-- Sample data pattern -->
    <rect x="70" y="20" width="10" height="10"/>
    <rect x="90" y="20" width="10" height="10"/>
    <rect x="110" y="20" width="10" height="10"/>
    <rect x="70" y="40" width="10" height="10"/>
    <rect x="110" y="40" width="10" height="10"/>
    
    <rect x="20" y="70" width="10" height="10"/>
    <rect x="40" y="70" width="10" height="10"/>
    <rect x="170" y="70" width="10" height="10"/>
    
    <rect x="80" y="80" width="40" height="40"/>
    <rect x="90" y="90" width="20" height="20" fill="#ffffff"/>
    <rect x="95" y="95" width="10" height="10"/>
    
    <rect x="20" y="170" width="10" height="10"/>
    <rect x="40" y="170" width="10" height="10"/>
    <rect x="150" y="170" width="10" height="10"/>
    <rect x="170" y="170" width="10" height="10"/>
  </g>
  
  <!-- GCash branding -->
  <text x="100" y="15" text-anchor="middle" font-family="Arial, sans-serif" font-size="8" fill="#0066cc">GCash</text>
  
  <!-- Amount and reference info -->
  <text x="100" y="230" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" fill="#333333">₱' . htmlspecialchars($amount) . '</text>
  <text x="100" y="245" text-anchor="middle" font-family="Arial, sans-serif" font-size="8" fill="#666666">' . htmlspecialchars($reference) . '</text>
</svg>';
?>
