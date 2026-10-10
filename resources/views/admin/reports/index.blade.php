@extends('layouts.app')

@section('title', 'Reports - ' . config('app.name'))

@section('content')
    @include('admin.reports.partials.shell')
@endsection
