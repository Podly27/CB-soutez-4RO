<?php

namespace App\Http\Controllers;

use DiDom\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Services\Cbpmr\CbpmrShareService;
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
        $shareService = app(CbpmrShareService::class);
        $originalUrl = $this->diaryUrl;
        $shareResponse = $shareService->fetch($originalUrl);
        if (! $shareResponse['ok']) {
            $this->logCbpmrShareFailure([
                'original_url' => $originalUrl,
                'final_url' => $shareResponse['final_url'] ?? null,
                'http_code' => $shareResponse['http_code'] ?? null,
                'title' => null,
                'found_rows_count' => null,
                'extracted_portable_id' => null,
                'error' => $shareResponse['error'] ?? null,
                'redirect_chain' => $shareResponse['redirect_chain'] ?? null,
            ]);
            throw new SubmissionException(422, [
                __('Nepodařilo se načíst deník z CBPMR. Zkontrolujte, že odkaz je veřejný.'),
            ]);
        }

        $html = $shareResponse['body'] ?? '';
        $finalUrl = $shareResponse['final_url'] ?? $originalUrl;
        $httpStatus = $shareResponse['http_code'] ?? null;
        $redirectChain = $shareResponse['redirect_chain'] ?? [];
        $finalPath = parse_url($finalUrl, PHP_URL_PATH) ?? '';
        $portableId = $this->extractPortableIdFromPath($finalPath);
        $isShare = Str::startsWith($finalPath, '/share/');
        if (Str::contains($finalPath, '/share/error')) {
            $this->logCbpmrShareFailure([
                'original_url' => $originalUrl,
                'final_url' => $finalUrl,
                'http_code' => $httpStatus,
                'title' => null,
                'found_rows_count' => null,
                'extracted_portable_id' => $portableId,
                'redirect_chain' => $redirectChain,
                'body_snippet' => $this->snippetHtml($html),
            ]);
            throw new SubmissionException(422, [
                __('Nepodařilo se načíst deník z CBPMR. Zkontrolujte, že odkaz je veřejný.'),
            ]);
        }

        $portableParse = $shareService->parsePortable((string) $html, $finalUrl);
        if (! isset($portableParse['title']) || $portableParse['title'] === null) {
            $portableParse['title'] = $shareResponse['title_snippet'] ?? null;
        }
        if (! isset($portableParse['exp_name']) || $portableParse['exp_name'] === null) {
            $portableParse['exp_name'] = $portableParse['title'] ?? null;
        }
        $entries = $portableParse['entries'] ?? [];
        $hasEntries = count($entries) > 0;

        if (! $isShare || ! $hasEntries) {
            $this->logCbpmrShareFailure([
                'original_url' => $originalUrl,
                'final_url' => $finalUrl,
                'http_code' => $httpStatus,
                'title' => null,
                'found_rows_count' => $portableParse['rows_found'] ?? null,
                'extracted_portable_id' => $portableId,
                'error' => $shareResponse['error'] ?? null,
                'redirect_chain' => $redirectChain,
                'body_snippet' => $this->snippetHtml($html),
            ]);
            throw new SubmissionException(422, [
                __('Nepodařilo se načíst deník z CBPMR. Zkontrolujte, že odkaz je veřejný.'),
            ]);
        }

        try {
            $this->applyCbpmrPortablePayload($portableParse, $originalUrl, $finalUrl, $portableId);
        } catch (\RuntimeException $e) {
            $this->logCbpmrShareFailure([
                'original_url' => $originalUrl,
                'final_url' => $finalUrl,
                'http_code' => $httpStatus,
                'title' => null,
                'found_rows_count' => $portableParse['rows_found'] ?? null,
                'extracted_portable_id' => $portableId,
                'error' => $e->getMessage(),
                'redirect_chain' => $redirectChain,
            ]);
            throw new SubmissionException(422, [
                __('Nepodařilo se načíst deník z CBPMR. Zkontrolujte, že odkaz je veřejný.'),
            ]);
        }
    }

    private function applyCbpmrPortablePayload(array $payload, string $originalUrl, string $finalUrl, ?string $portableId): void
    {
        $expName = $payload['exp_name'] ?? $payload['title'] ?? null;
        $place = $payload['place'] ?? $expName;
        $locator = $payload['my_locator'] ?? null;
        $date = $payload['date'] ?? null;
        $entries = $payload['entries'] ?? [];
        $totalCalls = count($entries);
        if (! $place || ! $locator || $totalCalls === 0) {
            throw new \RuntimeException('Invalid CBPMR.info portable response.');
        }

        $rowsFound = $payload['rows_found'] ?? null;
        $qsoCountHeader = $payload['qso_count_header'] ?? null;
        $qsoCount = $rowsFound && $rowsFound > 0 ? $rowsFound : $this->extractInt($qsoCountHeader);

        $this->diaryUrl = $originalUrl;
        $this->callSign = $expName ?? $place;
        $this->qthName = $place;
        $this->qthLocator = strtoupper($locator);
        $this->qsoCount = $qsoCount ?? $totalCalls;
        $this->diaryOptions = [
            'source' => 'cbpmr_info',
            'original_url' => $originalUrl,
            'final_url' => $finalUrl,
            'portable_id' => $portableId,
            'exp_name' => $expName,
            'date' => $date,
            'total_km' => $payload['total_km'] ?? null,
            'entries' => $this->mapCbpmrEntries($entries, $date, $expName),
            'entries_count' => $totalCalls,
            'fetched_at' => \Carbon\Carbon::now()->toAtomString(),
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

    private function extractInt(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function mapCbpmrEntries(array $entries, ?string $date = null, ?string $fallbackName = null): array
    {
        $mapped = [];
        foreach ($entries as $entry) {
            $kmInt = $entry['km_int'] ?? $entry['km'] ?? null;
            $locator = $entry['locator'] ?? $entry['remote_locator'] ?? null;
            $row = [
                'date' => $date,
                'time' => $entry['time'] ?? null,
                'locator' => $locator ? strtoupper($locator) : null,
                'km' => $kmInt !== null ? sprintf('%d km', $kmInt) : null,
                'km_int' => $kmInt,
                'name' => $entry['name'] ?? $fallbackName,
            ];

            if (! empty($entry['note'])) {
                $row['note'] = $entry['note'];
            }

            $mapped[] = $row;
        }

        return $mapped;
    }

    private function logCbpmrShareFailure(array $context): void
    {
        $payload = [
            'timestamp: ' . date(DATE_ATOM),
            'original_url: ' . ($context['original_url'] ?? ''),
            'final_url: ' . ($context['final_url'] ?? ''),
            'http_code: ' . ($context['http_code'] ?? ''),
            'error: ' . ($context['error'] ?? ''),
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

        return response()->view('submission', [ 'title' => __('Odeslat hlášení'),
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
            if ($this->isCbpmrShareUrl($diaryUrl)) {
                $this->diaryUrl = $diaryUrl;
                try {
                    $this->processCbpmrInfo();
                } catch (SubmissionException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw new SubmissionException(500, array(__('Deník se nepodařilo načíst.')));
                }
                $diarySourceFound = true;
            } else {
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
            $sessionDiary = Session::get('diary', []);
            $diaryUrl = trim((string) $request->input('diaryUrl', $sessionDiary['url'] ?? ''));
            $diaryOptions = (string) $request->input('diaryOptions', $sessionDiary['options'] ?? '');
            $request->merge([
                'diaryUrl' => $diaryUrl,
                'diaryOptions' => $diaryOptions,
            ]);

            $optionsJson = $diaryOptions;
            $decodedOptions = $this->decodeDiaryOptions($optionsJson);
            $source = $this->detectDiarySource($diaryUrl, $decodedOptions);

            if ($source === 'cbpmr_info') {
                $fallbackContest = $this->resolveContestNameFromOptions($decodedOptions);
                if (! $request->filled('contest') && $fallbackContest) {
                    $request->merge(['contest' => $fallbackContest]);
                }
            }

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
                    'url' => $diaryUrl,
                    'callSign' => $request->input('callSign'),
                    'qthName' => $request->input('qthName'),
                    'qthLocator' => $request->input('qthLocator'),
                    'qsoCount' => $request->input('qsoCount'),
                    'email' => $request->input('email'),
                    'options' => $diaryOptions,
                ]);
                Session::flash('submissionErrors', $validator->errors()->all());
                return redirect(route('submissionForm', [ 'step' => 2 ]));
            }

            try {
                $stage = 'map_import';
                $failReason = null;

                $contest = $this->contests->where('name', $request->input('contest'))->first();
                if (! $contest) {
                    $failReason = 'contest_not_found';
                    throw new \RuntimeException('Contest not found.');
                }

                $category = $this->categories->where('name', $request->input('category'))->first();
                if (! $category) {
                    $failReason = 'category_not_found';
                    throw new \RuntimeException('Category not found.');
                }

                $contestId = $contest->id;
                $categoryId = $category->id;

                $diary = new Diary;
                $stage = 'db_save';
                $diary->contest_id = $contestId;
                $diary->category_id = $categoryId;
                if (Auth::check()) {
                    $diary->user_id = Auth::user()->id;
                }
                $diary->diary_url = $diaryUrl !== '' ? $diaryUrl : NULL;
                $diary->call_sign = $request->input('callSign');
                $diary->qth_name = $request->input('qthName');
                $diary->qth_locator = strtoupper($request->input('qthLocator'));
                list($lon, $lat) = Utilities::locatorToGps($diary->qth_locator);
                $diary->qth_locator_lon = $lon;
                $diary->qth_locator_lat = $lat;
                $diary->qso_count = $request->input('qsoCount');
                $diary->email = $request->input('email');
                $decodedOptions = $this->normalizeDiaryOptions(
                    $decodedOptions,
                    [
                        'contest_id' => $contestId,
                        'category_id' => $categoryId,
                        'call_sign' => $request->input('callSign'),
                        'qth_name' => $request->input('qthName'),
                        'qth_locator' => $request->input('qthLocator'),
                    ]
                );
                if (is_array($decodedOptions)) {
                    $diary->options = $decodedOptions;
                }
                $diary->save();

                $contestLink = route('contest', [ 'name' => Str::replace(' ', '-', $request->input('contest')) ]) . '#scroll';
                $contestName = Utilities::contestL10n($request->input('contest'));
                Session::flash('submissionSuccess', __('Hlášení do soutěže <a href=":contestLink">:contestName</a> bylo úspěšně zpracováno.', [ 'contestLink' => $contestLink,
                                                                                                                                                'contestName' => $contestName ]));
                return redirect(route('submissionForm'));
            } catch (\Exception $e) {
                $this->logSubmissionException(
                    $stage ?? 'db_save',
                    $request,
                    $decodedOptions ?? null,
                    $source ?? $this->detectDiarySource($diaryUrl, $decodedOptions ?? null),
                    $failReason ?? null,
                    $e
                );
                throw new SubmissionException(500, array(__('Hlášení do soutěže se nepodařilo uložit.')));
            }
        } else {
            throw new SubmissionException(400, array(__('Neplatný formulářový krok nebo neúplný požadavek')));
        }
    }

    private function isCbpmrShareUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! is_array($parsed)) {
            return false;
        }

        $host = strtolower($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '';

        return in_array($host, ['cbpmr.info', 'www.cbpmr.info'], true)
            && Str::startsWith($path, '/share/');
    }

    public function dryRun(Request $request)
    {
        $token = env('DIAG_TOKEN');
        $requestToken = $request->query('token');
        if (! $token || ! hash_equals((string) $token, (string) $requestToken)) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden',
            ], 200);
        }

        $url = $request->query('url');
        if (! is_string($url) || trim($url) === '') {
            return response()->json([
                'ok' => false,
                'error' => 'missing_url',
            ], 200);
        }

        if (! $this->isCbpmrShareUrl($url)) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_url',
            ], 200);
        }

        $this->diaryUrl = $url;

        try {
            $this->processCbpmrInfo();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'import_failed',
                'message' => $e->getMessage(),
            ], 200);
        }

        $options = $this->diaryOptions;
        $source = $this->detectDiarySource($this->diaryUrl, $options);
        $contestName = $this->resolveContestNameFromOptions($options);
        $categoryName = $this->categories->first()->name ?? null;

        $payload = [
            'contest' => $contestName,
            'category' => $categoryName,
            'diaryUrl' => $this->diaryUrl,
            'callSign' => $this->callSign,
            'qthName' => $this->qthName,
            'qthLocator' => $this->qthLocator,
            'qsoCount' => $this->qsoCount,
            'email' => 'dry-run@example.com',
        ];

        $validator = Validator::make($payload, [
            'contest' => 'required|max:255',
            'category' => 'required|max:255',
            'diaryUrl' => 'max:255|unique:\App\Models\Diary,diary_url',
            'callSign' => 'required|max:255',
            'qthName' => 'required|max:255',
            'qthLocator' => [ 'required', 'size:6', new Locator ],
            'qsoCount' => 'required|integer|gt:0',
            'email' => 'required|email',
        ], $this->messages);

        $contest = $contestName ? $this->contests->where('name', $contestName)->first() : null;
        $category = $categoryName ? $this->categories->where('name', $categoryName)->first() : null;

        $options = $this->normalizeDiaryOptions($options, [
            'contest_id' => $contest ? $contest->id : null,
            'category_id' => $category ? $category->id : null,
            'call_sign' => $this->callSign,
            'qth_name' => $this->qthName,
            'qth_locator' => $this->qthLocator,
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->keys();
            $failReason = $errors[0] ?? 'validation_failed';
            return response()->json([
                'ok' => false,
                'fail_reason' => $failReason,
                'computed_fields' => [
                    'contest_id' => $options['contest_id'] ?? null,
                    'diary_type' => $options['diary_type'] ?? null,
                    'qth' => $this->qthLocator,
                    'entries_count' => $options['entries_count'] ?? null,
                    'source' => $source,
                ],
            ], 200);
        }

        return response()->json([
            'ok' => true,
            'computed_fields' => [
                'contest_id' => $options['contest_id'] ?? null,
                'diary_type' => $options['diary_type'] ?? null,
                'qth' => $this->qthLocator,
                'entries_count' => $options['entries_count'] ?? null,
                'source' => $source,
            ],
        ], 200);
    }

    private function decodeDiaryOptions(?string $optionsJson): ?array
    {
        if (! is_string($optionsJson) || trim($optionsJson) === '') {
            return null;
        }

        $decoded = json_decode($optionsJson, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeDiaryOptions(?array $options, array $context): ?array
    {
        if (! is_array($options)) {
            return null;
        }

        if (($options['source'] ?? null) !== 'cbpmr_info') {
            return $options;
        }

        $options['contest_id'] = $options['contest_id'] ?? $context['contest_id'] ?? null;
        $options['category_id'] = $options['category_id'] ?? $context['category_id'] ?? null;
        $options['diary_type'] = $options['diary_type'] ?? 'other';
        $options['call_sign'] = $options['call_sign'] ?? $context['call_sign'] ?? null;
        $options['qth_name'] = $options['qth_name'] ?? $context['qth_name'] ?? null;
        $qthLocator = $context['qth_locator'] ?? null;
        if (is_string($qthLocator)) {
            $qthLocator = strtoupper($qthLocator);
        }
        $options['qth_locator'] = $options['qth_locator'] ?? $qthLocator;
        $options['date'] = $options['date'] ?? $this->defaultCbpmrDate();

        if (! isset($options['entries_count']) && isset($options['entries']) && is_array($options['entries'])) {
            $options['entries_count'] = count($options['entries']);
        }

        return $options;
    }

    private function defaultCbpmrDate(): string
    {
        return \Carbon\Carbon::now()->format('d.m.Y');
    }

    private function detectDiarySource(?string $diaryUrl, ?array $options): string
    {
        if (is_array($options) && isset($options['source'])) {
            return $options['source'];
        }

        if (! is_string($diaryUrl) || $diaryUrl === '') {
            return 'manual';
        }

        if ($this->isCbpmrShareUrl($diaryUrl)) {
            return 'cbpmr_info';
        }

        if (Str::startsWith($diaryUrl, ['http://www.cbpmr.cz/deniky/', 'https://www.cbpmr.cz/deniky/'])) {
            return 'cbpmr_cz';
        }

        if (Str::startsWith($diaryUrl, ['http://drive.cbdx.cz/xdenik', 'https://drive.cbdx.cz/xdenik'])) {
            return 'cbdx';
        }

        return 'manual';
    }

    private function resolveContestNameFromOptions(?array $options): ?string
    {
        if (isset($options['contest_id'])) {
            $contest = $this->contests->firstWhere('id', $options['contest_id']);
            if ($contest) {
                return $contest->name;
            }
        }

        $date = is_array($options) ? ($options['date'] ?? null) : null;
        $parsedDate = $this->parseCbpmrDate($date);
        if ($parsedDate) {
            $contest = $this->contests->first(function ($contest) use ($parsedDate) {
                return $parsedDate->between($contest->contest_start, $contest->contest_end);
            });
            if ($contest) {
                return $contest->name;
            }
        }

        return $this->contests->first()->name ?? null;
    }

    private function parseCbpmrDate(?string $date): ?\Carbon\Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('d.m.Y', $date);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function logSubmissionException(
        string $stage,
        Request $request,
        ?array $options,
        string $source,
        ?string $failReason,
        \Throwable $exception
    ): void {
        $requiredFields = [
            'contest' => $request->input('contest'),
            'category' => $request->input('category'),
            'diaryUrl' => $request->input('diaryUrl'),
            'callSign' => $request->input('callSign'),
            'qthName' => $request->input('qthName'),
            'qthLocator' => $request->input('qthLocator'),
            'qsoCount' => $request->input('qsoCount'),
            'email' => $request->input('email'),
        ];

        $entries = is_array($options) ? ($options['entries'] ?? []) : [];
        $entriesPreview = [];
        if (is_array($entries)) {
            foreach (array_slice($entries, 0, 2) as $entry) {
                $entriesPreview[] = $entry;
            }
        }

        $payload = [
            'timestamp: ' . date(DATE_ATOM),
            'stage: ' . $stage,
            'source: ' . $source,
            'fail_reason: ' . ($failReason ?? ''),
            'exception: ' . get_class($exception) . ' ' . $exception->getMessage(),
            'exception_file: ' . $exception->getFile() . ':' . $exception->getLine(),
            'required_fields: ' . json_encode($requiredFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'session_diary_url: ' . Session::get('diary.url'),
            'session_diary_options: ' . Session::get('diary.options'),
            'entries_count: ' . (is_array($entries) ? count($entries) : 0),
            'entries_preview: ' . json_encode($entriesPreview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        @file_put_contents(storage_path('logs/last_exception.txt'), implode("\n", $payload));
    }
}
