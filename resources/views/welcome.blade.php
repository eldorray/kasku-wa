<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $appName = config('app.name', 'Kasku');
        $faviconUrl = \App\Models\AppSetting::faviconUrl();
        $logoUrl = \App\Models\AppSetting::logoUrl();
    @endphp
    <title>{{ $appName }} — Catat keuangan langsung dari WhatsApp</title>
    <meta name="description" content="Catat pemasukan, pengeluaran, budget, dan goals langsung dari chat WhatsApp. Otomatis terbukukan, laporan real-time, anti ribet.">
    <link rel="icon" href="{{ $faviconUrl }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @fonts

    <style>
        :root {
            --bg: #f7f7f5;
            --bg-elev: #ffffff;
            --bg-sunken: #f0f0ed;
            --ink: #0e0e0c;
            --ink-2: #4a4a47;
            --ink-3: #8a8a85;
            --line: #e8e8e3;
            --wa: #25d366;
            --wa-deep: #128c7e;
            --wa-ink: #075e54;
            --wa-bg: #e7f7ec;
            --pos: #1f8a5b;
            --neg: #c0382b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            font-family: 'Geist', ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }
        .display { font-family: 'Instrument Serif', 'Times New Roman', serif; font-weight: 400; letter-spacing: -0.01em; line-height: 1.05; }
        .mono { font-family: 'Geist Mono', ui-monospace, monospace; }
        a { color: inherit; text-decoration: none; }
        button { font-family: inherit; cursor: pointer; border: none; background: none; }

        .nav {
            position: sticky; top: 0; z-index: 50;
            background: rgba(247,247,245,0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
        }
        .nav-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px;
        }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 600; }
        .brand-mark {
            width: 32px; height: 32px;
            background: var(--ink); color: white;
            border-radius: 9px;
            display: grid; place-items: center;
            font-family: 'Instrument Serif', serif;
            font-size: 19px;
            overflow: hidden;
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
        .nav-links { display: flex; align-items: center; gap: 28px; font-size: 14px; }
        .nav-links a { color: var(--ink-2); transition: color .15s; }
        .nav-links a:hover { color: var(--ink); }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 14px; font-weight: 500;
            background: var(--bg-elev);
            color: var(--ink);
            border: 1px solid var(--line);
            transition: all .15s;
        }
        .btn:hover { background: var(--bg-sunken); }
        .btn--primary { background: var(--ink); color: white; border-color: var(--ink); }
        .btn--primary:hover { background: #2a2a28; }
        .btn--wa { background: var(--wa); color: white; border-color: var(--wa); }
        .btn--wa:hover { background: var(--wa-deep); }
        .btn--lg { padding: 14px 24px; font-size: 15px; border-radius: 12px; }

        .hero {
            max-width: 1200px; margin: 0 auto;
            padding: 64px 24px 48px;
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
            gap: 48px;
            align-items: center;
        }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px;
            background: var(--wa-bg); color: var(--wa-ink);
            border-radius: 999px;
            font-size: 12px; font-weight: 500;
            margin-bottom: 22px;
        }
        .hero-eyebrow .dot { width: 6px; height: 6px; background: var(--wa); border-radius: 50%; animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: 0.4 } }
        .hero-title { font-size: 64px; margin-bottom: 18px; }
        .hero-title em { font-style: italic; color: var(--wa-deep); }
        .hero-sub {
            font-size: 17px; color: var(--ink-2);
            max-width: 540px; margin-bottom: 28px;
        }
        .hero-cta { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .hero-trust {
            margin-top: 24px;
            display: flex; gap: 18px; flex-wrap: wrap;
            font-size: 12px; color: var(--ink-3);
        }
        .hero-trust span { display: flex; align-items: center; gap: 6px; }
        .hero-trust svg { color: var(--pos); }

        .hero-mock { position: relative; display: flex; justify-content: center; }
        .phone {
            width: 320px;
            border-radius: 36px;
            background: #0a0a0a;
            padding: 8px;
            box-shadow: 0 24px 60px -16px rgba(0,0,0,0.35);
        }
        .phone-screen {
            background: #ECE5DD;
            border-radius: 30px;
            overflow: hidden;
            min-height: 540px;
            display: flex; flex-direction: column;
        }
        .wa-hd {
            background: #075E54; color: white;
            padding: 14px 16px 10px;
            display: flex; align-items: center; gap: 10px;
        }
        .wa-hd-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: white; color: #075E54;
            display: grid; place-items: center;
            font-family: 'Instrument Serif', serif; font-size: 19px;
        }
        .wa-msgs { padding: 14px 12px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .bubble {
            max-width: 80%;
            padding: 8px 11px 7px;
            border-radius: 8px;
            font-size: 13px;
            box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
        }
        .bubble { color: #111b21; }
        .bubble * { color: inherit; }
        .bubble.out { background: #DCF8C6; align-self: flex-end; border-top-right-radius: 2px; }
        .bubble.in  { background: white; align-self: flex-start; border-top-left-radius: 2px; }
        .receipt, .receipt * { color: #111b21; }
        .float-chip, .float-chip * { color: #111b21; }
        .bubble-time { font-size: 9.5px; color: rgba(0,0,0,0.45); float: right; margin: 4px 0 -2px 8px; }
        .receipt {
            background: white;
            border-radius: 8px;
            padding: 10px 11px;
            font-size: 12px;
            margin-top: 4px;
            border-left: 3px solid #128C7E;
        }
        .receipt-row { display: flex; justify-content: space-between; padding: 1.5px 0; }
        .receipt-row b { font-weight: 500; }
        .wa-input {
            background: #f0f0f0;
            padding: 8px 10px;
            display: flex; align-items: center; gap: 8px;
        }
        .wa-input-box {
            flex: 1; background: white;
            border-radius: 22px; padding: 8px 14px;
            font-size: 12px; color: #888;
        }
        .wa-input-send {
            width: 34px; height: 34px;
            background: #128C7E; border-radius: 50%;
            display: grid; place-items: center; color: white;
        }

        .float-chip {
            position: absolute;
            background: white;
            padding: 10px 14px;
            border-radius: 12px;
            box-shadow: 0 6px 18px -4px rgba(0,0,0,0.12);
            font-size: 12px;
            display: flex; align-items: center; gap: 8px;
            border: 1px solid var(--line);
        }
        .float-chip.tl { top: 30px; left: -20px; }
        .float-chip.br { bottom: 60px; right: -30px; }
        .float-chip-icon {
            width: 28px; height: 28px;
            border-radius: 8px;
            display: grid; place-items: center;
            font-size: 14px;
        }

        .section {
            max-width: 1200px; margin: 0 auto;
            padding: 64px 24px;
        }
        .section-eyebrow {
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--ink-3); font-weight: 500;
            margin-bottom: 12px;
        }
        .section-title { font-size: 40px; margin-bottom: 14px; }
        .section-sub { font-size: 16px; color: var(--ink-2); max-width: 600px; }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 40px;
        }
        .feat {
            background: var(--bg-elev);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 24px;
        }
        .feat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: var(--ink); color: white;
            display: grid; place-items: center;
            font-size: 20px;
            margin-bottom: 16px;
        }
        .feat-title { font-size: 16px; font-weight: 500; margin-bottom: 6px; }
        .feat-sub { font-size: 13px; color: var(--ink-3); line-height: 1.55; }

        .how {
            background: var(--ink); color: white;
            padding: 64px 24px;
        }
        .how-inner { max-width: 1200px; margin: 0 auto; }
        .how .section-eyebrow { color: rgba(255,255,255,0.55); }
        .how .section-sub { color: rgba(255,255,255,0.7); }
        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 40px;
        }
        .step {
            padding: 24px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
        }
        .step-num {
            font-family: 'Instrument Serif', serif;
            font-size: 36px;
            color: rgba(255,255,255,0.4);
            line-height: 1; margin-bottom: 8px;
        }
        .step-title { font-size: 16px; font-weight: 500; margin-bottom: 6px; }
        .step-sub { font-size: 13px; color: rgba(255,255,255,0.65); line-height: 1.55; }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-top: 40px;
        }
        .stat {
            text-align: center; padding: 24px;
            background: var(--bg-elev);
            border: 1px solid var(--line);
            border-radius: 18px;
        }
        .stat-value {
            font-family: 'Instrument Serif', serif;
            font-size: 42px;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12px; color: var(--ink-3);
            text-transform: uppercase; letter-spacing: 0.08em;
        }

        .cta { max-width: 1200px; margin: 0 auto; padding: 64px 24px; }
        .cta-card {
            background: linear-gradient(135deg, #075E54 0%, #128C7E 100%);
            color: white;
            border-radius: 24px;
            padding: 56px 48px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta-card::after {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(37,211,102,0.4) 0%, transparent 70%);
            border-radius: 50%;
        }
        .cta-title { font-size: 36px; margin-bottom: 14px; position: relative; z-index: 1; }
        .cta-sub { font-size: 16px; opacity: 0.85; max-width: 520px; margin: 0 auto 24px; position: relative; z-index: 1; }
        .cta-buttons { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; position: relative; z-index: 1; }
        .cta .btn--primary { background: white; color: var(--wa-ink); border-color: white; }
        .cta .btn--primary:hover { background: rgba(255,255,255,0.92); }

        footer {
            border-top: 1px solid var(--line);
            padding: 32px 24px;
            font-size: 13px; color: var(--ink-3);
            text-align: center;
        }
        footer a { color: var(--ink-2); }
        footer a:hover { color: var(--ink); }

        @media (max-width: 900px) {
            .hero {
                grid-template-columns: 1fr;
                padding: 36px 20px 24px;
                gap: 36px;
                text-align: center;
            }
            .hero-sub { margin-left: auto; margin-right: auto; }
            .hero-cta { justify-content: center; }
            .hero-trust { justify-content: center; }
            .hero-title { font-size: 44px; }
            .hero-mock { order: -1; }
            .float-chip { display: none; }

            .section { padding: 44px 20px; }
            .section-title { font-size: 30px; }
            .features { grid-template-columns: 1fr; }
            .how { padding: 44px 20px; }
            .steps { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr 1fr; }
            .stat-value { font-size: 32px; }
            .cta { padding: 32px 20px; }
            .cta-card { padding: 36px 24px; border-radius: 20px; }
            .cta-title { font-size: 26px; }

            .nav-links a:not(.btn) { display: none; }
            .nav-inner { padding: 12px 20px; }
        }

        @media (max-width: 480px) {
            .hero-title { font-size: 36px; }
            .phone { width: 280px; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="/" class="brand">
                <div class="brand-mark">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}">
                    @else
                        k
                    @endif
                </div>
                <span>{{ $appName }}</span>
            </a>
            <div class="nav-links">
                <a href="#fitur">Fitur</a>
                <a href="#cara">Cara kerja</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn--primary">Buka Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn--primary">Masuk</a>
                @endauth
            </div>
        </div>
    </nav>

    <section class="hero">
        <div>
            <span class="hero-eyebrow"><span class="dot"></span> Personal use · By invitation only</span>
            <h1 class="display hero-title">Buku besar keluarga, <em>cukup chat</em> di WhatsApp.</h1>
            <p class="hero-sub">
                Aplikasi keuangan untuk Fahmi sekeluarga. Tulis "kopi 28rb" di WhatsApp — otomatis tercatat,
                saldo & laporan langsung terupdate. Suami-istri mencatat di buku yang sama, anggota lain hanya
                bergabung lewat undangan.
            </p>
            <div class="hero-cta">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn--primary btn--lg">
                        Buka Dashboard
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn--primary btn--lg">
                        Masuk
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                @endauth
            </div>
            <div class="hero-trust">
                <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg> Penggunaan privat — by invitation</span>
                <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Multi-akun & multi-anggota</span>
                <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Data tersimpan sendiri</span>
            </div>
        </div>

        <div class="hero-mock">
            <div class="float-chip tl">
                <div class="float-chip-icon" style="background:#fef3c7;color:#f59e0b">🍚</div>
                <div>
                    <div style="font-weight:500">Kopi Tuku</div>
                    <div style="color:var(--neg);font-weight:500" class="mono">−Rp28.000</div>
                </div>
            </div>
            <div class="float-chip br">
                <div class="float-chip-icon" style="background:#dcfce7;color:#1f8a5b">💼</div>
                <div>
                    <div style="font-weight:500">Gaji masuk</div>
                    <div style="color:var(--pos);font-weight:500" class="mono">+Rp5.000.000</div>
                </div>
            </div>

            <div class="phone">
                <div class="phone-screen">
                    <div class="wa-hd">
                        <div class="wa-hd-avatar">k</div>
                        <div style="flex:1">
                            <div style="font-weight:500;font-size:14px">{{ $appName }} Bot</div>
                            <div style="font-size:11px;opacity:0.85">online</div>
                        </div>
                    </div>
                    <div class="wa-msgs">
                        <div class="bubble in">
                            Halo Fahmi! 👋 Ketik transaksi langsung di sini.
                            <span class="bubble-time">08:00</span>
                        </div>
                        <div class="bubble out">
                            kopi tuku 28rb akun gopay
                            <span class="bubble-time">09:12 <span style="color:#4FC3F7">✓✓</span></span>
                        </div>
                        <div class="bubble in">
                            Tercatat ✅
                            <div class="receipt">
                                <div class="receipt-row"><span style="opacity:0.6">Tipe</span><b>🍚 Pengeluaran</b></div>
                                <div class="receipt-row"><span style="opacity:0.6">Kategori</span><b>Makan & Minum</b></div>
                                <div class="receipt-row"><span style="opacity:0.6">Akun</span><b>GoPay</b></div>
                                <div class="receipt-row" style="margin-top:6px;padding-top:6px;border-top:1px dashed #ddd"><span>Total</span><b>Rp28.000</b></div>
                            </div>
                            <span class="bubble-time">09:12</span>
                        </div>
                        <div class="bubble out">
                            /laporan bulan ini
                            <span class="bubble-time">17:45 <span style="color:#4FC3F7">✓✓</span></span>
                        </div>
                        <div class="bubble in">
                            <div style="font-weight:500;margin-bottom:4px">📊 Mei 2026</div>
                            <div style="line-height:1.6">
                                Pemasukan: <b>Rp16.000.000</b><br>
                                Pengeluaran: <b>Rp5.230.000</b><br>
                                Net: <b style="color:var(--pos)">+Rp10.770.000</b>
                            </div>
                            <span class="bubble-time">17:45</span>
                        </div>
                    </div>
                    <div class="wa-input">
                        <span style="color:#888">😊</span>
                        <div class="wa-input-box">Ketik pesan…</div>
                        <div class="wa-input-send">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="fitur" class="section">
        <div class="section-eyebrow">Fitur</div>
        <h2 class="display section-title">Apa saja yang bisa dilakukan di sini.</h2>
        <p class="section-sub">Dirancang untuk satu keluarga, bukan untuk skala publik. Sederhana, langsung pakai.</p>

        <div class="features">
            <div class="feat">
                <div class="feat-icon">💬</div>
                <div class="feat-title">Chat WhatsApp</div>
                <div class="feat-sub">"Bensin 50rb akun BCA" — bot otomatis parse jumlah, kategori, dan akun. Balasan dalam 2 detik.</div>
            </div>
            <div class="feat">
                <div class="feat-icon">👥</div>
                <div class="feat-title">Household bersama</div>
                <div class="feat-sub">Suami-istri pakai satu buku besar, masing-masing dengan nomor WA sendiri. Catatan siapa yang menginput tetap tersimpan.</div>
            </div>
            <div class="feat">
                <div class="feat-icon">🏦</div>
                <div class="feat-title">Multi-akun</div>
                <div class="feat-sub">BCA, GoPay, OVO, tunai, kartu kredit — saldo per akun otomatis terupdate. Transfer antar akun atomik.</div>
            </div>
            <div class="feat">
                <div class="feat-icon">🎯</div>
                <div class="feat-title">Budget & Goals</div>
                <div class="feat-sub">Limit per kategori, peringatan saat over-budget. Target tabungan auto-sync dengan saldo akun terkait.</div>
            </div>
            <div class="feat">
                <div class="feat-icon">📊</div>
                <div class="feat-title">Laporan</div>
                <div class="feat-sub">Cashflow, breakdown kategori, top merchant, insight rule-based. Mingguan, bulanan, tahunan.</div>
            </div>
            <div class="feat">
                <div class="feat-icon">🔒</div>
                <div class="feat-title">Tertutup & private</div>
                <div class="feat-sub">Pendaftaran umum dimatikan. Hanya anggota yang diundang owner yang bisa masuk. Data tersimpan sendiri di server pribadi.</div>
            </div>
        </div>
    </section>

    <section id="cara" class="how">
        <div class="how-inner">
            <div class="section-eyebrow">Cara kerja</div>
            <h2 class="display section-title">Singkat saja — tiga langkah.</h2>
            <p class="section-sub">Untuk anggota yang sudah punya akun, masuk langsung. Untuk yang belum, harus diundang dulu.</p>

            <div class="steps">
                <div class="step">
                    <div class="step-num">01</div>
                    <div class="step-title">Owner mengundang</div>
                    <div class="step-sub">Owner (Fahmi) menambahkan anggota lewat menu Household di Pengaturan, dengan email atau nomor WA yang sudah terdaftar.</div>
                </div>
                <div class="step">
                    <div class="step-num">02</div>
                    <div class="step-title">Anggota menerima undangan</div>
                    <div class="step-sub">Setelah login, undangan muncul di header. Sekali klik "Terima" — langsung jadi anggota household keluarga.</div>
                </div>
                <div class="step">
                    <div class="step-num">03</div>
                    <div class="step-title">Catat lewat WhatsApp</div>
                    <div class="step-sub">Tinggal kirim chat ke bot Kasku. "Kopi 25rb", "/saldo", "/laporan" — semua langsung tercatat dan dibalas.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="cta-card">
            <h2 class="display cta-title">Sudah punya akun? Masuk untuk lanjut.</h2>
            <p class="cta-sub">Aplikasi ini hanya untuk pemakaian pribadi. Pendaftaran publik dinonaktifkan — akun baru hanya dibuat lewat undangan owner.</p>
            <div class="cta-buttons">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn--primary btn--lg">Buka Dashboard Saya</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn--primary btn--lg">
                        Masuk
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                @endauth
            </div>
        </div>
    </section>

    <footer>
        <div>© {{ date('Y') }} {{ $appName }} · <a href="{{ route('login') }}">Masuk</a></div>
        <div style="margin-top:6px;font-size:11px">Aplikasi keuangan privat untuk keluarga. Pendaftaran umum tidak dibuka.</div>
    </footer>
</body>
</html>
