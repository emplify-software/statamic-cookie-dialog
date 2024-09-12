@extends('statamic::layout')
@section('title', 'Cookie Dialog')

@section('content')
    <publish-form
        title="{{ __('Cookie Dialog') }}"
        action="{{ cp_route('cookie-dialog.update') }}"
        :blueprint='@json($blueprint)'
        :meta='@json($meta)'
        :values='@json($values)'
    ></publish-form>
@stop
