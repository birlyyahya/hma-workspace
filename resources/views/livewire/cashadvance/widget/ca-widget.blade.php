<?php

use App\Services\CaCache;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    /** @var array<string, mixed> Dompet PL utama (data_category id 1, status approved) */
    public array $dompetPl = [];

    /** @var array<int, array<string, mixed>> Daftar dompet kegiatan (data_category id 2) */
    public array $dompetKegiatan = [];

    /** @var array<int, string> Label bulan untuk sumbu X chart */
    public array $chartCategories = [];

    /** @var array<int, float> Total penerimaan per bulan */
    public array $chartIncome = [];

    /** @var array<int, float> Total pengeluaran per bulan */
    public array $chartExpense = [];

    public function mount(CaCache $ca): void
    {
        $userId = (int) Auth::id();

        $this->dompetPl = $ca->dompetPl($userId);
        $this->dompetKegiatan = $ca->dompetKegiatan($userId);

        if (empty($this->dompetPl) && empty($this->dompetKegiatan)) {
            Toaster::error('Belum ada data dompet yang tersedia');
        }

        $this->buildChart($ca);
    }

    #[On('transaksi-added')]
    public function refreshDompet(CaCache $ca): void
    {
        $userId = (int) Auth::id();

        $this->dompetPl = $ca->dompetPl($userId);
        $this->dompetKegiatan = $ca->dompetKegiatan($userId);

        $this->buildChart($ca);

        $this->dispatch('ca-chart-updated', income: $this->chartIncome, expense: $this->chartExpense);
    }

    protected function buildChart(CaCache $ca): void
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $income = array_fill(0, 12, 0.0);
        $expense = array_fill(0, 12, 0.0);

        if (! empty($this->dompetPl)) {
            $saldoAwal = (float) ($this->dompetPl['total_penerimaan'] ?? 0);

            foreach ($ca->transaksi($this->dompetPl['kode_ca']) as $trx) {
                $monthIndex = (int) \Carbon\Carbon::parse($trx['tanggal'])->format('n') - 1;
                $amount = (float) ($trx['jumlah'] ?? 0);

                if (($trx['jenis'] ?? null) === 'penerimaan') {
                    $income[$monthIndex] += $amount;
                    $saldoAwal -= $amount;
                } else {
                    $expense[$monthIndex] += $amount;
                }
            }

            $startMonth = (int) \Carbon\Carbon::parse($this->dompetPl['tanggal_mulai'])->format('n') - 1;
            $income[$startMonth] += max($saldoAwal, 0);
        }

        $this->chartCategories = $months;
        $this->chartIncome = array_values($income);
        $this->chartExpense = array_values($expense);
    }
}; ?>

<div>
    <div class="grid grid-cols-1 max-h-full lg:grid-cols-3">

        {{-- LEFT AREA --}}
        <div class="bg-white rounded-s-lg border h-full lg:col-span-2">

            {{-- BALANCE CARD --}}
            <div class="p-6 space-y-6">

                <div class="flex justify-between items-start">

                    <div class="space-y-2">
                        <p class="text-sm text-gray-500">
                            Total Balance
                            @if (! empty($dompetPl))
                                <span class="text-gray-400">• {{ $dompetPl['judul_kegiatan'] }}</span>
                            @endif
                        </p>

                        <h2 class="text-4xl font-semibold text-gray-900">
                            Rp {{ number_format((float) ($dompetPl['saldo_akhir'] ?? 0), 2, ',', '.') }}
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            @if (! empty($dompetPl))
                                {{ $dompetPl['kode_ca'] }} • Tahun Anggaran {{ $dompetPl['tahun_anggaran'] }}
                            @else
                                Belum ada dompet PL yang disetujui
                            @endif
                        </p>
                    </div>

                    <div class="flex gap-2 self-end">
                        @if (! empty($dompetPl))
                            <livewire:cashadvance.transaksi-modal :kode-ca="$dompetPl['kode_ca']" :key="'trx-pl-' . $dompetPl['kode_ca']" />
                        @endif

                        <flux:button size="sm" variant="outline" class="cursor-pointer" iconTrailing="plus">
                            Top up
                        </flux:button>

                    </div>

                </div>

                {{-- CHART AREA --}}
                <div id="chartline-ca" class="h-82 w-full rounded-lg flex items-center justify-center text-gray-400 text-sm">
                </div>


            </div>


            {{-- STATS --}}
            <div class="grid md:grid-cols-2 border-t">

                {{-- INCOME --}}
                <div class=" border-e p-6">

                    <div class="flex items-center justify-between">

                        <p class="text-sm text-gray-500">Income</p>

                        <span class="text-blue-600 text-xs bg-blue-100 px-2 py-1 rounded">
                            Penerimaan
                        </span>

                    </div>

                    <h3 class="text-2xl font-semibold mt-2">
                        Rp {{ number_format((float) ($dompetPl['total_penerimaan'] ?? 0), 2, ',', '.') }}
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        Total dana yang diterima pada dompet PL
                    </p>

                </div>


                {{-- EXPENSE --}}
                <div class=" p-6">

                    <div class="flex items-center justify-between">

                        <p class="text-sm text-gray-500">Expense</p>

                        <span class="text-red-600 text-xs bg-red-100 px-2 py-1 rounded">
                            Pengeluaran
                        </span>

                    </div>

                    <h3 class="text-2xl font-semibold mt-2">
                        Rp {{ number_format((float) ($dompetPl['total_pengeluaran'] ?? 0), 2, ',', '.') }}
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        Total dana yang dikeluarkan pada dompet PL
                    </p>

                </div>

            </div>

        </div>

        {{-- RIGHT SIDEBAR --}}
        <div class="bg-white rounded-e-lg border border-l-0">

            {{-- MY CARD --}}
            <div class="p-6 space-y-4 border-b">

                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">
                        Dompet kegiatan
                    </h3>

                    <a class="text-sm text-orange-500 hover:underline">
                        See more
                    </a>
                </div>


                {{-- CARD ITEM --}}
                @forelse ($dompetKegiatan as $item)
                <a
                    wire:key="dompet-{{ $item['kode_ca'] }}"
                    href="{{ route('cashadvance.dompet-show', $item['kode_ca']) }}"
                    wire:navigate
                    class="flex items-center justify-between w-full -mx-2 px-2 py-1.5 rounded-lg cursor-pointer hover:bg-gray-50 transition"
                >

                    <div class="flex items-center gap-3">

                        <flux:avatar :name="$item['judul_kegiatan']" color="blue" />

                        <div class="text-left">
                            <p class="text-sm font-medium">
                                {{ $item['judul_kegiatan'] }}
                            </p>

                            <p class="text-xs text-gray-500">
                                {{ $item['tahun_anggaran'] }}
                            </p>
                        </div>

                    </div>

                    <p class="text-sm font-medium">
                        Rp {{ number_format((float) $item['saldo_akhir'], 2, ',', '.') }}
                    </p>

                </a>
                @empty
                <p class="text-sm text-gray-400 text-center py-2">
                    Belum ada dompet kegiatan
                </p>
                @endforelse


                <flux:button variant="outline" class="w-full">
                    + Create new card
                </flux:button>

            </div>



            {{-- SAVINGS --}}
            <div class="p-6 space-y-4 flex flex-col">

                <p class="text-sm text-gray-500">
                    Total your savings
                </p>

                <h3 class="text-3xl font-semibold">
                    $82,819.00
                </h3>


                {{-- SAVING BAR --}}
                <div class="h-12 rounded  bg-gray-100 overflow-hidden flex">
                    <div class="bg-orange-400/20 border-l-2 border-orange-500 w-[46%]"></div>
                    <div class="bg-blue-400/20 border-l-2 border-blue-500 w-[22%]"></div>
                    <div class="bg-purple-400/20 border-l-2 border-purple-500  w-[12%]"></div>
                    <div class="bg-pink-400/20 border-l-2 border-pink-500 w-[11%]"></div>
                    <div class="bg-green-400/20 border-l-2 border-green-500 w-[9%]"></div>
                </div>


                <div class="flex flex-wrap gap-4 text-[11px] text-gray-400 text-xs">
                    <p class="border-l-2 ps-2 border-orange-500">Emergency fund • 46%</p>
                    <p class="border-l-2 ps-2 border-blue-500">BPJS • 22%</p>
                    <p class="border-l-2 ps-2 border-purple-500">Pay rent • 12%</p>
                    <p class="border-l-2 ps-2 border-pink-500">App subscription • 11%</p>
                    <p class="border-l-2 ps-2 border-green-500">Shopping • 9%</p>

                </div>

                <p class="text-sm text-gray-600 pt-5 border-t content-end">
                    👍 Great job! Your savings have increased
                    <span class="text-green-600">20%</span> from last month.
                </p>

            </div>

        </div>

    </div>
</div>
@script
<script>
    const optionLine = {
        series: [{
            name: 'Income'
            , data: @json($chartIncome)
        }, {
            name: 'Expense'
            , data: @json($chartExpense)
        }]
        , chart: {
            height: 290
            , type: 'area'
            , toolbar: {
                show: false
            }
        }
        , dataLabels: {
            enabled: false
        }
        , stroke: {
            curve: 'smooth'
            , width: 3
            , colors: ['#2E93fA', '#E91E63']
        }
        , markers: {
            colors: ["#2E93fA", "#E91E63"]
        }
        , colors: ['#2E93fA', '#E91E63']
        , xaxis: {
            type: 'category'
            , categories: @json($chartCategories)
        }
        , yaxis: {
            labels: {
                minWidth: 0
                , align: 'left'
                , formatter: function(val) {
                    return "Rp " + new Intl.NumberFormat('id-ID').format(val)
                }
            }
        }
        , tooltip: {
            theme: 'light'
        }
    };

    const el = document.querySelector("#chartline-ca");

    if (el) {
        const chartline = new ApexCharts(el, optionLine);
        chartline.render();

        $wire.on('ca-chart-updated', ({ income, expense }) => {
            chartline.updateSeries([
                { name: 'Income', data: income }
                , { name: 'Expense', data: expense }
            ]);
        });
    }

</script>
@endscript
