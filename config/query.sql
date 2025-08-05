CREATE TABLE employees (
                           id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                           name VARCHAR(100),
                           email VARCHAR(100),
                           phone VARCHAR(50),
                           department VARCHAR(50),
                           job_title VARCHAR(50),
                           contract_type VARCHAR(50),
                           national_id VARCHAR(100),
                           tax_id VARCHAR(100),
                           ssn VARCHAR(100),
                           nhima_number VARCHAR(100),
                           status ENUM('active','inactive') DEFAULT 'active',
                           created_at TIMESTAMP NULL,
                           updated_at TIMESTAMP NULL
);

CREATE TABLE salaries (
                          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                          employee_id BIGINT UNSIGNED,
                          basic_salary DECIMAL(10,2),
                          pay_frequency ENUM('monthly', 'weekly'),
                          created_at TIMESTAMP NULL,
                          updated_at TIMESTAMP NULL,
                          FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE allowances (
                            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            employee_id BIGINT UNSIGNED,
                            type VARCHAR(100),
                            amount DECIMAL(10,2),
                            created_at TIMESTAMP NULL,
                            updated_at TIMESTAMP NULL,
                            FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE deductions (
                            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            employee_id BIGINT UNSIGNED,
                            type VARCHAR(100),
                            amount DECIMAL(10,2),
                            created_at TIMESTAMP NULL,
                            updated_at TIMESTAMP NULL,
                            FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE payrolls (
                          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                          employee_id BIGINT UNSIGNED,
                          gross_pay DECIMAL(10,2),
                          total_deductions DECIMAL(10,2),
                          net_pay DECIMAL(10,2),
                          pay_month DATE,
                          status ENUM('processed','pending') DEFAULT 'pending',
                          created_at TIMESTAMP NULL,
                          FOREIGN KEY (employee_id) REFERENCES employees(id)
);
