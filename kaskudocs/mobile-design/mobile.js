// Kasku Mobile — all views
(function(){
const { fmtIDR, fmtIDRk, CATEGORIES, catById, ACCOUNTS, TX, BUDGETS, MONTHLY, GOALS, CONVERSATIONS } = window.KK;

// ===== Icons =====
const I = {
  bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/>',
  search: '<circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/>',
  plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
  arrowR: '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
  arrowL: '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
  filter: '<polygon points="22 3 2 3 10 12.5 10 19 14 21 14 12.5"/>',
  more: '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
  eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
  trendUp: '<polyline points="3 17 9 11 13 15 21 7"/><polyline points="14 7 21 7 21 14"/>',
  trendDown: '<polyline points="3 7 9 13 13 9 21 17"/><polyline points="14 17 21 17 21 10"/>',
  wa: '<path d="M3 21l1.7-5.4A8 8 0 1 1 8 19l-5 2Z"/>',
  camera: '<path d="M21 17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3l2-3h4l2 3h3a2 2 0 0 1 2 2Z"/><circle cx="12" cy="12" r="3.5"/>',
  send: '<path d="M22 2 11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
  sparkle: '<path d="M12 3 L13.5 9 L19 10.5 L13.5 12 L12 18 L10.5 12 L5 10.5 L10.5 9 Z"/>',
  x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
  download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
  cal: '<rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
  tag: '<path d="M20.5 12.5 13 20a2 2 0 0 1-2.8 0l-7-7a2 2 0 0 1 0-2.8L9.5 3H20v10.5"/><circle cx="15.5" cy="7.5" r="1.2"/>',
  target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3m10-10h-3m-14 0H2m17.07-7.07-2.12 2.12M7.05 16.95l-2.12 2.12m0-14.14 2.12 2.12m9.9 9.9 2.12 2.12"/>',
};
const ic = (n, s=18) => `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">${I[n]||''}</svg>`;

// ===== Helpers =====
const formatDay = (d) => {
  if (d === '2026-05-06') return 'HARI INI · RABU, 6 MEI';
  if (d === '2026-05-05') return 'KEMARIN · SELASA, 5 MEI';
  return new Date(d).toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long' }).toUpperCase();
};

const viaChip = (via) => {
  if (via === 'wa') return `<span class="chip chip-wa">${ic('wa', 10)}WA</span>`;
  if (via === 'receipt') return `<span class="chip chip-wa">${ic('camera', 10)}struk</span>`;
  return '';
};

// ===== Sparkline =====
function spark(data, color, fill) {
  const w = 200, h = 36;
  const max = Math.max(...data), min = Math.min(...data);
  const range = max - min || 1;
  const pts = data.map((v, i) => [i / (data.length - 1) * w, h - ((v - min) / range) * (h - 4) - 2]);
  const path = 'M' + pts.map(p => p.join(',')).join(' L');
  const area = path + ` L${w},${h} L0,${h} Z`;
  return `<svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" style="width:100%;height:36px">
    ${fill ? `<path d="${area}" fill="${color}" opacity="0.12"/>` : ''}
    <path d="${path}" fill="none" stroke="${color}" stroke-width="1.5"/>
  </svg>`;
}

// ===== Cashflow chart (mobile) =====
function cashChart() {
  const w = 320, h = 140, pad = 14;
  const max = Math.max(...MONTHLY.flatMap(d => [d.income, d.expense]));
  const xs = i => pad + (i / (MONTHLY.length - 1)) * (w - pad * 2);
  const ys = v => h - pad - 16 - (v / max) * (h - pad * 2 - 16);
  const inc = 'M' + MONTHLY.map((d, i) => `${xs(i)},${ys(d.income)}`).join(' L');
  const exp = 'M' + MONTHLY.map((d, i) => `${xs(i)},${ys(d.expense)}`).join(' L');
  const incArea = inc + ` L${xs(MONTHLY.length-1)},${h-pad-16} L${xs(0)},${h-pad-16} Z`;
  return `<svg viewBox="0 0 ${w} ${h}" style="width:100%;height:140px">
    ${[0,0.5,1].map(g => `<line x1="${pad}" x2="${w-pad}" y1="${pad+g*(h-pad*2-16)}" y2="${pad+g*(h-pad*2-16)}" stroke="#e8e8e3" stroke-dasharray="2 3"/>`).join('')}
    <path d="${incArea}" fill="#1f8a5b" opacity="0.1"/>
    <path d="${inc}" fill="none" stroke="#1f8a5b" stroke-width="2"/>
    <path d="${exp}" fill="none" stroke="#c0382b" stroke-width="2" stroke-dasharray="3 2"/>
    ${MONTHLY.map((d, i) => `<text x="${xs(i)}" y="${h-2}" font-size="9.5" text-anchor="middle" fill="#8a8a85">${d.m}</text>`).join('')}
    ${MONTHLY.map((d, i) => `<circle cx="${xs(i)}" cy="${ys(d.income)}" r="2.5" fill="#fff" stroke="#1f8a5b" stroke-width="1.5"/>`).join('')}
  </svg>`;
}

// ===== VIEWS =====

function viewDashboard() {
  const total = ACCOUNTS.reduce((a,b)=>a+b.balance,0);
  const m = MONTHLY[5];
  const recent = TX.slice(0, 5);
  return `
    <div class="appbar">
      <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#f7d488,#c47a14);display:grid;place-items:center;color:white;font-weight:600;font-size:13px">RA</div>
      <div style="flex:1">
        <div class="text-xs muted">Selamat sore</div>
        <div class="fw-500" style="font-size:14px">Rama Adriansyah</div>
      </div>
      <div class="appbar-actions">
        <button class="appbar-icon">${ic('search', 16)}</button>
        <button class="appbar-icon" style="position:relative">${ic('bell', 16)}<span style="position:absolute;top:6px;right:6px;width:7px;height:7px;background:var(--neg);border-radius:50%"></span></button>
      </div>
    </div>

    <div class="scroll">
      <!-- Balance card -->
      <div class="balance-card">
        <div class="flex-b" style="margin-bottom:10px;position:relative;z-index:1">
          <span class="eyebrow" style="color:rgba(255,255,255,0.5)">Total Saldo · 5 akun</span>
          <button style="color:rgba(255,255,255,0.6)">${ic('eye', 14)}</button>
        </div>
        <div class="display" style="font-size:34px;position:relative;z-index:1">${fmtIDR(total)}</div>
        <div class="text-xs" style="color:rgba(255,255,255,0.55);margin-top:6px;position:relative;z-index:1">Mei 2026 · saving rate <b style="color:white">67%</b></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px;position:relative;z-index:1">
          <div style="background:rgba(255,255,255,0.08);border-radius:12px;padding:10px 12px">
            <div class="text-xs" style="color:rgba(255,255,255,0.5)">Masuk</div>
            <div class="fw-500 tabular" style="margin-top:2px">${fmtIDRk(m.income)}</div>
          </div>
          <div style="background:rgba(255,255,255,0.08);border-radius:12px;padding:10px 12px">
            <div class="text-xs" style="color:rgba(255,255,255,0.5)">Keluar</div>
            <div class="fw-500 tabular" style="margin-top:2px">${fmtIDRk(m.expense)}</div>
          </div>
        </div>
      </div>

      <!-- WA banner -->
      <div style="background:var(--wa-bg);border-radius:18px;padding:14px 16px;margin-top:14px;display:flex;align-items:center;gap:12px" onclick="setTab('chat')">
        <div style="width:38px;height:38px;background:var(--wa);border-radius:50%;display:grid;place-items:center;color:white;flex-shrink:0">${ic('wa', 18)}</div>
        <div style="flex:1">
          <div class="fw-500" style="font-size:13px;color:var(--wa-ink)">14 transaksi via chat minggu ini</div>
          <div class="text-xs" style="color:var(--wa-deep);margin-top:2px">70% otomasi · Tap untuk buka chat</div>
        </div>
        <span style="color:var(--wa-deep)">${ic('arrowR', 16)}</span>
      </div>

      <!-- Quick actions -->
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:16px">
        ${[
          {ico:'💬', l:'Catat',act:"setTab('chat')"},
          {ico:'📷', l:'Struk',act:"openSheet('add')"},
          {ico:'🎯', l:'Goals',act:"openSheet('goals')"},
          {ico:'🏷️', l:'Budget',act:"openSheet('budget')"},
        ].map(a => `<button onclick="${a.act}" style="background:var(--bg-elev);border:1px solid var(--line);border-radius:14px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;gap:6px">
          <span style="font-size:22px">${a.ico}</span>
          <span class="text-xs fw-500">${a.l}</span>
        </button>`).join('')}
      </div>

      <!-- Cashflow -->
      <div class="card" style="margin-top:18px">
        <div class="flex-b" style="margin-bottom:8px">
          <div>
            <div class="fw-500" style="font-size:13px">Cashflow 6 bulan</div>
            <div class="text-xs muted">Pemasukan vs pengeluaran</div>
          </div>
          <span class="chip chip-pos">${ic('trendUp', 10)}+12%</span>
        </div>
        ${cashChart()}
        <div class="flex-c gap-3" style="margin-top:6px;font-size:11px">
          <div class="flex-c gap-2"><span style="width:6px;height:6px;border-radius:50%;background:var(--pos)"></span>Masuk</div>
          <div class="flex-c gap-2"><span style="width:6px;height:6px;border-radius:50%;background:var(--neg)"></span>Keluar</div>
        </div>
      </div>

      <!-- Budget summary -->
      <div class="section-title">
        <h3>Budget bulan ini</h3>
        <a onclick="openSheet('budget')">Lihat semua →</a>
      </div>
      <div class="card" style="padding:12px">
        ${BUDGETS.slice(0,4).map(b => {
          const c = catById(b.cat);
          const pct = Math.min(100, Math.round(b.spent / b.limit * 100));
          const over = b.spent > b.limit;
          return `<div style="padding:8px 4px;${b!==BUDGETS[3]?'border-bottom:1px solid var(--line)':''}">
            <div class="flex-b" style="margin-bottom:6px">
              <div class="flex-c gap-2"><span style="font-size:14px">${c.emoji}</span><span style="font-size:12.5px;font-weight:500">${c.label}</span>${over?'<span class="chip chip-neg" style="font-size:9px;padding:1px 5px">over</span>':''}</div>
              <div class="text-xs muted tabular">${fmtIDRk(b.spent)}/${fmtIDRk(b.limit)}</div>
            </div>
            <div class="bar"><div class="bar-fill" style="width:${pct}%;background:${over?'var(--neg)':(pct>80?'var(--warn)':'var(--ink)')}"></div></div>
          </div>`;
        }).join('')}
      </div>

      <!-- Recent tx -->
      <div class="section-title">
        <h3>Transaksi terbaru</h3>
        <a onclick="setTab('transaksi')">Lihat semua →</a>
      </div>
      ${recent.map(t => txRow(t)).join('')}

      <!-- Goals -->
      <div class="section-title">
        <h3>Target tabungan</h3>
        <a onclick="openSheet('goals')">3 aktif →</a>
      </div>
      <div class="acc-carousel">
        ${GOALS.map(g => {
          const pct = Math.round(g.current/g.target*100);
          return `<div class="acc-card" style="flex:0 0 200px">
            <div class="flex-b">
              <div class="eyebrow">${g.by}</div>
              <span class="chip" style="background:${g.color};color:white">${pct}%</span>
            </div>
            <div class="display" style="font-size:18px;margin:10px 0 6px">${g.label}</div>
            <div class="text-xs muted" style="margin-bottom:10px"><b style="color:var(--ink)" class="tabular">${fmtIDRk(g.current)}</b> / ${fmtIDRk(g.target)}</div>
            <div class="bar" style="height:4px"><div class="bar-fill" style="width:${pct}%;background:${g.color}"></div></div>
          </div>`;
        }).join('')}
      </div>
    </div>
  `;
}

function txRow(t) {
  const c = catById(t.cat);
  const acc = ACCOUNTS.find(a => a.id === t.acc);
  return `<div class="tx-row" onclick="openSheet('tx-${t.id}')">
    <div class="cat-icon" style="background:${c.bg};color:${c.color}">${c.emoji}</div>
    <div style="min-width:0">
      <div class="tx-label">${t.label}</div>
      <div class="tx-meta"><span>${acc?.label}</span><span style="opacity:0.4">·</span><span class="mono">${t.when.slice(11,16)}</span>${t.via!=='manual'?' '+viaChip(t.via):''}</div>
    </div>
    <div class="tx-amount ${t.amount<0?'money-neg':'money-pos'}">${fmtIDRk(t.amount)}</div>
  </div>`;
}

let txFilter = 'all';
function setTxFilter(v) { txFilter = v; renderTx(); }
window.setTxFilter = setTxFilter;

function viewTransaksi() {
  return `
    <div class="appbar">
      <div style="flex:1">
        <div class="appbar-title">Transaksi</div>
        <div class="appbar-sub">${TX.length} transaksi · 70% via WA</div>
      </div>
      <div class="appbar-actions">
        <button class="appbar-icon">${ic('search', 16)}</button>
        <button class="appbar-icon">${ic('filter', 14)}</button>
      </div>
    </div>
    <div class="scroll-pills" id="tx-pills"></div>
    <div class="scroll" id="tx-list"></div>
  `;
}

function renderTx() {
  const pillsHost = document.getElementById('tx-pills');
  const listHost = document.getElementById('tx-list');
  if (!pillsHost || !listHost) return;

  pillsHost.innerHTML = `
    ${[['all','Semua'],['expense','Keluar'],['income','Masuk'],['wa','💬 WA'],['receipt','📷 Struk'],['manual','✍️ Manual']].map(([k,l]) =>
      `<button class="pill ${txFilter===k?'active':''}" onclick="setTxFilter('${k}')">${l}</button>`).join('')}
  `;

  const filt = TX.filter(t => {
    if (txFilter === 'all') return true;
    if (txFilter === 'expense') return t.amount < 0;
    if (txFilter === 'income') return t.amount > 0;
    return t.via === txFilter;
  });

  const grouped = {};
  filt.forEach(t => {
    const d = t.when.slice(0,10);
    if (!grouped[d]) grouped[d] = [];
    grouped[d].push(t);
  });

  const totalIn = filt.filter(t=>t.amount>0).reduce((a,b)=>a+b.amount,0);
  const totalOut = filt.filter(t=>t.amount<0).reduce((a,b)=>a+b.amount,0);

  listHost.innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
      <div style="background:rgba(31,138,91,0.08);border-radius:14px;padding:12px">
        <div class="text-xs" style="color:var(--pos);font-weight:500">PEMASUKAN</div>
        <div class="display tabular" style="font-size:18px;margin-top:4px;color:var(--pos)">${fmtIDRk(totalIn)}</div>
      </div>
      <div style="background:rgba(192,56,43,0.06);border-radius:14px;padding:12px">
        <div class="text-xs" style="color:var(--neg);font-weight:500">PENGELUARAN</div>
        <div class="display tabular" style="font-size:18px;margin-top:4px;color:var(--neg)">${fmtIDRk(totalOut)}</div>
      </div>
    </div>
    ${Object.entries(grouped).map(([day, items]) => {
      return `<div class="tx-day-header">${formatDay(day)}</div>${items.map(t => txRow(t)).join('')}`;
    }).join('')}
    ${filt.length === 0 ? '<div style="text-align:center;padding:40px;color:var(--ink-3);font-size:13px">Tidak ada transaksi</div>' : ''}
  `;
}

// ===== CHAT WA (mobile, full screen) =====
const CHAT_SCRIPT = [
  { from:'bot', text:'Halo Rama! Aku Kasku 👋 Catat pengeluaranmu cukup ketik di sini.', time:'08:00' },
  { from:'user', text:'kopi tuku 28rb', time:'09:12' },
  { from:'bot', kind:'typing' },
  { from:'bot', text:'Tercatat ✅', time:'09:12',
    receipt:{type:'Pengeluaran',emoji:'🍚',cat:'Makan & Minum',acc:'GoPay',amount:'Rp28.000',merchant:'Kopi Tuku'}},
  { from:'user', text:'gojek 42rb ke kuningan', time:'09:30' },
  { from:'bot', kind:'typing' },
  { from:'bot', text:'Tercatat ✅', time:'09:30',
    receipt:{type:'Pengeluaran',emoji:'🛵',cat:'Transportasi',acc:'GoPay',amount:'Rp42.000',merchant:'Gojek'}},
  { from:'user', text:'/laporan minggu ini', time:'17:45' },
  { from:'bot', kind:'typing' },
  { from:'bot', kind:'report', time:'17:45',
    summary:{period:'29 Apr — 5 Mei',income:'Rp16.000.000',expense:'Rp4.385.500',top:'Makan & Minum',topPct:'38%'}},
];

let chatTimer = null;

function viewChat() {
  setTimeout(startChat, 100);
  return `
    <div class="wa-mobile-header">
      <span style="opacity:0.85" onclick="setTab('home')">${ic('arrowL', 18)}</span>
      <div class="wa-mobile-avatar">k</div>
      <div style="flex:1">
        <div class="fw-500" style="font-size:15px">Kasku Bot</div>
        <div style="font-size:11px;opacity:0.85">online · &lt;2 detik balasan</div>
      </div>
      <span style="opacity:0.7">${ic('camera', 18)}</span>
      <span style="opacity:0.7">${ic('more', 18)}</span>
    </div>
    <div class="wa-mobile-msgs" id="chat-msgs"></div>
    <div class="wa-mobile-input">
      <span style="color:#888">😊</span>
      <div class="wa-mobile-input-box">Ketik pesan…</div>
      <span style="color:#888">${ic('camera', 20)}</span>
      <div class="wa-mobile-send">${ic('send', 16)}</div>
    </div>
  `;
}

function startChat() {
  const host = document.getElementById('chat-msgs');
  if (!host) return;
  host.innerHTML = '';
  let idx = 0;
  function step() {
    if (idx >= CHAT_SCRIPT.length) {
      chatTimer = setTimeout(() => { idx = 0; host.innerHTML = ''; step(); }, 3500);
      return;
    }
    const m = CHAT_SCRIPT[idx];
    addMsg(host, m);
    host.scrollTop = host.scrollHeight;
    idx++;
    const delay = m.kind === 'typing' ? 1100 : (m.from === 'user' ? 800 : 600);
    chatTimer = setTimeout(() => {
      if (m.kind === 'typing') { const t = host.querySelector('.typing'); if (t) t.remove(); }
      step();
    }, delay);
  }
  step();
}
function stopChat() { if (chatTimer) clearTimeout(chatTimer); chatTimer = null; }

function addMsg(host, m) {
  if (m.kind === 'typing') {
    const div = document.createElement('div');
    div.className = 'typing';
    div.innerHTML = '<span></span><span></span><span></span>';
    host.appendChild(div);
    return;
  }
  const b = document.createElement('div');
  b.className = 'wa-bubble ' + (m.from === 'user' ? 'wa-bubble-out' : 'wa-bubble-in');
  if (m.kind === 'report') {
    const s = m.summary;
    b.innerHTML = `<div style="font-weight:500;margin-bottom:6px">📊 Ringkasan ${s.period}</div>
      <div style="font-size:12px;line-height:1.6">Pemasukan: <b>${s.income}</b><br/>Pengeluaran: <b>${s.expense}</b><br/>Top: <b>${s.top}</b> (${s.topPct})</div>
      <div class="wa-time">${m.time}</div>`;
  } else if (m.receipt) {
    const r = m.receipt;
    b.innerHTML = `<div style="margin-bottom:4px">${m.text}</div>
      <div class="wa-receipt">
        <div class="wa-receipt-row"><span style="opacity:0.6">Tipe</span><b>${r.emoji} ${r.type}</b></div>
        <div class="wa-receipt-row"><span style="opacity:0.6">Kategori</span><b>${r.cat}</b></div>
        <div class="wa-receipt-row"><span style="opacity:0.6">Akun</span><b>${r.acc}</b></div>
        <div class="wa-receipt-row"><span style="opacity:0.6">Merchant</span><b>${r.merchant}</b></div>
        <div class="wa-receipt-row" style="margin-top:6px;padding-top:6px;border-top:1px dashed #ddd"><span>Total</span><b>${r.amount}</b></div>
      </div>
      <div class="wa-time">${m.time}${m.from==='user'?' <span class="read">✓✓</span>':''}</div>`;
  } else {
    b.innerHTML = `<span>${m.text}</span><div class="wa-time">${m.time}${m.from==='user'?' <span class="read">✓✓</span>':''}</div>`;
  }
  host.appendChild(b);
}

// ===== LAPORAN =====
function viewLaporan() {
  const monthTx = TX.filter(t => t.amount < 0 && t.when.startsWith('2026-05'));
  const byCat = {};
  monthTx.forEach(t => { byCat[t.cat] = (byCat[t.cat]||0) + Math.abs(t.amount); });
  const total = Object.values(byCat).reduce((a,b)=>a+b,0);
  const sorted = Object.entries(byCat).sort((a,b)=>b[1]-a[1]);
  let cum = 0;
  const donut = sorted.map(([id,v]) => {
    const c = catById(id);
    const pct = v/total;
    const start = cum*360; cum += pct; const end = cum*360;
    return { c, pct, start, end, v };
  });
  const arc = (cx,cy,r,start,end) => {
    const sx = cx+r*Math.cos((start-90)*Math.PI/180);
    const sy = cy+r*Math.sin((start-90)*Math.PI/180);
    const ex = cx+r*Math.cos((end-90)*Math.PI/180);
    const ey = cy+r*Math.sin((end-90)*Math.PI/180);
    const large = end-start>180?1:0;
    return `M ${cx} ${cy} L ${sx} ${sy} A ${r} ${r} 0 ${large} 1 ${ex} ${ey} Z`;
  };

  const daily = [120,85,210,65,140,0,305,175,95,60,280,130,45,0,220,165,90,110,75,0,195,145,240,80,70,0,120,90,185,142].map(v=>v*1000);
  const maxD = Math.max(...daily);

  return `
    <div class="appbar">
      <div style="flex:1">
        <div class="appbar-title">Laporan</div>
        <div class="appbar-sub">Mei 2026 · analisa keuangan</div>
      </div>
      <div class="appbar-actions">
        <button class="appbar-icon">${ic('cal', 14)}</button>
        <button class="appbar-icon">${ic('download', 14)}</button>
      </div>
    </div>
    <div class="scroll-pills">
      <button class="pill">Mingguan</button>
      <button class="pill active">Bulanan</button>
      <button class="pill">Tahunan</button>
    </div>
    <div class="scroll">
      <div class="card">
        <div class="fw-500" style="font-size:13px;margin-bottom:4px">Breakdown kategori</div>
        <div class="text-xs muted" style="margin-bottom:14px">Pengeluaran Mei 2026</div>
        <div style="display:flex;align-items:center;gap:16px">
          <svg viewBox="0 0 200 200" style="width:130px;height:130px;flex-shrink:0">
            ${donut.map(d => `<path d="${arc(100,100,90,d.start,d.end)}" fill="${d.c.color}" opacity="0.85"/>`).join('')}
            <circle cx="100" cy="100" r="58" fill="#fff"/>
            <text x="100" y="92" text-anchor="middle" font-size="11" fill="#8a8a85">Total</text>
            <text x="100" y="116" text-anchor="middle" font-size="20" fill="#0e0e0c" font-family="Instrument Serif">${fmtIDRk(total)}</text>
          </svg>
          <div style="flex:1;display:flex;flex-direction:column;gap:8px">
            ${donut.slice(0,4).map(d => `
              <div class="flex-b" style="font-size:12px">
                <div class="flex-c gap-2"><span style="width:8px;height:8px;border-radius:2px;background:${d.c.color}"></span>${d.c.emoji} ${d.c.label.split(' ')[0]}</div>
                <div class="tabular fw-500">${Math.round(d.pct*100)}%</div>
              </div>`).join('')}
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:14px">
        <div class="fw-500" style="font-size:13px;margin-bottom:4px">Pengeluaran 30 hari</div>
        <div class="text-xs muted" style="margin-bottom:12px">Rata-rata Rp${Math.round(daily.reduce((a,b)=>a+b,0)/daily.length/1000)}rb/hari</div>
        <svg viewBox="0 0 320 100" style="width:100%;height:100px">
          ${daily.map((v,i) => {
            const x = 4 + i*(312/30);
            const h = (v/maxD)*82;
            return `<rect x="${x}" y="${92-h}" width="${312/30-2}" height="${h}" fill="${v===0?'#e8e8e3':'#0e0e0c'}" rx="2"/>`;
          }).join('')}
          <line x1="0" x2="320" y1="92" y2="92" stroke="#e8e8e3"/>
        </svg>
        <div class="flex-b text-xs muted" style="margin-top:6px"><span>7 Apr</span><span>22 Apr</span><span>6 Mei</span></div>
      </div>

      <div class="section-title"><h3>Top merchant</h3></div>
      <div class="card" style="padding:0">
        ${[
          {n:'Tokopedia',cat:'shop',count:3,amt:1820000},
          {n:'GoFood',cat:'food',count:6,amt:412000},
          {n:'Indomaret',cat:'food',count:4,amt:325500},
          {n:'Gojek',cat:'transport',count:8,amt:248000},
        ].map((m,i,arr) => {
          const c = catById(m.cat);
          return `<div class="flex-b" style="padding:12px 14px;${i<arr.length-1?'border-bottom:1px solid var(--line)':''}">
            <div class="flex-c gap-3">
              <div class="cat-icon" style="background:${c.bg};color:${c.color};width:32px;height:32px;font-size:14px">${c.emoji}</div>
              <div>
                <div class="fw-500" style="font-size:12.5px">${m.n}</div>
                <div class="text-xs muted">${m.count} tx</div>
              </div>
            </div>
            <div class="tabular fw-500 money-neg" style="font-size:13px">${fmtIDRk(-m.amt)}</div>
          </div>`;
        }).join('')}
      </div>

      <div class="section-title"><h3>Insights AI</h3><a>${ic('sparkle', 12)}</a></div>
      ${[
        {ico:'🍚',t:'Makan turun 18% vs April',s:'Pertahankan kebiasaan masak di rumah',col:'var(--pos)'},
        {ico:'🛍️',t:'Belanja over budget Rp62.500',s:'Pertimbangkan naikkan limit ke Rp1.6jt',col:'var(--neg)'},
        {ico:'☕',t:'Kopi Tuku 4x minggu ini',s:'Total Rp112rb — coba kopi seduh sendiri',col:'var(--warn)'},
      ].map(i => `<div class="card" style="margin-bottom:8px;border-left:3px solid ${i.col};display:flex;gap:12px;align-items:flex-start">
        <div style="font-size:22px">${i.ico}</div>
        <div>
          <div class="fw-500" style="font-size:13px">${i.t}</div>
          <div class="text-xs muted" style="margin-top:3px;line-height:1.5">${i.s}</div>
        </div>
      </div>`).join('')}
    </div>
  `;
}

// ===== AKUN =====
function viewAkun() {
  const total = ACCOUNTS.reduce((a,b)=>a+b.balance,0);
  return `
    <div class="appbar">
      <div style="flex:1">
        <div class="appbar-title">Akun & Dompet</div>
        <div class="appbar-sub">5 sumber dana · ${fmtIDR(total)}</div>
      </div>
      <div class="appbar-actions">
        <button class="appbar-icon">${ic('plus', 16)}</button>
        <button class="appbar-icon">${ic('settings', 14)}</button>
      </div>
    </div>
    <div class="scroll">
      <!-- Total summary -->
      <div class="balance-card" style="margin-bottom:16px">
        <div class="eyebrow" style="color:rgba(255,255,255,0.5);position:relative;z-index:1">Total saldo gabungan</div>
        <div class="display" style="font-size:32px;margin-top:6px;position:relative;z-index:1">${fmtIDR(total)}</div>
        <svg viewBox="0 0 320 60" style="width:100%;height:50px;margin-top:14px;position:relative;z-index:1">
          ${(() => {
            const pts = [];
            let v = 22000000;
            for (let i = 0; i < 30; i++) {
              v += (Math.sin(i*0.4)+0.3)*200000 + (i%7===0?500000:-i*30000);
              pts.push(v);
            }
            const max = Math.max(...pts), min = Math.min(...pts), r = max-min;
            const path = pts.map((vv,i) => `${i===0?'M':'L'} ${i*(320/29)} ${50 - ((vv-min)/r)*44 + 4}`).join(' ');
            return `<path d="${path}" fill="none" stroke="white" stroke-width="1.5" opacity="0.7"/>`;
          })()}
        </svg>
      </div>

      <!-- Account cards -->
      ${ACCOUNTS.map(a => {
        const txs = TX.filter(t => t.acc === a.id);
        const inflow = txs.filter(t=>t.amount>0).reduce((s,t)=>s+t.amount,0);
        const outflow = txs.filter(t=>t.amount<0).reduce((s,t)=>s+t.amount,0);
        return `<div class="card" style="margin-bottom:10px;position:relative;overflow:hidden">
          <div style="position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:${a.color};opacity:0.07"></div>
          <div class="flex-b" style="margin-bottom:14px;position:relative">
            <div class="flex-c gap-3">
              <div style="width:38px;height:38px;border-radius:10px;background:${a.color};color:white;display:grid;place-items:center;font-weight:600;font-size:13px">${a.label.slice(0,2).toUpperCase()}</div>
              <div>
                <div class="fw-500" style="font-size:13.5px">${a.label}</div>
                <div class="text-xs muted">${a.type} ${a.last!=='—'?'·••'+a.last:''}</div>
              </div>
            </div>
            <button style="color:var(--ink-3)">${ic('more', 16)}</button>
          </div>
          <div class="display tabular" style="font-size:24px">${fmtIDR(a.balance)}</div>
          <div class="flex-c gap-4" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line)">
            <div style="flex:1">
              <div class="text-xs muted">Masuk</div>
              <div class="tabular fw-500 money-pos" style="font-size:12px;margin-top:2px">${fmtIDRk(inflow)}</div>
            </div>
            <div style="flex:1">
              <div class="text-xs muted">Keluar</div>
              <div class="tabular fw-500 money-neg" style="font-size:12px;margin-top:2px">${fmtIDRk(outflow)}</div>
            </div>
            <div style="flex:1">
              <div class="text-xs muted">Tx</div>
              <div class="tabular fw-500" style="font-size:12px;margin-top:2px">${txs.length}</div>
            </div>
          </div>
        </div>`;
      }).join('')}

      <button class="card" style="width:100%;border-style:dashed;display:flex;align-items:center;justify-content:center;gap:10px;color:var(--ink-3);padding:20px;background:transparent">
        <span>${ic('plus', 16)}</span>
        <span class="fw-500" style="font-size:13px">Hubungkan akun baru</span>
      </button>

      <!-- Settings -->
      <div class="section-title"><h3>Pengaturan</h3></div>
      <div class="card" style="padding:0">
        ${[
          {ico:'🏷️', l:'Kategori & Budget', act:"openSheet('budget')"},
          {ico:'🎯', l:'Target & Goals', act:"openSheet('goals')"},
          {ico:'🔔', l:'Notifikasi', act:""},
          {ico:'💬', l:'WhatsApp tersambung', act:"", sub:'+62 812 8731 4422'},
          {ico:'🔒', l:'Privasi & keamanan', act:""},
          {ico:'❓', l:'Bantuan', act:""},
        ].map((x,i,arr) => `<button onclick="${x.act}" style="width:100%;display:flex;align-items:center;gap:12px;padding:14px;${i<arr.length-1?'border-bottom:1px solid var(--line)':''};text-align:left;background:transparent">
          <span style="font-size:18px">${x.ico}</span>
          <div style="flex:1">
            <div class="fw-500" style="font-size:13px">${x.l}</div>
            ${x.sub?`<div class="text-xs muted mono" style="margin-top:2px">${x.sub}</div>`:''}
          </div>
          <span style="color:var(--ink-3)">${ic('arrowR', 14)}</span>
        </button>`).join('')}
      </div>

      <div style="text-align:center;font-size:11px;color:var(--ink-3);margin-top:24px">
        Kasku v1.0.0 · Mei 2026
      </div>
    </div>
  `;
}

// ===== SHEETS =====
function sheetTx(id) {
  const t = TX.find(x => x.id === id);
  if (!t) return '';
  const c = catById(t.cat);
  const acc = ACCOUNTS.find(a => a.id === t.acc);
  return `
    <div class="sheet-handle"></div>
    <div style="padding:16px 20px 0;display:flex;justify-content:space-between;align-items:center">
      <span class="eyebrow">Detail Transaksi</span>
      <button onclick="closeSheet()" style="color:var(--ink-3)">${ic('x', 18)}</button>
    </div>
    <div style="padding:20px 20px 0">
      <div class="cat-icon" style="width:52px;height:52px;background:${c.bg};color:${c.color};font-size:22px">${c.emoji}</div>
      <div class="display" style="font-size:32px;margin-top:14px;color:${t.amount>0?'var(--pos)':'var(--ink)'}">${fmtIDR(t.amount)}</div>
      <div class="fw-500" style="font-size:15px;margin-top:2px">${t.label}</div>
      <div class="muted text-xs" style="margin-top:4px">${new Date(t.when).toLocaleString('id-ID',{dateStyle:'full',timeStyle:'short'})}</div>

      <div class="divider"></div>
      <div style="display:flex;flex-direction:column;gap:12px;font-size:12.5px">
        <div class="flex-b"><span class="muted">Kategori</span><span class="fw-500">${c.emoji} ${c.label}</span></div>
        <div class="flex-b"><span class="muted">Akun</span><span class="fw-500"><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:${acc.color};margin-right:6px"></span>${acc.label}</span></div>
        <div class="flex-b"><span class="muted">Merchant</span><span class="fw-500">${t.merchant}</span></div>
        <div class="flex-b"><span class="muted">Sumber</span>${t.via==='wa'?`<span class="chip chip-wa">${ic('wa',10)}WhatsApp</span>`:t.via==='receipt'?`<span class="chip chip-wa">${ic('camera',10)}Foto struk</span>`:'<span class="chip">Manual</span>'}</div>
      </div>

      ${t.via==='wa'?`<div style="background:var(--wa-bg);padding:12px;border-radius:10px;margin-top:18px">
        <div class="text-xs" style="color:var(--wa-deep);margin-bottom:6px">${ic('wa',10)} Pesan asli</div>
        <div class="mono" style="font-size:12px;color:var(--wa-ink)">${t.note}</div>
        <div class="text-xs" style="color:var(--wa-deep);margin-top:6px">${ic('sparkle',10)} Diparse otomatis · 96% confidence</div>
      </div>`:''}

      <div style="background:var(--bg-sunken);padding:12px;border-radius:10px;margin-top:14px;display:flex;gap:10px">
        <div style="width:30px;height:30px;border-radius:8px;background:var(--ink);color:white;display:grid;place-items:center;flex-shrink:0">${ic('sparkle',12)}</div>
        <div>
          <div class="fw-500 text-sm">Saran AI</div>
          <div class="text-xs muted" style="margin-top:3px;line-height:1.5">Anda sudah Rp1.6jt untuk Makan & Minum minggu ini. Pertimbangkan masak di rumah 2x.</div>
        </div>
      </div>

      <div style="display:flex;gap:8px;margin-top:18px">
        <button class="btn" style="flex:1">Edit</button>
        <button class="btn" style="flex:1;color:var(--neg)">Hapus</button>
      </div>
    </div>
  `;
}

function sheetBudget() {
  const totalLimit = BUDGETS.reduce((a,b)=>a+b.limit,0);
  const totalSpent = BUDGETS.reduce((a,b)=>a+b.spent,0);
  return `
    <div class="sheet-handle"></div>
    <div style="padding:16px 20px 0;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="display" style="font-size:24px">Kategori & Budget</div>
        <div class="text-xs muted" style="margin-top:2px">${BUDGETS.length} kategori dengan limit aktif</div>
      </div>
      <button onclick="closeSheet()" style="color:var(--ink-3)">${ic('x', 18)}</button>
    </div>
    <div style="padding:20px">
      <div class="card" style="margin-bottom:12px">
        <div class="flex-b"><span class="eyebrow">Total bulan ini</span><span class="chip">${Math.round(totalSpent/totalLimit*100)}%</span></div>
        <div class="display tabular" style="font-size:24px;margin-top:6px">${fmtIDR(totalSpent)}</div>
        <div class="text-xs muted" style="margin-top:2px">dari ${fmtIDR(totalLimit)}</div>
        <div class="bar" style="margin-top:12px"><div class="bar-fill" style="width:${Math.round(totalSpent/totalLimit*100)}%"></div></div>
      </div>

      ${BUDGETS.map(b => {
        const c = catById(b.cat);
        const pct = Math.round(b.spent/b.limit*100);
        const over = b.spent > b.limit;
        return `<div class="card" style="margin-bottom:8px">
          <div class="flex-b">
            <div class="flex-c gap-3">
              <div class="cat-icon" style="background:${c.bg};color:${c.color};width:34px;height:34px;font-size:15px">${c.emoji}</div>
              <div>
                <div class="fw-500" style="font-size:13px">${c.label}</div>
                <div class="text-xs muted tabular">${fmtIDRk(b.spent)} / ${fmtIDRk(b.limit)}</div>
              </div>
            </div>
            ${over?'<span class="chip chip-neg">Over</span>':pct>80?'<span class="chip chip-warn">Hampir</span>':'<span class="chip chip-pos">Aman</span>'}
          </div>
          <div class="bar" style="height:6px;margin-top:10px"><div class="bar-fill" style="width:${Math.min(100,pct)}%;background:${over?'var(--neg)':(pct>80?'var(--warn)':c.color)}"></div></div>
        </div>`;
      }).join('')}

      <div class="section-title"><h3>Semua kategori</h3></div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
        ${CATEGORIES.map(c => `<div class="card" style="padding:12px;text-align:center">
          <div style="font-size:22px;margin-bottom:6px">${c.emoji}</div>
          <div class="text-xs fw-500">${c.label}</div>
        </div>`).join('')}
      </div>
    </div>
  `;
}

function sheetGoals() {
  return `
    <div class="sheet-handle"></div>
    <div style="padding:16px 20px 0;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="display" style="font-size:24px">Target Tabungan</div>
        <div class="text-xs muted" style="margin-top:2px">${GOALS.length} target aktif</div>
      </div>
      <button onclick="closeSheet()" style="color:var(--ink-3)">${ic('x', 18)}</button>
    </div>
    <div style="padding:20px">
      ${GOALS.map(g => {
        const pct = Math.round(g.current/g.target*100);
        const remain = g.target - g.current;
        return `<div class="card" style="margin-bottom:10px;position:relative;overflow:hidden">
          <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:${g.color};opacity:0.1"></div>
          <div class="flex-b" style="position:relative">
            <span class="eyebrow">Target ${g.by}</span>
            <span class="chip" style="background:${g.color};color:white">${pct}%</span>
          </div>
          <div class="display" style="font-size:22px;margin:10px 0 4px">${g.label}</div>
          <div class="text-xs muted">Sisa <b style="color:var(--ink)" class="tabular">${fmtIDR(remain)}</b> · ${fmtIDR(g.current)} / ${fmtIDR(g.target)}</div>
          <div class="bar" style="height:6px;margin-top:12px"><div class="bar-fill" style="width:${pct}%;background:${g.color}"></div></div>
        </div>`;
      }).join('')}
      <button class="card" style="width:100%;border-style:dashed;display:flex;align-items:center;justify-content:center;gap:10px;color:var(--ink-3);padding:20px;background:transparent">
        <span>${ic('plus', 16)}</span>
        <span class="fw-500" style="font-size:13px">Buat target baru</span>
      </button>
    </div>
  `;
}

function sheetAdd() {
  return `
    <div class="sheet-handle"></div>
    <div style="padding:16px 20px 0;display:flex;justify-content:space-between;align-items:center">
      <div class="display" style="font-size:24px">Tambah Transaksi</div>
      <button onclick="closeSheet()" style="color:var(--ink-3)">${ic('x', 18)}</button>
    </div>
    <div style="padding:20px">
      <div class="text-xs muted" style="margin-bottom:12px;text-transform:uppercase;letter-spacing:0.08em;font-weight:500">CARA INPUT</div>
      ${[
        {ico:'💬', t:'Kirim chat ke Kasku', s:'"kopi 25rb" / "/expense 50rb makan"', col:'var(--wa)', act:"setTab('chat'); closeSheet()"},
        {ico:'📷', t:'Foto struk', s:'AI parse merchant, total, dan kategori', col:'var(--ink)', act:""},
        {ico:'⌨️', t:'Input manual', s:'Form lengkap dengan semua field', col:'var(--ink-2)', act:""},
        {ico:'🔄', t:'Transfer antar akun', s:'BCA → Tabungan, e-wallet top-up', col:'var(--info)', act:""},
      ].map(x => `<button onclick="${x.act}" class="card" style="width:100%;display:flex;gap:14px;align-items:center;text-align:left;margin-bottom:10px">
        <div style="width:46px;height:46px;border-radius:14px;background:${x.col};color:white;display:grid;place-items:center;font-size:22px;flex-shrink:0">${x.ico}</div>
        <div style="flex:1">
          <div class="fw-500" style="font-size:14px">${x.t}</div>
          <div class="text-xs muted" style="margin-top:2px">${x.s}</div>
        </div>
        <span style="color:var(--ink-3)">${ic('arrowR', 16)}</span>
      </button>`).join('')}

      <div style="background:var(--wa-bg);border-radius:14px;padding:14px;margin-top:8px">
        <div class="flex-c gap-2" style="color:var(--wa-deep);font-size:11px;font-weight:500;margin-bottom:6px">${ic('wa',12)} TIPS</div>
        <div class="text-xs" style="color:var(--wa-ink);line-height:1.6">
          Cara tercepat: <b>kirim chat ke Kasku Bot</b>. Cukup tulis "beli kopi 25rb" atau "/laporan minggu ini". Bot bekerja dalam &lt;2 detik.
        </div>
      </div>
    </div>
  `;
}

window.openSheet = (key) => {
  const sheet = document.getElementById('sheet');
  let html = '';
  if (key === 'add') html = sheetAdd();
  else if (key === 'budget') html = sheetBudget();
  else if (key === 'goals') html = sheetGoals();
  else if (key.startsWith('tx-')) html = sheetTx(key.slice(3));
  sheet.innerHTML = html;
  sheet.classList.add('open');
  document.getElementById('sheet-overlay').classList.add('open');
};
window.closeSheet = () => {
  document.getElementById('sheet').classList.remove('open');
  document.getElementById('sheet-overlay').classList.remove('open');
};

// ===== TAB ROUTING =====
const VIEWS = {
  home: viewDashboard,
  transaksi: viewTransaksi,
  chat: viewChat,
  laporan: viewLaporan,
  akun: viewAkun,
};

function setTab(tab) {
  stopChat();
  document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  const host = document.getElementById('view-host');
  host.innerHTML = `<div class="view active" id="v-${tab}">${VIEWS[tab]()}</div>`;

  if (tab === 'transaksi') renderTx();

  // Hide FAB on chat (would conflict with WA send button), and on laporan/akun
  const fab = document.getElementById('fab');
  fab.style.display = (tab === 'home' || tab === 'transaksi') ? 'grid' : 'none';
}
window.setTab = setTab;

document.querySelectorAll('.tab').forEach(t => {
  t.addEventListener('click', () => setTab(t.dataset.tab));
});

setTab('home');
})();
