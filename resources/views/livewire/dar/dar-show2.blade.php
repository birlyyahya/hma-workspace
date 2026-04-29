<?php

use function Livewire\Volt\{state};

state([
    'task' => [
        'title' => 'UI/UX: Design and implement user interfaces',
        'priority' => 'Medium Priority',
        'status' => 'In Progress',
        'deadline' => 'Mar 14, 2025',
        'tracked_time' => '2h 41m',
        'date' => 'Mar 02, 2025',
        'description' => 'In this task, you will be responsible for designing and implementing user interfaces that are visually appealing, user-friendly, and aligned with best UX practices.',
    ],
    'comments' => [
        [
            'name' => 'Arnold Tanner',
            'message' => 'I have attached the requirements file. Please check it.',
            'time' => 'Mar 02, 2025 05:23 PM',
            'avatar' => 'https://i.pravatar.cc/40?img=1'
        ],
        [
            'name' => 'Dale Bartlett',
            'message' => 'OK, I got it. I will review it.',
            'time' => 'Mar 02, 2025 05:41 PM',
            'avatar' => 'https://i.pravatar.cc/40?img=2'
        ]
    ],
]);
?>

<div class="min-h-screen bg-[#0f1120] text-white p-6">
    <div class="max-w-7xl mx-auto">

        <!-- HEADER -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <p class="text-sm text-gray-400">Task 029384hg</p>
                <h1 class="text-2xl font-semibold mt-1">
                    {{ $task['title'] }}
                </h1>

                <div class="flex items-center gap-3 mt-3">
                    <span class="px-3 py-1 text-xs rounded-full border border-yellow-400 text-yellow-400">
                        {{ $task['priority'] }}
                    </span>

                    <span class="px-3 py-1 text-xs rounded-full bg-indigo-600">
                        Frontend
                    </span>

                    <span class="text-sm text-gray-400">
                        {{ $task['date'] }}
                    </span>
                </div>
            </div>

            <button class="text-gray-400 hover:text-white">✕</button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- LEFT -->
            <div class="lg:col-span-2 space-y-6">

                <!-- INFO -->
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-400">Status</p>
                        <p class="mt-1">{{ $task['status'] }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Deadline</p>
                        <p class="mt-1">{{ $task['deadline'] }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Tracked time</p>
                        <p class="mt-1">{{ $task['tracked_time'] }}</p>
                    </div>
                </div>

                <!-- DESCRIPTION -->
                <div>
                    <h2 class="font-semibold mb-2">Description</h2>
                    <p class="text-gray-300 text-sm leading-relaxed">
                        {{ $task['description'] }}
                    </p>
                </div>

                <!-- ATTACHMENTS -->
                <div>
                    <h2 class="font-semibold mb-3">Attachments</h2>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center bg-white/5 p-4 rounded-xl">
                            <div>
                                <p class="text-sm">List of requirements.doc</p>
                                <span class="text-xs text-gray-400">1.3 Mb</span>
                            </div>
                            <div class="flex gap-2 text-gray-400">
                                <button>🗑</button>
                                <button>⬇</button>
                            </div>
                        </div>

                        <div class="flex justify-between items-center bg-white/5 p-4 rounded-xl">
                            <div>
                                <p class="text-sm">Clients Brandbook.pdf</p>
                                <span class="text-xs text-gray-400">5.7 Mb</span>
                            </div>
                            <div class="flex gap-2 text-gray-400">
                                <button>🗑</button>
                                <button>⬇</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT (COMMENTS) -->
            <div x-data="{ tab: 'comments' }" class="bg-white/5 rounded-2xl p-4 flex flex-col">

                <!-- TABS -->
                <div class="flex gap-2 mb-4">
                    <button
                        @click="tab='comments'"
                        :class="tab==='comments' ? 'bg-white text-black' : 'text-gray-400'"
                        class="px-4 py-2 rounded-full text-sm">
                        Comments
                    </button>
                    <button
                        @click="tab='updates'"
                        :class="tab==='updates' ? 'bg-white text-black' : 'text-gray-400'"
                        class="px-4 py-2 rounded-full text-sm">
                        Updates
                    </button>
                </div>

                <!-- COMMENTS LIST -->
                <div class="space-y-4 flex-1 overflow-y-auto">
                    <template x-if="tab==='comments'">
                        <div>
                            @foreach($comments as $comment)
                                <div class="flex gap-3 bg-white/5 p-3 rounded-xl mb-3">
                                    <img src="{{ $comment['avatar'] }}" class="w-10 h-10 rounded-full">

                                    <div class="flex-1">
                                        <div class="flex justify-between text-sm">
                                            <p class="font-medium">{{ $comment['name'] }}</p>
                                            <span class="text-gray-400 text-xs">{{ $comment['time'] }}</span>
                                        </div>

                                        <p class="text-sm text-gray-300 mt-1">
                                            {{ $comment['message'] }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </template>
                </div>

                <!-- INPUT -->
                <div class="mt-4 flex gap-2">
                    <input
                        type="text"
                        placeholder="Write a comment..."
                        class="flex-1 bg-white/10 border-none rounded-lg px-3 py-2 text-sm focus:outline-none"
                    >
                    <button class="bg-indigo-600 px-4 py-2 rounded-lg text-sm">
                        Send
                    </button>
                </div>

            </div>

        </div>

    </div>
</div>
