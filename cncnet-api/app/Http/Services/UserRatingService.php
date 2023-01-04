<?php

namespace App\Http\Services;

use App\LadderHistory;
use App\PlayerCache;
use App\PlayerHistory;
use App\User;
use Carbon\Carbon;

class UserRatingService
{
    /**
     * Updates users tiers once a month (or when called from admin panel/cron)
     * @param mixed $history 
     * @return void 
     */
    public function calculateUserTiers($history)
    {
        # Update based on last months
        $lastMonth = Carbon::now();
        $lastMonthStart = $lastMonth->copy()->subMonth(1)->startOfMonth();
        $lastMonthEnd = $lastMonth->copy()->subMonth(1)->endOfMonth();

        $historyLastMonth = LadderHistory::where("ladder_id", $history->ladder->id)
            ->where("starts", $lastMonthStart)
            ->where("ends", $lastMonthEnd)
            ->first();

        $usersLastMonth = PlayerHistory::where("ladder_history_id", $historyLastMonth->id)
            ->join("players as p", "p.id", "=", "player_histories.player_id")
            ->join("users as u", "u.id", "=", "p.user_id")
            ->select("u.*")
            ->get();

        $usersThisMonth = PlayerHistory::where("ladder_history_id", $history->id)
            ->join("players as p", "p.id", "=", "player_histories.player_id")
            ->join("users as u", "u.id", "=", "p.user_id")
            ->select("u.*")
            ->get();

        $this->updateUserRatings($usersLastMonth, $history);
        $this->updateUserRatings($usersThisMonth, $history);
    }

    private function updateUserRatings($users, $history)
    {
        foreach ($users as $u)
        {
            $user = User::find($u->id);

            $userTier = $user->getLiveUserTier($history);
            $userPlayerIds = $user->usernames()->pluck("id")->toArray();

            PlayerHistory::where("ladder_history_id", $history->id)
                ->whereIn("player_id", $userPlayerIds)
                ->update(["tier" => $userTier]);

            PlayerCache::where("ladder_history_id", $history->id)
                ->whereIn("player_id", $userPlayerIds)
                ->update(["tier" => $userTier]);
        }
    }

    public function changeUserRating($user, $newRating, $history)
    {
        # Get live \App\UserRating
        $userRating = $user->getOrCreateLiveUserRating();
        $userRating->rating = $newRating;
        $userRating->save();

        # Update tier for all players history and players cache for this month only
        $userTier = $user->getLiveUserTier($history);

        $userPlayerIds = $user->usernames()->pluck("id")->toArray();

        PlayerHistory::where("ladder_history_id", $history->id)
            ->whereIn("player_id", $userPlayerIds)
            ->update(["tier" => $userTier]);

        PlayerCache::where("ladder_history_id", $history->id)
            ->whereIn("player_id", $userPlayerIds)
            ->update(["tier" => $userTier]);

        return $userRating;
    }


    /**
     * Get user tier based on ladder rules
     * @param mixed $rating 
     * @param mixed $history 
     * @return int 
     */
    public static function getTierByLadderRules($rating, $history)
    {
        if ($rating > $history->ladder->qmLadderRules->tier2_rating)
        {
            return 1;
        }

        return 2;
    }
}
