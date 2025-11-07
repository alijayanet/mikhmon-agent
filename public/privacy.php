<?php
/*
 * Privacy Policy Page
 */

include_once('../include/db_config.php');

// Get theme from MikhMon config
$theme = 'default';
$themecolor = '#3a4149';
if (file_exists('../include/theme.php')) {
    include_once('../include/theme.php');
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM site_pages WHERE page_slug = 'privacy' AND is_active = 1");
    $stmt->execute();
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        $page = [
            'page_title' => 'Kebijakan Privasi',
            'page_content' => '<p>Halaman ini belum dikonfigurasi.</p>'
        ];
    }
} catch (Exception $e) {
    $page = [
        'page_title' => 'Kebijakan Privasi',
        'page_content' => '<p>Error loading page.</p>'
    ];
}

// Use same template as TOS
include('tos.php');
?>
