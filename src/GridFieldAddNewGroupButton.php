<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use Exception;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\View\ArrayData;
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

    public function getHTMLFragments($grid)
    {
        // Check privileges: canWrite on record OR canCreate on gridfieldmodel
        if ($grid->getList() && !$grid->getForm()->getRecord()->canEdit() && !singleton($grid->getModelClass())->canCreate()) {
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
            $groupable->setOption('dividerTemplate', 'GFEnhancedGroupableDivider');
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

        $data = new ArrayData([
            'Title' => ($this->title == _t('GridFieldExtensions.ADD', 'Add') ? $this->title . " $groupLabel" : $this->title),
            'GroupsRelationField' => $this->groupsRelationField,
            'AvailableGroups' => json_encode($groupsFromGrid),
            'UnsavedGroupNotice' => _t(
                'GridFieldExtensions.UnsavedGroupNotice',
                'Sla {record_singular_name} op alvorens {relation_label} aan deze (nieuwe) {group_label} toe te voegen',
                'Save {record_singular_name} before adding {relation_label} to this (unsaved) {group_label}',
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
