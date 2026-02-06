<?php

namespace App\Services\WorkStudio\Shared\Exceptions;

use Exception;

class WorkStudioApiException extends Exception
{
    public function __construct(string $message, int $code = 500)
    {
        parent::__construct("WorkStudio API error: {$message}", $code);
    }
}
