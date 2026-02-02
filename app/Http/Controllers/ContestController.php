<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Exceptions\ContestException;
use App\Http\Utilities;
use App\Models\Contest;

class ContestController extends Controller
{
    public function show($name)
    {
        $contest = Contest::where('name', Str::replace('-', ' ', urldecode($name)))->first();
        if (empty($contest)) {
            throw new ContestException(404, array(__('Soutěž nebyla nalezena.')));
        }

        foreach (DiaryController::getContestDiaries($contest) as $categoryDiaries) {
            foreach ($categoryDiaries as $diary) {
                $diaries[$diary->qth_locator][] = [
                    'callSign' => $diary->call_sign,
                    'qthName' => $diary->qth_name,
                    'qthLocator' => $diary->qth_locator,
                    'qthLocatorLon' => $diary->qth_locator_lon,
                    'qthLocatorLat' => $diary->qth_locator_lat,
                    'categoryName' => __($diary->category->name),
                    'categoryMapMarkerColor' => $diary->category->map_marker_color,
                    'diaryUrl' => $diary->diary_url,
                    'qsoCount' => $diary->qso_count
                ];
            }
        }
        return response()->view('contest', [ 'title' => __('Soutěžní kolo') . ' ' . Utilities::contestL10n($contest->name),
                                             'contest' => $contest,
                                             'diaries' => $diaries ?? [] ]);
    }

    public function showAll(Request $request)
    {
        return response()->view('contests', [ 'title' => __('Soutěžní kola'),
                                              'contests' => Contest::allOrdered() ]);
    }
}
