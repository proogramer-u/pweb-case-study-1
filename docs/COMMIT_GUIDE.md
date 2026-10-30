# Commit dan push manual

Jalankan dari root repository. Commit menyimpan perubahan ke Git lokal; push mengirim commit itu ke GitHub.

## 1. Periksa perubahan

```sh
git status
```

File aplikasi, dokumentasi, screenshot, `composer.json`, `composer.lock`, `database/schema.sql`, dan `database/upgrade.sql` memang perlu disertakan. Konfigurasi PDO di `config/database.php` juga tetap disertakan: file itu membaca environment dan tidak menyimpan password database pribadi.

File berikut sudah diabaikan melalui `.gitignore`:

- `.env`, konfigurasi lokal rahasia, `auth.json`, dan private key.
- `vendor/`: dipasang ulang dengan `composer install`.
- Backup database serta SQL dump di luar dua file skema resmi.
- File upload pengguna, kecuali `.htaccess` dan `.gitkeep`.
- Log/hasil test, Python/Ruff cache, file editor/OS, dan catatan kerja internal.
- Laporan unduhan lokal dengan nama `kopipiro-*.csv` atau `kopipiro-*.xlsx`.

Aturan ignore tidak otomatis mengeluarkan file yang sudah terlanjur dilacak Git. Pada pemeriksaan persiapan ini, tidak ditemukan file terlacak yang cocok dengan aturan ignore. Jangan memakai `git add -f` untuk melewati aturan tersebut.

## 2. Masukkan file ke staging

```sh
git add .
git diff --cached --stat
git diff --cached --name-only
```

Periksa daftar tersebut. Backup database, `.env`, folder vendor, log test, dan upload pengguna tidak boleh muncul. File `schema.sql` dan `upgrade.sql` berisi struktur tabel, bukan salinan data akun/pesanan.

Untuk melihat isi perubahan teks yang akan disimpan:

```sh
git diff --cached
```

Jika salah memilih satu file, keluarkan dari staging tanpa menghapus file lokal:

```sh
git restore --staged -- path/ke/file
```

Ganti `path/ke/file` dengan nama file yang ingin dikeluarkan.

## 3. Buat commit

```sh
git commit -m "feat: complete Kopipiro ordering system and documentation"
```

## 4. Kirim ke GitHub

Remote yang diperiksa: `origin` → `https://github.com/proogramer-u/pweb-case-study-1.git`. Branch aktif: `main`.

```sh
git push origin main
git status
```

Setelah push berhasil, buka repository GitHub dan periksa README beserta gambarnya. Jika Git meminta autentikasi, gunakan alur login GitHub dari aplikasi/terminal; jangan menaruh token dalam URL remote atau source code.

Pemeriksaan ini mencakup pola kredensial pada source, aturan ignore, daftar calon file, serta email pada screenshot checkout. Ini bukan jaminan bahwa setiap jenis rahasia dapat dideteksi secara otomatis: tahap `git diff --cached` tetap merupakan pemeriksaan terakhir sebelum kamu menjalankan commit.
