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
    <link href="https://cdn.jsdelivr.net/npm/animate.css@3.5.1" rel="stylesheet" type="text/css">
</head>

<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm" v-if="isLogged">
            <router-link to="/">
                <img class="logo" src="{{ asset('images/logo.png') }}" alt="GitInteractions">
            </router-link>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <form class="form-inline mx-auto">
                    <div class="input-group input-group-lg" @click="search">
                        <div class="input-group-prepend">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                        <input type="text" class="form-control" placeholder="Search..." />
                    </div>
                </form>

                <!-- Right Side Of Navbar -->
                <ul class="navbar-nav ml-auto">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary" @click="logout">
                            Logout
                        </button>

                        <notifications></notifications>
                    </div>
                </ul>

            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>

        <footer>
            <div class="container">
                <div class="row">
                    <div class="col-md-6 col-sm-12 text-left">
                        <p>
                            &copy; GitInteractions by
                            <a href="https://github.com/xavi-ortega" title="Find me on Github" target="_blank">Xavi Ortega</a>
                        </p>
                    </div>

                    <div class="col-md-6 col-sm-12 text-right">
                        <p>Terms & Conditions | Privacy Policy | Supports | Cookies Policy</p>
                    </div>
                </div>
            </div>

        </footer>
        <search></search>
    </div>
</body>

</html>
