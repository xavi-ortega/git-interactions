<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}" rel="stylesheet">
</head>

<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">

            <router-link to="/">
                {{ config('app.name', 'Laravel') }}
            </router-link>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <form class="form-inline mx-auto">
                    <div class="input-group input-group-lg">
                        <div class="input-group-prepend">
                            <span class="input-group-text">
                                <i class="fa fa-search"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control" placeholder="Search...">
                    </div>
                </form>

                <!-- Right Side Of Navbar -->
                <ul class="navbar-nav ml-auto">
                    <template v-if="isLogged">
                        <button type="button" @click="logout()" v-if="isLogged">
                            Logout
                        </button>
                    </template>

                    <template v-else>
                        <li>
                            <router-link to="/login">Login</router-link>
                        </li>
                        <li>
                            <router-link to="/register">Register</router-link>
                        </li>
                    </template>
                </ul>
            </div>

        </nav>

        <main class="py-4">
            @yield('content')
        </main>
    </div>
</body>

</html>
