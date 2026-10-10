@extends('layouts.app')

@section('title', 'Edit Article - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="font-display text-3xl font-semibold text-slate-900">Edit Article</h1>
    </div>

    <form action="{{ route('admin.knowledge-base.update', $article) }}" method="POST" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('admin.knowledge-base.partials.form')
        <div class="flex items-center gap-2">
            <button type="submit" class="btn-primary">Save Changes</button>
            <a href="{{ route('admin.knowledge-base.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
