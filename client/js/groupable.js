(function($) {

    $.entwine("ss", function($) {

        /**
         * Groupable works on top of OrderableRows, overrides some methods by having a more specific entwine selector
         */

        $(".ss-gridfield-orderable.ss-gridfield-groupable tbody").entwine({

            // Role of the group field, eg 'Area' (descriptive)
            GroupRole: 'Group',

            // Available groups
            AvailableGroups: null,

            // The field on items that holds the group dropdown
            ItemGroupField: null,

            // Name/heading for 'no group' / 0
            NoGroupName: '(none)',

            ApplyEnhancements: false,

            // Mode: 'multivalue' (legacy) or 'dataobject'
            GroupMode: 'multivalue',

            //onmatch: function() {
            onadd: function() {
                var self = this; // this & self are already a jQuery obj

                this.setItemGroupField( self.getGridField().data('groupable-itemfield') );

                var groupRole = self.getGridField().data('groupable-role');
                if(groupRole){ this.setGroupRole( groupRole ); }

                var noGroupName = self.getGridField().data('groupable-unassigned');
                if(noGroupName){ this.setNoGroupName( noGroupName ); }

                // Detect mode: 'multivalue' (legacy) or 'dataobject'
                var mode = self.getGridField().data('groupable-mode') || 'multivalue';
                this.setGroupMode(mode);

                var groupsRaw = self.getGridField().data('groupable-groups'); // valid json, already parsed by jQ
                // get from add-new-button if exists, allows for dynamic updating via ajax as only the grid's CONTENT gets
                // reloaded via Ajax (not the grid itself, which thus wouldn't contain the newly added group)
                groupsRaw = $('.ss-gridfield-add-new-group').data('groups-available') ?? groupsRaw;

                // Convert array format to object format while preserving order for iteration
                // PHP sends array to preserve sort order (JS objects sort numeric keys)
                var groups = {};        // Object keyed by ID for lookups
                var groupsOrdered = []; // Array preserving sort order for iteration
                var noGroupData = mode === 'dataobject'
                    ? { id: null, name: this.getNoGroupName() }
                    : this.getNoGroupName();

                if (groupsRaw && (Array.isArray(groupsRaw) ? groupsRaw.length : Object.keys(groupsRaw).length)) {
                    if (Array.isArray(groupsRaw)) {
                        // New array format from PHP (preserves order)
                        groupsRaw.forEach(function(groupData) {
                            var key = groupData.id || '';
                            groups[key] = groupData;
                            groupsOrdered.push({ key: key, data: groupData });
                        });
                    } else {
                        // Legacy object format (order may be wrong due to numeric key sorting)
                        $.each(groupsRaw, function(groupKey, groupData) {
                            groups[groupKey] = groupData;
                            groupsOrdered.push({ key: groupKey, data: groupData });
                        });
                    }
                    // Add empty group at the end
                    groups[''] = noGroupData;
                    groupsOrdered.push({ key: '', data: noGroupData });
                } else {
                    groups[''] = noGroupData;
                    groupsOrdered.push({ key: '', data: noGroupData });
                }
                this.setAvailableGroups(groups);

                // get initial ID order to check if we need to update after sorting
                var initialIdOrder = self.getGridField().getItems()
                    .map(function() { return $(this).data("id"); }).get();

                // insert blockAreas boundaries (use ordered array to preserve sort order)
                var groupBoundElements = [];
                $.each(groupsOrdered, function(index, item) {
                    var groupKey = item.key;
                    var groupData = item.data;
                    var th_tds_list = self.siblings('thead').find('tr').map(function() {
                        return $(this).find('th').length;
                    }).get();

                    // Build template data based on mode
                    // Note: jQuery .data() auto-converts "true"/"false" strings to booleans
                    var editableTitle = self.getGridField().data('groupable-editable-title') == true;
                    var metaConfig = self.getGridField().data('groupable-meta-config') || {};
                    var data;
                    if (mode === 'dataobject' && typeof groupData === 'object') {
                        // Build metaFields array for generic template iteration
                        var metaFields = [];
                        $.each(groupData, function(key, val) {
                            // Skip structural keys, only include metadata fields with values
                            if (['id', 'name'].indexOf(key) === -1 && val) {
                                var cfg = metaConfig[key] || {};
                                metaFields.push($.extend({field: key, value: val}, cfg));
                            }
                        });

                        // DataObject mode: groupData is an object with name, id, and metadata
                        data = {
                            "groupName": groupData.name || self.getNoGroupName(),
                            "groupKey": groupKey,
                            "groupId": groupData.id || null,
                            "groupMeta": groupData,  // Pass full object for template access
                            "metaFields": metaFields, // Structured array for template loop
                            "editableTitle": editableTitle
                        };
                    } else {
                        // Legacy mode: groupData is just a string (the name)
                        data = {
                            "groupName": (groupData || self.getNoGroupName()),
                            "groupKey": groupKey,
                            "groupId": null,
                            "groupMeta": {},
                            "metaFields": [],
                            "editableTitle": false  // Never editable in legacy mode
                        };
                    }

                    var boundTmpl = window.tmpl('groupable_divider_template', data);
                    var boundEl = $(boundTmpl);

                    boundEl.data('groupKey', data.groupKey); // used for assigning group to item when dragging into group
                    boundEl.data('groupName', data.groupName); // make available for convenience
                    boundEl.data('groupId', data.groupId); // DataObject ID (null for legacy mode)
                    boundEl.data('groupMeta', data.groupMeta); // Full metadata object
                    groupBoundElements[groupKey] = boundEl;

                    // Add action buttons for DataObject mode groups
                    if (mode === 'dataobject' && data.groupId) {
                        var groupActions = self.getGridField().data('groupable-actions');
                        if (groupActions && typeof groupActions === 'object') {
                            var actionsContainer = boundEl.find('.group-actions');
                            if (actionsContainer.length) {
                                $.each(groupActions, function(actionName, actionConfig) {
                                    var actionBtn = $('<button type="button"></button>')
                                        .addClass('btn btn--no-text btn--icon-md grid-field__icon-action ss-gridfield-group-action')
                                        .addClass(actionConfig.icon)
                                        .attr('title', actionConfig.title)
                                        .attr('data-action', actionName)
                                        .attr('data-group-id', data.groupId);
                                    actionsContainer.append(actionBtn);
                                });
                            }
                        }
                    }

                    $(self).append(boundEl); //before(bound);

                    // update applyenhancements if advanced
                    if(boundEl.hasClass('groupable-advanced-bound')){
                        self.setApplyEnhancements(true);
                    }
                });

                // and put blocks in order below boundaries
                jQuery.fn.reverseOrder = [].reverse; // small reverse plugin
                self.getGridField().getItems().reverseOrder().each(function(){
                    var myGroup = $('.col-reorder .ss-groupable-hidden-group',this).val() || '';
                    if(! (myGroup in groupBoundElements)) myGroup = '';
                    $(this).insertAfter( groupBoundElements[myGroup] );
                });

                // get ID order again to check if we need to update now we've sorted primarily by area
                var sortedIdOrder = self.getGridField().getItems()
                    .map(function() { return $(this).data("id"); }).get();

                //
                // Now execute GridFieldOrderableRows::onadd to add drag & drop sorting/sortable()
                //
                this._super();

                // remove the auto sortable callback (called by hand after setting the correct area first)
                this.sortable({ update: null });

                // apply enhancements if required (AddNewGroupButton/sortable & renameable groups)
                if(this.getApplyEnhancements()){
                    this.applyenhancedsorthelper();
                }

            },

            onsortstop: function( event, ui ) {
                var grid = this.getGridField();
                var wasGroupBoundaryDrag = ui.item.hasClass('groupable-advanced-bound');

                // if a boundary/whole group was dragged...
                if (wasGroupBoundaryDrag) {
                    // insert items at helper position and remove helper (<div>)
                    ui.item.after(ui.item.data('multidrag'));
                    // remove original
                    ui.item.data('original').remove();
                    // and remove dragged container div
                    ui.item.remove();

                    // Check if group sorting is enabled (DataObject mode)
                    var groupSortable = grid.data('groupable-sortable') === 'true' || grid.data('groupable-sortable') === true;
                    var mode = grid.data('groupable-mode');

                    if (groupSortable && mode === 'dataobject') {
                        // Collect new group order (IDs of group DataObjects)
                        var groupOrder = [];
                        this.find('.groupable-bound').each(function() {
                            var groupId = $(this).data('groupId');
                            // Only include actual DataObject groups (not the empty/unassigned group)
                            if (groupId && groupId !== null) {
                                groupOrder.push(groupId);
                            }
                        });

                        // Post new group order to server
                        if (groupOrder.length > 0 && grid.data("immediate-update")) {
                            var reorderUrl = grid.data("url-group-reorder");

                            $.ajax({
                                url: reorderUrl,
                                type: 'POST',
                                data: { 'group_order': groupOrder },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        // Show message if provided
                                        if (response.message && typeof ss !== 'undefined' && ss.StatusMessage) {
                                            ss.StatusMessage(response.message);
                                        }
                                        // Optionally reload with new HTML
                                        if (response.html) {
                                            grid.replaceWith(response.html);
                                        }
                                    } else {
                                        alert(response.message || 'Reorder failed');
                                        // Reload to restore original order
                                        grid.reload();
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Error reordering groups:', error);
                                    alert('Error reordering groups: ' + error);
                                    grid.reload();
                                }
                            });
                            return; // Don't continue to item reordering
                        }
                    }

                    // Legacy mode or non-immediate: mark form as changed
                    $('.cms-edit-form').addClass('changed');
                    return;
                }

                // Regular item drag (not a group boundary)
                // set correct area on row/item areaselect
                var groupKey = ui.item.prevAll('.groupable-bound').first().data('groupKey');
                // $('.col-'+ this.getItemGroupField(),ui.item).find('select, input').val(groupKey);
                $('.col-reorder .ss-groupable-hidden-group',ui.item).val(groupKey);

                // Save group on object/rel
                // duplicated from GridFieldOrderableRows.onadd.update:
                var data = grid.getItems().map(function() {
                    // regular orderable row
                    return {
                        name: "order[]",
                        value: $(this).data("id")
                    };
                }).get();

                // insert area assignment data as well
                data.push({
                    name: 'groupable_item_id',
                    value: ui.item.data("id")
                });
                data.push({
                    name: 'groupable_group_key',
                    value: groupKey
                });

                // Get lowest sort value in this list (respects pagination)
                var minSort = null;
                grid.getItems().each(function() {
                    // get sort field
                    var sortField = $(this).find('.ss-orderable-hidden-sort');
                    if (sortField.length) {
                        var thisSort = sortField.val();
                        if (minSort === null && thisSort > 0) {
                            minSort = thisSort;
                        } else if (thisSort > 0) {
                            minSort = Math.min(minSort, thisSort);
                        }
                    }
                });
                minSort = Math.max(1, minSort);

                // With the min sort found, loop through all records and re-arrange
                var sort = minSort;
                grid.getItems().each(function() {
                    // get sort field
                    var sortField = $(this).find('.ss-orderable-hidden-sort');
                    if (sortField.length) {
                        sortField.val(sort);
                        sort++;
                    }
                });

                // Area-assignment forwards the request to gridfieldextensions::reorder server side
                // Check if we are allowed to postback
                if (grid.data("immediate-update") && data) {
                    // Check if we should avoid full refresh (preserves unsaved EditableColumns edits)
                    var softRefresh = grid.data("groupable-soft-refresh") === true || grid.data("groupable-soft-refresh") === 'true';

                    if (softRefresh) {
                        // Collect form data the same way grid.reload() does
                        // This sends full GridField state including GridFieldEditableColumns data
                        var form = grid.closest('form');
                        var formData = form.find(':input').serializeArray();

                        // Add the order[] array (built earlier, not in form inputs)
                        data.forEach(function(item) {
                            formData.push(item);
                        });

                        // Add groupable-specific data
                        formData.push({ name: 'groupable_item_id', value: ui.item.data("id") });
                        formData.push({ name: 'groupable_group_key', value: groupKey });

                        $.ajax({
                            url: grid.data("url-group-assignment"),
                            type: 'POST',
                            data: $.param(formData),
                            dataType: 'text', // handleGroupAssignment returns HTML
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            success: function(response) {
                                // HTTP 2xx = saved: server-side failures surface as non-2xx (httpError/fatals)
                                // and land in the error callback below. Do NOT sniff the HTML body for 'error'
                                // substrings — that false-positived on legitimate grid content (titles, CSS
                                // classnames etc. containing 'error') and wrongly marked the form dirty.
                                // if (response.indexOf('ERROR') !== -1 || response.indexOf('error') !== -1) {
                                //     console.error('Server returned error in response');
                                //     // Mark as changed since save failed but DOM state is different
                                //     var cmsForm = $('.cms-edit-form');
                                //     cmsForm.addClass('changed');
                                // } else {

                                // Success - data was saved, no need to mark form dirty
                                if (typeof ss !== 'undefined' && ss.StatusMessage) {
                                    ss.StatusMessage('Item moved and saved');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Error saving item position:', error, xhr.responseText);
                                // Don't alert on every error - just log and mark form as changed
                                // The user can save the form to persist changes
                                var cmsForm = $('.cms-edit-form');
                                cmsForm.addClass('changed');
                                if (typeof ss !== 'undefined' && ss.StatusMessage) {
                                    ss.StatusMessage('Position changed (will save with form)', 'warning');
                                }
                            }
                        });
                    } else {
                        // Full reload (original behavior)
                        grid.reload({
                            url: grid.data("url-group-assignment"),
                            data: data
                        });
                    }
                } else {
                    // Tells the user they have unsaved changes when they
                    // try and leave the page after sorting, also updates the
                    // save buttons to show the user they've made a change.
                    var form = $('.cms-edit-form');
                    form.addClass('changed');
                }

            },

            applyenhancedsorthelper: function(){
                // Enhance helper function to support groups of items
                var mainHelper = this.sortable('option', 'helper');
                var grid = this;

                // Helper to invoke the original helper (handles string vs function)
                var invokeMainHelper = function(e, row) {
                    if (typeof mainHelper === 'function') {
                        return mainHelper(e, row);
                    } else if (mainHelper === 'clone') {
                        return row.clone();
                    } else {
                        // 'original' or fallback
                        return row;
                    }
                };

                var enhancedHelper = function(e, row) {
                    if (!row) return;

                    // drag multiple in group (only for actual groups with groupable-advanced-bound)
                    if (row.hasClass('groupable-advanced-bound')) {
                        // Clone the selected items into an array (row + all items until next group boundary)
                        // Use .groupable-bound to stop at ANY boundary including "unassigned" group
                        var drag_target = row;
                        var originals = row.nextUntil('.groupable-bound').addBack();
                        var group_items = originals.clone();

                        // .clone() only carries markup/attribute state — the jQuery .data() store and LIVE
                        // form values (properties) are lost. Group identity (groupKey/name/id) is covered by
                        // data-* attributes on the divider row (see divider templates; .data() reads fall
                        // back to attributes), but unsaved form state on the cloned rows — an in-flight
                        // section rename, drag-set hidden group inputs, multidrag checkboxes — must be
                        // copied over explicitly (index-aligned), else it silently reverts after group-drag.
                        var origInputs = originals.find('input, select, textarea');
                        group_items.find('input, select, textarea').each(function(idx) {
                            var orig = origInputs.eq(idx);
                            if (orig.is(':checkbox, :radio')) {
                                this.checked = orig.prop('checked');
                            } else {
                                $(this).val(orig.val());
                            }
                        });

                        // Store clones for restore, and reference to original
                        row.data('multidrag', group_items).data('original', drag_target);

                        // Remove the items from source (not the row - sortable manages that)
                        row.nextUntil('.groupable-bound').remove();

                        // Create the helper - simple div containing the cloned items
                        var helper = $('<div/>').append(group_items);
                        return helper;
                    } else {
                        // return existing/regular helper
                        return invokeMainHelper(e, row);
                    }
                };

                // Restrict where groups can be dropped (only directly before other group boundaries)
                var restrictGroupPlacement = function(event, ui) {
                    // Only apply restriction when dragging a group
                    if (!ui.item.hasClass('groupable-advanced-bound')) {
                        return;
                    }

                    var placeholder = ui.placeholder;
                    var next = placeholder.next();

                    // Valid position: directly before a group boundary
                    if (!next.hasClass('groupable-bound')) {
                        // Find nearest group boundary below and snap to before it
                        var nearestBoundBelow = placeholder.nextAll('.groupable-bound').first();
                        if (nearestBoundBelow.length) {
                            nearestBoundBelow.before(placeholder);
                        } else {
                            // No boundary below (we're in/after unassigned group)
                            // Snap to before the last boundary (unassigned group)
                            var lastBound = placeholder.prevAll('.groupable-bound').first();
                            if (lastBound.length) {
                                lastBound.before(placeholder);
                            }
                        }
                    }
                };

                // Apply enhancedHelper and sort restriction to (OrderableRows) sortable
                this.sortable({
                    'helper': enhancedHelper,
                    'sort': restrictGroupPlacement
                });
            }

        });



        /**
         * GridFieldAddNewGroupButton
         */

        $(".ss-gridfield-orderable.ss-gridfield-groupable").entwine({

            onaddnewgroup: function(e) {
                if(e.target !== this[0]) {
                    return;
                }

                var data = {
                    "groupKey": 'group_' + (Math.floor( Math.random() * 9999) ) + '_' + (new Date).getTime(),
                    "groupName": '…',
                    "unsavedGroupNotice": this.find('.ss-gridfield-add-new-group').data('unsavedGroupNotice'),
                };
                var boundTmpl = window.tmpl('groupable_divider_template', data);
                var boundEl = $(boundTmpl);
                $('tbody', this).prepend(boundEl);

                // mark dirty
                $('.cms-edit-form').addClass('changed unsaved-group');
            }

        });

        $(".ss-gridfield .ss-gridfield-add-new-group").entwine({
            onclick: function() {
                this.getGridField().trigger("addnewgroup");
                return false;
            }
        });

        $(".ss-gridfield .ss-gridfield-delete-groups-divider").entwine({
            onclick: function(e) {
                var msg = ss.i18n.sprintf(
                    ss.i18n._t("GridFieldExtensions.CONFIRMDEL", "Are you sure you want to delete \"%s\"?"),
                    this.closest('.groupable-bound').first().data('groupName')
                );
                if(confirm(msg)) {
                    this.parents("tr.groupable-advanced-bound:first").remove();

                }

                return false;
            }
        });


        /**
         * GridFieldAddNewDataObjectGroupButton - for DataObject mode group creation
         */

        $(".ss-gridfield .ss-gridfield-add-new-do-group").entwine({

            /**
             * Create a new DataObject group via AJAX
             *
             * @param {jQuery} [input] the name input to read; defaults to the inline input. The
             *   add-group dialog passes its own input, which jQuery UI has moved out of this wrapper.
             * @return {jqXHR|false} the request, so a caller can act on its outcome (the dialog
             *   closes itself on success), or false when nothing was sent
             */
            createGroup: function(input) {
                var self = this;
                var grid = this.getGridField();
                // the grid is replaced on success; its id is how the new one is found again
                var gridId = grid.attr('id');
                input = input || this.find('.ss-gridfield-new-group-name');

                // A request is already running for this input (it is disabled until that completes):
                // a second Enter, or a held one, would otherwise create the group twice
                if (input.prop('disabled')) {
                    return false;
                }

                var groupName = input.val().trim();

                if (!groupName) {
                    // Focus on input if empty
                    input.focus();
                    return false;
                }

                var createUrl = this.data('create-url');
                if (!createUrl) {
                    console.error('GridFieldAddNewDataObjectGroupButton: No create URL found');
                    return false;
                }

                // Disable button while processing
                var btn = this.find('.ss-gridfield-create-group-btn');
                btn.prop('disabled', true);
                input.prop('disabled', true);

                // Post to create endpoint
                return $.ajax({
                    url: createUrl,
                    type: 'POST',
                    data: {
                        group_name: groupName,
                        group_title: groupName
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Clear input
                            input.val('');

                            // Reload GridField with new content
                            if (response.html) {
                                grid.replaceWith(response.html);
                                // focus went down with the old grid (to <body>): put it back on the
                                // add-group button, so a keyboard user carries on where they were
                                self.focusAddGroupButton(gridId);
                            } else {
                                // Fallback: full reload
                                grid.reload();
                            }
                        } else {
                            alert(response.message || 'Failed to create group');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error creating group:', error);
                        alert('Error creating group: ' + error);
                    },
                    complete: function() {
                        // Re-enable controls
                        btn.prop('disabled', false);
                        input.prop('disabled', false);
                    }
                });
            },

            /**
             * Focus the add-group button (inline or dialog variant) of the grid with this id: after a
             * create the grid, and this button with it, has been replaced.
             *
             * @param {string} gridId
             */
            focusAddGroupButton: function(gridId) {
                $('#' + gridId)
                    .find('.ss-gridfield-create-group-btn, .ss-gridfield-add-group-modal-btn')
                    .first()
                    .trigger('focus');
            },

            /**
             * Open the add-group dialog (non-inline variant, setInlineInput(false), #14).
             *
             * Uses jQuery UI's dialog: both CMS majors (admin 2 on Silverstripe 5, admin 3 on
             * Silverstripe 6) still bundle jQuery UI 1.13 on the global jQuery and style .ui-dialog,
             * while neither loads Bootstrap's modal JS. Each open works on a fresh copy of the hidden
             * template markup, destroyed again on close: jQuery UI moves the dialog to <body>, and the
             * GridField (and this wrapper with it) is replaced after a create, so a dialog kept between
             * opens would outlive the grid it belongs to.
             */
            openGroupDialog: function() {
                var self = this;
                var template = this.find('.ss-gridfield-add-group-dialog').first();

                if (typeof $.fn.dialog !== 'function') {
                    console.error('GridFieldAddNewDataObjectGroupButton: jQuery UI dialog is not loaded');
                    return false;
                }

                var dialog = template.clone().removeAttr('hidden');
                var input = dialog.find('.ss-gridfield-new-group-name');
                var buttons = dialog.find('button');

                var gridId = this.getGridField().attr('id');

                var submit = function() {
                    // a request is already running (createGroup disables the input for its duration)
                    if (input.prop('disabled')) {
                        return;
                    }
                    var request = self.createGroup(input);
                    if (!request) {
                        return;
                    }
                    // While the request runs the dialog cannot be closed (Escape, its X): its success
                    // handler closes it, and closing it first used to leave that handler calling
                    // close on a destroyed widget, which threw and left jQuery.active stuck at 1.
                    // createGroup re-enables the input itself; the dialog's own buttons are ours.
                    var closeButton = dialog.dialog('widget').find('.ui-dialog-titlebar-close');
                    buttons.prop('disabled', true);
                    closeButton.prop('disabled', true);
                    dialog.dialog('option', 'closeOnEscape', false);
                    request
                        .done(function(response) {
                            if (response && response.success && dialog.dialog('instance') && dialog.dialog('isOpen')) {
                                dialog.dialog('close');
                                // jQuery UI's close hands focus back to the button that opened the
                                // dialog, which went with the old grid, and so blurs to <body>:
                                // focus the new grid's button again once the dialog is gone
                                self.focusAddGroupButton(gridId);
                            }
                        })
                        .always(function() {
                            buttons.prop('disabled', false);
                            closeButton.prop('disabled', false);
                            if (dialog.dialog('instance')) {
                                dialog.dialog('option', 'closeOnEscape', true);
                            }
                        });
                };

                dialog.on('click', '.ss-gridfield-add-group-dialog-create', function(e) {
                    e.preventDefault();
                    submit();
                });
                dialog.on('click', '.ss-gridfield-add-group-dialog-cancel', function(e) {
                    e.preventDefault();
                    dialog.dialog('close');
                });
                // Enter creates; the inline input's entwine keypress handler does not reach here,
                // as the dialog lives outside .ss-gridfield
                input.on('keydown', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        submit();
                    }
                });

                dialog.dialog({
                    modal: true,
                    width: 420,
                    // a hook for client/css/groupable.css; jQuery UI merges this per key with the classes
                    // the Silverstripe 6 admin maps onto every dialog (modal-header, modal-title), but a
                    // key given here replaces that key's default, so ui-corner-all is repeated
                    classes: { 'ui-dialog': 'ui-corner-all ss-gridfield-add-group-ui-dialog' },
                    resizable: false,
                    // the copy is single-use: remove it with its jQuery UI wrapper and overlay
                    close: function() {
                        dialog.dialog('destroy').remove();
                    },
                    open: function() {
                        input.trigger('focus');
                    }
                });

                return false;
            }
        });

        // Non-inline add-group button: open the dialog (#14)
        $(".ss-gridfield .ss-gridfield-add-group-modal-btn").entwine({
            onclick: function() {
                this.closest('.ss-gridfield-add-new-do-group').openGroupDialog();
                return false;
            }
        });

        // Create button click handler
        $(".ss-gridfield .ss-gridfield-create-group-btn").entwine({
            onclick: function() {
                this.closest('.ss-gridfield-add-new-do-group').createGroup();
                return false;
            }
        });

        // Input field enter key handler
        $(".ss-gridfield .ss-gridfield-new-group-name").entwine({
            onkeypress: function(e) {
                // Create on Enter key
                if (e.which === 13) {
                    e.preventDefault();
                    this.closest('.ss-gridfield-add-new-do-group').createGroup();
                    return false;
                }
            }
        });


        /**
         * Group action button click handler (DataObject mode)
         */
        $(".ss-gridfield .ss-gridfield-group-action").entwine({
            onclick: function() {
                var self = this;
                var grid = this.getGridField();
                var actionName = this.data('action');
                var groupId = this.data('group-id');

                if (!actionName || !groupId) {
                    console.error('Missing action name or group ID');
                    return false;
                }

                var actionUrl = grid.data('url-group-action');
                if (!actionUrl) {
                    console.error('No group action URL found');
                    return false;
                }

                // Build URL with group ID and action name
                var url = actionUrl + '/' + groupId + '/' + actionName;

                // Disable button while processing
                self.prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Check for redirect
                            if (response.redirect) {
                                window.location.href = response.redirect;
                                return;
                            }

                            // Show message if provided
                            if (response.message) {
                                // Use SilverStripe's notification if available
                                if (typeof ss !== 'undefined' && ss.StatusMessage) {
                                    ss.StatusMessage(response.message);
                                }
                            }

                            // Reload GridField with new content
                            if (response.html) {
                                grid.replaceWith(response.html);
                            } else {
                                grid.reload();
                            }
                        } else {
                            alert(response.message || 'Action failed');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error executing action:', error);
                        alert('Error executing action: ' + error);
                    },
                    complete: function() {
                        self.prop('disabled', false);
                    }
                });

                return false;
            }
        });


        /**
         * Group delete button click handler (DataObject mode)
         */
        $(".ss-gridfield .ss-gridfield-group-delete").entwine({
            onclick: function() {
                var self = this;
                var grid = this.getGridField();
                var groupId = this.data('group-id');
                var groupName = this.data('group-name') || 'this group';
                var deleteMode = grid.data('groupable-delete-mode') || 'unassign';

                if (!groupId) {
                    console.error('Missing group ID for delete');
                    return false;
                }

                // Confirm deletion
                var confirmMsg;
                if (deleteMode === 'prevent') {
                    confirmMsg = 'Are you sure you want to delete "' + groupName + '"?\n\nNote: Deletion will fail if items are still assigned to this group.';
                } else if (deleteMode === 'unassign') {
                    confirmMsg = 'Are you sure you want to delete "' + groupName + '"?\n\nAny items in this group will be unassigned.';
                } else {
                    confirmMsg = 'Are you sure you want to delete "' + groupName + '"?';
                }

                if (!confirm(confirmMsg)) {
                    return false;
                }

                var deleteUrl = grid.data('url-group-delete');
                if (!deleteUrl) {
                    console.error('No group delete URL found');
                    return false;
                }

                // Build URL with group ID
                var url = deleteUrl + '/' + groupId;

                // Disable button while processing
                self.prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show message if provided
                            if (response.message) {
                                if (typeof ss !== 'undefined' && ss.StatusMessage) {
                                    ss.StatusMessage(response.message);
                                }
                            }

                            // Reload GridField with new content
                            if (response.html) {
                                grid.replaceWith(response.html);
                            } else {
                                grid.reload();
                            }
                        } else {
                            alert(response.message || 'Delete failed');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error deleting group:', error);
                        alert('Error deleting group: ' + error);
                    },
                    complete: function() {
                        self.prop('disabled', false);
                    }
                });

                return false;
            }
        });


        /**
         * Click-to-edit: click on title to start editing
         */
        $(".ss-gridfield .group-title-editable").entwine({
            onclick: function() {
                var input = this.next('.group-title-input');
                if (input.length) {
                    this.addClass('d-none');
                    input.removeClass('d-none').addClass('d-inline-block').focus().select();
                }
            }
        });

        /**
         * Group title input handlers (DataObject mode with editable titles)
         */
        $(".ss-gridfield .group-title-input").entwine({
            // Track if edit was cancelled
            Cancelled: false,

            /**
             * Save title on focus out (when input loses focus)
             * Note: entwine doesn't support onblur, uses onfocusout instead
             */
            onfocusout: function() {
                if (this.getCancelled()) {
                    this.setCancelled(false);
                    this._hideInput();
                    return;
                }
                this._saveTitle();
            },

            /**
             * Handle keyboard
             */
            onkeydown: function(e) {
                if (e.keyCode === 13) { // Enter key - save
                    e.preventDefault();
                    this.blur();
                } else if (e.keyCode === 27) { // Escape key - cancel
                    e.preventDefault();
                    this.val(this.data('original-value'));
                    this.setCancelled(true);
                    this.blur();
                }
            },

            /**
             * Hide input and show title wrapper
             */
            _hideInput: function() {
                var titleWrapper = this.prev('.group-title-editable');
                this.removeClass('d-inline-block').addClass('d-none');
                titleWrapper.removeClass('d-none');
            },

            /**
             * Save the title via AJAX
             */
            _saveTitle: function() {
                var self = this;
                var grid = this.getGridField();
                var groupId = this.data('group-id');
                var newTitle = this.val().trim();
                var originalTitle = this.data('original-value');
                var titleEl = this.prev('.group-title-editable').find('.group-title');

                // Skip if unchanged - just hide
                if (newTitle === originalTitle) {
                    this._hideInput();
                    return;
                }

                // Validate
                if (!newTitle) {
                    alert('Title cannot be empty');
                    this.val(originalTitle);
                    this._hideInput();
                    return;
                }

                var updateUrl = grid.data('url-group-title-update');
                if (!updateUrl) {
                    console.error('No group title update URL found');
                    this._hideInput();
                    return;
                }

                // Build URL with group ID
                var url = updateUrl + '/' + groupId;

                // Disable input while processing
                self.prop('disabled', true).addClass('loading');

                $.ajax({
                    url: url,
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ title: newTitle }),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update the original value and displayed title
                            self.data('original-value', newTitle);
                            titleEl.text(newTitle);

                            // Show message if provided
                            if (response.message) {
                                if (typeof ss !== 'undefined' && ss.StatusMessage) {
                                    ss.StatusMessage(response.message);
                                }
                            }
                        } else {
                            alert(response.message || 'Update failed');
                            // Revert to original value
                            self.val(originalTitle);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating group title:', error);
                        alert('Error updating title: ' + error);
                        // Revert to original value
                        self.val(originalTitle);
                    },
                    complete: function() {
                        self.prop('disabled', false).removeClass('loading');
                        self._hideInput();
                    }
                });
            }
        });

        /**
         * Copy-to-clipboard button for metadata fields
         */
        $(".ss-gridfield .groupable-copy-btn").entwine({
            onclick: function(e) {
                e.preventDefault();
                var self = this;
                var text = this.data('copy-value');

                navigator.clipboard.writeText(text);

                // Visual feedback: swap icon briefly
                self.removeClass('bi-copy').addClass('bi-check text-success');
                setTimeout(function() {
                    self.removeClass('bi-check text-success').addClass('bi-copy');
                }, 1500);

                return false;
            }
        });

    });
})(jQuery);
