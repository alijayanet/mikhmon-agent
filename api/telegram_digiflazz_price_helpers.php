<?php
/**
 * Telegram Digiflazz Price List Helpers
 * Functions untuk menampilkan daftar harga dan pencarian produk Digiflazz di Telegram
 */

/**
 * Show Digiflazz price list by category for Telegram
 */
function showTelegramDigiflazzPriceList($chatId, $category) {
    if (!function_exists('getDBConnection')) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nDatabase tidak tersedia.");
        return;
    }
    
    try {
        $db = getDBConnection();
        
        // Get products by category
        $stmt = $db->prepare("
            SELECT buyer_sku_code, product_name, price, seller_price, brand
            FROM digiflazz_products 
            WHERE category LIKE :category 
            AND status = 'active'
            AND buyer_product_status = 'active'
            ORDER BY brand, price ASC
            LIMIT 50
        ");
        $stmt->execute([':category' => "%{$category}%"]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($products)) {
            sendTelegramMessage($chatId, "ℹ️ *TIDAK ADA PRODUK*\n\nKategori: {$category}\n\nBelum ada produk dalam kategori ini.");
            return;
        }
        
        // Format message
        $message = "💰 *DAFTAR HARGA {$category}*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $currentBrand = '';
        foreach ($products as $product) {
            // Group by brand
            if ($currentBrand !== $product['brand']) {
                if ($currentBrand !== '') {
                    $message .= "\n";
                }
                $currentBrand = $product['brand'];
                $message .= "📱 *{$currentBrand}*\n";
            }
            
            $sku = $product['buyer_sku_code'];
            $name = $product['product_name'];
            $price = (int)($product['seller_price'] ?: $product['price']);
            $priceFormatted = number_format($price, 0, ',', '.');
            
            $message .= "• `{$sku}` - Rp {$priceFormatted}\n";
            $message .= "  {$name}\n";
        }
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📝 *Cara Order:*\n";
        $message .= "Ketik: `<SKU> <NOMOR>`\n";
        $message .= "Contoh: `{$products[0]['buyer_sku_code']} 081234567890`\n\n";
        $message .= "💡 Ketik *PRODUK DIGIFLAZZ* untuk lihat semua kategori";
        
        sendTelegramMessage($chatId, $message);
        
    } catch (Exception $e) {
        error_log("Error in showTelegramDigiflazzPriceList: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ *ERROR*\n\nGagal mengambil daftar harga.\n\n" . $e->getMessage());
    }
}

/**
 * Show all Digiflazz categories for Telegram
 */
function showTelegramDigiflazzCategories($chatId) {
    if (!function_exists('getDBConnection')) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nDatabase tidak tersedia.");
        return;
    }
    
    try {
        $db = getDBConnection();
        
        // Get all categories with product count
        $stmt = $db->query("
            SELECT category, COUNT(*) as total
            FROM digiflazz_products 
            WHERE status = 'active'
            AND buyer_product_status = 'active'
            AND category IS NOT NULL
            AND category != ''
            GROUP BY category
            ORDER BY category ASC
        ");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($categories)) {
            sendTelegramMessage($chatId, "ℹ️ *TIDAK ADA PRODUK*\n\nBelum ada produk Digiflazz yang tersedia.");
            return;
        }
        
        // Format message
        $message = "📦 *KATEGORI PRODUK DIGIFLAZZ*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        
        foreach ($categories as $cat) {
            $category = $cat['category'];
            $total = $cat['total'];
            
            // Icon based on category
            $icon = '📱';
            if (stripos($category, 'data') !== false) $icon = '📶';
            elseif (stripos($category, 'game') !== false) $icon = '🎮';
            elseif (stripos($category, 'money') !== false) $icon = '💳';
            elseif (stripos($category, 'pln') !== false) $icon = '⚡';
            elseif (stripos($category, 'voucher') !== false) $icon = '🎫';
            
            $message .= "{$icon} *{$category}* ({$total} produk)\n";
        }
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📝 *Cara Lihat Harga:*\n\n";
        $message .= "• HARGA PULSA\n";
        $message .= "• HARGA DATA\n";
        $message .= "• HARGA EMONEY\n";
        $message .= "• HARGA GAME\n";
        $message .= "• HARGA PLN\n\n";
        $message .= "🔍 *Cari Produk:*\n";
        $message .= "Ketik: `CARI <keyword>`\n";
        $message .= "Contoh: `CARI telkomsel`";
        
        sendTelegramMessage($chatId, $message);
        
    } catch (Exception $e) {
        error_log("Error in showTelegramDigiflazzCategories: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ *ERROR*\n\nGagal mengambil kategori.\n\n" . $e->getMessage());
    }
}

/**
 * Search Digiflazz products by keyword for Telegram
 */
function searchTelegramDigiflazzProducts($chatId, $keyword) {
    if (!function_exists('getDBConnection')) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nDatabase tidak tersedia.");
        return;
    }
    
    try {
        $db = getDBConnection();
        
        // Search products
        $stmt = $db->prepare("
            SELECT buyer_sku_code, product_name, category, price, seller_price, brand
            FROM digiflazz_products 
            WHERE (
                product_name LIKE :keyword1 
                OR buyer_sku_code LIKE :keyword2
                OR brand LIKE :keyword3
                OR category LIKE :keyword4
            )
            AND status = 'active'
            AND buyer_product_status = 'active'
            ORDER BY brand, price ASC
            LIMIT 20
        ");
        $searchTerm = "%{$keyword}%";
        $stmt->execute([
            ':keyword1' => $searchTerm,
            ':keyword2' => $searchTerm,
            ':keyword3' => $searchTerm,
            ':keyword4' => $searchTerm
        ]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($products)) {
            sendTelegramMessage($chatId, "🔍 *PENCARIAN: {$keyword}*\n\nTidak ada produk yang ditemukan.\n\nCoba kata kunci lain atau ketik *PRODUK DIGIFLAZZ* untuk lihat semua kategori.");
            return;
        }
        
        // Format message
        $message = "🔍 *HASIL PENCARIAN: {$keyword}*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Ditemukan " . count($products) . " produk\n\n";
        
        foreach ($products as $product) {
            $sku = $product['buyer_sku_code'];
            $name = $product['product_name'];
            $category = $product['category'];
            $price = (int)($product['seller_price'] ?: $product['price']);
            $priceFormatted = number_format($price, 0, ',', '.');
            
            $message .= "📦 *{$name}*\n";
            $message .= "   SKU: `{$sku}`\n";
            $message .= "   Kategori: {$category}\n";
            $message .= "   Harga: Rp {$priceFormatted}\n\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📝 *Cara Order:*\n";
        $message .= "Ketik: `<SKU> <NOMOR>`\n";
        $message .= "Contoh: `{$products[0]['buyer_sku_code']} 081234567890`";
        
        sendTelegramMessage($chatId, $message);
        
    } catch (Exception $e) {
        error_log("Error in searchTelegramDigiflazzProducts: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ *ERROR*\n\nGagal mencari produk.\n\n" . $e->getMessage());
    }
}

?>
