<div class="flex gap-4 h-[calc(100vh-10rem)]">
    {{-- Detail Panel (1/3) --}}
    <div class="w-1/3 overflow-y-auto custom-scrollbar">
        @if ($this->selectedAssessment)
            @php $a = $this->selectedAssessment; @endphp
            <div class="card bg-base-200 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="card-title text-lg">
                        {{ $a['work_order'] }} / {{ $a['extension'] }}
                    </h2>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <span class="text-base-content/60">Status</span>
                        <span>{{ $a['status'] }}</span>

                        <span class="text-base-content/60">Region</span>
                        <span>{{ $a['region'] ?? '—' }}</span>

                        <span class="text-base-content/60">Assigned To</span>
                        <span>{{ $a['assigned_to'] ?? '—' }}</span>

                        <span class="text-base-content/60">% Complete</span>
                        <span>{{ $a['percent_complete'] ?? 0 }}%</span>

                        <span class="text-base-content/60">Last Edited</span>
                        <span>{{ $a['last_edited'] ? \Carbon\Carbon::parse($a['last_edited'])->format('M j, Y') : '—' }}</span>
                    </div>

                    @if (! empty($a['metrics']))
                        <div class="divider my-0"></div>
                        <h3 class="font-semibold text-sm">Metrics</h3>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <span class="text-base-content/60">Total Units</span>
                            <span>{{ $a['metrics']['total_units'] }}</span>

                            <span class="text-base-content/60">Approved</span>
                            <span>{{ $a['metrics']['approved'] }}</span>

                            <span class="text-base-content/60">Pending</span>
                            <span>{{ $a['metrics']['pending'] }}</span>

                            <span class="text-base-content/60">Refused</span>
                            <span>{{ $a['metrics']['refused'] }}</span>

                            <span class="text-base-content/60">Stations With Work</span>
                            <span>{{ $a['metrics']['stations_with_work'] }}</span>

                            <span class="text-base-content/60">Oldest Pending</span>
                            <span>{{ $a['metrics']['oldest_pending_date'] ? \Carbon\Carbon::parse($a['metrics']['oldest_pending_date'])->format('M j, Y') : '—' }}</span>
                        </div>
                    @endif

                    @if (! empty($a['contributors']))
                        <div class="divider my-0"></div>
                        <h3 class="font-semibold text-sm">Contributors</h3>
                        <div class="space-y-1">
                            @foreach ($a['contributors'] as $c)
                                <div class="flex justify-between text-sm">
                                    <span>{{ $c['ws_username'] }}</span>
                                    <span class="text-base-content/60">{{ $c['unit_count'] }} units</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="flex items-center justify-center h-full text-base-content/40">
                No assessment selected
            </div>
        @endif
    </div>

    {{-- Table Panel (2/3) --}}
    <div class="w-2/3 overflow-y-auto custom-scrollbar">
        <table class="table table-sm table-zebra">
            <thead class="sticky top-0 bg-base-100 z-10">
                <tr>
                    <th>Work Order</th>
                    <th>Status</th>
                    <th>% Complete</th>
                    <th>Region</th>
                    <th>Assigned To</th>
                    <th>Oldest Pending</th>
                    <th>Total Units</th>
                    <th>Appr</th>
                    <th>Pend</th>
                    <th>Ref</th>
                    <th>Stations w/ Work</th>
                    <th>Last Edited</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->assessments as $assessment)
                    <tr
                        wire:click="selectAssessment('{{ $assessment['job_guid'] }}')"
                        class="cursor-pointer hover {{ ($this->selectedAssessment['job_guid'] ?? null) === $assessment['job_guid'] ? 'active' : '' }}"
                    >
                        <td class="font-mono">{{ $assessment['work_order'] }} / {{ $assessment['extension'] }}</td>
                        <td>{{ $assessment['status'] }}</td>
                        <td>{{ $assessment['percent_complete'] ?? 0 }}%</td>
                        <td>{{ $assessment['region'] ?? '—' }}</td>
                        <td>{{ $assessment['assigned_to'] ?? '—' }}</td>
                        <td>{{ isset($assessment['metrics']['oldest_pending_date']) ? \Carbon\Carbon::parse($assessment['metrics']['oldest_pending_date'])->format('M j') : '—' }}</td>
                        <td>{{ $assessment['metrics']['total_units'] ?? '—' }}</td>
                        <td>{{ $assessment['metrics']['approved'] ?? '—' }}</td>
                        <td>{{ $assessment['metrics']['pending'] ?? '—' }}</td>
                        <td>{{ $assessment['metrics']['refused'] ?? '—' }}</td>
                        <td>{{ $assessment['metrics']['stations_with_work'] ?? '—' }}</td>
                        <td>{{ $assessment['last_edited'] ? \Carbon\Carbon::parse($assessment['last_edited'])->format('M j') : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center text-base-content/40 py-8">
                            No active assessments found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
