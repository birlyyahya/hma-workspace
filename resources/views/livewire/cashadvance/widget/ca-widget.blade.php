<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <div class="grid grid-cols-1 max-h-full lg:grid-cols-3">

        {{-- LEFT AREA --}}
        <div class="bg-white rounded-s-lg border h-full lg:col-span-2">

            {{-- BALANCE CARD --}}
            <div class="p-6 space-y-6">

                <div class="flex justify-between items-start">

                    <div class="space-y-2">
                        <p class="text-sm text-gray-500">Total Balance</p>

                        <h2 class="text-4xl font-semibold text-gray-900">
                            $116,849.00
                        </h2>

                        <p class="text-sm text-green-600 mt-1">
                            +$7,845.00 (12.09%) <span class="text-gray-400">vs last month</span>
                        </p>
                    </div>

                    <div class="flex gap-2 self-end">
                        <flux:modal.trigger name="sendModal">
                            <flux:button size="sm" variant="primary" class="cursor-pointer" iconTrailing="arrow-up-right">
                                Send
                            </flux:button>
                        </flux:modal.trigger>

                        <flux:modal.trigger name="receiveModal">
                            <flux:button size="sm" variant="outline" class="cursor-pointer" iconTrailing="arrow-down-left">
                                Receive
                            </flux:button>
                        </flux:modal.trigger>

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

                        <span class="text-green-600 text-xs bg-green-100 px-2 py-1 rounded">
                            +12.73%
                        </span>

                    </div>

                    <h3 class="text-2xl font-semibold mt-2">
                        9,834.00
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        You made an extra <span class="text-green-600">$1,245.00</span> this month
                    </p>

                </div>


                {{-- EXPENSE --}}
                <div class=" p-6">

                    <div class="flex items-center justify-between">

                        <p class="text-sm text-gray-500">Expense</p>

                        <span class="text-red-600 text-xs bg-red-100 px-2 py-1 rounded">
                            -3.19%
                        </span>

                    </div>

                    <h3 class="text-2xl font-semibold mt-2">
                        1,371.00
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        You made an extra <span class="text-green-600">$891.00</span> this month
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
                        My card
                    </h3>

                    <a class="text-sm text-orange-500 hover:underline">
                        See more
                    </a>
                </div>


                {{-- CARD ITEM --}}
                <div class="flex items-center justify-between">

                    <div class="flex items-center gap-3">

                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-purple-500 to-pink-500"></div>

                        <div>
                            <p class="text-sm font-medium">
                                **** **** **** 9213
                            </p>

                            <p class="text-xs text-gray-500">
                                06/28
                            </p>
                        </div>

                    </div>

                    <p class="text-sm font-medium">
                        $102,489.00
                    </p>

                </div>


                <div class="flex items-center justify-between">

                    <div class="flex items-center gap-3">

                        <div class="w-10 h-10 rounded-lg bg-blue-500"></div>

                        <div>
                            <p class="text-sm font-medium">
                                **** **** **** 9213
                            </p>

                            <p class="text-xs text-gray-500">
                                04/29
                            </p>
                        </div>

                    </div>

                    <p class="text-sm font-medium">
                        $10,238.00
                    </p>

                </div>


                <div class="flex items-center justify-between">

                    <div class="flex items-center gap-3">

                        <div class="w-10 h-10 rounded-lg bg-red-500"></div>

                        <div>
                            <p class="text-sm font-medium">
                                **** **** **** 9213
                            </p>

                            <p class="text-xs text-gray-500">
                                02/30
                            </p>
                        </div>

                    </div>

                    <p class="text-sm font-medium">
                        $4,122.00
                    </p>

                </div>


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


    {{-- Modal --}}
    <flux:modal name="sendModal" class="w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Send Money</flux:heading>
            </div>

            <flux:input label="Amount" type="number" placeholder="Enter amount"></flux:input>
            <flux:select label="Category" placeholder="Select a Category">
                <flux:select.option>Transportation</flux:select.option>
                <flux:select.option>Food</flux:select.option>
                <flux:select.option>Entertainment</flux:select.option>
                <flux:select.option>Electronic</flux:select.option>
                <flux:select.option>Health</flux:select.option>
                <flux:select.option>Clothing</flux:select.option>
                <flux:select.option>Education</flux:select.option>
                <flux:select.option>Travel</flux:select.option>
                <flux:select.option>Others</flux:select.option>
            </flux:select>
            <flux:input label="Notes" placeholder="Enter notes"></flux:input>
            <flux:button variant="primary" class="w-full">Send Money</flux:button>
        </div>
    </flux:modal>
    <flux:modal name="receiveModal" class="w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Received Money</flux:heading>
            </div>

            <flux:input label="Amount" type="number" placeholder="Enter amount"></flux:input>
            <flux:input label="Notes" placeholder="Enter notes"></flux:input>
            <flux:button variant="primary" class="w-full">Confirm</flux:button>
        </div>
    </flux:modal>
</div>
@script
<script>
    const optionLine = {
        series: [{
            name: 'Income'
            , data: [100000, 50000, 300008, 200000, 340000, 439200, 540020, 400000 ,1000000]
        }, {
            name: 'Expense'
            , data: [110000, 102000, 360000, 400000, 200000, 329000, 300000, 450000, 1000000]
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
            , categories: [
               "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
            ]
        }
        , yaxis: {
            labels: {
                minWidth: 0
                , align: 'left'
                , formatter: function(val) {
                    return "Rp " + val
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
    }

</script>
@endscript
