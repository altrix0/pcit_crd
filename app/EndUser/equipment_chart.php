<?php
// Prevent any PHP errors/warnings from being displayed in output
error_reporting(0);
ini_set('display_errors', 0);

// Set content type to JSON
header('Content-Type: application/json');

// Get view type parameter (default to 'make')
$view_type = isset($_GET['view']) ? $_GET['view'] : 'make';

// Define mock equipment data
// This would normally come from a database query
$mockEquipment = [
    // Radio Equipment
    ['make' => 'Motorola', 'model' => 'APX 6000', 'count' => 12],
    ['make' => 'Motorola', 'model' => 'XPR 7550e', 'count' => 8],
    ['make' => 'Motorola', 'model' => 'SL300', 'count' => 5],
    ['make' => 'Kenwood', 'model' => 'NX-5000', 'count' => 6],
    ['make' => 'Kenwood', 'model' => 'NX-3000', 'count' => 4],
    ['make' => 'Hytera', 'model' => 'PD785', 'count' => 5],
    ['make' => 'Hytera', 'model' => 'PD565', 'count' => 3],
    
    // Body Cameras
    ['make' => 'Axon', 'model' => 'Body 3', 'count' => 15],
    ['make' => 'Axon', 'model' => 'Flex 2', 'count' => 7],
    ['make' => 'Motorola', 'model' => 'V300', 'count' => 7],
    ['make' => 'Panasonic', 'model' => 'Arbitrator BWC', 'count' => 3],
    
    // Mobile Devices
    ['make' => 'Samsung', 'model' => 'Galaxy Tab Active', 'count' => 6],
    ['make' => 'Apple', 'model' => 'iPad', 'count' => 4],
    ['make' => 'Getac', 'model' => 'F110', 'count' => 2],
    
    // Other Equipment
    ['make' => 'Taser', 'model' => 'X26P', 'count' => 10],
    ['make' => 'Taser', 'model' => 'X2', 'count' => 5],
    ['make' => 'Safariland', 'model' => '6360 Holster', 'count' => 20],
    ['make' => 'Streamlight', 'model' => 'Stinger', 'count' => 18]
];

// Process data based on view type
if ($view_type === 'make') {
    // Group by make
    $makeCount = [];
    
    foreach ($mockEquipment as $item) {
        if (!isset($makeCount[$item['make']])) {
            $makeCount[$item['make']] = 0;
        }
        $makeCount[$item['make']] += $item['count'];
    }
    
    // Convert to result format
    $result = [];
    foreach ($makeCount as $make => $count) {
        $result[] = [
            'make' => $make,
            'count' => $count
        ];
    }
    
    // Sort by count descending
    usort($result, function($a, $b) {
        return $b['count'] - $a['count'];
    });
} else {
    // For model view, limit to top 10 items for better chart readability
    usort($mockEquipment, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    // Take top models
    $result = array_slice($mockEquipment, 0, 10);
}

// Add a small random variation to counts (±10%) to simulate changing data
foreach ($result as &$item) {
    $variation = rand(-10, 10) / 100; // -10% to +10%
    $item['count'] = max(1, round($item['count'] * (1 + $variation)));
}

// Add a small delay to simulate network latency (remove in production)
usleep(rand(200000, 500000)); // 200-500ms delay

// Return JSON data with timestamp
echo json_encode($result);
?>