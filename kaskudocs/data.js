// Kasku — sample data + helpers (vanilla JS)

var fmtIDR = (n) => {
  const sign = n < 0 ? '-' : '';
  const v = Math.abs(n);
  return sign + 'Rp' + v.toLocaleString('id-ID');
};
var fmtIDRk = (n) => {
  const v = Math.abs(n);
  const s = n < 0 ? '-' : '';
  if (v >= 1e9) return s + 'Rp' + (v/1e9).toFixed(1).replace('.0','') + 'M';
  if (v >= 1e6) return s + 'Rp' + (v/1e6).toFixed(1).replace('.0','') + 'jt';
  if (v >= 1e3) return s + 'Rp' + Math.round(v/1e3) + 'rb';
  return fmtIDR(n);
};

const CATEGORIES = [
  { id:'food', label:'Makan & Minum', emoji:'🍚', color:'#f59e0b', bg:'#fef3c7' },
  { id:'transport', label:'Transportasi', emoji:'🛵', color:'#3b82f6', bg:'#dbeafe' },
  { id:'work', label:'Pendapatan Klien', emoji:'💼', color:'#1f8a5b', bg:'#dcfce7' },
  { id:'shop', label:'Belanja', emoji:'🛍️', color:'#ec4899', bg:'#fce7f3' },
  { id:'bill', label:'Tagihan', emoji:'🧾', color:'#8b5cf6', bg:'#ede9fe' },
  { id:'health', label:'Kesehatan', emoji:'🩺', color:'#14b8a6', bg:'#ccfbf1' },
  { id:'fun', label:'Hiburan', emoji:'🎬', color:'#f43f5e', bg:'#ffe4e6' },
  { id:'save', label:'Tabungan', emoji:'🏦', color:'#0ea5e9', bg:'#e0f2fe' },
  { id:'other', label:'Lainnya', emoji:'✨', color:'#6b7280', bg:'#f3f4f6' },
];
const catById = (id) => CATEGORIES.find(c => c.id === id) || CATEGORIES[CATEGORIES.length-1];

const ACCOUNTS = [
  { id:'bca', label:'BCA Tahapan', type:'Bank', last:'4521', balance:18750000, color:'#1d4ed8' },
  { id:'jago', label:'Bank Jago', type:'Bank', last:'8812', balance:4280000, color:'#f97316' },
  { id:'gopay', label:'GoPay', type:'E-wallet', last:'0810', balance:685000, color:'#10b981' },
  { id:'ovo', label:'OVO', type:'E-wallet', last:'0810', balance:142000, color:'#7c3aed' },
  { id:'cash', label:'Tunai', type:'Cash', last:'—', balance:320000, color:'#525252' },
];

const TX = [
  { id:'t01', when:'2026-05-06T09:12', via:'wa', cat:'food', acc:'gopay', label:'Kopi Tuku', amount:-28000, note:'"kopi tuku 28rb"', merchant:'Kopi Tuku Cipete' },
  { id:'t02', when:'2026-05-06T08:30', via:'wa', cat:'transport', acc:'gopay', label:'Gojek ke meeting', amount:-42000, note:'"gojek 42rb ke kuningan"', merchant:'Gojek' },
  { id:'t03', when:'2026-05-05T18:40', via:'wa', cat:'food', acc:'cash', label:'Warteg dekat kos', amount:-22000, note:'"makan siang 22rb"', merchant:'Warteg Bahari' },
  { id:'t04', when:'2026-05-05T14:00', via:'manual', cat:'work', acc:'bca', label:'Invoice #2026-014 — PT Sinar', amount:12500000, note:'Project Q2 retainer', merchant:'PT Sinar Kreasi' },
  { id:'t05', when:'2026-05-05T11:15', via:'wa', cat:'shop', acc:'jago', label:'Tokopedia — keyboard', amount:-1250000, note:'/expense keyboard 1.25jt', merchant:'Tokopedia' },
  { id:'t06', when:'2026-05-04T20:05', via:'receipt', cat:'food', acc:'jago', label:'Indomaret', amount:-87500, note:'Foto struk', merchant:'Indomaret Kemang' },
  { id:'t07', when:'2026-05-04T13:22', via:'wa', cat:'food', acc:'gopay', label:'GoFood — Padang', amount:-52000, note:'"makan siang padang 52rb"', merchant:'RM Sederhana' },
  { id:'t08', when:'2026-05-03T19:10', via:'wa', cat:'fun', acc:'jago', label:'CGV — Tiket bioskop', amount:-75000, note:'"nonton 75rb"', merchant:'CGV Plaza Indo' },
  { id:'t09', when:'2026-05-03T08:00', via:'wa', cat:'transport', acc:'gopay', label:'Grab', amount:-38000, note:'"grab 38rb"', merchant:'Grab' },
  { id:'t10', when:'2026-05-02T15:30', via:'manual', cat:'bill', acc:'bca', label:'PLN listrik', amount:-425000, note:'Token PLN bulan Mei', merchant:'PLN' },
  { id:'t11', when:'2026-05-02T10:45', via:'wa', cat:'health', acc:'jago', label:'Apotek K24', amount:-68000, note:'"obat batuk 68rb"', merchant:'K24' },
  { id:'t12', when:'2026-05-01T22:00', via:'wa', cat:'food', acc:'gopay', label:'GoFood — Bakmi', amount:-45000, note:'"bakmi 45rb"', merchant:'Bakmi GM' },
  { id:'t13', when:'2026-05-01T14:00', via:'manual', cat:'save', acc:'bca', label:'Transfer ke Tabungan Liburan', amount:-2000000, note:'Auto-saving', merchant:'Transfer' },
  { id:'t14', when:'2026-04-30T11:00', via:'wa', cat:'work', acc:'bca', label:'Top-up klien — Logo project', amount:3500000, note:'/income 3.5jt logo project', merchant:'CV Maju Jaya' },
  { id:'t15', when:'2026-04-30T08:30', via:'wa', cat:'food', acc:'gopay', label:'Sarapan nasi uduk', amount:-18000, note:'"nasi uduk 18rb"', merchant:'Nasi Uduk Babe' },
  { id:'t16', when:'2026-04-29T20:30', via:'receipt', cat:'shop', acc:'jago', label:'Superindo', amount:-312500, note:'Foto struk groceries', merchant:'Superindo' },
  { id:'t17', when:'2026-04-29T12:00', via:'wa', cat:'food', acc:'gopay', label:'GoFood — Sushi', amount:-118000, note:'"sushi 118rb"', merchant:'Sushi Tei' },
  { id:'t18', when:'2026-04-28T16:00', via:'manual', cat:'bill', acc:'bca', label:'Internet IndiHome', amount:-380000, note:'Bulanan', merchant:'Telkom' },
  { id:'t19', when:'2026-04-28T09:00', via:'wa', cat:'transport', acc:'gopay', label:'Bensin motor', amount:-25000, note:'"bensin 25rb"', merchant:'Pertamina' },
  { id:'t20', when:'2026-04-27T19:20', via:'wa', cat:'fun', acc:'jago', label:'Spotify Premium', amount:-54990, note:'"spotify"', merchant:'Spotify' },
];

const BUDGETS = [
  { cat:'food', limit:2500000, spent:1605500 },
  { cat:'transport', limit:800000, spent:510000 },
  { cat:'shop', limit:1500000, spent:1562500 },
  { cat:'fun', limit:500000, spent:309990 },
  { cat:'bill', limit:1200000, spent:805000 },
  { cat:'health', limit:400000, spent:68000 },
];

const MONTHLY = [
  { m:'Des', income:14200000, expense:8400000 },
  { m:'Jan', income:15800000, expense:9200000 },
  { m:'Feb', income:12500000, expense:7800000 },
  { m:'Mar', income:18200000, expense:9100000 },
  { m:'Apr', income:16500000, expense:8650000 },
  { m:'Mei', income:16000000, expense:5230990 },
];

const GOALS = [
  { id:'g1', label:'Liburan ke Jepang', target:25000000, current:14200000, by:'Okt 2026', color:'#ec4899' },
  { id:'g2', label:'Dana darurat 6 bulan', target:36000000, current:22500000, by:'Des 2026', color:'#1f8a5b' },
  { id:'g3', label:'MacBook baru', target:28000000, current:8400000, by:'Mar 2027', color:'#0e0e0c' },
];

const CONVERSATIONS = [
  { id:'kasku', name:'Kasku Bot', last:'Tercatat ✅ Kopi Tuku Rp28.000', time:'09:12', unread:0, online:true, pinned:true },
  { id:'reminder', name:'Reminder Tagihan', last:'IndiHome jatuh tempo besok', time:'08:00', unread:1 },
  { id:'weekly', name:'Weekly Report', last:'Laporan minggu lalu siap dilihat', time:'Sen', unread:0 },
  { id:'budget', name:'Budget Alert', last:'Belanja 104% dari budget bulanan', time:'Min', unread:2 },
];

window.KK = { fmtIDR, fmtIDRk, CATEGORIES, catById, ACCOUNTS, TX, BUDGETS, MONTHLY, GOALS, CONVERSATIONS };
