@props(['monthly' => []])

@php
    $w = 600; $h = 220; $pad = 28;
    $rows = array_values($monthly);
    $count = count($rows);
    $allValues = [];
    foreach ($rows as $r) { $allValues[] = $r['income']; $allValues[] = $r['expense']; }
    $max = $allValues ? max($allValues) : 1;
    $xs = function ($i) use ($pad, $w, $count) {
        return $count > 1 ? $pad + ($i / ($count - 1)) * ($w - $pad * 2) : $pad;
    };
    $ys = function ($v) use ($pad, $h, $max) {
        return $h - $pad - ($v / $max) * ($h - $pad * 2);
    };
    $income = []; $expense = [];
    foreach ($rows as $i => $r) {
        $income[] = $xs($i).','.$ys($r['income']);
        $expense[] = $xs($i).','.$ys($r['expense']);
    }
    $incomePath = 'M'.implode(' L', $income);
    $expensePath = 'M'.implode(' L', $expense);
    $incomeArea = $incomePath." L".$xs($count - 1).",".($h - $pad)." L".$xs(0).",".($h - $pad)." Z";
@endphp

<svg viewBox="0 0 {{ $w }} {{ $h }}" style="width:100%;height:240px">
    @foreach([0, 0.25, 0.5, 0.75, 1] as $g)
        <line x1="{{ $pad }}" x2="{{ $w - $pad }}" y1="{{ $pad + $g * ($h - $pad * 2) }}" y2="{{ $pad + $g * ($h - $pad * 2) }}" stroke="var(--color-line)" stroke-dasharray="2 4"/>
    @endforeach
    @if($count > 0)
        <rect x="{{ $xs($count - 1) - 18 }}" y="{{ $pad - 6 }}" width="36" height="{{ $h - $pad * 2 + 8 }}" fill="var(--color-ink)" opacity="0.04" rx="6"/>
    @endif
    <path d="{{ $incomeArea }}" fill="var(--color-pos)" opacity="0.08"/>
    <path d="{{ $incomePath }}" fill="none" stroke="var(--color-pos)" stroke-width="2"/>
    <path d="{{ $expensePath }}" fill="none" stroke="var(--color-neg)" stroke-width="2" stroke-dasharray="4 3"/>
    @foreach($rows as $i => $r)
        <circle cx="{{ $xs($i) }}" cy="{{ $ys($r['income']) }}" r="3.5" fill="var(--color-bg-elev)" stroke="var(--color-pos)" stroke-width="2"/>
        <circle cx="{{ $xs($i) }}" cy="{{ $ys($r['expense']) }}" r="3" fill="var(--color-bg-elev)" stroke="var(--color-neg)" stroke-width="1.5"/>
        <text x="{{ $xs($i) }}" y="{{ $h - 6 }}" font-size="11" text-anchor="middle" fill="var(--color-ink-3)" font-family="var(--font-sans)">{{ $r['m'] }}</text>
    @endforeach
</svg>
<div style="display:flex;gap:16px;margin-top:8px;font-size:12px;align-items:center">
    <div style="display:inline-flex;align-items:center;gap:8px"><span style="width:6px;height:6px;border-radius:50%;background:var(--color-pos)"></span> Pemasukan</div>
    <div style="display:inline-flex;align-items:center;gap:8px"><span style="width:6px;height:6px;border-radius:50%;background:var(--color-neg)"></span> Pengeluaran</div>
</div>
