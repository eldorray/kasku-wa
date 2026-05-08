# Fonnte WhatsApp Integration — Dev Setup

Panduan integrasi Fonnte untuk development. Tidak perlu deploy — pakai ngrok untuk expose localhost.

## Prasyarat

- Akun Fonnte (https://fonnte.com)
- Nomor WhatsApp aktif (untuk di-link ke Fonnte device)
- ngrok terinstall (sudah ada di mesin Anda: `/opt/homebrew/bin/ngrok`)
- ngrok auth token (dari https://dashboard.ngrok.com/get-started/your-authtoken — gratis, sekali setup)

---

## Step 1 — Dapat Fonnte token

1. Login ke https://fonnte.com → menu **Devices**.
2. Klik **Add Device** → isi nama (mis. "Kasku Dev") → **Connect**.
3. Scan QR code dengan WhatsApp di HP Anda (Settings → Linked Devices → Link a Device).
4. Setelah connected, copy **Token** dari kolom Token di tabel Devices. Format ±32 karakter alfanumerik.

> ⚠️ **Jangan share token ke siapapun, termasuk LLM/AI assistant.** Token ini = akses kirim WA atas nama nomor Anda.

## Step 2 — Isi .env (Anda sendiri)

Edit `/Users/elfahmie/Documents/Coding/kasku-wa/.env`:

```env
WA_PROVIDER=fonnte
WA_WEBHOOK_TOKEN=kasku-dev-secret      # bisa Anda ganti string acak apapun
WA_SEND_REAL=true                       # ⚠️ ubah ke true agar kirim WA real
FONNTE_TOKEN=PASTE_TOKEN_DARI_FONNTE_DI_SINI
FONNTE_SEND_URL=https://api.fonnte.com/send
```

Lalu clear cache config:

```bash
php artisan config:clear
```

## Step 3 — Update nomor WhatsApp Anda di Kasku

Login ke aplikasi (`rama@kasku.test` / `password`) → **Pengaturan** → **Profil** → isi field **Nomor WhatsApp** dengan nomor WA Anda (yang barusan di-link ke Fonnte). Format apa saja boleh: `+62…`, `62…`, `081…` — sistem akan normalisasi.

> Default seeder mengisi `+6281287314422`. Ganti ke nomor real Anda.

## Step 4 — Test OUTGOING (kirim WA dari Kasku)

Tanpa perlu ngrok, langsung test apakah token Anda valid + device aktif:

```bash
php artisan kasku:wa:send-test 6281xxxxxxxxx "Halo dari Kasku 👋"
```

Ganti `6281xxxxxxxxx` dengan nomor target (boleh nomor sendiri). HP Anda harusnya menerima pesan WA dari nomor device yang sudah di-link.

Output sukses contoh:
```json
{
    "ok": true,
    "mode": "fonnte",
    "status": 200,
    "body": { "detail": "success", "process": "pending", ... }
}
```

Kalau gagal (`status: 401`): token salah/kadaluarsa.
Kalau `process: rejected`: device tidak aktif (cek di dashboard Fonnte, scan QR ulang).

## Step 5 — Setup ngrok untuk INCOMING (webhook)

Di terminal **terpisah**, jalankan tunneling:

```bash
# Sekali saja, kalau belum pernah setup ngrok:
ngrok config add-authtoken YOUR_NGROK_AUTH_TOKEN

# Setiap kali development:
ngrok http 8000
```

Akan muncul output seperti:
```
Forwarding   https://abc123.ngrok-free.app -> http://localhost:8000
```

**Copy URL `https://abc123.ngrok-free.app`** — itu URL publik Anda.

> URL ngrok berubah setiap restart (kecuali Anda subscribe paid plan dengan static domain). Setiap kali ngrok restart, ulangi Step 6.

## Step 6 — Daftar webhook URL di Fonnte

1. Dashboard Fonnte → menu **Devices** → klik device Anda → tab **Incoming Webhook** atau **Setting**.
2. Isi URL:
   ```
   https://abc123.ngrok-free.app/api/wa/webhook?token=kasku-dev-secret
   ```
   Ganti `abc123.ngrok-free.app` dengan URL ngrok Anda, dan `kasku-dev-secret` dengan nilai `WA_WEBHOOK_TOKEN` di `.env` Anda.
3. **Save**.

## Step 7 — Test full flow

Pastikan dua terminal jalan:
- Terminal 1: `php artisan serve` (port 8000)
- Terminal 2: `ngrok http 8000`

Lalu dari HP Anda, **kirim WA ke nomor device Fonnte Anda** dengan pesan:

```
kopi tuku 28rb
```

Yang akan terjadi:
1. Fonnte terima pesan → POST ke `https://abc123.ngrok-free.app/api/wa/webhook?token=…`
2. Laravel app receive → `WhatsappBot::handleIncoming()` → parser → simpan transaksi → format reply.
3. `FonnteClient::send()` POST balik ke Fonnte → bot reply masuk ke WA Anda.
4. Buka `/chat` di browser → bubble pesan in/out muncul real (refresh atau tunggu 5 detik untuk poll).
5. Buka `/transaksi` → transaksi baru sudah tercatat dengan `via=wa`.

Coba juga commands:
- `/help` — daftar perintah
- `/saldo` — total saldo
- `/laporan` — ringkasan bulan ini
- `/budget` — status budget

## Troubleshooting

### Webhook tidak terpanggil

- Cek di terminal ngrok: ada inbound request? Kalau tidak, Fonnte belum kirim — pastikan webhook URL sudah di-save di dashboard Fonnte.
- Buka `https://abc123.ngrok-free.app/api/wa/webhook?token=kasku-dev-secret` di browser → harusnya 405 (Method Not Allowed, GET tidak di-support, tapi route ada).

### Webhook dipanggil tapi response 401

`token` di URL tidak match dengan `WA_WEBHOOK_TOKEN` di `.env`. Pastikan sama persis (sensitif huruf besar/kecil).

### Webhook 200 tapi `matched_user_id: null`

Nomor pengirim tidak match dengan `phone` user di DB. Cek di Pengaturan → Profil. Sistem normalize otomatis — `+6281…`, `6281…`, `081…` semua diperlakukan sama, dibandingkan tanpa kode negara.

### Bot reply tidak masuk ke WA

Cek `storage/logs/laravel.log`:
```bash
tail -f storage/logs/laravel.log
```
Cari entry `[WA→OUT] fonnte`. Kalau `mode: log` artinya `WA_SEND_REAL` masih `false`. Kalau ada error code dari Fonnte, search di docs Fonnte.

### Di Mode Demo (panel kanan Chat) tapi sudah isi token

Jalankan `php artisan config:clear` dan refresh.

## Production hardening (untuk nanti)

- Ganti `kasku-dev-secret` jadi string acak panjang.
- Set `WA_SEND_REAL=true` di production env.
- Pertimbangkan rate limit di webhook endpoint (Laravel `throttle` middleware).
- Validate Fonnte signature jika Fonnte menyediakan (cek dokumentasi terbaru).
- Pakai static ngrok domain atau deploy ke VPS supaya webhook URL stabil.
