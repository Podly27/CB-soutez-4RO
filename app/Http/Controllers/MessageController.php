<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Throwable;

use App\Http\Utilities;
use App\Mail\MessageMail;

class MessageController extends Controller
{
    public function send(Request $request)
    {
        try {
            Utilities::validateCsrfToken();
            Utilities::checkRecaptcha();

            $messages = [
                'email' => __('Pole :attribute obsahuje neplatnou e-mailovou adresu.'),
                'required' => __('Pole :attribute je vyžadováno.'),
                'max' => __('Pole :attribute přesahuje povolenou délku :max znaků.'),
            ];
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'email' => 'required|email|max:150',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:2000',
            ], $messages);

            if ($validator->fails()) {
                Session::flash('messageErrors', $validator->errors()->all());
                return redirect(route('index'));
            }

            $payload = [
                'name' => trim((string) $request->input('name')),
                'email' => trim((string) $request->input('email')),
                'subject' => trim((string) $request->input('subject')),
                'message' => trim((string) $request->input('message')),
            ];

            $ownerMail = config('ctvero.ownerMail');
            if (empty($ownerMail)) {
                $exception = new \RuntimeException('Missing CTVERO_OWNER_MAIL config value.');
                $this->storeLastException($exception);
                $this->storeMessageFallback($payload, $request);
                Session::flash('messageErrors', [__('Kontakt není nastaven. Zkuste později.')]);
                return redirect(route('index'));
            }

            try {
                $msg = new MessageMail(
                    $payload['name'],
                    $payload['email'],
                    $payload['subject'],
                    $payload['message']
                );
                Mail::to($ownerMail)->send($msg);
            } catch (Throwable $mailException) {
                $this->storeLastException($mailException);
                $this->storeLastMailError($mailException);
                $this->storeMessageFallback($payload, $request);
                Session::flash('messageErrors', [__('Zprávu se nepodařilo odeslat, zkuste to prosím později.')]);
                return redirect(route('index'));
            }

            Session::flash('messageSuccess', __('Zpráva byla úspěšně odeslána.'));
            return redirect(route('index'));
        } catch (Throwable $e) {
            $this->storeLastException($e);
            Session::flash('messageErrors', [__('Zprávu se nepodařilo odeslat, zkuste to prosím později.')]);
            return redirect(route('index'));
        }
    }

    private function storeLastException(Throwable $exception): void
    {
        $logPath = storage_path('logs/last_exception.txt');
        try {
            $message = $exception->getMessage();
            if ($message === '' || $message === null) {
                $message = sprintf(
                    'code:%s at %s:%s',
                    $exception->getCode(),
                    $exception->getFile(),
                    $exception->getLine()
                );
            }

            $payload = sprintf(
                "class: %s\nmessage: %s\ncode: %s\nlocation: %s:%s\ntrace:\n%s",
                get_class($exception),
                $message,
                $exception->getCode(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
            $payload = substr($payload, 0, 2000);

            @file_put_contents($logPath, $payload);
        } catch (Throwable $logException) {
            // Ignore logging failures to avoid cascading errors.
        }
    }

    private function storeLastMailError(Throwable $exception): void
    {
        $logPath = storage_path('logs/last_mail_error.txt');
        try {
            $message = $exception->getMessage();
            if ($message === '' || $message === null) {
                $message = sprintf(
                    'code:%s at %s:%s',
                    $exception->getCode(),
                    $exception->getFile(),
                    $exception->getLine()
                );
            }

            $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted]', $message);
            if ($message === null) {
                $message = '[redacted]';
            }

            $payload = sprintf(
                "class: %s\nmessage: %s\ncode: %s",
                get_class($exception),
                $message,
                $exception->getCode()
            );
            $payload = substr($payload, 0, 500);

            @file_put_contents($logPath, $payload);
        } catch (Throwable $logException) {
            // Ignore logging failures to avoid cascading errors.
        }
    }

    private function storeMessageFallback(array $payload, Request $request): bool
    {
        $logPath = storage_path('app/messages.log');
        try {
            $record = [
                'timestamp' => date(DATE_ATOM),
                'name' => $payload['name'],
                'email' => $payload['email'],
                'subject' => $payload['subject'],
                'message' => $payload['message'],
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 200),
            ];
            $line = json_encode($record, JSON_UNESCAPED_UNICODE);
            if ($line === false) {
                return false;
            }
            file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            return true;
        } catch (Throwable $e) {
            $this->storeLastException($e);
            return false;
        }
    }
}
