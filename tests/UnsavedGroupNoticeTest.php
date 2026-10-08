<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewGroupButton;
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\i18n\i18n;

/**
 * Issue #15: the notice on a newly added (unsaved) group divider in MultiValue mode had its _t()
 * default and translator comment swapped, so every CMS showed the Dutch text whatever its locale.
 * The default must be English; the Dutch text lives in lang/nl.yml.
 */
class UnsavedGroupNoticeTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableLegacySource::class,
        GroupableLegacyItem::class,
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

    /**
     * Render the MultiValue-mode add-group button and return its data-unsaved-group-notice text.
     */
    private function renderNotice(): string
    {
        $request = new HTTPRequest('GET', '/');
        $request->setSession(new Session([]));
        $this->testController = GroupableTestController::create();
        $this->testController->setRequest($request);
        $this->testController->pushCurrent();

        $Source = GroupableLegacySource::create(['Title' => 'Map']);
        $Source->Sections = ['sec_a' => 'Alpha'];
        $Source->write();

        $config = GridFieldConfig_Base::create();
        $config->addComponent(GridFieldGroupable::create('Section', 'Section', 'Other', null, 'Sections'));
        $button = new GridFieldAddNewGroupButton();
        $config->addComponent($button);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        $html = (string) ($button->getHTMLFragments($grid)['buttons-before-left'] ?? '');
        $this->assertSame(1, preg_match('/data-unsaved-group-notice="([^"]*)"/', $html, $m), 'The notice attribute must be rendered');

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    public function testNoticeDefaultsToEnglish(): void
    {
        $notice = i18n::with_locale('en_US', fn () => $this->renderNotice());

        $singular = strtolower(GroupableLegacySource::singleton()->singular_name());
        $this->assertSame("Save {$singular} before adding items to this (unsaved) section", $notice);
    }

    public function testNoticeIsTranslatedToDutch(): void
    {
        $notice = i18n::with_locale('nl_NL', fn () => $this->renderNotice());

        $singular = strtolower(GroupableLegacySource::singleton()->singular_name());
        $this->assertSame("Sla {$singular} op alvorens items aan deze (nieuwe) section toe te voegen", $notice);
    }
}
