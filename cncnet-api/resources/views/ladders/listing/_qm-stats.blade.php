<div class="row">
    <div class="header">
        <div class="col-md-12">
            <h5><strong>Ranked Match</strong> Stats</h5>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="qm-stats">
            <div class="stat purple">
                <div class="text-center">
                    <span class="material-symbols-outlined">
                        insights
                    </span>
                    <h5>Games this Month</h5>
                </div>
                <div class="text-center">
                    <div class="value">{{ $stats['matchesByMonth'] }} </div>
                    <div><small>(This month so far)</small></div>
                </div>
            </div>

            <div class="stat pink">
                <div class="text-center">
                    <span class="material-symbols-outlined">
                        insights
                    </span>
                    <h5>Games Played</h5>
                </div>
                <div class="text-center">
                    <div class="value">{{ $stats['past24hMatches'] }} </div>
                    <div><small>(Last 24 hours)</small></div>
                </div>
            </div>


            <div class="stat {{ $history->ladder->clans_allowed ? 'blue' : 'magenta' }}">
                <div class="text-center">
                    @if ($history->ladder->clans_allowed)
                        <i class="bi bi-flag-fill icon-clan"></i>
                        <h5>Queued Clans</h5>
                    @else
                        <span class="material-symbols-outlined">
                            group
                        </span>
                        <h5>Queued Players</h5>
                    @endif
                </div>
                <div class="text-center">
                    <div class="value">{{ $stats['queuedPlayers'] }}</div>
                    <div><small>(Right now)</small></div>
                </div>
            </div>

            @if ($statsXOfTheDay && $statsXOfTheDay->wins > 0)

                @if ($history->ladder->clans_allowed)
                    <?php $url = \App\Models\URLHelper::getClanProfileUrl($history, $statsXOfTheDay->name); ?>
                @else
                    <?php $url = \App\Models\URLHelper::getPlayerProfileUrl($history, $statsXOfTheDay->name); ?>
                @endif

                <a class="stat gold potd" style="position:relative" href="{{ $url }}" title="{{ $statsXOfTheDay->name }}">
                    <div class="text-center">

                        @if (\Carbon\Carbon::now()->month == 10)
                            @include('animations.player', [
                                'src' => '/animations/pumpkin.json',
                                'loop' => 'true',
                                'width' => '100%',
                                'height' => '80px',
                            ])
                        @elseif(\Carbon\Carbon::now()->month == 11)
                            @include('animations.player', [
                                'src' => '/animations/turkey.json',
                                'loop' => 'true',
                                'width' => '100%',
                                'height' => '150px',
                            ])
                        @elseif(\Carbon\Carbon::now()->month == 12)
                            @include('animations.player', [
                                'src' => '/animations/santa.json',
                                'loop' => 'true',
                                'width' => '100%',
                                'height' => '150px',
                            ])
                        @else
                            <div class="icon icon-crown pt-4">
                                @include('icons.crown', [
                                    'colour' => '#ffcd00',
                                ])
                            </div>
                        @endif

                        <h4>
                            @if ($history->ladder->clans_allowed)
                                Clan of the day
                            @else
                                Player of the day
                            @endif
                        </h4>
                    </div>
                    <div class="text-center" style="z-index:1;position:relative;">
                        <div class="value">{{ $statsXOfTheDay->name }}</div>
                        <div><small>{{ $statsXOfTheDay->wins }} wins <br />(Today)</small></div>
                    </div>
                    <div style="position: absolute; top: 0; left: 0;width:100%; z-index: 0;">
                        @include('animations.player', [
                            'src' => '/animations/confetti.json',
                            'loop' => 'false',
                            'width' => '100%',
                            'height' => '200px',
                        ])
                    </div>
                </a>
            @endif
        </div>
    </div>
</div>
