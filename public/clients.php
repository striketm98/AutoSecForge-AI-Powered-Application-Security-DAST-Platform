<?php
// ============================================================
// AutoSecForge – clients.php
// FIX ASF-004: Unrestricted File Upload – Extension-Only Validation
//
// BEFORE (vulnerable):
//   Only checked extension from the original filename.
//   Upload target was public/uploads/client-logos/ (inside webroot).
//   PHP webshells could be executed by the web server.
//
// AFTER (fixed):
//   1. MIME type validated server-side via finfo_file()
//   2. Image dimensions verified via getimagesize()
//   3. Files stored in storage/client-logos/ (OUTSIDE webroot)
//   4. Files served through a read-only proxy controller (serve-logo.php)
//   5. Random UUIDs used as filenames; original extension is dropped
//   6. Apache/Nginx must deny direct access to storage/ (see .htaccess)
// ============================================================

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Database.php';

requireLogin();

// ---- Configuration ----
const LOGO_UPLOAD_DIR    = __DIR__ . '/../../storage/client-logos/';   // OUTSIDE webroot
const LOGO_MAX_BYTES     = 2 * 1024 * 1024;   // 2 MB
const LOGO_ALLOWED_MIME  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const LOGO_ALLOWED_EXT   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Render the form (HTML omitted here – same as original minus the upload target change)
    include __DIR__ . '/../templates/clients_form.php';
    exit;
}

if (!verifyCsrfToken()) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$clientName = trim($_POST['client_name'] ?? '');
if ($clientName === '') {
    $_SESSION['flash_error'] = 'Client name is required.';
    header('Location: /clients.php');
    exit;
}

// ---- File upload handling ----
$logoPath = null;

if (isset($_FILES['client_logo']) && $_FILES['client_logo']['error'] !== UPLOAD_ERR_NO_FILE) {

    $file = $_FILES['client_logo'];

    // 1. PHP upload error check
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = 'Upload error (code ' . $file['error'] . ').';
        header('Location: /clients.php');
        exit;
    }

    // 2. File size
    if ($file['size'] > LOGO_MAX_BYTES) {
        $_SESSION['flash_error'] = 'Logo must be under 2 MB.';
        header('Location: /clients.php');
        exit;
    }

    // 3. ASF-004 FIX: Server-side MIME check via finfo (ignores the
    //    attacker-supplied filename and Content-Type header entirely).
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, LOGO_ALLOWED_MIME, true)) {
        $_SESSION['flash_error'] = 'Only JPEG, PNG, GIF, or WebP images are allowed.';
        header('Location: /clients.php');
        exit;
    }

    // 4. ASF-004 FIX: Verify it is actually an image (getimagesize reads
    //    image headers; a PHP file disguised as an image will fail here).
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $_SESSION['flash_error'] = 'Uploaded file is not a valid image.';
        header('Location: /clients.php');
        exit;
    }

    // 5. Derive a safe extension from the detected MIME type (never
    //    from the original filename).
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $safeExt = $mimeToExt[$mimeType];

    // 6. ASF-004 FIX: Random UUID filename – no user-supplied component.
    $newFilename = bin2hex(random_bytes(16)) . '.' . $safeExt;

    // 7. ASF-004 FIX: Move to storage directory OUTSIDE the webroot.
    if (!is_dir(LOGO_UPLOAD_DIR)) {
        mkdir(LOGO_UPLOAD_DIR, 0750, true);
    }
    $destPath = LOGO_UPLOAD_DIR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $_SESSION['flash_error'] = 'Could not save the uploaded file.';
        header('Location: /clients.php');
        exit;
    }

    $logoPath = $newFilename;   // Store only the filename in DB; full path never exposed
}

// ---- Persist to database ----
try {
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'INSERT INTO clients (name, logo_filename, created_by, created_at)
         VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$clientName, $logoPath, $_SESSION['user_id']]);

    $_SESSION['flash_success'] = 'Client added successfully.';
    header('Location: /clients.php');
    exit;

} catch (Throwable $e) {
    error_log('clients.php DB error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error – please try again.';
    header('Location: /clients.php');
    exit;
}
