<?php
require_once __DIR__ . "/../php/auth_check.php";
requireRole("admin");
require_once __DIR__ . "/../php/db_connect_mysqli.php";
require_once __DIR__ . "/../php/audit_log.php";

$dashboardError = '';
$statistics = ['users' => 0, 'students' => 0, 'events' => 0, 'registrations' => 0];
$countQueries = [
    'users' => 'SELECT COUNT(*) AS total FROM users',
    'students' => 'SELECT COUNT(*) AS total FROM students',
    'events' => 'SELECT COUNT(*) AS total FROM events',
    'registrations' => 'SELECT COUNT(*) AS total FROM registrations',
];
foreach ($countQueries as $key => $query) {
    $result = $conn->query($query);
    if ($result === false) {
        $dashboardError = 'Unable to load dashboard statistics.';
        break;
    }
    $statistics[$key] = (int) $result->fetch_assoc()['total'];
    $result->free();
}

recordAudit($conn, (int) $_SESSION['user_id'], 'VIEW_DASHBOARD', 'Administrator opened the dashboard.');

$latestRegistrations = [];
$registrationResult = $conn->query(
    'SELECT r.registered_on, s.name AS student_name, e.title AS event_title
     FROM registrations r
     INNER JOIN students s ON s.id = r.student_id
     INNER JOIN events e ON e.event_id = r.event_id
     ORDER BY r.registered_on DESC, r.reg_id DESC
     LIMIT 5'
);
if ($registrationResult !== false) {
    while ($registration = $registrationResult->fetch_assoc()) {
        $latestRegistrations[] = $registration;
    }
    $registrationResult->free();
} elseif ($dashboardError === '') {
    $dashboardError = 'Unable to load latest registrations.';
}

$auditLogs = [];
if (ensureAuditLogTable($conn)) {
    $auditResult = $conn->query(
        'SELECT a.action, a.details, a.created_at, COALESCE(u.name, \'Deleted user\') AS user_name
         FROM audit_logs a
         LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC, a.log_id DESC
         LIMIT 5'
    );
    if ($auditResult !== false) {
        while ($log = $auditResult->fetch_assoc()) {
            $auditLogs[] = $log;
        }
        $auditResult->free();
    }
}

function dashboardH(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="StudentHub administrator dashboard." />
<title>StudentHub | Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
<link rel="stylesheet" href="../css/style.css" />
<script src="../js/script.js" defer></script>
</head>
<body class="bg-body">
<a class="skip-link" href="#main">Skip to main content</a>

<div class="d-flex min-vh-100">
  <!-- Native Bootstrap Responsive Offcanvas Sidebar -->
  <div class="offcanvas-lg offcanvas-start bg-body-tertiary border-end dashboard-sidebar-container p-3 flex-column" tabindex="-1" id="dashboardSidebar" style="width:260px;">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
      <a class="d-flex align-items-center gap-2 text-body text-decoration-none fw-bold" href="admin_dashboard.php">
        <img class="rounded-3 border shadow-sm" src="../img/logo.jpg" alt="StudentHub logo" style="width:36px;height:36px;object-fit:cover;" />
        <div>
          <div class="lh-1 fs-5">StudentHub</div>
          <small class="text-secondary fw-normal" style="font-size:0.75rem">Admin Portal</small>
        </div>
      </a>
      <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#dashboardSidebar" aria-label="Close"></button>
    </div>

    <nav class="dashboard-sidebar-nav nav flex-column gap-1 flex-grow-1">
      <a href="admin_dashboard.php" class="nav-link active" aria-current="page"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="admin_profile.html" class="nav-link"><i class="bi bi-shield-person"></i> Admin Profile</a>
      <a href="student_management.php" class="nav-link"><i class="bi bi-people"></i> Manage Students</a>
      <a href="manage_events.php" class="nav-link"><i class="bi bi-calendar3"></i> Manage Events</a>
      <a href="manage_events_ajax.php" class="nav-link"><i class="bi bi-lightning"></i> AJAX Event CRUD</a>
      <a href="../php/logout.php" class="nav-link text-danger mt-3"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </nav>
  </div>

  <!-- Main Content Area -->
  <div class="flex-grow-1 d-flex flex-column min-vh-100 overflow-hidden">
    <!-- Top Bar for Mobile -->
    <nav class="navbar d-lg-none bg-body border-bottom px-3 py-2">
      <button class="btn btn-outline-secondary btn-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open Sidebar">
        <i class="bi bi-list fs-5"></i>
      </button>
      <span class="fw-bold">StudentHub Admin</span>
    </nav>

    <main id="main" class="p-3 p-lg-4 flex-grow-1">
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="../index.html" class="text-secondary text-decoration-none">Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">Admin Dashboard</li>
        </ol>
      </nav>

      <header class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-2">
        <div>
          <h1 class="h3 fw-bold mb-1">Administrator Dashboard</h1>
          <p class="text-secondary mb-0">System metrics, student records management, and event publishing tools.</p>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary btn-sm rounded-pill px-3" href="student_management.php"><i class="bi bi-table me-1"></i> Records</a>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-primary btn-sm rounded-pill px-3" href="manage_events_ajax.php"><i class="bi bi-lightning me-1"></i> AJAX CRUD</a>
            <a class="btn btn-primary btn-sm rounded-pill px-3" href="manage_events.php"><i class="bi bi-plus-circle me-1"></i> Add Event</a>
          </div>
        </div>
      </header>
      <?php if ($dashboardError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= dashboardH($dashboardError) ?></div>
      <?php endif; ?>

      <!-- KPI Stat Cards -->
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
        <div class="col">
          <div class="card stat-card h-100 p-4 rounded-4 shadow-sm border bg-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-secondary fw-semibold small text-uppercase">Total Students</span>
              <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                <i class="bi bi-mortarboard-fill"></i>
              </div>
            </div>
            <div class="display-6 fw-bold mb-1 text-primary"><?= number_format($statistics['students']) ?></div>
            <p class="text-secondary small mb-0">Registered student records</p>
          </div>
        </div>
        <div class="col">
          <div class="card stat-card h-100 p-4 rounded-4 shadow-sm border bg-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-secondary fw-semibold small text-uppercase">Active Events</span>
              <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                <i class="bi bi-calendar-event-fill"></i>
              </div>
            </div>
            <div class="display-6 fw-bold mb-1 text-info"><?= number_format($statistics['events']) ?></div>
            <p class="text-secondary small mb-0">Events in the database</p>
          </div>
        </div>
        <div class="col">
          <div class="card stat-card h-100 p-4 rounded-4 shadow-sm border bg-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-secondary fw-semibold small text-uppercase">Registrations</span>
              <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                <i class="bi bi-check-circle-fill"></i>
              </div>
            </div>
            <div class="display-6 fw-bold mb-1 text-success"><?= number_format($statistics['registrations']) ?></div>
            <p class="text-secondary small mb-0">Event registrations</p>
          </div>
        </div>
        <div class="col">
          <div class="card stat-card h-100 p-4 rounded-4 shadow-sm border bg-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-secondary fw-semibold small text-uppercase">Feedbacks</span>
              <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                <i class="bi bi-chat-square-text-fill"></i>
              </div>
            </div>
            <div class="display-6 fw-bold mb-1 text-warning"><?= number_format($statistics['users']) ?></div>
            <p class="text-secondary small mb-0">Registered portal users</p>
          </div>
        </div>
      </div>

      <!-- Quick Action Modules -->
      <h4 class="fw-bold mb-3">Administrative Modules</h4>
      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="card h-100 p-4 rounded-4 shadow-sm border bg-body">
            <div class="card-body p-0 d-flex flex-column">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;font-size:1.3rem">
                  <i class="bi bi-people-fill"></i>
                </div>
                <div>
                  <h5 class="fw-bold mb-0">Student Records Management</h5>
                  <span class="text-secondary small">Filter, search, and verify student enrollments</span>
                </div>
              </div>
              <p class="card-text text-secondary small mb-4 flex-grow-1">Access complete student profiles across Computer Engineering, Information Technology, and Computer Science departments with instant department filtering.</p>
              <a href="student_management.php" class="btn btn-primary btn-sm rounded-pill px-4 align-self-start shadow-sm">Open Student Registry &rarr;</a>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card h-100 p-4 rounded-4 shadow-sm border bg-body">
            <div class="card-body p-0 d-flex flex-column">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;font-size:1.3rem">
                  <i class="bi bi-calendar-plus-fill"></i>
                </div>
                <div>
                  <h5 class="fw-bold mb-0">Campus Event Scheduling</h5>
                  <span class="text-secondary small">Publish workshops, quizzes, and sports meets</span>
                </div>
              </div>
              <p class="card-text text-secondary small mb-4 flex-grow-1">Publish new campus activities, update dates, times, venues, and attendee capacity with real-time student portal synchronization.</p>
              <a href="manage_events.php" class="btn btn-primary btn-sm rounded-pill px-4 align-self-start shadow-sm">Manage Event Schedules &rarr;</a>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card h-100 p-4 rounded-4 shadow-sm border bg-body">
            <h5 class="fw-bold mb-3"><i class="bi bi-person-check me-1 text-primary"></i> Latest Registrations</h5>
            <?php if ($latestRegistrations === []): ?>
              <p class="text-secondary mb-0">No registrations found.</p>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($latestRegistrations as $registration): ?>
                  <div class="list-group-item px-0 d-flex justify-content-between gap-3">
                    <span><strong><?= dashboardH($registration['student_name']) ?></strong><br><small class="text-secondary"><?= dashboardH($registration['event_title']) ?></small></span>
                    <small class="text-secondary text-nowrap"><?= dashboardH(date('d M Y, H:i', strtotime($registration['registered_on']))) ?></small>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card h-100 p-4 rounded-4 shadow-sm border bg-body">
            <h5 class="fw-bold mb-3"><i class="bi bi-shield-check me-1 text-primary"></i> Audit Log</h5>
            <?php if ($auditLogs === []): ?>
              <p class="text-secondary mb-0">No audit activity found.</p>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($auditLogs as $log): ?>
                  <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between gap-2"><span class="badge text-bg-primary"><?= dashboardH($log['action']) ?></span><small class="text-secondary"><?= dashboardH(date('d M Y, H:i', strtotime($log['created_at']))) ?></small></div>
                    <small><?= dashboardH($log['details']) ?> · <?= dashboardH($log['user_name']) ?></small>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>

    <footer class="border-top py-3 mt-auto bg-body px-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-secondary small">&copy; 2026 StudentHub. All rights reserved.</span>
        <nav class="d-flex gap-3 small">
          <a href="contact.html" class="text-secondary text-decoration-none">Contact</a>
          <a href="faq.html" class="text-secondary text-decoration-none">FAQ</a>
          <a href="feedback.html" class="text-secondary text-decoration-none">Feedback</a>
        </nav>
      </div>
    </footer>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
