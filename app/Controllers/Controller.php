<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'marketing'): void
    {
        View::render($template, $data + ['errors' => self::errors()], $layout);
    }

    /** Redirect back to a form with errors and the user's previous input. */
    public function backWithErrors(string $to, array $errors): never
    {
        Session::keepOld($_POST);
        $_SESSION['_errors'] = $errors;
        redirect($to);
    }

    protected function validate(array $rules): Validator
    {
        return Validator::make($_POST, $rules);
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /** Errors stored by backWithErrors(), consumed once. */
    public static function errors(): array
    {
        $e = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $e;
    }
}
