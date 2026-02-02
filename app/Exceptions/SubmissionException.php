<?php

namespace App\Exceptions;

use Exception;
use App\Http\Controllers\SubmissionController;

class SubmissionException extends Exception
{
    public function __construct($statusCode, $messages = [], $resetStep = false)
    {
        $this->statusCode = $statusCode;
        $this->messages = $messages;
        $this->resetStep = $resetStep;
    }

    public function render($request)
    {
        $messages = $this->messages;
        if (is_string($messages)) {
            $messages = trim($messages) === '' ? [] : [ $messages ];
        } elseif ($messages === null) {
            $messages = [];
        }
        if ($messages === [] || count($messages) === 0) {
            $messages = [ __('Došlo k chybě při odesílání hlášení. Zkuste to prosím znovu.') ];
        }
        $request->session()->flash('submissionErrors', $messages);
        return response((new SubmissionController)->show($request, $this->resetStep))
                                                  ->setStatusCode($this->statusCode);
    }
}
