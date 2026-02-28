<?php
// ─── Database ────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'flor_de_tango');
define('DB_USER', 'flor_user');          // À modifier
define('DB_PASS', 'VotreMotDePasse');    // À modifier

// ─── Admin ───────────────────────────────────────────────────────────────────
define('ADMIN_PASSWORD', 'ChangeMe2026!');            // À modifier
define('ADMIN_EMAIL',    'celine79.baquet@yahoo.com');
define('SITE_NAME',      'Flor de Tango');
define('SITE_URL',       'https://votredomaine.fr');  // À modifier

// ─── Envoi d'e-mail (expéditeur) ─────────────────────────────────────────────
// Doit correspondre à un domaine autorisé par votre hébergeur
define('MAIL_FROM', 'noreply@votredomaine.fr');       // À modifier

// ─── Upload ──────────────────────────────────────────────────────────────────
define('UPLOAD_DIR',   __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);   // 5 Mo

define('ALLOWED_MIME', [
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
]);

define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
