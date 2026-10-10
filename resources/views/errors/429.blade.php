@extends('errors.layout')

@section('title', 'Too many requests')
@section('code', 'Error 429')
@section('art', 'thinking')
@section('heading', 'Please slow down for a moment')
@section('message', 'Too many requests were sent in a short time. Wait a minute, then try again.')
