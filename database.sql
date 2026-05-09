DROP DATABASE IF EXISTS notice_board;
CREATE DATABASE notice_board;
USE notice_board;

CREATE TABLE teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_name VARCHAR(100) NOT NULL,
    team_lead_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    dob DATE,
    designation VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('employee', 'team_lead', 'hr', 'admin') DEFAULT 'employee',
    team_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
);

CREATE TABLE notices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('general', 'individual', 'urgent', 'team') NOT NULL,
    employee_id INT,
    team_id INT,
    created_by INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    evaluator_id INT,
    csat DECIMAL(5,2) DEFAULT 0,
    tickets DECIMAL(5,2) DEFAULT 0,
    fcr DECIMAL(5,2) DEFAULT 0,
    resolution_time DECIMAL(5,2) DEFAULT 0,
    response_time DECIMAL(5,2) DEFAULT 0,
    total DECIMAL(5,2) DEFAULT 0,
    comments TEXT,
    status ENUM('pending', 'reviewed', 'consulted') DEFAULT 'pending',
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE TABLE consultations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    team_lead_id INT NOT NULL,
    performance_id INT,
    consultation_date DATE NOT NULL,
    notes TEXT,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (team_lead_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (performance_id) REFERENCES performance(id) ON DELETE CASCADE
);

ALTER TABLE teams ADD FOREIGN KEY (team_lead_id) REFERENCES employees(id) ON DELETE SET NULL;

-- Insert teams
INSERT INTO teams (team_name) VALUES ('Team Alpha'), ('Team Beta');

-- ** THIS IS THE CORRECT HASH FOR 'password123' **
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

INSERT INTO employees (employee_id, name, phone, dob, designation, password, role, team_id) VALUES
('HR001', 'HR Manager', '01710000001', '1985-01-01', 'HR Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hr', NULL),
('TL001', 'Team Lead Alpha', '01710000002', '1988-05-15', 'Team Lead', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'team_lead', 1),
('TL002', 'Team Lead Beta', '01710000003', '1989-08-20', 'Team Lead', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'team_lead', 2),
('EMP001', 'Rahim Uddin', '01710000004', '1992-03-10', 'Full Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 1),
('EMP002', 'Karim Mia', '01710000005', '1993-07-22', 'Full Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 1),
('EMP003', 'Fatema Begum', '01710000006', '1994-11-15', 'Part Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 1),
('EMP004', 'Jamal Hossain', '01710000007', '1991-06-30', 'Full Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 1),
('EMP005', 'Nasrin Sultana', '01710000008', '1995-02-14', 'Full Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 1),
('EMP006', 'Shakil Ahmed', '01710000009', '1993-09-05', 'Team Coordinator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 2),
('EMP007', 'Rashed Khan', '01710000010', '1992-12-25', 'Full Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 2),
('EMP008', 'Nusrat Jahan', '01710000011', '1994-04-18', 'Part Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 2),
('EMP009', 'Tanvir Hasan', '01710000012', '1991-08-08', 'Full Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 2),
('EMP010', 'Sabina Yasmin', '01710000013', '1996-01-20', 'Full Timer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 2);

-- Update team leads
UPDATE teams SET team_lead_id = (SELECT id FROM employees WHERE employee_id = 'TL001') WHERE id = 1;
UPDATE teams SET team_lead_id = (SELECT id FROM employees WHERE employee_id = 'TL002') WHERE id = 2;

-- Verify
SELECT employee_id, name, role, 
       CASE WHEN password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' THEN 'OK' ELSE 'MISMATCH' END as hash_check
FROM employees;