<?php

namespace Restruct\GgBrowser;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * BROWSER-TEST FIXTURE ONLY - lets a spec put its own source record back into the seeded state:
 * GET /admin/gg-reset/reseed?mode=do|mv&title=...&setup=... answers {"id": <new record ID>}.
 *
 * A LeftAndMain because the admin routes those by url_segment with no YAML: the fixtures are copied
 * into app/src/, where no _config is read. LeftAndMain's own access check (CMS access for this
 * section) applies, so only the logged-in admin can call it. See GgBSection for why this never
 * loads in a real install.
 */
class GgBResetAdmin extends LeftAndMain
{
    private static $url_segment = 'gg-reset';

    private static $menu_title = 'Groupable browser reset';

    private static $allowed_actions = ['reseed'];

    public function reseed(HTTPRequest $request): HTTPResponse
    {
        $title = (string) $request->getVar('title');
        $setup = (string) ($request->getVar('setup') ?: 'immediate');
        if ($title === '') {
            return $this->httpError(400, 'title is required');
        }
        $source = $request->getVar('mode') === 'mv'
            ? GgBMVSource::reseed($title, $setup)
            : GgBDOSource::reseed($title, $setup);

        return HTTPResponse::create(json_encode(['id' => $source->ID]))
            ->addHeader('Content-Type', 'application/json');
    }
}
