<?php

use Livewire\Volt\Component;

new class extends Component {
    public $totalTasks;
    public $completedTasks;
    public $inProgressTasks;
    public $overdueTasks;

    public $todayTasks = [];
    public $weeklyData = [];

    public function mount(){
        $this->weeklyData = [
        'Mon' => 3,
        'Tue' => 5,
        'Wed' => 2,
        'Thu' => 6,
        'Fri' => 4,
    ];
}
}; ?>

<div>
    <div class="space-y-6 mt-8">

        <div class="bg-white border rounded-2xl p-1">

            <div class="grid grid-cols-1 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x">

                {{-- ================= UNASSIGNED ================= --}}
                <div class="px-6 py-4">
                    <div class="flex items-start gap-4">

                        <div class="w-12 h-12 flex items-center justify-center rounded-full border-2 border-dashed border-gray-300">
                            <flux:icon name="ellipsis-horizontal-circle" class="w-6 h-6 text-gray-400" />
                        </div>

                        <div>
                            <p class="text-xs tracking-wider text-gray-400 uppercase">Unassigned</p>
                            <h2 class="text-lg font-semibold mt-1">47 Tasks</h2>
                        </div>
                    </div>
                    <p class="text-sm mt-4">
                        <span class="text-green-600 font-medium">+5%</span>
                        <span class="text-gray-400"> Increased from last month</span>
                    </p>
                </div>
                {{-- ================= IN PROGRESS ================= --}}
                  <div class="px-6 py-4">
                      <div class="flex items-start gap-4">
                          <div class="w-12 h-12 flex items-center justify-center rounded-full bg-indigo-100">
                              <flux:icon name="clock" class="w-6 h-6 text-indigo-600" />
                          </div>

                          <div>
                              <p class="text-xs tracking-wider text-gray-400 uppercase">Inprogress</p>
                              <h2 class="text-lg font-semibold mt-1">64 Tasks</h2>
                            </div>
                        </div>
                        <p class="text-sm mt-4">
                            <span class="text-red-600 font-medium">-25%</span>
                            <span class="text-gray-400"> drop from last month</span>
                        </p>
                  </div>


                {{-- ================= COMPLETED ================= --}}
                  <div class="px-6 py-4">
                      <div class="flex items-start gap-4">

                          <div class="w-12 h-12 flex items-center justify-center rounded-full bg-green-100">
                              <flux:icon name="check-circle" class="w-6 h-6 text-green-600" />
                          </div>

                          <div>
                              <p class="text-xs tracking-wider text-gray-400 uppercase">Completed</p>
                              <h2 class="text-lg font-semibold mt-1">112 Tasks</h2>
                            </div>
                        </div>
                        <p class="text-sm mt-4">
                            <span class="text-green-600 font-medium">+15%</span>
                            <span class="text-gray-400"> Increased from last month</span>
                        </p>
                  </div>


                {{-- ================= REVIEW ================= --}}
                  <div class="px-6 py-4">
                      <div class="flex items-start gap-4">
                          <div class="w-12 h-12 flex items-center justify-center rounded-full bg-red-100">
                              <flux:icon name="check-badge" class="w-6 h-6 text-red-600" />
                          </div>
                          <div>
                              <p class="text-xs tracking-wider text-gray-400 uppercase">Review</p>
                              <h2 class="text-lg font-semibold mt-1">74 Tasks</h2>
                            </div>
                        </div>
                        <p class="text-sm mt-4">
                            <span class="text-red-600 font-medium">-5%</span>
                            <span class="text-gray-400"> drop from last month</span>
                        </p>
                  </div>

            </div>

        </div>

    </div>
</div>
