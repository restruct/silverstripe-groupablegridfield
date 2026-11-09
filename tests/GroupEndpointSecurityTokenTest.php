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
 * #18: every group endpoint (getURLHandlers()) refuses a POST without the CSRF token of the form
 * the grid is in, with 400 and before it changes anything, as core's own GridField actions do.
 * Before the fix only canEdit() and the session cookie's SameSite setting stood in the way of a
 * cross-site POST that creates, deletes, renames, reorders or reassigns groups.
 *
 * Compatibility note: runs under PHPUnit 9 (Silverstripe 5) and PHPUnit 11 (Silverstripe 6); no
 * doc-comment metadata and no data providers, so the endpoints are looped over inside each test.
 */
class GroupEndpointSecurityTokenTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableDOSource::class,
        GroupableDOItem::class,
        GroupableSection::class,
    ];

    /** The routes of getURLHandlers(), checked against it in testEveryEndpointIsCoveredHere() */
    private const ENDPOINTS = [
        'group_assignment', 'group_create', 'group_reorder', 'group_action', 'group_delete', 'group_title_update',
    ];

    /** @var GroupableTestController */
    protected $testController;

    /** @var bool Set by the group action registered in buildGrid() */
    protected $actionRan = false;

    protected function setUp(): void
    {
        parent::setUp();
        # Push a controller so GridField::Link() / FieldHolder() resolve (several handlers render the grid)
        $request = new HTTPRequest('GET', '/');
        $request->setSession(new Session([]));
        $this->testController = GroupableTestController::create();
        $this->testController->setRequest($request);
        $this->testController->pushCurrent();
        $this->actionRan = false;
        $this->logInWithPermission('ADMIN');
    }

    protected function tearDown(): void
    {
        $this->testController->popCurrent();
        parent::tearDown();
    }

    /**
     * @return array [GroupableDOSource, GroupableSection, GroupableSection, GroupableDOItem]
     */
    private function createFixture(): array
    {
        $Source = GroupableDOSource::create(['Title' => 'Doc']);
        $Source->write();
        $One = GroupableSection::create(['Name' => 'One', 'Sort' => 1, 'SourceID' => $Source->ID]);
        $One->write();
        $Two = GroupableSection::create(['Name' => 'Two', 'Sort' => 2, 'SourceID' => $Source->ID]);
        $Two->write();
        $Item = GroupableDOItem::create(['Title' => 'Item', 'SortOrder' => 1, 'SectionID' => $One->ID, 'SourceID' => $Source->ID]);
        $Item->write();

        return [$Source, $One, $Two, $Item];
    }

    /**
     * A DataObject-mode grid with every endpoint switched on: a group sort field, an editable
     * title and one group action. OrderableRows is NOT in immediate mode, so group_assignment
     * stops after the assignment instead of handing over to OrderableRows::handleReorder(),
     * which needs the per-row sort inputs and answers 400 of its own without them.
     *
     * @return array [GridField, GridFieldGroupable, Form]
     */
    private function buildGrid(GroupableDOSource $Source): array
    {
        $config = GridFieldConfig_Base::create();
        $config->addComponent(GridFieldOrderableRows::create('SortOrder')->setImmediateUpdate(false));

        $groupable = GridFieldGroupable::create('SectionID', 'Section', 'No section')
            ->setGroupsFromRelation('Sections')
            ->setGroupTitleField('Name')
            ->setGroupSortField('Sort')
            ->setEditableGroupTitle(true)
            ->addGroupAction('probe', 'Probe', 'font-icon-cog', function () {
                $this->actionRan = true;
                return ['success' => true, 'message' => 'ok'];
            });
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return [$grid, $groupable, $form];
    }

    /**
     * One request per endpoint in getURLHandlers(), shaped as groupable.js sends it, plus a check
     * of whether the endpoint changed what it is there to change.
     *
     * @return array<string, array{0: string, 1: callable(): HTTPRequest, 2: callable(): bool}>
     *         handler name => [handler method, request factory, "did it act?" check]
     */
    private function endpoints(GroupableDOSource $Source, GroupableSection $One, GroupableSection $Two, GroupableDOItem $Item): array
    {
        return [
            'group_assignment' => [
                'handleGroupAssignment',
                function () use ($Item, $Two) {
                    return new HTTPRequest('POST', 'group_assignment', [], [
                        'groupable_item_id' => (string) $Item->ID,
                        'groupable_group_key' => (string) $Two->ID,
                    ]);
                },
                function () use ($Item, $Two) {
                    return (int) GroupableDOItem::get()->byID($Item->ID)->SectionID === (int) $Two->ID;
                },
            ],
            'group_create' => [
                'handleGroupCreate',
                function () {
                    return new HTTPRequest('POST', 'group_create', [], [
                        'group_name' => 'Forged',
                        'group_title' => 'Forged',
                    ]);
                },
                function () {
                    return GroupableSection::get()->filter('Name', 'Forged')->exists();
                },
            ],
            'group_reorder' => [
                'handleGroupReorder',
                function () use ($One, $Two) {
                    return new HTTPRequest('POST', 'group_reorder', [], [
                        'group_order' => [(string) $Two->ID, (string) $One->ID],
                    ]);
                },
                function () use ($One, $Two) {
                    return (int) GroupableSection::get()->byID($Two->ID)->Sort
                        < (int) GroupableSection::get()->byID($One->ID)->Sort;
                },
            ],
            'group_action' => [
                'handleGroupAction',
                function () use ($One) {
                    $request = new HTTPRequest('POST', 'group_action', [], []);
                    $request->setRouteParams(['GroupID' => (string) $One->ID, 'ActionName' => 'probe']);
                    return $request;
                },
                function () {
                    return $this->actionRan;
                },
            ],
            'group_delete' => [
                'handleGroupDelete',
                function () use ($Two) {
                    $request = new HTTPRequest('POST', 'group_delete', [], []);
                    $request->setRouteParams(['GroupID' => (string) $Two->ID]);
                    return $request;
                },
                function () use ($Two) {
                    return !GroupableSection::get()->byID($Two->ID);
                },
            ],
            'group_title_update' => [
                'handleGroupTitleUpdate',
                function () use ($One) {
                    # JSON body, as groupable.js sends it: a request var cannot carry the token here
                    $request = new HTTPRequest('POST', 'group_title_update', [], [], json_encode(['title' => 'Renamed']));
                    $request->setRouteParams(['GroupID' => (string) $One->ID]);
                    return $request;
                },
                function () use ($One) {
                    return GroupableSection::get()->byID($One->ID)->Name === 'Renamed';
                },
            ],
        ];
    }

    /**
     * Run one handler and return the HTTP status it ended with (200 when it returned normally).
     */
    private function statusOf(GridFieldGroupable $groupable, string $method, GridField $grid, HTTPRequest $request): int
    {
        try {
            $groupable->$method($grid, $request);
        } catch (HTTPResponse_Exception $e) {
            return $e->getResponse()->getStatusCode();
        }

        return 200;
    }

    public function testEveryEndpointIsCoveredHere(): void
    {
        [$Source, $One, $Two, $Item] = $this->createFixture();
        $handled = array_map(function ($route) {
            # 'POST group_delete/$GroupID' => 'group_delete'
            return explode('/', preg_replace('/^POST\s+/', '', $route))[0];
        }, array_keys((new GridFieldGroupable())->getURLHandlers(null)));

        $this->assertEqualsCanonicalizing($handled, self::ENDPOINTS, 'a new endpoint in getURLHandlers() needs a CSRF case in this test');
        $this->assertEqualsCanonicalizing(
            self::ENDPOINTS,
            array_keys($this->endpoints($Source, $One, $Two, $Item)),
            'every listed endpoint has a request to send'
        );
    }

    public function testRequestWithoutTokenIsRefusedAndChangesNothing(): void
    {
        foreach (self::ENDPOINTS as $name) {
            # A fresh fixture per endpoint, so one endpoint's effect cannot hide another's
            [$Source, $One, $Two, $Item] = $this->createFixture();
            [$grid, $groupable] = $this->buildGrid($Source);
            [$method, $makeRequest, $acted] = $this->endpoints($Source, $One, $Two, $Item)[$name];

            $this->assertSame(400, $this->statusOf($groupable, $method, $grid, $makeRequest()), "$name without a token");
            $this->assertFalse($acted(), "$name without a token changed nothing");
        }
    }

    public function testRequestWithAWrongTokenIsRefused(): void
    {
        foreach (self::ENDPOINTS as $name) {
            [$Source, $One, $Two, $Item] = $this->createFixture();
            [$grid, $groupable] = $this->buildGrid($Source);
            [$method, $makeRequest, $acted] = $this->endpoints($Source, $One, $Two, $Item)[$name];

            $request = $makeRequest();
            $request->addHeader('X-SecurityID', 'not-the-token');
            $this->assertSame(400, $this->statusOf($groupable, $method, $grid, $request), "$name with a wrong token");
            $this->assertFalse($acted(), "$name with a wrong token changed nothing");
        }
    }

    public function testTokenInTheHeaderIsAccepted(): void
    {
        foreach (self::ENDPOINTS as $name) {
            [$Source, $One, $Two, $Item] = $this->createFixture();
            [$grid, $groupable, $form] = $this->buildGrid($Source);
            [$method, $makeRequest, $acted] = $this->endpoints($Source, $One, $Two, $Item)[$name];

            # groupable.js sends the token in this header for every endpoint
            $request = $makeRequest();
            $request->addHeader('X-SecurityID', $form->getSecurityToken()->getValue());
            $this->assertSame(200, $this->statusOf($groupable, $method, $grid, $request), "$name with the token in the header");
            $this->assertTrue($acted(), "$name with the token did its job");
        }
    }

    public function testTokenAsARequestVarIsAccepted(): void
    {
        # A GridField reload posts the whole form, SecurityID field included: group_assignment
        # without immediate update and soft refresh is sent that way
        [$Source, , $Two, $Item] = $this->createFixture();
        [$grid, $groupable, $form] = $this->buildGrid($Source);

        $request = new HTTPRequest('POST', 'group_assignment', [], [
            'groupable_item_id' => (string) $Item->ID,
            'groupable_group_key' => (string) $Two->ID,
            'SecurityID' => $form->getSecurityToken()->getValue(),
        ]);
        $this->assertSame(200, $this->statusOf($groupable, 'handleGroupAssignment', $grid, $request));
        $this->assertSame((int) $Two->ID, (int) GroupableDOItem::get()->byID($Item->ID)->SectionID);
    }

    public function testFormWithItsTokenDisabledIsNotChecked(): void
    {
        # Same rule as core's GridField actions: the form's own token decides, and a form that
        # disabled it (NullSecurityToken) accepts the request
        [$Source] = $this->createFixture();
        [$grid, $groupable, $form] = $this->buildGrid($Source);
        $form->disableSecurityToken();

        $request = new HTTPRequest('POST', 'group_create', [], ['group_name' => 'Allowed']);
        $this->assertSame(200, $this->statusOf($groupable, 'handleGroupCreate', $grid, $request));
        $this->assertTrue(GroupableSection::get()->filter('Name', 'Allowed')->exists());
    }
}
