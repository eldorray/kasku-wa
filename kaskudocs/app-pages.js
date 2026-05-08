// Kasku — pages: Transaksi, Kategori, Laporan, Akun
(function(){
const { fmtIDR: fI, fmtIDRk: fIk, CATEGORIES: CAT, catById: cb, ACCOUNTS: AC, TX: T, BUDGETS: BG, MONTHLY: M, GOALS: G } = window.KK;

// ===== TRANSAKSI =====
const txState = { type:'all', via:'all', cat:'all' };

function renderTransaksi() {
  const filtered = T.filter(t => {
    if (txState.type === 'income' && t.amount < 0) return false;
    if (txState.type === 'expense' && t.amount > 0) return false;
    if (txState.via !== 'all' && t.via !== txState.via) return false;
    if (txState.cat !== 'all' && t.cat !== txState.cat) return false;
    return true;
  });

  const totalIn = filtered.filter(t => t.amount > 0).reduce((a,b)=>a+b.amount,0);
  const totalOut = filtered.filter(t => t.amount < 0).reduce((a,b)=>a+b.amount,0);

  // group by date
  const grouped = {};
  filtered.forEach(t => {
    const d = t.when.slice(0,10);
    if (!grouped[d]) grouped[d] = [];
    grouped[d].push(t);
  });

  const formatDay = (d) => {
    if (d === '2026-05-06') return 'Hari ini · Rabu, 6 Mei';
    if (d === '2026-05-05') return 'Kemarin · Selasa, 5 Mei';
    return new Date(d).toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long' });
  };

  return `
    <div class="page-hd">
      <div>
        <div class="eyebrow" style="margin-bottom:6px">Transaksi</div>
        <h1 class="page-title">Semua Transaksi</h1>
        <div class="page-sub">${filtered.length} transaksi · 70% dicatat otomatis dari WhatsApp</div>
      </div>
      <div class="flex gap-3">
        <button class="btn">${window.icon('filter')} Filter lanjutan</button>
        <button class="btn btn-primary">${window.icon('plus',14)} Tambah manual</button>
      </div>
    </div>

    <div class="grid grid-3" style="margin-bottom:24px">
      <div class="card">
        <div class="eyebrow">Pemasukan</div>
        <div class="display" style="font-size:24px;margin-top:8px;color:var(--pos)">${fI(totalIn)}</div>
      </div>
      <div class="card">
        <div class="eyebrow">Pengeluaran</div>
        <div class="display" style="font-size:24px;margin-top:8px;color:var(--neg)">${fI(totalOut)}</div>
      </div>
      <div class="card">
        <div class="eyebrow">Net</div>
        <div class="display" style="font-size:24px;margin-top:8px">${fI(totalIn + totalOut)}</div>
      </div>
    </div>

    <div class="flex" style="gap:16px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
      <div class="tabs">
        ${[['all','Semua'],['expense','Pengeluaran'],['income','Pemasukan']].map(([k,l]) =>
          `<button class="tab ${txState.type===k?'active':''}" onclick="setTxFilter('type','${k}')">${l}</button>`).join('')}
      </div>
      <div class="pills">
        <button class="pill ${txState.via==='all'?'active':''}" onclick="setTxFilter('via','all')">Semua sumber</button>
        <button class="pill ${txState.via==='wa'?'active':''}" onclick="setTxFilter('via','wa')">💬 Chat WA</button>
        <button class="pill ${txState.via==='receipt'?'active':''}" onclick="setTxFilter('via','receipt')">📷 Foto struk</button>
        <button class="pill ${txState.via==='manual'?'active':''}" onclick="setTxFilter('via','manual')">✍️ Manual</button>
      </div>
      <div class="pills">
        <button class="pill ${txState.cat==='all'?'active':''}" onclick="setTxFilter('cat','all')">Semua kategori</button>
        ${CAT.slice(0,5).map(c =>
          `<button class="pill ${txState.cat===c.id?'active':''}" onclick="setTxFilter('cat','${c.id}')">${c.emoji} ${c.label}</button>`).join('')}
      </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
      ${Object.entries(grouped).map(([day, items]) => {
        const dIn = items.filter(t=>t.amount>0).reduce((a,b)=>a+b.amount,0);
        const dOut = items.filter(t=>t.amount<0).reduce((a,b)=>a+b.amount,0);
        return `
          <div class="flex-b" style="padding:12px 20px;background:var(--bg-sunken);border-top:1px solid var(--line);border-bottom:1px solid var(--line);font-size:12px">
            <div class="fw-500">${formatDay(day)}</div>
            <div class="flex gap-3 muted tabular text-xs">
              ${dIn>0?`<span style="color:var(--pos)">+${fI(dIn)}</span>`:''}
              ${dOut<0?`<span style="color:var(--neg)">${fI(dOut)}</span>`:''}
            </div>
          </div>
          ${items.map(t => {
            const c = cb(t.cat);
            const acc = AC.find(a => a.id === t.acc);
            return `<div onclick="openTx('${t.id}')" style="display:grid;grid-template-columns:44px 1fr auto auto;gap:16px;align-items:center;padding:14px 20px;border-bottom:1px solid var(--line);cursor:pointer" onmouseover="this.style.background='var(--bg-sunken)'" onmouseout="this.style.background='transparent'">
              <div class="cat-icon" style="background:${c.bg};color:${c.color}">${c.emoji}</div>
              <div style="min-width:0">
                <div class="fw-500" style="font-size:14px">${t.label}</div>
                <div class="text-xs muted" style="margin-top:3px;display:flex;gap:8px;align-items:center">
                  <span>${c.label}</span><span style="opacity:0.4">·</span><span>${acc.label}</span><span style="opacity:0.4">·</span><span class="mono">${t.when.slice(11,16)}</span>
                </div>
              </div>
              <div>${window.viaChip(t.via)}</div>
              <div class="money ${t.amount<0?'money-neg':'money-pos'}" style="font-weight:500;font-size:14px;min-width:120px;text-align:right">${fI(t.amount)}</div>
            </div>`;
          }).join('')}
        `;
      }).join('')}
    </div>
  `;
}

function setTxFilter(key, val) {
  txState[key] = val;
  document.getElementById('page-transaksi').innerHTML = renderTransaksi();
}
window.setTxFilter = setTxFilter;
window.renderTransaksi = renderTransaksi;

// ===== TRANSACTION DETAIL DRAWER =====
function openTx(id) {
  const t = T.find(x => x.id === id);
  if (!t) return;
  const c = cb(t.cat);
  const acc = AC.find(a => a.id === t.acc);
  const drawerHTML = `
    <div class="overlay" onclick="closeTx()"></div>
    <div class="drawer">
      <div class="flex-b" style="padding:20px;border-bottom:1px solid var(--line)">
        <div class="eyebrow">Detail Transaksi</div>
        <button class="icon-btn" onclick="closeTx()">${window.icon('x')}</button>
      </div>
      <div style="padding:24px">
        <div class="cat-icon" style="width:56px;height:56px;border-radius:14px;background:${c.bg};color:${c.color};font-size:24px">${c.emoji}</div>
        <div class="display" style="font-size:36px;margin-top:16px;margin-bottom:4px;color:${t.amount>0?'var(--pos)':'var(--ink)'}">${fI(t.amount)}</div>
        <div style="font-size:16px;font-weight:500">${t.label}</div>
        <div class="muted text-sm" style="margin-top:4px">${new Date(t.when).toLocaleString('id-ID',{dateStyle:'full',timeStyle:'short'})}</div>

        <div class="divider"></div>

        <div style="display:flex;flex-direction:column;gap:14px;font-size:13px">
          ${row('Tipe', t.amount>0?'Pemasukan':'Pengeluaran')}
          ${row('Kategori', `<span style="margin-right:6px">${c.emoji}</span>${c.label}`)}
          ${row('Akun', `<span class="flex-c gap-2"><span style="width:10px;height:10px;border-radius:3px;background:${acc.color}"></span>${acc.label} <span class="muted text-xs">·••${acc.last}</span></span>`)}
          ${row('Merchant', t.merchant)}
          ${row('Sumber input', t.via==='wa'?`<span class="chip chip-wa">${window.icon('wa',11)}WhatsApp chat</span>`:t.via==='receipt'?`<span class="chip chip-wa">${window.icon('camera',11)}Foto struk</span>`:`<span class="chip">Input manual</span>`)}
        </div>

        ${t.via === 'wa' ? `
          <div style="background:var(--wa-bg);padding:14px;border-radius:10px;margin-top:20px">
            <div class="text-xs" style="color:var(--wa-deep);margin-bottom:6px;display:flex;align-items:center;gap:6px">${window.icon('wa',11)} Pesan asli</div>
            <div class="mono" style="font-size:13px;color:var(--wa-ink)">${t.note}</div>
            <div class="text-xs" style="color:var(--wa-deep);margin-top:8px;display:flex;align-items:center;gap:6px">${window.icon('sparkle',11)} Diparse otomatis dengan kepercayaan 96%</div>
          </div>
        ` : ''}

        <div class="divider"></div>
        <div class="eyebrow" style="margin-bottom:10px">Catatan</div>
        <div class="text-sm" style="color:var(--ink-2)">${t.note}</div>

        <div style="display:flex;gap:10px;margin-top:28px">
          <button class="btn" style="flex:1;justify-content:center">Edit</button>
          <button class="btn" style="flex:1;justify-content:center;color:var(--neg);border-color:transparent">Hapus</button>
        </div>

        <div style="margin-top:24px;padding:14px;border-radius:10px;background:var(--bg-sunken);display:flex;gap:12px;align-items:flex-start">
          <div style="width:32px;height:32px;border-radius:8px;background:var(--ink);color:white;display:grid;place-items:center;flex-shrink:0">${window.icon('sparkle',14)}</div>
          <div>
            <div class="fw-500 text-sm">Saran AI</div>
            <div class="text-xs muted" style="margin-top:4px;line-height:1.5">Anda sudah menghabiskan Rp1.6jt untuk Makan & Minum minggu ini, mendekati budget bulanan. Pertimbangkan masak di rumah 2x.</div>
          </div>
        </div>
      </div>
    </div>
  `;
  document.getElementById('drawer-host').innerHTML = drawerHTML;
}
function closeTx() { document.getElementById('drawer-host').innerHTML = ''; }
function row(label, val) { return `<div class="flex-b"><div class="muted">${label}</div><div class="fw-500">${val}</div></div>`; }
window.openTx = openTx;
window.closeTx = closeTx;

// ===== KATEGORI & BUDGET =====
function renderKategori() {
  return `
    <div class="page-hd">
      <div>
        <div class="eyebrow" style="margin-bottom:6px">Kategori & Budget</div>
        <h1 class="page-title">Kelola Pengeluaran</h1>
        <div class="page-sub">Atur batas bulanan tiap kategori, dapatkan notifikasi di WhatsApp saat mendekati limit.</div>
      </div>
      <button class="btn btn-primary">${window.icon('plus',14)} Kategori baru</button>
    </div>

    <div class="grid grid-3" style="margin-bottom:24px">
      <div class="card">
        <div class="eyebrow">Total budget bulanan</div>
        <div class="display" style="font-size:30px;margin-top:8px">${fI(BG.reduce((a,b)=>a+b.limit,0))}</div>
      </div>
      <div class="card">
        <div class="eyebrow">Terpakai bulan ini</div>
        <div class="display" style="font-size:30px;margin-top:8px">${fI(BG.reduce((a,b)=>a+b.spent,0))}</div>
        <div class="text-xs muted" style="margin-top:8px">${Math.round(BG.reduce((a,b)=>a+b.spent,0)/BG.reduce((a,b)=>a+b.limit,0)*100)}% dari budget</div>
      </div>
      <div class="card" style="background:rgba(196,122,20,0.08);border-color:transparent">
        <div class="eyebrow" style="color:var(--warn)">Perlu perhatian</div>
        <div class="display" style="font-size:30px;margin-top:8px;color:var(--warn)">2 kategori</div>
        <div class="text-xs" style="color:var(--warn);margin-top:8px">Belanja over-budget · Hiburan 62% ke limit</div>
      </div>
    </div>

    <div class="grid grid-2">
      ${BG.map(b => {
        const c = cb(b.cat);
        const pct = Math.round(b.spent / b.limit * 100);
        const over = b.spent > b.limit;
        const remain = b.limit - b.spent;
        return `<div class="card">
          <div class="flex-b" style="margin-bottom:16px">
            <div class="flex gap-3" style="align-items:center">
              <div class="cat-icon" style="width:40px;height:40px;background:${c.bg};color:${c.color};font-size:18px">${c.emoji}</div>
              <div>
                <div class="fw-500">${c.label}</div>
                <div class="text-xs muted" style="margin-top:2px">${T.filter(t=>t.cat===b.cat&&t.amount<0).length} transaksi</div>
              </div>
            </div>
            ${over ? '<span class="chip chip-neg">Over budget</span>' : pct>80 ? '<span class="chip chip-warn">Hampir habis</span>' : '<span class="chip chip-pos">Aman</span>'}
          </div>
          <div class="flex-b" style="margin-bottom:8px">
            <div class="display tabular" style="font-size:24px">${fI(b.spent)}</div>
            <div class="muted text-sm">/ ${fI(b.limit)}</div>
          </div>
          <div class="bar" style="height:8px"><div class="bar-fill" style="width:${Math.min(100,pct)}%;background:${over?'var(--neg)':(pct>80?'var(--warn)':c.color)}"></div></div>
          <div class="flex-b" style="margin-top:10px;font-size:12px">
            <span class="muted">${pct}% terpakai</span>
            <span class="${over?'money-neg':'muted'} fw-500 tabular">${over?'Over '+fI(Math.abs(remain)):'Sisa '+fI(remain)}</span>
          </div>
        </div>`;
      }).join('')}
    </div>

    <div style="margin-top:32px">
      <div class="card-title" style="margin-bottom:16px">Semua kategori</div>
      <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
        ${CAT.map(c => {
          const txCount = T.filter(t => t.cat === c.id).length;
          const total = T.filter(t => t.cat === c.id && t.amount < 0).reduce((a,b)=>a+b.amount,0);
          return `<div class="card" style="padding:16px">
            <div class="cat-icon" style="background:${c.bg};color:${c.color};font-size:18px;margin-bottom:12px">${c.emoji}</div>
            <div class="fw-500" style="font-size:13px">${c.label}</div>
            <div class="text-xs muted" style="margin-top:4px">${txCount} tx · ${fIk(total)}</div>
          </div>`;
        }).join('')}
      </div>
    </div>
  `;
}
window.renderKategori = renderKategori;

// ===== LAPORAN =====
function renderLaporan() {
  // Pie segments by category for current month
  const monthTx = T.filter(t => t.amount < 0 && t.when.startsWith('2026-05'));
  const byCat = {};
  monthTx.forEach(t => { byCat[t.cat] = (byCat[t.cat] || 0) + Math.abs(t.amount); });
  const total = Object.values(byCat).reduce((a,b)=>a+b, 0);
  const sorted = Object.entries(byCat).sort((a,b) => b[1]-a[1]);

  // Donut
  let cum = 0;
  const donut = sorted.map(([id, v]) => {
    const c = cb(id);
    const pct = v / total;
    const start = cum * 360;
    cum += pct;
    const end = cum * 360;
    return { c, pct, start, end, v };
  });

  function arc(cx, cy, r, start, end) {
    const sx = cx + r * Math.cos((start-90) * Math.PI/180);
    const sy = cy + r * Math.sin((start-90) * Math.PI/180);
    const ex = cx + r * Math.cos((end-90) * Math.PI/180);
    const ey = cy + r * Math.sin((end-90) * Math.PI/180);
    const large = end - start > 180 ? 1 : 0;
    return `M ${cx} ${cy} L ${sx} ${sy} A ${r} ${r} 0 ${large} 1 ${ex} ${ey} Z`;
  }

  // Daily bars (last 30 days)
  const daily = [120,85,210,65,140,0,305,175,95,60,280,130,45,0,220,165,90,110,75,0,195,145,240,80,70,0,120,90,185,142].map(v=>v*1000);
  const maxDaily = Math.max(...daily);

  return `
    <div class="page-hd">
      <div>
        <div class="eyebrow" style="margin-bottom:6px">Laporan</div>
        <h1 class="page-title">Analisa Keuangan</h1>
        <div class="page-sub">Tren bulanan, breakdown kategori, dan pola pengeluaran harian.</div>
      </div>
      <div class="flex gap-3">
        <div class="tabs">
          <button class="tab">Mingguan</button>
          <button class="tab active">Bulanan</button>
          <button class="tab">Tahunan</button>
        </div>
        <button class="btn">${window.icon('download')} PDF</button>
      </div>
    </div>

    <div class="grid" style="grid-template-columns:1fr 1.4fr;margin-bottom:20px">
      <div class="card">
        <div class="card-hd">
          <div>
            <div class="card-title">Breakdown kategori</div>
            <div class="card-sub">Pengeluaran Mei 2026</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:24px">
          <svg viewBox="0 0 200 200" style="width:200px;height:200px;flex-shrink:0">
            ${donut.map(d => `<path d="${arc(100,100,90,d.start,d.end)}" fill="${d.c.color}" opacity="0.85"/>`).join('')}
            <circle cx="100" cy="100" r="58" fill="var(--bg-elev)"/>
            <text x="100" y="92" text-anchor="middle" font-size="11" fill="var(--ink-3)" font-family="var(--font-sans)">Total</text>
            <text x="100" y="112" text-anchor="middle" font-size="18" fill="var(--ink)" font-family="var(--font-display)">${fIk(total)}</text>
          </svg>
          <div style="flex:1;display:flex;flex-direction:column;gap:10px">
            ${donut.slice(0,5).map(d => `
              <div class="flex-b text-sm">
                <div class="flex gap-2" style="align-items:center">
                  <span style="width:10px;height:10px;border-radius:3px;background:${d.c.color}"></span>
                  <span>${d.c.emoji} ${d.c.label}</span>
                </div>
                <div class="tabular fw-500">${Math.round(d.pct*100)}%</div>
              </div>
            `).join('')}
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-hd">
          <div>
            <div class="card-title">Pengeluaran harian — 30 hari terakhir</div>
            <div class="card-sub">Rata-rata Rp${Math.round(daily.reduce((a,b)=>a+b,0)/daily.length/1000)}rb/hari</div>
          </div>
        </div>
        <svg viewBox="0 0 600 200" style="width:100%;height:200px">
          ${daily.map((v, i) => {
            const x = 10 + i * (580/30);
            const h = (v / maxDaily) * 160;
            return `<rect x="${x}" y="${180-h}" width="${580/30 - 4}" height="${h}" fill="${v===0?'var(--line)':'var(--ink)'}" rx="2"/>`;
          }).join('')}
          <line x1="0" x2="600" y1="180" y2="180" stroke="var(--line)"/>
        </svg>
        <div class="flex-b text-xs muted" style="margin-top:8px">
          <span>7 Apr</span><span>15 Apr</span><span>22 Apr</span><span>29 Apr</span><span>6 Mei</span>
        </div>
      </div>
    </div>

    <div class="grid grid-2">
      <div class="card">
        <div class="card-hd">
          <div>
            <div class="card-title">Top merchant</div>
            <div class="card-sub">Tempat paling sering Anda transaksi</div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column">
          ${[
            {n:'Gojek', cat:'transport', count:8, amt:248000},
            {n:'GoFood', cat:'food', count:6, amt:412000},
            {n:'Tokopedia', cat:'shop', count:3, amt:1820000},
            {n:'Indomaret', cat:'food', count:4, amt:325500},
            {n:'CGV', cat:'fun', count:2, amt:150000},
          ].map((m,i) => {
            const c = cb(m.cat);
            return `<div class="flex-b" style="padding:12px 0;${i<4?'border-bottom:1px solid var(--line)':''}">
              <div class="flex gap-3" style="align-items:center">
                <div class="cat-icon" style="background:${c.bg};color:${c.color}">${c.emoji}</div>
                <div>
                  <div class="fw-500" style="font-size:13px">${m.n}</div>
                  <div class="text-xs muted">${m.count} transaksi</div>
                </div>
              </div>
              <div class="tabular fw-500 money-neg" style="font-size:14px">${fI(-m.amt)}</div>
            </div>`;
          }).join('')}
        </div>
      </div>

      <div class="card">
        <div class="card-hd">
          <div>
            <div class="card-title">Insights bulan ini</div>
            <div class="card-sub">Pola yang terdeteksi otomatis</div>
          </div>
          <span class="chip">${window.icon('sparkle',11)} AI</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px">
          ${[
            {ico:'🍚', t:'Pengeluaran makan turun 18%', s:'Dari Rp1.95jt → Rp1.6jt vs April. Pertahankan!', col:'var(--pos)'},
            {ico:'🛍️', t:'Belanja over-budget Rp62.500', s:'Rata-rata bulanan Anda Rp1.4jt — pertimbangkan naikkan limit.', col:'var(--neg)'},
            {ico:'☕', t:'Kopi Tuku 4x minggu ini', s:'Total Rp112.000 — hampir setara langganan kopi mingguan.', col:'var(--warn)'},
            {ico:'💼', t:'Pemasukan klien naik 21%', s:'PT Sinar bayar invoice tepat waktu 3 bulan beruntun.', col:'var(--pos)'},
          ].map(i => `<div style="display:flex;gap:12px;padding:14px;background:var(--bg-sunken);border-radius:10px;border-left:3px solid ${i.col}">
            <div style="font-size:20px">${i.ico}</div>
            <div>
              <div class="fw-500" style="font-size:13px">${i.t}</div>
              <div class="text-xs muted" style="margin-top:3px;line-height:1.5">${i.s}</div>
            </div>
          </div>`).join('')}
        </div>
      </div>
    </div>
  `;
}
window.renderLaporan = renderLaporan;

// ===== AKUN & DOMPET =====
function renderAkun() {
  const total = AC.reduce((a,b)=>a+b.balance,0);
  return `
    <div class="page-hd">
      <div>
        <div class="eyebrow" style="margin-bottom:6px">Akun & Dompet</div>
        <h1 class="page-title">Semua Saldo</h1>
        <div class="page-sub">5 sumber dana tersinkron · Total saldo ${fI(total)}</div>
      </div>
      <button class="btn btn-primary">${window.icon('plus',14)} Tambah akun</button>
    </div>

    <div class="grid grid-2" style="margin-bottom:32px">
      ${AC.map(a => {
        const txs = T.filter(t => t.acc === a.id);
        const inflow = txs.filter(t => t.amount > 0).reduce((s,t)=>s+t.amount,0);
        const outflow = txs.filter(t => t.amount < 0).reduce((s,t)=>s+t.amount,0);
        return `<div class="card" style="position:relative;overflow:hidden">
          <div style="position:absolute;top:-30px;right:-30px;width:140px;height:140px;border-radius:50%;background:${a.color};opacity:0.06"></div>
          <div class="flex-b" style="margin-bottom:24px;position:relative">
            <div class="flex gap-3" style="align-items:center">
              <div style="width:44px;height:44px;border-radius:11px;background:${a.color};color:white;display:grid;place-items:center;font-weight:600;font-size:14px">${a.label.slice(0,2).toUpperCase()}</div>
              <div>
                <div class="fw-500">${a.label}</div>
                <div class="text-xs muted">${a.type} ${a.last !== '—' ? '·••'+a.last : ''}</div>
              </div>
            </div>
            <button class="icon-btn" style="border:none">${window.icon('more')}</button>
          </div>
          <div class="eyebrow">Saldo</div>
          <div class="display" style="font-size:32px;margin-top:6px">${fI(a.balance)}</div>
          <div class="flex gap-4" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--line)">
            <div style="flex:1">
              <div class="text-xs muted">Masuk bulan ini</div>
              <div class="tabular fw-500 money-pos" style="margin-top:2px">${fIk(inflow)}</div>
            </div>
            <div style="flex:1">
              <div class="text-xs muted">Keluar bulan ini</div>
              <div class="tabular fw-500 money-neg" style="margin-top:2px">${fIk(outflow)}</div>
            </div>
            <div style="flex:1">
              <div class="text-xs muted">Transaksi</div>
              <div class="tabular fw-500" style="margin-top:2px">${txs.length}</div>
            </div>
          </div>
        </div>`;
      }).join('')}

      <div class="card" style="border-style:dashed;display:flex;align-items:center;justify-content:center;flex-direction:column;color:var(--ink-3);min-height:220px;cursor:pointer" onmouseover="this.style.background='var(--bg-sunken)'" onmouseout="this.style.background='transparent'">
        <div style="width:44px;height:44px;border-radius:11px;border:1.5px dashed var(--line-2);display:grid;place-items:center;margin-bottom:12px">${window.icon('plus',20)}</div>
        <div class="fw-500" style="color:var(--ink-2)">Hubungkan akun baru</div>
        <div class="text-xs" style="margin-top:4px">Bank · E-wallet · Kartu kredit</div>
      </div>
    </div>

    <div class="card">
      <div class="card-hd">
        <div>
          <div class="card-title">Tren saldo total</div>
          <div class="card-sub">Pergerakan saldo gabungan 30 hari</div>
        </div>
      </div>
      <svg viewBox="0 0 800 220" style="width:100%;height:220px">
        ${(() => {
          const pts = [];
          let val = 22000000;
          for (let i = 0; i < 30; i++) {
            val += (Math.sin(i*0.4)+0.3) * 200000 + (i%7===0?500000:-i*30000);
            pts.push(val);
          }
          const max = Math.max(...pts), min = Math.min(...pts);
          const range = max - min;
          const path = pts.map((v,i) => `${i===0?'M':'L'} ${10 + i*26} ${200 - ((v-min)/range)*180 + 10}`).join(' ');
          const area = path + ` L ${10 + 29*26} 210 L 10 210 Z`;
          return `<path d="${area}" fill="var(--ink)" opacity="0.05"/>
                  <path d="${path}" fill="none" stroke="var(--ink)" stroke-width="2"/>
                  ${pts.map((v,i) => i%5===0 ? `<circle cx="${10+i*26}" cy="${200-((v-min)/range)*180+10}" r="3" fill="var(--bg-elev)" stroke="var(--ink)" stroke-width="2"/>` : '').join('')}`;
        })()}
      </svg>
    </div>
  `;
}
window.renderAkun = renderAkun;
})();
