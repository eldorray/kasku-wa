// Kasku — Chat page (WhatsApp inbox + animated phone)
(function(){
const { CONVERSATIONS: CV } = window.KK;

// Scripted conversation that types itself out
const CHAT_SCRIPT = [
  { from: 'bot', text: 'Halo Rama! Aku Kasku 👋 Catat pengeluaranmu cukup ketik di sini.', time: '08:00' },
  { from: 'user', text: 'kopi tuku 28rb', time: '09:12' },
  { from: 'bot', kind: 'typing' },
  { from: 'bot', text: 'Tercatat ✅', time: '09:12',
    receipt: { type: 'Pengeluaran', emoji: '🍚', cat: 'Makan & Minum', acc: 'GoPay', amount: 'Rp28.000', merchant: 'Kopi Tuku' } },
  { from: 'user', text: 'gojek 42rb ke kuningan', time: '09:30' },
  { from: 'bot', kind: 'typing' },
  { from: 'bot', text: 'Tercatat ✅', time: '09:30',
    receipt: { type: 'Pengeluaran', emoji: '🛵', cat: 'Transportasi', acc: 'GoPay', amount: 'Rp42.000', merchant: 'Gojek' } },
  { from: 'user', text: 'laporan minggu ini', time: '17:45' },
  { from: 'bot', kind: 'typing' },
  { from: 'bot', kind: 'report', time: '17:45',
    summary: { period: '29 Apr — 5 Mei', income: 'Rp16.000.000', expense: 'Rp4.385.500', top: 'Makan & Minum', topPct: '38%' } },
];

let chatTimer = null;

function renderChat() {
  setTimeout(() => startChatAnimation(), 120);
  return `
    <div class="page-hd">
      <div>
        <div class="eyebrow" style="margin-bottom:6px">Chat WhatsApp</div>
        <h1 class="page-title">Bot Kasku</h1>
        <div class="page-sub">Catat transaksi, minta laporan, atau atur reminder — semuanya lewat chat.</div>
      </div>
      <div class="flex gap-3">
        <button class="btn">${window.icon('settings')} Pengaturan bot</button>
        <button class="btn btn-wa">${window.icon('wa',14)} Buka di WhatsApp</button>
      </div>
    </div>

    <div class="grid chat-grid" style="gap:20px;height:calc(100vh - 240px);min-height:600px">
      <!-- Inbox -->
      <div class="card" style="padding:0;display:flex;flex-direction:column;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--line)">
          <div class="card-title">Percakapan</div>
          <div class="card-sub">${CV.length} thread aktif</div>
        </div>
        <div style="flex:1;overflow:auto">
          ${CV.map((c,i) => `
            <div class="flex gap-3" style="padding:14px 20px;border-bottom:1px solid var(--line);align-items:center;cursor:pointer;${i===0?'background:var(--bg-sunken);border-left:3px solid var(--wa)':''}">
              <div style="width:40px;height:40px;border-radius:50%;background:${i===0?'var(--wa)':'var(--bg-sunken)'};display:grid;place-items:center;color:${i===0?'white':'var(--ink-2)'};font-family:var(--font-display);font-size:18px;flex-shrink:0;position:relative">
                ${i===0?'k':c.name.charAt(0)}
                ${c.online?'<span style="position:absolute;bottom:0;right:0;width:10px;height:10px;background:var(--wa);border-radius:50%;border:2px solid var(--bg-elev)"></span>':''}
              </div>
              <div style="flex:1;min-width:0">
                <div class="flex-b">
                  <div class="fw-500" style="font-size:13px">${c.name} ${c.pinned?'<span style="opacity:0.4;font-size:10px">📌</span>':''}</div>
                  <div class="text-xs muted">${c.time}</div>
                </div>
                <div class="text-xs muted" style="margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${c.last}</div>
              </div>
              ${c.unread ? `<span style="background:var(--wa);color:white;font-size:10px;padding:2px 7px;border-radius:999px;font-weight:600">${c.unread}</span>` : ''}
            </div>
          `).join('')}
        </div>
        <div style="padding:14px 20px;border-top:1px solid var(--line);background:var(--bg-sunken)">
          <div class="text-xs muted" style="margin-bottom:8px">Trik singkat</div>
          <div class="text-xs" style="line-height:1.6;color:var(--ink-2)">
            <code class="mono" style="background:var(--bg-elev);padding:1px 5px;border-radius:4px;border:1px solid var(--line)">/laporan</code> · ringkasan<br/>
            <code class="mono" style="background:var(--bg-elev);padding:1px 5px;border-radius:4px;border:1px solid var(--line)">/budget</code> · cek limit<br/>
            <code class="mono" style="background:var(--bg-elev);padding:1px 5px;border-radius:4px;border:1px solid var(--line)">/saldo</code> · lihat saldo
          </div>
        </div>
      </div>

      <!-- Phone preview -->
      <div style="display:flex;align-items:flex-start;justify-content:center;padding-top:8px">
        <div class="phone-frame">
          <div class="phone-screen">
            <div class="wa-header">
              <span style="opacity:0.9">${window.icon('arrowRight',18)}</span>
              <div class="wa-bot-avatar">k</div>
              <div style="flex:1">
                <div class="wa-bot-name">Kasku Bot</div>
                <div class="wa-bot-status">online · membalas dalam &lt;2 detik</div>
              </div>
              <span style="opacity:0.7">${window.icon('more',18)}</span>
            </div>
            <div class="wa-bg" id="wa-messages"></div>
            <div class="wa-input">
              <span style="color:#888">${window.icon('camera',20)}</span>
              <div class="wa-input-box" id="wa-input-box">Ketik pesan…</div>
              <div class="wa-input-send">${window.icon('send',16)}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Activity / context panel -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
          <div class="card-hd">
            <div class="card-title">Status koneksi</div>
            <span class="chip chip-pos"><span class="chip-dot"></span> Tersambung</span>
          </div>
          <div class="flex gap-3" style="align-items:center;padding:12px;background:var(--wa-bg);border-radius:10px">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--wa);display:grid;place-items:center;color:white">${window.icon('wa',18)}</div>
            <div>
              <div class="fw-500" style="font-size:13px">+62 812 8731 4422</div>
              <div class="text-xs" style="color:var(--wa-deep)">Aktif sejak 12 Mar 2026</div>
            </div>
          </div>
          <div class="text-xs muted" style="margin-top:12px;line-height:1.6">
            Bot memproses pesan secara end-to-end terenkripsi melalui WhatsApp Business API.
          </div>
        </div>

        <div class="card">
          <div class="card-title" style="margin-bottom:12px">Aksi cepat</div>
          <div style="display:flex;flex-direction:column;gap:8px">
            ${[
              {ico:'💬', t:'Catat pengeluaran', s:'"jajan 25rb" atau "/expense"'},
              {ico:'📷', t:'Kirim foto struk', s:'Auto-parse merchant & total'},
              {ico:'📊', t:'Minta laporan', s:'/laporan harian / mingguan'},
              {ico:'🔔', t:'Atur reminder', s:'/reminder bayar PLN tgl 5'},
              {ico:'💳', t:'Cek saldo', s:'/saldo · /budget'},
            ].map(a => `<div class="flex gap-3" style="padding:10px;border-radius:8px;align-items:flex-start;cursor:pointer" onmouseover="this.style.background='var(--bg-sunken)'" onmouseout="this.style.background='transparent'">
              <div style="font-size:18px;flex-shrink:0">${a.ico}</div>
              <div>
                <div class="fw-500" style="font-size:12.5px">${a.t}</div>
                <div class="text-xs muted mono" style="margin-top:2px">${a.s}</div>
              </div>
            </div>`).join('')}
          </div>
        </div>

        <div class="card" style="background:var(--ink);color:white;border-color:var(--ink)">
          <div class="flex-b" style="margin-bottom:10px">
            <div class="eyebrow" style="color:rgba(255,255,255,0.5)">Bulan ini</div>
            <span style="opacity:0.4">${window.icon('sparkle',14)}</span>
          </div>
          <div class="display" style="font-size:36px">14<span style="font-size:18px;opacity:0.5">/20</span></div>
          <div class="text-xs" style="opacity:0.6;margin-top:4px">Transaksi via chat</div>
          <div class="bar" style="margin-top:14px;background:rgba(255,255,255,0.1)"><div class="bar-fill" style="width:70%;background:var(--wa)"></div></div>
        </div>
      </div>
    </div>
  `;
}

function startChatAnimation() {
  const host = document.getElementById('wa-messages');
  if (!host) return;
  host.innerHTML = '';
  let idx = 0;

  function step() {
    if (idx >= CHAT_SCRIPT.length) {
      // Loop after a pause
      chatTimer = setTimeout(() => {
        idx = 0;
        host.innerHTML = '';
        step();
      }, 3500);
      return;
    }
    const m = CHAT_SCRIPT[idx];
    addMessage(host, m);
    host.scrollTop = host.scrollHeight;
    idx++;
    const delay = m.kind === 'typing' ? 1100 : (m.from === 'user' ? 800 : 600);
    chatTimer = setTimeout(() => {
      // Remove typing indicator before next msg
      if (m.kind === 'typing') {
        const t = host.querySelector('.typing');
        if (t) t.remove();
      }
      step();
    }, delay);
  }
  step();
}

function addMessage(host, m) {
  if (m.kind === 'typing') {
    const div = document.createElement('div');
    div.className = 'typing';
    div.innerHTML = '<span></span><span></span><span></span>';
    host.appendChild(div);
    return;
  }

  const bubble = document.createElement('div');
  bubble.className = 'wa-bubble ' + (m.from === 'user' ? 'wa-bubble-out' : 'wa-bubble-in');

  if (m.kind === 'report') {
    const s = m.summary;
    bubble.innerHTML = `
      <div style="font-weight:500;margin-bottom:6px">📊 Ringkasan ${s.period}</div>
      <div style="font-size:12px;line-height:1.6">
        Pemasukan: <b>${s.income}</b><br/>
        Pengeluaran: <b>${s.expense}</b><br/>
        Top kategori: <b>${s.top}</b> (${s.topPct})
      </div>
      <div class="wa-time">${m.time}</div>
    `;
  } else if (m.receipt) {
    const r = m.receipt;
    bubble.innerHTML = `
      <div style="margin-bottom:4px">${m.text}</div>
      <div class="wa-receipt">
        <div class="wa-receipt-row"><span style="opacity:0.6">Tipe</span><b>${r.emoji} ${r.type}</b></div>
        <div class="wa-receipt-row"><span style="opacity:0.6">Kategori</span><b>${r.cat}</b></div>
        <div class="wa-receipt-row"><span style="opacity:0.6">Akun</span><b>${r.acc}</b></div>
        <div class="wa-receipt-row"><span style="opacity:0.6">Merchant</span><b>${r.merchant}</b></div>
        <div class="wa-receipt-row" style="margin-top:6px;padding-top:6px;border-top:1px dashed #ddd"><span>Total</span><b>${r.amount}</b></div>
      </div>
      <div class="wa-time">${m.time}${m.from==='user'?' <span class="read">✓✓</span>':''}</div>
    `;
  } else {
    bubble.innerHTML = `<span>${m.text}</span><div class="wa-time">${m.time}${m.from==='user'?' <span class="read">✓✓</span>':''}</div>`;
  }
  host.appendChild(bubble);
}

function stopChat() {
  if (chatTimer) clearTimeout(chatTimer);
  chatTimer = null;
}

window.renderChat = renderChat;
window.stopChat = stopChat;
})();
