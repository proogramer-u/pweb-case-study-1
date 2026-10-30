# Berkas dibuat atau diubah

Daftar working tree pada akhir implementasi; tidak ada commit/push.

| Status | Berkas |
| --- | --- |
| Diubah | `README.md` |
| Diubah | `admin/create_product.php` |
| Diubah | `admin/delete_product.php` |
| Diubah | `admin/edit_product.php` |
| Diubah | `admin/products.php` |
| Diubah | `admin/upload_helper.php` |
| Diubah | `assets/css/style.css` |
| Diubah | `config/database.php` |
| Diubah | `index.php` |
| Diubah | `products.php` |
| Baru | `.gitignore` |
| Baru | `.htaccess` |
| Baru | `admin/assign_order.php` |
| Baru | `admin/cashiers.php` |
| Baru | `admin/categories.php` |
| Baru | `admin/dashboard.php` |
| Baru | `admin/export_excel.php` |
| Baru | `admin/order_detail.php` |
| Baru | `admin/orders.php` |
| Baru | `admin/product_form.php` |
| Baru | `assets/images/americano.jpeg` |
| Baru | `assets/images/hero.jpeg` |
| Baru | `assets/images/latte.jpeg` |
| Baru | `assets/images/matcha.jpeg` |
| Baru | `assets/images/placeholder.svg` |
| Baru | `assets/js/app.js` |
| Baru | `assets/js/chart.umd.min.js` |
| Baru | `assets/js/dashboard.js` |
| Baru | `auth/login.php` |
| Baru | `auth/logout.php` |
| Baru | `auth/register.php` |
| Baru | `cart/add.php` |
| Baru | `cart/index.php` |
| Baru | `cart/remove.php` |
| Baru | `cart/update.php` |
| Baru | `cashier/dashboard.php` |
| Baru | `cashier/order_detail.php` |
| Baru | `cashier/update_status.php` |
| Baru | `checkout.php` |
| Baru | `composer.json` |
| Baru | `composer.lock` |
| Baru | `config/.htaccess` |
| Baru | `customer/order_detail.php` |
| Baru | `customer/orders.php` |
| Baru | `customer/review.php` |
| Baru | `database/.htaccess` |
| Baru | `database/migrate.php` |
| Baru | `database/schema.sql` |
| Baru | `database/seed.php` |
| Baru | `database/seed_catalog.php` |
| Baru | `database/upgrade.sql` |
| Baru | `docs/.htaccess` |
| Baru | `docs/CHANGES.md` |
| Baru | `docs/PRD.md` |
| Baru | `docs/VERIFICATION.md` |
| Baru | `docs/screenshots/storefront.jpg` |
| Baru | `includes/.htaccess` |
| Baru | `includes/bootstrap.php` |
| Baru | `includes/domain.php` |
| Baru | `includes/footer.php` |
| Baru | `includes/header.php` |
| Baru | `includes/order_detail.php` |
| Baru | `includes/order_list.php` |
| Baru | `order_success.php` |
| Baru | `product.php` |
| Baru | `tests/.htaccess` |
| Baru | `tests/acceptance.py` |
| Baru | `tests/browser-checks.md` |
| Baru | `tests/db.php` |
| Baru | `tests/run.py` |
| Baru | `tests/unit.php` |
| Baru | `uploads/products/.gitkeep` |
| Baru | `uploads/products/.htaccess` |

Perapian keterbacaan 30 Oktober 2026 juga menambahkan `.editorconfig` (indentasi empat spasi) dan petunjuk membaca kode pada README.

## Panduan bergambar — 30 Oktober 2026

- README ditata berdasarkan fitur, penggunaan per role, instalasi, akun, struktur kode, dan pengujian.
- Detail konfigurasi, keamanan, skema, dan aturan bisnis tersedia di `docs/TECHNICAL.md`.
- Sebelas screenshot aplikasi ditambahkan di `docs/screenshots/`: tamu (katalog/keranjang/checkout), login, customer (katalog/checkout/riwayat), dan admin (dashboard/produk/form edit/pesanan).
- Login menggunakan akun lokal yang disediakan; password tidak disimpan dalam dokumentasi. Tidak ada order atau produk baru dibuat saat dokumentasi.
- Pemeriksaan: semua tautan lokal dan 11 referensi screenshot tersedia, code fence/details berpasangan, serta `git diff --check` lulus.

## Persiapan commit manual

- Aturan ignore diperluas untuk environment, konfigurasi rahasia, private key, backup/dump, laporan lokal, cache, editor, dan catatan internal.
- File schema/upgrade SQL serta Composer lock tetap disertakan agar proyek bisa dipasang ulang.
- Screenshot checkout customer diganti memakai akun contoh `customer@example.test` dari database sementara; akun dan database utama tidak diubah.
- Tidak ada staging, commit, atau push yang dijalankan.
