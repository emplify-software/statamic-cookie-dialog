<?php

namespace EmplifySoftware\StatamicCookieDialog\Http\Controllers;

use EmplifySoftware\StatamicCookieDialog\Helpers\CookieDialog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Statamic\Facades\Addon;
use Statamic\Facades\YAML;
use Statamic\Http\Controllers\CP\CpController;

class CookieDialogController extends CpController
{
    /**
     * Render the cookies settings page.
     *
     * @throws Exception
     */
    public function edit()
    {
        abort_if(Auth::user()->cant('manage cookie dialog'), 403);

        $values = CookieDialog::getContents();
        $blueprint = CookieDialog::getBlueprint();

        $fields = $blueprint->fields()->addValues($values)->preProcess();

        $addon = Addon::get('emplify-software/statamic-cookie-dialog');
        $isProEdition = $addon->edition() === 'pro';

        return Inertia::render('statamic-cookie-dialog::settings', [
            'blueprint' => $blueprint->toPublishArray(),
            'initialValues' => $fields->values(),
            'initialMeta' => $fields->meta(),
            'isProEdition' => $isProEdition,
        ]);
    }

    /**
     * Update the cookies settings.
     */
    public function update(Request $request)
    {
        abort_if(Auth::user()->cant('manage cookie dialog'), 403);

        // validate content against blueprint
        $blueprint = CookieDialog::getBlueprint();
        $fields = $blueprint->fields()->addValues($request->all());
        $fields->validate();

        // check if no duplicate cookies exist
        $groups = collect($request->get('groups'));
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

        $contents = YAML::dump($request->all());
        $path = CookieDialog::getContentPath();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    public function createDuplicateErrorResponse($message, $duplicates, $errors)
    {
        return response()->json([
            'message' => "{$message} (".$duplicates->unique()->map(fn ($item) => '"'.$item.'"')->implode(', ').')',
            'errors' => $errors,
        ], 422);
    }
}
