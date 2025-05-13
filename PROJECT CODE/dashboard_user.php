<?php
require_once 'config.php';

if (!isLoggedIn() || !checkRole('user')) {
    redirect('index.php');
}

$success = '';
$error = '';

// Handle crime report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crime_type = $_POST['crime_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $location = $_POST['location'] ?? '';
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';

    if ($crime_type && $description && $location && $date && $time) {
        $crimes = readJsonFile(CRIMES_FILE);
        
        $newCrime = [
            'id' => count($crimes['crimes']) + 1,
            'user_id' => $_SESSION['user_id'],
            'crime_type' => $crime_type,
            'description' => $description,
            'location' => $location,
            'date' => $date,
            'time' => $time,
            'status' => 'Pending',
            'assigned_officer' => null,
            'investigation_notes' => '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $crimes['crimes'][] = $newCrime;
        writeJsonFile(CRIMES_FILE, $crimes);
        
        $success = 'Crime report submitted successfully!';
    } else {
        $error = 'All fields are required';
    }
}

// Get user's crime reports
$crimes = readJsonFile(CRIMES_FILE);
$userCrimes = array_filter($crimes['crimes'], function($crime) {
    return $crime['user_id'] === $_SESSION['user_id'];
});

// Notify user about newly solved cases (for popup)
if (!isset($_SESSION['notified_solved_cases'])) {
    $_SESSION['notified_solved_cases'] = [];
}
$newly_solved = [];
foreach ($userCrimes as $crime) {
    if ($crime['status'] === 'Solved' && !in_array($crime['id'], $_SESSION['notified_solved_cases'])) {
        $newly_solved[] = $crime;
    }
}
// Handle user message to police
if (isset($_POST['send_message']) && isset($_POST['crime_id']) && isset($_POST['user_message'])) {
    $crime_id = $_POST['crime_id'];
    $user_message = trim($_POST['user_message']);
    if ($user_message) {
        $crimes = readJsonFile(CRIMES_FILE);
        foreach ($crimes['crimes'] as &$crime) {
            if ($crime['id'] == $crime_id && $crime['user_id'] == $_SESSION['user_id']) {
                $crime['user_message'] = $user_message;
            }
        }
        writeJsonFile(CRIMES_FILE, $crimes);
        $_SESSION['notified_solved_cases'][] = $crime_id;
        $success = 'Message sent to police!';
        // Redirect to avoid modal re-show and clear POST
        header('Location: dashboard_user.php');
        exit();
    }
    // Remove from newly_solved so modal doesn't show again
    $newly_solved = array_filter($newly_solved, function($c) use ($crime_id) { return $c['id'] != $crime_id; });
}

// Handle delete/cancel case
if (isset($_POST['delete_case']) && isset($_POST['crime_id'])) {
    $crime_id = $_POST['crime_id'];
    $crimes = readJsonFile(CRIMES_FILE);
    $crimes['crimes'] = array_filter($crimes['crimes'], function($crime) use ($crime_id) {
        return $crime['id'] != $crime_id;
    });
    writeJsonFile(CRIMES_FILE, $crimes);
    $success = 'Case deleted successfully!';
    // Remove from notified_solved_cases if present
    if (($key = array_search($crime_id, $_SESSION['notified_solved_cases'])) !== false) {
        unset($_SESSION['notified_solved_cases'][$key]);
    }
    // Refresh userCrimes after deletion
    $userCrimes = array_filter($crimes['crimes'], function($crime) {
        return $crime['user_id'] === $_SESSION['user_id'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Crime Management System</title>
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
            background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
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
            transition: transform 0.3s ease;
            margin-bottom: 1.5rem;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
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
        .form-select, .form-control {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            border: 2px solid #e9ecef;
        }
        .form-select:focus, .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
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
        .btn-primary {
            background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
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
            color: #3498db;
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
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .form-label i {
            color: #3498db;
            margin-right: 0.5rem;
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
                    <i class="fas fa-user me-2"></i>
                    Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <i class="fas fa-file-alt stats-icon"></i>
                    <div class="stats-number"><?= count($userCrimes) ?></div>
                    <div class="stats-label">Total Reports</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <i class="fas fa-spinner stats-icon"></i>
                    <div class="stats-number">
                        <?= count(array_filter($userCrimes, function($crime) { return $crime['status'] === 'In Progress'; })) ?>
                    </div>
                    <div class="stats-label">Cases In Progress</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <i class="fas fa-check-circle stats-icon"></i>
                    <div class="stats-number">
                        <?= count(array_filter($userCrimes, function($crime) { return $crime['status'] === 'Solved'; })) ?>
                    </div>
                    <div class="stats-label">Solved Cases</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-plus-circle me-2"></i>Report a Crime</h4>
                    </div>
                    <div class="card-body">
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

                        <form method="POST">
                            <div class="mb-3">
                                <label for="crime_type" class="form-label">
                                    <i class="fas fa-exclamation-triangle"></i>Crime Type
                                </label>
                                <select class="form-select" id="crime_type" name="crime_type" required>
                                    <option value="">Select Crime Type</option>
                                    <option value="Theft">Theft</option>
                                    <option value="Assault">Assault</option>
                                    <option value="Vandalism">Vandalism</option>
                                    <option value="Fraud">Fraud</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    <i class="fas fa-align-left"></i>Description
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Please provide detailed information about the incident..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i>Location
                                </label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="Enter the location of the incident" required>
                            </div>
                            <div class="mb-3">
                                <label for="date" class="form-label">
                                    <i class="fas fa-calendar"></i>Date
                                </label>
                                <input type="date" class="form-control" id="date" name="date" required>
                            </div>
                            <div class="mb-3">
                                <label for="time" class="form-label">
                                    <i class="fas fa-clock"></i>Time
                                </label>
                                <input type="time" class="form-control" id="time" name="time" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Submit Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-list me-2"></i>Your Crime Reports</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($userCrimes)): ?>
                            <p class="text-muted text-center py-4">
                                <i class="fas fa-inbox fa-2x mb-3"></i><br>
                                No crime reports submitted yet.
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
                                        <?php foreach ($userCrimes as $crime): ?>
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
                                                    <?php if ($crime['assigned_officer']): ?>
                                                        <i class="fas fa-user-tie me-2"></i><?= htmlspecialchars($crime['assigned_officer']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete/cancel this case?');" style="display:inline;">
                                                        <input type="hidden" name="crime_id" value="<?= $crime['id'] ?>">
                                                        <button type="submit" name="delete_case" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash me-2"></i>Delete
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
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 