<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;

/**
 * Minimal controller with a url_segment so GridField::Link() / form actions resolve
 * during FieldHolder rendering in tests (a bare Controller raises a url_segment warning,
 * which PHPUnit converts to an exception).
 */
class GroupableTestController extends Controller implements TestOnly
{
    private static $url_segment = 'groupable-test';
}
