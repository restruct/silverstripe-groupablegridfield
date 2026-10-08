<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use Exception;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
# ArrayData moved namespace in Silverstripe 6; built via GridFieldGroupable::create_array_data()
//use SilverStripe\View\ArrayData;
use Symbiote\MultiValueField\Fields\KeyValueField;

/**
 * Component to allow adding Groups for grouping objects using GridFieldGroupable
 * Requires a MultiValueField on
 */
class GridFieldAddNewGroupButton
    extends KeyValueField
    implements GridField_HTMLProvider
{

    protected $fragment;

    protected $title;

    protected $template = 'GFAddNewGroupButton';

    /**
     * The database field which specifies the group
     *
     * @see setSortField()
     * @var string
     */
    protected $groupsRelationField;

    /**
     * @param string $fragment the fragment to render the button in
     */
//	public function __construct($groupsRelationField = 'Groups', $fragment = 'buttons-before-left', $title = null, $sourceKeys = array(), $sourceValues = array(), $value=null, $form=null) {
    public function __construct($fragment = 'buttons-before-left')
    {
//        parent::__construct($title, $sourceKeys, $sourceValues, $value, $form);
        $this->fragment = $fragment;
        $this->title = _t('GridFieldExtensions.ADD', 'Add');
    }

    /**
     * Sets a config option.
     *
     * @param string $option [groupUnassignedName, groupFieldLabel, groupField, groupsAvailable]
     * @param mixed $value (string/array)
     * @return GridFieldAddNewGroupButton $this
     */
    public function setOption($option, $value)
    {
        $this->$option = $value;
        return $this;
    }

    /**
     * @param string $option [groupUnassignedName, groupFieldLabel, groupField, groupsAvailable]
     * @return mixed
     */
    public function getOption($option)
    {
        return $this->$option;
    }

    /**
     * Whether the button will actually render for the current user/grid.
     *
     * Also consulted by GridFieldGroupable's render-time divider template resolution, so the
     * enhanced (editable) divider only activates when this button renders too — keeps section
     * editability and the add-button in sync for readonly users.
     */
    public function canRender($grid): bool
    {
        # Check privileges: canWrite on record OR canCreate on gridfieldmodel
        # (null-safe: grids can render without a form/record, e.g. in previews — treat as renderable)
        if ($grid->getList()
            && ($form = $grid->getForm()) && ($record = $form->getRecord())
            && !$record->canEdit() && !singleton($grid->getModelClass())->canCreate()
        ) {
            return false;
        }

        return true;
    }

    public function getHTMLFragments($grid)
    {
        // Check privileges: canWrite on record OR canCreate on gridfieldmodel
        // if ($grid->getList() && !$grid->getForm()->getRecord()->canEdit() && !singleton($grid->getModelClass())->canCreate()) {  // old: inline check, not null-safe on getForm()/getRecord()
        if (!$this->canRender($grid)) {
            return [];
        }

//		$groupsRelationFieldID = '';
//        if(($fields = $grid->rootFieldList()) && ($groupsField = $fields->dataFieldByName($this->groupsRelationField)) ) {
//            $groupsRelationFieldID = $groupsField->ID();
//        }

        if (!$groupable = $grid->getConfig()->getComponentByType(GridFieldGroupable::class)) {
            throw new Exception('GridFieldAddNewGroupButton requires the GridFieldGroupable component');
        } else {
            $groupLabel = $groupable->getOption('groupFieldLabel');
            $this->groupsRelationField = $groupable->getOption('groupsFieldOnSource');
            // $groupable->setOption('dividerTemplate', 'GFEnhancedGroupableDivider');  // old: order-dependent (only worked when this button rendered BEFORE GridFieldGroupable) and clobbered custom templates — GridFieldGroupable now resolves the enhanced divider itself at render time via canRender()
//            die($groupable->getOption('dividerTemplate'));
        }
        if (!$this->groupsRelationField) {
            throw new Exception('GridFieldAddNewGroupButton requires the GridFieldGroupable component to have groupsFieldOnSource set');
        }

        // set groups on button to allow dynamic updating on creation of new groups (normally taken from gridfield itself, but that doesnt get reloaded when adding new groups)
        $record = null;
        $groupsFromGrid = $groupable->getOption('groupsAvailable');
        if (!$groupsFromGrid && $this->groupsRelationField && ($form = $grid->getForm()) && ($record = $form->getRecord())) { //&& $record->hasDatabaseField($groups)
            $groupsFromGrid = $record->dbObject($this->groupsRelationField)->getValues();
        }

        // $data = new ArrayData([  // old: SS5-only class name
        $data = GridFieldGroupable::create_array_data([
            'Title' => ($this->title == _t('GridFieldExtensions.ADD', 'Add') ? $this->title . " $groupLabel" : $this->title),
            'GroupsRelationField' => $this->groupsRelationField,
            'AvailableGroups' => json_encode($groupsFromGrid),
            # _t() takes (entity, default, translator comment, injection): the default must be the
            # English text, the module ships no en.yml. The Dutch text was passed as the default (and
            # the English as the comment), so every CMS showed Dutch whatever its locale (#15); the
            # Dutch now lives in lang/nl.yml under the same entity. The key stays as it was so a
            # project's own translation of it keeps working.
            'UnsavedGroupNotice' => _t(
                'GridFieldExtensions.UnsavedGroupNotice',
                'Save {record_singular_name} before adding {relation_label} to this (unsaved) {group_label}',
                'Notice on a newly added group divider; the record must be saved before items can be put in it',
                [
                    'record_singular_name' => strtolower($record ? $record->singular_name(): 'record'),
                    'relation_label' => strtolower($grid ? $grid->Title(): 'items'),
                    'group_label' => strtolower($groupLabel),
                ]),
        ]);

        return [
            $this->fragment => $data->renderWith($this->template)
        ];
    }

}
