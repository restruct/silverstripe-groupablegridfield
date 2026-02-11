<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * Class GridFieldGroupableTest
 */
class GridFieldGroupableTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testGetURLHandlers(): void
    {
        $groupable = new GridFieldGroupable();
        $this->assertIsArray($groupable->getURLHandlers(null));
    }

    public function testGetColumnsHandled(): void
    {
        $groupable = new GridFieldGroupable();
        $this->assertIsArray($groupable->getColumnsHandled(null));
    }

    public function testGetColumnAttributes(): void
    {
        $groupable = new GridFieldGroupable('ID');
        $record = DataObject::create();
        $attributes = $groupable->getColumnAttributes(null, $record, null);
        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('data-groupable-group', $attributes);
    }

    public function testGetColumnMetadata(): void
    {
        $groupable = new GridFieldGroupable();
        $this->assertIsArray($groupable->getColumnMetadata(null, ''));
    }
}