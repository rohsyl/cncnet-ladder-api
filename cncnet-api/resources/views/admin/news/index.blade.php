@extends('layouts.app')
@section('title', 'Ladder')
@section('feature-video', \App\Models\URLHelper::getVideoUrlbyAbbrev('ra2'))
@section('feature-video-poster', \App\Models\URLHelper::getVideoPosterUrlByAbbrev('ra2'))

@section('feature')
    <div class="feature pt-5 pb-5">
        <div class="container px-4 py-5 text-light">
            <div class="row flex-lg-row-reverse align-items-center g-5 py-5">
                <div class="col-12">
                    <h1 class="display-4 lh-1 mb-3">
                        <strong class="fw-bold">CnCNet</strong>
                        <span>Ladder News</span>
                    </h1>
                </div>
            </div>
            <div class="mini-breadcrumb d-none d-lg-flex">
                <div class="mini-breadcrumb-item">
                    <a href="/" class="">
                        <span class="material-symbols-outlined">
                            home
                        </span>
                    </a>
                </div>
                <div class="mini-breadcrumb-item">
                    <a href="/admin" class="">
                        <span class="material-symbols-outlined">
                            admin_panel_settings
                        </span>
                    </a>
                </div>

                <div class="mini-breadcrumb-item">
                    <a href="/admin/news" class="">
                        <span class="material-symbols-outlined">
                            admin_panel_settings
                        </span>
                        Manage News
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('breadcrumb')
    <nav aria-label="breadcrumb" class="breadcrumb-nav">
        <div class="container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="/">
                        <span class="material-symbols-outlined">
                            home
                        </span>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="/admin/news/create">
                        <span class="material-symbols-outlined pe-3">
                            admin_panel_settings
                        </span>
                        Manage News
                    </a>
                </li>
            </ol>
        </div>
    </nav>
@endsection

@section('content')
    <div class="container">
        <div class="mt-5 mb-5">
            <h2>News</h2>
            <p>
                Manage all news posts
            </p>
            <a href="/admin/news/create" class="btn btn-outline btn-size-md">Create new post</a>
        </div>
    </div>

    <div class="admin-news">
        <div class="container">
            <div class="news-container">
                @foreach ($news as $newsItem)
                    <div class="news-item">
                        <h3>{{ $newsItem->title }}</h3>

                        @if ($newsItem->getFeaturedImagePath())
                            <div class="image mt-2 mb-2" style="width:300px; overflow:hidden">
                                <img src="{{ $newsItem->getFeaturedImagePath() }}" alt="Featured news image for {{ $newsItem->title }}"
                                    style="max-width:100%" />
                            </div>
                        @endif

                        <p class="lead mt-2 mb-2">{{ $newsItem->description }}</p>
                        <div class="d-flex">
                            <div class="font-secondary-bold ">
                                <div class="d-flex">
                                    <div class="me-4">
                                        @include('components.avatar', ['avatar' => $newsItem->getAuthor->getUserAvatar(), 'size' => 80])
                                    </div>
                                    <div>
                                        <div>By {{ $newsItem->getAuthor->name }}</div>
                                        <div>Published {{ $newsItem->created_at }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="ms-auto">
                                <a href="/admin/news/edit/{{ $newsItem->id }}" class="btn btn-secondary">Edit</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
