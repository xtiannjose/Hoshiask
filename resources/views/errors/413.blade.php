@extends('errors.layout')

@section('title', 'Upload too large')
@section('code', 'Error 413')
@section('art', 'thinking')
@section('heading', 'That upload is too large')
@section('message', 'The files you sent are bigger than the server accepts in one go. Go back to the form, attach fewer or smaller files (10 MB each at most), and send it again.')
