<?php

use App\Services\DarCache;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.gantt-print', ['title' => 'Cetak Timeline Project'])]
class extends Component {
    public $id;

    public array $project = [];

    public array $timelines = [];

    public array $activities = [];

    public array $weeks = [];

    public array $monthGroups = [];

    public bool $truncated = false;

    public ?string $actualEndLabel = null;

    /**
     * Batas kolom minggu agar selalu muat satu halaman A4 landscape
     * (~6 bulan). Timeline lebih panjang (mis. maintenance 2 tahun) dipangkas
     * dan sisanya ditandai lewat kolom "Lanjutan".
     */
    private const MAX_DISPLAY_WEEKS = 27;

    /** Gradien fase — padanan hex dari kelas Tailwind di komponen gantt live. */
    private const COLORS = [
        'linear-gradient(90deg, #3b82f6, #6366f1)',
        'linear-gradient(90deg, #10b981, #14b8a6)',
        'linear-gradient(90deg, #f97316, #f59e0b)',
        'linear-gradient(90deg, #ec4899, #f43f5e)',
        'linear-gradient(90deg, #8b5cf6, #a855f7)',
        'linear-gradient(90deg, #0ea5e9, #06b6d4)',
    ];

    private const STATUS_MAP = [
        1 => ['label' => 'Open', 'color' => '#3b82f6'],
        2 => ['label' => 'Pending', 'color' => '#f59e0b'],
        3 => ['label' => 'Cancelled', 'color' => '#f87171'],
        4 => ['label' => 'Closed', 'color' => '#10b981'],
    ];

    public function mount(): void
    {
        $this->project = app(ProjectCache::class)->projectFor((int) $this->id);

        if (empty($this->project) || ! $this->canView()) {
            abort(403);
        }

        $this->timelines = app(ProjectCache::class)->timelines((int) $this->id);
        $this->activities = app(DarCache::class)->tasks(['project_id' => (int) $this->id])['data'] ?? [];

        $this->buildTimeline();
    }

    /**
     * Aturan visibilitas sama seperti project-show: pemilik project (leader),
     * tim internal, atau role dengan scope project 'all'.
     */
    private function canView(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ($user->viewScopeFor('project') === 'all') {
            return true;
        }

        $isLeader = (int) ($this->project['project_leader_id'] ?? 0) === (int) $user->id;

        $isMember = collect($this->project['support_team_internals'] ?? [])
            ->contains(fn ($member) => (int) ($member['user_id'] ?? 0) === (int) $user->id);

        return $isLeader || $isMember;
    }

    /**
     * Pecah rentang timeline jadi kolom mingguan (M1..M5 per bulan) lalu batasi
     * sampai MAX_DISPLAY_WEEKS. monthGroups menampung label bulan + jumlah minggu
     * yang benar-benar tampil sebagai colspan header.
     */
    private function buildTimeline(): void
    {
        if (empty($this->timelines)) {
            $this->weeks = [];
            $this->monthGroups = [];

            return;
        }

        $start = Carbon::parse(collect($this->timelines)->min('start_date'))->startOfMonth();
        $rangeEnd = Carbon::parse(collect($this->timelines)->max('end_date'))->endOfMonth();

        $period = CarbonPeriod::create($start, '1 month', $rangeEnd);

        $weeks = [];

        foreach ($period as $monthDate) {
            $monthStart = $monthDate->copy()->startOfMonth();
            $monthEnd = $monthDate->copy()->endOfMonth();

            $weekNo = 1;
            $cursor = $monthStart->copy();

            while ($cursor->lte($monthEnd)) {
                $weekEnd = $cursor->copy()->addDays(6);

                if ($weekEnd->gt($monthEnd)) {
                    $weekEnd = $monthEnd->copy();
                }

                $weeks[] = [
                    'month_value' => $monthDate->format('Y-m'),
                    'month_label' => $monthDate->locale('id')->translatedFormat('M Y'),
                    'label' => 'M'.$weekNo,
                    'start' => $cursor->format('Y-m-d'),
                    'end' => $weekEnd->format('Y-m-d'),
                ];

                $weekNo++;
                $cursor = $weekEnd->copy()->addDay();
            }
        }

        if (count($weeks) > self::MAX_DISPLAY_WEEKS) {
            $this->truncated = true;
            $this->actualEndLabel = $rangeEnd->locale('id')->translatedFormat('M Y');
            $weeks = array_slice($weeks, 0, self::MAX_DISPLAY_WEEKS);
        }

        $this->weeks = $weeks;
        $this->monthGroups = collect($weeks)
            ->groupBy('month_value')
            ->map(fn ($group) => [
                'label' => $group->first()['month_label'],
                'span' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function weekIndexForDate(string $date): int
    {
        if (empty($this->weeks)) {
            return 0;
        }

        $target = Carbon::parse($date);

        foreach ($this->weeks as $idx => $week) {
            if ($target->between(Carbon::parse($week['start'])->startOfDay(), Carbon::parse($week['end'])->endOfDay())) {
                return $idx;
            }
        }

        if ($target->lt(Carbon::parse($this->weeks[0]['start'])->startOfDay())) {
            return 0;
        }

        return count($this->weeks) - 1;
    }

    private function titleStyle(string $title): string
    {
        $length = mb_strlen($title);

        return match (true) {
            $length > 60 => 'font-size:8px;',
            $length > 40 => 'font-size:9px;',
            $length > 25 => 'font-size:9.5px;',
            default => 'font-size:11px;',
        };
    }

    /**
     * @return array<int, array{label: string, color: string}>
     */
    public function statusLegend(): array
    {
        return self::STATUS_MAP;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRowsProperty(): array
    {
        if (empty($this->weeks) || empty($this->timelines)) {
            return [];
        }

        $palette = self::COLORS;
        $statusMap = self::STATUS_MAP;
        $totalWeeks = count($this->weeks);
        $lastWeekEnd = end($this->weeks)['end'];

        return collect($this->timelines)
            ->sortBy('start_date')
            ->values()
            ->map(function ($tl, $index) use ($palette, $statusMap, $totalWeeks, $lastWeekEnd) {
                $startIdx = $this->weekIndexForDate($tl['start_date']);
                $endIdx = max($startIdx, $this->weekIndexForDate($tl['end_date']));
                $span = $endIdx - $startIdx + 1;
                $title = $tl['title'] ?? 'Untitled';

                $activities = collect($this->activities)
                    ->where('project_category_id', $tl['id'])
                    ->sortBy('start_date')
                    ->values()
                    ->map(function ($activity) use ($statusMap, $totalWeeks, $lastWeekEnd) {
                        $aStart = $this->weekIndexForDate($activity['start_date']);
                        $aEnd = max($aStart, $this->weekIndexForDate($activity['end_date']));
                        $aSpan = $aEnd - $aStart + 1;
                        $status = $statusMap[(int) ($activity['status'] ?? 1)] ?? $statusMap[1];
                        $aTitle = $activity['activity'] ?? 'Untitled';

                        return [
                            'id' => $activity['id'],
                            'title' => $aTitle,
                            'title_style' => $this->titleStyle($aTitle),
                            'start_label' => Carbon::parse($activity['start_date'])->locale('id')->translatedFormat('d M'),
                            'end_label' => Carbon::parse($activity['end_date'])->locale('id')->translatedFormat('d M Y'),
                            'leading' => $aStart,
                            'span' => $aSpan,
                            'trailing' => $totalWeeks - ($aStart + $aSpan),
                            'status_label' => $status['label'],
                            'color' => $status['color'],
                            'overflow' => Carbon::parse($activity['end_date'])->gt(Carbon::parse($lastWeekEnd)),
                        ];
                    })
                    ->all();

                return [
                    'id' => $tl['id'] ?? $index,
                    'title' => $title,
                    'title_style' => $this->titleStyle($title),
                    'start_label' => Carbon::parse($tl['start_date'])->locale('id')->translatedFormat('d M Y'),
                    'end_label' => Carbon::parse($tl['end_date'])->locale('id')->translatedFormat('d M Y'),
                    'leading' => $startIdx,
                    'span' => $span,
                    'trailing' => $totalWeeks - ($startIdx + $span),
                    'color' => $palette[$index % count($palette)],
                    'overflow' => Carbon::parse($tl['end_date'])->gt(Carbon::parse($lastWeekEnd)),
                    'activities' => $activities,
                    'activity_count' => count($activities),
                ];
            })
            ->all();
    }
}; ?>

<div class="gantt-doc">
    <style>
        .gantt-doc { color:#18181b; }
        .gantt-doc .head { display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:14px; margin-bottom:18px; border-bottom:2.5px solid #2563eb; }
        .gantt-doc .head h1 { font-size:18px; margin:0 0 4px; letter-spacing:-0.01em; }
        .gantt-doc .head .subtitle { margin:0; color:#3f3f46; font-size:12.5px; }
        .gantt-doc .head .subtitle strong { color:#2563eb; }
        .gantt-doc .head .client { margin:3px 0 0; color:#71717a; font-size:11px; }
        .gantt-doc .head .meta { text-align:right; font-size:10.5px; color:#71717a; line-height:1.6; }
        .gantt-doc .empty { padding:48px 0; text-align:center; color:#a1a1aa; font-size:13px; }

        .gantt-doc .notice { margin-bottom:14px; padding:9px 13px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; font-size:10.5px; color:#92400e; line-height:1.5; }

        .gantt-doc .board { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; }
        .gantt-doc table { width:100%; border-collapse:collapse; table-layout:fixed; }

        /* Header dua tingkat: bulan lalu minggu */
        .gantt-doc thead .month-th { background:#f8fafc; padding:6px 4px; font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:#475569; border-left:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; text-align:center; }
        .gantt-doc thead .week-th { background:#f8fafc; padding:4px 2px; font-size:8.5px; font-weight:600; color:#94a3b8; border-left:1px solid #f1f5f9; border-bottom:1px solid #e5e7eb; text-align:center; }
        .gantt-doc thead .label-th { background:#f8fafc; padding:8px 10px; font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; color:#475569; border-bottom:1px solid #e5e7eb; border-right:1px solid #e5e7eb; text-align:left; vertical-align:middle; }
        .gantt-doc thead .overflow-th { background:#fffbeb; padding:6px 4px; font-size:8.5px; font-weight:700; color:#b45309; border-left:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; text-align:center; vertical-align:middle; }

        /* Sel grid mingguan: garis vertikal sangat tipis */
        .gantt-doc td.wk { border-left:1px solid #f1f5f9; }
        .gantt-doc td.label { border-right:1px solid #e5e7eb; padding:6px 10px; vertical-align:middle; }
        .gantt-doc td.cell { padding:3px 2px; vertical-align:middle; }
        .gantt-doc td.overflow-col { border-left:1px solid #e5e7eb; padding:3px 5px; text-align:center; font-size:8px; color:#b45309; vertical-align:middle; }

        /* Fase = awal grup: garis atas tegas + label ber-tint */
        .gantt-doc tr.phase td { border-top:1.5px solid #e2e8f0; }
        .gantt-doc tr.phase:first-child td { border-top:none; }
        .gantt-doc tr.phase td.label { background:#f8fafc; }
        .gantt-doc tr.phase .p-title { font-weight:700; line-height:1.25; }
        .gantt-doc tr.phase .p-dates { font-size:8px; color:#64748b; margin-top:2px; }

        .gantt-doc tr.act td.label { padding-left:20px; }
        .gantt-doc tr.act .a-title { display:flex; align-items:center; gap:5px; color:#334155; line-height:1.2; }
        .gantt-doc tr.act .dot { width:5px; height:5px; border-radius:50%; flex-shrink:0; }

        /* Bar pil membulat mengambang di atas grid */
        .gantt-doc .bar { border-radius:4px; box-shadow: 0 1px 2px rgba(0,0,0,0.12), inset 0 0 0 1px rgba(0,0,0,0.04); display:flex; align-items:center; overflow:hidden; }
        .gantt-doc .bar.phase-bar { height:19px; padding:0 9px; }
        .gantt-doc .bar.act-bar { height:13px; padding:0 6px; }
        .gantt-doc .bar .bar-label { color:#fff; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-shadow:0 1px 1px rgba(0,0,0,0.18); }
        .gantt-doc .bar.act-bar .bar-label { font-weight:500; font-size:8.5px; }

        .gantt-doc .legend { margin-top:16px; display:flex; gap:16px; flex-wrap:wrap; align-items:center; font-size:9.5px; color:#475569; }
        .gantt-doc .legend .swatch { width:9px; height:9px; border-radius:50%; display:inline-block; }
        .gantt-doc .legend .item { display:inline-flex; align-items:center; gap:5px; }
    </style>

    {{-- HEADER --}}
    <div class="head">
        <div>
            <h1>Timeline Implementasi Project</h1>
            <p class="subtitle">
                @if(! empty($project['code']))
                    <strong>{{ $project['code'] }}</strong> —
                @endif
                {{ $project['name'] ?? '-' }}
            </p>
            @if(! empty($project['client']))
            <p class="client">Client: {{ $project['client'] }}</p>
            @endif
        </div>
        <div class="meta">
            <div>Dicetak: {{ now()->locale('id')->translatedFormat('d F Y, H:i') }}</div>
            <div>Oleh: {{ Auth::user()->name }}</div>
        </div>
    </div>

    @if(empty($timelines) || empty($weeks))
        <div class="empty">Belum ada timeline pada project ini.</div>
    @else
    @php
        $rows = $this->rows;
        $totalWeeks = count($weeks);
        $labelWidth = 170;
        $overflowWidth = $truncated ? 88 : 0;
    @endphp

    @if($truncated)
    <div class="notice">
        Rentang timeline penuh sampai <strong>{{ $actualEndLabel }}</strong>. Tampilan dibatasi {{ $totalWeeks }} minggu pertama agar muat satu halaman — fase/aktivitas yang lebih panjang ditandai <strong>→</strong> menuju tanggal akhir aslinya.
    </div>
    @endif

    <div class="board">
    <table>
        <colgroup>
            <col style="width: {{ $labelWidth }}px;">
            @for ($i = 0; $i < $totalWeeks; $i++)
                <col style="width: calc((100% - {{ $labelWidth + $overflowWidth }}px) / {{ $totalWeeks }});">
            @endfor
            @if($truncated)
                <col style="width: {{ $overflowWidth }}px;">
            @endif
        </colgroup>
        <thead>
            {{-- Baris bulan --}}
            <tr>
                <th class="label-th" rowspan="2">Fase / Aktivitas DAR</th>
                @foreach($monthGroups as $group)
                <th class="month-th" colspan="{{ $group['span'] }}">{{ $group['label'] }}</th>
                @endforeach
                @if($truncated)
                <th class="overflow-th" rowspan="2">Lanjutan</th>
                @endif
            </tr>
            {{-- Baris minggu --}}
            <tr>
                @foreach($weeks as $week)
                <th class="week-th" title="{{ Carbon::parse($week['start'])->locale('id')->translatedFormat('d M') }} – {{ Carbon::parse($week['end'])->locale('id')->translatedFormat('d M') }}">{{ $week['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr class="phase">
                <td class="label">
                    <div class="p-title" style="{{ $row['title_style'] }}">{{ $row['title'] }}</div>
                    <div class="p-dates">{{ $row['start_label'] }} – {{ $row['end_label'] }}</div>
                </td>
                @for ($i = 0; $i < $row['leading']; $i++)
                <td class="wk"></td>
                @endfor
                <td class="cell wk" colspan="{{ $row['span'] }}">
                    <div class="bar phase-bar" style="background:{{ $row['color'] }};">
                        <span class="bar-label" style="{{ $row['title_style'] }}">{{ $row['title'] }}</span>
                    </div>
                </td>
                @for ($i = 0; $i < $row['trailing']; $i++)
                <td class="wk"></td>
                @endfor
                @if($truncated)
                <td class="overflow-col">
                    @if($row['overflow'])→ {{ $row['end_label'] }}@endif
                </td>
                @endif
            </tr>
            @foreach($row['activities'] as $activity)
            <tr class="act">
                <td class="label">
                    <div class="a-title" style="{{ $activity['title_style'] }}">
                        <span class="dot" style="background:{{ $activity['color'] }};"></span>
                        <span>{{ $activity['title'] }}</span>
                    </div>
                </td>
                @for ($i = 0; $i < $activity['leading']; $i++)
                <td class="wk"></td>
                @endfor
                <td class="cell wk" colspan="{{ $activity['span'] }}">
                    <div class="bar act-bar" style="background:{{ $activity['color'] }};" title="{{ $activity['title'] }} · {{ $activity['start_label'] }} – {{ $activity['end_label'] }} · {{ $activity['status_label'] }}">
                        @if($activity['span'] >= 1)
                        <span class="bar-label">{{ $activity['title'] }}</span>
                        @endif
                    </div>
                </td>
                @for ($i = 0; $i < $activity['trailing']; $i++)
                <td class="wk"></td>
                @endfor
                @if($truncated)
                <td class="overflow-col">
                    @if($activity['overflow'])→ {{ $activity['end_label'] }}@endif
                </td>
                @endif
            </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>
    </div>

    {{-- LEGEND --}}
    <div class="legend">
        <span style="font-weight:700;">Status DAR:</span>
        @foreach($this->statusLegend() as $status)
        <span class="item">
            <span class="swatch" style="background:{{ $status['color'] }};"></span>
            {{ $status['label'] }}
        </span>
        @endforeach
    </div>
    @endif
</div>
