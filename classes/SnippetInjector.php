<?php

declare(strict_types=1);

namespace Grav\Plugin\IntentionHeaderFooterCode;

use Grav\Common\Grav;

/**
 * Inject enabled frontend snippets into public HTML output.
 */
class SnippetInjector
{
    public const MARKER_COMMENT = '<!-- Added by Intention Header Footer Code plugin -->';

    public function __construct(
        private readonly SnippetRepository $repository,
        private readonly Grav $grav,
    ) {
    }

    /**
     * @deprecated Assets are injected via onOutputGenerated so HTML marker comments
     *             can sit above every block. Kept for call-site compatibility.
     */
    public function registerAssets(): void
    {
        // Intentionally empty — see injectIntoOutput().
    }

    /**
     * Inject CSS, JS, and HTML snippets with a marker comment above each block.
     */
    public function injectHtmlIntoOutput(string $html): string
    {
        return $this->injectIntoOutput($html);
    }

    public function injectIntoOutput(string $html): string
    {
        $header = '';
        $footer = '';

        foreach ($this->repository->activeForTarget('frontend') as $snippet) {
            $block = $this->renderBlock($snippet);
            if ($block === null) {
                continue;
            }

            if ($snippet['type'] === 'css' || $snippet['location'] !== 'footer') {
                // CSS always goes in head (even when location is footer).
                $header .= $block;
            } else {
                $footer .= $block;
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

    /**
     * @param array<string, mixed> $snippet
     */
    private function renderBlock(array $snippet): ?string
    {
        $code = (string) $snippet['code'];
        if (trim($code) === '') {
            return null;
        }

        $marker = self::MARKER_COMMENT;
        $type = $snippet['type'];

        if ($type === 'css') {
            return "\n{$marker}\n<style>\n{$code}\n</style>\n";
        }

        if ($type === 'js') {
            $attrs = $this->scriptAttributes((bool) $snippet['defer'], (bool) $snippet['async']);

            return "\n{$marker}\n<script{$attrs}>\n{$code}\n</script>\n";
        }

        if ($type === 'html') {
            return "\n{$marker}\n{$code}\n";
        }

        return null;
    }

    private function scriptAttributes(bool $defer, bool $async): string
    {
        $parts = [];
        if ($async) {
            $parts[] = 'async';
        }
        if ($defer) {
            $parts[] = 'defer';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
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
