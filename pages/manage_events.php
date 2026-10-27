<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/auth_check.php';
requireRole('admin');
require_once __DIR__ . '/../php/db_connect_mysqli.php';
require_once __DIR__ . '/../php/audit_log.php';

const MAX_POSTER_SIZE = 2097152;
const POSTER_DIRECTORY = __DIR__ . '/../uploads/events';
const ALLOWED_POSTER_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectWithStatus(string $status): never
{
    header('Location: manage_events.php?status=' . rawurlencode($status));
    exit;
}

function posterPath(?string $relativePath): ?string
{
    if ($relativePath === null || $relativePath === '') {
        return null;
    }

    $filename = basename($relativePath);
    return POSTER_DIRECTORY . DIRECTORY_SEPARATOR . $filename;
}

function removePoster(?string $relativePath): void
{
    $path = posterPath($relativePath);
    if ($path !== null && is_file($path)) {
        unlink($path);
    }
}

function uploadPoster(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, 'The poster could not be uploaded. Please try again.'];
    }
    if (!isset($file['tmp_name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'The uploaded poster is invalid.'];
    }
    if ((int) $file['size'] > MAX_POSTER_SIZE) {
        return [null, 'Poster size must not exceed 2 MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo === false ? false : finfo_file($finfo, $file['tmp_name']);
    if ($finfo !== false) {
        finfo_close($finfo);
    }
    if (!is_string($mimeType) || !isset(ALLOWED_POSTER_TYPES[$mimeType])) {
        return [null, 'Only JPG, PNG, and WEBP poster files are allowed.'];
    }

    if (!is_dir(POSTER_DIRECTORY) && !mkdir(POSTER_DIRECTORY, 0755, true) && !is_dir(POSTER_DIRECTORY)) {
        return [null, 'The poster upload directory is unavailable.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . ALLOWED_POSTER_TYPES[$mimeType];
    $relativePath = 'uploads/events/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], POSTER_DIRECTORY . DIRECTORY_SEPARATOR . $filename)) {
        return [null, 'The poster could not be saved.'];
    }

    return [$relativePath, null];
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$editEvent = null;
$formValues = [
    'title' => '',
    'event_date' => '',
    'location' => '',
    'category' => '',
    'poster_path' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals((string) $_SESSION['csrf_token'], $postedToken)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $id = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'delete') {
        if ($id === false || $id < 1) {
            $error = 'The selected event ID is invalid.';
        } else {
            $statement = $conn->prepare('SELECT poster_path FROM events WHERE event_id = ?');
            if ($statement === false) {
                $error = 'Unable to prepare the delete operation.';
            } else {
                $statement->bind_param('i', $id);
                $statement->execute();
                $event = $statement->get_result()->fetch_assoc();
                $statement->close();

                if ($event === null) {
                    $error = 'Event was not deleted. The record may no longer exist.';
                } else {
                    $statement = $conn->prepare('DELETE FROM events WHERE event_id = ?');
                    if ($statement === false) {
                        $error = 'Unable to prepare the delete operation.';
                    } else {
                        $statement->bind_param('i', $id);
                        if ($statement->execute() && $statement->affected_rows > 0) {
                            removePoster($event['poster_path'] ?? null);
                            $statement->close();
                            recordAudit($conn, (int) $_SESSION['user_id'], 'DELETE_EVENT', 'Deleted event #' . $id . '.');
                            redirectWithStatus('deleted');
                        }
                        $error = 'Unable to delete the event.';
                        $statement->close();
                    }
                }
            }
        }
    } elseif ($action === 'add' || $action === 'update') {
        $formValues = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'event_date' => trim((string) ($_POST['event_date'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'poster_path' => null,
        ];
        $date = DateTime::createFromFormat('Y-m-d', $formValues['event_date']);
        $dateIsValid = $date !== false && $date->format('Y-m-d') === $formValues['event_date'];

        if ($formValues['title'] === '' || mb_strlen($formValues['title']) > 150) {
            $error = 'Enter an event title (maximum 150 characters).';
        } elseif (!$dateIsValid) {
            $error = 'Enter a valid event date.';
        } elseif (mb_strlen($formValues['location']) > 100) {
            $error = 'Location must not exceed 100 characters.';
        } elseif ($formValues['category'] === '' || mb_strlen($formValues['category']) > 50) {
            $error = 'Enter an event category (maximum 50 characters).';
        } elseif ($action === 'update' && ($id === false || $id < 1)) {
            $error = 'The selected event ID is invalid.';
        } else {
            [$newPoster, $uploadError] = uploadPoster($_FILES['poster'] ?? []);
            if ($uploadError !== null) {
                $error = $uploadError;
            } else {
                $oldPoster = null;
                $oldEvent = null;
                if ($action === 'update') {
                    $lookup = $conn->prepare('SELECT poster_path FROM events WHERE event_id = ?');
                    if ($lookup !== false) {
                        $lookup->bind_param('i', $id);
                        $lookup->execute();
                        $oldEvent = $lookup->get_result()->fetch_assoc();
                        $oldPoster = $oldEvent['poster_path'] ?? null;
                        $lookup->close();
                    }
                    if ($oldEvent === null) {
                        removePoster($newPoster);
                        $error = 'The event could not be found.';
                    }
                }

                if ($error === '') {
                    if ($action === 'add') {
                        $statement = $conn->prepare(
                            'INSERT INTO events (title, event_date, location, category, poster_path)
                             VALUES (?, ?, ?, ?, ?)'
                        );
                        $successStatus = 'added';
                    } else {
                        $posterValue = $newPoster ?? $oldPoster;
                        $statement = $conn->prepare(
                            'UPDATE events
                             SET title = ?, event_date = ?, location = ?, category = ?, poster_path = ?
                             WHERE event_id = ?'
                        );
                        $successStatus = 'updated';
                    }

                    if ($statement === false) {
                        removePoster($newPoster);
                        $error = 'Unable to prepare the event operation.';
                    } elseif ($action === 'add') {
                        $statement->bind_param(
                            'sssss',
                            $formValues['title'],
                            $formValues['event_date'],
                            $formValues['location'],
                            $formValues['category'],
                            $newPoster
                        );
                        if ($statement->execute()) {
                            recordAudit($conn, (int) $_SESSION['user_id'], 'ADD_EVENT', 'Added event "' . $formValues['title'] . '".');
                            $statement->close();
                            redirectWithStatus($successStatus);
                        }
                        removePoster($newPoster);
                        $error = 'Unable to add the event.';
                        $statement->close();
                    } else {
                        $statement->bind_param(
                            'sssssi',
                            $formValues['title'],
                            $formValues['event_date'],
                            $formValues['location'],
                            $formValues['category'],
                            $posterValue,
                            $id
                        );
                        if ($statement->execute()) {
                            if ($newPoster !== null && $oldPoster !== null) {
                                removePoster($oldPoster);
                            }
                            $statement->close();
                            recordAudit($conn, (int) $_SESSION['user_id'], 'UPDATE_EVENT', 'Updated event #' . $id . '.');
                            redirectWithStatus($successStatus);
                        }
                        removePoster($newPoster);
                        $error = 'Unable to update the event.';
                        $statement->close();
                    }
                }
            }
        }
    } else {
        $error = 'Unknown action.';
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId !== false && $editId > 0) {
    $statement = $conn->prepare(
        'SELECT event_id, title, event_date, location, category, poster_path
         FROM events WHERE event_id = ?'
    );
    if ($statement !== false) {
        $statement->bind_param('i', $editId);
        $statement->execute();
        $editEvent = $statement->get_result()->fetch_assoc() ?: null;
        if ($editEvent !== null && $error === '') {
            $formValues = $editEvent;
        }
        $statement->close();
    }
}

$events = [];
$result = $conn->query(
    'SELECT event_id, title, event_date, location, category, poster_path
     FROM events ORDER BY event_date ASC, title ASC'
);
if ($result === false) {
    $error = $error !== '' ? $error : 'Unable to load events.';
} else {
    while ($event = $result->fetch_assoc()) {
        $events[] = $event;
    }
    $result->free();
}

$statusMessages = [
    'added' => ['success', 'Event added successfully.'],
    'updated' => ['success', 'Event updated successfully.'],
    'deleted' => ['success', 'Event deleted successfully.'],
];
$status = $statusMessages[$_GET['status'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="StudentHub administrator event management." />
<title>StudentHub | Manage Events</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
<link rel="stylesheet" href="../css/style.css" />
</head>
<body class="bg-body">
<a class="skip-link" href="#main">Skip to main content</a>
<div class="d-flex min-vh-100">
  <div class="offcanvas-lg offcanvas-start bg-body-tertiary border-end dashboard-sidebar-container p-3 flex-column" tabindex="-1" id="dashboardSidebar" style="width:260px;">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
      <a class="d-flex align-items-center gap-2 text-body text-decoration-none fw-bold" href="admin_dashboard.php">
        <img class="rounded-3 border shadow-sm" src="../img/logo.jpg" alt="StudentHub logo" style="width:36px;height:36px;object-fit:cover;" />
        <div><div class="lh-1 fs-5">StudentHub</div><small class="text-secondary fw-normal" style="font-size:0.75rem">Admin Portal</small></div>
      </a>
      <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#dashboardSidebar" aria-label="Close"></button>
    </div>
    <nav class="dashboard-sidebar-nav nav flex-column gap-1 flex-grow-1">
      <a href="admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="admin_profile.html" class="nav-link"><i class="bi bi-shield-person"></i> Admin Profile</a>
      <a href="student_management.php" class="nav-link"><i class="bi bi-people"></i> Manage Students</a>
      <a href="manage_events.php" class="nav-link active" aria-current="page"><i class="bi bi-calendar3"></i> Manage Events</a>
      <a href="manage_events_ajax.php" class="nav-link"><i class="bi bi-lightning"></i> AJAX Event CRUD</a>
      <a href="../php/logout.php" class="nav-link text-danger mt-3"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </nav>
  </div>
  <div class="flex-grow-1 d-flex flex-column min-vh-100 overflow-hidden">
    <nav class="navbar d-lg-none bg-body border-bottom px-3 py-2">
      <button class="btn btn-outline-secondary btn-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open Sidebar"><i class="bi bi-list fs-5"></i></button>
      <span class="fw-bold">StudentHub Admin</span>
    </nav>
    <main id="main" class="p-3 p-lg-4 flex-grow-1">
      <nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.html" class="text-secondary text-decoration-none">Home</a></li><li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-secondary text-decoration-none">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Manage Events</li></ol></nav>
      <header class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-2">
        <div><h1 class="h3 fw-bold mb-1">Campus Event Management</h1><p class="text-secondary mb-0">Create, update, and publish event posters for students.</p></div>
      </header>
      <?php if ($status !== null): ?>
        <div class="alert alert-<?= h($status[0]) ?> alert-dismissible fade show" role="alert"><?= h($status[1]) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
      <?php endif; ?>
      <div class="card mb-4 p-4 rounded-4 shadow-sm border bg-body">
        <h4 class="fw-bold mb-3"><i class="bi bi-calendar-plus me-1 text-primary"></i> <?= $editEvent === null ? 'Add New Event' : 'Edit Event' ?></h4>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>" />
          <input type="hidden" name="action" value="<?= $editEvent === null ? 'add' : 'update' ?>" />
          <?php if ($editEvent !== null): ?><input type="hidden" name="event_id" value="<?= h($editEvent['event_id']) ?>" /><?php endif; ?>
          <div class="row g-3">
            <div class="col-md-6"><label for="title" class="form-label fw-semibold small">Event Title</label><input id="title" name="title" class="form-control rounded-3" maxlength="150" value="<?= h($formValues['title']) ?>" required /></div>
            <div class="col-md-6"><label for="event_date" class="form-label fw-semibold small">Event Date</label><input id="event_date" name="event_date" type="date" class="form-control rounded-3" value="<?= h($formValues['event_date']) ?>" required /></div>
            <div class="col-md-6"><label for="location" class="form-label fw-semibold small">Location</label><input id="location" name="location" class="form-control rounded-3" maxlength="100" value="<?= h($formValues['location']) ?>" /></div>
            <div class="col-md-6"><label for="category" class="form-label fw-semibold small">Category</label><input id="category" name="category" class="form-control rounded-3" maxlength="50" value="<?= h($formValues['category']) ?>" required /></div>
            <div class="col-12"><label for="poster" class="form-label fw-semibold small">Event Poster <span class="text-secondary fw-normal">(JPG, PNG, or WEBP; maximum 2 MB)</span></label><input id="poster" name="poster" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control rounded-3" /></div>
          </div>
          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary rounded-pill px-4"><?= $editEvent === null ? 'Add Event' : 'Save Changes' ?></button>
            <?php if ($editEvent !== null): ?><a href="manage_events.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>
      <h4 class="fw-bold mb-3"><i class="bi bi-calendar-check me-1 text-primary"></i> Existing Events</h4>
      <div class="table-responsive rounded-3 border mb-4">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Poster</th><th>Event Title</th><th>Date</th><th>Location</th><th>Category</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if ($events === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">No events found.</td></tr><?php endif; ?>
          <?php foreach ($events as $event): ?>
            <tr>
              <td><?php if (!empty($event['poster_path'])): ?><img src="../<?= h($event['poster_path']) ?>" alt="<?= h($event['title']) ?> poster" style="width:72px;height:48px;object-fit:cover;" class="rounded-2 border" /><?php else: ?><span class="text-secondary small">No poster</span><?php endif; ?></td>
              <td class="fw-semibold"><?= h($event['title']) ?></td><td><?= h(date('d M Y', strtotime($event['event_date']))) ?></td><td><?= h($event['location']) ?></td><td><span class="badge bg-primary-subtle text-primary"><?= h($event['category']) ?></span></td>
              <td><div class="d-flex gap-1"><a href="manage_events.php?edit=<?= h($event['event_id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a><form method="post" onsubmit="return confirm('Delete this event? This action cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>" /><input type="hidden" name="action" value="delete" /><input type="hidden" name="event_id" value="<?= h($event['event_id']) ?>" /><button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button></form></div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </main>
    <footer class="border-top py-3 mt-auto bg-body px-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><span class="text-secondary small">&copy; 2026 StudentHub. All rights reserved.</span><a href="../php/logout.php" class="text-secondary small">Sign out</a></div></footer>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
