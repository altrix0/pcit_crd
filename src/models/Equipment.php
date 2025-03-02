<?php
/**
 * Equipment Model
 * 
 * Handles equipment data management for the Central Resource Dashboard
 */
class Equipment {
    // Database connection
    private $conn;
    
    /**
     * Constructor - initialize with database connection
     * @param Database $db Database connection object
     */
    public function __construct($db) {
        $this->conn = $db->getConnection();
    }
    
    /**
     * Get equipment by ID
     * @param int $equipmentId The equipment ID
     * @return array|bool Equipment data or false if not found
     */
    public function getEquipmentById($equipmentId) {
        $query = "SELECT e.*, s.name as status_name, d.name as deployment_name, u.unit_name 
                FROM equipment e
                LEFT JOIN equipment_status s ON e.status = s.id
                LEFT JOIN deployment d ON e.deployment_id = d.deployment_id
                LEFT JOIN unit u ON e.unit_id = u.unit_id
                WHERE e.uid = :equipment_id";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':equipment_id', $equipmentId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }
    
    /**
     * Get all equipment with optional filtering
     * @param array $filters Optional filters for the query
     * @param int $page Page number for pagination
     * @param int $perPage Records per page
     * @return array Equipment records
     */
    public function getEquipment($filters = [], $page = 1, $perPage = 10) {
        // Base query
        $query = "SELECT e.*, s.name as status_name, d.name as deployment_name, u.unit_name 
                FROM equipment e
                LEFT JOIN equipment_status s ON e.status = s.id
                LEFT JOIN deployment d ON e.deployment_id = d.deployment_id
                LEFT JOIN unit u ON e.unit_id = u.unit_id
                WHERE 1=1";
        
        // Add filters if provided
        $params = [];
        
        if (!empty($filters['unit_id'])) {
            $query .= " AND e.unit_id = :unit_id";
            $params[':unit_id'] = $filters['unit_id'];
        }
        
        if (!empty($filters['equipment_type'])) {
            $query .= " AND e.equipment_type = :equipment_type";
            $params[':equipment_type'] = $filters['equipment_type'];
        }
        
        if (!empty($filters['status'])) {
            $query .= " AND e.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['modulation_type'])) {
            $query .= " AND e.modulation_type = :modulation_type";
            $params[':modulation_type'] = $filters['modulation_type'];
        }
        
        if (!empty($filters['freq_band'])) {
            $query .= " AND e.freq_band = :freq_band";
            $params[':freq_band'] = $filters['freq_band'];
        }
        
        if (isset($filters['locked'])) {
            $query .= " AND e.locked = :locked";
            $params[':locked'] = $filters['locked'];
        }
        
        // Add search if provided
        if (!empty($filters['search'])) {
            $query .= " AND (e.serial_number LIKE :search OR e.make LIKE :search OR e.model LIKE :search OR e.pw_no_year LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        // Add sorting
        $query .= " ORDER BY e.uid DESC";
        
        // Add pagination
        $offset = ($page - 1) * $perPage;
        $query .= " LIMIT :offset, :per_page";
        $params[':offset'] = $offset;
        $params[':per_page'] = $perPage;
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            // Handle LIMIT parameters differently as they need to be integers
            if ($key === ':offset' || $key === ':per_page') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count total equipment records with filters
     * @param array $filters Optional filters
     * @return int Total number of records
     */
    public function countEquipment($filters = []) {
        // Base query
        $query = "SELECT COUNT(*) as total FROM equipment e WHERE 1=1";
        
        // Add filters if provided
        $params = [];
        
        if (!empty($filters['unit_id'])) {
            $query .= " AND e.unit_id = :unit_id";
            $params[':unit_id'] = $filters['unit_id'];
        }
        
        if (!empty($filters['equipment_type'])) {
            $query .= " AND e.equipment_type = :equipment_type";
            $params[':equipment_type'] = $filters['equipment_type'];
        }
        
        if (!empty($filters['status'])) {
            $query .= " AND e.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['modulation_type'])) {
            $query .= " AND e.modulation_type = :modulation_type";
            $params[':modulation_type'] = $filters['modulation_type'];
        }
        
        if (!empty($filters['freq_band'])) {
            $query .= " AND e.freq_band = :freq_band";
            $params[':freq_band'] = $filters['freq_band'];
        }
        
        if (isset($filters['locked'])) {
            $query .= " AND e.locked = :locked";
            $params[':locked'] = $filters['locked'];
        }
        
        // Add search if provided
        if (!empty($filters['search'])) {
            $query .= " AND (e.serial_number LIKE :search OR e.make LIKE :search OR e.model LIKE :search OR e.pw_no_year LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'];
    }
    
    /**
     * Create new equipment record
     * @param array $data Equipment data
     * @return int|bool New equipment ID or false on failure
     */
    public function createEquipment($data) {
        try {
            // Build insert query using the provided data fields
            $fields = [];
            $placeholders = [];
            $values = [];
            
            foreach ($data as $field => $value) {
                $fields[] = $field;
                $placeholders[] = ":$field";
                $values[":$field"] = $value;
            }
            
            $query = "INSERT INTO equipment (" . implode(', ', $fields) . ") 
                     VALUES (" . implode(', ', $placeholders) . ")";
                     
            $stmt = $this->conn->prepare($query);
            
            // Bind all parameters
            foreach ($values as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating equipment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update equipment record
     * @param int $equipmentId Equipment ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function updateEquipment($equipmentId, $data) {
        try {
            // First check if equipment is locked
            $checkQuery = "SELECT locked FROM equipment WHERE uid = :equipment_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':equipment_id', $equipmentId);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $equipment = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // If equipment is locked, prevent update unless explicitly unlocking
                if ($equipment['locked'] && (!isset($data['locked']) || $data['locked'])) {
                    return false;
                }
            }
            
            // Build update query
            $updates = [];
            $values = [':equipment_id' => $equipmentId];
            
            foreach ($data as $field => $value) {
                $updates[] = "$field = :$field";
                $values[":$field"] = $value;
            }
            
            $query = "UPDATE equipment SET " . implode(', ', $updates) . " WHERE uid = :equipment_id";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind all parameters
            foreach ($values as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating equipment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete equipment record
     * @param int $equipmentId Equipment ID
     * @return bool Success status
     */
    public function deleteEquipment($equipmentId) {
        try {
            // Check if equipment is locked
            $checkQuery = "SELECT locked FROM equipment WHERE uid = :equipment_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':equipment_id', $equipmentId);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $equipment = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // Cannot delete locked equipment
                if ($equipment['locked']) {
                    return false;
                }
            }
            
            $query = "DELETE FROM equipment WHERE uid = :equipment_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':equipment_id', $equipmentId);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting equipment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lock equipment to prevent further edits
     * @param int $equipmentId Equipment ID
     * @return bool Success status
     */
    public function lockEquipment($equipmentId) {
        $query = "UPDATE equipment SET locked = 1 WHERE uid = :equipment_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':equipment_id', $equipmentId);
        
        return $stmt->execute();
    }
    
    /**
     * Unlock equipment to allow edits
     * @param int $equipmentId Equipment ID
     * @param int $userId User ID requesting unlock
     * @return bool Success status
     */
    public function unlockEquipment($equipmentId, $userId) {
        // Only users with higher access can unlock equipment
        require_once __DIR__ . '/../models/User.php';
        
        // Create a database wrapper object to pass to User model
        $dbWrapper = new stdClass();
        $dbWrapper->getConnection = function() { return $this->conn; };
        
        $userModel = new User($dbWrapper);
        
        // Check if user has sufficient privileges (level 3+)
        if (!$userModel->hasAccessLevel($userId, 3)) {
            return false;
        }
        
        $query = "UPDATE equipment SET locked = 0 WHERE uid = :equipment_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':equipment_id', $equipmentId);
        
        $success = $stmt->execute();
        
        if ($success) {
            // Log the unlock action
            $this->logEquipmentAction($equipmentId, $userId, 'unlocked');
        }
        
        return $success;
    }
    
    /**
     * Log equipment actions for audit
     * @param int $equipmentId Equipment ID
     * @param int $userId User ID
     * @param string $action Action performed
     * @param string $details Additional details
     * @return bool Success status
     */
    private function logEquipmentAction($equipmentId, $userId, $action, $details = '') {
        $query = "INSERT INTO logs (user_id, action_type, entity_id, entity_type, details) 
                VALUES (:user_id, :action_type, :entity_id, 'equipment', :details)";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':action_type', $action);
        $stmt->bindParam(':entity_id', $equipmentId);
        $stmt->bindParam(':details', $details);
        
        return $stmt->execute();
    }
    
    /**
     * Get equipment verification status
     * @param int $equipmentId Equipment ID
     * @return array Verification information
     */
    public function getVerificationStatus($equipmentId) {
        $query = "SELECT ev.*, 
                    u.first_name, u.last_name, 
                    ur.role_name
                FROM equipment_verification ev
                JOIN employee u ON ev.verified_by = u.uid
                JOIN user_roles ur ON u.login_user_role = ur.id
                WHERE ev.equipment_id = :equipment_id
                ORDER BY ev.verification_date DESC";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':equipment_id', $equipmentId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>