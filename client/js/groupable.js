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

                var groups = self.getGridField().data('groupable-groups'); // valid json, already parsed by jQ
                // get from add-new-button if exists, allows for dynamic updating via ajax as only the grid's CONTENT gets
                // reloaded via Ajax (not the grid itself, which thus wouldn't contain the newly added group)
                groups = $('.ss-gridfield-add-new-group').data('groups-available') ?? groups;

                // add empty/unset group based on mode
                if(groups && Object.keys( groups ).length){
                    if (mode === 'dataobject') {
                        // DataObject mode: empty group is an object with special key
                        groups[''] = { id: null, name: this.getNoGroupName() };
                    } else {
                        // Legacy mode: empty group is just a string
                        groups[''] = this.getNoGroupName();
                    }
                } else {
                    if (mode === 'dataobject') {
                        groups = { '' : { id: null, name: this.getNoGroupName() } };
                    } else {
                        groups = { '' : this.getNoGroupName() };
                    }
                }
                this.setAvailableGroups(groups);

                // get initial ID order to check if we need to update after sorting
                var initialIdOrder = self.getGridField().getItems()
                    .map(function() { return $(this).data("id"); }).get();

                // insert blockAreas boundaries
                var groupBoundElements = [];
                $.each(groups, function(groupKey, groupData) {
                    var th_tds_list = self.siblings('thead').find('tr').map(function() {
                        return $(this).find('th').length;
                    }).get();

                    // Build template data based on mode
                    var data;
                    if (mode === 'dataobject' && typeof groupData === 'object') {
                        // DataObject mode: groupData is an object with name, id, and metadata
                        data = {
                            "groupName": groupData.name || self.getNoGroupName(),
                            "groupKey": groupKey,
                            "groupId": groupData.id || null,
                            "groupMeta": groupData  // Pass full object for template access
                        };
                    } else {
                        // Legacy mode: groupData is just a string (the name)
                        data = {
                            "groupName": (groupData || self.getNoGroupName()),
                            "groupKey": groupKey,
                            "groupId": null,
                            "groupMeta": {}
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
                            var reorderData = groupOrder.map(function(id) {
                                return { name: 'group_order[]', value: id };
                            });

                            grid.reload({
                                url: grid.data("url-group-reorder"),
                                data: reorderData
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
                // (NOTE: don't call original sort callback from JS to prevent double reload, instead request gets forwarded via PHP)
                // Check if we are allowed to postback
                if (grid.data("immediate-update") && data) {
                    grid.reload({
                        //url: grid.data("url-reorder"),
                        url: grid.data("url-group-assignment"),
                        data: data
                    });
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

                var enhancedHelper = function(e, row) {
                    if(!row) return;

                    // drag multiple in group
                    if (row.hasClass('groupable-advanced-bound')) {
                        //Clone the selected items into an array
                        var drag_target = row;
                        var group_items = row.nextUntil('.groupable-advanced-bound').addBack().clone();
                        // Add a property to `row` called 'multidrag` that contains the
                        // selected items, then remove the selected items from the source list
                        row.data('multidrag', group_items).data('original',drag_target);
                        row.nextUntil('.groupable-advanced-bound').remove();

                        // Now the selected items exist in memory, attached to the `item`,
                        // so we can access them later when we get to the `stop()` callback

                        // Create the helper
                        var helper = $('<div/>');
                        return helper.append(group_items);
                    } else {
                        // return existing/regular helper
                        return mainHelper(e, row);
                    }
                };

                // Apply enhancedHelper to (OrderableRows) sortable
                this.sortable({ 'helper': enhancedHelper });
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
             */
            createGroup: function() {
                var self = this;
                var grid = this.getGridField();
                var input = this.find('.ss-gridfield-new-group-name');
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
                $.ajax({
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

    });
})(jQuery);
