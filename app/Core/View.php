<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /** Renders app/Views/$template.php inside app/Views/layouts/$layout.php (null = no layout). */
    public static function render(string $template, array $data = [], ?string $layout = 'app'): void
    {
        $content = self::capture($template, $data);
        echo $layout === null ? $content : self::capture('layouts/' . $layout, $data + ['content' => $content]);
        Session::clearOld(); // remembered form input lives for exactly one page view
    }

    private static function capture(string $template, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
