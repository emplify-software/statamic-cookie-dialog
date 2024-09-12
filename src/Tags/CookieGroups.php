<?php

namespace EmplifySoftware\StatamicCookieDialog\Tags;

use EmplifySoftware\StatamicCookieDialog\Helpers\CookieDialog;
use EmplifySoftware\StatamicCookieDialog\Helpers\CookieDialog as CookieDialogHelper;
use Exception;
use Statamic\Support\Arr;
use Statamic\Tags\Tags;

class CookieGroups extends Tags
{
    protected static $handle = 'cookie_groups';

    /**
     * @throws Exception
     */
    public function index() {
        $settings = CookieDialogHelper::getContents();
        return $settings['groups'];
    }
}
