<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * handleGroupAssignment()'s boundary-drag branch (legacy mode) writes the SOURCE record, so it must
 * check canEdit() on that record, as every other handler does - not only the item class.
 */
class BoundaryDragPermissionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableLegacySource::class,
        GroupableLockedLegacySource::class,
        GroupableLegacyItem::class,
    ];

    /** @var GroupableTestController */
    protected $testController;

    protected function setUp(): void
    {
        parent::setUp();

        $request = new HTTPRequest('GET', '/');
        $request->setSession(new Session([]));
        $this->testController = GroupableTestController::create();
        $this->testController->setRequest($request);
        $this->testController->pushCurrent();

        $this->logInWithPermission('ADMIN');
    }

    protected function tearDown(): void
    {
        $this->testController->popCurrent();
        parent::tearDown();
    }

    private function dragBoundary(GroupableLegacySource $Source): void
    {
        $config = GridFieldConfig_Base::create();
        $orderable = GridFieldOrderableRows::create('SortOrder');
        $orderable->setImmediateUpdate(true);
        $config->addComponent($orderable);
        $groupable = GridFieldGroupable::create('Section', 'Sectie', 'Overig', null, 'Sections');
        $config->addComponent($groupable);
        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        # No item id: the JS sends this when a group boundary (divider) was dragged, with the groups
        # in the grid-namespaced divider inputs
        $request = new HTTPRequest('POST', 'group_assignment', [], [
            'groupable_item_id' => '',
            'groupable_group_key' => '',
            'Items' => [
                'Sections' => ['key' => ['sec_a', 'sec_z'], 'val' => ['Alpha', 'Injected']],
            ],
        ]);
        $groupable->handleGroupAssignment($grid, $request);
    }

    public function testBoundaryDragOnAnEditableSourceWritesTheGroups(): void
    {
        $Source = GroupableLegacySource::create(['Title' => 'Map']);
        $Source->Sections = ['sec_a' => 'Alpha'];
        $Source->write();

        $this->dragBoundary($Source);

        $this->assertSame(
            ['sec_a' => 'Alpha', 'sec_z' => 'Injected'],
            GroupableLegacySource::get()->byID($Source->ID)->dbObject('Sections')->getValue()
        );
    }

    public function testBoundaryDragOnANonEditableSourceIsRefused(): void
    {
        $Source = GroupableLockedLegacySource::create(['Title' => 'Locked']);
        $Source->Sections = ['sec_a' => 'Alpha'];
        $Source->write();

        $status = null;
        try {
            $this->dragBoundary($Source);
        } catch (HTTPResponse_Exception $e) {
            $status = $e->getResponse()->getStatusCode();
        }

        $this->assertSame(403, $status, 'A boundary drag must be refused when the source record is not editable');
        $this->assertSame(
            ['sec_a' => 'Alpha'],
            GroupableLockedLegacySource::get()->byID($Source->ID)->dbObject('Sections')->getValue(),
            'The source record must be left unchanged'
        );
    }
}
