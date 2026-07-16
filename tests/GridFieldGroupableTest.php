<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewGroupButton;
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\ORM\DataObject;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class GridFieldGroupableTest
 *
 * Covers both modes against consumer-shaped fixtures:
 * - MultiValue (legacy) mode: FUSE-shaped — groups in a MultiValueField on the source,
 *   membership in a many_many_extraFields column
 * - DataObject mode: ELP-shaped — groups are DataObjects, membership is an FK on the item
 */
class GridFieldGroupableTest extends SapphireTest
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

    /** @var Controller */
    protected $testController;

    protected function setUp(): void
    {
        parent::setUp();
        # Push a controller so GridField::Link() / Form actions resolve during getHTMLFragments/FieldHolder
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

    // ========================================
    // Pre-existing smoke tests
    // ========================================

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

    // ========================================
    // Fixture/grid builders
    // ========================================

    /**
     * FUSE-shaped legacy source: groups sec_a/sec_b in the Sections MultiValueField,
     * two items assigned to sec_a via the many_many extra field.
     *
     * @return array [GroupableLegacySource, GroupableLegacyItem, GroupableLegacyItem]
     */
    protected function createLegacyFixture(): array
    {
        $Source = GroupableLegacySource::create(['Title' => 'Map']);
        $Source->Sections = ['sec_a' => 'Alpha', 'sec_b' => 'Beta'];
        $Source->write();

        $ItemA = GroupableLegacyItem::create(['Title' => 'Doc A']);
        $ItemA->write();
        $ItemB = GroupableLegacyItem::create(['Title' => 'Doc B']);
        $ItemB->write();

        $Source->Items()->add($ItemA, ['Section' => 'sec_a', 'SortOrder' => 1]);
        $Source->Items()->add($ItemB, ['Section' => 'sec_a', 'SortOrder' => 2]);

        return [$Source, $ItemA, $ItemB];
    }

    /**
     * Build a legacy-mode grid + form around the source's Items list.
     *
     * @return array [GridField, GridFieldGroupable, GridFieldOrderableRows]
     */
    protected function buildLegacyGrid(GroupableLegacySource $Source, bool $immediate): array
    {
        $config = GridFieldConfig_Base::create();
        $orderable = GridFieldOrderableRows::create('SortOrder');
        $orderable->setImmediateUpdate($immediate);
        $config->addComponent($orderable);

        $groupable = GridFieldGroupable::create('Section', 'Sectie', 'Overig', null, 'Sections');
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return [$grid, $groupable, $orderable];
    }

    /**
     * ELP-shaped DataObject-mode source: two Section records, two items assigned to the first.
     *
     * @return array [GroupableDOSource, GroupableSection, GroupableSection, GroupableDOItem, GroupableDOItem]
     */
    protected function createDataObjectFixture(): array
    {
        $Source = GroupableDOSource::create(['Title' => 'KK Doc']);
        $Source->write();

        $SectionOne = GroupableSection::create(['Name' => 'Section One', 'Code' => 'S1', 'Sort' => 1, 'SourceID' => $Source->ID]);
        $SectionOne->write();
        $SectionTwo = GroupableSection::create(['Name' => 'Section Two', 'Code' => 'S2', 'Sort' => 2, 'SourceID' => $Source->ID]);
        $SectionTwo->write();

        $ItemA = GroupableDOItem::create(['Title' => 'Q A', 'SortOrder' => 1, 'SectionID' => $SectionOne->ID, 'SourceID' => $Source->ID]);
        $ItemA->write();
        $ItemB = GroupableDOItem::create(['Title' => 'Q B', 'SortOrder' => 2, 'SectionID' => $SectionOne->ID, 'SourceID' => $Source->ID]);
        $ItemB->write();

        return [$Source, $SectionOne, $SectionTwo, $ItemA, $ItemB];
    }

    /**
     * Build a DataObject-mode grid + form around the source's Items list.
     *
     * @return array [GridField, GridFieldGroupable, GridFieldOrderableRows]
     */
    protected function buildDataObjectGrid(GroupableDOSource $Source, bool $immediate): array
    {
        $config = GridFieldConfig_Base::create();
        $orderable = GridFieldOrderableRows::create('SortOrder');
        $orderable->setImmediateUpdate($immediate);
        $config->addComponent($orderable);

        $groupable = GridFieldGroupable::create('SectionID', 'Sectie', 'Geen sectie')
            ->setGroupsFromRelation('Sections')
            ->setGroupTitleField('Name')
            ->setGroupSortField('Sort');
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return [$grid, $groupable, $orderable];
    }

    // ========================================
    // Bug #1: handleSave must derive immediate-mode from OrderableRows
    // ========================================

    public function testHandleSavePersistsDragAssignmentsWhenOrderableNotImmediate(): void
    {
        [$Source, $ItemA] = $this->createLegacyFixture();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, immediate: false);

        # Simulate the per-row hidden inputs the JS writes when deferring to form-save
        $grid->setValue([
            'GridFieldGroupable' => [
                $ItemA->ID => ['Section' => 'sec_b'],
            ],
        ]);

        # The component's own flag stays at its default (true) — pre-fix this skipped the
        # persistence branch and the drag silently reverted (bug #1); it must now follow
        # OrderableRows::getImmediateUpdate() === false
        $this->assertTrue($groupable->immediateUpdate);

        $groupable->handleSave($grid, $Source);

        $this->assertSame(
            'sec_b',
            $Source->Items()->byID($ItemA->ID)->Section,
            'Drag-assignment submitted via per-row hidden input must persist on form save when OrderableRows is not immediate'
        );
    }

    public function testHandleSaveSkipsPerRowPersistenceWhenOrderableImmediate(): void
    {
        [$Source, $ItemA] = $this->createLegacyFixture();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, immediate: true);

        $grid->setValue([
            'GridFieldGroupable' => [
                $ItemA->ID => ['Section' => 'sec_b'],
            ],
        ]);

        $groupable->handleSave($grid, $Source);

        # In immediate mode assignments were already persisted via AJAX — form save must not re-apply
        $this->assertSame('sec_a', $Source->Items()->byID($ItemA->ID)->Section);
    }

    public function testHandleSavePersistsDragAssignmentsInDataObjectMode(): void
    {
        [$Source, , $SectionTwo, $ItemA] = $this->createDataObjectFixture();
        [$grid, $groupable] = $this->buildDataObjectGrid($Source, immediate: false);

        $grid->setValue([
            'GridFieldGroupable' => [
                $ItemA->ID => ['SectionID' => (string) $SectionTwo->ID],
            ],
        ]);

        $groupable->handleSave($grid, $Source);

        $this->assertEquals(
            $SectionTwo->ID,
            GroupableDOItem::get()->byID($ItemA->ID)->SectionID,
            'FK drag-assignment must persist on form save when OrderableRows is not immediate'
        );
    }

    // ========================================
    // Item #4: groups data read from the grid's submitted value
    // ========================================

    public function testHandleSavePersistsSubmittedGroupsFromGridValue(): void
    {
        [$Source] = $this->createLegacyFixture();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, immediate: false);

        # Enhanced divider inputs are namespaced under the grid name: Items[Sections][key][] / [val][]
        # so they arrive inside the grid's own submitted value
        $grid->setValue([
            'Sections' => [
                'key' => ['sec_a', 'sec_b', 'sec_c'],
                'val' => ['Alpha renamed', 'Beta', 'Gamma'],
            ],
        ]);

        $groupable->handleSave($grid, $Source);
        $Source->write();

        $this->assertSame(
            ['sec_a' => 'Alpha renamed', 'sec_b' => 'Beta', 'sec_c' => 'Gamma'],
            GroupableLegacySource::get()->byID($Source->ID)->dbObject('Sections')->getValue(),
            'Groups submitted via grid-namespaced divider inputs must persist to the MultiValueField'
        );
    }

    // ========================================
    // Bug #2: immediate/AJAX assignment path (protected property access fataled)
    // ========================================

    public function testHandleGroupAssignmentImmediateAjaxPersistsAndReorders(): void
    {
        $this->logInWithPermission('ADMIN');
        [$Source, , $SectionTwo, $ItemA, $ItemB] = $this->createDataObjectFixture();
        [$grid, $groupable] = $this->buildDataObjectGrid($Source, immediate: true);

        # Same postVars shape the JS sends (serialized form state): assignment vars + the per-row
        # hidden sort inputs OrderableRows::handleReorder reads ({GridName}[GridFieldEditableColumns][id][SortOrder])
        $request = new HTTPRequest('POST', 'group_assignment', [], [
            'groupable_item_id' => (string) $ItemA->ID,
            'groupable_group_key' => (string) $SectionTwo->ID,
            'Items' => [
                'GridFieldEditableColumns' => [
                    (string) $ItemB->ID => ['SortOrder' => '1'],
                    (string) $ItemA->ID => ['SortOrder' => '2'],
                ],
            ],
        ]);

        # Pre-fix this fataled: direct access to GridFieldOrderableRows::$immediateUpdate (protected)
        $groupable->handleGroupAssignment($grid, $request);

        $this->assertEquals($SectionTwo->ID, GroupableDOItem::get()->byID($ItemA->ID)->SectionID);
        # And the forwarded OrderableRows::handleReorder must have applied the new order
        $this->assertEquals(1, GroupableDOItem::get()->byID($ItemB->ID)->SortOrder);
        $this->assertEquals(2, GroupableDOItem::get()->byID($ItemA->ID)->SortOrder);
    }

    public function testHandleGroupAssignmentImmediateAjaxLegacyExtraFields(): void
    {
        $this->logInWithPermission('ADMIN');
        [$Source, $ItemA, $ItemB] = $this->createLegacyFixture();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, immediate: true);

        $request = new HTTPRequest('POST', 'group_assignment', [], [
            'groupable_item_id' => (string) $ItemA->ID,
            'groupable_group_key' => 'sec_b',
            'Items' => [
                'GridFieldEditableColumns' => [
                    (string) $ItemB->ID => ['SortOrder' => '1'],
                    (string) $ItemA->ID => ['SortOrder' => '2'],
                ],
            ],
        ]);

        $groupable->handleGroupAssignment($grid, $request);

        $this->assertSame(
            'sec_b',
            $Source->Items()->byID($ItemA->ID)->Section,
            'AJAX assignment must persist to the many_many extra field'
        );
    }

    // ========================================
    // Item #8: 'none'/unassigned semantics per mode
    // ========================================

    public function testNoneGroupKeyMapsToEmptyStringInMultiValueMode(): void
    {
        [$Source, $ItemA] = $this->createLegacyFixture();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, immediate: false);

        $request = new HTTPRequest('POST', 'group_assignment', [], [
            'groupable_item_id' => (string) $ItemA->ID,
            'groupable_group_key' => 'none',
        ]);

        $groupable->handleGroupAssignment($grid, $request);

        # The component maps 'none' to '' (empty string); the many_many extra-field DB roundtrip may
        # yield null — both mean "unassigned" (JS normalises via `|| ''`), so assert emptiness
        $Section = $Source->Items()->byID($ItemA->ID)->Section;
        $this->assertEmpty($Section);
        $this->assertNotEquals('sec_a', $Section);
    }

    public function testNoneGroupKeyMapsToNullInDataObjectMode(): void
    {
        [$Source, , , $ItemA] = $this->createDataObjectFixture();
        [$grid, $groupable] = $this->buildDataObjectGrid($Source, immediate: false);

        $request = new HTTPRequest('POST', 'group_assignment', [], [
            'groupable_item_id' => (string) $ItemA->ID,
            'groupable_group_key' => 'none',
        ]);

        $groupable->handleGroupAssignment($grid, $request);

        $this->assertEquals(0, GroupableDOItem::get()->byID($ItemA->ID)->SectionID);
    }

    // ========================================
    // Item #3: render-time divider template resolution
    // ========================================

    public function testDividerStaysPlainWithoutAddGroupButton(): void
    {
        [$Source] = $this->createLegacyFixture();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, immediate: false);

        $fragment = (string) $groupable->getHTMLFragments($grid)['after'];

        $this->assertStringContainsString('groupable_divider_template', $fragment);
        $this->assertStringNotContainsString('group-val', $fragment, 'Without the add-group button the plain display-only divider must render');
        $this->assertSame('multivalue', $grid->getAttribute('data-groupable-mode'));
    }

    public function testEnhancedDividerActivatesWithButtonAddedAfterGroupable(): void
    {
        [$Source] = $this->createLegacyFixture();
        [$grid, $groupable] = $this->buildLegacyGrid($Source, immediate: false);

        # NB added AFTER GridFieldGroupable — pre-fix the activation only worked when the button
        # rendered first (it mutated dividerTemplate from its own getHTMLFragments)
        $grid->getConfig()->addComponent(new GridFieldAddNewGroupButton());

        $fragment = (string) $groupable->getHTMLFragments($grid)['after'];

        $this->assertStringContainsString('group-val', $fragment, 'Enhanced divider (editable name inputs) must activate regardless of component order');
        $this->assertStringContainsString('ss-gridfield-delete-groups-divider', $fragment);
        # Item #4: inputs namespaced under the grid name so they land in $grid->Value()
        $this->assertStringContainsString('name="Items[Sections][key][]"', $fragment);
        $this->assertStringContainsString('name="Items[Sections][val][]"', $fragment);
        # Group identity must be a data-ATTRIBUTE on the divider row (2.4.1): the .data() store is
        # lost on the .clone() used for whole-group drags — without the attribute, a doc dragged
        # into a just-reordered section read an undefined groupKey and was saved unassigned
        $this->assertStringContainsString('data-group-key="{%=o.groupKey%}"', $fragment);
    }

    public function testDataObjectModeUsesDataObjectDivider(): void
    {
        [$Source] = $this->createDataObjectFixture();
        [$grid, $groupable] = $this->buildDataObjectGrid($Source, immediate: true);

        $fragment = (string) $groupable->getHTMLFragments($grid)['after'];

        $this->assertStringContainsString('group-title', $fragment);
        $this->assertSame('dataobject', $grid->getAttribute('data-groupable-mode'));
        # Clone-surviving group identity attributes on the divider row (2.4.1, see enhanced-divider test)
        $this->assertStringContainsString('data-group-key="{%=o.groupKey%}"', $fragment);
        $this->assertStringContainsString('data-group-id="{%=o.groupId%}"', $fragment);
    }

    // ========================================
    // Metadata fields config parsing (2.3.0 feature)
    // ========================================

    public function testSetGroupMetadataFieldsAcceptsFlatAndAssociativeEntries(): void
    {
        [$Source] = $this->createDataObjectFixture();
        [$grid, $groupable] = $this->buildDataObjectGrid($Source, immediate: true);

        $groupable->setGroupMetadataFields([
            'Code' => ['badge' => true, 'copyable' => true],
            'ContentSummary',
        ]);

        $this->assertSame(['Code', 'ContentSummary'], $groupable->getGroupMetadataFields());

        $groupable->getHTMLFragments($grid);
        $metaConfig = json_decode($grid->getAttribute('data-groupable-meta-config') ?? '', true);
        $this->assertSame(['badge' => true, 'copyable' => true], $metaConfig['Code'] ?? null);
    }

    // ========================================
    // Group actions: documented handler signature ($grid, $sourceRecord, $group, $actionData)
    // ========================================

    public function testAddGroupActionHandlerReceivesDocumentedArgumentOrder(): void
    {
        $this->logInWithPermission('ADMIN');
        [$Source, $SectionOne] = $this->createDataObjectFixture();
        [$grid, $groupable] = $this->buildDataObjectGrid($Source, immediate: true);

        $captured = null;
        # README order: addGroupAction($name, $title, $icon, $handler)
        $groupable->addGroupAction('test_act', 'Do Test', 'font-icon-cog', function ($g, $rec, $grp, $actionData) use (&$captured) {
            $captured = [$g, $rec, $grp, $actionData];
            return ['success' => true, 'message' => 'ok'];
        });

        $this->assertSame('font-icon-cog', $groupable->getGroupActions()['test_act']['icon']);
        $this->assertSame('Do Test', $groupable->getGroupActions()['test_act']['title']);

        $request = new HTTPRequest('POST', 'group_action', [], []);
        $request->setRouteParams(['GroupID' => (string) $SectionOne->ID, 'ActionName' => 'test_act']);

        $response = json_decode($groupable->handleGroupAction($grid, $request), true);

        $this->assertTrue($response['success']);
        $this->assertInstanceOf(GroupableDOSource::class, $captured[1], 'Second handler argument must be the SOURCE record');
        $this->assertEquals($Source->ID, $captured[1]->ID);
        $this->assertInstanceOf(GroupableSection::class, $captured[2], 'Third handler argument must be the GROUP');
        $this->assertEquals($SectionOne->ID, $captured[2]->ID);
        $this->assertIsArray($captured[3]);
    }

    // ========================================
    // Group delete: unassign mode reports the correct count
    // ========================================

    public function testHandleGroupDeleteUnassignsItemsAndReportsCount(): void
    {
        $this->logInWithPermission('ADMIN');
        [$Source, $SectionOne, , $ItemA, $ItemB] = $this->createDataObjectFixture();
        [$grid, $groupable] = $this->buildDataObjectGrid($Source, immediate: true);

        $request = new HTTPRequest('POST', 'group_delete', [], []);
        $request->setRouteParams(['GroupID' => (string) $SectionOne->ID]);

        $response = json_decode($groupable->handleGroupDelete($grid, $request), true);

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('2 item(s) unassigned', $response['message']);
        $this->assertEquals(0, GroupableDOItem::get()->byID($ItemA->ID)->SectionID);
        $this->assertEquals(0, GroupableDOItem::get()->byID($ItemB->ID)->SectionID);
        $this->assertNull(GroupableSection::get()->byID($SectionOne->ID));
    }

    // ========================================
    // Item #6: GroupableDataField getValue() semantics
    // ========================================

    public function testGroupableDataFieldGetValueReturnsArrayOrNull(): void
    {
        $Obj = GroupableDataFieldTestObject::create(['Title' => 'Empty']);
        $Obj->write();
        $this->assertNull(
            GroupableDataFieldTestObject::get()->byID($Obj->ID)->dbObject('Data')->getValue(),
            'Empty GroupableDataField must yield null (not a field instance)'
        );

        $Obj->Data = ['sec_a' => 'Alpha'];
        $Obj->write();
        $this->assertSame(
            ['sec_a' => 'Alpha'],
            GroupableDataFieldTestObject::get()->byID($Obj->ID)->dbObject('Data')->getValue(),
            'Populated GroupableDataField must yield the unserialized array'
        );
    }
}
