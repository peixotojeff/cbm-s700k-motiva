@props(['dias'])
@if ($dias === null)
    <span class="text-slate-500">n/d</span>
@elseif ($dias <= 0)
    <span class="font-semibold text-red-700">agora</span>
@else
    ~{{ round($dias) }} d
@endif
