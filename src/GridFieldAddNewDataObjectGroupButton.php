<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use Exception;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\View\ArrayData;

/**
 * Button component for creating new DataObject groups in GridFieldGroupable.
 *
 * Unlike GridFieldAddNewGroupButton (for MultiValueField mode), this component:
 * - Works with DataObject groups via the handleGroupCreate endpoint
 * - Supports custom creation handlers (e.g., FUSE API calls)
 * - Creates groups via AJAX and updates the GridField
 *
 * Usage:
 * ```php
 * $gridField->getConfig()->addComponent(
 *     GridFieldAddNewDataObjectGroupButton::create()
 *         ->setButtonTitle('Add Section')
 * );
 * ```
 */
class GridFieldAddNewDataObjectGroupButton implements GridField_HTMLProvider
{
    /**
     * The fragment to render the button in.
     */
    protected string $fragment = 'buttons-before-left';

    /**
     * Button title/label.
     */
    protected string $title;

    /**
     * Template to use for rendering.
     */
    protected string $template = 'GFAddNewDataObjectGroupButton';

    /**
     * Placeholder text for the group name input.
     */
    protected string $placeholder = 'New group name...';

    /**
     * Whether to show an inline input field or use a modal/popup.
     */
    protected bool $inlineInput = true;

    /**
     * Create a new button component.
     *
     * @param string $fragment The fragment to render in (default: 'buttons-before-left')
     */
    public function __construct(string $fragment = 'buttons-before-left')
    {
        $this->fragment = $fragment;
        $this->title = _t('GridFieldGroupable.AddGroup', 'Add Group');
    }

    /**
     * Static factory method for fluent syntax.
     */
    public static function create(string $fragment = 'buttons-before-left'): self
    {
        return new static($fragment);
    }

    /**
     * Set the button title.
     *
     * @param string $title
     * @return $this
     */
    public function setButtonTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get the button title.
     */
    public function getButtonTitle(): string
    {
        return $this->title;
    }

    /**
     * Set the placeholder text for the input field.
     *
     * @param string $placeholder
     * @return $this
     */
    public function setPlaceholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Get the placeholder text.
     */
    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    /**
     * Set whether to use inline input (true) or modal (false).
     *
     * @param bool $inline
     * @return $this
     */
    public function setInlineInput(bool $inline): self
    {
        $this->inlineInput = $inline;
        return $this;
    }

    /**
     * Check if using inline input.
     */
    public function getInlineInput(): bool
    {
        return $this->inlineInput;
    }

    /**
     * Set the template to use for rendering.
     *
     * @param string $template
     * @return $this
     */
    public function setTemplate(string $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Get the template name.
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Set which fragment to render in.
     *
     * @param string $fragment
     * @return $this
     */
    public function setFragment(string $fragment): self
    {
        $this->fragment = $fragment;
        return $this;
    }

    /**
     * Get the fragment.
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * Return HTML fragments for the GridField.
     *
     * @param GridField $grid
     * @return array
     */
    public function getHTMLFragments($grid)
    {
        // Check privileges
        if ($grid->getList() && !$grid->getForm()?->getRecord()?->canEdit()) {
            return [];
        }

        // Require GridFieldGroupable in DataObject mode
        $groupable = $grid->getConfig()->getComponentByType(GridFieldGroupable::class);
        if (!$groupable) {
            throw new Exception('GridFieldAddNewDataObjectGroupButton requires a GridFieldGroupable component');
        }

        if (!$groupable->isDataObjectMode()) {
            throw new Exception('GridFieldAddNewDataObjectGroupButton requires GridFieldGroupable in DataObject mode (use setGroupsFromRelation())');
        }

        // Get label from groupable config
        $groupLabel = $groupable->getOption('groupFieldLabel') ?? 'Group';

        $data = new ArrayData([
            'Title' => $this->title,
            'GroupLabel' => $groupLabel,
            'Placeholder' => $this->placeholder,
            'InlineInput' => $this->inlineInput,
            'CreateURL' => $grid->Link('group_create'),
        ]);

        return [
            $this->fragment => $data->renderWith($this->template),
        ];
    }
}
