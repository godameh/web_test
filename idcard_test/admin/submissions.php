<?php
/**
 * UBIDS Student ID Card Photo Portal - Admin Submissions Review
 */

// Define application constant
define('UBIDS_PORTAL', true);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin login
require_admin_login();

// Get current admin
$admin = get_current_admin();

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $bulk_action = $_POST['bulk_action'] ?? '';
    $selected_submissions = $_POST['selected_submissions'] ?? [];
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf_token)) {
        redirect_with_message('submissions.php', 'Invalid request. Please try again.', 'error');
    }
    
    if (!empty($selected_submissions) && is_array($selected_submissions)) {
        $count = 0;
        
        foreach ($selected_submissions as $submission_id) {
            $submission_id = (int) $submission_id;
            
            if ($bulk_action === 'approve') {
                $sql = "UPDATE submissions SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id";
                $params = [':admin_id' => $admin['id'], ':id' => $submission_id];
                
                if (db_execute($sql, $params)) {
                    log_audit('admin', $admin['id'], 'submission_approved', "Bulk approved submission ID: $submission_id");
                    $count++;
                }
            } elseif ($bulk_action === 'reject' && !empty($rejection_reason)) {
                $sql = "UPDATE submissions SET status = 'rejected', rejection_reason = :reason, reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id";
                $params = [':reason' => $rejection_reason, ':admin_id' => $admin['id'], ':id' => $submission_id];
                
                if (db_execute($sql, $params)) {
                    log_audit('admin', $admin['id'], 'submission_rejected', "Bulk rejected submission ID: $submission_id - Reason: $rejection_reason");
                    $count++;
                }
            }
        }
        
        if ($count > 0) {
            redirect_with_message('submissions.php', "$count submissions processed successfully.", 'success');
        } else {
            redirect_with_message('submissions.php', 'No submissions were processed.', 'warning');
        }
    } else {
        redirect_with_message('submissions.php', 'Please select submissions to process.', 'warning');
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$department_filter = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "s.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "s.student_type = :student_type";
    $params[':student_type'] = $type_filter;
}

if (!empty($department_filter)) {
    $where_conditions[] = "(
        (s.student_type = 'new' AND sn.department = :department) OR 
        (s.student_type = 'continuing' AND sc.department = :department)
    )";
    $params[':department'] = $department_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(
        sn.admission_number LIKE :search OR 
        sc.admission_number LIKE :search OR 
        sn.first_name LIKE :search OR 
        sc.first_name LIKE :search OR 
        sn.last_name LIKE :search OR 
        sc.last_name LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM submissions s
    LEFT JOIN students_new sn ON s.student_type = 'new' AND s.student_id = sn.id
    LEFT JOIN students_continuing sc ON s.student_type = 'continuing' AND s.student_id = sc.id
    $where_clause
";
$total_submissions = db_query_column($count_sql, $params);
$total_pages = ceil($total_submissions / $per_page);

// Get submissions
$sql = "
    SELECT s.*, 
           sn.first_name as new_first_name, sn.last_name as new_last_name, 
           sn.admission_number as new_admission_number, sn.department as new_department,
           sc.first_name as cont_first_name, sc.last_name as cont_last_name,
           sc.admission_number as cont_admission_number, sc.department as cont_department,
           a.full_name as reviewer_name
    FROM submissions s
    LEFT JOIN students_new sn ON s.student_type = 'new' AND s.student_id = sn.id
    LEFT JOIN students_continuing sc ON s.student_type = 'continuing' AND s.student_id = sc.id
    LEFT JOIN admins a ON s.reviewed_by = a.id
    $where_clause
    ORDER BY s.submitted_at DESC
    LIMIT :limit OFFSET :offset
";

$params[':limit'] = $per_page;
$params[':offset'] = $offset;

$submissions = db_query($sql, $params);

// Get departments for filter
$departments = db_query("
    SELECT department, COUNT(*) as count
    FROM (
        SELECT department FROM students_new WHERE is_active = 1
        UNION ALL
        SELECT department FROM students_continuing WHERE is_active = 1
    ) as all_students
    GROUP BY department
    ORDER BY department
");

// Get flash message
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions - <?php echo h(APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Lora', serif; }
        .display-font { font-family: 'Syne', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-900 text-white">
            <div class="p-6">
                <div class="flex items-center mb-8">
                    <div class="h-10 w-10 bg-yellow-500 rounded-full flex items-center justify-center">
                        <svg class="h-6 w-6 text-gray-900" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h1 class="display-font text-lg font-bold">Admin Portal</h1>
                        <p class="text-xs text-gray-400">UBIDS ID Card System</p>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="space-y-2">
                    <a href="index.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="submissions.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg bg-gray-800 text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9h-4v4h-2v-4H9V9h4V5h2v4h4v2z"/>
                        </svg>
                        Submissions
                    </a>
                    <a href="students.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                        </svg>
                        Students
                    </a>
                    <a href="export.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                        Export
                    </a>
                    <a href="audit.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        Audit Log
                    </a>
                </nav>

                <!-- Admin Profile -->
                <div class="absolute bottom-0 left-0 right-0 p-6 border-t border-gray-800">
                    <div class="flex items-center">
                        <div class="h-8 w-8 bg-gray-700 rounded-full flex items-center justify-center">
                            <svg class="h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white"><?php echo h($admin['full_name']); ?></p>
                            <p class="text-xs text-gray-400"><?php echo h($admin['role']); ?></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="../logout.php" class="text-xs text-gray-400 hover:text-white">Sign out</a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="display-font text-2xl font-bold text-gray-900">Submissions</h1>
                            <p class="text-sm text-gray-500">Review and manage student photo submissions</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500">Total: <?php echo number_format($total_submissions); ?> submissions</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="mx-6 mt-6 rounded-md p-4 <?php echo $flash['type'] === 'error' ? 'bg-red-50 text-red-800' : ($flash['type'] === 'warning' ? 'bg-yellow-50 text-yellow-800' : ($flash['type'] === 'success' ? 'bg-green-50 text-green-800' : 'bg-blue-50 text-blue-800')); ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <?php if ($flash['type'] === 'error'): ?>
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            <?php elseif ($flash['type'] === 'warning'): ?>
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            <?php elseif ($flash['type'] === 'success'): ?>
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            <?php else: ?>
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo h($flash['message']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="p-6">
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400">
                                <option value="">All Status</option>
                                <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="generated" <?php echo $status_filter === 'generated' ? 'selected' : ''; ?>>Generated</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Student Type</label>
                            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400">
                                <option value="">All Types</option>
                                <option value="new" <?php echo $type_filter === 'new' ? 'selected' : ''; ?>>New Student</option>
                                <option value="continuing" <?php echo $type_filter === 'continuing' ? 'selected' : ''; ?>>Continuing</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo h($dept['department']); ?>" <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo h($dept['department']); ?> (<?php echo $dept['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" name="search" value="<?php echo h($search); ?>" 
                                   placeholder="Name or admission number"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        </div>
                        
                        <div class="md:col-span-4 flex justify-end space-x-3">
                            <button type="submit" class="px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-yellow-500 hover:bg-yellow-600">
                                Apply Filters
                            </button>
                            <a href="submissions.php" class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                                Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Bulk Actions -->
                <?php if (!empty($submissions)): ?>
                    <form method="POST" id="bulkForm" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="flex items-center justify-between bg-white rounded-xl shadow-sm p-4">
                            <div class="flex items-center space-x-4">
                                <input type="checkbox" id="selectAll" class="h-4 w-4 text-yellow-400 focus:ring-yellow-400 border-gray-300 rounded">
                                <label for="selectAll" class="text-sm text-gray-700">Select All</label>
                                
                                <select name="bulk_action" class="px-3 py-1 border border-gray-300 rounded text-sm">
                                    <option value="">Bulk Actions</option>
                                    <option value="approve">Approve Selected</option>
                                    <option value="reject">Reject Selected</option>
                                </select>
                                
                                <input type="text" name="rejection_reason" placeholder="Rejection reason (if rejecting)" 
                                       class="px-3 py-1 border border-gray-300 rounded text-sm" style="display: none;" id="bulkRejectionReason">
                                
                                <button type="submit" class="px-3 py-1 bg-yellow-500 text-white rounded text-sm hover:bg-yellow-600">
                                    Apply
                                </button>
                            </div>
                            
                            <div class="text-sm text-gray-500">
                                Showing <?php echo ($offset + 1) . ' - ' . min($offset + $per_page, $total_submissions); ?> of <?php echo $total_submissions; ?>
                            </div>
                        </div>
                <?php endif; ?>

                <!-- Submissions Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" class="h-4 w-4 text-yellow-400 focus:ring-yellow-400 border-gray-300 rounded submission-checkbox">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($submissions): ?>
                                    <?php foreach ($submissions as $submission): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox" name="selected_submissions[]" value="<?php echo $submission['id']; ?>" 
                                                       class="h-4 w-4 text-yellow-400 focus:ring-yellow-400 border-gray-300 rounded submission-checkbox">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php 
                                                        $first_name = $submission['student_type'] === 'new' ? $submission['new_first_name'] : $submission['cont_first_name'];
                                                        $last_name = $submission['student_type'] === 'new' ? $submission['new_last_name'] : $submission['cont_last_name'];
                                                        echo h($first_name . ' ' . $last_name);
                                                        ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php 
                                                        $admission_number = $submission['student_type'] === 'new' ? $submission['new_admission_number'] : $submission['cont_admission_number'];
                                                        echo h($admission_number);
                                                        ?>
                                                    </div>
                                                    <div class="text-xs text-gray-400">
                                                        <?php 
                                                        $department = $submission['student_type'] === 'new' ? $submission['new_department'] : $submission['cont_department'];
                                                        echo h($department);
                                                        ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $submission['student_type'] === 'new' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                                    <?php echo ucfirst($submission['student_type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($submission['photo_filename']): ?>
                                                    <img src="<?php echo APP_URL . '/uploads/photos/' . $submission['photo_filename']; ?>" 
                                                         alt="Photo" class="h-12 w-12 rounded object-cover cursor-pointer hover:opacity-80"
                                                         onclick="showPhotoModal('<?php echo $submission['photo_filename']; ?>')">
                                                <?php else: ?>
                                                    <div class="h-12 w-12 bg-gray-200 rounded flex items-center justify-center">
                                                        <svg class="h-6 w-6 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php echo get_status_badge($submission['status']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo format_date($submission['submitted_at'], 'M j, Y g:i A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="review.php?id=<?php echo $submission['id']; ?>" class="text-blue-600 hover:text-blue-900">Review</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                            No submissions found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($submissions)): ?>
                    </form>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="px-3 py-1 border rounded text-sm <?php echo $i === $page ? 'bg-yellow-500 text-white border-yellow-500' : 'border-gray-300 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Photo Modal -->
    <div id="photoModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Student Photo</h3>
                <button onclick="hidePhotoModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M6.293 6.293a1 1 0 011.414 0L12 10.586l4.293-4.293a1 1 0 111.414 1.414L13.414 12l4.293 4.293a1 1 0 01-1.414 1.414L12 13.414l-4.293 4.293a1 1 0 01-1.414-1.414L10.586 12 6.293 7.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <div class="text-center">
                <img id="modalPhoto" src="" alt="Student Photo" class="max-w-full max-h-96 rounded-lg">
            </div>
        </div>
    </div>

    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.submission-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.submission-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.submission-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.submission-checkbox:checked');
                document.getElementById('selectAll').checked = allCheckboxes.length === checkedCheckboxes.length;
            });
        });

        // Show rejection reason when reject is selected
        document.querySelector('select[name="bulk_action"]').addEventListener('change', function() {
            const rejectionReason = document.getElementById('bulkRejectionReason');
            if (this.value === 'reject') {
                rejectionReason.style.display = 'inline-block';
                rejectionReason.required = true;
            } else {
                rejectionReason.style.display = 'none';
                rejectionReason.required = false;
            }
        });

        // Photo modal
        function showPhotoModal(filename) {
            document.getElementById('modalPhoto').src = '<?php echo APP_URL; ?>/uploads/photos/' + filename;
            document.getElementById('photoModal').classList.remove('hidden');
        }

        function hidePhotoModal() {
            document.getElementById('photoModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('photoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hidePhotoModal();
            }
        });
    </script>
</body>
</html>
