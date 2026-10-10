@extends('errors.layout')

@php
    $customMessage = trim((string) ($exception?->getMessage() ?? ''));
    $isStaffTicketPage = rescue(static fn () => request()->route()?->getName(), '', false) === 'admin.tickets.show';
    $message = match (true) {
        $isStaffTicketPage => 'This ticket is assigned to someone else, so it is not available to your account. If you need it, ask a super user to assign it to you.',
        $customMessage !== '' && $customMessage !== 'This action is unauthorized.' => $customMessage,
        default => 'Your account does not have access to this page. If you followed a link someone sent you, it may belong to another account.',
    };
@endphp

@section('title', 'No access')
@section('code', 'Error 403')
@section('art', 'thinking')
@section('heading', 'You cannot open this page')
@section('message', $message)
