<?php

namespace EmplifySoftware\StatamicCookieDialog\Helpers;

use Exception;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\YAML;
use Statamic\Support\Arr;

class CookieDialog
{
    public const CONTENT_PATH = 'content/cookie-dialog/cookie-dialog.yaml';

    public const BLUEPRINT_PATH = __DIR__.'/../../resources/blueprints/cookie-dialog.yaml';

    /**
     * Get the contents of the cookie settings.
     *
     * @throws Exception
     */
    public static function getContents(): array
    {
        if (! File::exists(self::getContentPath())) {
            throw new Exception('Cookies content file does not exist at path "'.self::getContentPath().'". Did you run `php artisan vendor:publish --tag=statamic-cookie-dialog`?');
        }

        return YAML::parse(File::get(self::getContentPath()));
    }

    /**
     * Get the path to the cookie settings file.
     */
    public static function getContentPath(): string
    {
        return base_path(self::CONTENT_PATH);
    }

    /**
     * Get the blueprint for the cookie settings.
     */
    public static function getBlueprint(): \Statamic\Fields\Blueprint
    {
        $blueprintContents = YAML::parse(File::get(self::BLUEPRINT_PATH));

        return Blueprint::make()->setContents($blueprintContents);
    }

    /**
     * Get the cookie consent data of the current visitor.
     */
    public static function getCookieConsentData(): ?array
    {
        $cookieConsent = Arr::get($_COOKIE, 'cookie-consent', '');

        if (! json_validate($cookieConsent)) {
            return null;
        }

        return $cookieConsent ? json_decode($cookieConsent, true) : null;
    }

    /**
     * Check if the visitor's cookie version and the version in the cookie settings match.
     *
     * @throws Exception
     */
    public static function cookieVersionIsUpToDate(): bool
    {
        $settings = self::getContents();
        $cookieConsentData = self::getCookieConsentData();
        $consentVersion = Arr::get($cookieConsentData, 'version');
        $currentVersion = Arr::get($settings, 'version');

        return empty($currentVersion) || $currentVersion == $consentVersion;
    }
}
