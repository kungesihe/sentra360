<?php
declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message ?: match ($status) {
            400 => 'Bad request',
            403 => 'You do not have permission to access this page.',
            404 => 'Page not found',
            419 => 'Your session expired. Please go back, refresh the page and try again.',
            429 => 'Too many attempts. Please wait a few minutes and try again.',
            default => 'Something went wrong',
        });
    }
}
