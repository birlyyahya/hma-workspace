<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <div class="bg-white rounded-2xl p-6 shadow-sm">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Weekly Report</h2>

            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Income
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-pink-400"></span> Previous Week
                </div>
            </div>
        </div>

        <div id="weeklyChart"></div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
   let weeklyChartInstance = null;

document.addEventListener('livewire:navigated', () => {

    const chartEl = document.querySelector("#weeklyChart");
    if (!chartEl) return;

    // Destroy chart lama kalau ada
    if (weeklyChartInstance) {
        weeklyChartInstance.destroy();
    }

    const options = {
        chart: {
            type: 'area',
            height: 300,
            width: '100%',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        series: [
            { name: 'Income', data: [32000, 8000, 26000, 12000, 24000, 5000, 22000] },
            { name: 'Previous Week', data: [6000, 20000, 15000, 30000, 21000, 9000, 13000] }
        ],
        xaxis: {
            categories: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
            labels: { style: { colors: '#9CA3AF' } }
        },
        yaxis: {
            labels: { formatter: val => (val/1000)+'k' }
        },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#3B82F6', '#F472B6'],
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.05, stops: [0,90,100] }
        },
        grid: { borderColor: '#eee', strokeDashArray: 5 },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 6 } },
        tooltip: {
            shared: true,
            y: { formatter: val => "Rp " + val.toLocaleString() }
        },
        legend: { show: false }
    };

    weeklyChartInstance = new ApexCharts(chartEl, options);
    weeklyChartInstance.render();

    // Fix sidebar/layout width issue
    setTimeout(() => window.dispatchEvent(new Event('resize')), 200);
});


</script>
