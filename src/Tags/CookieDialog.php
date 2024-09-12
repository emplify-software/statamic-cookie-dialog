<?php

namespace EmplifySoftware\StatamicCookieDialog\Tags;

use EmplifySoftware\StatamicCookieDialog\Helpers\CookieDialog as CookieDialogHelper;
use Exception;
use Statamic\Tags\Tags;

use function view;

class CookieDialog extends Tags
{
    protected static $handle = 'cookie_dialog';

    /**
     * Render the cookie dialog.
     *
     * @throws Exception
     */
    public function index()
    {
        $settings = CookieDialogHelper::getContents();

        $cookieConsentData = CookieDialogHelper::getCookieConsentData();
        $dialogEnabled = ! $cookieConsentData || ! CookieDialogHelper::cookieVersionIsUpToDate();

        if ($settings['enabled'] === false) {
            return '';
        } else {
            return view('cookie-dialog::cookie_dialog', ['data' => $settings, 'enabled' => $dialogEnabled]);
        }
    }
}
