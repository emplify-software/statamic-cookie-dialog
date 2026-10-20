<?php

namespace EmplifySoftware\StatamicCookieDialog\Http\Controllers;

use EmplifySoftware\StatamicCookieDialog\Helpers\CookieDialog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Support\Arr;

class CookieDialogController extends CpController
{
    /**
     * Render the cookies settings page.
     *
     * @throws Exception
     */
    public function edit(Request $request)
    {
        abort_if(Auth::user()->cant('manage cookie dialog'), 403);

        $site = $this->resolveSite($request);
        $isDefaultSite = CookieDialog::isDefaultSite($site);
        $blueprint = CookieDialog::getBlueprint(! $isDefaultSite);

        [$values, $meta] = $this->extractFromFields(CookieDialog::getContents($site), $blueprint);

        if (! $isDefaultSite) {
            // cookie groups are merged per group and cookie, so they can't be synced as a whole field
            [$originValues, $originMeta] = $this->extractFromFields(CookieDialog::getContents(Site::default()->handle()), $blueprint);
            $originValues = Arr::except($originValues, 'groups');
            $originMeta = Arr::except($originMeta, 'groups');

            $localizedFields = array_values(array_intersect(
                array_keys(CookieDialog::getLocalizedValues($site)),
                CookieDialog::getLocalizableFields(),
            ));
        }

        $addon = Addon::get('emplify-software/statamic-cookie-dialog');
        $isProEdition = $addon->edition() === 'pro';

        $viewData = [
            'blueprint' => $blueprint->toPublishArray(),
            'initialValues' => $values,
            'initialMeta' => $meta,
            'site' => $site,
            'originValues' => $originValues ?? null,
            'originMeta' => $originMeta ?? null,
            'localizedFields' => $localizedFields ?? [],
            'localizations' => $this->getLocalizations($site),
            'saveUrl' => $this->siteUrl('cookie-dialog.update', $site),
            'isProEdition' => $isProEdition,
        ];

        if ($request->wantsJson()) {
            return $viewData;
        }

        return Inertia::render('statamic-cookie-dialog::settings', $viewData);
    }

    /**
     * Update the cookies settings.
     *
     * @throws Exception
     */
    public function update(Request $request)
    {
        abort_if(Auth::user()->cant('manage cookie dialog'), 403);

        $site = $this->resolveSite($request);
        $isDefaultSite = CookieDialog::isDefaultSite($site);

        // validate content against blueprint
        $blueprint = CookieDialog::getBlueprint(! $isDefaultSite);
        $fields = $blueprint->fields()->addValues($request->all());
        $fields->validate();

        $groups = collect($request->get('groups'));

        if (! $isDefaultSite && ($error = $this->validateGroupStructure($groups))) {
            return $error;
        }

        // check if no duplicate cookies exist
        $cookies = $groups->pluck('cookies')->flatMap(fn ($fields) => collect($fields)->pluck('cookie_identifier'));
        $cookieDuplicates = $cookies->duplicates();

        if ($cookieDuplicates->isNotEmpty()) {

            $duplicateCookiesByGroup = $groups->map(fn ($group) => collect($group['cookies'])
                ->pluck('cookie_identifier')
                ->filter(fn ($cookie) => $cookieDuplicates->contains($cookie)));

            $cookieErrors = $duplicateCookiesByGroup->mapWithKeys(fn ($cookies, $groupKey) => $cookies->mapWithKeys(fn ($cookie, $cookieKey) => ["groups.{$groupKey}.cookies.{$cookieKey}.cookie_identifier" => ['Duplicate cookie identifier']]));

            return $this->createDuplicateErrorResponse(
                'Duplicate cookie identifiers are not allowed',
                $cookieDuplicates,
                $cookieErrors
            );
        }

        // check if no duplicate cookie groups exist
        $groupIdentifiers = $groups->pluck('identifier');
        $groupDuplicates = $groupIdentifiers->duplicates();

        if ($groupDuplicates->isNotEmpty()) {

            $groupErrors = $groupIdentifiers->filter(fn ($group) => $groupDuplicates->contains($group))
                ->mapWithKeys(fn ($group, $key) => ["groups.{$key}.identifier" => ['Duplicate cookie group identifier']]);

            return $this->createDuplicateErrorResponse(
                'Duplicate cookie group identifiers are not allowed',
                $groupDuplicates,
                $groupErrors
            );
        }

        $values = $request->only($blueprint->fields()->all()->keys()->all());

        // additional sites only store the overridden fields and translated cookie texts
        if (! $isDefaultSite) {
            $localizedFields = array_intersect($request->input('_localized', []), CookieDialog::getLocalizableFields());
            $originGroups = CookieDialog::getContents(Site::default()->handle())['groups'] ?? [];

            $values = array_filter([
                ...Arr::only($values, $localizedFields),
                'groups' => CookieDialog::diffGroups($originGroups, $groups->all()),
            ], fn ($value, $key) => $key !== 'groups' || ! empty($value), ARRAY_FILTER_USE_BOTH);
        }

        $path = CookieDialog::getContentPath($site);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, YAML::dump($values));
    }

    public function createDuplicateErrorResponse($message, $duplicates, $errors)
    {
        return response()->json([
            'message' => "{$message} (".$duplicates->unique()->map(fn ($item) => '"'.$item.'"')->implode(', ').')',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Ensure the cookie groups and cookies of an additional site match the structure of the default site.
     */
    protected function validateGroupStructure($groups)
    {
        $originGroups = collect(CookieDialog::getContents(Site::default()->handle())['groups'] ?? []);

        $structure = fn ($groups) => collect($groups)
            ->mapWithKeys(fn ($group) => [
                $group['identifier'] ?? '' => collect($group['cookies'] ?? [])->pluck('cookie_identifier')->sort()->values()->all(),
            ])
            ->sortKeys()
            ->all();

        if ($structure($groups) === $structure($originGroups)) {
            return null;
        }

        return response()->json([
            'message' => __('Cookie groups and cookies can only be added or removed on the default site.'),
            'errors' => ['groups' => [__('Cookie groups and cookies can only be added or removed on the default site.')]],
        ], 422);
    }

    /**
     * Get the site that is being edited, falling back to the site selected in the control panel.
     */
    protected function resolveSite(Request $request): string
    {
        if (! Site::multiEnabled()) {
            return Site::default()->handle();
        }

        $site = $request->input('site', Site::selected()->handle());

        abort_unless(Site::authorized()->has($site), 404);

        return $site;
    }

    /**
     * Get the sites the current user can edit the cookie settings for.
     */
    protected function getLocalizations(string $activeSite): array
    {
        if (! Site::multiEnabled()) {
            return [];
        }

        return Site::authorized()->map(fn ($site) => [
            'handle' => $site->handle(),
            'name' => $site->name(),
            'active' => $site->handle() === $activeSite,
            'origin' => CookieDialog::isDefaultSite($site->handle()),
            'exists' => true,
            'published' => true,
            'url' => $this->siteUrl('cookie-dialog.edit', $site->handle()),
        ])->values()->all();
    }

    protected function siteUrl(string $route, string $site): string
    {
        return cp_route($route, Site::multiEnabled() ? ['site' => $site] : []);
    }

    protected function extractFromFields(array $values, $blueprint): array
    {
        $fields = $blueprint->fields()->addValues($values)->preProcess();

        return [$fields->values()->all(), $fields->meta()->all()];
    }
}
