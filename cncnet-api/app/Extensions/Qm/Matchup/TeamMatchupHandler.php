<?php

namespace App\Extensions\Qm\Matchup;

use App\Models\QmQueueEntry;
use Illuminate\Support\Facades\Log;

class TeamMatchupHandler extends BaseMatchupHandler
{

    public function matchup() : void
    {
        $ladder = $this->history->ladder;
        $ladderRules = $ladder->qmLadderRules;

        // Check if current player is an observer
        if ($this->qmPlayer->isObserver()) {
            // If yes, then we skip the matchup because we don't want to compare
            // observer with other actual players to find a match.
            // Observer will be added to the match later on.
            return;
        }

        // Fetch all other players in the queue
        $opponents = $this->quickMatchService->fetchQmQueueEntry($this->history, $this->qmQueueEntry);

        // Find opponents that can be matched with current player.
        $matchableOpponents = $opponents;

        // Count the number of players we need to start a match
        // Excluding current player
        $numberOfOpponentsNeeded = $ladderRules->player_count - 1;

        // Check if there is enough opponents
        if ($matchableOpponents->count() < $numberOfOpponentsNeeded) {
            Log::info("FindOpponent ** Not enough players for match yet");
            $this->qmPlayer->touch();
            return;
        }

        [$teamAPlayers, $teamBPlayers] = $this->quickMatchService->getBestMatch2v2ForPlayer(
            $this->qmQueueEntry,
            $matchableOpponents,
            $this->history
        );

        $players = $teamAPlayers->merge($teamBPlayers);

        $commonQmMaps = $this->quickMatchService->getCommonMapsForPlayers($ladder, $players);

        if (count($commonQmMaps) < 1) {
            Log::info("FindOpponent ** No common maps available");
            $this->qmPlayer->touch();
            return;
        }

        // todo create match
        // ...
        // $this->createMatch($commonQmMaps, $teamAPlayers, $teamBPlayers)
    }
}