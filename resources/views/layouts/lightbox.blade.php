<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js" integrity="sha256-u0L8aA6Ev3bY2HI4y0CAyr9H8FRWgX4hZ9+K7C2nzdc=" crossorigin="anonymous"></script>
    <script src="{{asset('js/ckeditor/ckeditor.js') }}"></script>

    <!-- Scripts 
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
-->

    <link rel="stylesheet" href="{{ url('/build/assets/app-D-sv12UV.css')}}" />
    <script src="{{ url('/build/assets/app-DlPQVG7t.js')}}" defer></script>

</head>

<body>
    <div id="app">
        <main class="">
            @yield('content')
        </main>
    </div>


    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        @include('inc/messages')
    </div>

</body>

</html>

<script type="text/javascript">
    modal = $(".modal").clone();
    $(".modal").remove();
    $("body").append(modal);
</script>

<script type="text/javascript">
    $(document).ready(function() {
        $("#myToastEl").toast('show');
    });
</script>
<script>
    $(function() {
        $('[data-bs-toggle="popover"]').popover({
            html: true,
            sanitize: false
        })
    })
</script>