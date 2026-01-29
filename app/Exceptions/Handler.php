<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        self::storeLastException($exception);

        if ($exception instanceof UnauthorizedHttpException) {
            return $this->errorJsonOrErrorPageResponse(__('Je vyžadováno přihlášení.'), 401);
        }

        if ($exception instanceof ForbiddenException) {
            return $this->errorJsonOrErrorPageResponse(__('Přístup odepřen.'), 403);
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->errorJsonOrErrorPageResponse(__('Stránka nebyla nalezena.'), 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->errorJsonOrErrorPageResponse(__('Metoda není povolena.'), 405);
        }

        if ($exception instanceof QueryException) {
            return $this->errorJsonOrErrorPageResponse(__('Došlo k chybě!'), 500);
        }

        return parent::render($request, $exception);
    }

    public static function storeLastException(Throwable $exception, $errorId = null)
    {
        $logPath = storage_path('logs/last_exception.txt');
        try {
            if (! $errorId) {
                try {
                    $errorId = bin2hex(random_bytes(4));
                } catch (Throwable $e) {
                    $errorId = uniqid('err_', true);
                }
            }

            $exceptionClass = get_class($exception);
            $message = $exception->getMessage();
            if ($message === '' || $message === null) {
                try {
                    $message = (string) $exception;
                } catch (Throwable $e) {
                    $message = sprintf(
                        'code:%s at %s:%s',
                        $exception->getCode(),
                        $exception->getFile(),
                        $exception->getLine()
                    );
                }
            }

            $trace = $exception->getTraceAsString();
            $payload = sprintf(
                "id: %s\nclass: %s\nmessage: %s\nlocation: %s:%s\ntrace:\n%s",
                $errorId,
                $exceptionClass,
                $message,
                $exception->getFile(),
                $exception->getLine(),
                $trace
            );
            $payload = substr($payload, 0, 2000);

            @file_put_contents($logPath, $payload);
        } catch (Throwable $e) {
            // Ignore logging failures to avoid cascading errors.
        }

        return $errorId;
    }

    public static function errorJsonOrErrorPageResponse($message, $statusCode)
    {
        if (request()->ajax()) {
            return response()->json([ 'errors' => array($message) ])->setStatusCode($statusCode);
        }
        return response(view('error', [ 'msg' => $message ]))->setStatusCode($statusCode);
    }
}
