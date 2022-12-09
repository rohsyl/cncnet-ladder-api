@extends('layouts.app')
@section('title', 'Manage Clan')

@section('feature-image', '/images/feature/feature-index.jpg')

@section('feature')
    <div class="feature pt-5 pb-5">
        <div class="container px-4 py-5 text-light">
            <div class="row flex-lg-row-reverse align-items-center g-5 py-5">
                <div class="col-12">
                    <h1 class="display-4 lh-1 mb-3 text-uppercase">
                        <strong class="fw-bold">{{ $clan->ladder->name }} - {{ $clan->short }}</strong>
                    </h1>

                    <p class="lead">
                        {{ $clan->name }}
                    </p>
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
                    <a href="">
                        <span class="material-symbols-outlined icon pe-3">
                            person
                        </span>
                        {{ $user->name }}
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="">
                        <i class="bi bi-flag-fill pe-2"></i>
                        Editing Clan - {{ $clan->name }}
                    </a>
                </li>
            </ol>
        </div>
    </nav>
@endsection

@section('content')
    <section>
        <div class="container">
            <div class="row mb-2">
                <div class="col-md-12">
                    @if ($player->clanPlayer->isOwner())
                        <button class="btn btn-secondary btn-md inline-after-edit" data-bs-toggle="modal" data-bs-target="#renameClan">
                            Edit clan name <i class="fa fa-edit"></i>
                        </button>
                    @endif
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    @include('components.form-messages')
                </div>
            </div>

            <div class="feature">
                <div class="row">
                    <div class="col-md-6 clan-members-box">
                        <div class="clan-members-header">
                            <h3>Invitations</h3>
                            @if ($player->clanPlayer->isOwner() || $player->clanPlayer->isManager())
                                <button class="btn btn-md btn-primary" data-bs-toggle="modal" data-bs-target="#sendInvitation">New Invite</button>
                            @endif
                        </div>
                        <div class="clan-members-body">
                            <table class="table clan-members-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Name</th>
                                        <th>Manager</th>
                                        <th>Sent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invitations as $invite)
                                        <tr>
                                            <td>
                                                @if ($player->clanPlayer->isOwner() || $player->clanPlayer->isManager())
                                                    <form action="/clans/{{ $ladder->abbreviation }}/invite/{{ $invite->clan_id }}/cancel" method="POST">
                                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                        <input type="hidden" name="id" value="{{ $invite->id }}">
                                                        <input type="hidden" name="player_id" value="{{ $player->id }}">
                                                        <button class="btn btn-secondary btn-sm" type="submit" name="submit" data-toggle="tooltip" data-placement="top" title="Cancel Invitation">
                                                            <span class="fa fa-times fa-lg clan-danger"></span> Cancel invite
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                            <td>{{ $invite->player->username }}</td>
                                            <td>{{ $invite->author->username }}</td>
                                            <td>{{ $invite->created_at->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="clan-members-footer">
                            @if ($invitations->count() > 10)
                                @if ($player->clanPlayer->isOwner() || $player->clanPlayer->isManager())
                                    <button class="btn btn-md btn-primary" data-toggle="modal" data-target="#sendInvitation">New Invite</button>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6 clan-members-box">
                        <form method="POST" action="members">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="player_id" value="{{ $player->id }}">
                            <div class="clan-members-header">
                                <h3>Members</h3>
                                @if ($player->clanPlayer->isOwner())
                                    <div class="text-center"><button class="btn btn-primary" type="submit" name="submit" value="apply">Apply</button></div>
                                @endif
                            </div>
                            <div class="clan-members-body">
                                <table class="table clan-members-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($clan->clanPlayers as $cp)
                                            <tr>
                                                <td>{{ $cp->player->username }}</span></td>
                                                <td>
                                                    @if ($player->clanPlayer->isOwner())
                                                        <select class="form-control" name="role[{{ $cp->id }}]">
                                                            @foreach ($roles as $role)
                                                                <option value="{{ $role->id }}" @if ($cp->clan_role_id == $role->id) selected @endif>{{ $role->value }}</option>
                                                            @endforeach
                                                            <option value="kick">Kick</option>
                                                        </select>
                                                    @else
                                                        {{ $cp->role }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="clan-members-footer">
                                @if ($player->clanPlayer->isOwner())
                                    @if ($clan->clanPlayers->count() > 10)
                                        <div class="text-center"> <button class="btn btn-primary" type="submit" name="submit" value="apply">Apply</button></div>
                                    @endif
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" tabindex="-1" id="renameClan" tabIndex="-1" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Clan name</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form method="POST" action="/clans/{{ $ladder->abbreviation }}/edit/{{ $clan->id }}">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="ladder_id" value="{{ $ladder->id }}">
                        <input type="hidden" name="player_id" value="{{ $player->id }}">

                        <p>Usernames will be the name shown when you login to CnCNet clients and play games.</p>

                        <div class="form-group mb-2">
                            <label for="short">Short Name</label>
                            <input type="text" name="short" class="form-control border" id="short" value="{{ $clan->short }}">
                        </div>

                        <div class="form-group mb-2">
                            <label for="name">Full Clan Name</label>
                            <input type="text" name="name" class="form-control border" id="name" placeholder="New Clan Name" value="{{ $clan->name }}">
                        </div>

                        <button type="submit" class="btn btn-primary mt-2">Update Clan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="renameClan" tabIndex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h3 class="modal-title">Rename Your Clan!</h3>
                </div>
                <div class="modal-body clearfix">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-12 player-box player-card" style="padding:8px;margin:8px;">
                                <div class="account-box">
                                    <form method="POST" action="/clans/{{ $ladder->abbreviation }}/edit/{{ $clan->id }}">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="hidden" name="ladder_id" value="{{ $ladder->id }}">
                                        <input type="hidden" name="player_id" value="{{ $player->id }}">

                                        <p>
                                            Usernames will be the name shown when you login to CnCNet clients and play games.
                                        </p>

                                        <div class="form-group">
                                            <label for="short">Short Name</label>
                                            <input type="text" name="short" class="form-control" id="short" value="{{ $clan->short }}">
                                        </div>

                                        <div class="form-group">
                                            <label for="name">Full Clan Name</label>
                                            <input type="text" name="name" class="form-control" id="name" placeholder="New Clan Name" value="{{ $clan->name }}">
                                        </div>

                                        <button type="submit" class="btn btn-primary">Update Clan</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" tabindex="-1" id="sendInvitation" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Invite A Player</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="/clans/{{ $ladder->abbreviation }}/invite/{{ $clan->id }}">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="ladder_id" value="{{ $ladder->id }}">

                        <div class="form-group mb-2">
                            <label for="short">Player Name</label>
                            <input type="text" name="playerName" class="form-control border" id="playerName">
                        </div>

                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
