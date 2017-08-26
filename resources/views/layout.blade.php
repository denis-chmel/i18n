<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta id="token" name="csrf-token" content="{{ csrf_token() }}">

    <title>Yulia Translate</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

</head>
<body>

@if (\Session::has('error')):
<div class="alert alert-danger">
    <strong>{!! e(\Session::get('error'))  !!}</strong>
</div>
@endif

@yield('contents')

<script type="text/javascript" src="{{ asset('js/app.js') }}?v=1"></script>
@yield('footer-scripts')

</body>
</html>
