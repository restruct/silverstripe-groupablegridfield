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

            //onmatch: function() {
            onadd: function() {
                var self = this; // this & self are already a jQuery obj

                this.setItemGroupField( self.getGridField().data('groupable-itemfield') );

                var groupRole = self.getGridField().data('groupable-role');
                if(groupRole){ this.setGroupRole( groupRole ); }

                var noGroupName = self.getGridField().data('groupable-unassigned');
                if(noGroupName){ this.setNoGroupName( noGroupName ); }

                var groups = self.getGridField().data('groupable-groups'); // valid json, already parsed by jQ
                // get from add-new-button if exists, allows for dynamic updating via ajax as only the grid's CONTENT gets
                // reloaded via Ajax (not the grid itself, which thus wouldn't contain the newly added group)
                groups = $('.ss-gridfield-add-new-group').data('groups-available') ?? groups;

                // add empty/unset group
                if(groups && Object.keys( groups ).length){
                    groups[''] = this.getNoGroupName();
                } else {
                    groups = { '' : this.getNoGroupName() }
                }
                this.setAvailableGroups(groups);

                // get initial ID order to check if we need to update after sorting
                var initialIdOrder = self.getGridField().getItems()
                    .map(function() { return $(this).data("id"); }).get();

                // insert blockAreas boundaries
                var groupBoundElements = [];
                $.each(groups, function(groupKey, groupName) {
                    var th_tds_list = self.siblings('thead').find('tr').map(function() {
                        return $(this).find('th').length;
                    }).get();

                    var data = {
                        "groupName": (groupName || self.getNoGroupName()),
                        "groupKey" : groupKey,
                    };
                    var boundTmpl = window.tmpl('groupable_divider_template', data);
                    var boundEl = $(boundTmpl);

                    boundEl.data('groupKey', data.groupKey); // used for assigning group to item when dragging into group
                    boundEl.data('groupName', data.groupName); // make available for convenience
                    groupBoundElements[groupKey] = boundEl;
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

                // if a boundary/whole group was dragged...
                if (ui.item.hasClass('groupable-advanced-bound')) {
                    // insert items at helper position and remove helper (<div>)
                    ui.item.after(ui.item.data('multidrag'));
                    // remove original
                    ui.item.data('original').remove();
                    // and remove dragged container div
                    ui.item.remove();
                }

                // set correct area on row/item areaselect
                var groupKey = ui.item.prevAll('.groupable-bound').first().data('groupKey');
                // $('.col-'+ this.getItemGroupField(),ui.item).find('select, input').val(groupKey);
                $('.col-reorder .ss-groupable-hidden-group',ui.item).val(groupKey);

                // Save group on object/rel
                // duplicated from GridFieldOrderableRows.onadd.update:
                var grid = this.getGridField();
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
                    ss.i18n._t("GridFieldExtensions.CONFIRMDEL", "Are you sure you want to delete “%s”?"),
                    this.closest('.groupable-bound').first().data('groupName')
                );
                if(confirm(msg)) {
                    this.parents("tr.groupable-advanced-bound:first").remove();

                }

                return false;
            }
        });

    });
})(jQuery);
