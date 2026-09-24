<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewDataObjectGroupButton;
use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewGroupButton;
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use Restruct\Silverstripe\GroupableGridfield\GroupableDataField;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * The code paths that differ between Silverstripe 5 and 6 (3.x line, one codebase for both).
 * Each test names the SS6 change it guards; all of them must pass on BOTH majors.
 */
class CrossMajorCompatTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableLegacySource::class,
        GroupableLegacyItem::class,
        GroupableDOSource::class,
        GroupableDOItem::class,
        GroupableSection::class,
        GroupableDataFieldTestObject::class,
    ];

    /** @var GroupableTestController|null */
    protected $testController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testController = null;
    }

    protected function tearDown(): void
    {
        if ($this->testController) {
            $this->testController->popCurrent();
        }
        parent::tearDown();
    }

    /**
     * Push a controller (GridField::Link() / FieldHolder() need one), optionally carrying POST vars.
     */
    private function pushController(array $postVars = []): GroupableTestController
    {
        $request = new HTTPRequest($postVars ? 'POST' : 'GET', '/', [], $postVars);
        $request->setSession(new Session([]));
        $this->testController = GroupableTestController::create();
        $this->testController->setRequest($request);
        $this->testController->pushCurrent();

        return $this->testController;
    }

    private function createLegacySource(): GroupableLegacySource
    {
        $Source = GroupableLegacySource::create(['Title' => 'Map']);
        $Source->Sections = ['sec_a' => 'Alpha', 'sec_b' => 'Beta'];
        $Source->write();

        return $Source;
    }

    private function buildLegacyGrid(GroupableLegacySource $Source, GroupableTestController $controller, bool $immediate): array
    {
        $config = GridFieldConfig_Base::create();
        $orderable = GridFieldOrderableRows::create('SortOrder');
        $orderable->setImmediateUpdate($immediate);
        $config->addComponent($orderable);

        $groupable = GridFieldGroupable::create('Section', 'Sectie', 'Overig', null, 'Sections');
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($controller, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return [$grid, $groupable];
    }

    private function buildDataObjectGrid(GroupableDOSource $Source, GroupableTestController $controller): array
    {
        $config = GridFieldConfig_Base::create();
        $config->addComponent(GridFieldOrderableRows::create('SortOrder'));

        $groupable = GridFieldGroupable::create('SectionID', 'Sectie', 'Geen sectie')
            ->setGroupsFromRelation('Sections')
            ->setGroupTitleField('Name')
            ->setGroupSortField('Sort');
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($controller, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return [$grid, $groupable];
    }

    /**
     * SS6: multivaluefield 7 types scaffoldFormField(); the old untyped override fataled at class load.
     */
    public function testGroupableDataFieldScaffoldsHiddenDisabledKeyValueField(): void
    {
        $field = GroupableDataField::create('Data')->scaffoldFormField('Groups');

        $this->assertInstanceOf(FormField::class, $field);
        $this->assertSame('Data', $field->getName());
        $this->assertTrue($field->hasClass('groupable-data'));
        $this->assertTrue($field->hasClass('groupable-data-hidden'));
        $this->assertTrue($field->isDisabled(), 'The groups store is edited through the grid, never directly');
    }

    /**
     * SS6: ArrayData moved namespace; the add-group button renders through create_array_data().
     */
    public function testAddNewGroupButtonRendersWithEscapedGroups(): void
    {
        $controller = $this->pushController();
        $Source = $this->createLegacySource();
        [$grid] = $this->buildLegacyGrid($Source, $controller, false);
        $button = new GridFieldAddNewGroupButton();
        $grid->getConfig()->addComponent($button);

        $html = (string) ($button->getHTMLFragments($grid)['buttons-before-left'] ?? '');

        $this->assertStringContainsString('ss-gridfield-add-new-group', $html);
        $this->assertStringContainsString('data-groups-relation-field="Sections"', $html);
        # The JSON map of groups must arrive attribute-escaped (quotes as &quot;)
        $this->assertStringContainsString(
            'data-groups-available="{&quot;sec_a&quot;:&quot;Alpha&quot;,&quot;sec_b&quot;:&quot;Beta&quot;}"',
            $html
        );
        $this->assertStringContainsString('Add Sectie', $html);
    }

    /**
     * SS6: ArrayData moved namespace; the DataObject-mode button renders through create_array_data().
     */
    public function testAddNewDataObjectGroupButtonRenders(): void
    {
        $controller = $this->pushController();
        $this->logInWithPermission('ADMIN');
        $Source = GroupableDOSource::create(['Title' => 'KK Doc']);
        $Source->write();
        [$grid] = $this->buildDataObjectGrid($Source, $controller);
        $button = GridFieldAddNewDataObjectGroupButton::create()->setPlaceholder('Name "quoted"');
        $grid->getConfig()->addComponent($button);

        $html = (string) ($button->getHTMLFragments($grid)['buttons-before-left'] ?? '');

        $this->assertStringContainsString('ss-gridfield-add-new-do-group', $html);
        $this->assertStringContainsString('group_create', $html, 'The create URL must be rendered into data-create-url');
        $this->assertStringContainsString('placeholder="Name &quot;quoted&quot;"', $html);
    }

    /**
     * SS6: SS_List moved namespace; getGroupSortTable() was typed with the SS5 name, so every group
     * reorder threw a TypeError on SS6.
     */
    public function testHandleGroupReorderRewritesSortValues(): void
    {
        $controller = $this->pushController();
        $this->logInWithPermission('ADMIN');
        $Source = GroupableDOSource::create(['Title' => 'KK Doc']);
        $Source->write();
        $One = GroupableSection::create(['Name' => 'One', 'Sort' => 1, 'SourceID' => $Source->ID]);
        $One->write();
        $Two = GroupableSection::create(['Name' => 'Two', 'Sort' => 2, 'SourceID' => $Source->ID]);
        $Two->write();
        [$grid, $groupable] = $this->buildDataObjectGrid($Source, $controller);

        $this->assertSame('GroupableSection', $groupable->getGroupSortTable($Source->Sections()));

        $request = new HTTPRequest('POST', 'group_reorder', [], [
            'group_order' => [(string) $Two->ID, (string) $One->ID],
        ]);
        $response = json_decode($groupable->handleGroupReorder($grid, $request), true);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertEquals(1, GroupableSection::get()->byID($Two->ID)->Sort);
        $this->assertEquals(2, GroupableSection::get()->byID($One->ID)->Sort);
    }

    /**
     * SS6: Controller::has_curr() is removed. The pre-2.4 fallback that reads top-level
     * {groupsField}[key][] request vars must still work through the replacement.
     */
    public function testSubmittedGroupsFallBackToTopLevelRequestVars(): void
    {
        $controller = $this->pushController([
            'Sections' => [
                'key' => ['sec_a', 'sec_c'],
                'val' => ['Alpha', 'Gamma'],
            ],
        ]);
        $Source = $this->createLegacySource();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, $controller, true);
        # Nothing arrives in the grid's own value, so the fallback is the only source
        $grid->setValue(null);

        $groupable->handleSave($grid, $Source);
        $Source->write();

        $this->assertSame(
            ['sec_a' => 'Alpha', 'sec_c' => 'Gamma'],
            GroupableLegacySource::get()->byID($Source->ID)->dbObject('Sections')->getValue()
        );
    }

    /**
     * SS5 + SS6: with no current controller (CLI, a queued job) handleSave must stay silent. A bare
     * Controller::curr() raises E_USER_WARNING on SS5, which PHPUnit 9 turns into an error here.
     */
    public function testHandleSaveWithNoCurrentControllerIsSilent(): void
    {
        $Source = $this->createLegacySource();
        # A controller for the Form, deliberately NOT pushed onto the stack
        $controller = GroupableTestController::create();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, $controller, true);
        $grid->setValue(null);

        $groupable->handleSave($grid, $Source);

        $this->assertSame(
            ['sec_a' => 'Alpha', 'sec_b' => 'Beta'],
            GroupableLegacySource::get()->byID($Source->ID)->dbObject('Sections')->getValue(),
            'Nothing submitted, so the stored groups must be unchanged'
        );
    }
}
