<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Utilities;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PageController extends Controller
{
    public function index()
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            return response()->view('initializing', [
                'message' => 'DB není nastavená nebo není inicializovaná. Dokončete nastavení.',
            ], 200);
        }

        return app(IndexController::class)->show(request());
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

        return view('profile')->with(['title' => __('Můj profil')]);
    }

    public function termsAndPrivacy()
    {
        return view('terms-and-privacy')->with([
            'title' => __('Podmínky použití a Zásady ochrany osobních údajů'),
        ]);
    }
}
