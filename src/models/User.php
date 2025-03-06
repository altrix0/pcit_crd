<?php
/**
 * User Model
 * Handles user authentication, role management, and persistence
 */
class User {
    // Database connection
    private $conn;
    
    // User properties
    private $uid;
    private $sevarthId;
    private $password;
    private $firstName;
    private $lastName;
    private $roleId;
    private $accessLevel;
    private $email;
    private $mobileNumber;
    private $loginToken;
    private $tokenExpiry;
    
    /**
     * Constructor - initialize with database connection
     * @param Database $db Database connection object
     */
    public function __construct($db) {
        $this->conn = $db->getConnection();
    }
    
    /**
     * Authenticate user with Sevarth ID and password
     * @param string $sevarthId User's Sevarth ID
     * @param string $password User's password
     * @return array|bool User data array if authenticated, false otherwise
     */
    public function authenticate($sevarthId, $password) {
        // Query to get user with the provided Sevarth ID
        $query = "SELECT e.uid, e.sevarth_id, e.first_name, e.last_name, e.email_id, 
                        e.mobile_number, e.password, r.id as role_id, r.role_name, r.access_level
                 FROM employee e
                 INNER JOIN user_roles r ON e.login_user_role = r.id
                 WHERE e.sevarth_id = :sevarth_id";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sevarth_id', $sevarthId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Don't return password in the user data
                unset($user['password']);
                
                // Check if user requires 2FA (based on role level)
                $user['requires_2fa'] = ($user['access_level'] >= 3); // Higher access levels require 2FA
                
                return $user;
            }
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     * @param int $userId User ID
     * @return array|bool User data array if found, false otherwise
     */
    public function getUserById($userId) {
        $query = "SELECT e.uid, e.sevarth_id, e.first_name, e.last_name, e.email_id, 
                        e.mobile_number, r.id as role_id, r.role_name, r.access_level
                 FROM employee e
                 INNER JOIN user_roles r ON e.login_user_role = r.id
                 WHERE e.uid = :uid";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }
    
    /**
     * Get user by Sevarth ID
     * @param string $sevarthId User's Sevarth ID
     * @return array|bool User data array if found, false otherwise
     */
    public function getUserBySevarthId($sevarthId) {
        $query = "SELECT e.uid, e.sevarth_id, e.first_name, e.last_name, e.email_id, 
                        e.mobile_number, r.id as role_id, r.role_name, r.access_level
                 FROM employee e
                 INNER JOIN user_roles r ON e.login_user_role = r.id
                 WHERE e.sevarth_id = :sevarth_id";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sevarth_id', $sevarthId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }
    
    /**
     * Check if user has specific role
     * @param int $userId User ID
     * @param int|array $roleIds Role ID or array of Role IDs to check
     * @return bool True if user has role, false otherwise
     */
    public function checkRole($userId, $roleIds) {
        $query = "SELECT r.id as role_id, r.access_level
                 FROM employee e
                 INNER JOIN user_roles r ON e.login_user_role = r.id
                 WHERE e.uid = :uid";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If checking for a single role
            if (!is_array($roleIds)) {
                return ($user['role_id'] == $roleIds);
            }
            
            // If checking for multiple roles
            return in_array($user['role_id'], $roleIds);
        }
        
        return false;
    }
    
    /**
     * Check if user has minimum access level
     * @param int $userId User ID
     * @param int $minAccessLevel Minimum required access level
     * @return bool True if user has sufficient access, false otherwise
     */
    public function hasAccessLevel($userId, $minAccessLevel) {
        $query = "SELECT r.access_level
                 FROM employee e
                 INNER JOIN user_roles r ON e.login_user_role = r.id
                 WHERE e.uid = :uid";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($user['access_level'] >= $minAccessLevel);
        }
        
        return false;
    }
    
    /**
     * Store login token for persistent sessions
     * @param int $userId User ID
     * @param string $token Secure token
     * @param int $expiry Token expiry timestamp
     * @return bool Success status
     */
    public function storeLoginToken($userId, $token, $expiry) {
        try {
            // First, clear any existing tokens for this user
            $query = "DELETE FROM persistent_logins WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            // Store new token
            $query = "INSERT INTO persistent_logins (user_id, token, expiry)
                      VALUES (:user_id, :token, FROM_UNIXTIME(:expiry))";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expiry', $expiry);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error storing login token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove login token
     * @param int $userId User ID
     * @param string $token Token to remove (optional)
     * @return bool Success status
     */
    public function removeLoginToken($userId, $token = null) {
        try {
            $query = "DELETE FROM persistent_logins WHERE user_id = :user_id";
            
            if ($token) {
                $query .= " AND token = :token";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            
            if ($token) {
                $stmt->bindParam(':token', $token);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error removing login token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by persistent login token
     * @param string $token Login token
     * @return array|bool User data if token is valid, false otherwise
     */
    public function getUserByToken($token) {
        try {
            $query = "SELECT e.uid, e.sevarth_id, e.first_name, e.last_name, e.email_id, 
                            e.mobile_number, r.id as role_id, r.role_name, r.access_level, 
                            UNIX_TIMESTAMP(p.expiry) as expiry
                     FROM persistent_logins p
                     INNER JOIN employee e ON p.user_id = e.uid
                     INNER JOIN user_roles r ON e.login_user_role = r.id
                     WHERE p.token = :token AND p.expiry > NOW()";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Refresh token if it's close to expiring (within 7 days)
                if (($userData['expiry'] - time()) < (7 * 24 * 60 * 60)) {
                    $newExpiry = time() + (90 * 24 * 60 * 60); // 90 days
                    $this->refreshToken($token, $newExpiry);
                }
                
                return $userData;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error retrieving user by token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Refresh token expiry
     * @param string $token Token to refresh
     * @param int $newExpiry New expiry timestamp
     * @return bool Success status
     */
    private function refreshToken($token, $newExpiry) {
        try {
            $query = "UPDATE persistent_logins 
                      SET expiry = FROM_UNIXTIME(:expiry) 
                      WHERE token = :token";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':expiry', $newExpiry);
            $stmt->bindParam(':token', $token);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error refreshing token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify user's password
     * @param int $userId User ID
     * @param string $password Password to verify
     * @return bool True if password is correct, false otherwise
     */
    public function verifyPassword($userId, $password) {
        try {
            $query = "SELECT password FROM employee WHERE uid = :uid";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':uid', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                return password_verify($password, $user['password']);
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error verifying password: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user password
     * @param int $userId User ID
     * @param string $newPassword New password (will be hashed)
     * @return bool Success status
     */
    public function updatePassword($userId, $newPassword) {
        try {
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $query = "UPDATE employee 
                      SET password = :password, 
                          password_changed_date = NOW()
                      WHERE uid = :uid";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':uid', $userId);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating password: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get reporting structure for a user
     * @param int $userId User ID
     * @return array Hierarchical reporting structure
     */
    public function getReportingStructure($userId) {
        // Get the user's reporting chain (upward)
        $reportingChain = $this->getReportingChain($userId);
        
        // Get users reporting to this user (downward)
        $subordinates = $this->getSubordinates($userId);
        
        return [
            'reporting_to' => $reportingChain,
            'subordinates' => $subordinates
        ];
    }
    
    /**
     * Get upward reporting chain
     * @param int $userId User ID
     * @return array List of users in reporting chain
     */
    private function getReportingChain($userId) {
        try {
            $chain = [];
            $currentUserId = $userId;
            $maxDepth = 10; // Prevent infinite loops
            $depth = 0;
            
            while ($depth < $maxDepth) {
                $query = "SELECT e.reporting_person, 
                                r.uid, r.sevarth_id, r.first_name, r.last_name,
                                p.id as post_id, p.name as post_name,
                                u.unit_id, u.unit_name
                         FROM employee e
                         LEFT JOIN employee r ON e.reporting_person = r.uid
                         LEFT JOIN posting po ON r.current_posting = po.uid
                         LEFT JOIN post_types p ON po.post = p.id
                         LEFT JOIN unit u ON po.posting_unit = u.unit_id
                         WHERE e.uid = :user_id";
                         
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $currentUserId);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // If no reporting person, we've reached the top
                    if (empty($supervisor['reporting_person'])) {
                        break;
                    }
                    
                    // Add to chain and continue up
                    $chain[] = [
                        'uid' => $supervisor['uid'],
                        'sevarth_id' => $supervisor['sevarth_id'],
                        'name' => $supervisor['first_name'] . ' ' . $supervisor['last_name'],
                        'post' => $supervisor['post_name'],
                        'unit' => $supervisor['unit_name']
                    ];
                    
                    $currentUserId = $supervisor['reporting_person'];
                    $depth++;
                } else {
                    break;
                }
            }
            
            return $chain;
        } catch (PDOException $e) {
            error_log("Error getting reporting chain: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get subordinates reporting to user
     * @param int $userId User ID
     * @return array List of direct subordinates
     */
    private function getSubordinates($userId) {
        try {
            $query = "SELECT e.uid, e.sevarth_id, e.first_name, e.last_name,
                            p.id as post_id, p.name as post_name,
                            u.unit_id, u.unit_name
                     FROM employee e
                     LEFT JOIN posting po ON e.current_posting = po.uid
                     LEFT JOIN post_types p ON po.post = p.id
                     LEFT JOIN unit u ON po.posting_unit = u.unit_id
                     WHERE e.reporting_person = :user_id
                     ORDER BY p.priority ASC, e.first_name ASC";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $subordinates = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $subordinates[] = [
                    'uid' => $row['uid'],
                    'sevarth_id' => $row['sevarth_id'],
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'post' => $row['post_name'],
                    'unit' => $row['unit_name']
                ];
            }
            
            return $subordinates;
        } catch (PDOException $e) {
            error_log("Error getting subordinates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new user account
     * @param array $userData User data
     * @return bool|int User ID on success, false on failure
     */
    public function createUser($userData) {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Hash the password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert into employee table
            $query = "INSERT INTO employee (
                        sevarth_id, first_name, last_name, 
                        father_name, mother_name, spouse_name,
                        dob, mobile_number, email_id, 
                        aadhar_number, retirement_date, current_posting,
                        login_user_role, reporting_person, password
                      ) VALUES (
                        :sevarth_id, :first_name, :last_name,
                        :father_name, :mother_name, :spouse_name,
                        :dob, :mobile_number, :email_id,
                        :aadhar_number, :retirement_date, :current_posting,
                        :login_user_role, :reporting_person, :password
                      )";
                      
            $stmt = $this->conn->prepare($query);
            
            // Bind parameters
            $stmt->bindParam(':sevarth_id', $userData['sevarth_id']);
            $stmt->bindParam(':first_name', $userData['first_name']);
            $stmt->bindParam(':last_name', $userData['last_name']);
            
            // Create variables for nullable fields before binding
            $fatherName = $userData['father_name'] ?? null;
            $motherName = $userData['mother_name'] ?? null;
            $spouseName = $userData['spouse_name'] ?? null;
            $aadharNumber = $userData['aadhar_number'] ?? null;
            
            $stmt->bindParam(':father_name', $fatherName);
            $stmt->bindParam(':mother_name', $motherName);
            $stmt->bindParam(':spouse_name', $spouseName);
            $stmt->bindParam(':dob', $userData['dob']);
            $stmt->bindParam(':mobile_number', $userData['mobile_number']);
            $stmt->bindParam(':email_id', $userData['email_id']);
            $stmt->bindParam(':aadhar_number', $aadharNumber);
            $stmt->bindParam(':retirement_date', $userData['retirement_date']);
            $stmt->bindParam(':current_posting', $userData['current_posting']);
            $stmt->bindParam(':login_user_role', $userData['login_user_role']);
            
            // Create variable for reporting person before binding
            $reportingPerson = $userData['reporting_person'] ?? null;
            $stmt->bindParam(':reporting_person', $reportingPerson);
            $stmt->bindParam(':password', $hashedPassword);
            
            $stmt->execute();
            $userId = $this->conn->lastInsertId();
            
            // Commit the transaction
            $this->conn->commit();
            
            return $userId;
            
        } catch (PDOException $e) {
            // Roll back the transaction on error
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user information
     * @param int $userId User ID
     * @param array $userData User data to update
     * @return bool Success status
     */
    public function updateUser($userId, $userData) {
        try {
            // Generate SQL based on provided data
            $fields = [];
            $params = [':uid' => $userId];
            
            foreach ($userData as $key => $value) {
                // Skip uid as it's not updatable
                if ($key !== 'uid' && $key !== 'password') {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }
            
            // If no fields to update, return true
            if (empty($fields)) {
                return true;
            }
            
            $query = "UPDATE employee SET " . implode(', ', $fields) . " WHERE uid = :uid";
            $stmt = $this->conn->prepare($query);
            
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }
}
?>