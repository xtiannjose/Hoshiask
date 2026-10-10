<form method="GET" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4" data-submit-feedback data-search-history-form data-search-history-key="admin-user-filters">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6 xl:items-end">
        <div class="relative xl:col-span-2">
            <label for="search" class="sr-only">Search users</label>
            <input id="search" name="search" type="text"
                   value="{{ request('search') }}"
                   data-search-history-input
                   placeholder="Search"
                   class="h-10 block w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-700 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20"
                   autocomplete="off">
            <div class="search-history-panel hidden" data-search-history-panel></div>
        </div>

        <div>
            <label for="role" class="sr-only">Role</label>
            <select id="role" name="role" class="h-10 block w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20">
                <option value="all">All roles</option>
                @foreach($availableRolesFilter as $role)
                    <option value="{{ $role }}" {{ request('role', 'all') === $role ? 'selected' : '' }}>
                        {{ \App\Models\User::publicRoleLabel($role) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="department" class="sr-only">Department</label>
            <select id="department" name="department" class="h-10 block w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20">
                <option value="all">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department }}" {{ request('department', 'all') === $department ? 'selected' : '' }}>
                        {{ $department }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="sr-only">Status</label>
            <select id="status" name="status" class="h-10 block w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-hoshishi-primary focus:outline-none focus:ring-2 focus:ring-hoshishi-primary/20">
                <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-hoshishi-dark px-4 text-sm font-semibold text-white transition hover:brightness-95" data-loading-text="Filtering...">Filter</button>
            <a href="{{ ($segment ?? 'staff') === 'clients' ? route('admin.users.clients') : route('admin.users.index') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
        </div>
    </div>
</form>
