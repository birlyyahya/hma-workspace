<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white p-2 rounded-xl">
            <div class="flex px-4 pt-4">
                <div class="block">
                    <flux:heading class="text-xl font-medium">Activity</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">Activity in the last 7 days</flux:text>
                    <div class="flex gap-10">
                        <div class="flex items-start mt-4 gap-2">
                            <div class="rounded-full shadow-sm p-0.5">
                                <div class="w-3 h-3 rounded-full bg-red-300"></div>
                            </div>
                            <div class="space-y-1">
                                <flux:text size="sm" class="font-normal">Active work</flux:text>
                                <flux:heading size="sm">420 <span class="font-normal text-gray-400">hrs</span></flux:heading>
                            </div>
                        </div>
                        <div class="flex items-start mt-4 gap-2">
                             <div class="rounded-full shadow-sm p-0.5">
                                 <div class="w-3 h-3 rounded-full bg-yellow-300"></div>
                             </div>
                            <div class="space-y-1">
                                <flux:text size="sm" class="font-normal">Active work</flux:text>
                                <flux:heading size="sm">420 <span class="font-normal text-gray-400">hrs</span></flux:heading>
                            </div>
                        </div>
                    </div>
                </div>
                <flux:button icon="calendar-days" variant="outline" size="xs" class="!p-3 ml-auto cursor-pointer">Daily Activity</flux:button>
            </div>
            <div id="chartline-activity"></div>
        </div>
        <div class="bg-white p-2 rounded-xl">
            <div class="flex p-4">
                <div class="block">
                    <flux:heading class="text-xl font-medium">Activity</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">Activity in the last 7 days</flux:text>
                    <div class="flex gap-10">
                        <div class="flex items-start mt-4 gap-2">
                            <div class="rounded-full shadow-sm p-0.5">
                                <div class="w-3 h-3 rounded-full bg-red-300"></div>
                            </div>
                            <div class="space-y-1">
                                <flux:text size="sm" class="font-normal">Active work</flux:text>
                                <flux:heading size="sm">420 <span class="font-normal text-gray-400">hrs</span></flux:heading>
                            </div>
                        </div>
                        <div class="flex items-start mt-4 gap-2">
                             <div class="rounded-full shadow-sm p-0.5">
                                 <div class="w-3 h-3 rounded-full bg-yellow-300"></div>
                             </div>
                            <div class="space-y-1">
                                <flux:text size="sm" class="font-normal">Active work</flux:text>
                                <flux:heading size="sm">420 <span class="font-normal text-gray-400">hrs</span></flux:heading>
                            </div>
                        </div>
                    </div>
                </div>

                <flux:button icon="calendar-days" variant="outline" size="xs" class="!p-3 ml-auto cursor-pointer">Daily Activity</flux:button>
            </div>
            <div id="chartbar-activity"></div>
        </div>
    </div>
</div>
<script>
    var optionLine = {
        series: [{
            name: 'series1'
            , data: [31, 40, 28, 51, 42, 109]
        }]
        , chart: {
            height: 250
            , type: 'area',
             toolbar: {
            autoSelected: "pan",
            show: false,
            }
        , }
        , dataLabels: {
            enabled: false
        }
        , stroke: {
            curve: 'smooth'
            , width: 3
            , colors: ['oklch(70.4% 0.191 22.216)']
        }
        , markers: {
            colors: ["oklch(44.4% 0.177 26.899)"]
        , }
        , fill: {
            type: "gradient"
            , gradient: {
                shadeIntensity: 1
                , opacityFrom: 0.7
                , opacityTo: 0.9
                , stops: [0, 100, 100]
                , colorStops: [{
                        offset: 0
                        , color: 'oklch(70.4% 0.191 22.216)'
                        , opacity: 1
                    }
                    , {
                        offset: 100
                        , color: 'oklch(70.4% 0.191 22.216)'
                        , opacity: 0
                    }
                ]
            },
            colors: 'oklch(70.4% 0.191 22.216)'
        }
        , xaxis: {
            type: 'category',
            categories: ["Sen, Apr 1", "Sel, Apr 2", "Rab, Apr 3", "Kam, Apr 4", "Jum, Apr5", "Sab, Apr 6"]
        , },
        yaxis: {
            labels: {
                formatter: function(val) {
                    return val + " h"
                }
            },
            labels: {
                minWidth: 0,
                align: 'left',
                  style: {
                    padding: 90,
                },
            }
        }
        , tooltip: {
            x: {
                format: 'dd/MM/yy HH:mm'
            , },
            theme: 'light'
        , }
    , };

    var optionBar = {
        series: [{
            name: 'Net Profit'
            , data: [1, 3, 4, 4.5, 5, 6, 5.5, 4.5, 5, 6, 7, 9]
        }]
        , chart: {
            type: 'bar'
            , height: 250,
             toolbar: {
            autoSelected: "pan",
            show: false
            }
        }
        , plotOptions: {
            bar: {
                horizontal: false
                , columnWidth: '55%'
                , borderRadius: 5
                , borderRadiusApplication: 'end'
            }
        , }
        , dataLabels: {
            enabled: false
        }
        , stroke: {
            show: true
            , width: 2
            , colors: ['transparent']
        }
        , xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        , }
        , yaxis: {
            title: {
                text: '$ (thousands)'
            }
            , labels: {
                formatter: function(val) {
                    return val + " h"
                }
            }
        }
        , fill: {
            opacity: 1
            , colors: ['oklch(70.4% 0.191 22.216)']
        }
        , tooltip: {
            y: {
                formatter: function(val) {
                    return "$ " + val + " thousands"
                }
            }
        }
    };


    var chartline = new ApexCharts(document.querySelector("#chartline-activity"), optionLine);
    var chartbar = new ApexCharts(document.querySelector("#chartbar-activity"), optionBar);
    chartline.render();
    chartbar.render();

</script>
