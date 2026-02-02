<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Category;
use App\Models\Contest;

class IndexController extends Controller
{
    public function show(Request $request)
    {
        $lastContest = Contest::lastContest();
        $useScorePoints = ($lastContest->options['criterion'] ?? NULL == 'score_points') or false;
        $currentAndNextTwo = Contest::currentAndNextTwo();
        $recentAndFutureContests = (Contest::previousOne())->merge($currentAndNextTwo);
        $activeContests = (Contest::runningOrdered())->merge(Contest::submissionActiveOrdered());
        $highlightContestId = $activeContests->merge($currentAndNextTwo)->first()->id ?? NULL;
        $lastContestDiaries = DiaryController::getContestDiaries($lastContest);
        $categories = Category::allOrdered();

        return response()->view('index', [ 'title' => 'Home',
                                           'recentAndFutureContests' => $recentAndFutureContests,
                                           'highlightContestId' => $highlightContestId,
                                           'useScorePoints' => $useScorePoints,
                                           'lastContestDiaries' => $lastContestDiaries,
                                           'categories' => $categories,
                                           'lastContest' => $lastContest ]);
    }
}
