// Kasku — main app (vanilla JS)
(function(){
const { fmtIDR, fmtIDRk, CATEGORIES, catById, ACCOUNTS, TX, BUDGETS, MONTHLY, GOALS } = window.KK;

// SVG icons (Lucide-ish, paths only)
const ICONS = {
  home: '<path d="M3 12 L12 4 L21 12"/><path d="M5 10v10h14V10"/>',
  list: '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
  chat: '<path d="M21 12a8 8 0 0 1-12.5 6.7L3 20l1.3-5.5A8 8 0 1 1 21 12Z"/>',
  tag: '<path d="M20.5 12.5 13 20a2 2 0 0 1-2.8 0l-7-7a2 2 0 0 1 0-2.8L9.5 3H20v10.5"/><circle cx="15.5" cy="7.5" r="1.2"/>',
  chart: '<line x1="3" y1="20" x2="21" y2="20"/><rect x="6" y="11" width="3" height="7"/><rect x="11" y="6" width="3" height="12"/><rect x="16" y="14" width="3" height="4"/>',
  wallet: '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1H5a2 2 0 0 1-2-1Z"/><circle cx="17" cy="14" r="1.2"/>',
  target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.7l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.7-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.7.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.7 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.7.3 1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.7-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.7 1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',
  search: '<circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/>',
  plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
  arrowRight: '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
  chevronRight: '<polyline points="9 6 15 12 9 18"/>',
  bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/>',
  filter: '<polygon points="22 3 2 3 10 12.5 10 19 14 21 14 12.5"/>',
  x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
  camera: '<path d="M21 17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3l2-3h4l2 3h3a2 2 0 0 1 2 2Z"/><circle cx="12" cy="12" r="3.5"/>',
  send: '<path d="M22 2 11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
  calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
  download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
  eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
  more: '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
  trendUp: '<polyline points="3 17 9 11 13 15 21 7"/><polyline points="14 7 21 7 21 14"/>',
  trendDown: '<polyline points="3 7 9 13 13 9 21 17"/><polyline points="14 17 21 17 21 10"/>',
  sparkle: '<path d="M12 3 L13.5 9 L19 10.5 L13.5 12 L12 18 L10.5 12 L5 10.5 L10.5 9 Z"/>',
  wa: '<path d="M3 21l1.7-5.4A8 8 0 1 1 8 19l-5 2Z"/><path d="M9 9.5c.3 1.7 1.6 3.5 3.5 4.5l1-1c.3-.3.7-.4 1-.2l1.5.6c.4.2.6.6.5 1l-.3 1.2c-.2.7-.9 1.1-1.6 1-3.5-.6-6.4-3.4-7-6.9-.1-.7.3-1.4 1-1.6l1.2-.3c.4-.1.8.1 1 .5l.6 1.5c.1.3 0 .7-.2 1l-1 1Z"/>',
  zap: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
};

const icon = (name, size = 16) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">${ICONS[name] || ''}</svg>`;
window.icon = icon;

// Format time
const formatDay = (d) => {
  if (d === '2026-05-06') return 'Hari ini · Rabu, 6 Mei';
  if (d === '2026-05-05') return 'Kemarin · Selasa, 5 Mei';
  const date = new Date(d);
  return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });
};

// ===== Sparkline =====
function sparkline(data, color, fill) {
  const w = 200, h = 36;
  const max = Math.max(...data, 1), min = Math.min(...data, 0);
  const range = max - min || 1;
  const pts = data.map((v, i) => [i / (data.length - 1) * w, h - ((v - min) / range) * (h - 4) - 2]);
  const path = 'M' + pts.map(p => p.join(',')).join(' L');
  const area = path + ` L${w},${h} L0,${h} Z`;
  return `<svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" style="width:100%;height:36px;display:block">
    ${fill ? `<path d="${area}" fill="${color}" opacity="0.08"/>` : ''}
    <path d="${path}" fill="none" stroke="${color}" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
  </svg>`;
}

// ===== Cashflow chart =====
function cashflowChart() {
  const w = 600, h = 220, pad = 28;
  const max = Math.max(...MONTHLY.flatMap(d => [d.income, d.expense]));
  const xs = i => pad + (i / (MONTHLY.length - 1)) * (w - pad * 2);
  const ys = v => h - pad - (v / max) * (h - pad * 2);
  const incomePath = 'M' + MONTHLY.map((d, i) => `${xs(i)},${ys(d.income)}`).join(' L');
  const expensePath = 'M' + MONTHLY.map((d, i) => `${xs(i)},${ys(d.expense)}`).join(' L');
  const incomeArea = incomePath + ` L${xs(MONTHLY.length-1)},${h-pad} L${xs(0)},${h-pad} Z`;
  return `<svg viewBox="0 0 ${w} ${h}" style="width:100%;height:240px">
    ${[0,0.25,0.5,0.75,1].map(g => `<line x1="${pad}" x2="${w-pad}" y1="${pad+g*(h-pad*2)}" y2="${pad+g*(h-pad*2)}" stroke="var(--line)" stroke-dasharray="2 4"/>`).join('')}
    <rect x="${xs(5)-18}" y="${pad-6}" width="36" height="${h-pad*2+8}" fill="var(--ink)" opacity="0.04" rx="6"/>
    <path d="${incomeArea}" fill="var(--pos)" opacity="0.08"/>
    <path d="${incomePath}" fill="none" stroke="var(--pos)" stroke-width="2"/>
    <path d="${expensePath}" fill="none" stroke="var(--neg)" stroke-width="2" stroke-dasharray="4 3"/>
    ${MONTHLY.map((d, i) => `
      <circle cx="${xs(i)}" cy="${ys(d.income)}" r="3.5" fill="var(--bg-elev)" stroke="var(--pos)" stroke-width="2"/>
      <circle cx="${xs(i)}" cy="${ys(d.expense)}" r="3" fill="var(--bg-elev)" stroke="var(--neg)" stroke-width="1.5"/>
      <text x="${xs(i)}" y="${h-6}" font-size="11" text-anchor="middle" fill="var(--ink-3)" font-family="var(--font-sans)">${d.m}</text>
    `).join('')}
  </svg>
  <div class="flex gap-4" style="margin-top:8px;font-size:12px">
    <div class="flex-c gap-2"><span class="chip-dot" style="background:var(--pos)"></span> Pemasukan</div>
    <div class="flex-c gap-2"><span class="chip-dot" style="background:var(--neg)"></span> Pengeluaran</div>
  </div>`;
}

// ===== DASHBOARD =====
function renderDashboard() {
  const totalBalance = ACCOUNTS.reduce((a, b) => a + b.balance, 0);
  const m = MONTHLY[5];
  const recent = TX.slice(0, 6);

  return `
    <div class="page-hd">
      <div>
        <div class="eyebrow" style="margin-bottom:6px">Selamat sore, Rama 👋</div>
        <h1 class="page-title">Ringkasan Keuangan</h1>
        <div class="page-sub">Mei 2026 — 5 hari berlalu, Anda menghemat <b style="color:var(--pos)">Rp10.769.010</b> bulan ini.</div>
      </div>
      <div class="flex gap-3">
        <button class="btn">${icon('calendar')} Mei 2026</button>
        <button class="btn">${icon('download')} Ekspor</button>
      </div>
    </div>

    <div class="grid grid-4" style="margin-bottom:20px">
      <div class="card" style="background:var(--ink);color:white;border-color:var(--ink)">
        <div class="flex-b" style="margin-bottom:12px">
          <span class="eyebrow" style="color:rgba(255,255,255,0.6)">Total Saldo</span>
          <button class="icon-btn" style="border-color:rgba(255,255,255,0.15);color:rgba(255,255,255,0.7)">${icon('eye',14)}</button>
        </div>
        <div class="display" style="font-size:36px">${fmtIDR(totalBalance)}</div>
        <div class="text-xs mono" style="color:rgba(255,255,255,0.55);margin-top:8px">5 akun · Bank, e-wallet, tunai</div>
      </div>

      <div class="card">
        <div class="flex-b" style="margin-bottom:12px">
          <span class="eyebrow">Pemasukan Mei</span>
          <span class="chip chip-pos">${icon('trendUp',11)} +12%</span>
        </div>
        <div class="display" style="font-size:30px;color:var(--pos)">${fmtIDR(m.income)}</div>
        ${sparkline([12,11,14,13,15,12,18,16,15,16],'#1f8a5b',true)}
      </div>

      <div class="card">
        <div class="flex-b" style="margin-bottom:12px">
          <span class="eyebrow">Pengeluaran Mei</span>
          <span class="chip chip-neg">${icon('trendDown',11)} -8%</span>
        </div>
        <div class="display" style="font-size:30px;color:var(--neg)">${fmtIDR(m.expense)}</div>
        ${sparkline([8,9,7,8,9,7,9,8,7,5],'#c0382b',true)}
      </div>

      <div class="card">
        <div class="flex-b" style="margin-bottom:12px">
          <span class="eyebrow">Saving Rate</span>
          <span class="chip chip-pos">target 60%</span>
        </div>
        <div class="display" style="font-size:30px">67%</div>
        <div class="bar" style="margin-top:14px"><div class="bar-fill" style="width:67%;background:var(--pos)"></div></div>
      </div>
    </div>

    <div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr)">
      <div class="card">
        <div class="card-hd">
          <div>
            <div class="card-title">Cashflow 6 bulan</div>
            <div class="card-sub">Pemasukan vs pengeluaran bulanan</div>
          </div>
          <div class="tabs">
            <button class="tab">3M</button>
            <button class="tab active">6M</button>
            <button class="tab">1Y</button>
          </div>
        </div>
        ${cashflowChart()}
      </div>

      <div class="card" style="background:var(--wa-bg);border-color:transparent">
        <div class="card-hd">
          <div>
            <div class="card-title" style="color:var(--wa-ink);display:flex;align-items:center;gap:8px">${icon('wa',14)} Aktivitas WhatsApp</div>
            <div class="card-sub" style="color:var(--wa-deep)">14 transaksi via chat minggu ini</div>
          </div>
        </div>
        <div class="display" style="font-size:56px;color:var(--wa-ink);line-height:0.9">14<span style="font-size:22px;opacity:0.6">/20</span></div>
        <div class="text-xs" style="color:var(--wa-deep);margin-top:6px;margin-bottom:16px">tx via chat &nbsp;·&nbsp; 70% otomasi</div>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:12px;color:var(--wa-ink)">
          <div class="flex gap-3" style="align-items:center"><div style="width:24px;height:24px;background:white;border-radius:6px;display:grid;place-items:center">💬</div>Chat natural · 11</div>
          <div class="flex gap-3" style="align-items:center"><div style="width:24px;height:24px;background:white;border-radius:6px;display:grid;place-items:center">📷</div>Foto struk · 2</div>
          <div class="flex gap-3" style="align-items:center"><div style="width:24px;height:24px;background:white;border-radius:6px;display:grid;place-items:center">⚡</div>Command /expense · 1</div>
        </div>
        <button class="btn btn-wa" style="margin-top:18px;width:100%;justify-content:center" onclick="goTo('chat')">Buka chat ${icon('arrowRight',12)}</button>
      </div>
    </div>

    <div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);margin-top:20px">
      <div class="card" style="padding:0">
        <div class="card-hd" style="padding:20px;margin-bottom:0">
          <div>
            <div class="card-title">Transaksi terbaru</div>
            <div class="card-sub">6 dari 248 transaksi bulan ini</div>
          </div>
          <button class="btn btn-ghost" onclick="goTo('transaksi')">Lihat semua ${icon('arrowRight',12)}</button>
        </div>
        <table class="tbl">
          <tbody>
            ${recent.map(t => {
              const c = catById(t.cat);
              const acc = ACCOUNTS.find(a => a.id === t.acc);
              return `<tr onclick="openTx('${t.id}')">
                <td style="width:44px"><div class="cat-icon" style="background:${c.bg};color:${c.color}">${c.emoji}</div></td>
                <td><div class="fw-500">${t.label}</div><div class="text-xs muted" style="margin-top:2px">${c.label} · ${acc.label}</div></td>
                <td style="width:100px">${viaChip(t.via)}</td>
                <td style="text-align:right;font-weight:500" class="money ${t.amount<0?'money-neg':'money-pos'}">${fmtIDR(t.amount)}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>

      <div class="card">
        <div class="card-hd">
          <div>
            <div class="card-title">Budget bulan ini</div>
            <div class="card-sub">5 dari 6 kategori sehat</div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:14px">
          ${BUDGETS.map(b => {
            const c = catById(b.cat);
            const pct = Math.min(100, Math.round(b.spent / b.limit * 100));
            const over = b.spent > b.limit;
            return `<div>
              <div class="flex-b" style="margin-bottom:6px">
                <div class="flex gap-2" style="align-items:center">
                  <span style="font-size:14px">${c.emoji}</span>
                  <span style="font-size:13px">${c.label}</span>
                  ${over ? '<span class="chip chip-neg" style="padding:1px 6px;font-size:10px">over</span>' : ''}
                </div>
                <div class="text-xs muted tabular">${fmtIDRk(b.spent)} / ${fmtIDRk(b.limit)}</div>
              </div>
              <div class="bar"><div class="bar-fill" style="width:${pct}%;background:${over?'var(--neg)':(pct>80?'var(--warn)':'var(--ink)')}"></div></div>
            </div>`;
          }).join('')}
        </div>
      </div>
    </div>

    <div style="margin-top:32px">
      <div class="flex-b" style="margin-bottom:16px">
        <div>
          <div class="card-title">Goals & target tabungan</div>
          <div class="card-sub">3 target aktif</div>
        </div>
        <button class="btn btn-ghost">${icon('plus',12)} Goal baru</button>
      </div>
      <div class="grid grid-3">
        ${GOALS.map(g => {
          const pct = Math.round(g.current / g.target * 100);
          return `<div class="card">
            <div class="flex-b">
              <div class="eyebrow">${g.by}</div>
              <span class="chip">${pct}%</span>
            </div>
            <div class="display" style="font-size:22px;margin-top:12px;margin-bottom:4px">${g.label}</div>
            <div class="text-xs muted" style="margin-bottom:16px"><span class="tabular fw-500" style="color:var(--ink)">${fmtIDR(g.current)}</span> dari ${fmtIDR(g.target)}</div>
            <div class="bar" style="height:4px"><div class="bar-fill" style="width:${pct}%;background:${g.color}"></div></div>
          </div>`;
        }).join('')}
      </div>
    </div>
  `;
}

function viaChip(via) {
  if (via === 'wa') return `<span class="chip chip-wa">${icon('wa',11)}chat</span>`;
  if (via === 'receipt') return `<span class="chip chip-wa">${icon('camera',11)}struk</span>`;
  return `<span class="chip">manual</span>`;
}

window.viaChip = viaChip;
window.renderDashboard = renderDashboard;
})();
