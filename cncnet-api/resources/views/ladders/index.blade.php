@extends('layouts.app')
@section('title', 'Ladder')
@section('feature-video', \App\URLHelper::getVideoUrlbyAbbrev('ra2'))
@section('feature-video-poster', \App\URLHelper::getVideoPosterUrlByAbbrev('ra2'))

@section('feature')
    <div class="feature pt-5 pb-5">
        <div class="container px-4 py-5 text-light">
            <div class="row flex-lg-row-reverse align-items-center g-5 py-5">
                <div class="col-12">
                    <h1 class="display-4 lh-1 mb-3 text-uppercase">
                        <strong class="fw-bold">CnCNet</strong>
                        <span>Ladder Rankings</span>
                    </h1>

                    <p class="lead">
                        Compete in <strong>1vs1</strong> ranked matches with players all over the world.
                    </p>

                    @if (!\Auth::user())
                        <div class="mt-4">
                            <a class="btn btn--outline-primary me-3 btn-size-lg" href="/auth/register">Register</a>
                            <a class="btn btn--outline-secondary btn-size-lg" href="/auth/login">Login</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('breadcrumb')
    <nav aria-label="breadcrumb" class="breadcrumb-nav">
        <div class="container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">
                    <a href="/">
                        <span class="material-symbols-outlined">
                            home
                        </span>
                    </a>
                </li>
            </ol>
        </div>
    </nav>
@endsection

@section('content')
    <div class="ladder-index">
        <section class="pt-3 pb-3">
            <div class="container">
                <div class="countdown announcement m-auto" style="width: 450px; max-width: 100%;">
                    <a href="/news/clan-ladder" title="Clan Ladder Announcement">
                        2vs2 - Clan Ladder Announcement
                    </a>
                </div>
            </div>
        </section>

        <section class="pt-5 pb-5">
            <div class="container">
                <h3>
                    <span class="material-symbols-outlined icon">
                        military_tech
                    </span>
                    <strong>1vs1</strong> Ladders
                </h3>

                <div class="d-flex flex-wrap mt-4">
                    @foreach ($ladders as $history)
                        @include('components.ladder-box', [
                            'history' => $history,
                            'url' => \App\URLHelper::getLadderUrl($history),
                        ])
                    @endforeach
                </div>
            </div>
        </section>

        <section class="pt-5 pb-5">
            <div class="container">
                <h3>
                    <span class="material-symbols-outlined icon">
                        military_tech
                    </span>
                    <strong>Clan</strong> Ladders
                </h3>

                <div class="d-flex flex-wrap mt-4">
                    @foreach ($clan_ladders as $history)
                        @if (!$history->ladder->private)
                            @include('components.ladder-box', [
                                'history' => $history,
                                'url' => \App\URLHelper::getLadderUrl($history),
                            ])
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
