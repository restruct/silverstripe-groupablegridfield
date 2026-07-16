<script type="text/x-tmpl" class="ss-gridfield-inline-new ss-gridfield-groupable-divider-template" id="groupable_divider_template">
    <%-- group identity as data-ATTRIBUTES: survive the .clone() used for whole-group dragging --%>
    <%-- (jQuery .data() reads fall back to these when the store is absent on cloned rows) --%>
	<tr class="groupable-bound" data-group-key="{%=o.groupKey%}" data-group-name="{%=o.groupName%}">
        <td>&darr;</td>
        <td colspan="$ColSpan">{$GroupFieldLabel}: <strong>{%=o.groupName%}</strong></td>
    </tr>
</script>