<?php namespace App\Http\Services;

class LadderService 
{

	public function __construct()
	{

	}

    public function getLadderByGame($game)
    {
        return \App\Ladder::where("abbreviation", "=", $game)
            ->where("ladder_history_id", "=", null)
            ->first();
    }
    
	public function getLadderByGameAbbreviation($game, $limit = 25)
	{
        $ladder = $this->getLadderByGame($game);

        if($ladder == null)
            return "No ladder found";

        $players = \App\Player::where("ladder_id", "=", $ladder->id)
            ->orderBy("points", "DESC")
            ->limit($limit)
            ->get();

        return $players;
	}

    public function getLadderGameById($game, $gameId)
    {
        $ladder = $this->getLadderByGame($game);

        if($ladder == null || $gameId == null)
            return "Invalid parameters";

        $ladderGame = \App\LadderGame::where("ladder_id", "=", $ladder->id)
            ->where("game_id", "=", $gameId)->first();

        if($ladderGame == null)
            return "Game not found";

        return \App\Game::find($ladderGame->id)->first();
    }

    public function getLadderPlayer($game, $player)
    {
        $ladder = $this->getLadderByGame($game);

        if($ladder == null)
            return "No ladder found";

        return \App\Player::where("ladder_id", "=", $ladder->id)
            ->where("username", "=", $player)->first();
    }
}
