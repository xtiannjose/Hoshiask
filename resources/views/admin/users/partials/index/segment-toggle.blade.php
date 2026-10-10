@if(auth()->user()->isSuperAdmin())
    <div class="mb-6 inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm">
        <a href="{{ route('admin.users.index') }}"
           class="rounded-lg px-3 py-1.5 font-medium {{ ($segment ?? 'staff') === 'staff' ? 'bg-hoshishi-blue-100 text-hoshishi-blue-800' : 'text-slate-600 hover:bg-slate-100' }}">
            Staff
        </a>
        <a href="{{ route('admin.users.clients') }}"
           class="rounded-lg px-3 py-1.5 font-medium {{ ($segment ?? 'staff') === 'clients' ? 'bg-hoshishi-blue-100 text-hoshishi-blue-800' : 'text-slate-600 hover:bg-slate-100' }}">
            Clients
        </a>
    </div>
@endif
