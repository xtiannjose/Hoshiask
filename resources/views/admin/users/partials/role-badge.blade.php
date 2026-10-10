@php
    $displayRole = \App\Models\User::publicRoleValue($role);
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
    @if($displayRole === 'admin') bg-indigo-100 text-indigo-800
    @elseif($displayRole === 'super_user') bg-blue-100 text-blue-800
    @elseif($displayRole === 'technical') bg-amber-100 text-amber-800
    @else bg-slate-100 text-slate-800
    @endif">
    {{ \App\Models\User::publicRoleLabel($displayRole) }}
</span>
