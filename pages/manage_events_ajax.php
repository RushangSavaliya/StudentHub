<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/auth_check.php';
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="StudentHub asynchronous event management." />
<title>StudentHub | AJAX Event CRUD</title>
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
      <a href="student_management.php" class="nav-link"><i class="bi bi-people"></i> Manage Students</a>
      <a href="manage_events.php" class="nav-link"><i class="bi bi-calendar3"></i> Manage Events</a>
      <a href="manage_events_ajax.php" class="nav-link active" aria-current="page"><i class="bi bi-lightning"></i> AJAX Event CRUD</a>
      <a href="../php/logout.php" class="nav-link text-danger mt-3"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </nav>
  </div>
  <div class="flex-grow-1 d-flex flex-column min-vh-100 overflow-hidden">
    <nav class="navbar d-lg-none bg-body border-bottom px-3 py-2">
      <button class="btn btn-outline-secondary btn-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open Sidebar"><i class="bi bi-list fs-5"></i></button>
      <span class="fw-bold">StudentHub Admin</span>
    </nav>
    <main id="main" class="p-3 p-lg-4 flex-grow-1">
      <nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.html" class="text-secondary text-decoration-none">Home</a></li><li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-secondary text-decoration-none">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">AJAX Event CRUD</li></ol></nav>
      <header class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-2">
        <div><h1 class="h3 fw-bold mb-1">Asynchronous Event Management</h1><p class="text-secondary mb-0">Add, edit, delete, and list events with Fetch API without a page reload.</p></div>
        <span class="badge text-bg-primary rounded-pill px-3 py-2"><i class="bi bi-lightning-charge me-1"></i> Fetch API</span>
      </header>
      <div id="message" class="alert d-none" role="alert" aria-live="polite"></div>
      <div class="card mb-4 p-4 rounded-4 shadow-sm border bg-body">
        <h2 id="form-heading" class="h5 fw-bold mb-3"><i class="bi bi-calendar-plus me-1 text-primary"></i> Add Event</h2>
        <form id="event-form" novalidate>
          <input type="hidden" id="event-id" />
          <div class="row g-3">
            <div class="col-md-6"><label for="title" class="form-label fw-semibold small">Event Title</label><input id="title" class="form-control rounded-3" maxlength="150" required /></div>
            <div class="col-md-6"><label for="event-date" class="form-label fw-semibold small">Event Date</label><input id="event-date" type="date" class="form-control rounded-3" required /></div>
            <div class="col-md-6"><label for="location" class="form-label fw-semibold small">Location</label><input id="location" class="form-control rounded-3" maxlength="100" /></div>
            <div class="col-md-6"><label for="category" class="form-label fw-semibold small">Category</label><input id="category" class="form-control rounded-3" maxlength="50" required /></div>
          </div>
          <div class="d-flex gap-2 mt-4">
            <button id="submit-button" class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-plus-circle me-1"></i> Add Event</button>
            <button id="cancel-button" class="btn btn-outline-secondary rounded-pill px-4 d-none" type="button">Cancel Edit</button>
          </div>
        </form>
      </div>
      <div class="card p-4 rounded-4 shadow-sm border bg-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h2 class="h5 fw-bold mb-0">Events from MySQL Database</h2>
          <button id="refresh-button" class="btn btn-outline-primary btn-sm rounded-pill" type="button"><i class="bi bi-arrow-clockwise me-1"></i> Refresh List</button>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Title</th><th>Date</th><th>Location</th><th>Category</th><th class="text-end">Actions</th></tr></thead>
            <tbody id="event-rows"><tr><td colspan="5" class="text-center text-secondary py-4">Loading events...</td></tr></tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>
<script>
const API_URL = '../api/events.php';
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const form = document.getElementById('event-form');
const rows = document.getElementById('event-rows');
const message = document.getElementById('message');
const eventId = document.getElementById('event-id');
const submitButton = document.getElementById('submit-button');
const cancelButton = document.getElementById('cancel-button');
let events = [];

function showMessage(text, type = 'success') {
  message.textContent = text;
  message.className = `alert alert-${type}`;
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[character]));
}

function renderEvents() {
  if (events.length === 0) {
    rows.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-4">No events found.</td></tr>';
    return;
  }
  rows.innerHTML = events.map(event => `
    <tr>
      <td class="fw-semibold">${escapeHtml(event.title)}</td>
      <td>${escapeHtml(event.event_date)}</td>
      <td>${escapeHtml(event.location)}</td>
      <td><span class="badge text-bg-light border">${escapeHtml(event.category)}</span></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary rounded-pill me-1" data-action="edit" data-id="${event.event_id}">Edit</button>
        <button class="btn btn-sm btn-outline-danger rounded-pill" data-action="delete" data-id="${event.event_id}">Delete</button>
      </td>
    </tr>`).join('');
}

async function request(url = API_URL, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN, ...(options.headers || {})}
  });
  const data = await response.json();
  if (!response.ok || !data.success) throw new Error(data.message || 'The request failed.');
  return data;
}

async function loadEvents() {
  try {
    const data = await request();
    events = data.events;
    renderEvents();
  } catch (error) {
    showMessage(error.message, 'danger');
    rows.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Unable to load events.</td></tr>';
  }
}

function resetForm() {
  form.reset();
  eventId.value = '';
  document.getElementById('form-heading').innerHTML = '<i class="bi bi-calendar-plus me-1 text-primary"></i> Add Event';
  submitButton.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Add Event';
  cancelButton.classList.add('d-none');
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const payload = {
    action: eventId.value ? 'update' : 'add',
    event_id: eventId.value ? Number(eventId.value) : undefined,
    title: document.getElementById('title').value.trim(),
    event_date: document.getElementById('event-date').value,
    location: document.getElementById('location').value.trim(),
    category: document.getElementById('category').value.trim()
  };
  submitButton.disabled = true;
  try {
    const data = await request(API_URL, {method: 'POST', body: JSON.stringify(payload)});
    showMessage(data.message);
    if (payload.action === 'add') events.push(data.event);
    else events = events.map(item => item.event_id === data.event.event_id ? data.event : item);
    events.sort((a, b) => a.event_date.localeCompare(b.event_date) || a.title.localeCompare(b.title));
    renderEvents();
    resetForm();
  } catch (error) {
    showMessage(error.message, 'danger');
  } finally { submitButton.disabled = false; }
});

rows.addEventListener('click', async event => {
  const button = event.target.closest('button[data-action]');
  if (!button) return;
  const id = Number(button.dataset.id);
  const selected = events.find(item => item.event_id === id);
  if (button.dataset.action === 'edit' && selected) {
    eventId.value = selected.event_id;
    document.getElementById('title').value = selected.title;
    document.getElementById('event-date').value = selected.event_date;
    document.getElementById('location').value = selected.location;
    document.getElementById('category').value = selected.category;
    document.getElementById('form-heading').innerHTML = '<i class="bi bi-pencil-square me-1 text-primary"></i> Edit Event';
    submitButton.innerHTML = '<i class="bi bi-save me-1"></i> Save Changes';
    cancelButton.classList.remove('d-none');
    document.getElementById('title').focus();
    return;
  }
  if (button.dataset.action === 'delete' && confirm('Delete this event?')) {
    button.disabled = true;
    try {
      const data = await request(API_URL, {method: 'POST', body: JSON.stringify({action: 'delete', event_id: id})});
      events = events.filter(item => item.event_id !== id);
      renderEvents();
      if (eventId.value === String(id)) resetForm();
      showMessage(data.message);
    } catch (error) {
      showMessage(error.message, 'danger');
      button.disabled = false;
    }
  }
});

cancelButton.addEventListener('click', resetForm);
document.getElementById('refresh-button').addEventListener('click', loadEvents);
loadEvents();
</script>
</body>
</html>
