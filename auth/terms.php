<?php
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set('Asia/Kolkata');
$last_updated = '2025-12-05'; // update as needed
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Terms &amp; Conditions — Career Pathway</title>
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
    <h1>Terms &amp; Conditions</h1>
    <div class="meta">Last updated: <?= e($last_updated) ?></div>

    <p>Welcome to <strong>Career Pathway</strong>. These Terms &amp; Conditions ("Terms") govern your use of the website and services provided by Career Pathway, including the MCQ testing and practice platform used by student clubs and participants. By creating an account, registering for tests, or otherwise using the service you agree to these Terms.</p>

    <section>
        <h2>1. Purpose</h2>
        <p>The platform offers multiple-choice tests, practice quizzes, score tracking, and related academic features for students and club activities.</p>
    </section>

    <section>
        <h2>2. Eligibility</h2>
        <p>Only individuals who are authorized by their institution or club (for example, enrolled students) may register. By registering you confirm you are eligible to use the platform.</p>
    </section>

    <section>
        <h2>3. Account and Security</h2>
        <ul>
            <li>You are responsible for keeping your account credentials secure.</li>
            <li>Do not share your password or allow others to use your account.</li>
            <li>If you suspect unauthorized access, contact the administrator immediately.</li>
            <li>We may suspend or remove accounts that pose a security risk.</li>
        </ul>
    </section>

    <section>
        <h2>4. User Responsibilities and Test Integrity</h2>
        <ul>
            <li>Provide accurate registration information (name, roll number, email, class, batch).</li>
            <li>Follow test rules and timings. Use only permitted resources during a test.</li>
            <li>Do not copy, share, or publish test questions, answers, or other restricted content.</li>
            <li>No multiple accounts to gain unfair advantage. Violation may lead to score cancellation or account suspension.</li>
        </ul>
    </section>

    <section>
        <h2>5. Data and Privacy</h2>
        <p>We collect and store basic information such as your name, roll number, email address, test results, and activity logs to provide and improve the service. This data is used for academic evaluation and administration. For details on how we store and process personal data, see our <a href="<?= e(BASE_URL) ?>/auth/privacy.php">Privacy Policy</a>.</p>
    </section>

    <section>
        <h2>6. Admin Rights</h2>
        <p>Administrators may modify tests, reset scores, restrict access, or remove accounts for rule violations, suspected cheating, or other policy breaches.</p>
    </section>

    <section>
        <h2>7. Content and Intellectual Property</h2>
        <p>Test questions, UI, and other original content on this platform are the property of the institution or the platform. You may use content only for permitted educational purposes and not reproduce or distribute it without permission.</p>
    </section>

    <section>
        <h2>8. Limitation of Liability</h2>
        <p>To the extent permitted by law, Career Pathway and its administrators are not liable for indirect, incidental, special or consequential damages arising from use of the platform. We are not responsible for losses caused by internet outages, device failures, or user-side problems during a test.</p>
    </section>

    <section>
        <h2>9. Termination</h2>
        <p>We may terminate or suspend access to the service for any user who violates these Terms. Users may close their accounts by contacting the administrator; some records (such as test logs) may be retained for academic or legal reasons.</p>
    </section>

    <section>
        <h2>10. Changes to Terms</h2>
        <p>We may update these Terms from time to time. Updated Terms will be posted on this page with a revised "Last updated" date. Continued use after changes means acceptance of the new Terms.</p>
    </section>

    <section>
        <h2>11. Contact</h2>
        <p>If you have questions about these Terms, contact the administrator at <a href="mailto:careerpathway2k25@gmail.com">careerpathway2k25@gmail.com</a>.</p>
    </section>

    <p>By using this platform you acknowledge that you have read, understood, and agree to these Terms &amp; Conditions.</p>
</main>


</body>
</html>