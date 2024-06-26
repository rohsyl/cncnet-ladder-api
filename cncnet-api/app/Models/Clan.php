<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Clan extends Model
{
    use HasFactory;

    protected $fillable = ['ladder_id', 'short', 'name', 'description', 'avatar_path'];

    public function owners()
    {
        $ownerId = ClanRole::where('value', '=', 'Owner')->first()->id;
        return $this->clanPlayers()->where('clan_role_id', '=', $ownerId)->orderBy('updated_at', 'ASC');
    }

    public function managers()
    {
        $managerId = ClanRole::where('value', '=', 'manager')->first()->id;
        return $this->clanPlayers()->where('clan_role_id', '=', $managerId)->orderBy('updated_at', 'ASC');
    }

    public function members()
    {
        $memberId = ClanRole::where('value', '=', 'member')->first()->id;
        return $this->clanPlayers()->where('clan_role_id', '=', $memberId)->orderBy('updated_at', 'ASC');
    }

    public function nextOwner($current = null)
    {
        if ($current !== null)
        {
            $excepted = $this->owners->filter(function ($p) use ($current)
            {
                return $p->id != $current->id;
            });
            if ($excepted->count() > 0)
            {
                return $excepted->first();
            }
        }

        $manager = $this->managers->first();
        return $manager !== null ? $manager : ($this->members !== null ? $this->members->first() : null);
    }

    public function wins($history = null)
    {
        $result = 0;

        if ($history == null)
        {
            $result = $this->clanGames()->where('won', true)->get();
        }
        else
        {
            $result = $this->clanGames()->where('won', true)->where('ladder_history_id', $history->id)->get();
        }

        return count($result);
    }

    // Calculate the clan's total points, up until this game_id
    public function pointsBefore($history, $gameId, $clanId)
    {
        $points = PlayerGameReport::where('player_game_reports.clan_id', $clanId)
            ->join('games as g', 'g.game_report_id', '=', 'player_game_reports.game_report_id')
            ->where("g.ladder_history_id", "=", $history->id)
            ->where('g.id', '<', $gameId)
            ->sum('player_game_reports.points');
        return $points !== null ? $points : 0;
    }

    public function clanGames()
    {
        return $this->playerGameReports()
            ->join('game_reports', 'game_reports.id', '=', 'player_game_reports.game_report_id')
            ->join('games', 'games.id', '=', 'game_reports.game_id')
            ->join('stats2', 'player_game_reports.stats_id', '=', 'stats2.id')
            ->where('player_game_reports.clan_id', $this->id)
            ->where('game_reports.valid', true)
            ->where('game_reports.best_report', true)
            ->groupBy("game_reports.game_id")
            ->select(
                'player_game_reports.id as id',
                'player_game_reports.clan_id as clan_id',
                'game_reports.id as game_report_id',
                'games.ladder_history_id as ladder_history_id',
                'game_reports.game_id as game_id',
                'duration',
                'fps',
                'oos',
                'sid',
                'cty',
                'local_id',
                'local_team_id',
                'points',
                'stats_id',
                'disconnected',
                'no_completion',
                'quit',
                'won',
                'defeated',
                'draw',
                'spectator',
                'game_reports.created_at',
                'wol_game_id',
                'bamr',
                'crat',
                'cred',
                'shrt',
                'supr',
                'unit',
                'plrs',
                'scen',
                'hash'
            );
    }

    public function totalGames24Hours($history)
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();

        $totalGames = $this->clanGames()->where("ladder_history_id", $history->id)
            ->whereBetween("game_reports.created_at", [$start, $end])
            ->get();

        return count($totalGames);
    }

    public function totalGames($history = null)
    {
        $result = 0;

        if ($history == null)
        {
            $result = $this->clanGames->get();
        }
        else
        {
            $result = $this->clanGames()->where('ladder_history_id', $history->id)->get();
        }

        return count($result);
    }

    public function sideUsage($history)
    {
        $pq = $this->clanGames()->where('ladder_history_id', '=', $history->id);
        $total = $pq->count();
        return $pq->select('sid', DB::raw('count(*) / ' . $total . ' * 100 as count'))->groupBy('sid')->orderBy('count', 'desc')->get();
    }

    public function countryUsage($history)
    {
        $pq = $this->clanGames()->where('ladder_history_id', '=', $history->id);
        $total = $pq->count();
        return $pq->select('cty', DB::raw('count(*) / ' . $total . ' * 100 as count'))->groupBy('cty')->orderBy('count', 'desc')->get();
    }

    public function averageFPS($history)
    {
        $count = $this->clanGames()->where('ladder_history_id', '=', $history->id)->where('fps', '>', 25)->count();
        $total = $this->clanGames()->where('ladder_history_id', '=', $history->id)->where('fps', '>', 25)->sum('fps');
        if ($count != 0)
            return $total / $count;
        return 0;
    }

    public function points($history)
    {
        $points = PlayerGameReport::where('player_game_reports.clan_id', $this->id)
            ->join('games as g', 'g.game_report_id', '=', 'player_game_reports.game_report_id')
            ->where("g.ladder_history_id", "=", $history->id)
            ->sum('player_game_reports.points');
        return $points !== null ? $points : 0;
    }

    public function getClanAvatar()
    {
        if ($this->avatar_path)
        {
            $env = config("app.env");
            if ($env !== "production" && $env !== "staging")
            {
                return "https://ladder.cncnet.org/" . $this->avatar_path;
            }
            return asset($this->avatar_path, true);
        }
        return null;
    }

    /**
     * Returns live user rating or creates a new one if one doesn't exist yet
     * @return mixed 
     */
    public function getOrCreateLiveClanRating()
    {
        $clanRating = ClanRating::where("clan_id", "=", $this->id)->first();
        if ($clanRating == null)
        {
            $clanRating = ClanRating::createNew($this, ClanRating::$DEFAULT_RATING);
        }
        return $clanRating;
    }


    # Relationships
    public function playerGameReports()
    {
        return $this->hasMany(PlayerGameReport::class);
    }

    public function ladder()
    {
        return $this->belongsTo(Ladder::class);
    }

    public function clanPlayers()
    {
        return $this->hasMany(ClanPlayer::class)->orderBy('clan_role_id', 'ASC');
    }

    public function invitations()
    {
        return $this->hasMany(ClanInvitation::class);
    }

    public function clanRating()
    {
        return $this->belongsTo(ClanRating::class);
    }
}
