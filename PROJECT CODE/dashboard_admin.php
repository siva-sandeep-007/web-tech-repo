<?php
require_once 'config.php';

if (!isLoggedIn() || !checkRole('admin')) {
    redirect('index.php');
}

$success = '';
$error = '';

// Handle status update and officer assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crime_id = $_POST['crime_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $assigned_officer = $_POST['assigned_officer'] ?? '';
    
    if ($crime_id && $status) {
        $crimes = readJsonFile(CRIMES_FILE);
        
        foreach ($crimes['crimes'] as &$crime) {
            if ($crime['id'] == $crime_id) {
                $crime['status'] = $status;
                if ($assigned_officer) {
                    $crime['assigned_officer'] = $assigned_officer;
                }
                break;
            }
        }
        
        writeJsonFile(CRIMES_FILE, $crimes);
        $success = 'Crime report updated successfully!';
    } else {
        $error = 'Invalid update request';
    }
}

// Get all crime reports
$crimes = readJsonFile(CRIMES_FILE);

// Get all police officers
$users = readJsonFile(USERS_FILE);
$police_officers = array_filter($users['users'], function($user) {
    return $user['role'] === 'police';
});

// Read requests
$requests = file_exists('data/requests.json') ? readJsonFile('data/requests.json') : ['requests' => []];
// Handle assign from request
if (isset($_POST['assign_request'])) {
    $crime_id = $_POST['crime_id'];
    $officer = $_POST['officer'];
    $crimes = readJsonFile(CRIMES_FILE);
    foreach ($crimes['crimes'] as &$crime) {
        if ($crime['id'] == $crime_id) {
            $crime['assigned_officer'] = $officer;
            $crime['status'] = $crime['status'] === 'Pending' ? 'In Progress' : $crime['status'];
        }
    }
    writeJsonFile(CRIMES_FILE, $crimes);
    // Remove request
    $requests['requests'] = array_filter($requests['requests'], function($req) use ($crime_id, $officer) {
        return !($req['crime_id'] == $crime_id && $req['username'] == $officer);
    });
    writeJsonFile('data/requests.json', $requests);
    $success = 'Case assigned to officer!';
}

// Handle add new police
if (isset($_POST['add_police'])) {
    $new_username = trim($_POST['new_police_username']);
    $new_password = $_POST['new_police_password'];
    if ($new_username && $new_password) {
        $users = readJsonFile(USERS_FILE);
        // Check if username exists
        $exists = false;
        foreach ($users['users'] as $u) {
            if ($u['username'] === $new_username) $exists = true;
        }
        if ($exists) {
            $error = 'Username already exists!';
        } else {
            $users['users'][] = [
                'id' => count($users['users']) + 1,
                'username' => $new_username,
                'password' => password_hash($new_password, PASSWORD_DEFAULT),
                'role' => 'police'
            ];
            writeJsonFile(USERS_FILE, $users);
            $success = 'New police officer added!';
        }
    } else {
        $error = 'Please provide both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Crime Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .navbar {
            background: linear-gradient(90deg, #4b6cb7 0%, #182848 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 600;
            font-size: 1.5rem;
        }
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }
        .welcome-text {
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            transform: translateY(0);
            will-change: transform;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(90deg, #4b6cb7 0%, #182848 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }
        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }
        .table {
            margin: 0;
        }
        .table th {
            font-weight: 600;
            color: #2c3e50;
            border-top: none;
        }
        .table td {
            vertical-align: middle;
        }
        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
            border-radius: 8px;
        }
        .btn-sm {
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            transform: translateY(0);
        }
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(75, 108, 183, 0.2);
        }
        .modal-content {
            border: none;
            border-radius: 15px;
        }
        .modal-header {
            background: linear-gradient(90deg, #4b6cb7 0%, #182848 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .modal-title {
            font-weight: 600;
        }
        .form-select, .form-control {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            border: 2px solid #e9ecef;
        }
        .form-select:focus, .form-control:focus {
            border-color: #4b6cb7;
            box-shadow: 0 0 0 0.2rem rgba(75, 108, 183, 0.25);
        }
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .stats-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #4b6cb7;
        }
        .stats-number {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .stats-label {
            color: #6c757d;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt me-2"></i>
                Crime Management System
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link welcome-text">
                    <i class="fas fa-user-shield me-2"></i>
                    Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <i class="fas fa-file-alt stats-icon"></i>
                    <div class="stats-number"><?= count($crimes['crimes']) ?></div>
                    <div class="stats-label">Total Reports</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <i class="fas fa-user-tie stats-icon"></i>
                    <div class="stats-number"><?= count($police_officers) ?></div>
                    <div class="stats-label">Police Officers</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <i class="fas fa-check-circle stats-icon"></i>
                    <div class="stats-number">
                        <?= count(array_filter($crimes['crimes'], function($crime) { return $crime['status'] === 'Solved'; })) ?>
                    </div>
                    <div class="stats-label">Solved Cases</div>
                </div>
            </div>
        </div>

        <!-- Add after stats cards, before police access requests -->
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-user-plus me-2"></i>Add New Police Officer</h4>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Username</label>
                        <input type="text" name="new_police_username" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Password</label>
                        <input type="password" name="new_police_password" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="add_police" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add after stats cards, before the reports table -->
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-paper-plane me-2"></i>Police Access Requests</h4>
            </div>
            <div class="card-body">
                <?php if (empty($requests['requests'])): ?>
                    <p class="text-muted text-center">No pending requests.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Case ID</th>
                                    <th>Police Officer</th>
                                    <th>Requested At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests['requests'] as $req): ?>
                                    <tr>
                                        <td><?= $req['crime_id'] ?></td>
                                        <td><?= htmlspecialchars($req['username']) ?></td>
                                        <td><?= $req['requested_at'] ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="crime_id" value="<?= $req['crime_id'] ?>">
                                                <input type="hidden" name="officer" value="<?= htmlspecialchars($req['username']) ?>">
                                                <button type="submit" name="assign_request" class="btn btn-success btn-sm">
                                                    <i class="fas fa-user-plus me-2"></i>Assign Case
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-list me-2"></i>All Crime Reports</h4>
            </div>
            <div class="card-body">
                <?php if (empty($crimes['crimes'])): ?>
                    <p class="text-muted text-center py-4">
                        <i class="fas fa-inbox fa-2x mb-3"></i><br>
                        No crime reports available.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                    <th><i class="fas fa-exclamation-triangle me-2"></i>Type</th>
                                    <th><i class="fas fa-map-marker-alt me-2"></i>Location</th>
                                    <th><i class="fas fa-calendar me-2"></i>Date</th>
                                    <th><i class="fas fa-tasks me-2"></i>Status</th>
                                    <th><i class="fas fa-user-tie me-2"></i>Assigned Officer</th>
                                    <th><i class="fas fa-cogs me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($crimes['crimes'] as $crime): ?>
                                    <tr>
                                        <td><?= $crime['id'] ?></td>
                                        <td><?= htmlspecialchars($crime['crime_type']) ?></td>
                                        <td><?= htmlspecialchars($crime['location']) ?></td>
                                        <td><?= $crime['date'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= $crime['status'] === 'Solved' ? 'success' : ($crime['status'] === 'In Progress' ? 'warning' : 'secondary') ?>">
                                                <?= $crime['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($crime['assigned_officer']): ?>
                                                <i class="fas fa-user-tie me-2"></i><?= htmlspecialchars($crime['assigned_officer']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateModal<?= $crime['id'] ?>">
                                                <i class="fas fa-edit me-2"></i>Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Move all modals here, outside the table -->
        <?php foreach ($crimes['crimes'] as $crime): ?>
            <div class="modal fade" id="updateModal<?= $crime['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-edit me-2"></i>
                                Update Crime Report #<?= $crime['id'] ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="crime_id" value="<?= $crime['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-tasks me-2"></i>Status
                                    </label>
                                    <select class="form-select" name="status" required>
                                        <option value="Pending" <?= $crime['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="In Progress" <?= $crime['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="Solved" <?= $crime['status'] === 'Solved' ? 'selected' : '' ?>>Solved</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user-tie me-2"></i>Assign Officer
                                    </label>
                                    <select class="form-select" name="assigned_officer">
                                        <option value="">Select Officer</option>
                                        <?php foreach ($police_officers as $officer): ?>
                                            <option value="<?= htmlspecialchars($officer['username']) ?>" <?= $crime['assigned_officer'] === $officer['username'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($officer['username']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 