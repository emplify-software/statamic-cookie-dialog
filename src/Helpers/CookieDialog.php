<?php

namespace EmplifySoftware\StatamicCookieDialog\Helpers;

use Exception;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Support\Arr;

class CookieDialog
{
    public const CONTENT_PATH = 'content/cookie-dialog/cookie-dialog.yaml';

    public const SITES_CONTENT_PATH = 'content/cookie-dialog/sites';

    public const BLUEPRINT_PATH = __DIR__.'/../../resources/blueprints/cookie-dialog.yaml';

    /**
     * Fields of a cookie group that can be translated for additional sites.
     */
    public const GROUP_TEXT_FIELDS = ['name', 'description'];

    /**
     * Fields of a cookie that can be translated for additional sites.
     */
    public const COOKIE_TEXT_FIELDS = ['cookie_name', 'cookie_description', 'cookie_lifetime'];

    /**
     * Fields of cookie groups and cookies whose values are always taken from the default site.
     */
    public const STRUCTURE_FIELDS = ['identifier', 'required', 'enabled_default', 'cookie_identifier'];

    /**
     * Get the contents of the cookie settings for the given site (defaults to the current site).
     * Settings of additional sites are merged on top of the settings of the default site.
     *
     * @throws Exception
     */
    public static function getContents(?string $site = null): array
    {
        if (! File::exists(self::getContentPath())) {
            throw new Exception('Cookies content file does not exist at path "'.self::getContentPath().'". Did you run `php artisan vendor:publish --tag=statamic-cookie-dialog`?');
        }

        $origin = YAML::parse(File::get(self::getContentPath()));
        $site ??= Site::current()->handle();

        if (self::isDefaultSite($site)) {
            return $origin;
        }

        $localized = self::getLocalizedValues($site);

        return array_merge(
            $origin,
            Arr::only($localized, self::getLocalizableFields()),
            ['groups' => self::mergeGroups($origin['groups'] ?? [], $localized['groups'] ?? [])],
        );
    }

    /**
     * Get the values that are overridden by an additional site.
     */
    public static function getLocalizedValues(string $site): array
    {
        $path = self::getContentPath($site);

        return File::exists($path) ? (YAML::parse(File::get($path)) ?? []) : [];
    }

    /**
     * Get the path to the cookie settings file of the given site.
     */
    public static function getContentPath(?string $site = null): string
    {
        if ($site === null || self::isDefaultSite($site)) {
            return base_path(self::CONTENT_PATH);
        }

        return base_path(self::SITES_CONTENT_PATH."/{$site}.yaml");
    }

    /**
     * Check if the given site is the default site, which holds the base settings.
     */
    public static function isDefaultSite(string $site): bool
    {
        return ! Site::multiEnabled() || $site === Site::default()->handle();
    }

    /**
     * Get the handles of the top-level fields that can be overridden by additional sites.
     */
    public static function getLocalizableFields(): array
    {
        return self::getBlueprint()->fields()->localizable()->all()->keys()->all();
    }

    /**
     * Merge the translated texts of an additional site into the cookie groups of the default site.
     * The structure (groups, cookies, identifiers, order, toggles) is always taken from the default site.
     */
    public static function mergeGroups(array $originGroups, array $localizedGroups): array
    {
        $localizedGroups = collect($localizedGroups)->keyBy('identifier');

        return collect($originGroups)->map(function ($group) use ($localizedGroups) {
            $localizedGroup = $localizedGroups->get($group['identifier'] ?? null, []);
            $localizedCookies = collect($localizedGroup['cookies'] ?? [])->keyBy('cookie_identifier');

            return array_merge($group, Arr::only($localizedGroup, self::GROUP_TEXT_FIELDS), [
                'cookies' => collect($group['cookies'] ?? [])->map(fn ($cookie) => array_merge(
                    $cookie,
                    Arr::only($localizedCookies->get($cookie['cookie_identifier'] ?? null, []), self::COOKIE_TEXT_FIELDS),
                ))->all(),
            ]);
        })->all();
    }

    /**
     * Reduce the submitted cookie groups of an additional site to the texts that differ from the default site.
     */
    public static function diffGroups(array $originGroups, array $groups): array
    {
        $groups = collect($groups)->keyBy('identifier');

        return collect($originGroups)->map(function ($originGroup) use ($groups) {
            $group = $groups->get($originGroup['identifier'] ?? null, []);
            $cookies = collect($group['cookies'] ?? [])->keyBy('cookie_identifier');

            $cookieDiffs = collect($originGroup['cookies'] ?? [])
                ->map(fn ($originCookie) => [
                    'cookie_identifier' => $originCookie['cookie_identifier'],
                    ...self::diffFields($originCookie, $cookies->get($originCookie['cookie_identifier'], []), self::COOKIE_TEXT_FIELDS),
                ])
                ->filter(fn ($cookie) => count($cookie) > 1)
                ->values()
                ->all();

            return [
                'identifier' => $originGroup['identifier'],
                ...self::diffFields($originGroup, $group, self::GROUP_TEXT_FIELDS),
                ...($cookieDiffs ? ['cookies' => $cookieDiffs] : []),
            ];
        })
            ->filter(fn ($group) => count($group) > 1)
            ->values()
            ->all();
    }

    /**
     * Get the given fields whose values differ from the origin values.
     */
    private static function diffFields(array $origin, array $values, array $fields): array
    {
        return collect($fields)
            ->filter(fn ($field) => array_key_exists($field, $values) && (string) ($values[$field] ?? '') !== (string) ($origin[$field] ?? ''))
            ->mapWithKeys(fn ($field) => [$field => $values[$field]])
            ->all();
    }

    /**
     * Get the blueprint for the cookie settings.
     * For additional sites, the structural fields of the cookie groups are read-only.
     */
    public static function getBlueprint(bool $localized = false): \Statamic\Fields\Blueprint
    {
        $blueprintContents = YAML::parse(File::get(self::BLUEPRINT_PATH));

        if ($localized) {
            $blueprintContents = self::lockStructureFields($blueprintContents);
        }

        // distinct handles, as Statamic caches blueprint contents by handle
        return Blueprint::make($localized ? 'cookie_dialog_localized' : 'cookie_dialog')->setContents($blueprintContents);
    }

    /**
     * Recursively mark the structural fields of the cookie groups as read-only.
     */
    private static function lockStructureFields(array $contents): array
    {
        foreach ($contents as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (isset($value['handle'], $value['field']) && in_array($value['handle'], self::STRUCTURE_FIELDS)) {
                $value['field']['visibility'] = 'read_only';
            }

            if (($value['handle'] ?? null) === 'groups' && isset($value['field'])) {
                $value['field']['instructions'] = __('Cookie groups and cookies are managed on the default site. Only their texts can be translated here.');
            }

            $contents[$key] = self::lockStructureFields($value);
        }

        return $contents;
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

        if (! $cookieConsent) {
            return null;
        }

        $decoded = json_decode($cookieConsent, true);

        return is_array($decoded) ? $decoded : null;
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
