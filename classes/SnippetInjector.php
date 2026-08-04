<?php

declare(strict_types=1);

namespace Grav\Plugin\HeaderFooterCode;

use Grav\Common\Assets;
use Grav\Common\Grav;

/**
 * Inject enabled frontend snippets into public HTML output.
 */
class SnippetInjector
{
    public function __construct(
        private readonly SnippetRepository $repository,
        private readonly Grav $grav,
    ) {
    }

    /**
     * Register CSS/JS via the Asset Manager.
     */
    public function registerAssets(): void
    {
        /** @var Assets $assets */
        $assets = $this->grav['assets'];

        foreach ($this->repository->activeForTarget('frontend') as $snippet) {
            $code = trim((string) $snippet['code']);
            if ($code === '') {
                continue;
            }

            $type = $snippet['type'];
            if ($type === 'css') {
                $assets->addInlineCss($code, ['priority' => 50]);
                continue;
            }

            if ($type === 'js') {
                $options = [
                    'group' => $snippet['location'] === 'footer' ? 'bottom' : 'head',
                    'priority' => 50,
                ];
                $loading = $this->loadingAttr((bool) $snippet['defer'], (bool) $snippet['async']);
                if ($loading !== null) {
                    $options['loading'] = $loading;
                }
                $assets->addInlineJs($code, $options);
            }
        }
    }

    /**
     * Inject HTML snippets before </head> / </body>.
     */
    public function injectHtmlIntoOutput(string $html): string
    {
        $header = '';
        $footer = '';

        foreach ($this->repository->activeForTarget('frontend') as $snippet) {
            if ($snippet['type'] !== 'html') {
                continue;
            }
            $code = (string) $snippet['code'];
            if (trim($code) === '') {
                continue;
            }
            if ($snippet['location'] === 'footer') {
                $footer .= "\n" . $code . "\n";
            } else {
                $header .= "\n" . $code . "\n";
            }
        }

        if ($header !== '') {
            $html = $this->insertBefore($html, '</head>', $header);
        }
        if ($footer !== '') {
            $html = $this->insertBefore($html, '</body>', $footer);
        }

        return $html;
    }

    private function loadingAttr(bool $defer, bool $async): ?string
    {
        $parts = [];
        if ($async) {
            $parts[] = 'async';
        }
        if ($defer) {
            $parts[] = 'defer';
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function insertBefore(string $html, string $needle, string $insert): string
    {
        $pos = stripos($html, $needle);
        if ($pos === false) {
            return $html . $insert;
        }

        return substr($html, 0, $pos) . $insert . substr($html, $pos);
    }
}
