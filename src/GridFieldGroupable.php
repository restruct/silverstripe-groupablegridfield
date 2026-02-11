<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_SaveHandler;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\MultiValueField\Fields\KeyValueField;

class GridFieldGroupable
    extends RequestHandler
    implements GridField_HTMLProvider,
        GridField_ColumnProvider,
        GridField_URLHandler,
        GridField_SaveHandler
{

    private static $allowed_actions = [
        'handleGroupAssignment',
    ];

    /**
     * The field on subjects to hold group key
     *
     * @var string
     */
    protected $groupField;

    /**
     * The label for field on subjects to hold group key
     *
     * @var string
     */
    protected $groupFieldLabel;

    /**
     * fallback/unassigned group name
     *
     * @var string
     */
    protected $groupUnassignedName;

    /**
     * The list of available groups (key/value) or null if providing $groupsFieldNameOnSource
     *
     * @var array
     */
    protected $groupsAvailable;

    /**
     * The database field on the source record which provides the groups (MultiValueField)
     *
     * @see setSortField()
     * @var string
     */
    protected $groupsFieldOnSource;

    /**
     * If true, group assignments are saved immediately via AJAX.
     * If false, they are saved when the form is submitted.
     */
    public bool $immediateUpdate = true;

    /**
     * The row template to render this with
     *
     * @var string
     */
    protected $dividerTemplate = 'GFGroupableDivider';

    /**
     * @param string $groupField field on subjects to hold group key
     * @param string $groupFieldLabel label for field on subjects to hold group key
     * @param string $groupUnassignedName fallback/unassigned group name
     * @param array $groupsAvailable list of groups (key value)
     * @param string $groupsFieldOnSource MultiValue field on source record to provide groups
     */
    public function __construct(
        $groupFieldOnSubject = 'Group',
        $groupFieldLabel = 'Group',
        $groupUnassignedName = '[none/inactive]',
        $groupsAvailable = [],
        $groupsFieldOnSource = null
    )
    {
        parent::__construct();
        $this->groupField = $groupFieldOnSubject;
        $this->groupFieldLabel = $groupFieldLabel;
        $this->groupUnassignedName = $groupUnassignedName;
        $this->groupsAvailable = $groupsAvailable;
        $this->groupsFieldOnSource = $groupsFieldOnSource;
    }

    /**
     * Sets a config option.
     *
     * @param string $option [groupUnassignedName, groupFieldLabel, groupField, groupsAvailable]
     * @param mixed $value (string/array)
     * @return GridFieldGroupable $this
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
     * Convenience function to have the requirements included
     */
    public static function include_requirements()
    {
        Requirements::javascript('symbiote/silverstripe-gridfieldextensions:javascript/tmpl.js');
        Requirements::javascript('restruct/silverstripe-groupable-gridfield:client/js/groupable.js');
        Requirements::css('restruct/silverstripe-groupable-gridfield:client/css/groupable.css');

    }

    public function getURLHandlers($grid)
    {
        return [
            'POST group_assignment' => 'handleGroupAssignment',
        ];
    }

    /**
     * @param GridField $field
     */
    public function getHTMLFragments($grid)
    {

        if (!$grid->getConfig()->getComponentByType(GridFieldOrderableRows::class)) {
            user_error("GridFieldGroupable requires a GridFieldOrderableRows component", E_USER_WARNING);
        }

        self::include_requirements();

        // set ajax urls / vars
        $grid->addExtraClass('ss-gridfield-groupable');
        $grid->setAttribute('data-url-group-assignment', $grid->Link('group_assignment'));
        // setoptions [groupUnassignedName, groupFieldLabel, groupField, groupsAvailable]
        $grid->setAttribute('data-groupable-unassigned', $this->getOption('groupUnassignedName'));
        $grid->setAttribute('data-groupable-role', $this->getOption('groupFieldLabel'));
        $grid->setAttribute('data-groupable-itemfield', $this->getOption('groupField'));

        // Get groups from source record if string MultiValueField name
//        $grid->setAttribute('data-groupable-groups', json_encode( $this->getOption('groupsAvailable') ) );
        $groups = $this->getOption('groupsAvailable');
        if (!$groups && $this->groupsFieldOnSource && ($form = $grid->getForm()) && ($record = $form->getRecord())) { //&& $record->hasDatabaseField($groups)
            $groups = $record->dbObject($this->groupsFieldOnSource)->getValues();
        }
        $grid->setAttribute('data-groupable-groups', json_encode($groups));

        // insert divider js tmpl
        $groupsField = (is_string($this->getOption('groupsFieldOnSource')) ? $this->getOption('groupsFieldOnSource') : '');
        $data = new ArrayData([
            'ColSpan' => $grid->getColumnCount() - 1,
            'GroupFieldLabel' => $this->groupFieldLabel,
            'GroupsFieldNameOnSource' => $groupsField,
        ]);

        return [
            'after' => $data->renderWith($this->dividerTemplate)
        ];

    }

    /**
     * Handles requests to assign a new block area to a block item
     *
     * @param GridField $grid
     * @param HTTPRequest $request
     * @return string
     */
    public function handleGroupAssignment($grid, $request)
    {
        $list = $grid->getList();

        // (copied from GridFieldOrderableRows::handleReorder)
        $modelClass = $grid->getModelClass();
        if ($list instanceof ManyManyList && !singleton($modelClass)->canView()) {
            $this->httpError(403);
        } else if (!($list instanceof ManyManyList) && !singleton($modelClass)->canEdit()) {
            $this->httpError(403);
        }
        //

        $item_id = $request->postVar('groupable_item_id');
        $group_key = $request->postVar('groupable_group_key');
        if ($group_key == 'none') {
            $group_key = '';
        }
        $item = $list->byID($item_id);
        $groupField = $this->getOption('groupField');


//        // only update  if we have an actual item (not if a boundary/whole group was dragged)
//        if(!$item) return $grid->FieldHolder();
        if ($item) {

            // Update item with correct Group assigned (custom query required to write m_m_extraField)
            //        DB::query(sprintf(
            //            "UPDATE `%s` SET `%s` = '%s' WHERE `BlockID` = %d",
            //            'SiteTree_Blocks',
            //            'BlockArea',
            //            $group_key,
            //            $item_id
            //        ));

            if ($list instanceof ManyManyList && array_key_exists($groupField, $list->getExtraFields())) {
                // update many_many_extrafields (MMList->add() with a new item adds a row, with existing item modifies a row)
                $list->add($item, [$groupField => $group_key]);
            } else {
                // or simply update the field on the item itself
                $item->$groupField = $group_key;
                $item->write();
            }

            $this->extend('onAfterAssignGroupItems', $list);

        } else {
            // boundary was dragged
            $groupsFieldOnSource = $this->groupsFieldOnSource;
            $groupData = $request->requestVar($groupsFieldOnSource);

            if ($groupsFieldOnSource && $groupData && ($form = $grid->getForm()) && ($record = $form->getRecord())) {
                // update groups on record
                $this->updateGroupsOnRecord($record, $groupsFieldOnSource, $groupData);

                // load any other inline edits from gridfield into record (normally handled by forwarding to orderablerows::reorder)
                $grid->saveInto($record);
                $record->write();

                return $grid->FieldHolder();
            }

            // dunno what happened here, try & continue normally...?

        }

        // Forward the request to GridFieldOrderableRows::handleReorder (if GridFieldOrderableRows)
        $orderableRowsComponent = $grid->getConfig()->getComponentByType(GridFieldOrderableRows::class);
        if ($orderableRowsComponent && $orderableRowsComponent->immediateUpdate) {
            return $orderableRowsComponent->handleReorder($grid, $request);
        } else {
            return $grid->FieldHolder();
        }

    }

    public function handleSave(GridField $grid, DataObjectInterface $record)
    {
//        $list = $grid->getList();
//        $value = $grid->Value();
//        if (!isset($value[__CLASS__]) || !is_array($value[__CLASS__])) {
//            return;
//        }
        $groupsFieldOnSource = $this->groupsFieldOnSource;
        // probably not the correct way to get the submitted data, but it works for now...
        $groupData = Controller::curr()->getRequest()->requestVar($groupsFieldOnSource);

        if ($groupsFieldOnSource && $groupData && ($form = $grid->getForm()) && ($record = $form->getRecord())) {
            // update groups on record
            $this->updateGroupsOnRecord($record, $groupsFieldOnSource, $groupData);
        }

        // and update each record's section if not already done via Ajax
        if (!$this->immediateUpdate) {
            $groupField = $this->getOption('groupField');
            $list = $grid->getList();
            $values = $grid->Value();
//            "GridFieldGroupable"]=>
//              array(10) {
//                        [5]=>
//                array(1) {
//                            ["Section"]=>
//                  string(24) "group_5460_1491390152511"
            // Basic checks
            if (!is_array($values)) return;
            if (!array_key_exists('GridFieldGroupable', $values)) return;
            $groupData = $values['GridFieldGroupable'];

            // update each with new group
            foreach ($list as $item) {
                // checks
                if (!array_key_exists($item->ID, $groupData)) continue;
                if (!array_key_exists($groupField, $groupData[$item->ID])) continue;
                $group_key = $groupData[$item->ID][$groupField];
                if ($item->$groupField == $group_key) continue; // skip unchanged
                // update
                if ($list instanceof ManyManyList && array_key_exists($groupField, $list->getExtraFields())) {
                    // update many_many_extrafields (MMList->add() with a new item adds a row, with existing item modifies a row)
                    $list->add($item, [$groupField => $group_key]);
                } else {
                    // or simply update the field on the item itself
                    $item->$groupField = $group_key;
                    $item->write();
                }
            }

//            $sortedIDs = $this->getSortedIDs($value);
//            if ($sortedIDs) {
//                $this->executeReorder($grid, $sortedIDs);
//            }
        }

    }

    private function updateGroupsOnRecord($record, $groupsFieldOnSource, $groupData)
    {
        // use KeyValueField to serialize, filters out empty values as well
        $keyValueField = KeyValueField::create($groupsFieldOnSource);
        $keyValueField->setValue($groupData);

        // unset 'none' (not sent anymore because 'none' key gets 'disabled' and is thus not submitted anymore
        // key has become '' (empty string) anyway
        // left here because why not...
        $keyvals = $keyValueField->Value(); // now a key-value list, with empty vals already filtered out
        if (array_key_exists('none', $keyvals)) unset($keyvals['none']);
        $keyValueField->setValue($keyvals);

        // and save groups into record
        $keyValueField->saveInto($record); // record is an object, so can be updated from this scope
    }

    /**
     * Gets the table which contains the group field.
     * (adapted from GridFieldOrderableRows)
     *
     * @param DataList $list
     * @return string
     */
    public function getGroupTable(DataList $list)
    {
        $field = $this->getOption('groupField');

        if ($list instanceof ManyManyList) {
            $extra = $list->getExtraFields();
            $table = $list->getJoinTable();

            if ($extra && array_key_exists($field, $extra)) {
                return $table;
            }
        }

        $classes = ClassInfo::dataClassesFor($list->dataClass());

        foreach ($classes as $class) {
            if (singleton($class)->hasOwnTableDatabaseField($field)) {
                return $class;
            }
        }

        throw new Exception("Couldn't find the sort field '$field'");
    }

    // (adapted from GridFieldOrderableRows)
    protected function getGroupTableClauseForIds(DataList $list, $ids)
    {
        if (is_array($ids)) {
            $value = 'IN (' . implode(', ', array_map('intval', $ids)) . ')';
        } else {
            $value = '= ' . (int)$ids;
        }

        if ($list instanceof ManyManyList) {
            $extra = $list->getExtraFields();
            $key = $list->getLocalKey();
            $foreignKey = $list->getForeignKey();
            $foreignID = (int)$list->getForeignID();

            if ($extra && array_key_exists($this->getOption('groupField'), $extra)) {
                return sprintf(
                    '"%s" %s AND "%s" = %d',
                    $key,
                    $value,
                    $foreignKey,
                    $foreignID
                );
            }
        }

        return "\"ID\" $value";
    }


    /**
     * Methods to implement from GridField_ColumnProvider
     * ('Add a new column to the table display body, or modify existing columns')
     * Used once per record/row.
     *
     * @package forms
     * @subpackage fields-gridfield
     */

    /**
     * Modify the list of columns displayed in the table.
     *
     * @see {@link GridFieldDataColumns->getDisplayFields()}
     * @see {@link GridFieldDataColumns}.
     *
     * @param GridField $gridField
     * @param array $columns List of columns
     * @param array - List reference of all column names.
     */
    public function augmentColumns($gridField, &$columns)
    {
    }

    /**
     * Names of all columns which are affected by this component.
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return ['Reorder'];
    }

    /**
     * HTML for the column, content of the <td> element.
     *
     * @param GridField $gridField
     * @param DataObject $record - Record displayed in this row
     * @param string $columnName
     * @return string - HTML for the column. Return NULL to skip.
     */
    public function getColumnContent($grid, $record, $columnName)
    {
        // In case you are using GridFieldGroupable, this ensures that
        // the correct group is saved. If you are not using that component,
        // this will be ignored by other components, but will still work for this.
        $groupFieldName = sprintf(
            '%s[GridFieldGroupable][%s][%s]',
            $grid->getName(),
            $record->ID,
            $this->groupField
        );
        $sortField = new HiddenField($groupFieldName, false, $record->getField($this->groupField));
        $sortField->addExtraClass('ss-groupable-hidden-group');
        $sortField->setForm($grid->getForm());
        return $sortField->Field();
    }

    /**
     * Attributes for the element containing the content returned by {@link getColumnContent()}.
     *
     * @param GridField $gridField
     * @param DataObject $record displayed in this row
     * @param string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        $groupField = $this->getOption('groupField');
        return ['data-groupable-group' => $record->$groupField];
    }

    /**
     * Additional metadata about the column which can be used by other components,
     * e.g. to set a title for a search column header.
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array - Map of arbitrary metadata identifiers to their values.
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        return [];
    }

}
