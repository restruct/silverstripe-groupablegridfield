<script type="text/x-tmpl" class="ss-gridfield-inline-new ss-gridfield-groupable-divider-template" id="groupable_divider_template">
	<tr class="groupable-bound groupable-advanced-bound">

        <td class="col-reorder">
            <div class="handle ui-sortable-handle"><i class="icon font-icon-drag-handle"></i></div>
        </td>

        <td colspan="$ColSpan">
            <span class="boundary-indicator">&darr;</span>
            {$GroupFieldLabel}:
            <input type="hidden" value="{%=o.groupKey%}" placeholder="$GroupFieldLabel Key" name="$GroupsFieldNameOnSource[key][]" class="group-key" {% if (o.groupKey=='') { %}disabled{% } %} ></input>
            <input type="text" value="{%=o.groupName%}" placeholder="$GroupFieldLabel Name" name="$GroupsFieldNameOnSource[val][]" class="group-val editable-column-field text" {% if (o.groupKey=='') { %}disabled{% } %} ></input>
            {% if (o.unsavedGroupNotice) { %}<span class="alert alert-warning icon font-icon-info-circled">&nbsp;{%=o.unsavedGroupNotice%}</span>{% } %}
            <button type="button" title="Remove “{%=o.groupName%}”" class="btn btn--no-text btn--icon-md font-icon-cross-mark grid-field__icon-action float-right ss-gridfield-delete-groups-divider"></button>
        </td>

    </tr>
</script>