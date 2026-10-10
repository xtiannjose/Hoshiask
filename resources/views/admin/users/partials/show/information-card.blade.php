<div class="bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h2 class="text-lg leading-6 font-medium text-slate-900">User Information</h2>
    </div>
    <div class="border-t border-slate-200 px-4 py-5 sm:px-6">
        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <dt class="text-sm font-medium text-slate-500">Username</dt>
                <dd class="mt-1 font-mono text-sm text-slate-900">{{ '@'.$user->username }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Display name</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Email address</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Phone number</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $user->phone ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Department</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $user->department ?? 'Not specified' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Role</dt>
                <dd class="mt-1">
                    @include('admin.users.partials.role-badge', ['role' => $user->role])
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Status</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </dd>
            </div>
            @if(auth()->user()->isShadow())
                <div>
                    <dt class="text-sm font-medium text-slate-500">Account created</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $user->created_at->format('F j, Y \a\t g:i A') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Last updated</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $user->updated_at->format('F j, Y \a\t g:i A') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Last seen</dt>
                    <dd class="mt-1">
                        @include('admin.users.partials.last-seen', ['isOnline' => $isOnline ?? false])
                    </dd>
                </div>
            @endif
            @if($user->isClient() && auth()->user()->isShadow())
                <div class="sm:col-span-2 xl:col-span-3">
                    <dt class="text-sm font-medium text-slate-500">Client Notes</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if(filled($user->client_notes))
                            {!! nl2br(e($user->client_notes)) !!}
                        @else
                            No notes added.
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
    </div>
</div>
