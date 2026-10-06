<?php
declare(strict_types=1);

function renderPage(string $title, string $heading, string $message, array $errors = []): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>StudentHub | ' . $safeTitle . '</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="../css/style.css"><script src="../js/script.js" defer></script></head><body><main class="container py-5"><div class="card p-4 p-md-5 mx-auto" style="max-width: 680px;"><div class="card-body p-0"><h1 class="h3 fw-bold mb-3">' . $safeHeading . '</h1><p class="text-body-secondary lead mb-4">' . $safeMessage . '</p>';

    if ($errors) {
        echo '<div class="alert alert-danger mb-4"><ul class="mb-0 ps-3">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<a class="btn btn-primary" href="../pages/register.html">&larr; Back to Registration</a></div></div></main></body></html>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    renderPage('Invalid Request', 'Invalid Request', 'Please submit the registration form using POST.');
    exit;
}

$name = trim(strip_tags($_POST['full_name'] ?? ''));
$email = trim($_POST['email'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$course = trim(strip_tags($_POST['course'] ?? ''));
$year = trim(strip_tags($_POST['year'] ?? ''));
$gender = trim(strip_tags($_POST['gender'] ?? ''));
$termsAccepted = isset($_POST['terms']);

$errors = [];

if ($name === '') {
    $errors[] = 'Name is required.';
} elseif (!preg_match("/^[A-Za-z]+(?:[ '-][A-Za-z]+)*$/", $name)) {
    $errors[] = 'Name can contain letters, spaces, apostrophes, and hyphens only.';
}

if ($email === '') {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
    $errors[] = 'Please enter a valid 10-digit mobile number.';
}

$passwordIsStrong = strlen($password) >= 8
    && preg_match('/[A-Z]/', $password)
    && preg_match('/[a-z]/', $password)
    && preg_match('/[0-9]/', $password)
    && preg_match('/[^A-Za-z0-9]/', $password);
if (!$passwordIsStrong) {
    $errors[] = 'Password must contain at least 8 characters, uppercase and lowercase letters, a number, and a special character.';
}

if ($confirmPassword === '') {
    $errors[] = 'Please confirm your password.';
} elseif ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

$validCourses = ['computer-engineering', 'information-technology', 'computer-science'];
if (!in_array($course, $validCourses, true)) {
    $errors[] = 'Please select your course.';
}

if (!in_array($year, ['1', '2', '3', '4'], true)) {
    $errors[] = 'Please select your academic year.';
}

if (!in_array($gender, ['male', 'female', 'other'], true)) {
    $errors[] = 'Please select your gender.';
}

if (!$termsAccepted) {
    $errors[] = 'You must accept the terms and conditions.';
}

if ($errors) {
    http_response_code(422);
    renderPage('Registration Error', 'Registration Failed', 'Please correct the following errors and try again.', $errors);
    exit;
}

$storagePath = __DIR__ . '/../data/registrations.csv';
$file = fopen($storagePath, 'c+');
if ($file === false || !flock($file, LOCK_EX)) {
    if ($file !== false) {
        fclose($file);
    }
    http_response_code(500);
    renderPage('Storage Error', 'Registration Unavailable', 'Your registration could not be saved. Please try again later.');
    exit;
}

if (filesize($storagePath) === 0) {
    fputcsv($file, ['Name', 'Email', 'Mobile', 'Password Hash', 'Course', 'Year', 'Gender']);
}

fseek($file, 0, SEEK_END);
$saved = fputcsv($file, [
    $name,
    $email,
    $mobile,
    password_hash($password, PASSWORD_DEFAULT),
    $course,
    $year,
    $gender,
]);
fflush($file);
flock($file, LOCK_UN);
fclose($file);

if ($saved === false) {
    http_response_code(500);
    renderPage('Storage Error', 'Registration Unavailable', 'Your registration could not be saved. Please try again later.');
    exit;
}

renderPage('Registration Successful', 'Registration Successful', 'Your registration has been submitted and stored safely.');
