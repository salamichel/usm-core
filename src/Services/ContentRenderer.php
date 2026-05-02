<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use App\Models\SiteConfig;
use Twig\Error\TwigError;

class ContentRenderer
{
    public static function renderWithConfig(string $content): string
    {
        if (empty(trim($content))) {
            return $content;
        }

        try {
            $twig = View::getInstance();
            $context = ['site_config' => SiteConfig::all()];
            return $twig->createTemplate($content)->render($context);
        } catch (TwigError $e) {
            // Log the error but don't expose it to prevent information leakage
            Logger::errors()->error('Twig rendering error in content', [
                'error' => $e->getMessage(),
                'content' => substr($content, 0, 200),
            ]);

            // In debug mode, show the error. In production, show raw content.
            if (APP_DEBUG) {
                return '<div style="color: red; padding: 10px; background: #fdd;">
                    <strong>Twig Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                </div>' . $content;
            }

            return $content;
        } catch (\Throwable $e) {
            Logger::errors()->error('Unexpected error rendering content with config', [
                'error' => $e->getMessage(),
            ]);
            return $content;
        }
    }
}
