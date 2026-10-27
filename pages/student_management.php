<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/auth_check.php';
requireRole('admin');
require_once __DIR__ . '/../php/db_connect_mysqli.php';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectWithStatus(string $status): never
{
    header('Location: student_management.php?status=' . rawurlencode($status));
    exit;
}

function bindParams(mysqli_stmt $statement, string $types, array &$params): void
{
    $references = [];
    foreach ($params as $key => &$value) {
        $references[$key] = &$value;
    }
    $statement->bind_param($types, ...$references);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$editStudent = null;
$formValues = [
    'name' => '',
    'email' => '',
    'mobile' => '',
    'course_id' => '',
    'year' => '',
    'gender' => '',
];

$courses = [];
$courseResult = $conn->query('SELECT course_id, course_name FROM courses ORDER BY course_name');
if ($courseResult === false) {
    $error = 'Unable to load courses. Please try again later.';
} else {
    while ($course = $courseResult->fetch_assoc()) {
        $courses[] = $course;
    }
    $courseResult->free();
}

$courseNames = [];
foreach ($courses as $course) {
    $courseNames[(int) $course['course_id']] = (string) $course['course_name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals((string) $_SESSION['csrf_token'], $postedToken)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $error = 'The selected student ID is invalid.';
        } else {
            $statement = $conn->prepare('DELETE FROM students WHERE id = ?');
            if ($statement === false) {
                $error = 'Unable to prepare the delete operation.';
            } else {
                $statement->bind_param('i', $id);
                if ($statement->execute() && $statement->affected_rows > 0) {
                    $statement->close();
                    redirectWithStatus('deleted');
                }
                $error = 'Student was not deleted. The record may no longer exist.';
                $statement->close();
            }
        }
    } elseif ($action === 'add' || $action === 'update') {
        $formValues = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'mobile' => trim((string) ($_POST['mobile'] ?? '')),
            'course_id' => filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT),
            'year' => filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT),
            'gender' => (string) ($_POST['gender'] ?? ''),
        ];

        $selectedCourseId = $formValues['course_id'];
        $selectedCourse = is_int($selectedCourseId)
            ? ($courseNames[$selectedCourseId] ?? null)
            : null;
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if ($formValues['name'] === '' || mb_strlen($formValues['name']) > 100) {
            $error = 'Enter a student name (maximum 100 characters).';
        } elseif (
            !filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)
            || strlen($formValues['email']) > 150
        ) {
            $error = 'Enter a valid email address (maximum 150 characters).';
        } elseif ($formValues['mobile'] === '' || strlen($formValues['mobile']) > 15) {
            $error = 'Enter a mobile number of up to 15 characters.';
        } elseif ($selectedCourse === null) {
            $error = 'Select a valid course.';
        } elseif ($formValues['year'] === false || $formValues['year'] < 1 || $formValues['year'] > 10) {
            $error = 'Enter a valid academic year.';
        } elseif (!in_array($formValues['gender'], ['male', 'female', 'other'], true)) {
            $error = 'Select a valid gender.';
        } elseif ($action === 'update' && ($id === false || $id < 1)) {
            $error = 'The selected student ID is invalid.';
        } else {
            if ($action === 'add') {
                $statement = $conn->prepare(
                    'INSERT INTO students (name, email, mobile, course_id, year, gender)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $successStatus = 'added';
                $successMessage = 'Student added successfully.';
            } else {
                $statement = $conn->prepare(
                    'UPDATE students
                     SET name = ?, email = ?, mobile = ?, course_id = ?, year = ?, gender = ?
                     WHERE id = ?'
                );
                $successStatus = 'updated';
                $successMessage = 'Student updated successfully.';
            }

            if ($statement === false) {
                $error = 'Unable to prepare the student operation.';
            } elseif ($action === 'add') {
                $statement->bind_param(
                    'sssiis',
                    $formValues['name'],
                    $formValues['email'],
                    $formValues['mobile'],
                    $selectedCourseId,
                    $formValues['year'],
                    $formValues['gender']
                );
                if ($statement->execute()) {
                    $statement->close();
                    redirectWithStatus($successStatus);
                }
                $error = $statement->errno === 1062
                    ? 'A record with that email already exists.'
                    : 'Unable to add the student.';
                $statement->close();
            } else {
                $statement->bind_param(
                    'sssiisi',
                    $formValues['name'],
                    $formValues['email'],
                    $formValues['mobile'],
                    $selectedCourseId,
                    $formValues['year'],
                    $formValues['gender'],
                    $id
                );
                if ($statement->execute()) {
                    $statement->close();
                    redirectWithStatus($successStatus);
                }
                $error = $statement->errno === 1062
                    ? 'A record with that email already exists.'
                    : 'Unable to update the student.';
                $statement->close();
            }
        }
    } else {
        $error = 'Unknown action.';
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId !== false && $editId > 0) {
    $statement = $conn->prepare(
        'SELECT id, name, email, mobile, course_id, year, gender
         FROM students WHERE id = ?'
    );
    if ($statement !== false) {
        $statement->bind_param('i', $editId);
        if ($statement->execute()) {
            $editStudent = $statement->get_result()->fetch_assoc() ?: null;
            if ($editStudent !== null && $error === '') {
                $formValues = $editStudent;
            }
        }
        $statement->close();
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$courseFilter = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT);
$courseFilter = $courseFilter !== false && $courseFilter > 0 ? $courseFilter : null;
$yearFilter = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
$yearFilter = $yearFilter !== false && $yearFilter > 0 ? $yearFilter : null;
$genderFilter = (string) ($_GET['gender'] ?? '');
$genderFilter = in_array($genderFilter, ['male', 'female', 'other'], true) ? $genderFilter : '';

$where = [];
$filterParams = [];
$filterTypes = '';
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(s.name LIKE ? OR s.email LIKE ? OR c.course_name LIKE ?)';
    array_push($filterParams, $like, $like, $like);
    $filterTypes .= 'sss';
}
if ($courseFilter !== null) {
    $where[] = 's.course_id = ?';
    $filterParams[] = $courseFilter;
    $filterTypes .= 'i';
}
if ($yearFilter !== null) {
    $where[] = 's.year = ?';
    $filterParams[] = $yearFilter;
    $filterTypes .= 'i';
}
if ($genderFilter !== '') {
    $where[] = 's.gender = ?';
    $filterParams[] = $genderFilter;
    $filterTypes .= 's';
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$countStatement = $conn->prepare(
    'SELECT COUNT(*) AS total FROM students s
     INNER JOIN courses c ON c.course_id = s.course_id' . $whereSql
);
$totalRecords = 0;
if ($countStatement !== false) {
    if ($filterTypes !== '') {
        bindParams($countStatement, $filterTypes, $filterParams);
    }
    if ($countStatement->execute()) {
        $totalRecords = (int) $countStatement->get_result()->fetch_assoc()['total'];
    }
    $countStatement->close();
}

$perPage = 10;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;
$totalPages = max(1, (int) ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listParams = $filterParams;
$listTypes = $filterTypes . 'ii';
$listParams[] = $perPage;
$listParams[] = $offset;
$students = [];
$listStatement = $conn->prepare(
    'SELECT s.id, s.name, s.email, s.mobile, c.course_name, s.year, s.gender
     FROM students s
     INNER JOIN courses c ON c.course_id = s.course_id' . $whereSql . '
     ORDER BY s.id DESC LIMIT ? OFFSET ?'
);
if ($listStatement !== false) {
    bindParams($listStatement, $listTypes, $listParams);
    if ($listStatement->execute()) {
        $result = $listStatement->get_result();
        while ($student = $result->fetch_assoc()) {
            $students[] = $student;
        }
        $result->free();
    }
    $listStatement->close();
}

$statusMessages = [
    'added' => 'Student added successfully.',
    'updated' => 'Student updated successfully.',
    'deleted' => 'Student deleted successfully.',
];
$successMessage = $statusMessages[(string) ($_GET['status'] ?? '')] ?? '';

function pageUrl(int $page, string $search, ?int $courseFilter, ?int $yearFilter, string $genderFilter): string
{
    $query = ['page' => $page];
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($courseFilter !== null) {
        $query['course_id'] = $courseFilter;
    }
    if ($yearFilter !== null) {
        $query['year'] = $yearFilter;
    }
    if ($genderFilter !== '') {
        $query['gender'] = $genderFilter;
    }
    return 'student_management.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="StudentHub student management CRUD page for administrators.">
<title>StudentHub | Manage Students</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-body">
<a class="skip-link" href="#main">Skip to main content</a>
<div class="d-flex min-vh-100">
  <aside class="offcanvas-lg offcanvas-start bg-body-tertiary border-end dashboard-sidebar-container p-3 flex-column" tabindex="-1" id="dashboardSidebar" style="width:260px;">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
      <a class="d-flex align-items-center gap-2 text-body text-decoration-none fw-bold" href="admin_dashboard.php">
        <img class="rounded-3 border shadow-sm" src="../img/logo.jpg" alt="StudentHub logo" style="width:36px;height:36px;object-fit:cover;">
        <div><div class="lh-1 fs-5">StudentHub</div><small class="text-secondary fw-normal" style="font-size:0.75rem">Admin Portal</small></div>
      </a>
      <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#dashboardSidebar" aria-label="Close"></button>
    </div>
    <nav class="dashboard-sidebar-nav nav flex-column gap-1 flex-grow-1">
      <a href="admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="admin_profile.html" class="nav-link"><i class="bi bi-shield-person"></i> Admin Profile</a>
      <a href="student_management.php" class="nav-link active" aria-current="page"><i class="bi bi-people"></i> Manage Students</a>
      <a href="manage_events.php" class="nav-link"><i class="bi bi-calendar3"></i> Manage Events</a>
      <a href="../php/logout.php" class="nav-link text-danger mt-3"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </nav>
  </aside>

  <div class="flex-grow-1 d-flex flex-column min-vh-100 overflow-hidden">
    <nav class="navbar d-lg-none bg-body border-bottom px-3 py-2">
      <button class="btn btn-outline-secondary btn-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open Sidebar"><i class="bi bi-list fs-5"></i></button>
      <span class="fw-bold">StudentHub Admin</span>
    </nav>
    <main id="main" class="p-3 p-lg-4 flex-grow-1">
      <nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.html" class="text-secondary text-decoration-none">Home</a></li><li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-secondary text-decoration-none">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Manage Students</li></ol></nav>
      <header class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-2">
        <div><h1 class="h3 fw-bold mb-1">Manage Student Records</h1><p class="text-secondary mb-0">Create, search, filter, edit, and delete student records.</p></div>
        <?php if ($editStudent !== null): ?><a href="student_management.php" class="btn btn-outline-secondary rounded-pill px-3">Cancel Edit</a><?php endif; ?>
      </header>

      <?php if ($successMessage !== ''): ?><div class="alert alert-success" role="status"><?= h($successMessage) ?></div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>

      <section class="card mb-4 p-4 rounded-4 shadow-sm border bg-body" aria-labelledby="student-form-heading">
        <h2 id="student-form-heading" class="h5 fw-bold mb-3"><?= $editStudent !== null ? 'Edit Student' : 'Add Student' ?></h2>
        <?php if ($courses === []): ?><div class="alert alert-warning">No courses are available. Add a course before creating a student.</div><?php endif; ?>
        <form method="post" action="student_management.php<?= $editStudent !== null ? '?edit=' . (int) $editStudent['id'] : '' ?>">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="action" value="<?= $editStudent !== null ? 'update' : 'add' ?>">
          <?php if ($editStudent !== null): ?><input type="hidden" name="id" value="<?= (int) $editStudent['id'] ?>"><?php endif; ?>
          <div class="row g-3">
            <div class="col-md-6"><label for="student-name" class="form-label fw-semibold">Full name</label><input id="student-name" name="name" class="form-control" maxlength="100" required value="<?= h($formValues['name']) ?>"></div>
            <div class="col-md-6"><label for="student-email" class="form-label fw-semibold">Email</label><input id="student-email" type="email" name="email" class="form-control" maxlength="150" required value="<?= h($formValues['email']) ?>"></div>
            <div class="col-md-4"><label for="student-mobile" class="form-label fw-semibold">Mobile</label><input id="student-mobile" name="mobile" class="form-control" maxlength="15" required value="<?= h($formValues['mobile']) ?>"></div>
            <div class="col-md-4"><label for="student-course" class="form-label fw-semibold">Course</label><select id="student-course" name="course_id" class="form-select" required><option value="">Select course</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['course_id'] ?>" <?= (string) $formValues['course_id'] === (string) $course['course_id'] ? 'selected' : '' ?>><?= h($course['course_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label for="student-year" class="form-label fw-semibold">Year</label><input id="student-year" type="number" name="year" class="form-control" min="1" max="10" required value="<?= h($formValues['year']) ?>"></div>
            <div class="col-md-2"><label for="student-gender" class="form-label fw-semibold">Gender</label><select id="student-gender" name="gender" class="form-select" required><option value="">Select</option><?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?><option value="<?= $value ?>" <?= $formValues['gender'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
          </div>
          <button type="submit" class="btn btn-primary rounded-pill px-4 mt-4" <?= $courses === [] ? 'disabled' : '' ?>><?= $editStudent !== null ? 'Update Student' : 'Add Student' ?></button>
        </form>
      </section>

      <section class="card p-4 rounded-4 shadow-sm border bg-body" aria-labelledby="student-list-heading">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 id="student-list-heading" class="h5 fw-bold mb-0">Student Records</h2><span class="text-secondary small"><?= $totalRecords ?> matching record<?= $totalRecords === 1 ? '' : 's' ?> · Page <?= $page ?> of <?= $totalPages ?></span></div>
        <form method="get" class="row g-3 align-items-end mb-4">
          <div class="col-lg-4"><label for="search" class="form-label fw-semibold">Search name, email, or course</label><input id="search" type="search" name="q" class="form-control" value="<?= h($search) ?>" placeholder="e.g. aarav or gmail"></div>
          <div class="col-lg-3"><label for="course-filter" class="form-label fw-semibold">Filter by course</label><select id="course-filter" name="course_id" class="form-select"><option value="">All courses</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['course_id'] ?>" <?= $courseFilter === (int) $course['course_id'] ? 'selected' : '' ?>><?= h($course['course_name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-lg-2"><label for="year-filter" class="form-label fw-semibold">Filter by year</label><select id="year-filter" name="year" class="form-select"><option value="">All years</option><?php for ($year = 1; $year <= 10; $year++): ?><option value="<?= $year ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endfor; ?></select></div>
          <div class="col-lg-2"><label for="gender-filter" class="form-label fw-semibold">Filter by gender</label><select id="gender-filter" name="gender" class="form-select"><option value="">All genders</option><?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?><option value="<?= $value ?>" <?= $genderFilter === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
          <div class="col-lg-1 d-grid"><button class="btn btn-primary" type="submit">Apply</button></div>
        </form>
        <div class="table-responsive rounded-3 border">
          <table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Course</th><th>Year</th><th>Gender</th><th>Actions</th></tr></thead><tbody>
          <?php if ($students === []): ?><tr><td colspan="8" class="text-center text-secondary py-4">No student records found.</td></tr><?php else: foreach ($students as $student): ?><tr><td><?= (int) $student['id'] ?></td><td class="fw-semibold"><?= h($student['name']) ?></td><td><?= h($student['email']) ?></td><td><?= h($student['mobile']) ?></td><td><?= h($student['course_name']) ?></td><td><?= (int) $student['year'] ?></td><td><?= h(ucfirst((string) $student['gender'])) ?></td><td><div class="d-flex gap-1"><a href="student_management.php?edit=<?= (int) $student['id'] ?>" class="btn btn-outline-secondary btn-sm">Edit</a><form method="post" action="student_management.php" onsubmit="return confirm('Are you sure you want to delete this student record?');"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $student['id'] ?>"><button type="submit" class="btn btn-outline-danger btn-sm">Delete</button></form></div></td></tr><?php endforeach; endif; ?>
          </tbody></table>
        </div>
        <?php if ($totalPages > 1): ?><nav class="mt-4" aria-label="Student pagination"><ul class="pagination justify-content-center mb-0"><?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?= h(pageUrl($page - 1, $search, $courseFilter, $yearFilter, $genderFilter)) ?>">Previous</a></li><?php endif; ?><?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?><li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>"><a class="page-link" href="<?= h(pageUrl($pageNumber, $search, $courseFilter, $yearFilter, $genderFilter)) ?>"><?= $pageNumber ?></a></li><?php endfor; ?><?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="<?= h(pageUrl($page + 1, $search, $courseFilter, $yearFilter, $genderFilter)) ?>">Next</a></li><?php endif; ?></ul></nav><?php endif; ?>
      </section>
    </main>
    <footer class="border-top py-3 mt-auto bg-body px-4"><span class="text-secondary small">&copy; 2026 StudentHub. All rights reserved.</span></footer>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
