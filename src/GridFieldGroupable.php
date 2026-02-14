<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use Closure;
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
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\ORM\SS_List;
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
        'handleGroupCreate',
        'handleGroupReorder',
        'handleGroupAction',
        'handleGroupDelete',
        'handleGroupTitleUpdate',
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
     * If true (default), saves item reorders without refreshing the full GridField.
     * This preserves unsaved edits in EditableColumns.
     * If false, full GridField refresh after each reorder.
     */
    public bool $softRefresh = true;

    /**
     * The row template to render this with
     *
     * @var string
     */
    protected $dividerTemplate = 'GFGroupableDivider';

    // ========================================
    // DataObject Groups Mode (Phase 1)
    // ========================================

    /**
     * Relation name on source record for DataObject groups (has_many/many_many).
     * When set, enables DataObject mode instead of MultiValueField mode.
     */
    protected ?string $groupsRelation = null;

    /**
     * Field on group DataObject that provides the display name.
     * Default: 'Title'
     */
    protected string $groupTitleField = 'Title';

    /**
     * Additional fields from group DataObject to serialize for JS template.
     * These become available as {%=o.groupMeta.FieldName%} in templates.
     */
    protected array $groupMetadataFields = [];

    /**
     * Whether the item field stores a FK ID (DataObject mode) or string key (legacy mode).
     * Automatically set to true when setGroupsFromRelation() is called.
     */
    protected bool $groupFieldIsFK = false;

    /**
     * Sort field for groups (on DataObject or many_many relation).
     * Inspired by GridFieldOrderableRows::$sortField.
     */
    protected ?string $groupSortField = null;

    // ========================================
    // Group Creation (Phase 2)
    // ========================================

    /**
     * Custom handler for creating new groups (DataObject mode only).
     *
     * Callback signature: (GridField $gridField, DataObject $sourceRecord, array $groupData)
     * Should return: ['success' => bool, 'group' => DataObject|null, 'message' => string]
     *
     * If not set, creates DataObject directly and adds to relation.
     */
    protected ?Closure $groupCreateHandler = null;

    // ========================================
    // Group Actions (Phase 4)
    // ========================================

    /**
     * Custom action buttons for group rows (DataObject mode only).
     *
     * Array format: [
     *   'action_name' => [
     *     'icon' => 'font-icon-sync',
     *     'title' => 'Sync to FUSE',
     *     'handler' => Closure,
     *   ],
     * ]
     *
     * Handler signature: (GridField $gridField, DataObject $group, DataObject $sourceRecord)
     * Should return: ['success' => bool, 'message' => string, 'redirect' => string|null]
     */
    protected array $groupActions = [];

    // ========================================
    // Group Delete Handling (Phase 5)
    // ========================================

    /**
     * Behavior when deleting a group.
     *
     * 'unassign' (default) - Unassign items from the group (set to null), then delete group
     * 'prevent' - Prevent deletion if items are assigned to the group
     * 'callback' - Use custom handler for deletion logic
     */
    protected string $deleteMode = 'unassign';

    /**
     * Custom handler for group deletion (only used when deleteMode is 'callback').
     *
     * Callback signature: (GridField $gridField, DataObject $group, DataList $itemsInGroup)
     * Should return: ['success' => bool, 'message' => string]
     */
    protected ?Closure $groupDeleteHandler = null;

    // ========================================
    // Inline Title Editing (Phase 5)
    // ========================================

    /**
     * Whether group title is editable inline.
     * When true, an input field replaces the static title text.
     */
    protected bool $editableGroupTitle = false;

    /**
     * Custom handler for group title updates (DataObject mode only).
     *
     * Callback signature: (GridField $gridField, DataObject $sourceRecord, DataObject $group, string $newTitle)
     * Should return: ['success' => bool, 'message' => string]
     *
     * If not set, updates the group's title field directly.
     */
    protected ?Closure $groupTitleUpdateHandler = null;

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

    // ========================================
    // DataObject Groups Mode Setters/Getters
    // ========================================

    /**
     * Configure groups from a DataObject relation (has_many or many_many).
     * This enables DataObject mode and automatically sets groupFieldIsFK = true.
     *
     * @param string $relationName The relation name on the source record
     * @return $this
     */
    public function setGroupsFromRelation(string $relationName): self
    {
        $this->groupsRelation = $relationName;
        $this->groupFieldIsFK = true;
        return $this;
    }

    /**
     * Get the relation name for DataObject groups.
     */
    public function getGroupsRelation(): ?string
    {
        return $this->groupsRelation;
    }

    /**
     * Set which field on the group DataObject provides the display name.
     *
     * @param string $field Field name (default: 'Title')
     * @return $this
     */
    public function setGroupTitleField(string $field): self
    {
        $this->groupTitleField = $field;
        return $this;
    }

    /**
     * Get the title field for group DataObjects.
     */
    public function getGroupTitleField(): string
    {
        return $this->groupTitleField;
    }

    /**
     * Set additional fields from group DataObject to serialize for JS template.
     * These become available as {%=o.groupMeta.FieldName%} in templates.
     *
     * @param array $fields List of field names
     * @return $this
     */
    public function setGroupMetadataFields(array $fields): self
    {
        $this->groupMetadataFields = $fields;
        return $this;
    }

    /**
     * Get the metadata fields for group DataObjects.
     */
    public function getGroupMetadataFields(): array
    {
        return $this->groupMetadataFields;
    }

    /**
     * Set whether the item field stores a FK ID (true) or string key (false).
     * This is automatically set to true when setGroupsFromRelation() is called.
     *
     * @param bool $isFK
     * @return $this
     */
    public function setGroupFieldIsFK(bool $isFK): self
    {
        $this->groupFieldIsFK = $isFK;
        return $this;
    }

    /**
     * Check if item field stores FK ID (DataObject mode).
     */
    public function getGroupFieldIsFK(): bool
    {
        return $this->groupFieldIsFK;
    }

    /**
     * Set the sort field for groups (on DataObject or many_many relation).
     * Inspired by GridFieldOrderableRows::setSortField().
     *
     * @param string $field Sort field name
     * @return $this
     */
    public function setGroupSortField(string $field): self
    {
        $this->groupSortField = $field;
        return $this;
    }

    /**
     * Get the sort field for groups.
     */
    public function getGroupSortField(): ?string
    {
        return $this->groupSortField;
    }

    /**
     * Gets the table which contains the group sort field.
     * Adapted from GridFieldOrderableRows::getSortTable().
     *
     * @param SS_List $groupList The list of groups
     * @return string The table name
     * @throws Exception If sort field cannot be found
     */
    public function getGroupSortTable(SS_List $groupList): string
    {
        $field = $this->groupSortField;

        if (!$field) {
            throw new Exception('No group sort field configured');
        }

        if ($groupList instanceof ManyManyList) {
            $extra = $groupList->getExtraFields();
            if ($extra && array_key_exists($field, $extra)) {
                return $groupList->getJoinTable();
            }
        } elseif ($groupList instanceof ManyManyThroughList) {
            // For ManyManyThroughList, check the through/join class
            $joinClass = $groupList->getJoinClass();
            $schema = DataObject::getSchema();
            $fieldTable = $schema->tableForField($joinClass, $field);
            if ($fieldTable) {
                return $fieldTable;
            }
        }

        // Field is on the DataObject itself
        $classes = ClassInfo::dataClassesFor($groupList->dataClass());
        foreach ($classes as $class) {
            if (DataObject::singleton($class)->hasOwnTableDatabaseField($field)) {
                return DataObject::getSchema()->tableName($class);
            }
        }

        throw new Exception("Couldn't find group sort field '$field'");
    }

    /**
     * Check if this component is in DataObject mode (vs MultiValueField mode).
     */
    public function isDataObjectMode(): bool
    {
        return $this->groupsRelation !== null;
    }

    // ========================================
    // Group Creation Setters/Getters (Phase 2)
    // ========================================

    /**
     * Set custom handler for creating new groups (DataObject mode only).
     *
     * Callback signature: (GridField $gridField, DataObject $sourceRecord, array $groupData)
     * Should return: ['success' => bool, 'group' => DataObject|null, 'message' => string]
     *
     * If not set, creates DataObject directly and adds to relation.
     *
     * @param Closure $handler
     * @return $this
     */
    public function setGroupCreateHandler(Closure $handler): self
    {
        $this->groupCreateHandler = $handler;
        return $this;
    }

    /**
     * Get the custom group creation handler.
     */
    public function getGroupCreateHandler(): ?Closure
    {
        return $this->groupCreateHandler;
    }

    // ========================================
    // Group Actions Setters/Getters (Phase 4)
    // ========================================

    /**
     * Add an action button to group rows (DataObject mode only).
     *
     * Inspired by GridFieldSaveToFuseButton closure pattern.
     *
     * @param string $name Action identifier (used in URL and JS)
     * @param string $icon Font icon class (e.g., 'font-icon-sync')
     * @param string $title Button title/tooltip
     * @param Closure $handler Handler receives: (GridField, DataObject $group, DataObject $source)
     *                         Returns: ['success' => bool, 'message' => string, 'redirect' => string|null]
     * @return $this
     */
    public function addGroupAction(string $name, string $icon, string $title, Closure $handler): self
    {
        $this->groupActions[$name] = [
            'icon' => $icon,
            'title' => $title,
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * Remove a group action by name.
     *
     * @param string $name Action identifier
     * @return $this
     */
    public function removeGroupAction(string $name): self
    {
        unset($this->groupActions[$name]);
        return $this;
    }

    /**
     * Get all registered group actions.
     *
     * @return array
     */
    public function getGroupActions(): array
    {
        return $this->groupActions;
    }

    /**
     * Check if any group actions are registered.
     */
    public function hasGroupActions(): bool
    {
        return !empty($this->groupActions);
    }

    // ========================================
    // Group Delete Handling Setters/Getters (Phase 5)
    // ========================================

    /**
     * Set the behavior when deleting a group.
     *
     * @param string $mode 'unassign' (default), 'prevent', or 'callback'
     * @param Closure|null $handler Required if mode is 'callback'
     *        Receives: (GridField $gridField, DataObject $group, DataList $itemsInGroup)
     *        Returns: ['success' => bool, 'message' => string]
     * @return $this
     */
    public function setGroupDeleteBehavior(string $mode, ?Closure $handler = null): self
    {
        if (!in_array($mode, ['unassign', 'prevent', 'callback'])) {
            throw new \InvalidArgumentException("Invalid delete mode: $mode. Must be 'unassign', 'prevent', or 'callback'");
        }

        if ($mode === 'callback' && !$handler) {
            throw new \InvalidArgumentException("Delete mode 'callback' requires a handler Closure");
        }

        $this->deleteMode = $mode;
        $this->groupDeleteHandler = $handler;
        return $this;
    }

    /**
     * Get the current delete mode.
     */
    public function getDeleteMode(): string
    {
        return $this->deleteMode;
    }

    /**
     * Get the custom delete handler.
     */
    public function getGroupDeleteHandler(): ?Closure
    {
        return $this->groupDeleteHandler;
    }

    // ========================================
    // Inline Title Editing Setters/Getters (Phase 5)
    // ========================================

    /**
     * Enable or disable inline group title editing.
     *
     * When enabled, the group title becomes an editable input field.
     * Changes are saved via AJAX when the input loses focus.
     *
     * @param bool $editable
     * @param Closure|null $handler Optional custom handler for title updates
     *        Receives: (GridField $gridField, DataObject $sourceRecord, DataObject $group, string $newTitle)
     *        Returns: ['success' => bool, 'message' => string]
     * @return $this
     */
    public function setEditableGroupTitle(bool $editable = true, ?Closure $handler = null): self
    {
        $this->editableGroupTitle = $editable;
        if ($handler) {
            $this->groupTitleUpdateHandler = $handler;
        }
        return $this;
    }

    /**
     * Check if group title is editable.
     */
    public function isEditableGroupTitle(): bool
    {
        return $this->editableGroupTitle;
    }

    /**
     * Get the custom title update handler.
     */
    public function getGroupTitleUpdateHandler(): ?Closure
    {
        return $this->groupTitleUpdateHandler;
    }

    // ========================================
    // Soft Refresh Option
    // ========================================

    /**
     * Enable or disable soft refresh mode.
     *
     * When enabled, item reorders save without refreshing the full GridField.
     * This preserves unsaved edits in EditableColumns but doesn't update
     * visual feedback like row order badges.
     *
     * @param bool $soft
     * @return $this
     */
    public function setSoftRefresh(bool $soft = true): self
    {
        $this->softRefresh = $soft;
        return $this;
    }

    /**
     * Check if soft refresh is enabled.
     */
    public function isSoftRefresh(): bool
    {
        return $this->softRefresh;
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
            'POST group_create' => 'handleGroupCreate',
            'POST group_reorder' => 'handleGroupReorder',
            'POST group_action/$GroupID/$ActionName' => 'handleGroupAction',
            'POST group_delete/$GroupID' => 'handleGroupDelete',
            'POST group_title_update/$GroupID' => 'handleGroupTitleUpdate',
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
        $grid->setAttribute('data-url-group-reorder', $grid->Link('group_reorder'));
        // setoptions [groupUnassignedName, groupFieldLabel, groupField, groupsAvailable]
        $grid->setAttribute('data-groupable-unassigned', $this->getOption('groupUnassignedName'));
        $grid->setAttribute('data-groupable-role', $this->getOption('groupFieldLabel'));
        $grid->setAttribute('data-groupable-itemfield', $this->getOption('groupField'));
        // DataObject mode specific attributes
        $grid->setAttribute('data-groupable-sortable', $this->groupSortField ? 'true' : 'false');
        $grid->setAttribute('data-url-group-action', $grid->Link('group_action'));
        $grid->setAttribute('data-url-group-delete', $grid->Link('group_delete'));
        $grid->setAttribute('data-groupable-delete-mode', $this->deleteMode);
        $grid->setAttribute('data-groupable-editable-title', $this->editableGroupTitle ? 'true' : 'false');
        $grid->setAttribute('data-url-group-title-update', $grid->Link('group_title_update'));
        $grid->setAttribute('data-groupable-soft-refresh', $this->softRefresh ? 'true' : 'false');

        // Serialize group actions for JS (without handlers)
        if ($this->hasGroupActions()) {
            $actionsForJs = [];
            foreach ($this->groupActions as $name => $action) {
                $actionsForJs[$name] = [
                    'icon' => $action['icon'],
                    'title' => $action['title'],
                ];
            }
            $grid->setAttribute('data-groupable-actions', json_encode($actionsForJs));
        }

        // Get groups - either from DataObject relation or MultiValueField
        $groups = [];
        $mode = 'multivalue'; // default mode

        if ($this->isDataObjectMode() && ($form = $grid->getForm()) && ($record = $form->getRecord())) {
            // DataObject mode: load groups from has_many/many_many relation
            $mode = 'dataobject';
            $relationName = $this->groupsRelation;
            $groupList = $record->$relationName();

            // Apply sorting if configured
            if ($this->groupSortField) {
                $groupList = $groupList->sort($this->groupSortField);
            }

            // Serialize groups with metadata
            // Use array (not object keyed by ID) to preserve sort order in JSON
            // JavaScript objects sort numeric keys automatically, breaking our sort order
            foreach ($groupList as $group) {
                $groupData = [
                    'id' => $group->ID,
                    'name' => $group->{$this->groupTitleField},
                ];
                // Add configured metadata fields
                foreach ($this->groupMetadataFields as $field) {
                    $groupData[$field] = $group->$field;
                }
                $groups[] = $groupData;
            }
        } else {
            // Legacy mode: MultiValueField or static array
            $groups = $this->getOption('groupsAvailable');
            if (!$groups && $this->groupsFieldOnSource && ($form = $grid->getForm()) && ($record = $form->getRecord())) {
                $groups = $record->dbObject($this->groupsFieldOnSource)->getValues();
            }
        }

        $grid->setAttribute('data-groupable-groups', json_encode($groups));
        $grid->setAttribute('data-groupable-mode', $mode);

        // insert divider js tmpl
        $groupsField = (is_string($this->getOption('groupsFieldOnSource')) ? $this->getOption('groupsFieldOnSource') : '');
        $data = new ArrayData([
            'ColSpan' => $grid->getColumnCount() - 1,
            'GroupFieldLabel' => $this->groupFieldLabel,
            'GroupsFieldNameOnSource' => $groupsField,
            'IsDataObjectMode' => $this->isDataObjectMode(),
            'HasGroupActions' => $this->hasGroupActions(),
        ]);

        // Select template: use DataObject template if in DataObject mode and no custom template set
        $template = $this->dividerTemplate;
        if ($this->isDataObjectMode() && $template === 'GFGroupableDivider') {
            $template = 'GFDataObjectGroupableDivider';
        }

        return [
            'after' => $data->renderWith($template)
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

        // Process group_key based on mode
        if ($this->groupFieldIsFK) {
            // DataObject mode: store FK ID (integer) or null for unassigned
            $group_key = ($group_key === 'none' || $group_key === '' || $group_key === null)
                ? null
                : (int) $group_key;
        } else {
            // Legacy mode: store string key, empty string for unassigned
            if ($group_key == 'none') {
                $group_key = '';
            }
        }

        $item = $list->byID($item_id);
        $groupField = $this->getOption('groupField');

        if ($item) {
            // Extension hook before assignment
            $this->extend('onBeforeAssignGroupItems', $list, $item, $group_key);

            if ($list instanceof ManyManyList && array_key_exists($groupField, $list->getExtraFields())) {
                // update many_many_extrafields (MMList->add() with a new item adds a row, with existing item modifies a row)
                $list->add($item, [$groupField => $group_key]);
            } else {
                // or simply update the field on the item itself
                $item->$groupField = $group_key;
                $item->write();
            }

            // Extension hook after assignment
            $this->extend('onAfterAssignGroupItems', $list, $item, $group_key);

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

        // Handle reordering via GridFieldOrderableRows
        // JS now sends data in the same format as grid.reload(), so we can always use handleReorder
        $orderableRowsComponent = $grid->getConfig()->getComponentByType(GridFieldOrderableRows::class);

        if ($orderableRowsComponent && $orderableRowsComponent->immediateUpdate) {
            return $orderableRowsComponent->handleReorder($grid, $request);
        }

        return $grid->FieldHolder();

    }

    /**
     * Handle group creation request (DataObject mode only).
     *
     * Supports custom creation handlers for complex scenarios (e.g., FUSE API calls).
     * Falls back to default DataObject creation if no handler is set.
     *
     * @param GridField $grid
     * @param HTTPRequest $request
     * @return string JSON response
     */
    public function handleGroupCreate($grid, $request)
    {
        // Only supported in DataObject mode
        if (!$this->isDataObjectMode()) {
            return json_encode([
                'success' => false,
                'message' => 'Group creation is only supported in DataObject mode',
            ]);
        }

        // Permission check
        $form = $grid->getForm();
        $record = $form ? $form->getRecord() : null;

        if (!$record || !$record->canEdit()) {
            $this->httpError(403, 'Permission denied');
        }

        // Get group data from request
        $groupData = [
            'name' => $request->postVar('group_name') ?? '',
            'title' => $request->postVar('group_title') ?? $request->postVar('group_name') ?? '',
        ];

        // Collect any additional data from request with 'group_' prefix
        foreach ($request->postVars() as $key => $value) {
            if (str_starts_with($key, 'group_') && !in_array($key, ['group_name', 'group_title'])) {
                $fieldName = substr($key, 6); // Remove 'group_' prefix
                $groupData[$fieldName] = $value;
            }
        }

        // Extension hook before creation
        $this->extend('onBeforeCreateGroup', $grid, $record, $groupData);

        $group = null;
        $success = false;
        $message = '';

        try {
            if ($this->groupCreateHandler) {
                // Use custom handler (for complex creation like FUSE API calls)
                $handler = $this->groupCreateHandler;
                $result = $handler($grid, $record, $groupData);

                // Handler should return array with 'success', 'group', 'message' keys
                if (is_array($result)) {
                    $success = $result['success'] ?? false;
                    $group = $result['group'] ?? null;
                    $message = $result['message'] ?? '';
                } elseif ($result instanceof DataObject) {
                    // Handler returned DataObject directly - treat as success
                    $success = true;
                    $group = $result;
                    $message = 'Group created successfully';
                } else {
                    $success = false;
                    $message = 'Invalid response from group creation handler';
                }
            } else {
                // Default creation: create DataObject and add to relation
                $relationName = $this->groupsRelation;
                $relation = $record->$relationName();

                // Get the class of related objects
                $relationClass = $relation->dataClass();

                // Create new group DataObject
                $group = $relationClass::create();

                // Set title field
                $titleField = $this->groupTitleField;
                $group->$titleField = $groupData['title'] ?? $groupData['name'] ?? 'New Group';

                // Set any other matching fields from groupData
                foreach ($groupData as $field => $value) {
                    if ($group->hasField($field) && $field !== $titleField) {
                        $group->$field = $value;
                    }
                }

                // Set sort value if configured (place at end)
                if ($this->groupSortField) {
                    $sortField = $this->groupSortField;
                    $maxSort = $relation->max($sortField) ?? 0;
                    $group->$sortField = $maxSort + 1;
                }

                // Write the group
                $group->write();

                // Add to relation
                $relation->add($group);

                $success = true;
                $message = 'Group created successfully';
            }
        } catch (Exception $e) {
            $success = false;
            $message = 'Error creating group: ' . $e->getMessage();
        }

        // Extension hook after creation
        $this->extend('onAfterCreateGroup', $grid, $record, $group, $success, $message);

        // Return JSON response with group data for JS
        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if ($success && $group) {
            $response['group'] = [
                'id' => $group->ID,
                'name' => $group->{$this->groupTitleField},
            ];

            // Include metadata fields
            foreach ($this->groupMetadataFields as $field) {
                $response['group'][$field] = $group->$field;
            }
        }

        // Return updated GridField HTML along with response
        $response['html'] = $grid->FieldHolder();

        return json_encode($response);
    }

    /**
     * Handle group reordering request (DataObject mode only).
     *
     * Receives sorted group IDs and updates sort values.
     *
     * @param GridField $grid
     * @param HTTPRequest $request
     * @return string JSON response
     */
    public function handleGroupReorder($grid, $request)
    {
        // Only supported in DataObject mode with sort field
        if (!$this->isDataObjectMode()) {
            return json_encode([
                'success' => false,
                'message' => 'Group reordering is only supported in DataObject mode',
            ]);
        }

        if (!$this->groupSortField) {
            return json_encode([
                'success' => false,
                'message' => 'No group sort field configured',
            ]);
        }

        // Permission check
        $form = $grid->getForm();
        $record = $form ? $form->getRecord() : null;

        if (!$record || !$record->canEdit()) {
            $this->httpError(403, 'Permission denied');
        }

        // Get sorted IDs from request
        $sortedIDs = $request->postVar('group_order');
        if (!$sortedIDs || !is_array($sortedIDs)) {
            return json_encode([
                'success' => false,
                'message' => 'No group order provided',
            ]);
        }

        // Clean and validate IDs
        $sortedIDs = array_filter(array_map('intval', $sortedIDs));

        if (empty($sortedIDs)) {
            return json_encode([
                'success' => false,
                'message' => 'No valid group IDs provided',
            ]);
        }

        try {
            $relationName = $this->groupsRelation;
            $groupList = $record->$relationName();
            $sortField = $this->groupSortField;

            // Extension hook before reordering
            $this->extend('onBeforeReorderGroups', $grid, $record, $groupList, $sortedIDs);

            // Determine where sort field lives
            $sortTable = $this->getGroupSortTable($groupList);

            // Update sort values
            $sort = 1;
            foreach ($sortedIDs as $groupID) {
                if ($groupList instanceof ManyManyList) {
                    // Check if sort field is in extra fields
                    $extra = $groupList->getExtraFields();
                    if ($extra && array_key_exists($sortField, $extra)) {
                        // Update via many_many extra fields
                        $group = $groupList->byID($groupID);
                        if ($group) {
                            $groupList->add($group, [$sortField => $sort]);
                        }
                    } else {
                        // Sort field is on the DataObject
                        $group = $groupList->byID($groupID);
                        if ($group) {
                            $group->$sortField = $sort;
                            $group->write();
                        }
                    }
                } elseif ($groupList instanceof ManyManyThroughList) {
                    // For through lists, update the join record
                    $group = $groupList->byID($groupID);
                    if ($group) {
                        // Access through record and update
                        $throughList = $groupList->filter('ID', $groupID);
                        foreach ($throughList as $throughRecord) {
                            $joinRecord = $throughRecord->getJoin();
                            if ($joinRecord && $joinRecord->hasField($sortField)) {
                                $joinRecord->$sortField = $sort;
                                $joinRecord->write();
                            } else {
                                // Fallback: sort on main object
                                $throughRecord->$sortField = $sort;
                                $throughRecord->write();
                            }
                        }
                    }
                } else {
                    // Regular has_many - sort field is on the DataObject
                    $group = $groupList->byID($groupID);
                    if ($group) {
                        $group->$sortField = $sort;
                        $group->write();
                    }
                }
                $sort++;
            }

            // Extension hook after reordering
            $this->extend('onAfterReorderGroups', $grid, $record, $groupList, $sortedIDs);

            // Clear relation cache so FieldHolder renders with fresh data
            $record->flushCache(true);

            return json_encode([
                'success' => true,
                'message' => 'Groups reordered successfully',
                'html' => $grid->FieldHolder(),
            ]);

        } catch (Exception $e) {
            return json_encode([
                'success' => false,
                'message' => 'Error reordering groups: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle group action request (DataObject mode only).
     *
     * Routes to registered action handlers based on group ID and action name.
     *
     * @param GridField $grid
     * @param HTTPRequest $request
     * @return string JSON response
     */
    public function handleGroupAction($grid, $request)
    {
        // Only supported in DataObject mode
        if (!$this->isDataObjectMode()) {
            return json_encode([
                'success' => false,
                'message' => 'Group actions are only supported in DataObject mode',
            ]);
        }

        // Permission check
        $form = $grid->getForm();
        $record = $form ? $form->getRecord() : null;

        if (!$record || !$record->canEdit()) {
            $this->httpError(403, 'Permission denied');
        }

        // Get action parameters from URL
        $groupID = (int) $request->param('GroupID');
        $actionName = $request->param('ActionName');

        if (!$groupID || !$actionName) {
            return json_encode([
                'success' => false,
                'message' => 'Missing group ID or action name',
            ]);
        }

        // Check if action is registered
        if (!isset($this->groupActions[$actionName])) {
            return json_encode([
                'success' => false,
                'message' => "Unknown action: $actionName",
            ]);
        }

        // Get the group DataObject
        $relationName = $this->groupsRelation;
        $groupList = $record->$relationName();
        $group = $groupList->byID($groupID);

        if (!$group) {
            return json_encode([
                'success' => false,
                'message' => "Group not found: $groupID",
            ]);
        }

        try {
            // Extension hook before action
            $this->extend('onBeforeGroupAction', $grid, $group, $actionName, $record);

            // Execute the action handler
            $action = $this->groupActions[$actionName];
            $handler = $action['handler'];
            $result = $handler($grid, $group, $record);

            // Normalize result
            if (!is_array($result)) {
                $result = [
                    'success' => (bool) $result,
                    'message' => $result ? 'Action completed' : 'Action failed',
                ];
            }

            // Extension hook after action
            $this->extend('onAfterGroupAction', $grid, $group, $actionName, $result, $record);

            // Add HTML if no redirect
            if (!isset($result['redirect']) || !$result['redirect']) {
                $result['html'] = $grid->FieldHolder();
            }

            return json_encode($result);

        } catch (Exception $e) {
            return json_encode([
                'success' => false,
                'message' => 'Error executing action: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle group deletion request (DataObject mode only).
     *
     * Behavior depends on deleteMode:
     * - 'unassign': Unassign items from group, then delete group
     * - 'prevent': Return error if items are assigned to group
     * - 'callback': Use custom handler
     *
     * @param GridField $grid
     * @param HTTPRequest $request
     * @return string JSON response
     */
    public function handleGroupDelete($grid, $request)
    {
        // Only supported in DataObject mode
        if (!$this->isDataObjectMode()) {
            return json_encode([
                'success' => false,
                'message' => 'Group deletion is only supported in DataObject mode',
            ]);
        }

        // Permission check
        $form = $grid->getForm();
        $record = $form ? $form->getRecord() : null;

        if (!$record || !$record->canEdit()) {
            $this->httpError(403, 'Permission denied');
        }

        // Get group ID from URL
        $groupID = (int) $request->param('GroupID');

        if (!$groupID) {
            return json_encode([
                'success' => false,
                'message' => 'Missing group ID',
            ]);
        }

        // Get the group DataObject
        $relationName = $this->groupsRelation;
        $groupList = $record->$relationName();
        $group = $groupList->byID($groupID);

        if (!$group) {
            return json_encode([
                'success' => false,
                'message' => "Group not found: $groupID",
            ]);
        }

        // Get items assigned to this group
        $list = $grid->getList();
        $groupField = $this->getOption('groupField');
        $itemsInGroup = $list->filter($groupField, $groupID);

        try {
            // Extension hook before deletion
            $this->extend('onBeforeDeleteGroup', $grid, $group, $itemsInGroup, $record);

            $result = null;

            switch ($this->deleteMode) {
                case 'prevent':
                    if ($itemsInGroup->count() > 0) {
                        return json_encode([
                            'success' => false,
                            'message' => sprintf(
                                'Cannot delete group "%s": %d item(s) are still assigned',
                                $group->{$this->groupTitleField},
                                $itemsInGroup->count()
                            ),
                        ]);
                    }
                    // No items, safe to delete
                    $group->delete();
                    $result = [
                        'success' => true,
                        'message' => 'Group deleted successfully',
                    ];
                    break;

                case 'callback':
                    if ($this->groupDeleteHandler) {
                        $handler = $this->groupDeleteHandler;
                        $result = $handler($grid, $group, $itemsInGroup);

                        // Normalize result
                        if (!is_array($result)) {
                            $result = [
                                'success' => (bool) $result,
                                'message' => $result ? 'Group deleted' : 'Deletion failed',
                            ];
                        }
                    } else {
                        return json_encode([
                            'success' => false,
                            'message' => 'Callback mode requires a delete handler',
                        ]);
                    }
                    break;

                case 'unassign':
                default:
                    // Unassign items from the group
                    foreach ($itemsInGroup as $item) {
                        if ($list instanceof ManyManyList && array_key_exists($groupField, $list->getExtraFields())) {
                            // Update many_many extra field
                            $list->add($item, [$groupField => null]);
                        } else {
                            // Update field on item
                            $item->$groupField = null;
                            $item->write();
                        }
                    }

                    // Remove group from relation and delete
                    $groupList->remove($group);
                    $group->delete();

                    $result = [
                        'success' => true,
                        'message' => sprintf(
                            'Group deleted. %d item(s) unassigned.',
                            $itemsInGroup->count()
                        ),
                    ];
                    break;
            }

            // Extension hook after deletion
            $this->extend('onAfterDeleteGroup', $grid, $group, $result, $record);

            // Add updated HTML
            $result['html'] = $grid->FieldHolder();

            return json_encode($result);

        } catch (Exception $e) {
            return json_encode([
                'success' => false,
                'message' => 'Error deleting group: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle group title update request (DataObject mode only).
     *
     * Updates the title field on the group DataObject.
     * Supports custom handler for additional logic (e.g., FUSE sync).
     *
     * @param GridField $grid
     * @param HTTPRequest $request
     * @return string JSON response
     */
    public function handleGroupTitleUpdate($grid, $request)
    {
        // Only supported in DataObject mode
        if (!$this->isDataObjectMode()) {
            return json_encode([
                'success' => false,
                'message' => 'Title update is only supported in DataObject mode',
            ]);
        }

        // Check if title editing is enabled
        if (!$this->editableGroupTitle) {
            return json_encode([
                'success' => false,
                'message' => 'Inline title editing is not enabled',
            ]);
        }

        // Permission check
        $form = $grid->getForm();
        $record = $form ? $form->getRecord() : null;

        if (!$record || !$record->canEdit()) {
            $this->httpError(403, 'Permission denied');
        }

        // Get group ID from URL
        $groupID = (int) $request->param('GroupID');

        if (!$groupID) {
            return json_encode([
                'success' => false,
                'message' => 'Missing group ID',
            ]);
        }

        // Get the new title from request body
        $body = json_decode($request->getBody(), true);
        $newTitle = trim($body['title'] ?? '');

        if (empty($newTitle)) {
            return json_encode([
                'success' => false,
                'message' => 'Title cannot be empty',
            ]);
        }

        // Get the group DataObject
        $relationName = $this->groupsRelation;
        $groupList = $record->$relationName();
        $group = $groupList->byID($groupID);

        if (!$group) {
            return json_encode([
                'success' => false,
                'message' => "Group not found: $groupID",
            ]);
        }

        try {
            // Extension hook before title update
            $this->extend('onBeforeGroupTitleUpdate', $grid, $group, $newTitle, $record);

            if ($this->groupTitleUpdateHandler) {
                // Use custom handler (for complex updates like FUSE sync)
                $handler = $this->groupTitleUpdateHandler;
                $result = $handler($grid, $record, $group, $newTitle);

                // Normalize result
                if (!is_array($result)) {
                    $result = [
                        'success' => (bool) $result,
                        'message' => $result ? 'Title updated' : 'Update failed',
                    ];
                }
            } else {
                // Default: update the title field directly
                $titleField = $this->groupTitleField;
                $group->$titleField = $newTitle;
                $group->write();

                $result = [
                    'success' => true,
                    'message' => 'Title updated',
                ];
            }

            // Extension hook after title update
            $this->extend('onAfterGroupTitleUpdate', $grid, $group, $newTitle, $result, $record);

            return json_encode($result);

        } catch (Exception $e) {
            return json_encode([
                'success' => false,
                'message' => 'Error updating title: ' . $e->getMessage(),
            ]);
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

                // Process group_key based on mode
                if ($this->groupFieldIsFK) {
                    // DataObject mode: store FK ID (integer) or null for unassigned
                    $group_key = ($group_key === 'none' || $group_key === '' || $group_key === null)
                        ? null
                        : (int) $group_key;
                } else {
                    // Legacy mode: store string key, empty string for unassigned
                    if ($group_key == 'none') {
                        $group_key = '';
                    }
                }

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
