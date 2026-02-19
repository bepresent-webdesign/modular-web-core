<?php
// For PHP built-in server: php -S localhost:8080 router.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve existing files (assets, uploads) as-is
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// Frontend
if (preg_match('#^/(impressum|datenschutz)(\.php)?$#', $uri, $m)) {
    $_GET['page'] = $m[1];
    require __DIR__ . '/index.php';
    return true;
}
if ($uri === '/') {
    $_GET['page'] = 'home';
    require __DIR__ . '/index.php';
    return true;
}

// Setup
if ($uri === '/setup' || $uri === '/setup/') {
    require __DIR__ . '/setup/index.php';
    return true;
}

// Admin
if (preg_match('#^/admin/(login|logout|passwort|content|media|backup|upload-api|images-api)\.php#', $uri, $m)) {
    require __DIR__ . '/admin/' . $m[1] . '.php';
    return true;
}
if ($uri === '/admin' || $uri === '/admin/') {
    require __DIR__ . '/admin/index.php';
    return true;
}

if ($uri === '/superadmin' || $uri === '/superadmin/') {
    require __DIR__ . '/superadmin/index.php';
    return true;
}

// 404
http_response_code(404);
echo '404 Not Found';
return true;