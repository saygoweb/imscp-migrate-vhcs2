<!-- BDP: migration_list -->
<table>
    <thead>
    <tr>
        <th>{TR_STATUS}</th>
        <th>{TR_DOMAIN_NAME}</th>
        <th>{TR_ACTION}</th>
    </tr>
    </thead>
    <tfoot>
    <tr>
        <td colspan="4">&nbsp;</td>
    </tr>
    </tfoot>
    <tbody>
    <!-- BDP: key_item -->
    <tr>
        <td><div class="icon i_{STATUS_ICON}">{DOMAIN_ID}<div></td>
        <td><label for="keyid_{DOMAIN_ID}">{DOMAIN_NAME}</label></td>
        <td><a class="icon i_edit" href="{MIGRATE_LINK}" title="{MIGRATE_INFO}">{MIGRATE}</a></td>
    </tr>
    <!-- EDP: key_item -->
    </tbody>
</table>
<div class="paginator">
    <!-- BDP: scroll_prev -->
    <a class="icon i_prev" href="migrator.php?psi={PREV_PSI}" title="{TR_PREVIOUS}">{TR_PREVIOUS}</a>
    <!-- EDP: scroll_prev -->
    <!-- BDP: scroll_prev_gray -->
    <a class="icon i_prev_gray" href="#"></a>
    <!-- EDP: scroll_prev_gray -->
    <!-- BDP: scroll_next_gray -->
    <a class="icon i_next_gray" href="#"></a>
    <!-- EDP: scroll_next_gray -->
    <!-- BDP: scroll_next -->
    <a class="icon i_next" href="migrator.php?psi={NEXT_PSI}" title="{TR_NEXT}">{TR_NEXT}</a>
    <!-- EDP: scroll_next -->
</div>
<script>
    $(function () {
        $(".link_as_button").on('click', function () {
            return confirm("{DEACTIVATE_DOMAIN_ALERT}");
        });
    });
</script>
<!-- EDP: migration_list -->
