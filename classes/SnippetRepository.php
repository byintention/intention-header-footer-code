<?php

declare(strict_types=1);

namespace Grav\Plugin\IntentionHeaderFooterCode;

use Grav\Common\Grav;
use Grav\Common\Yaml;
use Grav\Plugin\Api\Exceptions\NotFoundException;
use Grav\Plugin\Api\Exceptions\ValidationException;

/**
 * Load and persist HFCM-style snippets under user/data/intention-header-footer-code/.
 */
class SnippetRepository
{
    public const TYPES = ['html', 'css', 'js'];
    public const LOCATIONS = ['header', 'footer'];
    public const TARGETS = ['frontend', 'backend', 'both'];

    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            $this->filePath = $filePath;
            return;
        }

        $userDir = defined('USER_DIR') ? rtrim(USER_DIR, '/') : rtrim(Grav::instance()['locator']->findResource('user://', true, true) ?: '', '/');
        $this->filePath = $userDir . '/data/intention-header-footer-code/snippets.yaml';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $snippets = $this->raw()['snippets'] ?? [];
        if (!is_array($snippets)) {
            return [];
        }

        $out = [];
        foreach ($snippets as $id => $snippet) {
            if (!is_array($snippet)) {
                continue;
            }
            $out[] = $this->normalize((string) $id, $snippet);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        $snippets = $this->raw()['snippets'] ?? [];
        if (!is_array($snippets) || !isset($snippets[$id]) || !is_array($snippets[$id])) {
            throw new NotFoundException("Snippet '{$id}' not found.");
        }

        return $this->normalize($id, $snippets[$id]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $data = $this->raw();
        $snippets = is_array($data['snippets'] ?? null) ? $data['snippets'] : [];

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException('Title is required.');
        }

        $id = $this->uniqueId($this->slugify($title), $snippets);
        $snippet = $this->validatePayload($input, true);
        $snippet['updated_at'] = $this->now();

        $snippets[$id] = $snippet;
        $data['snippets'] = $snippets;
        $this->save($data);

        return $this->normalize($id, $snippet);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $id, array $input): array
    {
        $data = $this->raw();
        $snippets = is_array($data['snippets'] ?? null) ? $data['snippets'] : [];

        if (!isset($snippets[$id]) || !is_array($snippets[$id])) {
            throw new NotFoundException("Snippet '{$id}' not found.");
        }

        $merged = array_merge($snippets[$id], $input);
        $snippet = $this->validatePayload($merged, false);
        $snippet['updated_at'] = $this->now();

        $snippets[$id] = $snippet;
        $data['snippets'] = $snippets;
        $this->save($data);

        return $this->normalize($id, $snippet);
    }

    public function delete(string $id): void
    {
        $data = $this->raw();
        $snippets = is_array($data['snippets'] ?? null) ? $data['snippets'] : [];

        if (!isset($snippets[$id])) {
            throw new NotFoundException("Snippet '{$id}' not found.");
        }

        unset($snippets[$id]);
        $data['snippets'] = $snippets;
        $this->save($data);
    }

    /**
     * Enabled snippets for a given inject target (frontend|backend).
     *
     * @return list<array<string, mixed>>
     */
    public function activeForTarget(string $target): array
    {
        if (!in_array($target, ['frontend', 'backend'], true)) {
            throw new ValidationException('Target must be frontend or backend.');
        }

        $out = [];
        foreach ($this->all() as $snippet) {
            if (!$snippet['enabled']) {
                continue;
            }
            $snippetTarget = $snippet['target'];
            if ($snippetTarget === 'both' || $snippetTarget === $target) {
                $out[] = $snippet;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(): array
    {
        if (!is_file($this->filePath)) {
            return ['snippets' => []];
        }

        $parsed = Yaml::parse((string) file_get_contents($this->filePath));
        if (!is_array($parsed)) {
            return ['snippets' => []];
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function save(array $data): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ValidationException('Unable to create snippet data directory.');
        }

        $yaml = Yaml::dump($data, 99, 2);
        if (@file_put_contents($this->filePath, $yaml) === false) {
            throw new ValidationException('Unable to write snippet data file.');
        }
    }

    /**
     * @param array<string, mixed> $snippet
     * @return array<string, mixed>
     */
    private function normalize(string $id, array $snippet): array
    {
        $type = in_array($snippet['type'] ?? '', self::TYPES, true) ? $snippet['type'] : 'html';
        $location = in_array($snippet['location'] ?? '', self::LOCATIONS, true) ? $snippet['location'] : 'header';
        $target = in_array($snippet['target'] ?? '', self::TARGETS, true) ? $snippet['target'] : 'frontend';

        return [
            'id' => $id,
            'title' => (string) ($snippet['title'] ?? $id),
            'enabled' => (bool) ($snippet['enabled'] ?? true),
            'type' => $type,
            'location' => $location,
            'target' => $target,
            'defer' => $type === 'js' ? (bool) ($snippet['defer'] ?? false) : false,
            'async' => $type === 'js' ? (bool) ($snippet['async'] ?? false) : false,
            'code' => (string) ($snippet['code'] ?? ''),
            'updated_at' => (string) ($snippet['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function validatePayload(array $input, bool $requireTitle): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($requireTitle && $title === '') {
            throw new ValidationException('Title is required.');
        }
        if ($title === '') {
            $title = 'Untitled';
        }

        $type = (string) ($input['type'] ?? 'html');
        if (!in_array($type, self::TYPES, true)) {
            throw new ValidationException('Type must be html, css, or js.');
        }

        $location = (string) ($input['location'] ?? 'header');
        if (!in_array($location, self::LOCATIONS, true)) {
            throw new ValidationException('Location must be header or footer.');
        }

        $target = (string) ($input['target'] ?? 'frontend');
        if (!in_array($target, self::TARGETS, true)) {
            throw new ValidationException('Target must be frontend, backend, or both.');
        }

        $enabled = array_key_exists('enabled', $input)
            ? (bool) $input['enabled']
            : true;

        $defer = $type === 'js' ? (bool) ($input['defer'] ?? false) : false;
        $async = $type === 'js' ? (bool) ($input['async'] ?? false) : false;

        return [
            'title' => $title,
            'enabled' => $enabled,
            'type' => $type,
            'location' => $location,
            'target' => $target,
            'defer' => $defer,
            'async' => $async,
            'code' => (string) ($input['code'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $existing
     */
    private function uniqueId(string $base, array $existing): string
    {
        $id = $base !== '' ? $base : 'snippet';
        if (!isset($existing[$id])) {
            return $id;
        }

        $i = 2;
        while (isset($existing[$id . '-' . $i])) {
            $i++;
        }

        return $id . '-' . $i;
    }

    private function slugify(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'snippet';
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
    }
}
