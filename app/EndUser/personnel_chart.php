<?php
// Set headers for JSON response
header('Content-Type: application/json');

// Define base sanctioned strength and typical staffing levels
$positions = [
    [
        'post_id' => 1,
        'name' => 'Police Inspector (PI)',
        'sanctioned' => 3,
        'min_filled' => 1,
        'max_filled' => 3
    ],
    [
        'post_id' => 2,
        'name' => 'Police Sub-Inspector (PSI)',
        'sanctioned' => 6,
        'min_filled' => 3,
        'max_filled' => 6
    ],
    [
        'post_id' => 3,
        'name' => 'Assistant Sub-Inspector (ASI)',
        'sanctioned' => 10,
        'min_filled' => 6,
        'max_filled' => 9
    ],
    [
        'post_id' => 4,
        'name' => 'Head Constable (HC)',
        'sanctioned' => 20,
        'min_filled' => 13,
        'max_filled' => 18
    ],
    [
        'post_id' => 5,
        'name' => 'Constable',
        'sanctioned' => 50,
        'min_filled' => 37,
        'max_filled' => 47
    ],
    [
        'post_id' => 6,
        'name' => 'Radio Mechanic (RM)',
        'sanctioned' => 5,
        'min_filled' => 2,
        'max_filled' => 5
    ]
];

// Generate more realistic data with proper variations
$result = [];
$totalSanctioned = 0;
$totalFilled = 0;

foreach ($positions as $position) {
    // Create realistic random values within specified ranges
    $filled = rand($position['min_filled'], $position['max_filled']);
    
    // Occasionally simulate critical staffing (10% chance)
    if (rand(1, 10) == 1) {
        $filled = max(1, floor($position['min_filled'] * 0.7));
    }
    
    // Ensure filled doesn't exceed sanctioned
    $filled = min($filled, $position['sanctioned']);
    $vacant = $position['sanctioned'] - $filled;
    
    // Track totals
    $totalSanctioned += $position['sanctioned'];
    $totalFilled += $filled;
    
    // Add to result array with a standardized format
    $result[] = [
        'post_id' => $position['post_id'],
        'name' => $position['name'],
        'filled' => $filled,
        'vacant' => $vacant,
        'total' => $position['sanctioned']
    ];
}

// Create the response with additional useful information
$response = [
    'positions' => $result,
    'summary' => [
        'total_sanctioned' => $totalSanctioned,
        'total_filled' => $totalFilled,
        'total_vacant' => $totalSanctioned - $totalFilled,
        'staffing_percentage' => round(($totalFilled / $totalSanctioned) * 100)
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response);
?>