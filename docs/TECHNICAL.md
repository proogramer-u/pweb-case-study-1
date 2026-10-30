# Dokumentasi teknis Kopipiro

[Kembali ke README](../README.md)

Dokumen ini menjelaskan konfigurasi, struktur data, aturan bisnis, keamanan, dan cara pengujian. Untuk instalasi dasar dan panduan bergambar, mulai dari README.

## Struktur

```text
admin/       Dashboard, produk, kategori, kasir, pesanan, ekspor
cashier/     Pesanan yang ditugaskan dan perubahan status
customer/    Riwayat, detail, penyimpanan ulasan
auth/        Register, login, logout
cart/        Keranjang dan aksi POST
includes/    Bootstrap, domain uang/workflow, layout dan tampilan bersama
config/      Koneksi PDO melalui environment
database/    SQL instalasi/upgrade, CLI migrasi dan inisialisasi
tests/       Unit PHP dan acceptance HTTP/PDO dalam database sementara
docs/        PRD, rencana implementasi, hasil verifikasi
assets/      CSS, JS, Chart.js lokal dan foto bawaan proyek
uploads/products/ Gambar upload; eksekusi script diblokir Apache
vendor/      Dependensi Composer (tidak masuk Git)
```


## Konfigurasi lanjutan

### Konfigurasi database

`config/database.php` membaca environment berikut. Tidak ada parser `.env` otomatis.

| Variable | Default lokal |
| --- | --- |
| `DB_HOST` | `localhost` |
| `DB_NAME` | `kopipiro` |
| `DB_USER` | `root` |
| `DB_PASS` | kosong, mengikuti konfigurasi awal XAMPP |
| `DB_DSN` | opsional; menggantikan DSN, termasuk port/socket khusus |
| `APP_BASE` | terdeteksi dari path script; `/pweb-casestudy1` untuk XAMPP, kosong untuk document root |

Untuk PHP built-in server lokal:

```sh
DB_HOST=localhost DB_NAME=kopipiro APP_BASE='' php -S 127.0.0.1:8000
```

Environment pada terminal berlaku untuk proses CLI/server tersebut, **bukan** otomatis untuk Apache yang sudah berjalan. Atur environment Apache dalam konfigurasi virtual host lokal yang tidak dikomit, kemudian restart Apache. Jangan menaruh kredensial produksi dalam repository. Gunakan user database aplikasi dengan hak minimum; root tanpa password hanya untuk XAMPP lokal.

### Upgrade database yang sudah ada

Upgrade disiapkan untuk skema awal proyek: `categories(id,name)` dan `products(id,category_id,name,description,price,image,is_available,created_at)`, dengan ID `INT`, harga `DECIMAL(12,2)`, dan InnoDB.

1. Backup, misalnya `mysqldump -u root kopipiro > backup-kopipiro.sql`. Simpan backup di luar direktori publik atau direktori yang diblokir web.
2. Jalankan `php database/migrate.php` dari root proyek, atau import `database/upgrade.sql` melalui phpMyAdmin ke database yang benar. Keduanya memakai CREATE TABLE IF NOT EXISTS dan tidak menghapus/mengubah baris katalog yang ada.
3. Jika skema lokal berbeda dari skema awal di atas, periksa tipe kolom dan foreign key sebelum migrasi. Migrasi ini tidak menebak atau memperbaiki skema lain secara destruktif.
4. Buat akun owner melalui prosedur berikut. Migrasi tidak membuat akun berpassword default.

Pada pengerjaan 29 Oktober 2026, migrasi sudah diterapkan pada `kopipiro` lokal. Lima produk dan tiga kategori awal dipertahankan; tidak ada akun/pesanan demo dimasukkan ke database tersebut. Backup sebelum migrasi berada di `database/backups/before-upgrade-20261029.sql` dan diabaikan Git serta diblokir Apache.

Untuk instalasi **kosong** yang ingin memakai lima menu contoh awal proyek, jalankan `php database/seed_catalog.php`. Script menolak berjalan jika kategori atau produk sudah berisi data. Tidak perlu menjalankannya pada database yang sudah ada.


Pembuatan akun owner dan kasir dijelaskan pada [panduan akun di README](../README.md#akun-admin-dan-customer).

## Skema dan hubungan

- `categories` 1→N `products`.
- `users` menyimpan nama, email unik, hash password dan role owner/cashier/customer.
- `orders.user_id` nullable untuk tamu; `cashier_id` nullable sebelum ditugaskan. Keduanya mengarah ke users.
- `orders` menyimpan subtotal, diskon, total, status, nomor unik, checkout key unik yang di-hash, serta hash token akses tamu.
- `order_items` N→1 orders dan N→1 products; menyimpan snapshot nama/harga, jumlah dan total baris. Edit/nonaktifkan produk tidak mengubah riwayat.
- `reviews` menghubungkan user dan produk, unik per pasangan; nilai rating 1–5.
- Index pada pemilik pesanan, kasir, status/tanggal, tanggal, produk ulasan dan foreign key. Semua nilai uang disimpan sebagai DECIMAL, bukan FLOAT.

Produk dinonaktifkan dengan `is_available=0`; tidak dihapus secara fisik. Kategori yang digunakan produk tidak dapat dihapus. Foreign key menjaga riwayat pesanan tetap utuh.


## Hak akses dan implementasi

| Role | Hak |
| --- | --- |
| Tamu | Katalog, keranjang, checkout harga normal, konfirmasi dengan token session rahasia |
| Customer | Tambahan diskon 10%, riwayat miliknya, ulasan setelah completed |
| Cashier | Hanya orders dengan `cashier_id` sama dengan ID session; status berurutan |
| Owner | Seluruh pesanan, assignment, CRUD katalog, kategori/kasir, analytics dan ekspor |

`requireLogin()` dan `requireRole()` berjalan sebelum aksi terlindungi. Query detail/riwayat pelanggan mencakup `user_id`, query kasir mencakup `cashier_id`; mengubah URL atau hidden input tidak membuka pesanan lain. Owner saja dapat membuat akun kasir.

CRUD memakai PDO prepared statement dan validasi server: nama, harga positif, kategori yang ada, serta availability. Upload memakai `$_FILES`, MIME dari `finfo`, pemeriksaan gambar, JPG/PNG/WebP maksimal 2 MB dan 8000×8000, nama acak, `move_uploaded_file()`, cleanup file baru jika save gagal dan cleanup foto lama setelah penggantian sukses. Nama asli dan MIME browser tidak dipercaya.

Seluruh form mutasi menggunakan POST + CSRF; output HTML di-escape. Session menggunakan strict mode, cookie HttpOnly/SameSite, secure cookie saat HTTPS; ID diregenerasi setelah login. Logout menghapus session dan cookie. Pesan database ke pengguna bersifat umum; detail masuk error log server.


## Perhitungan diskon dan checkout

Harga selalu diambil ulang dari database. Browser hanya mengirim ID dan jumlah; field subtotal/diskon/total dari browser tidak digunakan.

Contoh: Rp25.000 × 2 + Rp20.000 × 1 = Rp70.000. Pelanggan mendapatkan diskon Rp7.000 dan membayar Rp63.000; tamu membayar Rp70.000. Perhitungan internal memakai integer sen; diskon dibulatkan half-up ke rupiah penuh. Harga satuan dan snapshot baris tetap menyimpan dua desimal.

Checkout mengunci produk dengan `FOR UPDATE`, memeriksa availability, menghitung total, menyimpan order dan seluruh item dalam **satu transaksi**, kemudian mengosongkan keranjang setelah commit. Kegagalan membatalkan semuanya dan mempertahankan keranjang. Order yang melampaui kapasitas DECIMAL ditolak tanpa merusak akses keranjang.

Checkout key acak per versi keranjang mempunyai unique constraint di database. POST ulang dari session yang berhak diarahkan ke order sebelumnya; refresh konfirmasi tidak membuat order baru. Penambahan/perubahan keranjang menghasilkan key baru. Nomor pesanan memakai tanggal dan nilai acak.

Token tamu acak 256-bit disimpan mentah hanya dalam session browser, hash-nya di database. Konfirmasi tidak bisa dibuka hanya dengan menebak ID. Tamu perlu mempertahankan session browser yang memesan; setelah logout/penghapusan cookie, tautan ID saja tidak cukup untuk memulihkan konfirmasi.


## Workflow pesanan

1. Checkout menghasilkan **pending**, tanpa kasir.
2. Owner membuka Pesanan → detail → pilih kasir → Simpan penugasan.
3. Kasir tersebut mengubah **pending → processing → ready → completed** secara berurutan.
4. Owner dapat menugaskan ulang pesanan yang masih pending/processing/ready dan membatalkan ketiga status itu. Kasir tidak dapat membatalkan atau self-assign.
5. Completed/cancelled bersifat terminal; tidak dapat ditugaskan ulang atau dibuka kembali.
6. Pelanggan melihat status dan dapat menulis/memperbarui ulasan setelah completed.

Perubahan status memakai row lock dan pengecekan role/assignment dalam query yang sama dengan pembacaan status. Status `completed` adalah indikator operasional; aplikasi tidak mencatat transaksi gateway.


## Dashboard dan ekspor

Dashboard memakai tanggal pembuatan order dalam WIB, rentang tanggal inklusif. Total pesanan, completed, pending dan pendapatan mengikuti filter; jumlah produk tersedia mencerminkan katalog saat ini. Pendapatan hanya menjumlahkan `orders.total_amount` untuk completed. Pesanan pending/processing/ready/cancelled tidak dihitung sebagai pendapatan. Chart.js disimpan lokal sehingga grafik tidak memerlukan CDN saat demo. Tabel data grafik tersedia untuk pembaca layar.

**Unduh Excel** menghasilkan XLSX asli melalui PhpSpreadsheet dengan delapan kolom sesuai PRD. Kolom nominal numerik dengan format Rp; kolom teks memakai tipe string eksplisit sehingga nama seperti `=SUM(...)` tidak menjadi formula. Filter tanggal sama dengan dashboard. Laporan memuat semua status dalam periode (kolom status dapat difilter di Excel); hanya metrik pendapatan dashboard yang membatasi completed.

Jika vendor belum diinstal, endpoint XLSX menampilkan HTTP 503 dengan instruksi dan tautan fallback CSV. CSV tidak diklaim sebagai XLSX; prefix berbahaya dinetralkan. Ekspor CSV juga dapat dipilih melalui `admin/export_excel.php?format=csv`.


## Pengujian otomatis

Jalankan dari root repository:

```sh
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/unit.php
python3 tests/run.py
composer validate --strict
composer audit
```

Jika executable PHP tidak ada di PATH, tambahkan PATH XAMPP di atas atau tetapkan `PHP_BIN=/path/to/php` untuk test runner Python. Python 3 hanya dipakai untuk tes HTTP, bukan backend.

`tests/run.py` membuat database acak bernama `kopipiro_test_*`, membuat fixture dengan password acak, menjalankan server di loopback port sementara, memeriksa aplikasi melalui HTTP nyata dan query PDO, kemudian menghentikan server dan menghapus **database test itu saja**. Jangan jalankan dua instance test suite bersamaan karena direktori upload dipakai bersama. User DB pengujian perlu hak CREATE/DROP DATABASE untuk prefix tersebut. Jangan arahkan `DB_DSN` ke database produksi; gunakan DB_HOST/DB_USER/DB_PASS untuk server test. Log lokal ada di `tests/.runtime/`, diabaikan Git.

Cakupan: autentikasi/role/CSRF/metode, checkout multi-produk tamu/pelanggan, forged totals, duplicate submission, guest token, customer/cashier isolation, status workflow, review eligibility/escaping, CRUD/foto asli/palsu/besar, cleanup foto saat DB gagal, rollback checkout saat item gagal, kategori/kasir, snapshot historis, cart invalid/unavailable/oversized, dashboard dan validitas/isi/filter XLSX/CSV. Tidak menambahkan data demo ke database utama.

Hasil aktual dan langkah manual: [docs/VERIFICATION.md](VERIFICATION.md).


## Batasan dan produksi

- Tidak ada gateway pembayaran, stok kuantitatif, email transaksional, reset password, ataupun refund. Ini workflow pemesanan offline sesuai PRD.
- Foto bawaan menggunakan aset yang sudah ada pada proyek untuk Americano, Latte dan Matcha; produk lainnya menampilkan placeholder sampai owner mengupload foto. Daftar menu/harga/analytics tetap dari MySQL.
- Tidak ada akun owner default. Inisialisasi CLI diperlukan sebelum demo manajemen.
- SQL additive ditujukan untuk skema awal yang diaudit; tidak mengubah kolom/tabel lama secara otomatis.
- Default konfigurasi ditujukan untuk XAMPP lokal. Untuk produksi: HTTPS, secure cookies, kredensial DB terpisah, `display_errors=Off`, direktori session privat, logging dan backup di luar web root, dan pembatasan login/rate limiting pada server/reverse proxy.
- Apache harus mengizinkan `.htaccess` (`AllowOverride` yang sesuai) untuk menolak akses metadata `.git`, config/includes/database/tests/docs, directory listing dan eksekusi script upload. Pada Nginx gunakan aturan setara. Jangan mengekspos PHP built-in server ke internet: server itu mengabaikan `.htaccess`.
- Tidak ada commit atau push yang dibuat oleh agen. Review dan commit/push dilakukan pemilik repository.


## Membaca kode

Mulai dari file berikut untuk memahami alurnya:

1. `config/database.php`: membuat koneksi PDO.
2. `includes/bootstrap.php`: memulai session dan menyediakan helper form, login, serta query.
3. `index.php`: mengambil produk, menerapkan filter, lalu menampilkan katalog.
4. `cart/add.php` dan `cart/index.php`: menyimpan ID/jumlah produk di session dan menampilkan keranjang.
5. `includes/domain.php`: aturan harga, diskon 10%, jumlah item, dan perpindahan status.
6. `checkout.php`: validasi pemesan, pengecekan order duplikat, transaksi database, lalu konfirmasi.
7. `admin/assign_order.php` dan `cashier/update_status.php`: penugasan owner dan pemrosesan oleh kasir.

Pada halaman PHP, pengambilan/validasi data berada di bagian atas; HTML berada di bawahnya. `e()` berarti escape HTML, `db()` menjalankan prepared statement, dan `requirePost()` memeriksa metode POST serta token CSRF. CSS dikelompokkan berdasarkan bagian halaman. JavaScript buatan proyek ada di `app.js` dan `dashboard.js`; `chart.umd.min.js` adalah library pihak ketiga.
