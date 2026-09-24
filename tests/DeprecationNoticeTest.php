<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use ReflectionProperty;
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Silverstripe 5 only: Controller::has_curr() is deprecated since 5.4.0 (and removed in 6). The
 * legacy-mode save may only reach for it when the grid value carries no groups, so an ordinary
 * save raises no deprecation notice.
 */
class DeprecationNoticeTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableLegacySource::class,
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
    }

    protected function tearDown(): void
    {
        $this->testController->popCurrent();
        parent::tearDown();
    }

    public function testSaveWithGroupsInTheGridValueDoesNotCallHasCurr(): void
    {
        if (!method_exists(Controller::class, 'has_curr')) {
            $this->markTestSkipped('Controller::has_curr() does not exist on this Silverstripe major');
        }

        $Source = GroupableLegacySource::create(['Title' => 'Map']);
        $Source->Sections = ['sec_a' => 'Alpha'];
        $Source->write();

        $config = GridFieldConfig_Base::create();
        $orderable = GridFieldOrderableRows::create('SortOrder');
        $orderable->setImmediateUpdate(true);
        $config->addComponent($orderable);
        $groupable = GridFieldGroupable::create('Section', 'Sectie', 'Overig', null, 'Sections');
        $config->addComponent($groupable);
        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);
        $grid->setValue([
            'Sections' => ['key' => ['sec_a', 'sec_b'], 'val' => ['Alpha', 'Beta']],
        ]);

        # Deprecation collects notices in a private buffer and prints them at shutdown. Enable it,
        # start from an empty buffer, and empty it again afterwards so nothing leaks into the run.
        $buffer = new ReflectionProperty(Deprecation::class, 'userErrorMessageBuffer');
        $buffer->setAccessible(true);
        $wasEnabled = Deprecation::isEnabled();
        $saved = $buffer->getValue();
        $buffer->setValue(null, []);
        Deprecation::enable(true);
        try {
            $groupable->handleSave($grid, $Source);
            $messages = array_column($buffer->getValue(), 'message');
        } finally {
            $buffer->setValue(null, $saved);
            if (!$wasEnabled) {
                Deprecation::disable();
            }
        }

        $this->assertSame(
            [],
            array_values(array_filter($messages, fn ($m) => str_contains($m, 'has_curr'))),
            'Groups arrived in the grid value, so has_curr() must not be called'
        );
        # And the save itself still worked
        $Source->write();
        $this->assertSame(
            ['sec_a' => 'Alpha', 'sec_b' => 'Beta'],
            GroupableLegacySource::get()->byID($Source->ID)->dbObject('Sections')->getValue()
        );
    }
}
