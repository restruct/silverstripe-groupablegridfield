<?php

namespace Restruct\Silverstripe\GroupableGridfield\Test;

use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * Class GridFieldGroupableTest
 */
class GridFieldGroupableTest extends SapphireTest
{

    /**
     *
     */
    public function testGetURLHandlers()
    {
        $groupable = new GridFieldGroupable();
        $this->assertInternalType('array', $groupable->getURLHandlers(null));
    }

    /**
     *
     */
    public function testGetColumnsHandled()
    {
        $groupable = new GridFieldGroupable();
        $this->assertInternalType('array', $groupable->getColumnsHandled(null));
    }

    /**
     *
     */
    public function testGetColumnContent()
    {
        $groupable = new GridFieldGroupable();
        $this->assertNull($groupable->getColumnContent(null, null, ''));
    }

    /**
     *
     */
    public function testGetColumnAttributes()
    {
        $groupable = new GridFieldGroupable('ID');
        $record = DataObject::create();
        $attributes = $groupable->getColumnAttributes(null, $record, null);
        $this->assertInternalType('array', $attributes);
        $this->assertArrayHasKey('data-groupable-group', $attributes);
    }

    /**
     *
     */
    public function testGetColumnMetadata()
    {
        $groupable = new GridFieldGroupable();
        $this->assertInternalType('array', $groupable->getColumnMetadata(null,''));
    }
}