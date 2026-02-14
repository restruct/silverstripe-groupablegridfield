<script type="text/x-tmpl" class="ss-gridfield-inline-new ss-gridfield-groupable-divider-template" id="groupable_divider_template">
    <tr class="groupable-bound groupable-dataobject-bound {% if (o.groupId) { %}groupable-advanced-bound{% } %}" data-group-id="{%=o.groupId%}">

        <td class="col-reorder">
            {% if (o.groupId) { %}
            <div class="handle ui-sortable-handle"><i class="icon font-icon-drag-handle"></i></div>
            {% } %}
        </td>

        <td colspan="$ColSpan">
            <%-- Action buttons area (floated right, must come first in DOM) --%>
            {% if (o.groupId) { %}
            <div class="group-actions float-right">
                <%-- Delete button --%>
                <button type="button"
                        title="Delete {%=o.groupName%}"
                        class="btn btn--no-text btn--icon-md font-icon-trash grid-field__icon-action ss-gridfield-group-delete"
                        data-group-id="{%=o.groupId%}"
                        data-group-name="{%=o.groupName%}"></button>
                <%-- Custom actions will be dynamically added by JavaScript based on data-groupable-actions --%>
            </div>
            {% } %}

            <span class="boundary-indicator">&darr;</span>
            {$GroupFieldLabel}:
            <%-- Title: click-to-edit when editableTitle is true --%>
            {% if (o.groupId && o.editableTitle) { %}
            <span class="group-title-editable" data-group-id="{%=o.groupId%}">
                <strong class="group-title">{%=o.groupName%}</strong>
            </span>
            <input type="text"
                   class="group-title-input form-control form-control-sm d-none"
                   style="width: auto; min-width: 200px;"
                   value="{%=o.groupName%}"
                   data-original-value="{%=o.groupName%}"
                   data-group-id="{%=o.groupId%}" />
            {% } else { %}
            <strong class="group-title" data-group-id="{%=o.groupId%}">{%=o.groupName%}</strong>
            {% } %}

            <%-- Render metadata badges if available --%>
            {% if (o.groupMeta && o.groupMeta.Code) { %}
            <span class="badge badge-secondary ml-2">{%=o.groupMeta.Code%}</span>
            {% } %}

            <%-- Render ContentSummary if available --%>
            {% if (o.groupMeta && o.groupMeta.ContentSummary) { %}
            <div class="group-content-summary text-muted small mt-1">{%=o.groupMeta.ContentSummary%}</div>
            {% } %}
        </td>

    </tr>
</script>
