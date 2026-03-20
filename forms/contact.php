<?php
/**
 * Contact Form Handler for Bravin Rotich Portfolio
 * Secure form handler with validation and CSRF protection
 * Sends form submissions to rotichbravin13@gmail.com
 */

// Enable error logging but don't display errors to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Please use POST.'
    ]);
    exit;
}

// Your email address
$receiving_email_address = 'rotichbravin13@gmail.com';

// Security: Rate limiting (prevent spam)
session_start();
$rate_limit_time = 60; // 60 seconds between submissions
$rate_limit_key = 'form_submission_time';

if (isset($_SESSION[$rate_limit_key]) && (time() - $_SESSION[$rate_limit_key]) < $rate_limit_time) {
    $remaining_time = $rate_limit_time - (time() - $_SESSION[$rate_limit_key]);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => "Please wait {$remaining_time} seconds before submitting again."
    ]);
    exit;
}

// Security: CSRF Token Validation (if implemented in frontend)
if (isset($_POST['csrf_token'])) {
    if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security token. Please refresh the page and try again.'
        ]);
        exit;
    }
}

// Security: Honeypot field (spam detection)
if (!empty($_POST['website_url']) || !empty($_POST['phone'])) {
    // These fields should be empty - if filled, it's likely a bot
    http_response_code(200); // Return success to confuse bots
    echo json_encode([
        'success' => true,
        'message' => 'Message received. Thank you!'
    ]);
    exit;
}

// Get and sanitize form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : 'Portfolio Contact Form Submission';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Security: Input validation
$errors = [];

// Validate name (2-100 characters, letters and spaces only)
if (empty($name)) {
    $errors[] = 'Name is required';
} elseif (strlen($name) < 2 || strlen($name) > 100) {
    $errors[] = 'Name must be between 2 and 100 characters';
} elseif (!preg_match('/^[a-zA-Z\s\-\']+$/u', $name)) {
    $errors[] = 'Name can only contain letters, spaces, hyphens, and apostrophes';
}

// Validate email
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
} elseif (strlen($email) > 255) {
    $errors[] = 'Email address is too long';
}

// Validate subject
if (!empty($subject) && strlen($subject) > 200) {
    $errors[] = 'Subject must be less than 200 characters';
}

// Validate message (10-5000 characters)
if (empty($message)) {
    $errors[] = 'Message is required';
} elseif (strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters';
} elseif (strlen($message) > 5000) {
    $errors[] = 'Message must be less than 5000 characters';
}

// Security: Check for suspicious patterns
$suspicious_patterns = [
    '/<script/i',
    '/javascript:/i',
    '/on\w+\s*=/i',
    '/<iframe/i',
    '/<object/i',
    '/<embed/i',
    '/content-type:/i',
    '/boundary=/i'
];

foreach ([$name, $email, $subject, $message] as $input) {
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            $errors[] = 'Invalid characters detected in input';
            break 2;
        }
    }
}

// Return validation errors
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
    exit;
}

// Sanitize inputs for display
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// Prepare email content
$email_subject = "Portfolio Contact: " . $subject;
$email_body = "You have received a new message from your portfolio website.\n\n";
$email_body .= "=== CONTACT DETAILS ===\n\n";
$email_body .= "Name: {$name}\n";
$email_body .= "Email: {$email}\n";
$email_body .= "Subject: {$subject}\n";
$email_body .= "Date: " . date('Y-m-d H:i:s') . "\n";
$email_body .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n\n";
$email_body .= "=== MESSAGE ===\n\n";
$email_body .= "{$message}\n\n";
$email_body .= "===================\n";
$email_body .= "Sent from Bravin Rotich Portfolio Website\n";

// Email headers with security
$headers = [];
$headers[] = "From: Portfolio Contact <noreply@" . $_SERVER['HTTP_HOST'] . ">";
$headers[] = "Reply-To: {$name} <{$email}>";
$headers[] = "X-Mailer: PHP/" . phpversion();
$headers[] = "X-Priority: 3";
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/plain; charset=UTF-8";

// Try to send email using mail() function
$email_sent = false;
$mail_error = '';

// Method 1: Try PHP mail() function
if (function_exists('mail')) {
    try {
        if (@mail($receiving_email_address, $email_subject, $email_body, implode("\r\n", $headers))) {
            $email_sent = true;
        } else {
            $mail_error = 'mail() function failed';
        }
    } catch (Exception $e) {
        $mail_error = $e->getMessage();
    }
}

// Method 2: If mail() failed, try sendmail directly (Linux/Unix)
if (!$email_sent && function_exists('exec') && PHP_OS_FAMILY === 'Linux') {
    try {
        $sendmail_path = ini_get('sendmail_path');
        if (!empty($sendmail_path) && $sendmail_path !== 'sendmail') {
            $process = popen($sendmail_path . ' -t', 'w');
            if ($process) {
                fwrite($process, "To: {$receiving_email_address}\n");
                fwrite($process, "From: Portfolio Contact <noreply@" . $_SERVER['HTTP_HOST'] . ">\n");
                fwrite($process, "Reply-To: {$email}\n");
                fwrite($process, "Subject: {$email_subject}\n");
                fwrite($process, "Content-Type: text/plain; charset=UTF-8\n\n");
                fwrite($process, $email_body);
                pclose($process);
                $email_sent = true;
            }
        }
    } catch (Exception $e) {
        // Silently fail
    }
}

// Update rate limit timestamp
$_SESSION[$rate_limit_key] = time();

// Return response
if ($email_sent) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your message has been sent successfully. I will get back to you soon.'
    ]);
} else {
    // Log error for debugging
    error_log("Contact form email failed: {$mail_error}");
    
    // Return user-friendly message
    http_response_code(200); // Return 200 to show message
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your message! Due to server configuration, the email could not be sent automatically. Please contact me directly at rotichbravin13@gmail.com',
        'fallback' => true,
        'direct_email' => 'rotichbravin13@gmail.com'
    ]);
}
?>
