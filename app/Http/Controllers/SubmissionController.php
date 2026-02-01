<?php

namespace App\Http\Controllers;

use DiDom\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Services\Cbpmr\CbpmrShareFetcher;
use App\Services\Cbpmr\CbpmrShareParser;
use App\Exceptions\SubmissionException;
use App\Http\Utilities;
use App\Models\Category;
use App\Models\Contest;
use App\Models\Diary;
use App\Rules\Locator;

class SubmissionController extends Controller
{
    private ?array $diaryOptions = null;

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
        $shareFetcher = app(CbpmrShareFetcher::class);
        $shareParser = app(CbpmrShareParser::class);
        $originalUrl = $this->diaryUrl;
        $shareResponse = $shareFetcher->fetch($originalUrl, ['use_cookies' => true]);
        if (! $shareResponse['ok']) {
            $this->logCbpmrShareFailure([
                'original_url' => $originalUrl,
                'final_url' => $shareResponse['url'] ?? null,
                'http_code' => $shareResponse['http_status'] ?? null,
                'title' => null,
                'found_rows_count' => null,
                'extracted_portable_id' => null,
                'redirect_chain' => $shareResponse['redirect_chain'] ?? null,
            ]);
            throw new SubmissionException(422, [
                __('Nepodařilo se načíst data deníku z CBPMR. Ověřte, že odkaz je veřejný.'),
            ]);
        }

        $html = $shareResponse['body'] ?? '';
        $finalUrl = $shareResponse['final_url'] ?? $originalUrl;
        $httpStatus = $shareResponse['http_status'] ?? null;
        $redirectChain = $shareResponse['redirect_chain'] ?? [];
        $finalPath = parse_url($finalUrl, PHP_URL_PATH) ?? '';
        $portableId = $this->extractPortableIdFromPath($finalPath);
        $portableParse = $shareParser->parsePortable($html);
        $title = $portableParse['title'] ?? null;
        $foundRowsCount = $portableParse['found_rows_count'] ?? null;
        $hasTable = $portableParse['has_table'] ?? false;
        $entries = $portableParse['entries'] ?? [];
        $hasEntries = count($entries) > 0;
        $isPortable = Str::contains($finalPath, '/share/portable/');

        if (! $isPortable || ! $hasTable || ! $hasEntries) {
            $this->logCbpmrShareFailure([
                'original_url' => $originalUrl,
                'final_url' => $finalUrl,
                'http_code' => $httpStatus,
                'title' => $title,
                'found_rows_count' => $foundRowsCount,
                'extracted_portable_id' => $portableId,
                'redirect_chain' => $redirectChain,
                'body_snippet' => $this->snippetHtml($html),
            ]);
            throw new SubmissionException(422, [
                __('Nepodařilo se načíst data deníku z CBPMR. Ověřte, že odkaz je veřejný.'),
            ]);
        }

        try {
            $this->applyCbpmrPortablePayload($portableParse, $originalUrl, $finalUrl, $portableId);
        } catch (\RuntimeException $e) {
            $this->logCbpmrShareFailure([
                'original_url' => $originalUrl,
                'final_url' => $finalUrl,
                'http_code' => $httpStatus,
                'title' => $title,
                'found_rows_count' => $foundRowsCount,
                'extracted_portable_id' => $portableId,
                'redirect_chain' => $redirectChain,
            ]);
            throw new SubmissionException(422, [
                __('Nepodařilo se načíst data deníku z CBPMR. Ověřte, že odkaz je veřejný.'),
            ]);
        }
    }

    private function applyCbpmrPortablePayload(array $payload, string $originalUrl, string $finalUrl, ?string $portableId): void
    {
        $expName = $payload['exp_name'] ?? $payload['title'] ?? null;
        $place = $payload['place'] ?? $expName;
        $locator = $payload['my_locator'] ?? null;
        $entries = $payload['entries'] ?? [];
        $totalCalls = count($entries);
        if (! $place || ! $locator || $totalCalls === 0) {
            throw new \RuntimeException('Invalid CBPMR.info portable response.');
        }

        $this->diaryUrl = $originalUrl;
        $this->callSign = $expName ?? $place;
        $this->qthName = $place;
        $this->qthLocator = $locator;
        $this->qsoCount = $totalCalls;
        $this->diaryOptions = [
            'source' => 'cbpmr',
            'original_url' => $originalUrl,
            'final_url' => $finalUrl,
            'portable_id' => $portableId,
            'total_km' => $payload['total_km'] ?? null,
            'fetched_at' => now()->toAtomString(),
        ];
    }

    private function extractPortableIdFromPath(string $path): ?string
    {
        if (preg_match('|/share/portable/(\\d+)|', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function snippetHtml(string $html): ?string
    {
        $snippet = trim(mb_substr($html, 0, 500, 'UTF-8'));
        return $snippet !== '' ? $snippet : null;
    }

    private function logCbpmrShareFailure(array $context): void
    {
        $payload = [
            'timestamp: ' . date(DATE_ATOM),
            'original_url: ' . ($context['original_url'] ?? ''),
            'final_url: ' . ($context['final_url'] ?? ''),
            'http_code: ' . ($context['http_code'] ?? ''),
            'title: ' . ($context['title'] ?? ''),
            'found_rows_count: ' . ($context['found_rows_count'] ?? ''),
            'extracted_portable_id: ' . ($context['extracted_portable_id'] ?? ''),
        ];

        if (! empty($context['redirect_chain'])) {
            $payload[] = 'redirect_chain: ' . json_encode($context['redirect_chain'], JSON_UNESCAPED_SLASHES);
        }

        if (! empty($context['body_snippet'])) {
            $payload[] = 'body_snippet: ' . $context['body_snippet'];
        }

        $message = implode("\n", $payload);
        @file_put_contents(storage_path('logs/last_exception.txt'), $message);
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

            $optionsJson = null;
            if (is_array($this->diaryOptions)) {
                $optionsJson = json_encode($this->diaryOptions, JSON_UNESCAPED_SLASHES);
            }

            Session::flash('diary', [
                'url' => $this->diaryUrl,
                'callSign' => $this->callSign,
                'qthName' => $this->qthName,
                'qthLocator' => $this->qthLocator,
                'qsoCount' => $this->qsoCount,
                'options' => $optionsJson,
            ]);

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
                    'email' => $request->input('email'),
                    'options' => $request->input('diaryOptions'),
                ]);
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
                $optionsJson = $request->input('diaryOptions');
                if (is_string($optionsJson) && trim($optionsJson) !== '') {
                    $decodedOptions = json_decode($optionsJson, true);
                    if (is_array($decodedOptions)) {
                        $diary->options = $decodedOptions;
                    }
                }
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
