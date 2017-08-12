<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta id="token" name="csrf-token" content="{{ csrf_token() }}">

        <title>Yulia Translate</title>

        <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    </head>
    <body>

        <div id="app" v-cloak>
        @yield('contents')
        </div>

        <script type="text/javascript" src="{{ asset('js/app.js') }}"></script>
        @yield('footer-scripts')

    </body>
</html>
