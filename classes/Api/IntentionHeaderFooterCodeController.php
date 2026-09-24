<?php

declare(strict_types=1);

namespace Grav\Plugin\IntentionHeaderFooterCode\Api;

use Grav\Plugin\Api\Controllers\AbstractApiController;
use Grav\Plugin\Api\Exceptions\ValidationException;
use Grav\Plugin\Api\Response\ApiResponse;
use Grav\Plugin\IntentionHeaderFooterCode\SnippetRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IntentionHeaderFooterCodeController extends AbstractApiController
{
    private const PERM_READ = 'admin.intention-header-footer-code.read';
    private const PERM_WRITE = 'admin.intention-header-footer-code.write';

    /**
     * GET /intention-header-footer-code/snippets
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERM_READ);

        return ApiResponse::create([
            'snippets' => $this->repository()->all(),
        ]);
    }

    /**
     * POST /intention-header-footer-code/snippets
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERM_WRITE);

        $body = $this->getRequestBody($request);
        $snippet = $this->repository()->create($body);

        return ApiResponse::created(
            $snippet,
            $this->getApiBaseUrl() . '/intention-header-footer-code/snippets/' . $snippet['id'],
        );
    }

    /**
     * GET /intention-header-footer-code/snippets/{id}
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERM_READ);

        $id = $this->requireId($request);
        $snippet = $this->repository()->get($id);

        return ApiResponse::create($snippet);
    }

    /**
     * PATCH /intention-header-footer-code/snippets/{id}
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERM_WRITE);

        $id = $this->requireId($request);
        $body = $this->getRequestBody($request);
        $snippet = $this->repository()->update($id, $body);

        return ApiResponse::create($snippet);
    }

    /**
     * DELETE /intention-header-footer-code/snippets/{id}
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERM_WRITE);

        $id = $this->requireId($request);
        $this->repository()->delete($id);

        return ApiResponse::noContent();
    }

    /**
     * GET /intention-header-footer-code/active?target=frontend|backend
     */
    public function active(ServerRequestInterface $request): ResponseInterface
    {
        // Backend injector runs for any authenticated admin with api.access.
        // Frontend active list is only needed server-side; still gate on read.
        $query = $request->getQueryParams();
        $target = (string) ($query['target'] ?? 'frontend');

        if ($target === 'backend') {
            $this->requirePermission($request, 'api.access');
        } else {
            $this->requirePermission($request, self::PERM_READ);
        }

        return ApiResponse::create([
            'snippets' => $this->repository()->activeForTarget($target),
        ]);
    }

    private function repository(): SnippetRepository
    {
        return new SnippetRepository();
    }

    private function requireId(ServerRequestInterface $request): string
    {
        $id = $this->getRouteParam($request, 'id');
        if ($id === null || $id === '') {
            throw new ValidationException('Snippet id is required.');
        }

        return $id;
    }
}
