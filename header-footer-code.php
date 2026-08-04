<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Events\PermissionsRegisterEvent;
use Grav\Framework\Acl\PermissionsReader;
use Grav\Plugin\HeaderFooterCode\SnippetInjector;
use Grav\Plugin\HeaderFooterCode\SnippetRepository;
use RocketTheme\Toolbox\Event\Event;

class HeaderFooterCodePlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function autoload(): ClassLoader
    {
        $loader = new ClassLoader();
        $loader->addPsr4('Grav\\Plugin\\HeaderFooterCode\\', __DIR__ . '/classes');
        $loader->register();

        return $loader;
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isCli()) {
            return;
        }

        $this->enable([
            'onApiRegisterRoutes' => ['onApiRegisterRoutes', 0],
            'onApiSidebarItems' => ['onApiSidebarItems', 0],
            'onApiPluginPageInfo' => ['onApiPluginPageInfo', 0],
            'onApiFloatingWidgets' => ['onApiFloatingWidgets', 0],
            PermissionsRegisterEvent::class => ['onRegisterPermissions', 0],
            'onAssetsInitialized' => ['onAssetsInitialized', 0],
            'onOutputGenerated' => ['onOutputGenerated', 0],
        ]);
    }

    public function onRegisterPermissions(PermissionsRegisterEvent $event): void
    {
        $permissions = $event->permissions;
        $permissions->addActions(
            PermissionsReader::fromYaml("plugin://{$this->name}/permissions.yaml")
        );
    }

    public function onApiRegisterRoutes(Event $event): void
    {
        $routes = $event['routes'];
        $controller = \Grav\Plugin\HeaderFooterCode\Api\HeaderFooterCodeController::class;

        $routes->get('/header-footer-code/active', [$controller, 'active']);
        $routes->get('/header-footer-code/snippets', [$controller, 'index']);
        $routes->post('/header-footer-code/snippets', [$controller, 'create']);
        $routes->get('/header-footer-code/snippets/{id}', [$controller, 'show']);
        $routes->patch('/header-footer-code/snippets/{id}', [$controller, 'update']);
        $routes->delete('/header-footer-code/snippets/{id}', [$controller, 'delete']);
    }

    public function onApiSidebarItems(Event $event): void
    {
        $items = $event['items'] ?? [];
        $items[] = [
            'id' => 'header-footer-code',
            'plugin' => 'header-footer-code',
            'label' => 'PLUGIN_HEADER_FOOTER_CODE.SIDEBAR',
            'icon' => 'fa-code',
            'route' => '/plugin/header-footer-code',
            'priority' => 25,
            'authorize' => 'admin.header-footer-code.read',
        ];
        $event['items'] = $items;
    }

    public function onApiPluginPageInfo(Event $event): void
    {
        if (($event['plugin'] ?? null) !== 'header-footer-code') {
            return;
        }

        $event['definition'] = [
            'id' => 'header-footer-code',
            'plugin' => 'header-footer-code',
            'title' => 'Header Footer Code',
            'icon' => 'fa-code',
            'page_type' => 'component',
        ];
    }

    public function onApiFloatingWidgets(Event $event): void
    {
        $widgets = $event['widgets'] ?? [];
        $widgets[] = [
            'id' => 'header-footer-code-injector',
            'plugin' => 'header-footer-code',
            'label' => 'Header Footer Code Injector',
            'icon' => 'code',
            'priority' => 0,
            'autoLoad' => true,
            'showFab' => false,
            'authorize' => 'api.access',
        ];
        $event['widgets'] = $widgets;
    }

    public function onAssetsInitialized(): void
    {
        if ($this->shouldSkipFrontInjection()) {
            return;
        }

        $this->injector()->registerAssets();
    }

    public function onOutputGenerated(): void
    {
        if ($this->shouldSkipFrontInjection()) {
            return;
        }

        $this->grav->output = $this->injector()->injectHtmlIntoOutput((string) $this->grav->output);
    }

    private function injector(): SnippetInjector
    {
        return new SnippetInjector(new SnippetRepository(), $this->grav);
    }

    private function shouldSkipFrontInjection(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $path = (string) ($this->grav['uri']->path() ?? '');
        $adminRoute = rtrim((string) (
            $this->config->get('plugins.admin2.route')
            ?? $this->config->get('plugins.admin.route')
            ?? '/admin'
        ), '/');

        if ($adminRoute !== '' && ($path === $adminRoute || str_starts_with($path, $adminRoute . '/'))) {
            return true;
        }

        $apiRoute = rtrim((string) ($this->config->get('plugins.api.route') ?? '/api'), '/');
        if ($apiRoute !== '' && ($path === $apiRoute || str_starts_with($path, $apiRoute . '/'))) {
            return true;
        }

        return false;
    }
}
