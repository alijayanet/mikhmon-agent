# Responsive Tables Implementation Guide

## 📋 Overview

File `responsive-tables.css` menyediakan style universal untuk membuat semua tabel di agent-admin menjadi responsive dengan konsisten.

## 🚀 Quick Start

### 1. Include CSS File

Tambahkan di bagian `<head>` halaman Anda:

```html
<link rel="stylesheet" href="./css/responsive-tables.css">
```

### 2. Implementasi Dual-View Layout

#### Desktop Table View
```html
<!-- Desktop Table View -->
<div class="desktop-only">
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Column 1</th>
                    <th>Column 2</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $item['field1']; ?></td>
                    <td><?= $item['field2']; ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary">Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
```

#### Mobile Card View
```html
<!-- Mobile Card View -->
<div class="mobile-only">
    <?php foreach ($items as $item): ?>
    <div class="data-card">
        <div class="data-card-header">
            <div class="data-card-title">
                <?= $item['title']; ?>
            </div>
            <div class="data-card-badge">
                <?= $item['badge']; ?>
            </div>
        </div>
        
        <div class="data-row">
            <span class="data-label">Field 1:</span>
            <span class="data-value"><?= $item['field1']; ?></span>
        </div>
        
        <div class="data-row">
            <span class="data-label">Field 2:</span>
            <span class="data-value"><?= $item['field2']; ?></span>
        </div>
        
        <div class="data-actions">
            <button class="btn btn-sm btn-primary">Edit</button>
            <button class="btn btn-sm btn-danger">Delete</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
```

## 📦 Available Card Types

### 1. Generic Data Card (`.data-card`)
Untuk data umum seperti list items, settings, dll.

```html
<div class="data-card">
    <div class="data-card-header">
        <div class="data-card-title">Title</div>
        <div class="data-card-badge">Badge</div>
    </div>
    <div class="data-row">
        <span class="data-label">Label:</span>
        <span class="data-value">Value</span>
    </div>
    <div class="data-actions">
        <button class="btn">Action</button>
    </div>
</div>
```

### 2. Transaction Card (`.transaction-card`)
Untuk transaksi agent, pembayaran, dll.

```html
<div class="transaction-card">
    <div class="transaction-card-header">
        <div class="transaction-date">
            <i class="fa fa-calendar"></i> 14 Des 2024
        </div>
        <div class="transaction-amount amount-positive">
            +Rp 100.000
        </div>
    </div>
    <div class="transaction-row">
        <span class="transaction-label">Type:</span>
        <span class="transaction-value">Topup</span>
    </div>
</div>
```

### 3. Price Card (`.price-card`)
Untuk harga agent, pricing, dll.

```html
<div class="price-card">
    <div class="price-card-header">
        <div class="price-profile-name">
            <i class="fa fa-tag"></i> 3JAM
        </div>
        <div class="price-profit">
            +Rp 2.000
        </div>
    </div>
    <div class="price-row">
        <span class="price-label">Harga Beli:</span>
        <span class="price-value">Rp 5.000</span>
    </div>
    <div class="price-actions">
        <button class="btn btn-warning">Edit</button>
        <button class="btn btn-danger">Delete</button>
    </div>
</div>
```

### 4. Customer Card (`.customer-card`)
Untuk data pelanggan, billing customers, dll.

```html
<div class="customer-card">
    <div class="customer-card-header">
        <div class="customer-name">
            <i class="fa fa-user"></i> John Doe
        </div>
        <div class="customer-status status-active">
            ACTIVE
        </div>
    </div>
    <div class="data-row">
        <span class="data-label">Phone:</span>
        <span class="data-value">081234567890</span>
    </div>
</div>
```

### 5. Method Card (`.method-card`)
Untuk payment methods, gateway config, dll.

```html
<div class="method-card">
    <div class="method-card-header">
        <div class="method-name">
            <i class="fa fa-credit-card"></i> QRIS
        </div>
        <div class="method-type">E-Wallet</div>
    </div>
    <div class="data-row">
        <span class="data-label">Fee:</span>
        <span class="data-value">Rp 1.000</span>
    </div>
</div>
```

### 6. Sale Card (`.sale-card`)
Untuk public sales, voucher sales, dll.

```html
<div class="sale-card">
    <div class="sale-card-header">
        <div class="sale-reference">
            #REF-12345
        </div>
        <div class="sale-amount">
            Rp 50.000
        </div>
    </div>
    <div class="data-row">
        <span class="data-label">Product:</span>
        <span class="data-value">3JAM</span>
    </div>
</div>
```

## 🎨 Utility Classes

### Status Badges
```html
<span class="badge-status status-success">SUCCESS</span>
<span class="badge-status status-pending">PENDING</span>
<span class="badge-status status-failed">FAILED</span>
<span class="badge-status status-paid">PAID</span>
<span class="badge-status status-unpaid">UNPAID</span>
<span class="badge-status status-overdue">OVERDUE</span>
```

### Text Colors
```html
<span class="text-success">Success Text</span>
<span class="text-danger">Danger Text</span>
<span class="text-warning">Warning Text</span>
<span class="text-muted">Muted Text</span>
```

### Amount Colors
```html
<span class="amount-positive">+Rp 100.000</span>
<span class="amount-negative">-Rp 50.000</span>
```

### Other Utilities
```html
<span class="font-mono">SERIAL123</span>
<span class="nowrap">No Wrap Text</span>
```

## 📱 Responsive Breakpoints

| Breakpoint | Behavior |
|------------|----------|
| **> 768px** | Desktop table view visible, mobile cards hidden |
| **≤ 768px** | Mobile cards visible, desktop table hidden |
| **≤ 480px** | Stacked headers, full-width buttons |

## ✅ Implementation Checklist

- [ ] Include `responsive-tables.css` in page
- [ ] Wrap existing table with `<div class="desktop-only">`
- [ ] Create mobile card view with `<div class="mobile-only">`
- [ ] Choose appropriate card type (data-card, transaction-card, etc.)
- [ ] Add card header with title/badge
- [ ] Add data rows for each field
- [ ] Add action buttons if needed
- [ ] Test on mobile devices (< 768px)
- [ ] Test on small mobile (< 480px)

## 🔧 Customization

Jika perlu custom styling, tambahkan di file page-specific:

```css
/* Custom styles for specific page */
@media (max-width: 768px) {
    .my-custom-card {
        /* Custom mobile styles */
    }
}
```

## 📝 Example Files

Lihat implementasi lengkap di:
- `agent_transactions.php` - Transaction cards
- `agent_prices.php` - Price cards

## 🎯 Best Practices

1. **Konsistensi**: Gunakan card type yang sama untuk data yang sama
2. **Icons**: Tambahkan icon untuk visual clarity
3. **Colors**: Gunakan color-coding untuk status/amounts
4. **Actions**: Letakkan action buttons di bagian bawah card
5. **Spacing**: Gunakan standard spacing dari CSS
6. **Testing**: Selalu test di berbagai screen sizes

## 🚨 Common Issues

### Issue: Desktop table masih muncul di mobile
**Solution**: Pastikan wrapper `<div class="desktop-only">` ada

### Issue: Mobile cards tidak muncul
**Solution**: Pastikan wrapper `<div class="mobile-only">` ada dan CSS sudah di-include

### Issue: Buttons terlalu kecil di mobile
**Solution**: Akan otomatis full-width di < 480px jika menggunakan `.data-actions`

### Issue: Text overflow di mobile
**Solution**: Gunakan class `.nowrap` untuk field yang perlu no-wrap

## 📞 Support

Jika ada pertanyaan atau issue, silakan hubungi development team.
