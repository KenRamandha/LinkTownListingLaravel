@foreach ($userActivities as $activity)
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-all group">
        <div class="flex gap-5">
            <!-- User Photo -->
            <div class="shrink-0">
                @if ($activity->avatar_url)
                    <img src="{{ $activity->avatar_url }}" alt="{{ $activity->name }}"
                        class="w-20 h-28 object-cover rounded-2xl border border-gray-100 shadow-sm">
                @else
                    <div
                        class="w-20 h-28 rounded-2xl bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center border border-gray-100">
                        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                @endif
            </div>

            <!-- Details -->
            <div class="flex-1 flex flex-col justify-between py-1">
                <div>
                    <h3 class="font-bold text-gray-900 leading-tight mb-3 line-clamp-1">
                        {{ $activity->name }}
                    </h3>

                    <div class="grid grid-cols-2 gap-y-3 gap-x-2">
                        <div class="space-y-1 cursor-pointer hover:bg-gray-50 p-1 rounded-lg transition-colors"
                            @click="openAttendanceModal('{{ $activity->id }}')">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">In</p>
                            <p
                                class="text-sm font-semibold {{ $activity->checkin_time ? 'text-blue-600' : 'text-gray-300' }}">
                                {{ $activity->checkin_time ? \Carbon\Carbon::parse($activity->checkin_time)->format('H:i') : '--:--' }}
                            </p>
                        </div>
                        <div class="space-y-1 cursor-pointer hover:bg-gray-50 p-1 rounded-lg transition-colors"
                            @click="openAttendanceModal('{{ $activity->id }}')">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Out</p>
                            <p
                                class="text-sm font-semibold {{ $activity->checkout_time ? 'text-indigo-600' : 'text-gray-300' }}">
                                {{ $activity->checkout_time ? \Carbon\Carbon::parse($activity->checkout_time)->format('H:i') : '--:--' }}
                            </p>
                        </div>
                        <div class="space-y-1 cursor-pointer hover:bg-gray-50 p-1 rounded-lg transition-colors"
                            @click="openVisitModal('{{ $activity->id }}')">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Visits</p>
                            <p
                                class="text-sm font-semibold {{ $activity->total_visit ? 'text-green-600' : 'text-gray-300' }}">
                                {{ number_format($activity->total_visit ?? 0) }}
                            </p>
                        </div>
                        <div class="space-y-1 cursor-pointer hover:bg-gray-50 p-1 rounded-lg transition-colors"
                            @click="openVisitModal('{{ $activity->id }}')">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Last Visit</p>
                            <p
                                class="text-sm font-semibold {{ $activity->last_visit_time ? 'text-orange-600' : 'text-gray-300' }}">
                                {{ $activity->last_visit_time ? \Carbon\Carbon::parse($activity->last_visit_time)->format('H:i') : '--:--' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach