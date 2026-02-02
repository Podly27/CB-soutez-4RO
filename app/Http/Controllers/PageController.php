<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Utilities;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PageController extends Controller
{
    public function indexSafe()
    {
        try {
            return $this->index();
        } catch (\Throwable $exception) {
            $errorId = \App\Exceptions\Handler::storeLastException($exception);
            return response('INIT ERROR ' . $errorId, 200, [ 'Content-Type' => 'text/plain' ]);
        }
    }

    public function index()
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            return response()->view('initializing', [
                'message' => 'DB není nastavená nebo není inicializovaná. Dokončete nastavení.',
            ], 200);
        }

        $response = app(IndexController::class)->show(request());
        if (is_array($response)) {
            return response()->json($this->normalizeJsonValue($response));
        }

        return $response;
    }

    public function contact()
    {
        return redirect(route('index') . '#contact-message');
    }

    public function setLocale($lang)
    {
        if (in_array($lang, config('ctvero.locales'))) {
            request()->session()->put('locale', $lang);
            return Utilities::smartRedirect();
        }

        throw new AppException(422, array(__('Neznámá lokalizace')));
    }

    public function profile()
    {
        if (! Auth::check()) {
            throw new UnauthorizedHttpException('');
        }

        return response()->view('profile')->with(['title' => __('Můj profil')]);
    }

    public function termsAndPrivacy()
    {
        return response()->view('terms-and-privacy')->with([
            'title' => __('Podmínky použití a Zásady ochrany osobních údajů'),
        ]);
    }

    private function normalizeJsonValue($value)
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeJsonValue($item);
            }
            return $normalized;
        }

        if ($value instanceof \Closure) {
            return 'closure';
        }

        if (is_resource($value)) {
            return 'resource:' . get_resource_type($value);
        }

        if (is_object($value)) {
            if ($value instanceof \JsonSerializable) {
                return $value;
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            if ($value instanceof \Throwable) {
                return [
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                ];
            }
            return [ 'class' => get_class($value) ];
        }

        return $value;
    }
}
