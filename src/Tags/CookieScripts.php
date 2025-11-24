<?php

namespace EmplifySoftware\StatamicCookieDialog\Tags;

use Exception;
use Statamic\Tags\Tags;

class CookieScripts extends Tags
{
    protected static $handle = 'cookie_scripts';

    /**
     * @throws Exception
     */
    public function index() {
        return view('cookie-dialog::cookie_scripts');
    }
}
