@extends('layouts.app')

@section('title', 'Unlock Monitoring Tools - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="font-display text-2xl font-semibold text-slate-900">Unlock Monitoring Tools</h1>
            <p class="mt-1 text-sm text-slate-500">
                Re-enter your shadow account password to view system logs, online users, and sign-in activity. Access stays unlocked for 20 minutes.
            </p>
        </div>

        <form action="{{ route('admin.system-logs.unlock.store') }}" method="POST" class="space-y-5 px-6 py-6">
            @csrf
            @if($returnTo)
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
            @endif
            <div>
                <label for="password" class="form-label">
                    Shadow Account Password <span class="text-red-500">*</span>
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autofocus
                    class="form-input @error('password') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                    placeholder="Enter your password"
                >
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                <a href="{{ route('admin.dashboard') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Unlock</button>
            </div>
        </form>
    </div>
</div>
@endsection
