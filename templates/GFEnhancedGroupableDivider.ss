<script type="text/x-tmpl" class="ss-gridfield-inline-new ss-gridfield-groupable-divider-template" id="groupable_divider_template">
    <%-- group identity as data-ATTRIBUTES on the row: jQuery .data() reads fall back to these, and --%>
    <%-- unlike the .data() store they SURVIVE the .clone() used for whole-group dragging — without --%>
    <%-- them a doc dragged into a just-reordered section read an undefined groupKey (assigned 'none') --%>
	<tr class="groupable-bound groupable-advanced-bound" data-group-key="{%=o.groupKey%}" data-group-name="{%=o.groupName%}">

        <td class="col-reorder">
            <div class="handle ui-sortable-handle"><i class="icon font-icon-drag-handle"></i></div>
        </td>

        <td colspan="$ColSpan">
            <span class="boundary-indicator">&darr;</span>
            {$GroupFieldLabel}:
            <%-- inputs are namespaced under the grid name so they arrive in the grid's submitted value --%>
            <%-- (read by GridFieldGroupable::handleSave via $grid->Value()) instead of as top-level request vars --%>
            <%-- NB the unassigned divider (groupKey=='') stays disabled so it never submits --%>
            <input type="hidden" value="{%=o.groupKey%}" placeholder="$GroupFieldLabel Key" name="{$GridName}[{$GroupsFieldNameOnSource}][key][]" class="group-key" {% if (o.groupKey=='') { %}disabled{% } %} ></input>
            <input type="text" value="{%=o.groupName%}" placeholder="$GroupFieldLabel Name" name="{$GridName}[{$GroupsFieldNameOnSource}][val][]" class="group-val editable-column-field text" {% if (o.groupKey=='') { %}disabled{% } %} ></input>
            {% if (o.unsavedGroupNotice) { %}<span class="alert alert-warning icon font-icon-info-circled">&nbsp;{%=o.unsavedGroupNotice%}</span>{% } %}
            <button type="button" title="Remove “{%=o.groupName%}”" class="btn btn--no-text btn--icon-md font-icon-cross-mark grid-field__icon-action float-right ss-gridfield-delete-groups-divider"></button>
        </td>

    </tr>
</script>