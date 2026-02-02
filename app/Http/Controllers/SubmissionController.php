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
        $entries = $payload['entries'] ?? [];
        $totalCalls = count($entries);
        if (! $place || ! $locator || $totalCalls === 0) {
            throw new \RuntimeException('Invalid CBPMR.info portable response.');
        }

        $normalizedLocator = $this->normalizeLocator($locator);
        if ($normalizedLocator === null) {
            throw new \RuntimeException('Invalid CBPMR.info locator.');
        }

        $rowsFound = $payload['rows_found'] ?? null;
        $qsoCountHeader = $payload['qso_count_header'] ?? null;
        $qsoCount = $this->resolveCbpmrQsoCount($rowsFound, $qsoCountHeader, $totalCalls);

        $this->diaryUrl = $originalUrl;
        $this->callSign = $expName ?? $place;
        $this->qthName = $place;
        $this->qthLocator = $normalizedLocator;
        $this->qsoCount = $qsoCount;
        $this->diaryOptions = $this->ensureDiaryOptions([
            'source' => 'cbpmr',
            'original_url' => $originalUrl,
            'final_url' => $finalUrl,
            'portable_id' => $portableId,
            'total_km' => $payload['total_km'] ?? null,
            'entries' => $this->mapCbpmrEntries($entries),
            'fetched_at' => \Carbon\Carbon::now()->toAtomString(),
        ]);
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

    private function normalizeLocator(?string $locator): ?string
    {
        if (! is_string($locator)) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $locator));

        return strlen($normalized) === 6 ? $normalized : null;
    }

    private function resolveCbpmrQsoCount($rowsFound, ?string $qsoCountHeader, int $totalCalls): int
    {
        $rowsFoundInt = is_numeric($rowsFound) ? (int) $rowsFound : null;
        $qsoCount = $rowsFoundInt && $rowsFoundInt > 0 ? $rowsFoundInt : $this->extractInt($qsoCountHeader);
        if (! $qsoCount || $qsoCount <= 0) {
            $qsoCount = $totalCalls;
        }

        if ($qsoCount <= 0) {
            throw new \RuntimeException('Invalid CBPMR.info QSO count.');
        }

        return $qsoCount;
    }

    private function ensureDiaryOptions(array $options): array
    {
        $encoded = json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Invalid CBPMR.info diary options.');
        }

        $decoded = json_decode($encoded, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid CBPMR.info diary options.');
        }

        return $decoded;
    }

    private function mapCbpmrEntries(array $entries): array
    {
        $mapped = [];
        foreach ($entries as $entry) {
            $row = [
                'time' => $entry['time'] ?? null,
                'locator' => $entry['locator'] ?? null,
                'km' => $entry['km_int'] ?? null,
                'name' => $entry['name'] ?? null,
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
                $optionsJson = json_encode($this->diaryOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

            $decodedOptions = null;
            $optionsJson = $request->input('diaryOptions');
            if (is_string($optionsJson) && trim($optionsJson) !== '') {
                $decodedOptions = json_decode($optionsJson, true);
            }
            $contestRecord = null;
            $categoryRecord = null;
            $contestId = null;
            $categoryId = null;
            $diary = null;

            try {
                $contestRecord = $this->contests->where('name', $request->input('contest'))->first();
                $contestId = $contestRecord->id;
                $categoryRecord = $this->categories->where('name', $request->input('category'))->first();
                $categoryId = $categoryRecord->id;

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
                if (is_array($decodedOptions)) {
                    $diary->options = $decodedOptions;
                }
                $diary->offsetUnset('options');
                $diary->save();

                $contestLink = route('contest', [ 'name' => Str::replace(' ', '-', $request->input('contest')) ]) . '#scroll';
                $contestName = Utilities::contestL10n($request->input('contest'));
                Session::flash('submissionSuccess', __('Hlášení do soutěže <a href=":contestLink">:contestName</a> bylo úspěšně zpracováno.', [ 'contestLink' => $contestLink,
                                                                                                                                                'contestName' => $contestName ]));
                return redirect(route('submissionForm'));
            } catch (\App\Exceptions\SubmissionException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $id = bin2hex(random_bytes(4));

                $payload = [
                    'id' => $id,
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'time' => date('c'),
                    'context' => [
                        'contestId' => $contestId ?? null,
                        'categoryId' => $categoryId ?? null,
                        'diaryUrl' => $diary->diary_url ?? null,
                        'locator' => $diary->qth_locator ?? null,
                        'qsoCount' => $diary->qso_count ?? null,
                        'source' => $diary->source ?? null,
                    ],
                ];

                if ($e instanceof \Illuminate\Database\QueryException) {
                    $payload['sql'] = $e->getSql();
                    $payload['bindings'] = $e->getBindings();
                }

                $txt = '';
                foreach ($payload as $k => $v) {
                    $txt .= $k . ': ' . (is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . PHP_EOL;
                }
                $txt .= 'Stack trace:' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;

                @file_put_contents(storage_path('logs/last_exception.txt'), $txt);

                throw new \App\Exceptions\SubmissionException(
                    500,
                    'Hlášení do soutěže se nepodařilo uložit. ID: ' . $id
                );
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
}
