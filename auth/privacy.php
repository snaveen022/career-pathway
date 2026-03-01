<?php
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set('Asia/Kolkata');
$last_updated = '2025-12-05';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Privacy Policy — Career Pathway</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/public/css/main.css">
    <style>
        .container { max-width: 900px; margin: 32px auto; padding: 20px; background: #fff; border-radius: 10px; font-family: Arial, sans-serif; }
        h1 { margin-top: 0; }
        .meta { color: #6b7280; font-size: 0.95rem; margin-bottom: 16px; }
        section { margin-bottom: 18px; }
        ul { margin: 8px 0 8px 20px; }
    </style>
</head>
<body>


<main class="container">
    <h1>Privacy Policy</h1>
    <div class="meta">Last updated: <?= e($last_updated) ?></div>

    <p>This Privacy Policy explains how <strong>Career Pathway</strong> collects, uses and protects the personal information of users who register or participate in MCQ tests and practice sessions. By using the platform you consent to the practices described below.</p>

    <section>
        <h2>1. Information We Collect</h2>
        <ul>
            <li><strong>Identity data:</strong> Name, roll number, class, batch.</li>
            <li><strong>Contact data:</strong> Email address for login, OTP verification and notifications.</li>
            <li><strong>Login data:</strong> Password hash (never stored in plain text).</li>
            <li><strong>Usage data:</strong> Test answers, scores, attempt logs, timestamps and activity history.</li>
            <li><strong>Technical data:</strong> Browser type, IP address (for security and anti‑cheating purposes).</li>
        </ul>
    </section>

    <section>
        <h2>2. How We Use Your Information</h2>
        <ul>
            <li>Create and manage your account.</li>
            <li>Conduct MCQ tests and record results.</li>
            <li>Detect cheating or irregular activity.</li>
            <li>Improve test quality and platform performance.</li>
            <li>Send necessary communications such as OTPs or announcements.</li>
        </ul>
    </section>

    <section>
        <h2>3. How We Store and Protect Data</h2>
        <ul>
            <li>Your password is stored as a secure hash, not readable by administrators.</li>
            <li>Test data and logs are stored in a protected database accessible only by authorized personnel.</li>
            <li>We take reasonable steps to prevent unauthorized access or data leaks.</li>
        </ul>
    </section>

    <section>
        <h2>4. Sharing of Information</h2>
        <p>We do not sell or share your data with outside parties. Data may be shared only with:</p>
        <ul>
            <li>Institution administrators for academic evaluation.</li>
            <li>System maintainers for technical troubleshooting if required.</li>
        </ul>
    </section>

    <section>
        <h2>5. Cookies and Logs</h2>
        <p>The platform may use basic session cookies to maintain login states and prevent unauthorized access. These cookies do not track personal behaviour outside the platform.</p>
    </section>

    <section>
        <h2>6. Retention of Data</h2>
        <p>Test results and logs may be kept for academic, audit or security purposes. Account data may be deleted upon request, but test logs may be retained as required by institutional policy.</p>
    </section>

    <section>
        <h2>7. Your Rights</h2>
        <ul>
            <li>You may request correction of incorrect profile details.</li>
            <li>You may request deletion of your account by contacting the admin. (Some test records may remain.)</li>
            <li>You may ask what data is stored about you.</li>
        </ul>
    </section>

    <section>
        <h2>8. Changes to This Policy</h2>
        <p>We may update this Privacy Policy periodically. The updated version will appear on this page with a revised date. Continued use of the platform indicates acceptance of changes.</p>
    </section>

    <section>
        <h2>9. Contact</h2>
        <p>For privacy‑related questions or requests, contact: <a href="mailto:careerpathway2k25@gmail.com">careerpathway2k25@gmail.com</a>.</p>
    </section>

    <p>By using this platform you acknowledge that you understand and accept the terms of this Privacy Policy.</p>
</main>


</body>
</html>
