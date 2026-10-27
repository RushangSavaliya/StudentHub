<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/auth_check.php';
requireApiRole('admin');
require_once __DIR__ . '/../php/db_connect_mysqli.php';
require_once __DIR__ . '/../php/audit_log.php';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function requestBody(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        respond(['success' => false, 'message' => 'A JSON request body is required.'], 400);
    }

    $body = json_decode($rawBody, true);
    if (!is_array($body)) {
        respond(['success' => false, 'message' => 'The request body must contain valid JSON.'], 400);
    }

    return $body;
}

function validateEvent(array $body): array
{
    $title = trim((string) ($body['title'] ?? ''));
    $eventDate = trim((string) ($body['event_date'] ?? ''));
    $location = trim((string) ($body['location'] ?? ''));
    $category = trim((string) ($body['category'] ?? ''));
    $date = DateTime::createFromFormat('Y-m-d', $eventDate);

    if ($title === '' || mb_strlen($title) > 150) {
        respond(['success' => false, 'message' => 'Enter an event title (maximum 150 characters).'], 422);
    }
    if ($date === false || $date->format('Y-m-d') !== $eventDate) {
        respond(['success' => false, 'message' => 'Enter a valid event date.'], 422);
    }
    if (mb_strlen($location) > 100) {
        respond(['success' => false, 'message' => 'Location must not exceed 100 characters.'], 422);
    }
    if ($category === '' || mb_strlen($category) > 50) {
        respond(['success' => false, 'message' => 'Enter an event category (maximum 50 characters).'], 422);
    }

    return [$title, $eventDate, $location, $category];
}

function eventFromRow(array $row): array
{
    return [
        'event_id' => (int) $row['event_id'],
        'title' => (string) $row['title'],
        'event_date' => (string) $row['event_date'],
        'location' => (string) ($row['location'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
    ];
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(['success' => false, 'message' => 'Invalid request token. Refresh the page and try again.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $result = $conn->query(
        'SELECT event_id, title, event_date, location, category
         FROM events ORDER BY event_date ASC, title ASC'
    );
    if ($result === false) {
        respond(['success' => false, 'message' => 'Unable to load events.'], 500);
    }

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = eventFromRow($row);
    }
    $result->free();
    respond(['success' => true, 'events' => $events]);
}

if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    respond(['success' => false, 'message' => 'HTTP method not supported.'], 405);
}

$body = requestBody();
$action = (string) ($body['action'] ?? '');

if ($method === 'POST' && $action === 'add') {
    [$title, $eventDate, $location, $category] = validateEvent($body);
    $statement = $conn->prepare(
        'INSERT INTO events (title, event_date, location, category) VALUES (?, ?, ?, ?)'
    );
    if ($statement === false) {
        respond(['success' => false, 'message' => 'Unable to prepare the add operation.'], 500);
    }
    $statement->bind_param('ssss', $title, $eventDate, $location, $category);
    if (!$statement->execute()) {
        $statement->close();
        respond(['success' => false, 'message' => 'Unable to add the event.'], 500);
    }
    $eventId = $statement->insert_id;
    $statement->close();
    recordAudit($conn, (int) $_SESSION['user_id'], 'ADD_EVENT', 'Added event "' . $title . '" through the JSON API.');
    respond([
        'success' => true,
        'message' => 'Event added successfully without reloading the page.',
        'event' => [
            'event_id' => $eventId,
            'title' => $title,
            'event_date' => $eventDate,
            'location' => $location,
            'category' => $category,
        ],
    ], 201);
}

$eventId = filter_var($body['event_id'] ?? null, FILTER_VALIDATE_INT);
if ($eventId === false || $eventId < 1) {
    respond(['success' => false, 'message' => 'A valid event ID is required.'], 422);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    [$title, $eventDate, $location, $category] = validateEvent($body);
    $statement = $conn->prepare(
        'UPDATE events SET title = ?, event_date = ?, location = ?, category = ? WHERE event_id = ?'
    );
    if ($statement === false) {
        respond(['success' => false, 'message' => 'Unable to prepare the update operation.'], 500);
    }
    $statement->bind_param('ssssi', $title, $eventDate, $location, $category, $eventId);
    if (!$statement->execute()) {
        $statement->close();
        recordAudit($conn, (int) $_SESSION['user_id'], 'UPDATE_EVENT', 'Updated event #' . $eventId . ' through the JSON API.');
        respond(['success' => false, 'message' => 'Unable to update the event.'], 500);
    }
    if ($statement->affected_rows === 0) {
        $check = $conn->prepare('SELECT event_id FROM events WHERE event_id = ?');
        if ($check === false) {
            $statement->close();
            recordAudit($conn, (int) $_SESSION['user_id'], 'DELETE_EVENT', 'Deleted event #' . $eventId . ' through the JSON API.');
            respond(['success' => false, 'message' => 'Unable to verify the updated event.'], 500);
        }
        $check->bind_param('i', $eventId);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();
        if (!$exists) {
            $statement->close();
            respond(['success' => false, 'message' => 'The event was not found.'], 404);
        }
    }
    $statement->close();
    respond([
        'success' => true,
        'message' => 'Event updated successfully without reloading the page.',
        'event' => [
            'event_id' => $eventId,
            'title' => $title,
            'event_date' => $eventDate,
            'location' => $location,
            'category' => $category,
        ],
    ]);
}

if ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
    $statement = $conn->prepare('DELETE FROM events WHERE event_id = ?');
    if ($statement === false) {
        respond(['success' => false, 'message' => 'Unable to prepare the delete operation.'], 500);
    }
    $statement->bind_param('i', $eventId);
    if (!$statement->execute() || $statement->affected_rows === 0) {
        $statement->close();
        respond(['success' => false, 'message' => 'Event was not found or could not be deleted.'], 404);
    }
    $statement->close();
    respond([
        'success' => true,
        'message' => 'Event deleted successfully without reloading the page.',
        'event_id' => $eventId,
    ]);
}

respond(['success' => false, 'message' => 'Unknown event operation.'], 400);
