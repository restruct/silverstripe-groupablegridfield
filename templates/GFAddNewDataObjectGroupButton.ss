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
    <%-- Non-inline variant (#14): the button opens a jQuery UI dialog, the one dialog both CMS majors still ship --%>
    <%-- for non-React code (admin 2 and admin 3 bundle jQuery UI 1.13 and style .ui-dialog). Bootstrap's modal --%>
    <%-- data-api is not loaded by either admin, so the old data-toggle="modal" markup never opened anything. --%>
    <%-- client/js/groupable.js opens a copy of the hidden .ss-gridfield-add-group-dialog below and reuses createGroup(). --%>
    <button type="button"
            class="btn btn-outline-secondary ss-gridfield-add-group-modal-btn font-icon-plus"
            aria-haspopup="dialog">
        $Title
    </button>
    <div class="ss-gridfield-add-group-dialog" title="$Title.ATT" hidden>
        <input type="text"
               class="form-control ss-gridfield-new-group-name"
               placeholder="$Placeholder.ATT"
               aria-label="$GroupLabel.ATT name">
        <div class="ss-gridfield-add-group-dialog-actions mt-3">
            <button type="button" class="btn btn-primary ss-gridfield-add-group-dialog-create">
                <%t GridFieldGroupable.CreateGroup 'Create {label}' label=$GroupLabel %>
            </button>
            <button type="button" class="btn btn-outline-secondary ss-gridfield-add-group-dialog-cancel">
                <%t GridFieldGroupable.Cancel 'Cancel' %>
            </button>
        </div>
    </div>
    <% end_if %>
</div>
