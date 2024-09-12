<?php

namespace EmplifySoftware\StatamicCookieDialog\Tags;

use EmplifySoftware\StatamicCookieDialog\Helpers\CookieDialog;
use Exception;
use Statamic\Support\Arr;
use Statamic\Tags\Tags;

class Cookie extends Tags
{
    protected static $handle = 'cookie';

    public function allowed(): array|string
    {
        return $this->renderContent(
            $this->params->get('cookies') ?? $this->params->get('cookie'),
            $this->params->get('groups') ?? $this->params->get('group'),
            true
        );
    }

    public function denied(): array|string
    {
        return $this->renderContent(
            $this->params->get('cookies') ?? $this->params->get('cookie'),
            $this->params->get('groups') ?? $this->params->get('group'),
            false
        );
    }

    /**
     * Render the content based on the cookie consent.
     * - If this function is run by the `allowed` function, the content will only be rendered if all required groups and cookies are allowed.
     * - Else, if this function is run by the `denied` function, the content will only be rendered if not all required groups and cookies are allowed.
     *
     * @throws Exception
     */
    private function renderContent($cookies, $groups, $allowed = true): array|string
    {
        $cookieConsentData = CookieDialog::getCookieConsentData();

        // render without wrapper if consent cookie already set
        if ($cookieConsentData && CookieDialog::cookieVersionIsUpToDate()) {
            if ($this->validateCookieConsent(Arr::get($cookieConsentData, 'cookies'), $cookies, $groups)) {
                // cookie:allowed - show content if all required cookies are allowed
                if ($allowed) {
                    return $this->parse();
                }
                // cookie:denied - show nothing if not all required cookies are allowed
                else {
                    return '';
                }
            } else {
                // cookie:allowed - show nothing if not all required cookies are allowed
                if ($allowed) {
                    return '';
                }
                // cookie:denied - show content if not all required cookies are allowed
                else {
                    return $this->parse();
                }
            }
        }
        // render with content wrapper
        else {
            $variables = ['cookies' => $cookies, 'groups' => $groups, 'content' => $this->parse()];

            // cookie:allowed - wrap in template if consent cookie not yet set
            if ($allowed) {
                return view('cookie-dialog::cookie_consent_wrapper', $variables);
            }
            // cookie:denied - wrap in container if consent cookie not yet set
            else {
                return view('cookie-dialog::cookie_denied_wrapper', $variables);
            }
        }
    }

    /**
     * Check if there is consent for the combination of required groups and cookies.
     */
    private function validateCookieConsent(mixed $cookieConsentData, ?string $cookies, ?string $groups): bool
    {
        $requiredCookies = $cookies ? explode('|', $cookies) : null;
        $requiredGroups = $groups ? explode('|', $groups) : null;

        // 1. check if any of the required groups are not allowed
        if ($requiredGroups) {
            foreach ($requiredGroups as $requiredGroup) {
                $groupCookies = Arr::get($cookieConsentData, $requiredGroup);

                // group doesn't exist or at least one cookie in the group is not allowed
                if (! $groupCookies || in_array(false, $groupCookies)) {
                    return false;
                }
            }
        }

        // 2. check if any of the required cookies are not allowed
        if ($requiredCookies) {
            $enabledCookies = [];

            // retrieve an array of enabled cookies only
            foreach ($cookieConsentData as $group) {
                foreach ($group as $cookie => $isEnabled) {
                    if ($isEnabled) {
                        $enabledCookies[] = $cookie;
                    }
                }
            }

            // check if all required cookies are enabled
            foreach ($requiredCookies as $requiredCookie) {
                if (! in_array($requiredCookie, $enabledCookies)) {
                    return false;
                }
            }
        }

        // cookie consent is valid
        return true;
    }
}
