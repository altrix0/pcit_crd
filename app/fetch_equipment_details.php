<?php
// Start session and include database connection
session_start();
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Check if action parameter is provided
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'models':
                // Get models for a specific make
                if (isset($_GET['make'])) {
                    $make = $_GET['make'];
                    
                    // Prepare and execute the query
                    $stmt = $pdo->prepare("SELECT model FROM equipment_options WHERE make = ? ORDER BY model");
                    $stmt->execute([$make]);
                    
                    // Fetch all models as a simple array
                    $models = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Return JSON response
                    header('Content-Type: application/json');
                    echo json_encode($models);
                } else {
                    throw new Exception('Make parameter is required');
                }
                break;
                
            case 'deployments':
                // Get all deployment options
                $stmt = $pdo->prepare("SELECT deployment_id, name, deployment_type, height_of_mast, type_of_mast, deployment_location 
                                      FROM deployment 
                                      ORDER BY name");
                $stmt->execute();
                $deployments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Return JSON response
                header('Content-Type: application/json');
                echo json_encode($deployments);
                break;
                
            case 'deployment':
                // Get details for a specific deployment
                if (isset($_GET['id'])) {
                    $deployment_id = $_GET['id'];
                    
                    $stmt = $pdo->prepare("SELECT deployment_id, name, deployment_type, height_of_mast, type_of_mast, deployment_location 
                                          FROM deployment 
                                          WHERE deployment_id = ?");
                    $stmt->execute([$deployment_id]);
                    $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Return JSON response
                    header('Content-Type: application/json');
                    echo json_encode($deployment);
                } else {
                    throw new Exception('Deployment ID is required');
                }
                break;
                
            default:
                throw new Exception('Invalid action specified');
        }
    } 
    catch (PDOException $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    catch (Exception $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
} 
else {
    // Return error if action parameter is missing
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Action parameter is required']);
}
?>
