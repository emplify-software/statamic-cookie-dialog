<?php

namespace EmplifySoftware\StatamicCookieDialog\Tags;

use EmplifySoftware\StatamicCookieDialog\Helpers\CookieDialog as CookieDialogHelper;
use Exception;
use Statamic\Tags\Tags;

use function view;

class CookieDialogButton extends Tags
{
    protected static $handle = 'cookie_dialog_button';

    /**
     * Render the cookie dialog button.
     *
     * @throws Exception
     */
    public function index()
    {
        $settings = CookieDialogHelper::getContents();

        if ($settings['button_enabled'] === false) {
            return '';
        } else {
            return view('cookie-dialog::cookie_dialog_button', ['data' => $settings]);
        }
    }
}
