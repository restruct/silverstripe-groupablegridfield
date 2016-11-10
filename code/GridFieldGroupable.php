<?php

//	GridField_ColumnProvider, GridField_DataManipulator,
class GridFieldGroupable
    extends RequestHandler
    implements GridField_HTMLProvider,
        GridField_ColumnProvider,
        GridField_URLHandler
{

    private static $allowed_actions = array(
        'handleGroupAssignment',
    );

    /**
     * The database field which specifies the sort, defaults to "Sort".
     *
     * @see setSortField()
     * @var string
     */
    protected $groupUnassignedName;

    /**
     * The database field which specifies the sort, defaults to "Sort".
     *
     * @see setSortField()
     * @var string
     */
    protected $groupFieldLabel;

    /**
     * The database field which specifies the sort, defaults to "Sort".
     *
     * @see setSortField()
     * @var string
     */
    protected $groupField;

    /**
     * The database field which specifies the sort, defaults to "Sort".
     *
     * @see setSortField()
     * @var string
     */
    protected $groupsAvailable;

    /**
     * @param string $groupField
     * @param string $groupFieldLabel
     * @param string $groupUnassignedName
     * @param array $groupsAvailable
     */
    public function __construct(
        $groupField = 'Group',
        $groupFieldLabel = 'Group',
        $groupUnassignedName = '[none/inactive]',
        $groupsAvailable = array()
    ) {
        parent::__construct();
        $this->groupField = $groupField;
        $this->groupFieldLabel = $groupFieldLabel;
        $this->groupUnassignedName = $groupUnassignedName;
        $this->groupsAvailable = $groupsAvailable;
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
    public static function include_requirements() {

        $moduleDir = GROUPABLE_DIR;

        Requirements::javascript($moduleDir.'/js/groupable.js');
        Requirements::css($moduleDir.'/css/groupable.css');

	}

	public function getURLHandlers($grid) {
		return array(
			'POST group_assignment'    => 'handleGroupAssignment',
		);
	}

	/**
	 * @param GridField $field
	 */
	public function getHTMLFragments($field) {

        if( ! $field->getConfig()->getComponentByType('GridFieldOrderableRows')) {
            return;
        }

        self::include_requirements();

        // set ajax urls / vars
		$field->addExtraClass('ss-gridfield-groupable');
		$field->setAttribute('data-url-group-assignment', $field->Link('group_assignment'));
        // setoptions [groupUnassignedName, groupFieldLabel, groupField, groupsAvailable]
		$field->setAttribute('data-groupable-unassigned', $this->getOption('groupUnassignedName'));
		$field->setAttribute('data-groupable-role', $this->getOption('groupFieldLabel'));
		$field->setAttribute('data-groupable-itemfield', $this->getOption('groupField'));
		$field->setAttribute('data-groupable-groups', json_encode( $this->getOption('groupsAvailable') ) );

	}

	/**
	 * Handles requests to assign a new block area to a block item
	 *
	 * @param GridField $grid
	 * @param SS_HTTPRequest $request
	 * @return SS_HTTPResponse
	 */
	public function handleGroupAssignment($grid, $request) {
		$list = $grid->getList();

        // (copied from GridFieldOrderableRows::handleReorder)
		$modelClass = $grid->getModelClass();
		if ($list instanceof ManyManyList && !singleton($modelClass)->canView()) {
			$this->httpError(403);
		} else if(!($list instanceof ManyManyList) && !singleton($modelClass)->canEdit()) {
			$this->httpError(403);
		}
        //

		$item_id   = $request->postVar('groupable_item_id');
		$group_key   = $request->postVar('groupable_group_key');
        if($group_key=='none') $group_key = '';
		$item = $list->byID($item_id);
        $groupField = $this->getOption('groupField');

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
            $list->add($item, array($groupField => $group_key));
        } else {
            // or simply update the field on the item itself
            $item->$groupField = $group_key;
            $item->write();
        }

        $this->extend('onAfterAssignGroupItems', $list);

        // Forward the request to GridFieldOrderableRows::handleReorder (if GridFieldOrderableRows)
        $orderableRowsComponent = $grid->getConfig()->getComponentByType('GridFieldOrderableRows');
        if($orderableRowsComponent) {
            return $orderableRowsComponent->handleReorder($grid, $request);
        } else {
            return $grid->FieldHolder();
        }

	}

    /**
     * Gets the table which contains the group field.
     * (adapted from GridFieldOrderableRows)
     *
     * @param DataList $list
     * @return string
     */
    public function getGroupTable(DataList $list) {
        $field = $this->getOption('groupField');

        if($list instanceof ManyManyList) {
            $extra = $list->getExtraFields();
            $table = $list->getJoinTable();

            if($extra && array_key_exists($field, $extra)) {
                return $table;
            }
        }

        $classes = ClassInfo::dataClassesFor($list->dataClass());

        foreach($classes as $class) {
            if(singleton($class)->hasOwnTableDatabaseField($field)) {
                return $class;
            }
        }

        throw new Exception("Couldn't find the sort field '$field'");
    }

    // (adapted from GridFieldOrderableRows)
    protected function getGroupTableClauseForIds(DataList $list, $ids) {
        if(is_array($ids)) {
            $value = 'IN (' . implode(', ', array_map('intval', $ids)) . ')';
        } else {
            $value = '= ' . (int) $ids;
        }

        if($list instanceof ManyManyList) {
            $extra = $list->getExtraFields();
            $key   = $list->getLocalKey();
            $foreignKey = $list->getForeignKey();
            $foreignID  = (int) $list->getForeignID();

            if($extra && array_key_exists($this->getOption('groupField'), $extra)) {
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
     * @param arary $columns List of columns
     * @param array - List reference of all column names.
     */
    public function augmentColumns($gridField, &$columns){ }

    /**
     * Names of all columns which are affected by this component.
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField){
        return array('Reorder');
    }

    /**
     * HTML for the column, content of the <td> element.
     *
     * @param  GridField $gridField
     * @param  DataObject $record - Record displayed in this row
     * @param  string $columnName
     * @return string - HTML for the column. Return NULL to skip.
     */
    public function getColumnContent($gridField, $record, $columnName){ }

    /**
     * Attributes for the element containing the content returned by {@link getColumnContent()}.
     *
     * @param  GridField $gridField
     * @param  DataObject $record displayed in this row
     * @param  string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName){
        $groupField = $this->getOption('groupField');
        return array('data-groupable-group'=>$record->$groupField);
    }

    /**
     * Additional metadata about the column which can be used by other components,
     * e.g. to set a title for a search column header.
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array - Map of arbitrary metadata identifiers to their values.
     */
    public function getColumnMetadata($gridField, $columnName){
        return array();
    }

}
