<?php
/*
 * Terms of Service Page
 */

include_once('../include/db_config.php');

// Get theme from MikhMon config
$theme = 'default';
$themecolor = '#3a4149';
if (file_exists('../include/theme.php')) {
    include_once('../include/theme.php');
}

// Get TOS content from database
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM site_pages WHERE page_slug = 'tos' AND is_active = 1");
    $stmt->execute();
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        $page = [
            'page_title' => 'Syarat dan Ketentuan',
            'page_content' => '<p>Halaman ini belum dikonfigurasi.</p>'
        ];
    }
} catch (Exception $e) {
    $page = [
        'page_title' => 'Syarat dan Ketentuan',
        'page_content' => '<p>Error loading page.</p>'
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page['page_title']); ?></title>
    
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="../img/favicon.png" />
    
    <style>
        body {
            background: #f5f5f5;
            padding: 20px 0;
        }
        
        .container {
            max-width: 900px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .content-box h3 {
            color: #667eea;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        
        .content-box h4 {
            color: #333;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .content-box ul {
            padding-left: 20px;
        }
        
        .content-box li {
            margin-bottom: 8px;
        }
        
        .back-link {
            text-align: center;
            margin: 20px 0;
        }
        
        .back-link a {
            color: white;
            background: #667eea;
            padding: 10px 30px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-link a:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    
    <div class="container">
        
        <div class="page-header">
            <h1><i class="fa fa-file-text-o"></i> <?= htmlspecialchars($page['page_title']); ?></h1>
        </div>
        
        <div class="content-box">
            <?= $page['page_content']; ?>
        </div>
        
        <div class="back-link">
            <a href="javascript:history.back()">
                <i class="fa fa-arrow-left"></i> Kembali
            </a>
        </div>
        
    </div>
    
</body>
</html>
