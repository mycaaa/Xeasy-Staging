{{--
    Tracker #35 / #19: read-only participant list for the punch, verifier and
    approver claim modals (Team Meeting 42, activity claims, Group Meal 17).
    Props (all optional): title, pax, employees (array|string of codes/names),
    others (array of [name, category, details] or raw JSON/text), text (free text),
    department, count, files (array of file names).
--}}
@props(['title' => 'Participants', 'pax' => null, 'employees' => [], 'others' => [], 'text' => null, 'department' => null, 'count' => null, 'files' => []])
@php
    $employeeList = is_array($employees)
        ? $employees
        : array_values(array_filter(array_map('trim', explode(',', (string) $employees))));
    $otherList = is_array($others) ? $others : \App\Helpers\CommonHelper::parseOtherParticipants((string) $others);
    $fileList = array_values(array_filter(is_array($files) ? $files : (array) json_decode((string) $files, true)));
    $textValue = trim((string) $text);
    $shownCount = count($employeeList) + count($otherList);
    $hasAny = $shownCount > 0 || $textValue !== '' || !empty($fileList) || trim((string) $department) !== '' || (int) $count > 0;
@endphp
<div class="card border border-info shadow-sm my-2 claim-participants-panel">
    <div class="card-header bg-info-subtle text-info fw-bold d-flex justify-content-between align-items-center py-2 px-3" style="font-size: 12px;">
        <span><i class="ri-group-line me-1"></i>{{ $title }}</span>
        <span>
            @if(!is_null($pax) && $pax !== '')
                <span class="badge bg-info text-white">PAX: {{ $pax }}</span>
            @endif
            @if((int) $count > 0)
                <span class="badge bg-info text-white">Count: {{ (int) $count }}</span>
            @endif
            @if($shownCount > 0)
                <span class="badge bg-secondary text-white">{{ count($employeeList) }} employee(s), {{ count($otherList) }} other(s)</span>
            @endif
        </span>
    </div>
    <div class="card-body py-2 px-3" style="font-size: 12px;">
        @if(!$hasAny)
            <span class="text-danger fw-semibold">Not given</span>
        @endif
        @if(trim((string) $department) !== '')
            <div><strong>Department:</strong> {{ $department }}</div>
        @endif
        @if($textValue !== '')
            <div class="text-break"><strong>Details:</strong> {{ $textValue }}</div>
        @endif
        @if(count($employeeList) > 0)
            <div class="mt-1"><strong>Employees:</strong> {{ implode(', ', $employeeList) }}</div>
        @endif
        @if(count($otherList) > 0)
            <table class="table table-sm table-bordered mb-1 mt-1">
                <thead class="table-light"><tr><th style="width:40px;">#</th><th>Other participant</th><th>Category</th><th>Details</th></tr></thead>
                <tbody>
                    @foreach($otherList as $i => $other)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ is_array($other) ? ($other['name'] ?? '') : $other }}</td>
                            <td>{{ is_array($other) ? ($other['category'] ?? '') : '' }}</td>
                            <td>{{ is_array($other) ? ($other['details'] ?? '') : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if(!empty($fileList))
            <div class="mt-1"><strong>Participant list file(s):</strong> {{ implode(', ', $fileList) }}</div>
        @endif
    </div>
</div>
