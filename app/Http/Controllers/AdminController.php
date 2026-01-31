<?php

namespace App\Http\Controllers;

use App\Http\Utilities;
use App\Models\Category;
use App\Models\Contest;
use App\Models\Diary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'title' => __('Admin'),
        ]);
    }

    public function contestsIndex()
    {
        return view('admin.contests.index', [
            'title' => __('Soutěže'),
            'contests' => Contest::orderBy('contest_start', 'desc')->get(),
        ]);
    }

    public function contestsCreate()
    {
        return view('admin.contests.create', [
            'title' => __('Nové kolo'),
            'defaults' => $this->defaultContestDates(),
        ]);
    }

    public function contestsEdit($id)
    {
        $contest = Contest::find($id);
        if (! $contest) {
            abort(404);
        }

        return view('admin.contests.edit', [
            'title' => __('Upravit soutěž'),
            'contest' => $contest,
        ]);
    }

    public function contestsStore(Request $request)
    {
        Utilities::validateCsrfToken();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'contest_start' => 'required|string',
            'contest_end' => 'required|string',
            'submission_start' => 'required|string',
            'submission_end' => 'required|string',
        ], Utilities::validatorMessages());

        if ($validator->fails()) {
            Session::flash('errors', $validator->errors()->all());
            return redirect(route('adminContestCreate'));
        }

        $contestStart = $this->parseAdminDateTime($request->input('contest_start'));
        $contestEnd = $this->parseAdminDateTime($request->input('contest_end'));
        $submissionStart = $this->parseAdminDateTime($request->input('submission_start'));
        $submissionEnd = $this->parseAdminDateTime($request->input('submission_end'));

        if (! $contestStart || ! $contestEnd || ! $submissionStart || ! $submissionEnd) {
            Session::flash('errors', [__('Datum a čas musí být ve formátu RRRR-MM-DD HH:MM.')]);
            return redirect(route('adminContestCreate'));
        }

        if ($contestStart->gt($contestEnd)) {
            Session::flash('errors', [__('Začátek soutěže musí být dříve než konec.')]);
            return redirect(route('adminContestCreate'));
        }

        if ($submissionStart->gt($submissionEnd)) {
            Session::flash('errors', [__('Začátek odesílání musí být dříve než konec.')]);
            return redirect(route('adminContestCreate'));
        }

        if ($submissionStart->lt($contestStart)) {
            Session::flash('errors', [__('Začátek odesílání nesmí být dříve než začátek soutěže.')]);
            return redirect(route('adminContestCreate'));
        }

        $payload = [
            'name' => trim((string) $request->input('name')),
            'contest_start' => $contestStart->format('Y-m-d H:i:s'),
            'contest_end' => $contestEnd->format('Y-m-d H:i:s'),
            'submission_start' => $submissionStart->format('Y-m-d H:i:s'),
            'submission_end' => $submissionEnd->format('Y-m-d H:i:s'),
        ];

        $contest = new Contest();
        $contest->fill($payload);
        $contest->save();

        $this->logAdminAction(
            Auth::user()->email ?? null,
            'contest_create',
            $contest->id,
            ['fields' => $payload]
        );

        Session::flash('successes', [__('Kolo vytvořeno')]);
        return redirect(route('adminContestEdit', ['id' => $contest->id]));
    }

    public function contestsUpdate(Request $request, $id)
    {
        Utilities::validateCsrfToken();

        $contest = Contest::find($id);
        if (! $contest) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'contest_start' => 'required|string',
            'contest_end' => 'required|string',
            'submission_start' => 'required|string',
            'submission_end' => 'required|string',
        ], Utilities::validatorMessages());

        if ($validator->fails()) {
            Session::flash('errors', $validator->errors()->all());
            return redirect(route('adminContestEdit', ['id' => $contest->id]));
        }

        $contestStart = $this->parseAdminDateTime($request->input('contest_start'));
        $contestEnd = $this->parseAdminDateTime($request->input('contest_end'));
        $submissionStart = $this->parseAdminDateTime($request->input('submission_start'));
        $submissionEnd = $this->parseAdminDateTime($request->input('submission_end'));

        if (! $contestStart || ! $contestEnd || ! $submissionStart || ! $submissionEnd) {
            Session::flash('errors', [__('Datum a čas musí být ve formátu RRRR-MM-DD HH:MM.')]);
            return redirect(route('adminContestEdit', ['id' => $contest->id]));
        }

        if ($contestStart->gt($contestEnd)) {
            Session::flash('errors', [__('Začátek soutěže musí být dříve než konec.')]);
            return redirect(route('adminContestEdit', ['id' => $contest->id]));
        }

        if ($submissionStart->gt($submissionEnd)) {
            Session::flash('errors', [__('Začátek odesílání musí být dříve než konec.')]);
            return redirect(route('adminContestEdit', ['id' => $contest->id]));
        }

        $payload = [
            'name' => trim((string) $request->input('name')),
            'contest_start' => $contestStart->format('Y-m-d H:i:s'),
            'contest_end' => $contestEnd->format('Y-m-d H:i:s'),
            'submission_start' => $submissionStart->format('Y-m-d H:i:s'),
            'submission_end' => $submissionEnd->format('Y-m-d H:i:s'),
        ];

        $original = $contest->only(array_keys($payload));
        $contest->fill($payload);
        $contest->save();

        $this->logAdminAction(
            Auth::user()->email ?? null,
            'contest_update',
            $contest->id,
            $this->diffPayload($original, $payload)
        );

        Session::flash('successes', [__('Uloženo')]);
        return redirect(route('adminContestEdit', ['id' => $contest->id]));
    }

    public function diariesIndex(Request $request)
    {
        $contestId = $request->query('contest_id');
        $categoryId = $request->query('category_id');
        $search = trim((string) $request->query('search'));

        $query = Diary::with(['contest', 'category', 'user'])->orderBy('id', 'desc');

        if ($contestId) {
            $query->where('contest_id', $contestId);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('call_sign', 'like', '%' . $search . '%')
                    ->orWhere('qth_name', 'like', '%' . $search . '%')
                    ->orWhere('qth_locator', 'like', '%' . $search . '%');
            });
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $diaries = $query->forPage($page, $perPage)->get();

        return view('admin.diaries.index', [
            'title' => __('Deníky'),
            'diaries' => $diaries,
            'contests' => Contest::orderBy('contest_start', 'desc')->get(),
            'categories' => Category::allOrdered(),
            'filters' => [
                'contest_id' => $contestId,
                'category_id' => $categoryId,
                'search' => $search,
            ],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
        ]);
    }

    public function diariesEdit($id)
    {
        $diary = Diary::with(['contest', 'category', 'user'])->find($id);
        if (! $diary) {
            abort(404);
        }

        return view('admin.diaries.edit', [
            'title' => __('Upravit deník'),
            'diary' => $diary,
            'contests' => Contest::orderBy('contest_start', 'desc')->get(),
            'categories' => Category::allOrdered(),
        ]);
    }

    public function diariesUpdate(Request $request, $id)
    {
        Utilities::validateCsrfToken();

        $diary = Diary::find($id);
        if (! $diary) {
            abort(404);
        }

        $messages = Utilities::validatorMessages();
        $messages['url'] = __('Pole :attribute neobsahuje platnou URL adresu.');
        $messages['email'] = __('Pole :attribute obsahuje neplatnou e-mailovou adresu.');
        $messages['min'] = __('Pole :attribute musí být větší nebo rovno :min.');

        $validator = Validator::make($request->all(), [
            'call_sign' => 'required|string|max:255',
            'diary_url' => 'nullable|url|max:255',
            'qth_name' => 'nullable|string|max:255',
            'qth_locator' => 'required|string|max:6',
            'qso_count' => 'required|integer|min:0',
            'score_points' => 'nullable|integer|min:0',
            'email' => 'nullable|email|max:255',
            'category_id' => 'required|integer',
            'contest_id' => 'required|integer',
        ], $messages);

        if ($validator->fails()) {
            Session::flash('errors', $validator->errors()->all());
            return redirect(route('adminDiaryEdit', ['id' => $diary->id]));
        }

        $payload = [
            'call_sign' => trim((string) $request->input('call_sign')),
            'diary_url' => $this->nullableTrim($request->input('diary_url')),
            'qth_name' => $this->nullableTrim($request->input('qth_name')),
            'qth_locator' => strtoupper(trim((string) $request->input('qth_locator'))),
            'qso_count' => (int) $request->input('qso_count'),
            'score_points' => $this->nullableInt($request->input('score_points')),
            'email' => $this->nullableTrim($request->input('email')),
            'category_id' => (int) $request->input('category_id'),
            'contest_id' => (int) $request->input('contest_id'),
        ];

        $original = $diary->only(array_keys($payload));
        $diary->fill($payload);
        $diary->save();

        $this->logAdminAction(
            Auth::user()->email ?? null,
            'diary_update',
            $diary->id,
            $this->diffPayload($original, $payload)
        );

        Session::flash('successes', [__('Uloženo')]);
        return redirect(route('adminDiaryEdit', ['id' => $diary->id]));
    }

    public function diariesDelete(Request $request, $id)
    {
        Utilities::validateCsrfToken();

        $diary = Diary::find($id);
        if (! $diary) {
            abort(404);
        }

        $snapshot = $diary->only([
            'call_sign',
            'qth_name',
            'qth_locator',
            'qso_count',
            'score_points',
            'email',
            'category_id',
            'contest_id',
            'user_id',
        ]);

        $diary->delete();

        $this->logAdminAction(
            Auth::user()->email ?? null,
            'diary_delete',
            $id,
            ['deleted' => $snapshot]
        );

        Session::flash('successes', [__('Deník byl smazán.')]);
        return redirect(route('adminDiaries'));
    }

    private function parseAdminDateTime(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        $value = trim((string) $value);
        $formats = ['Y-m-d\\TH:i', 'Y-m-d H:i'];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value, 'Europe/Prague');
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        return null;
    }

    private function defaultContestDates(): array
    {
        $now = Carbon::now('Europe/Prague');
        $contestStart = $now->copy()->startOfDay();
        $contestEnd = $now->copy()->setTime(23, 59);
        $submissionStart = $contestStart->copy();
        // Conservative default: keep submissions open for a full week after the contest ends.
        $submissionEnd = $contestEnd->copy()->addDays(7);

        return [
            'contest_start' => $contestStart->format('Y-m-d\\TH:i'),
            'contest_end' => $contestEnd->format('Y-m-d\\TH:i'),
            'submission_start' => $submissionStart->format('Y-m-d\\TH:i'),
            'submission_end' => $submissionEnd->format('Y-m-d\\TH:i'),
        ];
    }

    private function logAdminAction(?string $adminEmail, string $action, int $entityId, array $diff): void
    {
        try {
            $payload = [
                'timestamp' => date(DATE_ATOM),
                'admin_email' => $adminEmail,
                'action' => $action,
                'entity_id' => $entityId,
                'diff' => $diff,
            ];

            $line = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($line === false) {
                return;
            }

            file_put_contents(storage_path('logs/admin_actions.log'), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Best effort logging only.
        }
    }

    private function diffPayload(array $original, array $updated): array
    {
        $diff = [];
        foreach ($updated as $key => $value) {
            $before = $original[$key] ?? null;
            if ((string) $before !== (string) $value) {
                $diff[$key] = [
                    'from' => $before,
                    'to' => $value,
                ];
            }
        }

        return $diff;
    }

    private function nullableTrim($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
