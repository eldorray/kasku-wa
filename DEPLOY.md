# Deploy ke Hostinger (hPanel) via Git

Target: `https://kasku.fahmiealkhudhorie.site`
Strategi: clone repo di server, build assets di lokal, `git pull` manual saat update.

---

## A. Sekali setup (di lokal)

1. Build assets produksi & commit:
   ```bash
   npm run build
   git add public/build
   git commit -m "build: production assets"
   git push
   ```
   > Setiap kali ada perubahan CSS/JS/Blade yang dibundel Vite, ulangi langkah ini sebelum `git pull` di server.

2. Pastikan repo GitHub sudah terupdate:
   ```bash
   git push origin main
   ```

---

## B. Sekali setup (di Hostinger hPanel)

### B.1. Subdomain & SSL
1. **hPanel → Domains → Subdomains** → buat `kasku.fahmiealkhudhorie.site`.
2. Saat membuat, set **Document Root** ke: `/home/USER/domains/fahmiealkhudhorie.site/public_html/kasku/public`
   (artinya repo akan di-clone di `…/kasku`, dan domain langsung serve folder `public/`-nya).
3. **hPanel → Security → SSL** → install Let's Encrypt untuk subdomain itu.

### B.2. Database
1. **hPanel → Databases → MySQL Databases** → buat DB + user, catat:
   - Nama DB: `u123456_kasku`
   - User: `u123456_kasku`
   - Password: (generate kuat)
2. Assign user ke DB dengan **All Privileges**.

### B.3. SSH key untuk GitHub (private repo only)
> Lewati kalau repo sudah public.

1. **hPanel → Advanced → SSH Access** → enable SSH, catat host & port.
2. SSH ke server:
   ```bash
   ssh -p PORT USER@HOST
   ```
3. Generate key & tambahkan ke GitHub Deploy Keys:
   ```bash
   ssh-keygen -t ed25519 -C "hostinger-kasku" -f ~/.ssh/github_kasku -N ""
   cat ~/.ssh/github_kasku.pub
   ```
   Copy output → GitHub repo → **Settings → Deploy keys → Add** (read-only cukup).
4. Tambah ke `~/.ssh/config`:
   ```
   Host github-kasku
     HostName github.com
     User git
     IdentityFile ~/.ssh/github_kasku
     StrictHostKeyChecking no
   ```

### B.4. Clone repo
```bash
cd ~/domains/fahmiealkhudhorie.site/public_html
git clone https://github.com/eldorray/kasku-wa.git kasku
# atau pakai SSH alias di atas:
# git clone github-kasku:eldorray/kasku-wa.git kasku
cd kasku
```

### B.5. Setup `.env`
```bash
cp .env.production.example .env
nano .env
```
Isi: `DB_PASSWORD`, `FONNTE_TOKEN`, `MAIL_PASSWORD`. Sesuaikan `DB_DATABASE` & `DB_USERNAME` dengan prefix Hostinger Anda (`u123456_*`).

Generate APP_KEY:
```bash
php artisan key:generate
```

### B.6. Cek versi PHP & Composer
```bash
php -v        # harus 8.2+
composer -V   # harus ada
```
Kalau `composer` tidak ada, di Hostinger biasanya jalankan `composer-stable` atau install via:
```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar ~/bin/composer
export PATH=$HOME/bin:$PATH
```
Set PHP 8.2+ via **hPanel → Advanced → PHP Configuration**.

### B.7. First deploy
```bash
bash deploy.sh
```
Skrip ini akan: `git pull` → `composer install` → `migrate` → cache config/route/view → `storage:link` → set permission.

### B.8. Set Document Root (kalau B.1 belum)
Kalau Anda terlanjur set document root ke `…/kasku` (bukan `…/kasku/public`), buat `.htaccess` di `…/kasku/`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```
Tapi **lebih baik ubah Document Root** di hPanel agar langsung point ke `public/` — lebih aman.

---

## C. Webhook Fonnte

1. Login dashboard Fonnte → **Device → pilih device → Advanced**.
2. **URL Webhook**: `https://kasku.fahmiealkhudhorie.site/api/wa/webhook`
3. **Method**: POST
4. Save.

Tes manual dari mana saja:
```bash
curl -X POST https://kasku.fahmiealkhudhorie.site/api/wa/webhook \
  -H "Content-Type: application/json" \
  -d '{"sender":"628xxxxxxxxx","message":"saldo"}'
```
Harus respon 200 (atau 204). Kalau 403 → tiket ke Hostinger minta whitelist endpoint dari ModSecurity.

---

## D. Update rutin (workflow harian)

**Di lokal:**
```bash
# 1. coding
npm run build              # kalau ada perubahan CSS/JS/Blade
git add -A
git commit -m "feat: ..."
git push origin main
```

**Di server:**
```bash
ssh -p PORT USER@HOST
cd ~/domains/fahmiealkhudhorie.site/public_html/kasku
bash deploy.sh
```

Selesai.

---

## E. Cron (opsional tapi disarankan)

**hPanel → Advanced → Cron Jobs** → tambah:

```
* * * * * cd /home/USER/domains/fahmiealkhudhorie.site/public_html/kasku && php artisan schedule:run >> /dev/null 2>&1
```

Berguna untuk: cleanup invite expired, daily summary, dll (kalau nanti ditambahkan).

---

## F. Troubleshooting

| Gejala | Solusi |
|---|---|
| `500 Server Error` | Cek `storage/logs/laravel.log`. Biasanya `APP_KEY` kosong atau permission `storage/` salah. |
| `419 Page Expired` di webhook | Route `api/wa/webhook` belum di-exempt CSRF. Sudah di-handle di `bootstrap/app.php` repo ini. |
| Webhook Fonnte 403 | ModSecurity Hostinger memblokir. Buka tiket support, sebut endpoint-nya. |
| Asset (CSS/JS) 404 | `public/build/` belum ter-commit. Lakukan `npm run build && git add public/build && git push`, lalu `git pull` di server. |
| Migration gagal | Pastikan DB user punya `ALL PRIVILEGES`. Coba `php artisan migrate:status`. |
| Session error | `SESSION_DOMAIN` di `.env` harus `.fahmiealkhudhorie.site` (dengan titik depan). |
| HTTPS terdeteksi sebagai HTTP | Hostinger pakai proxy. Tambahkan `'*'` di `app/Http/Middleware/TrustProxies.php` (kalau ada) atau register `TrustProxies` di `bootstrap/app.php`. |
