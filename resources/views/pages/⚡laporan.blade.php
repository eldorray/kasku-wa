<?php

use App\Services\ReportService;
use App\Support\Charts;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Laporan')] class extends Component
{
    #[Url(as: 'periode')]
    public string $tab = 'month';

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['week', 'month', 'year'], true)) {
            $this->tab = $tab;
        }
    }

    public function with(ReportService $svc): array
    {
        $household = currentHousehold();
        $period = $svc->resolvePeriod($this->tab);

        $breakdown = $svc->categoryBreakdown($household, $period['start'], $period['end']);
        $series = $svc->expenseSeries($household, $this->tab, $period['start'], $period['end']);
        $merchants = $svc->topMerchants($household, $period['start'], $period['end'], 6);
        $insights = $svc->insights($household, $this->tab, $period['start'], $period['end'], $period['prev_start'], $period['prev_end']);

        return [
            'tab' => $this->tab,
            'periodLabel' => $period['label'],
            'donut' => $breakdown['donut'],
            'total' => $breakdown['total'],
            'seriesLabels' => $series['labels'],
            'seriesValues' => $series['values'],
            'seriesSubLabel' => $series['sub_label'],
            'merchants' => $merchants,
            'insights' => $insights,
        ];
    }
};
?>

<div>
    {{-- Mobile appbar --}}
    <div class="kasku-mobile-appbar">
        <div style="flex:1">
            <div class="kasku-mobile-appbar-title">Laporan</div>
            <div class="kasku-mobile-appbar-sub">{{ $periodLabel }}</div>
        </div>
        <div class="kasku-mobile-appbar-actions">
            <button type="button" onclick="window.print()" class="kasku-mobile-appbar-icon" aria-label="Cetak">
                <x-kasku.icon name="download" :size="14" />
            </button>
        </div>
    </div>

    {{-- Mobile period pills --}}
    <div class="kasku-mobile-only" style="padding:0 0 8px">
        <div style="display:flex;gap:6px">
            <button type="button" wire:click="setTab('week')" class="kasku-mobile-pill @if($tab === 'week') is-active @endif">Mingguan</button>
            <button type="button" wire:click="setTab('month')" class="kasku-mobile-pill @if($tab === 'month') is-active @endif">Bulanan</button>
            <button type="button" wire:click="setTab('year')" class="kasku-mobile-pill @if($tab === 'year') is-active @endif">Tahunan</button>
        </div>
    </div>

    <x-kasku.page-header
        class="kasku-desktop-only"
        eyebrow="Laporan"
        title="Analisa Keuangan"
        :sub="'Analisa untuk: ' . $periodLabel">
        <x-slot:actions>
            <div class="kasku-tabs">
                <button type="button" wire:click="setTab('week')" class="kasku-tab @if($tab === 'week') is-active @endif">Mingguan</button>
                <button type="button" wire:click="setTab('month')" class="kasku-tab @if($tab === 'month') is-active @endif">Bulanan</button>
                <button type="button" wire:click="setTab('year')" class="kasku-tab @if($tab === 'year') is-active @endif">Tahunan</button>
            </div>
            <button type="button" onclick="window.print()" class="kasku-btn"><x-kasku.icon name="download" /> Cetak / PDF</button>
        </x-slot:actions>
    </x-kasku.page-header>

    <div class="kasku-grid" style="grid-template-columns:1fr 1.4fr;margin-bottom:20px">
        {{-- Donut breakdown --}}
        <x-kasku.card title="Breakdown kategori" :sub="'Pengeluaran ' . $periodLabel">
            @if($total === 0)
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 0;color:var(--color-ink-3);text-align:center">
                    <div style="font-size:36px;margin-bottom:8px">📊</div>
                    <div style="font-weight:500;font-size:14px;color:var(--color-ink)">Belum ada pengeluaran</div>
                    <div style="font-size:12px;margin-top:4px">Catat transaksi untuk melihat breakdown.</div>
                </div>
            @else
                <div style="display:flex;align-items:center;gap:24px">
                    <svg viewBox="0 0 200 200" style="width:200px;height:200px;flex-shrink:0">
                        @foreach($donut as $d)
                            @if($d->category)
                                <path d="{{ \App\Support\Charts::arc(100, 100, 90, $d->start, $d->end) }}" fill="{{ $d->category->color }}" opacity="0.85"/>
                            @endif
                        @endforeach
                        <circle cx="100" cy="100" r="58" fill="var(--color-bg-elev)"/>
                        <text x="100" y="92" text-anchor="middle" font-size="11" fill="var(--color-ink-3)" font-family="var(--font-sans)">Total</text>
                        <text x="100" y="112" text-anchor="middle" font-size="18" fill="var(--color-ink)" font-family="var(--font-display)">{{ \App\Support\Money::fmtShort($total) }}</text>
                    </svg>
                    <div style="flex:1;display:flex;flex-direction:column;gap:10px">
                        @foreach($donut->take(6) as $d)
                            @if($d->category)
                                <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px">
                                    <div style="display:flex;align-items:center;gap:8px;min-width:0">
                                        <span style="width:10px;height:10px;border-radius:3px;background:{{ $d->category->color }};flex-shrink:0"></span>
                                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $d->category->emoji }} {{ $d->category->label }}</span>
                                    </div>
                                    <div class="kasku-tabular" style="font-weight:500;flex-shrink:0;margin-left:8px">{{ (int) round($d->pct * 100) }}%</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </x-kasku.card>

        {{-- Time-bucketed bar chart --}}
        <x-kasku.card
            :title="$tab === 'year' ? 'Pengeluaran bulanan — 12 bulan' : ($tab === 'week' ? 'Pengeluaran harian — 7 hari' : 'Pengeluaran harian — bulan ini')"
            :sub="$seriesSubLabel">
            @php
                $values = $seriesValues;
                $labels = $seriesLabels;
                $count = count($values);
                $maxV = max($values) ?: 1;
                $w = 600;
                $h = 200;
                $padX = 10;
                $barGap = 4;
                $barW = $count > 0 ? ($w - 2 * $padX) / $count - $barGap : 10;
            @endphp
            <svg viewBox="0 0 {{ $w }} {{ $h }}" style="width:100%;height:200px">
                @foreach($values as $i => $v)
                    @php
                        $x = $padX + $i * (($w - 2 * $padX) / max($count, 1));
                        $hBar = $maxV > 0 ? ($v / $maxV) * 160 : 0;
                    @endphp
                    <rect x="{{ $x }}" y="{{ 180 - $hBar }}" width="{{ max($barW, 1) }}" height="{{ $hBar }}" fill="{{ $v === 0 ? 'var(--color-line)' : 'var(--color-ink)' }}" rx="2">
                        <title>{{ $labels[$i] }}: {{ \App\Support\Money::fmt($v) }}</title>
                    </rect>
                @endforeach
                <line x1="0" x2="{{ $w }}" y1="180" y2="180" stroke="var(--color-line)"/>
            </svg>
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--color-ink-3);margin-top:8px">
                @if($count > 0)
                    <span>{{ $labels[0] }}</span>
                    @if($count > 4)
                        <span>{{ $labels[(int) ($count / 4)] }}</span>
                        <span>{{ $labels[(int) ($count / 2)] }}</span>
                        <span>{{ $labels[(int) ($count * 3 / 4)] }}</span>
                    @endif
                    <span>{{ $labels[$count - 1] }}</span>
                @endif
            </div>
        </x-kasku.card>
    </div>

    <div class="kasku-grid kasku-grid-2">
        {{-- Top merchants --}}
        <x-kasku.card title="Top merchant" :sub="'Tempat paling sering Anda transaksi · ' . $periodLabel">
            @if($merchants->isEmpty())
                <div style="padding:24px;text-align:center;color:var(--color-ink-3);font-size:13px">
                    Belum ada transaksi dengan merchant tercatat di periode ini.
                </div>
            @else
                <div style="display:flex;flex-direction:column">
                    @foreach($merchants as $i => $m)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;@if($i < $merchants->count() - 1)border-bottom:1px solid var(--color-line)@endif">
                            <div style="display:flex;gap:12px;align-items:center;min-width:0">
                                @if($m->category)<x-kasku.cat-icon :category="$m->category" />@else<div class="kasku-cat-icon" style="background:var(--color-bg-sunken);color:var(--color-ink-3)">·</div>@endif
                                <div style="min-width:0">
                                    <div style="font-weight:500;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $m->merchant }}</div>
                                    <div style="font-size:11px;color:var(--color-ink-3)">{{ $m->count }} transaksi</div>
                                </div>
                            </div>
                            <div class="kasku-tabular kasku-money kasku-money--neg" style="font-weight:500;font-size:14px;flex-shrink:0;margin-left:12px">{{ \App\Support\Money::fmt(-$m->total) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-kasku.card>

        {{-- Insights --}}
        <x-kasku.card title="Insights" sub="Pola yang terdeteksi otomatis">
            <x-slot:action>
                <x-kasku.chip><x-kasku.icon name="sparkle" :size="11" /> Auto</x-kasku.chip>
            </x-slot:action>
            @if(empty($insights))
                <div style="padding:24px;text-align:center;color:var(--color-ink-3);font-size:13px">
                    Belum ada pola signifikan untuk periode ini.
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:12px">
                    @foreach($insights as $i)
                        <div style="display:flex;gap:12px;padding:14px;background:var(--color-bg-sunken);border-radius:10px;border-left:3px solid {{ $i['col'] }}">
                            <div style="font-size:20px;flex-shrink:0">{{ $i['ico'] }}</div>
                            <div style="min-width:0">
                                <div style="font-weight:500;font-size:13px">{{ $i['t'] }}</div>
                                <div style="font-size:11px;color:var(--color-ink-3);margin-top:3px;line-height:1.5">{{ $i['s'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-kasku.card>
    </div>
</div>
