<div class="ss-gridfield-add-new-do-group" data-create-url="$CreateURL.ATT">
    <% if $InlineInput %>
    <%-- Bootstrap 4 requires input-group-append for buttons in input groups --%>
    <div class="input-group">
        <input type="text"
               class="form-control ss-gridfield-new-group-name"
               placeholder="$Placeholder.ATT"
               aria-label="$GroupLabel.ATT name">
        <div class="input-group-append">
            <button type="button"
                    class="btn btn-outline-secondary ss-gridfield-create-group-btn font-icon-plus"
                    title="<%t GridFieldGroupable.CreateGroup 'Create {label}' label=$GroupLabel %>">
                $Title
            </button>
        </div>
    </div>
    <% else %>
    <button type="button"
            class="btn btn-outline-secondary ss-gridfield-add-group-modal-btn font-icon-plus"
            data-toggle="modal"
            data-target="#add-group-modal-{$GridFieldID}">
        $Title
    </button>
    <% end_if %>
</div>
