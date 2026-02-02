<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Contest;

class ResultsController extends Controller
{
    public function show()
    {
        $allContests = Contest::allOrdered();
        $categories = Category::allOrdered();
        $contestsInProgress = Contest::submissionActiveOrdered();
        foreach ($allContests as $contest) {
            $diaries = DiaryController::getContestDiaries($contest);
            if ($diaries) {
                $allContestsDiaries[$contest->name] = $diaries;
            }
        }

        return response()->view('results', [ 'title' => __('Výsledkové listiny'),
                                             'allContests' => $allContests,
                                             'allContestsDiaries' => $allContestsDiaries,
                                             'categories' => $categories,
                                             'contestsInProgress' => $contestsInProgress ]);
    }
}
