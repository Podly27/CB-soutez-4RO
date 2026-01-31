<?php

namespace App\Http\Controllers;

use DiDom\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Exceptions\SubmissionException;
use App\Http\Utilities;
use App\Http\Parsers\CbpmrShareParser;
use App\Models\Category;
use App\Models\Contest;
use App\Models\Diary;
use App\Rules\Locator;

class SubmissionController extends Controller
{
    function __construct()
    {
        foreach (config('ctvero.diaryUrlToProcessor') as $diaryUrlTemplate => $processor) {
            $diarySources[] = preg_replace('|^http(s)?://([^/]+)/.*|', '$2', $diaryUrlTemplate);
        }
        $this->diarySources = array_unique($diarySources);
        $this->contests = Contest::submissionActiveOrdered();
        $this->categories = Category::allOrdered();
        $this->messages = Utilities::validatorMessages();
    }

    public function processCbdxCz()
    {
        $doc = new Document($this->diaryUrl, true);
        if ($doc->first('table tr:nth-child(1) td')->text() == 'Název expedice') {
            $this->callSign = preg_replace('|^\s+|u', '', $doc->first('table tr:nth-child(1) td:nth-child(2)')->text());
            $this->callSign = trim($this->callSign);
        }
        if ($doc->first('table tr:nth-child(2) td')->text() == 'QTH - místo vysílání') {
            $this->qthName = preg_replace('|^\s+|u', '', $doc->first('table tr:nth-child(2) td:nth-child(2)')->text());
            $this->qthName = trim($this->qthName);
        }
        if ($doc->first('table tr:nth-child(3) td')->text() == 'Lokátor stanoviště') {
            $this->qthLocator = preg_replace('|^\s+|u', '', $doc->first('table tr:nth-child(3) td:nth-child(2)')->text());
            $this->qthLocator = trim($this->qthLocator);
        }
        if ($doc->first('table tr:nth-child(16) td')->text() == 'Počet uskutečněných spojení') {
            $qsoCountWithDesc = $doc->first('table tr:nth-child(16) td:nth-child(2)')->text();
            $this->qsoCount = preg_replace('|^\s+|u', '', $qsoCountWithDesc);
            $this->qsoCount = preg_replace('| spojení$|', '', $this->qsoCount);
            $this->qsoCount = trim($this->qsoCount);
        }
    }

    public function processCbpmrCz()
    {
        $doc = new Document($this->diaryUrl, true, 'ISO-8859-2');
        if ($doc->first('table.tbl tr:nth-child(5) td')->text() == 'Volačka') {
            $this->callSign = trim($doc->first('table.tbl tr:nth-child(5) td:nth-child(2)')->text());
        }
        if ($doc->first('table.tbl tr:nth-child(6) td')->text() == 'Místo vysílání') {
            $this->qthName = trim($doc->first('table.tbl tr:nth-child(6) td:nth-child(2)')->text());
        }
        if ($doc->first('table.tbl tr:nth-child(7) td')->text() == 'Lokátor stanoviště') {
            $this->qthLocator = trim($doc->first('table.tbl tr:nth-child(7) td:nth-child(2)')->text());
        }
        if ($doc->first('table.tbl tr:nth-child(17) td')->text() == 'Počet spojení') {
            $this->qsoCount = trim($doc->first('table.tbl tr:nth-child(17) td:nth-child(2)')->text());
        }
    }

    public function processCbpmrInfo()
    {
        $this->diaryUrl = preg_replace('|^http:|', 'https:', $this->diaryUrl);
        $parser = new CbpmrShareParser();
        $parsed = $parser->parse($this->diaryUrl);
        $header = $parsed['header'];
        $entries = $parsed['entries'];

        $this->callSign = $header['exp_name'] ?? $header['my_place'] ?? 'CBPMR share';
        $this->qthName = $header['my_place'] ?? $header['exp_name'] ?? 'CBPMR share';
        $this->qthLocator = $header['my_locator'];
        $this->qsoCount = count($entries);

        if (! $this->qthLocator) {
            throw new SubmissionException(422, [__('Nepodařilo se načíst lokátor stanoviště z cbpmr share.')]);
        }
    }

    public function show(Request $request, $resetStep = false)
    {
        $step = $resetStep ? 1 : intval(request()->input('step', 1));
        if ($step < 1 or $step > 2) {
            throw new SubmissionException(422, array(__('Neplatný formulářový krok')), true);
        }
        $diarySources = implode(', ', $this->diarySources);

        return view('submission', [ 'title' => __('Odeslat hlášení'),
                                    'data' => $this,
                                    'step' => $step,
                                    'diarySources' => $diarySources ]);
    }

    public function submit(Request $request)
    {
        Utilities::validateCsrfToken();
        Utilities::checkRecaptcha();

        if ($request->input('step') == 1) {
            if (! $request->input('diaryUrl', false)) {
                throw new SubmissionException(400, array(__('Neúplný požadavek')));
            }

            $diaryUrl = trim($request->input('diaryUrl'));
            $diarySourceFound = false;
            foreach (config('ctvero.diaryUrlToProcessor') as $diaryUrlTemplate => $processor) {
                if (preg_match('|^' . preg_quote($diaryUrlTemplate) . '|', $diaryUrl)) {
                    $processor = 'process' . $processor;
                    $this->diaryUrlTemplate = $diaryUrlTemplate;
                    $this->diaryUrl = $diaryUrl;
                    try {
                        $this->$processor();
                    } catch (SubmissionException $e) {
                        throw $e;
                    } catch (\Exception $e) {
                        throw new SubmissionException(500, array(__('Deník se nepodařilo načíst.')));
                    }
                    $diarySourceFound = true;
                    break;
                }
            }

            if (! $diarySourceFound) {
                throw new SubmissionException(422, array(__('Neznámý zdroj deníku')));
            }

            Session::flash('diary', [
                'url' => $this->diaryUrl,
                'callSign' => $this->callSign,
                'qthName' => $this->qthName,
                'qthLocator' => $this->qthLocator,
                'qsoCount' => $this->qsoCount ]);

            $validator = Validator::make($request->all(), [
                'diaryUrl' => 'required|max:255|unique:\App\Models\Diary,diary_url',
            ], $this->messages);
            if ($validator->fails()) {
                Session::flash('submissionErrors', $validator->errors()->all());
                return redirect(route('submissionForm'));
            }

            return redirect(route('submissionForm', [ 'step' => 2 ]) . '#scroll');
        } elseif ($request->input('step') == 2) {
            $validator = Validator::make($request->all(), [
                'contest' => 'required|max:255',
                'category' => 'required|max:255',
                'diaryUrl' => 'max:255|unique:\App\Models\Diary,diary_url',
                'callSign' => 'required|max:255',
                'qthName' => 'required|max:255',
                'qthLocator' => [ 'required', 'size:6', new Locator ],
                'qsoCount' => 'required|integer|gt:0',
                'email' => 'required|email',
            ], $this->messages);

            if ($validator->fails()) {
                Session::flash('diary', [
                    'contest' => $request->input('contest'),
                    'category' => $request->input('category'),
                    'url' => $request->input('diaryUrl'),
                    'callSign' => $request->input('callSign'),
                    'qthName' => $request->input('qthName'),
                    'qthLocator' => $request->input('qthLocator'),
                    'qsoCount' => $request->input('qsoCount'),
                    'email' => $request->input('email') ]);
                Session::flash('submissionErrors', $validator->errors()->all());
                return redirect(route('submissionForm', [ 'step' => 2 ]));
            }

            try {
                $contestId = $this->contests->where('name', $request->input('contest'))->first()->id;
                $categoryId = $this->categories->where('name', $request->input('category'))->first()->id;

                $diary = new Diary;
                $diary->contest_id = $contestId;
                $diary->category_id = $categoryId;
                if (Auth::check()) {
                    $diary->user_id = Auth::user()->id;
                }
                $diary->diary_url = $request->input('diaryUrl') !== '' ? $request->input('diaryUrl') : NULL;
                $diary->call_sign = $request->input('callSign');
                $diary->qth_name = $request->input('qthName');
                $diary->qth_locator = strtoupper($request->input('qthLocator'));
                list($lon, $lat) = Utilities::locatorToGps($diary->qth_locator);
                $diary->qth_locator_lon = $lon;
                $diary->qth_locator_lat = $lat;
                $diary->qso_count = $request->input('qsoCount');
                $diary->email = $request->input('email');
                $diary->save();

                $contestLink = route('contest', [ 'name' => Str::replace(' ', '-', $request->input('contest')) ]) . '#scroll';
                $contestName = Utilities::contestL10n($request->input('contest'));
                Session::flash('submissionSuccess', __('Hlášení do soutěže <a href=":contestLink">:contestName</a> bylo úspěšně zpracováno.', [ 'contestLink' => $contestLink,
                                                                                                                                                'contestName' => $contestName ]));
                return redirect(route('submissionForm'));
            } catch (\Exception $e) {
                throw new SubmissionException(500, array(__('Hlášení do soutěže se nepodařilo uložit.')));
            }
        } else {
            throw new SubmissionException(400, array(__('Neplatný formulářový krok nebo neúplný požadavek')));
        }
    }
}
