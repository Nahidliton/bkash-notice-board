<?php
require_once 'includes/session_check.php';

// Ensure only admins can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

require_once 'includes/db_connection.php';

$admin_name = $_SESSION['name'];
$admin_id = $_SESSION['user_id'];

// Handle notice deletion
if (isset($_GET['delete_notice'])) {
    $notice_id = intval($_GET['delete_notice']);
    $delete_stmt = $conn->prepare("DELETE FROM notices WHERE id = ?");
    $delete_stmt->bind_param("i", $notice_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    header('Location: admin_dashboard.php?notice_deleted=1');
    exit();
}

// Handle notice editing
if (isset($_POST['edit_notice'])) {
    $notice_id = intval($_POST['notice_id']);
    $title = sanitize($_POST['edit_title']);
    $description = sanitize($_POST['edit_description']);
    $type = sanitize($_POST['edit_type']);
    $employee_id = $type === 'individual' ? intval($_POST['edit_employee_id']) : null;
    
    $update_stmt = $conn->prepare("UPDATE notices SET title = ?, description = ?, type = ?, employee_id = ? WHERE id = ?");
    $update_stmt->bind_param("sssii", $title, $description, $type, $employee_id, $notice_id);
    $update_stmt->execute();
    $update_stmt->close();
    header('Location: admin_dashboard.php?notice_updated=1');
    exit();
}

// Fetch all notices (general + individual for others + admin notices)
$notices_query = "SELECT n.*, e.name as employee_name 
                  FROM notices n 
                  LEFT JOIN employees e ON n.employee_id = e.id 
                  ORDER BY n.created_at DESC";
$notices_result = $conn->query($notices_query);

// Fetch notices specifically for admin (general + individual for admin)
$admin_notices_query = "SELECT * FROM notices WHERE type = 'general' OR employee_id = ? ORDER BY created_at DESC LIMIT 10";
$admin_stmt = $conn->prepare($admin_notices_query);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_notices = $admin_stmt->get_result();

// Search functionality
$search_results = [];
if (isset($_GET['search'])) {
    $search_term = sanitize($_GET['search_term']);
    $search_query = "SELECT * FROM employees WHERE employee_id LIKE ? OR name LIKE ?";
    $search_stmt = $conn->prepare($search_query);
    $search_param = "%$search_term%";
    $search_stmt->bind_param("ss", $search_param, $search_param);
    $search_stmt->execute();
    $search_results = $search_stmt->get_result();
    $search_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - bKash Notice Board</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-three-col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 1200px) {
            .admin-three-col {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .admin-three-col {
                grid-template-columns: 1fr;
            }
        }
        
        .edit-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .edit-modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
        }
        
        .btn-edit {
            background: #FF9800;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-edit:hover {
            background: #F57C00;
        }
        
        .notice-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .success-badge {
            background: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <div style="font-size: 24px; color: #E91E63; font-weight: bold;">bK</div>
            <h2>Admin Dashboard</h2>
        </div>
        <div class="user-info">
            Welcome, <?php echo htmlspecialchars($admin_name); ?> (Admin) | 
            <?php echo $_SESSION['full_id']; ?> |
            <a href="admin_signup.php" class="link" style="margin-left: 10px;">+ New Admin</a> |
            <a href="logout.php" class="link" onclick="return confirmLogout()">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if (isset($_GET['notice_added'])): ?>
            <div class="success">✅ Notice added successfully!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['notice_updated'])): ?>
            <div class="success">✅ Notice updated successfully!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['notice_deleted'])): ?>
            <div class="success">✅ Notice deleted successfully!</div>
        <?php endif; ?>
        
        <div class="admin-three-col">
            <!-- Column 1: Admin Notices -->
            <div class="card">
                <h3>📢 Notices</h3>
                
                <?php if ($admin_notices->num_rows > 0): ?>
                    <?php while ($notice = $admin_notices->fetch_assoc()): ?>
                        <div class="notice-item">
                            <h4><?php echo htmlspecialchars($notice['title']); ?></h4>
                            <p><?php echo htmlspecialchars($notice['description']); ?></p>
                            <span class="notice-type notice-<?php echo $notice['type']; ?>">
                                <?php echo $notice['type'] === 'general' ? 'General' : 'Personal'; ?>
                            </span>
                            <small style="display: block; color: #999; margin-top: 5px;">
                                <?php echo date('d M Y, h:i A', strtotime($notice['created_at'])); ?>
                            </small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #666;">No notices yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- Column 2: Manage Notices -->
            <div class="card">
                <h3>📝 Manage Notices</h3>
                
                <form method="POST" action="add_notice.php" class="form-grid">
                    <div class="full-width">
                        <div class="form-group">
                            <label for="title">Notice Title *</label>
                            <input type="text" id="title" name="title" required placeholder="Enter notice title">
                        </div>
                    </div>
                    
                    <div class="full-width">
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" rows="3" required placeholder="Enter notice description"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Notice Type *</label>
                        <select id="type" name="type" required onchange="toggleEmployeeSelect()">
                            <option value="general">General</option>
                            <option value="individual">Individual</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="employee-select-group" style="display: none;">
                        <label for="employee_id">Select Employee</label>
                        <select id="employee_id" name="employee_id">
                            <option value="">Choose Employee</option>
                            <?php
                            $emp_query = "SELECT id, name, employee_id FROM employees";
                            $emp_result = $conn->query($emp_query);
                            while ($emp = $emp_result->fetch_assoc()):
                            ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['name'] . ' (' . $emp['employee_id'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="full-width">
                        <button type="submit" class="btn">Add Notice</button>
                    </div>
                </form>
                
                <!-- Existing Notices -->
                <div style="margin-top: 20px; max-height: 500px; overflow-y: auto;">
                    <h4 style="color: #E91E63; margin-bottom: 10px;">All Notices</h4>
                    <?php if ($notices_result->num_rows > 0): ?>
                        <?php while ($notice = $notices_result->fetch_assoc()): ?>
                            <div class="notice-item" style="position: relative;">
                                <h4><?php echo htmlspecialchars($notice['title']); ?></h4>
                                <p><?php echo htmlspecialchars(substr($notice['description'], 0, 100)) . '...'; ?></p>
                                <span class="notice-type notice-<?php echo $notice['type']; ?>">
                                    <?php echo $notice['type'] === 'general' ? 'General' : 'For: ' . htmlspecialchars($notice['employee_name']); ?>
                                </span>
                                <div class="notice-actions">
                                    <button class="btn-edit" onclick="openEditModal(<?php echo $notice['id']; ?>, '<?php echo htmlspecialchars(addslashes($notice['title'])); ?>', '<?php echo htmlspecialchars(addslashes($notice['description'])); ?>', '<?php echo $notice['type']; ?>', <?php echo $notice['employee_id'] ?? 0; ?>)">
                                        ✏️ Edit
                                    </button>
                                    <a href="?delete_notice=<?php echo $notice['id']; ?>" 
                                       class="btn-small btn-danger" 
                                       onclick="return confirm('Delete this notice?')">🗑️ Delete</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #666;">No notices created yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Column 3: Employee Search -->
            <div class="card">
                <h3>🔍 Employee Search</h3>
                
                <form method="GET" action="" class="search-box">
                    <input type="text" name="search_term" placeholder="Search by ID or Name" 
                           value="<?php echo isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
                    <button type="submit" name="search">Search</button>
                </form>
                
                <?php if (!empty($search_results) && $search_results->num_rows > 0): ?>
                    <div class="employee-list">
                        <?php while ($employee = $search_results->fetch_assoc()): ?>
                            <div class="employee-card">
                                <div class="employee-info">
                                    <span class="employee-name"><?php echo htmlspecialchars($employee['name']); ?></span>
                                    <span class="employee-id">ID: <?php echo htmlspecialchars($employee['employee_id']); ?></span>
                                    <span style="font-size: 12px; color: #666;">
                                        <?php echo htmlspecialchars($employee['designation']); ?>
                                    </span>
                                </div>
                                <a href="evaluate_employee.php?emp_id=<?php echo $employee['id']; ?>" 
                                   class="btn btn-small">Evaluate</a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php elseif (isset($_GET['search'])): ?>
                    <p style="color: #666; margin-top: 20px;">No employees found.</p>
                <?php else: ?>
                    <div style="margin-top: 20px;">
                        <p style="color: #666;">All Employees:</p>
                        <?php
                        $all_emp = $conn->query("SELECT * FROM employees LIMIT 5");
                        while ($emp = $all_emp->fetch_assoc()):
                        ?>
                            <div class="employee-card">
                                <div class="employee-info">
                                    <span class="employee-name"><?php echo htmlspecialchars($emp['name']); ?></span>
                                    <span class="employee-id">ID: <?php echo htmlspecialchars($emp['employee_id']); ?></span>
                                </div>
                                <a href="evaluate_employee.php?emp_id=<?php echo $emp['id']; ?>" 
                                   class="btn btn-small">Evaluate</a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit Notice Modal -->
    <div id="editModal" class="edit-modal">
        <div class="modal-content">
            <h3 style="color: #E91E63; margin-bottom: 20px;">✏️ Edit Notice</h3>
            <form method="POST" action="">
                <input type="hidden" name="notice_id" id="edit_notice_id">
                
                <div class="form-group">
                    <label for="edit_title">Title</label>
                    <input type="text" id="edit_title" name="edit_title" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="edit_description" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_type">Type</label>
                    <select id="edit_type" name="edit_type" onchange="toggleEditEmployee()">
                        <option value="general">General</option>
                        <option value="individual">Individual</option>
                    </select>
                </div>
                
                <div class="form-group" id="edit_employee_group" style="display: none;">
                    <label for="edit_employee_id">Employee</label>
                    <select id="edit_employee_id" name="edit_employee_id">
                        <option value="">Select Employee</option>
                        <?php
                        $emp_result2 = $conn->query("SELECT id, name, employee_id FROM employees");
                        while ($emp2 = $emp_result2->fetch_assoc()):
                        ?>
                            <option value="<?php echo $emp2['id']; ?>">
                                <?php echo htmlspecialchars($emp2['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_notice" class="btn">Update Notice</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleEmployeeSelect() {
            const type = document.getElementById('type').value;
            const empGroup = document.getElementById('employee-select-group');
            if (type === 'individual') {
                empGroup.style.display = 'block';
            } else {
                empGroup.style.display = 'none';
            }
        }
        
        function openEditModal(id, title, description, type, employeeId) {
            document.getElementById('edit_notice_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_type').value = type;
            
            if (type === 'individual') {
                document.getElementById('edit_employee_group').style.display = 'block';
                document.getElementById('edit_employee_id').value = employeeId;
            } else {
                document.getElementById('edit_employee_group').style.display = 'none';
            }
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function toggleEditEmployee() {
            const type = document.getElementById('edit_type').value;
            const empGroup = document.getElementById('edit_employee_group');
            empGroup.style.display = type === 'individual' ? 'block' : 'none';
        }
        
        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>