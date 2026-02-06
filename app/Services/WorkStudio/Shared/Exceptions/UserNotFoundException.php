<?php

namespace App\Services\WorkStudio\Shared\Exceptions;

use Exception;

class UserNotFoundException extends Exception
{
    public function __construct(string $username)
    {
        parent::__construct("User not found in WorkStudio: {$username}");
    }
}
