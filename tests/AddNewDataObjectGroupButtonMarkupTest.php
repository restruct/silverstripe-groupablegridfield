<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Composer\InstalledVersions;
use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewDataObjectGroupButton;
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Issue #14 (markup half): the inline add-group input group must use the Bootstrap version of the
 * CMS it renders in. The Silverstripe 5 CMS (admin 2) is on Bootstrap 4, which needs the button
 * wrapped in .input-group-append; the Silverstripe 6 CMS (admin 3) is on Bootstrap 5, which has no
 * rules for that wrapper and expects the button as a direct child of .input-group.
 */
class AddNewDataObjectGroupButtonMarkupTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableDOSource::class,
        GroupableDOItem::class,
        GroupableSection::class,
    ];

    /** @var GroupableTestController|null */
    protected $testController;

    protected function tearDown(): void
    {
        if ($this->testController) {
            $this->testController->popCurrent();
            $this->testController = null;
        }
        parent::tearDown();
    }

    private function renderButton(bool $inline = true): string
    {
        $request = new HTTPRequest('GET', '/');
        $request->setSession(new Session([]));
        $this->testController = GroupableTestController::create();
        $this->testController->setRequest($request);
        $this->testController->pushCurrent();
        $this->logInWithPermission('ADMIN');

        $Source = GroupableDOSource::create(['Title' => 'Doc']);
        $Source->write();

        $config = GridFieldConfig_Base::create();
        $config->addComponent(GridFieldOrderableRows::create('SortOrder'));
        $config->addComponent(
            GridFieldGroupable::create('SectionID', 'Section', 'No section')
                ->setGroupsFromRelation('Sections')
                ->setGroupTitleField('Name')
                ->setGroupSortField('Sort')
        );
        $button = GridFieldAddNewDataObjectGroupButton::create()->setInlineInput($inline);
        $config->addComponent($button);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return (string) ($button->getHTMLFragments($grid)['buttons-before-left'] ?? '');
    }

    public function testInputGroupMatchesTheCmsBootstrapVersion(): void
    {
        $html = $this->renderButton();
        $this->assertStringContainsString('ss-gridfield-create-group-btn', $html);

        # Read the major from Composer rather than from the class the implementation keys on, so
        # the test does not just mirror the code under test
        $frameworkMajor = (int) InstalledVersions::getVersion('silverstripe/framework');

        if ($frameworkMajor >= 6) {
            $this->assertStringNotContainsString('input-group-append', $html, 'Bootstrap 5 (SS6 CMS) has no input-group-append');
            $this->assertMatchesRegularExpression(
                '#<div class="input-group">\s*<input[^>]*>\s*<button#',
                $html,
                'On Bootstrap 5 the button must be a direct child of .input-group'
            );
        } else {
            $this->assertMatchesRegularExpression(
                '#<div class="input-group-append">\s*<button#',
                $html,
                'Bootstrap 4 (SS5 CMS) needs the button inside .input-group-append'
            );
        }
    }

    /**
     * Issue #14 (dialog half): without the inline input, the button pointed Bootstrap's modal
     * data-api at #add-group-modal-{id}, which nothing rendered and neither CMS loads. The
     * template now renders the dialog's markup itself (hidden), for client/js/groupable.js to
     * open as a jQuery UI dialog. Whether it opens is the browser suite's job; this pins the markup
     * the script depends on.
     */
    public function testNonInlineButtonRendersItsOwnDialogMarkup(): void
    {
        $html = $this->renderButton(false);

        $this->assertStringContainsString('ss-gridfield-add-group-modal-btn', $html);
        $this->assertStringNotContainsString('ss-gridfield-create-group-btn', $html, 'no inline input group');
        # Bootstrap's modal data-api is not loaded by either CMS, so these attributes open nothing
        $noDataApi = 'no Bootstrap modal data-api: neither CMS loads it';
        $this->assertStringNotContainsString('data-bs-toggle="modal"', $html, $noDataApi);
        $this->assertStringNotContainsString('data-toggle="modal"', $html, $noDataApi);

        $this->assertMatchesRegularExpression(
            '#<div class="ss-gridfield-add-group-dialog" title="Add Group" hidden>.*?'
            . 'class="form-control ss-gridfield-new-group-name".*?'
            . 'class="btn btn-primary ss-gridfield-add-group-dialog-create".*?'
            . 'class="btn btn-outline-secondary ss-gridfield-add-group-dialog-cancel"#s',
            $html,
            'the hidden dialog template holds the name input and its create and cancel buttons'
        );
    }
}
