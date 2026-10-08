<%-- Bootstrap 4 (SS5 CMS) and Bootstrap 5 (SS6 CMS) utility classes are paired (float-right float-end, ml-2 ms-2, --%>
<%-- badge-secondary bg-secondary, data-toggle data-bs-toggle): each CMS styles its own and ignores the other --%>
<div class="ss-gridfield-add-new-do-group" data-create-url="$CreateURL.ATT">
    <% if $InlineInput %>
    <%-- Bootstrap 4 requires input-group-append for buttons in input groups --%>
    <%-- Bootstrap 5 (SS6 CMS) has no rules for that wrapper and wants the button as a direct child, --%>
    <%-- so the wrapper is only rendered on SS5: see GridFieldAddNewDataObjectGroupButton::usesBootstrap4InputGroup() (#14) --%>
    <div class="input-group">
        <input type="text"
               class="form-control ss-gridfield-new-group-name"
               placeholder="$Placeholder.ATT"
               aria-label="$GroupLabel.ATT name">
        <% if $UsesBootstrap4InputGroup %><div class="input-group-append"><% end_if %>
            <button type="button"
                    class="btn btn-outline-secondary ss-gridfield-create-group-btn font-icon-plus"
                    title="<%t GridFieldGroupable.CreateGroup 'Create {label}' label=$GroupLabel %>">
                $Title
            </button>
        <% if $UsesBootstrap4InputGroup %></div><% end_if %>
    </div>
    <% else %>
    <button type="button"
            class="btn btn-outline-secondary ss-gridfield-add-group-modal-btn font-icon-plus"
            data-toggle="modal" data-bs-toggle="modal"
            data-target="#add-group-modal-{$GridFieldID}" data-bs-target="#add-group-modal-{$GridFieldID}">
        $Title
    </button>
    <% end_if %>
</div>
