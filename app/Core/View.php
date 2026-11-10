<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * Render app/Views/{$template}.php inside app/Views/layouts/{$layout}.php.
     * Templates must escape all dynamic output with e().
     */
    public static function render(string $template, array $data = [], ?string $layout = null): void
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            echo $content;
        } else {
            echo self::partial('layouts/' . $layout, $data + ['content' => $content]);
        }
        Session::clearOld();
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
