<?php
// Check if user is logged in with admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Administrator') {
    // Redirect to login or show access denied message
    echo "<div class='alert alert-danger'>You don't have permission to access this page.</div>";
    exit;
}

// Include database connection
include_once('../config/database.php');
?>

<div class="container mt-4">
    <h2>User Management</h2>
    
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" id="userManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" 
                data-bs-target="#pending-users" type="button" role="tab" 
                aria-controls="pending-users" aria-selected="true">Pending Users</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="verified-tab" data-bs-toggle="tab" 
                data-bs-target="#verified-users" type="button" role="tab" 
                aria-controls="verified-users" aria-selected="false">Verified Users</button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="userManagementContent">
        <!-- Pending Users Tab -->
        <div class="tab-pane fade show active" id="pending-users" role="tabpanel" 
            aria-labelledby="pending-tab">
            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title">Users Awaiting Verification</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Registration Date</th>
                                    <th>Assign Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pendingUsersTable">
                                <!-- Pending users will be loaded here via AJAX -->
                                <tr>
                                    <td colspan="6" class="text-center">Loading users...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Verified Users Tab -->
        <div class="tab-pane fade" id="verified-users" role="tabpanel" 
            aria-labelledby="verified-tab">
            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title">Verified Users</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Current Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="verifiedUsersTable">
                                <!-- Verified users will be loaded here via AJAX -->
                                <tr>
                                    <td colspan="5" class="text-center">Loading users...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom JavaScript for User Management -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load both tables when the page loads
        loadPendingUsers();
        loadVerifiedUsers();
        
        // Add event listeners for tab switching to refresh data
        document.getElementById('pending-tab').addEventListener('click', loadPendingUsers);
        document.getElementById('verified-tab').addEventListener('click', loadVerifiedUsers);
    });
    
    // Function to load pending users
    function loadPendingUsers() {
        fetch('ajax/user_management_ajax.php?action=get_pending_users')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                const tableBody = document.getElementById('pendingUsersTable');
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No pending users found</td></tr>';
                    return;
                }
                
                let html = '';
                data.forEach(user => {
                    html += `
                    <tr id="pending-user-${user.user_id}">
                        <td>${user.user_id}</td>
                        <td>${user.username}</td>
                        <td>${user.email}</td>
                        <td>${user.created_at}</td>
                        <td>
                            <select class="form-select role-select" id="role-${user.user_id}">
                                <option value="">Select Role</option>
                                ${user.available_roles.map(role => 
                                    `<option value="${role.role_id}">${role.role_name}</option>`
                                ).join('')}
                            </select>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="verifyUser(${user.user_id})">
                                Verify User
                            </button>
                        </td>
                    </tr>`;
                });
                tableBody.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading pending users:', error);
                document.getElementById('pendingUsersTable').innerHTML = 
                    '<tr><td colspan="6" class="text-center text-danger">Error loading users: ' + error.message + '</td></tr>';
            });
    }
    
    // Function to load verified users
    function loadVerifiedUsers() {
        fetch('ajax/user_management_ajax.php?action=get_verified_users')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                const tableBody = document.getElementById('verifiedUsersTable');
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No verified users found</td></tr>';
                    return;
                }
                
                let html = '';
                data.forEach(user => {
                    html += `
                    <tr id="verified-user-${user.user_id}">
                        <td>${user.user_id}</td>
                        <td>${user.username}</td>
                        <td>${user.email}</td>
                        <td id="role-display-${user.user_id}" data-role-id="${user.role_id}">
                            ${user.role_name}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editUserRole(${user.user_id})">
                                Edit Role
                            </button>
                        </td>
                    </tr>`;
                });
                tableBody.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading verified users:', error);
                document.getElementById('verifiedUsersTable').innerHTML = 
                    '<tr><td colspan="5" class="text-center text-danger">Error loading users</td></tr>';
            });
    }
    
    // Function to verify user and assign role
    function verifyUser(userId) {
        const roleSelect = document.getElementById(`role-${userId}`);
        const roleId = roleSelect.value;
        
        if (!roleId) {
            alert('Please select a role for this user');
            return;
        }
        
        // Send verification request
        fetch('ajax/user_management_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=verify_user&user_id=${userId}&role_id=${roleId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove from pending list and refresh verified list
                const row = document.getElementById(`pending-user-${userId}`);
                row.classList.add('table-success');
                setTimeout(() => {
                    row.remove();
                    loadPendingUsers();
                    loadVerifiedUsers();
                }, 500);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error verifying user:', error);
            alert('An error occurred while verifying the user');
        });
    }
    
    // Function to edit user role
    function editUserRole(userId) {
        const roleCell = document.getElementById(`role-display-${userId}`);
        const currentRoleId = roleCell.dataset.roleId;
        const currentRoleName = roleCell.innerText;
        
        // Get all available roles
        fetch('ajax/user_management_ajax.php?action=get_roles')
            .then(response => response.json())
            .then(roles => {
                // Create role dropdown
                let selectHtml = `
                    <select class="form-select" id="edit-role-${userId}">
                        ${roles.map(role => 
                            `<option value="${role.role_id}" ${role.role_id == currentRoleId ? 'selected' : ''}>
                                ${role.role_name}
                            </option>`
                        ).join('')}
                    </select>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-success" onclick="saveUserRole(${userId})">✅</button>
                        <button class="btn btn-sm btn-danger" onclick="cancelEditRole(${userId}, '${currentRoleName}', ${currentRoleId})">❌</button>
                    </div>
                `;
                roleCell.innerHTML = selectHtml;
                
                // Change the edit button to save button
                const actionsCell = roleCell.nextElementSibling;
                actionsCell.innerHTML = '';
            })
            .catch(error => {
                console.error('Error fetching roles:', error);
                alert('An error occurred while fetching roles');
            });
    }
    
    // Function to save user role
    function saveUserRole(userId) {
        const roleSelect = document.getElementById(`edit-role-${userId}`);
        const roleId = roleSelect.value;
        
        // Send update request
        fetch('ajax/user_management_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_role&user_id=${userId}&role_id=${roleId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the cell with new role name
                const roleCell = document.getElementById(`role-display-${userId}`);
                roleCell.dataset.roleId = roleId;
                roleCell.innerHTML = data.role_name;
                
                // Restore edit button
                const actionsCell = roleCell.nextElementSibling;
                actionsCell.innerHTML = `
                    <button class="btn btn-sm btn-primary" onclick="editUserRole(${userId})">
                        Edit Role
                    </button>
                `;
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating role:', error);
            alert('An error occurred while updating the role');
        });
    }
    
    // Function to cancel role editing
    function cancelEditRole(userId, roleName, roleId) {
        const roleCell = document.getElementById(`role-display-${userId}`);
        roleCell.innerHTML = roleName;
        roleCell.dataset.roleId = roleId;
        
        // Restore edit button
        const actionsCell = roleCell.nextElementSibling;
        actionsCell.innerHTML = `
            <button class="btn btn-sm btn-primary" onclick="editUserRole(${userId})">
                Edit Role
            </button>
        `;
    }
</script>
