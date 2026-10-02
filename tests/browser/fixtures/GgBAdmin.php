<?php

namespace Restruct\GgBrowser;

use SilverStripe\Admin\ModelAdmin;

/**
 * BROWSER-TEST FIXTURE ONLY - the CMS screen the specs open: /admin/gg-browser/<tab>, and a source
 * record's edit form under it, which holds the groupable grid (see GgBSection for why this never
 * loads in a real install).
 */
class GgBAdmin extends ModelAdmin
{
    private static $url_segment = 'gg-browser';

    private static $menu_title = 'Groupable browser test';

    # Keyed managed_models (SS5 and SS6): the key becomes the URL segment and the list grid's name.
    private static $managed_models = [
        'do' => ['dataClass' => GgBDOSource::class, 'title' => 'DataObject mode'],
        'mv' => ['dataClass' => GgBMVSource::class, 'title' => 'MultiValue mode'],
    ];
}
