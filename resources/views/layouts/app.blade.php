<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <script>
        // Read by public/js/typeahead/typeahead.js's field-suggestion loading
        // indicator. Null (no custom GIF configured — see /settings) means it
        // falls back to a plain CSS spinner instead of an image.
        window.APP_LOADING_GIF_URL = @json($branding->loadingGifUrl());
    </script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js" integrity="sha256-u0L8aA6Ev3bY2HI4y0CAyr9H8FRWgX4hZ9+K7C2nzdc=" crossorigin="anonymous"></script>
    @can('manage-content')
    <script>window.EDITOR_FILES = {!! json_encode(['images' => url('editor-files') . '?type=images', 'files' => url('editor-files') . '?type=files']) !!};</script>
    @endcan
    <script src="{{asset('js/ckeditor/ckeditor.js') }}"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>

<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    @if ($branding->logoUrl())
                        <img src="{{ $branding->logoUrl() }}" alt="{{ config('app.name', 'Laravel') }}" width="" height="24">
                    @else
                        {{ config('app.name', 'Laravel') }}
                    @endif
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        @if(isset($nav_menu))
                            @foreach($nav_menu as $menu_item)
                                @include('layouts.partials.menu_item', ['item' => $menu_item, 'depth' => 0])
                            @endforeach
                        @else
                        {{-- the menu it always had, until the navigation tables exist --}}
                        <li class="nav-item"><a class="nav-link" href="{{ url('projects') }}"><?php echo get_dialogs('Projects', $active_language); ?></a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="{{ url('about') }}"><?php echo get_dialogs('About', $active_language); ?></a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="{{ url('tutorial') }}"><?php echo get_dialogs('tutorial', $active_language); ?></a>
                        </li>
                        @endif
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                        @if (Route::has('login'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}"><?php echo get_dialogs('Login', $active_language); ?></a>
                        </li>
                        @endif

                        @if (registration_open())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}"><?php echo get_dialogs('Register', $active_language); ?></a>
                        </li>
                        @endif
                        @else
                        @canany(['manage-projects', 'manage-users', 'edit-dialogs', 'manage-fields', 'run-sync', 'resolve-conflicts', 'view-all-submissions', 'manage-settings', 'manage-access', 'manage-languages', 'manage-content'])
                            @can('edit-dialogs')
                            <li class="nav-item d-flex align-items-center me-2">
                                <button type="button" id="dialogEditModeToggle" class="btn btn-sm btn-outline-secondary" title="{{ get_dialogs_simple('Toggle inline text editing', $active_language) }}">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </li>
                            @endcan

                            {{-- the website itself: the menu, the contents and the footer --}}
                            @can('manage-content')
                            <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                {!! get_dialogs('Content', $active_language) !!}
                            </a>
                            <ul class="dropdown-menu" role="navbarDropdown">
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('contents') }}">{!! get_dialogs('Contents', $active_language) !!}</a></li>
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('navigation') }}">{!! get_dialogs('Menu', $active_language) !!}</a></li>
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('footer') }}">{!! get_dialogs('Footer', $active_language) !!}</a></li>
                            </ul>
                            </li>
                            @endcan

                            {{-- the transcription work and the people and settings around it --}}
                            @canany(['manage-projects', 'manage-users', 'manage-fields', 'run-sync', 'resolve-conflicts', 'view-all-submissions', 'manage-settings', 'manage-access', 'manage-languages'])
                            <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                {!! get_dialogs('Admin', $active_language) !!}
                            </a>
                            <ul class="dropdown-menu" role="navbarDropdown">
                                @canany(['manage-projects', 'manage-fields', 'run-sync', 'resolve-conflicts', 'view-all-submissions', 'view-project-submissions'])
                                <li><h6 class="dropdown-header">{!! get_dialogs('Transcription', $active_language) !!}</h6></li>
                                @can('manage-projects')
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('projects') }}">{!! get_dialogs('Projects', $active_language) !!}</a>
                                </li>
                                @endcan
                                @can('manage-fields')
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('fields') }}">{!! get_dialogs('Fields', $active_language) !!}</a>
                                </li>
                                @endcan
                                @can('run-sync')
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('validate/manual') }}">{!! get_dialogs('Validation', $active_language) !!}</a>
                                </li>
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('validated') }}">{!! get_dialogs('Validated values', $active_language) !!}</a>
                                </li>
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('sync-runs') }}">{!! get_dialogs('Sync history', $active_language) !!}</a>
                                </li>
                                @endcan
                                @canany(['run-sync', 'resolve-conflicts'])
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('validated?status=4') }}">{!! get_dialogs('Conflicts with Specify', $active_language) !!}</a>
                                </li>
                                @endcanany
                                @can('view-project-submissions')
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('all_submissions') }}">{!! get_dialogs('User submissions', $active_language) !!}</a>
                                </li>
                                @endcan
                                @can('view-all-submissions')
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('all_stats') }}">{!! get_dialogs('User Stats', $active_language) !!}</a>
                                </li>
                                @endcan
                                @endcanany

                                @can('manage-users')
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">{!! get_dialogs('People', $active_language) !!}</h6></li>
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('users') }}">{!! get_dialogs('Users', $active_language) !!}</a>
                                </li>
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('user_categories') }}">{!! get_dialogs('User categories', $active_language) !!}</a>
                                </li>
                                @endcan

                                @canany(['manage-languages', 'manage-settings', 'manage-access'])
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">{!! get_dialogs('Platform', $active_language) !!}</h6></li>
                                @can('manage-languages')
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('languages') }}">{!! get_dialogs('Languages', $active_language) !!}</a>
                                </li>
                                @endcan
                                @canany(['manage-settings', 'manage-access'])
                                <li class="nav-item"><a class="dropdown-item" href="{{ url('settings') }}">{!! get_dialogs('Settings', $active_language) !!}</a>
                                </li>
                                @endcanany
                                @endcanany
                            </ul>
                            </li>
                            @endcanany
                            @endcanany

                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    {!! display_avatar(Auth::user(), 24) !!} {{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu" role="navbarDropdown">

                                    <li class="nav-item"><a class="dropdown-item" href="{{ url('profile') }}">{!! get_dialogs('My profile', $active_language) !!}</a>
                                    </li>
                                    <li class="nav-item"><a class="dropdown-item" href="{{ url('dashboard') }}">{!! get_dialogs('My submissions', $active_language) !!}</a>
                                    </li>
                                    <li class="nav-item"><a class="dropdown-item" href="{{ url('my_stats') }}">{!! get_dialogs('My stats', $active_language) !!}</a>
                                    </li>
                                    <li class="nav-item"><a class="dropdown-item" href="{{ url('help') }}">{!! get_dialogs('Help', $active_language) !!}</a>
                                    </li>

                                    <li>
                                        <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                            <?php echo get_dialogs('Logout', $active_language); ?>
                                        </a>

                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                            @csrf
                                        </form>
                                    </li>
                                </ul>

                            </li>
                            <?php
                            //generate tooltip table
                            //note: attribute values (data-bs-title/data-bs-content) can't carry the
                            //edit-trigger markup get_dialogs() appends, so these use get_dialogs_simple()
                            $user_category = Auth::user()->user_categories->id;
                            $user_message = get_dialogs_simple('user_categories_description_' . Auth::user()->user_categories->id, $active_language);
                            $table_users_category = '<table class="table"><tr><th>' . get_dialogs_simple('user level', $active_language) . '</th><th>' . get_dialogs_simple('valid contributions', $active_language) . '</th></tr>';
                            $user_categories = $user_categories->sortBy('validations_required');
                            foreach ($user_categories as $category) {
                                if (!is_null($category->validations_required)) {
                                    $table_users_category .= '<tr><td>' . get_dialogs_simple('user_categories_name_' . $category->id, $active_language) . '</td><td class="text-end">' . $category->validations_required . '<td></tr>';
                                }
                            }
                            $table_users_category .= '</table>';

                            ?>

                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-title="{{$user_message}}" data-bs-content="{{$table_users_category}}" data-bs-placement="bottom">{!! get_dialogs('user_categories_name_'.Auth::user()->user_categories->id, $active_language) !!}</button>


                            @endguest
                            <li class="nav-item dropdown language-selector">
                                <a href="" class="nav-link dropdown-toggle" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <img width="20px" src="{{ url('/') }}/flags/4x3/<?php echo $active_language; ?>.svg" />
                                </a>
                                <ul class="dropdown-menu">
                                    <?php
                                    foreach ($languages as $language) {
                                    ?>
                                        <li class="nav-item" <?php if ($language->iso2 == $active_language) {
                                                                    echo 'class="active"';
                                                                } ?>>
                                            <a class="dropdown-item" href="" onclick="document.cookie = 'active_language=<?php echo $language->iso2; ?>; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/'; window.location.reload();">
                                                <img width="20px" src="{{ url('/') }}/flags/4x3/<?php echo $language->iso2; ?>.svg" />
                                                <span><?php echo $language->text; ?></span>
                                            </a>
                                        </li>
                                    <?php
                                    }
                                    ?>
                                </ul>
                            </li>

                    </ul>
                </div>
            </div>
        </nav>

        <main class="@yield('main_class', 'page-content')">
            @yield('content')
        </main>

        {{-- not on the full-height transcription workspace, which opts out of the page padding --}}
        @if(trim($__env->yieldContent('main_class', 'page-content')) !== '')
            @include('layouts.partials.footer')
        @endif
    </div>


    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        @include('inc/messages')
    </div>

    @include('projects.delete_modal')
    @include('dialogs.edit_modal')
<script>
    document.querySelectorAll('.navbar .dropdown-submenu > a').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var menu = a.nextElementSibling;
            if (menu && a.getAttribute('href') === '#') { e.preventDefault(); }
            e.stopPropagation();
            if (menu) { menu.classList.toggle('show'); }
        });
    });
</script>
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