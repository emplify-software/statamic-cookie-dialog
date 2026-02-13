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
        Tags\CookieDialogButton::class,
        Tags\CookieScripts::class,
    ];

    protected $vite = [
        'input' => [
            'resources/css/cookie_banner.css',
            'resources/css/parent.css',
            'resources/js/cookie_dialog.js',

            'resources/js/cp.js',
            'resources/css/cp.css'
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon(): void
    {
        $this->publishes([
            __DIR__.'/../content/cookie-dialog' => base_path('content/cookie-dialog'),
        ], 'statamic-cookie-dialog');

        Nav::extend(function ($nav) {
            $nav->create('Cookie Dialog')
                ->section('Tools')
                ->icon('<svg width="100%" height="100%" viewBox="0 0 640 640" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:1.5;"> <g transform="matrix(1.09084,0,0,1.09084,-29.3443,-29.1895)"> <path d="M312.064,84.946C309.754,69.91 165.762,130.969 140.917,154.785C122.601,172.342 86.692,254.787 79.983,293.464C74.843,323.093 90.943,409.481 109.454,437.358C127.101,463.934 157.629,500.863 208.861,534.791C232.865,550.688 316.062,558.863 349.27,560.021C378.594,561.044 466.697,516.467 494.702,488.088C517.175,465.317 522.031,434.083 552.795,373.544C566.583,346.409 572.895,322.047 560.017,320C464.292,304.785 434.736,229.453 434.602,214.35C434.466,198.986 433.689,201.792 421.724,201.909C356.255,202.547 315.122,104.846 312.064,84.946Z" style="fill:none;stroke:currentColor;stroke-width:31.17px;"/> </g> <g transform="matrix(0.981921,0,0,0.981921,-6.37469,17.8902)"> <circle cx="251.167" cy="391.948" r="40.286" style="fill:none;stroke:currentColor;stroke-width:34.63px;"/> </g> <g transform="matrix(0.981921,0,0,0.981921,33.1826,-140.696)"> <circle cx="251.167" cy="391.948" r="40.286" style="fill:none;stroke:currentColor;stroke-width:34.63px;"/> </g> <g transform="matrix(0.981921,0,0,0.981921,168.328,-3.18542)"> <circle cx="251.167" cy="391.948" r="40.286" style="fill:none;stroke:currentColor;stroke-width:34.63px;"/> </g> </svg>')
                ->route('cookie-dialog.edit')
                ->can('manage cookie dialog');
        });

        Permission::extend(function () {
            Permission::register('manage cookie dialog')->label('Manage Cookie Dialog');
        });
    }
}
