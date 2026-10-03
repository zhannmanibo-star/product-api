<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AccountSetup extends Controller
{
    public function create()
    {
        // Account creation is available only through the terminal.
        if (PHP_SAPI !== 'cli') {
            http_response_code(404);
            exit;
        }

        $username = trim(getenv('SEED_USERNAME') ?: '');
        $email = trim(getenv('SEED_EMAIL') ?: '');
        $password = getenv('SEED_PASSWORD') ?: '';

        if (
            !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username) ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 255 ||
            strlen($password) < 12 ||
            strlen($password) > 72
        ) {
            echo "Invalid account details." . PHP_EOL;
            echo "Username: 3–50 letters, numbers, or underscores." . PHP_EOL;
            echo "Use a valid email and a 12–72 byte password." . PHP_EOL;
            exit(1);
        }

        $this->call->database();

        $existing = $this->db->raw(
            'SELECT id FROM users
             WHERE username = ? OR email = ?
             LIMIT 1',
            [$username, $email]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo "Username or email already exists. No changes made." . PHP_EOL;
            exit(1);
        }

        $this->db->raw(
            'INSERT INTO users
                (username, email, password, role, is_active)
             VALUES (?, ?, ?, ?, ?)',
            [
                $username,
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                'user',
                1
            ]
        );

        echo "Account created successfully." . PHP_EOL;
    }
}