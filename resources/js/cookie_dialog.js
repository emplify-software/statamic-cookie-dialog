const root = document.querySelector("#cookie-dialog").shadowRoot;

let allowDialogClose = false;
let reloadPageAfterClose;

function initCookieModal() {
    // prevent closing the dialog if !allowDialogClose
    cookieDialog.addEventListener('cancel', (event) => {
        if (!allowDialogClose) event.preventDefault();
    });

    // close dialog on click outside if allowDialogClose
    cookieDialog.addEventListener('click', ({target:dialog}) => {
        if (allowDialogClose && dialog.nodeName === 'DIALOG'){
            dialog.close('dismiss')
        }
    })

    // prevent close on escape key
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !allowDialogClose) {
            event.preventDefault();
        }
    });

    // handle close event to prepare dialog for next show
    cookieDialog.addEventListener('close', handleConsentDialogClose);

    // set button listeners
    const acceptAllBtn = cookieDialog.querySelector('#accept-all-btn');
    const savePreferencesBtn = cookieDialog.querySelector('#save-preferences-btn');

    acceptAllBtn.addEventListener('click', () => {
        setConsentCookie(getAllCookieNames(), true);
        cookieDialog.close();
    });

    savePreferencesBtn.addEventListener('click', () => {
        setConsentCookie(getCheckedCookieNames());
        cookieDialog.close();
    });
}

function initGroupToggles() {
    const groups = root.querySelectorAll("[data-cookie-group]");
    for (const group of groups) {
        const toggle = group.querySelector("input[type='checkbox']");
        const cookieToggles = group.querySelectorAll("[data-cookie] input[type='checkbox']");

        for (const cookieToggle of cookieToggles) {
            cookieToggle.addEventListener('change', () => {
                toggle.checked = [...cookieToggles].some(toggle => toggle.checked);
            });
        }

        toggle.addEventListener('change', () => {
            for (const cookieToggle of cookieToggles) {
                cookieToggle.checked = toggle.checked;
            }
        });
    }
}

function showConsentDialog() {
    // update toggle states
    populateCookiePreferences();
    // show dialog and fade in
    cookieDialog.showModal();
    cookieDialog.classList.remove('es-opacity-0');
    cookieDialog.classList.remove('es-translate-y-10');

    // prevent scrolling on body
    document.body.style.overflow = 'hidden';
}

/**
 * Prepare dialog for fade in on next show.
 */
function handleConsentDialogClose() {
    cookieDialog.classList.add('es-opacity-0');
    cookieDialog.classList.add('es-translate-y-10');
    reloadPageAfterClose = undefined;

    // allow scrolling on body
    document.body.style.overflow = 'auto';
}

function getCookiePreferences() {
    try {
        return JSON.parse(getCookie('cookie-consent'));
    }
    catch (e) {
        return null;
    }
}

/**
 * Inject template content and remove template elements based on the allowed cookies and groups.
 * @param allowedCookies
 * @param allowedGroups
 */
function enableDOMSectionsForCookies(allowedCookies, allowedGroups) {
    // load templates that are allowed
    for (const template of document.querySelectorAll(`template[data-requires-cookie], template[data-requires-cookie-group]`)) {
        // check if all required cookies and cookie groups are allowed
        const requiredCookies = template.dataset.requiresCookie?.split('|');
        const requiredGroups = template.dataset.requiresCookieGroup?.split('|');

        const cookiesAllowed = !requiredCookies || requiredCookies.every(cookie => allowedCookies.includes(cookie));
        const groupsAllowed = !requiredGroups || requiredGroups.every(group => allowedGroups.includes(group));

        if (cookiesAllowed && groupsAllowed) {
            template.before(template.content);
            template.remove();
        }
    }

    // remove fallback content for allowed cookies
    for (const container of document.querySelectorAll(`[data-denied-cookie], [data-denied-cookie-group]`)) {
        // check if all denied cookies / cookie groups are now allowed
        const deniedCookies = container.dataset.deniedCookie?.split('|');
        const deniedGroups = container.dataset.deniedCookieGroup?.split('|');

        const cookiesAllowed = !deniedCookies || deniedCookies.every(cookie => allowedCookies.includes(cookie));
        const groupsAllowed = !deniedGroups || deniedGroups.every(group => allowedGroups.includes(group));

        if (cookiesAllowed && groupsAllowed) {
            container.remove();
        }
    }
}

function getAllCookieNames() {
    return getCookieNames("[data-cookie]");
}

function getCheckedCookieNames() {
    return getCookieNames("[data-cookie-group]:has(input:checked) [data-cookie]:has(input:checked)");
}

function getCookieVersion() {
    return root.querySelector('[data-cookie-version]').dataset.cookieVersion;
}

/**
 * Returns true if the cookie dialog is displayed per default on the page.
 * In this state, consent wrappers are still injected on the page and can dynamically load content.
 * @returns
 */
function isInitialConsent() {
    return !!root.querySelector('dialog[data-enabled]');
}


/**
 * Returns an array of cookie names listed in the cookie modal.
 */
function getCookieNames(querySelector) {
    const cookies = root.querySelectorAll(querySelector);
    return Array.from(cookies).map(cookie => cookie.dataset['cookie']);
}

/**
 * Get the cookie groups with their cookies from the DOM (all cookies are set to false).
 */
function getCookieGroups() {
    const groupElements = root.querySelectorAll("[data-cookie-group]");
    const groups = {};
    for (const groupEl of groupElements) {
        const groupIdentifier = groupEl.dataset.cookieGroup;
        const group = {};
        groups[groupIdentifier] = group;

        for (const cookie of groupEl.querySelectorAll("[data-cookie]")) {
            group[cookie.dataset.cookie] = false;
        }
    }

    return groups;
}


/**
 * Updates the consent cookie with the allowed cookies.
 */
function setConsentCookie(allowedCookies, acceptAll = false) {

    const previousAllowedCookies = CookieDialog.allowedCookies;
    const previousConsentVersion = getCookiePreferences()?.version;

    const groups = getCookieGroups();

    // set allowed cookies to true
    for (const [groupIdentifier, group] of Object.entries(groups)) {
        // group without individual cookies - check if checked and add pseudo (*) cookie
        if (Object.keys(group).length === 0) {
            group['*'] = acceptAll || !!root.querySelector(`[data-cookie-group="${groupIdentifier}"]:has(:checked)`);
            continue;
        }

        for (const cookieName of Object.keys(group)) {
            if (allowedCookies.includes(cookieName)) {
                group[cookieName] = true;
            }
        }
    }

    // cookie data object saved in the consent cookie
    const cookieData = {
        cookies: groups,
        timestamp: Date.now(),
        version: getCookieVersion()
    }

    // update consent cookie
    setCookie('cookie-consent', JSON.stringify(cookieData), 365);
    const newAllowedCookies = CookieDialog.allowedCookies;

    // update content only if cookie preferences have changed or the consent version has changed
    if (JSON.stringify(previousAllowedCookies) !== JSON.stringify(newAllowedCookies) || previousConsentVersion != getCookiePreferences()?.version) {
        // page reload preference is overwritten by the showDialog function.
        if (reloadPageAfterClose != undefined)  {
            if (reloadPageAfterClose) location.reload();
        }
        // default behaviour:
        else {
            // always reload page if cookie consent was already set and page reloaded without template wrappers
            if (!isInitialConsent()) location.reload();
            // reload page if previously allowed cookies have been disabled
            else {
                const removedCookies = previousAllowedCookies.filter(cookie => !newAllowedCookies.includes(cookie));
                if (removedCookies.length > 0) location.reload();
            }
        }

        // update content dynamically without page reload
        const allowedGroups = Object.entries(groups).filter(([_, cookies]) => Object.values(cookies).every(allowed => allowed)).map(([group, _]) => group);
        enableDOMSectionsForCookies(allowedCookies, allowedGroups);
    }

}

// ---------------------------------------------------------------------------------------------------
// Cookie Helper Functions
// ---------------------------------------------------------------------------------------------------

function setCookie(name, value, days) {

    let expires = '';
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = `; expires=${date.toUTCString()}; SameSite=Lax`;
    }
    value = encodeURIComponent(value);
    document.cookie = `${name}=${value || ''}${expires}; path=/`;
}

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return decodeURIComponent(match[2]);
    else return null;
}

/**
 * On consent dialog show, populate the group toggles and cookie checkboxes with the current preferences.
 */
function populateCookiePreferences() {
    const cookiePreferences = getCookiePreferences();

    if (!cookiePreferences) return;

    const groups = cookiePreferences.cookies;

    // Go through all groups
    for (const [groupIdentifier, cookies] of Object.entries(groups)) {
        const groupEl = root.querySelector(`[data-cookie-group="${groupIdentifier}"]`);
        // Skip if group is not found
        if (!groupEl) continue;
        const groupToggle = groupEl.querySelector("input[type='checkbox']");

        // Go through all cookies in the group
        for (const [cookieName, isEnabled] of Object.entries(cookies)) {
            const cookieCheckbox = groupEl.querySelector(`[data-cookie="${cookieName}"] input[type='checkbox']`);
            // Check the checkbox if cookie is enabled in preferences
            if (cookieCheckbox) cookieCheckbox.checked = isEnabled;
        }

        // If at least one cookie in the group is enabled, check the group toggle
        groupToggle.checked = Object.values(cookies).some(isEnabled => isEnabled);
    }
}

// ---------------------------------------------------------------------------------------------------
// Init Script
// ---------------------------------------------------------------------------------------------------

// show cookie dialog on page load if enabled
const cookieDialog = root.querySelector('dialog');
if (cookieDialog && cookieDialog.hasAttribute("data-enabled")) showConsentDialog()

initCookieModal();
initGroupToggles();



// ---------------------------------------------------------------------------------------------------
// Public JavaScript API for the cookie consent dialog.
// ---------------------------------------------------------------------------------------------------

/**
 * @typedef {Object} CookiePreferences
 * @property cookies {Object.<string, Object.<string, boolean>>}
 * @property timestamp {number} - Timestamp when the cookie preferences were set.
 * @property version {string} - Version of the cookie preferences.
 */

/**
 * Globally accessible CookieDialog namespace.
 */
const CookieDialog = {

    /**
     * Show the cookie consent dialog.
     * @param {boolean?} reloadPage - Force reload the page after changing the cookie preferences. Per default, the page will be reloaded when necessary to apply the cookie preference changes to content wrapped in {{ cookie:allowed }} or {{ cookie:denied }} tags.
     */
    showDialog: (reloadPage) => {
        allowDialogClose = true;
        reloadPageAfterClose = reloadPage;
        showConsentDialog();
    },

    /**
     * A list of all allowed cookies.
     * @returns {string[]}
     * */
    get allowedCookies() {
        const cookiePreferences = getCookiePreferences();
        return cookiePreferences ?
            Object.entries(cookiePreferences.cookies)
                .flatMap(([_, cookies]) => Object.entries(cookies).filter(([_, allowed]) => allowed).map(([cookie, _]) => cookie)) :
            [];
    },

    /**
     * A list of all allowed cookie groups.
     * @returns {string[]}
     */
    get allowedGroups() {
        const cookiePreferences = getCookiePreferences();
        return cookiePreferences ?
            Object.entries(cookiePreferences.cookies)
                .filter(([_, cookies]) => Object.values(cookies).every(allowed => allowed))
                .map(([group, _]) => group) :
            [];
    },

    /**
     * The user cookie preferences object as stored in the cookie.
     * @returns {CookiePreferences | null }
     */
    get cookiePreferences() {
        return getCookiePreferences();
    }

}

globalThis.CookieDialog = CookieDialog
