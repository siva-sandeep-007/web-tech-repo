<?php
require_once 'config.php';

if (!isLoggedIn() || !checkRole('police')) {
    redirect('index.php');
}

$success = '';
$error = '';
$show_update_error = false;

// Handle investigation notes and status update
if (isset($_POST['crime_id']) && isset($_POST['status']) && !isset($_POST['request_access'])) {
    $crime_id = $_POST['crime_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $investigation_notes = $_POST['investigation_notes'] ?? '';
    if ($crime_id && $status) {
        $crimes = readJsonFile(CRIMES_FILE);
        foreach ($crimes['crimes'] as &$crime) {
            if ($crime['id'] == $crime_id && $crime['assigned_officer'] === $_SESSION['username']) {
                $crime['status'] = $status;
                if ($investigation_notes) {
                    $crime['investigation_notes'] = $investigation_notes;
                }
                break;
            }
        }
        writeJsonFile(CRIMES_FILE, $crimes);
        $success = 'Case updated successfully!';
    } else {
        $error = 'Invalid update request';
        $show_update_error = true;
    }
}

// Get all crime reports
$crimes = readJsonFile(CRIMES_FILE);
// Get all requests
$requests = file_exists('data/requests.json') ? readJsonFile('data/requests.json') : ['requests' => []];

// Helper: check if this police has requested this case
function hasRequested($crime_id, $username, $requests) {
    foreach ($requests['requests'] as $req) {
        if ($req['crime_id'] == $crime_id && $req['username'] == $username) return true;
    }
    return false;
}

// Handle request access
if (isset($_POST['request_access'])) {
    $crime_id = $_POST['crime_id'];
    $username = $_SESSION['username'];
    if (!hasRequested($crime_id, $username, $requests)) {
        $requests['requests'][] = [
            'crime_id' => $crime_id,
            'username' => $username,
            'requested_at' => date('Y-m-d H:i:s')
        ];
        writeJsonFile('data/requests.json', $requests);
        $success = 'Request sent to admin!';
    } else {
        $error = 'You have already requested access to this case.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Police Dashboard - Crime Management System</title>
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
            background: linear-gradient(90deg, #2ecc71 0%, #27ae60 100%);
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
            background: linear-gradient(90deg, #2ecc71 0%, #27ae60 100%);
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
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.2);
        }
        .modal-content {
            border: none;
            border-radius: 15px;
        }
        .modal-header {
            background: linear-gradient(90deg, #2ecc71 0%, #27ae60 100%);
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
            border-color: #2ecc71;
            box-shadow: 0 0 0 0.2rem rgba(46, 204, 113, 0.25);
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
            color: #2ecc71;
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
        .case-status {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
        }
        .case-status.pending {
            background: #f3f4f6;
            color: #6b7280;
        }
        .case-status.in-progress {
            background: #fef3c7;
            color: #d97706;
        }
        .case-status.solved {
            background: #d1fae5;
            color: #059669;
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
                    <i class="fas fa-user-tie me-2"></i>
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
        
        <?php if ($show_update_error && $error): ?>
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
                    <div class="stats-label">Total Cases</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <i class="fas fa-spinner stats-icon"></i>
                    <div class="stats-number">
                        <?= count(array_filter($crimes['crimes'], function($crime) { return $crime['status'] === 'In Progress'; })) ?>
                    </div>
                    <div class="stats-label">Cases In Progress</div>
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

        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-list me-2"></i>All Cases</h4>
            </div>
            <div class="card-body">
                <?php if (empty($crimes['crimes'])): ?>
                    <p class="text-muted text-center py-4">
                        <i class="fas fa-inbox fa-2x mb-3"></i><br>
                        No cases found.
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
                                            <span class="case-status <?= strtolower(str_replace(' ', '-', $crime['status'])) ?>">
                                                <i class="fas fa-circle me-2"></i>
                                                <?= $crime['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($crime['assigned_officer'] === $_SESSION['username']): ?>
                                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateModal<?= $crime['id'] ?>">
                                                    <i class="fas fa-edit me-2"></i>Update Case
                                                </button>
                                            <?php elseif (!$crime['assigned_officer']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="crime_id" value="<?= $crime['id'] ?>">
                                                    <button type="submit" name="request_access" class="btn btn-warning btn-sm" <?= hasRequested($crime['id'], $_SESSION['username'], $requests) ? 'disabled' : '' ?>>
                                                        <i class="fas fa-paper-plane me-2"></i>Request Access
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">Assigned to <?= htmlspecialchars($crime['assigned_officer']) ?></span>
                                            <?php endif; ?>
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
            <?php if ($crime['assigned_officer'] === $_SESSION['username']): ?>
            <div class="modal fade" id="updateModal<?= $crime['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-edit me-2"></i>
                                Update Case #<?= $crime['id'] ?>
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
                                        <i class="fas fa-clipboard-list me-2"></i>Investigation Notes
                                    </label>
                                    <textarea class="form-control" name="investigation_notes" rows="4" 
                                              placeholder="Enter your investigation notes here..."><?= htmlspecialchars($crime['investigation_notes'] ?? '') ?></textarea>
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
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 