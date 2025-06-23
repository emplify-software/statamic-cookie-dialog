<?php

namespace EmplifySoftware\StatamicCookieDialog\Tags;

use EmplifySoftware\StatamicCookieDialog\Helpers\CookieDialog as CookieDialogHelper;
use Exception;
use Illuminate\Support\Arr;
use Statamic\Facades\Addon;
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

        // Resolve URLs for any entries in the info pages
        if (!empty($settings['info_pages']['privacy_policy_link'])) {
            if (str_starts_with($settings['info_pages']['privacy_policy_link'], 'entry::')) {
                $entryId = substr($settings['info_pages']['privacy_policy_link'], 7);
                $entry = Entry::find($entryId);
                if (!$entry) {
                    $settings['info_pages']['privacy_policy_link'] = '';
                }
                $settings['info_pages']['privacy_policy_link'] = $entry->absoluteUrl();
            }
        }

        if (!empty($settings['info_pages']['imprint_link'])) {
            if (str_starts_with($settings['info_pages']['imprint_link'], 'entry::')) {
                $entryId = substr($settings['info_pages']['imprint_link'], 7);
                $entry = Entry::find($entryId);
                if (!$entry) {
                    $settings['info_pages']['imprint_link'] = '';
                }
                $settings['info_pages']['imprint_link'] = $entry->absoluteUrl();
            }
        }

        $cookieConsentData = CookieDialogHelper::getCookieConsentData();
        $dialogEnabled =
            ! Arr::get($this->params, "hidden", false) && // force hide the dialog
            (! $cookieConsentData || ! CookieDialogHelper::cookieVersionIsUpToDate()); // enable if cookie consent is not set or is outdated

        $addon = Addon::get('emplify-software/statamic-cookie-dialog');
        $isProEdition = $addon->edition() === 'pro';

        if ($settings['enabled'] === false) {
            return '';
        } else {
            return view('cookie-dialog::cookie_dialog', ['data' => $settings, 'enabled' => $dialogEnabled, 'isProEdition' => $isProEdition]);
        }
    }
}
