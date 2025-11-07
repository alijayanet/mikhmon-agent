<?php
/*
 * Profile Direct Links Manager
 * Show direct order links for each profile
 */

// Get session from URL
$session = $_GET['session'] ?? '';
if (empty($session)) {
    die('Session required');
}

// Get theme
$theme = 'default';
$themecolor = '#3a4149';
if (file_exists('../include/theme.php')) {
    include_once('../include/theme.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Direct Links - <?= htmlspecialchars($session); ?></title>
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="../img/favicon.png" />
    
    <style>
        :root {
            --primary-color: <?= $themecolor; ?>;
        }
        
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: var(--primary-color);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        
        .profile-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .profile-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        
        .profile-price {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .link-section {
            margin-bottom: 15px;
        }
        
        .link-label {
            font-weight: 600;
            color: #34495e;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .link-input {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .link-field {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            background: #f8f9fa;
        }
        
        .copy-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            background: #2c5282;
            transform: translateY(-1px);
        }
        
        .qr-section {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-top: 15px;
        }
        
        .qr-code {
            max-width: 150px;
            height: auto;
            border: 2px solid #ddd;
            border-radius: 6px;
        }
        
        .usage-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .usage-info h5 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        
        .usage-info ul {
            margin-bottom: 0;
            color: #1976d2;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .link-input {
                flex-direction: column;
            }
            
            .copy-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    
    <div class="header">
        <div class="container">
            <h1><i class="fa fa-link"></i> Profile Direct Links</h1>
            <p>Link langsung untuk order per profile - <?= htmlspecialchars($session); ?></p>
        </div>
    </div>
    
    <div class="container">
        
        <div class="usage-info">
            <h5><i class="fa fa-info-circle"></i> Cara Penggunaan</h5>
            <ul>
                <li><strong>Copy link</strong> dan paste di hotspot login page MikroTik</li>
                <li><strong>QR Code</strong> bisa di-scan customer untuk akses cepat</li>
                <li><strong>Link pendek</strong> untuk penggunaan internal</li>
                <li>Customer langsung ke form order tanpa pilih paket</li>
            </ul>
        </div>
        
        <div id="loading" class="text-center">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <p>Loading profile links...</p>
        </div>
        
        <div id="profile-links"></div>
        
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            loadProfileLinks();
        });
        
        function loadProfileLinks() {
            $.ajax({
                url: 'get_profile_links.php',
                method: 'GET',
                data: { session: '<?= htmlspecialchars($session); ?>' },
                dataType: 'json',
                success: function(response) {
                    $('#loading').hide();
                    
                    if (response.success) {
                        displayProfileLinks(response);
                    } else {
                        $('#profile-links').html('<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#loading').hide();
                    $('#profile-links').html('<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Failed to load profile links</div>');
                }
            });
        }
        
        function displayProfileLinks(data) {
            let html = '';
            
            data.links.forEach(function(link) {
                html += `
                    <div class="profile-card">
                        <div class="profile-header">
                            <h3 class="profile-name">${link.profile_name}</h3>
                            <div class="profile-price">Rp ${new Intl.NumberFormat('id-ID').format(link.price)}</div>
                        </div>
                        
                        <div class="link-section">
                            <div class="link-label"><i class="fa fa-globe"></i> Full URL (untuk hotspot login page)</div>
                            <div class="link-input">
                                <input type="text" class="link-field" value="${link.direct_link}" readonly>
                                <button class="copy-btn" onclick="copyToClipboard('${link.direct_link}', 'Full URL')">
                                    <i class="fa fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                        
                        <div class="link-section">
                            <div class="link-label"><i class="fa fa-link"></i> Short URL (untuk internal)</div>
                            <div class="link-input">
                                <input type="text" class="link-field" value="${link.short_link}" readonly>
                                <button class="copy-btn" onclick="copyToClipboard('${link.short_link}', 'Short URL')">
                                    <i class="fa fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                        
                        <div class="qr-section">
                            <div class="link-label"><i class="fa fa-qrcode"></i> QR Code</div>
                            <img src="${link.qr_code}" alt="QR Code" class="qr-code">
                            <br><small class="text-muted">Customer bisa scan untuk akses langsung</small>
                        </div>
                    </div>
                `;
            });
            
            $('#profile-links').html(html);
        }
        
        function copyToClipboard(text, type) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success feedback
                const btn = event.target.closest('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
                btn.style.background = '#27ae60';
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                }, 2000);
                
            }).catch(function() {
                alert('Failed to copy to clipboard');
            });
        }
    </script>
    
</body>
</html>
