<nav class="navbar navbar-main navbar-expand-xxl fixed-top js-navbar">
    <div class="container-fluid">

        <a class="navbar-brand" href="{{ url('/') }}" title="CnCNet Home">
            <img src="{{ Vite::asset('resources/images/logo.svg') }}" alt="CnCNet logo" loading="lazy" class="logo-full" />
        </a>

        <button class="navbar-toggler hamburger hamburger--collapse" type="button" data-bs-toggle="offcanvas" data-bs-target="#fullscreenNav"
            aria-controls="fullscreenNav" aria-expanded="false" aria-label="Toggle navigation">
            <div class="hamburger-box">
                <div class="hamburger-inner"></div>
            </div>
        </button>

        <div class="navbar-collapse collapse" id="navbarSupportedContent">
            @include('components.navigation.primary-links')
            @include('components.navigation.secondary-links')
        </div>
    </div>
</nav>
