<?php
/**
 * FlowStack — Database Setup Utility
 * Visit: http://localhost/FlowStack/setup_db.php
 *
 * Reads /database/schema.sql and runs it against MySQL.
 * DELETE or restrict this file after first run in production.
 */

// ── Credentials (must match /backend/config/db.php) ──────────
$host    = 'localhost';
$user    = 'root';
$pass    = '';
$dbName  = 'flowstack';
$charset = 'utf8mb4';

$schemaFile = __DIR__ . '/database/schema.sql';

// ── Simple HTML wrapper ───────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FlowStack — Database Setup</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 700px; margin: 4rem auto; padding: 0 1.5rem; background:#f5f5f3; color:#2c2c2a; }
  h1   { color:#534AB7; }
  pre  { background:#1a1a2e; color:#e8e7f0; padding:1rem; border-radius:8px; overflow-x:auto; font-size:.85rem; }
  .ok  { color:#1D9E75; font-weight:600; }
  .err { color:#D85A30; font-weight:600; }
  .btn { display:inline-block; margin-top:1.5rem; padding:.6rem 1.4rem; background:#534AB7; color:#fff;
         border-radius:8px; text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<h1>⚡ FlowStack — DB Setup</h1>
<?php

if (!file_exists($schemaFile)) {
    echo '<p class="err">❌ Schema file not found at: ' . htmlspecialchars($schemaFile) . '</p>';
    echo '</body></html>';
    exit;
}

try {
    // Connect without selecting a DB first (schema creates it)
    $pdo = new PDO("mysql:host={$host};charset={$charset}", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sql      = file_get_contents($schemaFile);
    $stmts    = array_filter(array_map('trim', explode(';', $sql)));
    $executed = 0;
    $errors   = [];

    foreach ($stmts as $stmt) {
        if (empty($stmt) || strpos(ltrim($stmt), '--') === 0) continue;
        try {
            $pdo->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            // Skip "already exists" warnings; log real errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        echo '<p class="ok">✅ Database setup complete! ' . $executed . ' statements executed.</p>';
    } else {
        echo '<p class="err">⚠️ Completed with errors:</p><pre>' . htmlspecialchars(implode("\n", $errors)) . '</pre>';
    }

    // Verify tables
    $pdo->exec("USE {$dbName}");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo '<p><strong>Tables created:</strong> ' . implode(', ', array_map('htmlspecialchars', $tables)) . '</p>';

} catch (PDOException $e) {
    echo '<p class="err">❌ Connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Check credentials in this file and <code>/backend/config/db.php</code>.</p>';
}

echo '<a class="btn" href="frontend/login.html">→ Launch FlowStack</a>';
?>
</body>
</html>
