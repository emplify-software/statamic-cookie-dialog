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

    <script type="module">
        window.addEventListener("load", () => {
            document.querySelector(".page-wrapper h1").innerHTML = "{{ __('Cookie Dialog') }} <sup style='font-size:0.5em;display:inline-block;transform:translateY(-0.5em)'>{{ $isProEdition ? 'PRO' : 'FREE' }}</sup>"
        })
    </script>
@stop