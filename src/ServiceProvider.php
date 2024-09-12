<?php

namespace EmplifySoftware\StatamicCookieDialog;

use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'cookie-dialog';

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $tags = [
        Tags\CookieDialog::class,
        Tags\Cookie::class,
        Tags\CookieGroups::class,
    ];

    public function bootAddon(): void
    {
        $this->publishes([
            __DIR__.'/../content/cookie-dialog' => base_path('content/cookie-dialog'),
            __DIR__.'/../resources/dist/build' => public_path('vendor/statamic-cookie-dialog'),
        ], 'statamic-cookie-dialog');

        Nav::extend(function ($nav) {
            $nav->create('Cookie Dialog')
                ->section('Tools')
                ->icon('toggle')
                ->route('cookie-dialog.edit');
        });

        Permission::extend(function () {
            Permission::register('manage cookie dialog')->label('Manage Cookie Dialog');
        });
    }
}
