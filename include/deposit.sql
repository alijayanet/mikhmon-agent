CREATE TABLE user_deposit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    deposit_amount DECIMAL(10,2),
    balance DECIMAL(10,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    transaction_type ENUM('deposit', 'purchase'),
    amount DECIMAL(10,2),
    description TEXT,
    wa_number VARCHAR(15),
    status ENUM('pending', 'completed', 'failed'),
    created_at TIMESTAMP
); 