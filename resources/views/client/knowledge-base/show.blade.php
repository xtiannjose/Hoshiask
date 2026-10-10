@extends('layouts.app')

@section('title', $knowledgeArticle->title . ' - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
    <div class="mb-4">
        <a href="{{ route('client.knowledge-base.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back to Knowledge Base</a>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <x-knowledge-base.article :article="$knowledgeArticle" />
    </div>
</div>
@endsection
