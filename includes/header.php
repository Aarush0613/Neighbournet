<?php
/**
 * NeighbourNet - Common Header Include
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'NeighbourNet — Hyperlocal Community Safety';
$userPreferences = ['language' => 'en', 'theme_mode' => 'system', 'colour_theme' => 'ocean-blue'];
if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../config/db.php';
        $prefQuery = getDBConnection()->prepare('SELECT language, theme_mode, colour_theme FROM user_preferences WHERE user_id = ?');
        $prefQuery->execute([(int)$_SESSION['user_id']]);
        $storedPreferences = $prefQuery->fetch(PDO::FETCH_ASSOC);
        if ($storedPreferences) $userPreferences = array_merge($userPreferences, $storedPreferences);
    } catch (Throwable $ignored) { /* Defaults keep the page usable during migration. */ }
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($userPreferences['language'] === 'hi' ? 'hi' : 'en') ?>" data-language="<?= htmlspecialchars($userPreferences['language']) ?>" data-theme-mode="<?= htmlspecialchars($userPreferences['theme_mode']) ?>" data-colour-theme="<?= htmlspecialchars($userPreferences['colour_theme']) ?>">
<div id="networkStatus" class="network-status" role="status" aria-live="polite" hidden>
  <span class="network-status-dot" aria-hidden="true"></span> You are offline. Changes will not reach NeighbourNet until you reconnect.
</div>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Meta and PWA -->
    <meta name="description" content="Report It. Verify It. Act Together. Hyperlocal neighbourhood issue-management and safety platform.">
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="/neighbournet/manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/neighbournet/assets/images/icon-192.png">
    <link rel="apple-touch-icon" href="/neighbournet/assets/images/icon-192.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Leaflet CSS for Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="/neighbournet/assets/css/style.css">
    <link rel="stylesheet" href="/neighbournet/assets/css/profile-settings.css">
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body class="d-flex flex-column min-vh-100 bg-light" data-theme-mode="<?= htmlspecialchars($userPreferences['theme_mode']) ?>" data-colour-theme="<?= htmlspecialchars($userPreferences['colour_theme']) ?>">
<div id="networkStatus" class="network-status" role="status" aria-live="polite" hidden>
  <span class="network-status-dot" aria-hidden="true"></span> You are offline. Changes will not reach NeighbourNet until you reconnect.
</div>
