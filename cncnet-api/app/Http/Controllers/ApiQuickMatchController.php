<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Http\Services\LadderService;
use \App\Http\Services\GameService;
use \App\Http\Services\PlayerService;
use \App\Http\Services\EloService;
use \App\Http\Services\AuthService;
use \App\PlayerActiveHandle;
use \Carbon\Carbon;
use DB;
use \App\Commands\FindOpponent;
use App\Game;
use App\Helpers\AIHelper;
use App\Helpers\GameHelper;
use App\PlayerRating;
use \App\QmQueueEntry;
use Illuminate\Support\Facades\Cache;
use \App\SpawnOptionType;
use DateTime;
use \App\Helpers\LeagueHelper;
use App\Http\Services\QuickMatchService;
use App\Http\Services\QuickMatchSpawnService;
use App\Http\Services\StatsService;
use App\LeaguePlayer;
use App\MapPool;
use App\QmMatch;
use App\QmMatchPlayer;
use BadMethodCallException;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use MaxMind\Db\Reader\InvalidDatabaseException;

class ApiQuickMatchController extends Controller
{
    private $ladderService;
    private $playerService;
    private $quickMatchService;
    private $quickMatchSpawnService;
    private $statsService;

    public function __construct()
    {
        $this->ladderService = new LadderService();
        $this->playerService = new PlayerService();
        $this->quickMatchService = new QuickMatchService();
        $this->quickMatchSpawnService = new QuickMatchSpawnService();
        $this->statsService = new StatsService();
    }

    public function clientVersion(Request $request, $platform = null)
    {
        return json_encode(DB::table("client_version")->where("platform", $platform)->first());
    }

    public function statsRequest(Request $request, $ladderAbbrev = null, $tierId = 1)
    {
        $qmStats = $this->statsService->getQmStats($ladderAbbrev, $tierId);

        return [
            'recentMatchedPlayers' => $qmStats['recentMatchedPlayers'],
            'queuedPlayers' => $qmStats['queuedPlayers'],
            'past24hMatches' => $qmStats['past24hMatches'],
            'recentMatches' => $qmStats['recentMatches'],
            'activeMatches'   => $qmStats['activeMatches'],
            'clans' => $qmStats['clans'],
            'time' => $qmStats['time']
        ];
    }

    /**
     * Fetch details about games thare are currently in match
     */
    public function getActiveMatches(Request $request, $ladderAbbrev = null)
    {
        $games = [];
        if ($ladderAbbrev == "all")
        {
            foreach ($this->ladderService->getLadders() as $ladder)
            {
                $results = $this->getActiveMatchesByLadder($ladder->abbreviation);

                foreach ($results as $key => $val)
                {
                    $games[$ladder->abbreviation][$key] = $val;
                }
            }
        }
        else
        {
            $results = $this->getActiveMatchesByLadder($ladderAbbrev);

            foreach ($results as $key => $val)
            {
                $games[$ladderAbbrev][$key] = $val;
            }
        }

        return $games;
    }

    private function getActiveMatchesByLadder($ladderAbbrev)
    {
        $ladder = $this->ladderService->getLadderByGame($ladderAbbrev);

        if ($ladder == null)
            abort(400, "Invalid ladder provided");

        //get all recent QMs that whose games have spawned. (state_type_id == 5)
        $qms = $this->ladderService->getRecentSpawnedMatches($ladder->id, 30);

        $games = [];

        foreach ($qms as $qm) //iterate over every active quick match
        {
            $map = trim($qm->map);
            $dt = new DateTime($qm->qm_match_created_at);

            //get the player data pertaining to this quick match
            $players = $this->ladderService->getQmMatchPlayersInMatch($qm->id);

            $playersString = "";
            if ($ladder->clans_allowed)
            {
                $playersString = $this->getActiveClanMatchesData($players);
            }
            else
            {
                $playersString = $this->getActivePlayerMatchesData($players, $qm->qm_match_created_at);
            }

            $duration = Carbon::now()->diff($dt);
            $duration_formatted = $duration->format('%i mins %s sec');
            $games[] = $playersString . " on " . $map . ". (" . $duration_formatted . ")";
        }

        return $games;
    }

    private function getActiveClanMatchesData($players)
    {
        $clans = [];

        //put each player data in appropriate clan array
        foreach ($players as $player)
        {
            if (count($clans) == 0)
            {
                $clans[$player->clan_id][] = $player;
                continue;
            }

            if (in_array($player->clan_id, $clans))
            {
                $clans[$player->clan_id][] = $player;
                continue;
            }
            else
            {
                $clans[$player->clan_id][] = $player;
                continue;
            }
        }

        $playersString = "";

        $j = 0;
        foreach ($clans as $clanId => $players)
        {
            $i = 0;
            $clanName = \App\Clan::where('id', $clanId)->first()->short;
            foreach ($players as $player)
            {
                $playersString .= "[$clanName]" . $player->name . " (" . $player->faction . ")";

                if ($i < count($players) - 1)
                    $playersString .= " and ";

                $i++;
            }

            if ($j < count($clans) - 1)
                $playersString .= " vs ";

            $j++;
        }

        return $playersString;
    }

    private function getActivePlayerMatchesData($players, $created_at)
    {
        $playersString = "";
        $dt = new DateTime($created_at);
        for ($i = 0; $i < count($players); $i++)
        {
            $player = $players[$i];
            $playerName = "Player" . ($i + 1);
            if (Carbon::now()->diffInSeconds($dt) > 120) //only show real player name if 2mins has passed
                $playerName = $player->name;

            $playersString .= $playerName . " (" . $player->faction . ")";

            if ($i < count($players) - 1)
                $playersString .= " vs ";
        }
        return $playersString;
    }

    public function mapListRequest(Request $request, $ladderAbbrev = null)
    {
        return \App\QmMap::findMapsByLadder($this->ladderService->getLadderByGame($ladderAbbrev)->id);
    }

    public function sidesListRequest(Request $request, $ladderAbbrev = null)
    {
        $ladder = $this->ladderService->getLadderByGame($ladderAbbrev);
        $rules = $ladder->qmLadderRules()->first();
        $allowed_sides = $rules->allowed_sides();
        $sides = $ladder->sides()->get();

        return $sides->filter(function ($side) use (&$allowed_sides)
        {
            return in_array($side->local_id, $allowed_sides);
        });
    }


    /**
     * Called by cron service only
     */
    public function prunePlayersInQueue()
    {
        $queuedPlayers = QmQueueEntry::all();
        $now = Carbon::now();

        foreach ($queuedPlayers as $queuedPlayer)
        {
            $secondsSinceQMClientTouch = $now->copy()->diffInSeconds($queuedPlayer->updated_at);

            # QM client calls API calls every 10-15 seconds when in queue, cron called every minute
            if ($secondsSinceQMClientTouch > 20)
            {
                try
                {
                    $player = $queuedPlayer->qmPlayer->player;
                    Log::info("Removing player from queue due to inactivity: $player");
                }
                catch (Exception $ex)
                {
                    Log::info("Removing player from queue due to inactivity: $queuedPlayer");
                }

                $queuedPlayer->delete();
            }
        }
    }

    private function checkQMClientRequiresUpdate($ladder, $version)
    {
        # YR Games check
        if ($ladder->game == "yr")
        {
            if ($version < 1.79)
            {
                return true;
            }

            return false;
        }

        # RA/TS Games check
        if ($ladder->game == "ra" || $ladder->game == "ts")
        {
            if ($version < 1.69)
            {
                return true;
            }

            return false;
        }

        return false;
    }

    public function matchRequest(Request $request, $ladderAbbrev = null, $playerName = null)
    {
        $ladder = $this->ladderService->getLadderByGame($ladderAbbrev);

        $requiresUpdate = false; //$this->checkQMClientRequiresUpdate($ladder, $request->version);

        if ($requiresUpdate === true)
        {
            # Deprecate older versions
            $error = "Quick Match Version is no longer supported, please restart the CnCNet client to get the latest updates";
            return $this->onMatchFatalError($error);
        }


        $playerHasAuth = $this->ladderService->checkPlayer($request);
        if ($playerHasAuth !== null)
        {
            return $playerHasAuth;
        }

        $player = $this->playerService->findPlayerByUsername($playerName, $ladder);
        if ($player == null)
        {
            $error = "$playerName is not registered in $ladderAbbrev";
            return $this->onMatchFailError($error);
        }

        $user = $request->user();
        if ($user->id !== $player->user->id)
        {
            return $this->onMatchFailError("Failed");
        }

        # Check player has an active nick to play with, set one if not
        $this->playerService->setActiveUsername($player, $ladder);

        # Check for player bans
        $playerBan = $this->playerService->checkPlayerForBans($player, $request->getClientIp());
        if ($playerBan)
        {
            return $this->onMatchFatalError($playerBan);
        }

        # Require a verified email address
        $playerHasVerifiedEmail = $this->playerService->checkPlayerHasVerifiedEmail($player);
        if (!$playerHasVerifiedEmail)
        {
            $errorMessage = "Quick Match now requires a verified email address to play.\n" .
                "A verification code has been sent to {$player->user->email}.\n";

            return $this->onMatchFatalError($errorMessage);
        }

        $this->playerService->createPlayerRatingIfNull($player);

        $qmPlayer = QmMatchPlayer::where("player_id", $player->id)
            ->where("waiting", true)
            ->first();

        switch ($request->type)
        {
            case "quit":
                return $this->onQuit($qmPlayer);

            case "update":
                return $this->onUpdate(
                    $request->status,
                    $player,
                    $request->seed,
                    $request->peers
                );

            case "match me up":
                return $this->onMatchMeUp(
                    $request,
                    $ladder,
                    $player,
                    $qmPlayer
                );

            default:
                return ["type" => "error", "description" => "unknown type: {$request->type}"];
        }
    }


    /**
     * This matchup system is restful, a player will have to check in to see if there is a matchup waitin.
     * If there is already a matchup then all these top level ifs will fall through and the game info will be sent.
     * Else we'll try to set up a match.
     * @param mixed $request 
     * @param mixed $ladder 
     * @param mixed $player 
     * @param mixed $qmPlayer 
     */
    private function onMatchMeUp($request, $ladder, $player, $qmPlayer)
    {
        $ladderRules = $ladder->qmLadderRules()->first();
        $history = $ladder->currentHistory();
        $user = $player->user;

        # If we're new to the queue, create required QmMatchPlayer model
        if ($qmPlayer == null)
        {
            $qmPlayer = $this->quickMatchService->createQMPlayer($request, $player, $history);
            $validSides = $this->quickMatchService->checkPlayerSidesAreValid($qmPlayer, $request->side, $ladderRules);

            if (!$validSides)
            {
                $error = "Side ({$request->side}) is not allowed";
                return $this->onMatchError($error);
            }
        }

        # Important check, sent from qm client
        if ($request->ai_dat)
        {
            $qmPlayer->ai_dat = $request->ai_dat;
            $qmPlayer->save();

            $error = "Error, please contact us on the CnCNet Discord";
            return $this->onMatchFatalError($error);
        }
        $qmPlayer->save();

        $alert = $this->quickMatchService->checkForAlerts($ladder, $player);
        $userPlayerTier = $player->getCachedPlayerTierByLadderHistory($history);

        # Check if player should match AI
        $playerWillMatchAI = $this->checkPlayerWillMatchAI(
            $request->version,
            $user,
            $ladderRules,
            $userPlayerTier,
            $qmPlayer
        );

        if ($playerWillMatchAI == true)
        {
            # Delete player from queue if they were in one.
            if ($qmPlayer->qEntry != null)
            {
                $qmPlayer->qEntry->delete();
            }

            $gameType = Game::GAME_TYPE_1VS1_AI;

            # Match against AI
            return $this->onHandle1vs1AIMatchupRequest(
                $qmPlayer,
                $userPlayerTier,
                $history,
                $gameType,
                $ladder,
                $ladderRules
            );
        }

        if ($qmPlayer->qEntry !== null)
        {
            $gameType = $qmPlayer->qEntry->game_type;
        }
        else
        {
            $gameType = Game::GAME_TYPE_1VS1;

            if ($history->ladder->clans_allowed)
            {
                $gameType = Game::GAME_TYPE_2VS2;
            }
        }

        return $this->onHandlePlayersMatchupRequest(
            $qmPlayer,
            $player,
            $history,
            $gameType,
            $alert,
            $ladder,
            $ladderRules
        );
    }

    private function checkPlayerWillMatchAI(
        $version,
        $user,
        $ladderRules,
        $userPlayerTier,
        $qmPlayer
    )
    {
        $qmQueueEntry = $qmPlayer->qEntry;

        if (
            $userPlayerTier == LeagueHelper::CONTENDERS_LEAGUE
            && $qmQueueEntry !== null
            && $version >= 1.75
            && $user->userSettings->getMatchAI() == true
        )
        {
            # We're in the queue for normal player matchups
            # If we reach a certain amount of time, switch to AI matchup

            $now = Carbon::now();
            $timeSinceQueuedSeconds = $now->diffInRealSeconds($qmQueueEntry->created_at);
            Log::info("ApiQuickMatchController ** Time Since Queued $timeSinceQueuedSeconds QM Player: $qmPlayer , QM Client Version: $version");

            # Reached max queue time without match as set by ladder rules
            return ($timeSinceQueuedSeconds > $ladderRules->getMatchAIAfterSeconds());
        }

        return false;
    }

    private function onHandle1vs1AIMatchupRequest($qmPlayer, $userPlayerTier, $history, $gameType, $ladder, $ladderRules)
    {
        # Delete player from queue if they were in one.
        if ($qmPlayer->qEntry != null)
        {
            $qmPlayer->qEntry->delete();
        }

        if ($ladder->abbreviation === GameHelper::$GAME_BLITZ)
        {
            # Exclude certain maps that do not work with AI well
            $maps = MapPool::where("id", 63)->first()->maps;
        }
        else
        {
            $maps = $history->ladder->mapPool->maps;
        }

        $qmMatch = $this->quickMatchService->createQmAIMatch(
            $qmPlayer,
            $userPlayerTier,
            $maps,
            $gameType
        );

        $spawnStruct = QuickMatchSpawnService::createSpawnStruct($qmMatch, $qmPlayer, $ladder, $ladderRules);
        $spawnStruct = QuickMatchSpawnService::addQuickMatchAISpawnIni($spawnStruct, $ladder, AIHelper::BRUTAL_AI);

        return $spawnStruct;
    }

    private function onHandlePlayersMatchupRequest($qmPlayer, $player, $history, $gameType, $alert, $ladder, $ladderRules)
    {
        # Queue up to match an opponent 
        if ($qmPlayer->qm_match_id === null)
        {
            $qmQueueEntry = $this->quickMatchService->createOrUpdateQueueEntry($player, $qmPlayer, $history, $gameType);

            # No match found yet
            # Push a job to find an opponent
            $this->dispatch(new FindOpponent($qmQueueEntry->id, $gameType));

            $qmPlayer->touch();

            return $this->onCheckback($alert);
        }

        # If we're past this point, a match has been found
        $qmMatch = QmMatch::find($qmPlayer->qm_match_id);

        # Creates the initial spawn.ini to send to client
        $spawnStruct = QuickMatchSpawnService::createSpawnStruct(
            $qmMatch,
            $qmPlayer,
            $ladder,
            $ladderRules
        );

        # Check we have all players ready before writing them to spawn.ini
        $otherQmMatchPlayers = $qmMatch->players()->where('id', '<>', $qmPlayer->id)->orderBy('color', 'ASC')->get();
        if (count($otherQmMatchPlayers) == 0)
        {
            $qmPlayer->waiting = false;
            $qmPlayer->save();

            Log::info("ApiQuickMatchController ** Player Check: QMPlayer: $qmPlayer  - QMMatch: $qmMatch");

            return $this->onCheckback($alert);
        }

        if ($gameType == Game::GAME_TYPE_2VS2_AI)
        {
            # Prepend quick-coop AI ini file
            $spawnStruct = QuickMatchSpawnService::addQuickMatchCoopAISpawnIni($spawnStruct, AIHelper::BRUTAL_AI);
        }

        # Write the spawn.ini "Others" sections
        $spawnStruct = QuickMatchSpawnService::appendOthersAndTeamAlliancesToSpawnIni(
            $spawnStruct,
            $qmPlayer,
            $otherQmMatchPlayers
        );

        # Write the observers
        $spawnStruct = QuickMatchSpawnService::appendObservers($spawnStruct, $qmPlayer, $otherQmMatchPlayers);

        $qmPlayer->waiting = false;
        $qmPlayer->save();

        return $spawnStruct;
    }

    private function onQuit($qmPlayer)
    {
        if ($qmPlayer != null)
        {
            if ($qmPlayer->qm_match_id !== null)
            {
                $qmPlayer->qmMatch->save();
            }
            if ($qmPlayer->qEntry !== null)
            {
                $qmPlayer->qEntry->delete();
            }

            $qmPlayer->delete();
        }
        return [
            "type" => "quit"
        ];
    }

    private function onUpdate($status, $player, $seed, $peers)
    {
        if ($seed)
        {
            $qmMatch = QmMatch::where('seed', '=', $seed)
                ->join('qm_match_players', 'qm_match_id', '=', 'qm_matches.id')
                ->where('qm_match_players.player_id', '=', $player->id)
                ->select('qm_matches.*')
                ->first();

            if ($qmMatch !== null)
            {
                switch ($status)
                {
                    case 'touch':
                        $qmMatch->touch();
                        break;

                    default:
                        $qmState = new \App\QmMatchState;
                        $qmState->player_id = $player->id;
                        $qmState->qm_match_id = $qmMatch->id;
                        $qmState->state_type_id = \App\StateType::findByName($status)->id;
                        $qmState->save();

                        if ($qmState->state_type_id === 7) //match not ready
                        {
                            $canceledMatch = new \App\QmCanceledMatch;
                            $canceledMatch->qm_match_id = $qmMatch->id;
                            $canceledMatch->player_id = $player->id;
                            $canceledMatch->ladder_id = $qmMatch->ladder_id;
                            $canceledMatch->save();
                        }

                        if ($peers !== null)
                        {
                            foreach ($peers as $peer)
                            {
                                $con = new \App\QmConnectionStats;
                                $con->qm_match_id = $qmMatch->id;
                                $con->player_id = $player->id;
                                $con->peer_id = $peer['id'];
                                $con->ip_address_id = \App\IpAddress::getID($peer['address']);
                                $con->port = $peer['port'];
                                $con->rtt = $peer['rtt'];
                                $con->save();
                            }
                        }
                        break;
                }

                $qmMatch->save();

                return ["message"  => "update qm match: " . $status];
            }
        }
        return ["type" => "update"];
    }

    private function onMatchFailError($error)
    {
        return [
            "type" => "fail",
            "description" => $error
        ];
    }

    private function onMatchError($error)
    {
        return [
            "type" => "error",
            "description" => $error
        ];
    }

    private function onMatchFatalError($error)
    {
        return [
            "type" => "fatal",
            "message" => $error
        ];
    }

    private function onCheckback($alert)
    {
        if ($alert)
        {
            return ["type" => "please wait", "checkback" => 10, "no_sooner_than" => 5, 'warning' => $alert];
        }
        else
        {
            return ["type" => "please wait", "checkback" => 10, "no_sooner_than" => 5];
        }
    }

    public function getPlayerRankings($count = 50)
    {
        $month = Carbon::now()->month;
        $year = Carbon::now()->format('Y');

        $rankings = [];

        foreach ($this->ladderService->getLadders() as $ladder)
        {
            $history = \App\LadderHistory::where('short', '=', $month . "-" . $year)
                ->where('ladder_id', $ladder->id)
                ->first();

            if ($history == null)
                continue;

            $pc = \App\PlayerCache::where('ladder_history_id', '=', $history->id)
                ->join('players as p', 'player_caches.player_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->orderBy('player_caches.points', 'DESC')
                ->select('u.discord_profile as discord_name', 'player_caches.*')
                ->limit($count)
                ->orderBy('points', 'DESC')
                ->get();

            $rankings[strtoupper($ladder->abbreviation)] = $pc;
        }

        return $rankings;
    }

    public function getErroredGames($ladderAbbrev)
    {
        $ladder = \App\Ladder::where('abbreviation', $ladderAbbrev)->first();

        if ($ladder == null)
            return "Bad ladder abbreviation " . $ladderAbbrev;

        $ladderHistory = $ladder->currentHistory();

        $numErroredGames = \App\Game::join('game_reports', 'games.game_report_id', '=', 'game_reports.id')
            ->where("ladder_history_id", "=", $ladderHistory->id)
            ->where('game_reports.duration', '<=', 3)
            ->where('finished', '=', 1)
            ->get()->count();

        $url = \App\URLHelper::getLadderUrl($ladderHistory) . '/games?errorGames=true';

        $data = [];
        $data["url"] = "https://ladder.cncnet.org" . $url;
        $data["count"] = $numErroredGames;

        return $data;
    }

    public function getRecentLadderWashedGamesCount($ladderAbbrev, $hours)
    {
        $ladder = \App\Ladder::where("abbreviation", $ladderAbbrev)->first();

        if ($ladder == null)
            return "Bad ladder abbreviation " . $ladderAbbrev;

        $ladderHistory = $ladder->currentHistory();

        $start = Carbon::now()->subHour($hours);

        $gameAuditsCount = \App\GameAudit::where("created_at", ">=", $start)
            ->where("ladder_history_id", $ladderHistory->id)
            ->where("username", "ladder-auto-wash")
            ->count();

        $url = \App\URLHelper::getWashGamesUrl($ladderAbbrev);

        $data = [];
        $data["count"] = $gameAuditsCount;
        $data["url"] = "https://ladder.cncnet.org" . $url;

        return $data;
    }
}
