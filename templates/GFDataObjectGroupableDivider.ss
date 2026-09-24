<script type="text/x-tmpl" class="ss-gridfield-inline-new ss-gridfield-groupable-divider-template" id="groupable_divider_template">
    <%-- group identity as data-ATTRIBUTES: survive the .clone() used for whole-group dragging --%>
    <%-- (jQuery .data() reads fall back to these when the store is absent on cloned rows) --%>
    <tr class="groupable-bound groupable-dataobject-bound {% if (o.groupId) { %}groupable-advanced-bound{% } %}" data-group-id="{%=o.groupId%}" data-group-key="{%=o.groupKey%}" data-group-name="{%=o.groupName%}">

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

            <%-- Render metadata fields based on config --%>
            {% if (o.metaFields) { for (var i=0; i < o.metaFields.length; i++) { var mf = o.metaFields[i]; %}
                {% if (mf.value) { %}
                    {% if (mf.badge) { %}
                    <span class="badge {%=mf.badgeClass || 'badge-secondary'%} ml-2">
                        {% if (mf.icon) { %}<i class="{%=mf.icon%} mr-1"></i>{% } %}
                        {%=mf.value%}
                        {% if (mf.copyable) { %}<i class="bi bi-copy ml-1 groupable-copy-btn" style="cursor:pointer;opacity:.6" title="Copy" data-copy-value="{%=mf.value%}"></i>{% } %}
                    </span>
                    {% } else { %}
                    <{%=mf.element || 'div'%} class="group-meta-{%=mf.field%} {%=mf['class'] || ''%}">{%=mf.value%}</{%=mf.element || 'div'%}>
                    {% } %}
                {% } %}
            {% } } %}
        </td>

    </tr>
</script>
