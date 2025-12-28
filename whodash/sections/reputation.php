<?php
declare(strict_types=1);
session_start();

// --- Check login status ---
if (!isset($_SESSION['user_id'])) {
    // Not logged in: send 401 so SPA can load login fragment
    http_response_code(401);
    exit;
}

// --- Set headers for SPA fragment ---
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

// --- Output only the card fragment (no <html> shell) ---
?>
<div class="container">
    <!-- Page Card Start -->
    <section class="tab-content">
        <h1>Reputation</h1>
        <p>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'user', ENT_QUOTES, 'UTF-8') ?>!</p>

        <!--
      Add your custom page logic/content below.
      For example: graphs, tables, forms, etc.
    -->
        <!-- TODO: Insert page-specific content here -->

    </section>
    <!-- Page Card End -->
</div>