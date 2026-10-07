-- =============================================================================
-- StudentHub Database Schema
-- WEB DEVELOPMENT FRAMEWORKS · ITUE203
-- =============================================================================

CREATE DATABASE IF NOT EXISTS studenthub
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE studenthub;

-- Drop child tables first, then parent tables
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS events;

-- =============================================================================
-- 1. Table: courses (Normalized 3NF table for course definitions)
-- =============================================================================
CREATE TABLE courses (
    course_id INT(11) NOT NULL AUTO_INCREMENT,
    course_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Courses
INSERT INTO courses (course_id, course_name) VALUES
(1, 'Information Technology'),
(2, 'Computer Science'),
(3, 'Computer Engineering');

-- =============================================================================
-- 2. Table: students
-- =============================================================================
CREATE TABLE students (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mobile VARCHAR(15) NOT NULL,
    course_id INT(11),
    year INT(11),
    gender VARCHAR(20),
    PRIMARY KEY (id),
    FOREIGN KEY (course_id)
        REFERENCES courses(course_id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Students
INSERT INTO students (id, name, email, mobile, course_id, year, gender) VALUES
(1, 'Aarav Patel', 'aarav@gmail.com', '9876543201', 1, 3, 'male'),
(2, 'Diya Shah', 'diya@gmail.com', '9876543202', 1, 2, 'female'),
(3, 'Isha Joshi', 'isha@gmail.com', '9876543203', 2, 1, 'female'),
(4, 'Rohan Mehta', 'rohan@gmail.com', '9876543204', 3, 3, 'male'),
(5, 'Neha Patel', 'neha@gmail.com', '9876543205', 2, 2, 'female'),
(6, 'Yash Desai', 'yash@gmail.com', '9876543206', 1, 4, 'male');

-- =============================================================================
-- 3. Table: events
-- =============================================================================
CREATE TABLE events (
    event_id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    event_date DATE NOT NULL,
    location VARCHAR(100),
    category VARCHAR(50),
    PRIMARY KEY (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Events
INSERT INTO events (event_id, title, event_date, location, category) VALUES
(1, 'Web Development Workshop', '2026-09-15', 'Computer Laboratory', 'Workshop'),
(2, 'Tech Fest 2026', '2026-09-20', 'Main Auditorium', 'Technical'),
(3, 'Annual Sports Event', '2026-09-25', 'College Sports Ground', 'Sports'),
(4, 'UI Design Bootcamp', '2026-10-03', 'Design Studio', 'Workshop'),
(5, 'Code Sprint', '2026-10-08', 'Innovation Lab', 'Competition');

-- =============================================================================
-- 4. Table: registrations (M:N Join Table)
-- =============================================================================
CREATE TABLE registrations (
    reg_id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reg_id),
    UNIQUE (student_id, event_id),
    FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,
    FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Registrations
INSERT INTO registrations (student_id, event_id) VALUES
(1, 1),
(1, 2),
(2, 1),
(2, 3),
(3, 4),
(4, 2),
(5, 5),
(6, 1);

-- =============================================================================
-- 5. Table: users (Secure User Authentication & Roles)
-- =============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'student',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Users (Passwords hashed using password_hash('Admin@123', PASSWORD_DEFAULT) / 'Student@123')
INSERT INTO users (id, name, email, password, role) VALUES
(1, 'System Administrator', 'admin@studenthub.local', '$2y$10$eA0AqvY97X5cWfX5WJz8a.y7uRkVp8UvL4xO5A.lBkJv0YfIq5w3G', 'admin'),
(2, 'Aarav Patel', 'aarav@gmail.com', '$2y$10$w09u74M8rL4P65U3i/fG9.mUqfGjZvZ0O4jV7vE9u8N0c1k7L4kO6', 'student')
ON DUPLICATE KEY UPDATE id=id;

