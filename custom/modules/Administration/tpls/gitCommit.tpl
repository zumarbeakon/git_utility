<form id="gitSettings" name="gitSettings" enctype="multipart/form-data" method="POST"
      action="index.php?module=Administration&action=gitsave" class="edit view">
    <table width="100%" border="0" cellspacing="1" cellpadding="0">
        <tr>
            <th align="left" scope="row" colspan="4"><h4>GIT Credentials/Message</h4></th>
        </tr>
        <tr>
            <td scope="row" width="200">Username<spam style="color: red;">*</spam>: </td>
            <td>
                <input type="text" id="git_usersname" name="git_usersname">
                <div id="gitUserError" style="color: red;"></div>
            </td>
        </tr>
        <tr>
            <td scope="row" width="200">Token<spam style="color: red;">*</spam>: </td>
            <td>
                <input type="password" id="git_token" name="git_token">
                &nbsp;&nbsp;<a target="_blank" style="color: blue !important; text-decoration: underline !important;" href="https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens">Guide to generate access token</a>
                <div id="gitTokenError" style="color: red;"></div>
            </td>
        </tr>
        <tr>
            <td scope="row" width="200">Message<spam style="color: red;">*</spam>: </td>
            <td>
                <textarea id="message" name="message" rows="4" cols="50"></textarea>
                <div id="messageError" style="color: red;"></div>
            </td>
        </tr>
    </table>
    <div style="padding-top: 2px;">
        <input class="btn btn-primary" type="submit" value="Commit Changes">
    </div>
    <tr>
        <th align="left" scope="row" colspan="4"><spam style="margin:2px;"></spam></th>
    </tr>
    <tr>
        <td>
            <div id="errorMessages" style="color: red;"></div>
        </td>
    </tr>
    <tr>
        <th align="left" scope="row" colspan="4"><h4>Please choose files to commit</h4></th>
    </tr>
    {if $untrackedPaths|@count > 0}
    <tr>
        <th align="left" scope="row" colspan="4"></th>
    </tr>
    <tr>
        <th align="left" scope="row" colspan="4" style="margin-top:20px;"><h4>New Files:</h4></th>
    </tr>
    {foreach from=$untrackedPaths item=statuspath}
    <tr>
        <td scope="row" colspan="4"><div><input type="checkbox" name="changes[]" value="{$statuspath|escape}">&nbsp;&nbsp{$statuspath|escape}</div></td>
    </tr>
    {/foreach}
    {/if}
    {if $modifiedpath|@count > 0}
    <tr>
        <th align="left" scope="row" colspan="4"><spam style="margin:2px;"></spam></th>
    </tr>
    <tr>
        <th align="left" scope="row" colspan="4" style="margin-top:20px;"><h4>Modified Files:</h4></th>
    </tr>
    {foreach from=$modifiedpath item=statuspath}
    <tr>
        <td scope="row" colspan="4"><div><input type="checkbox" name="changes[]" value="{$statuspath|escape}">&nbsp;&nbsp{$statuspath|escape}</div></td>
    </tr>
    {/foreach}
    {/if}
    {if $deletedpath|@count > 0}
    <tr>
        <th align="left" scope="row" colspan="4"><spam style="margin:2px;"></spam></th>
    </tr>
    <tr>
        <th align="left" scope="row" colspan="4" style="margin-top:20px;"><h4>Deleted Files:</h4></th>
    </tr>
    {foreach from=$deletedpath item=statuspath}
    <tr>
        <td scope="row" colspan="4"><div><input type="checkbox" name="changes[]" value="{$statuspath|escape}">&nbsp;&nbsp{$statuspath|escape}</div></td>
    </tr>
    {/foreach}
    {/if}
</form>
{literal}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $("#gitSettings").submit(function(event) {
        let errors = [];
        let fileError = false;
        $("#errorMessages").empty();
        $("#gitUserError").empty();
        $("#gitTokenError").empty();
        $("#messageError").empty();
        
        if ($("#git_usersname").val().trim() === "") {
            errors.push("GIT User is required.");
            $("#gitUserError").text("Username is required.");
        }
        if ($("#git_token").val().trim() === "") {
            errors.push("GIT Token is required.");
            $("#gitTokenError").text("Token is required.");
        }
        if ($("#message").val().trim() === "") {
            errors.push("Message is required.");
            $("#messageError").text("Message is required.");
        }
        if ($("input[name='changes[]']:checked").length === 0) {
            fileError = true;
            $("#errorMessages").append("<p>At least one file must be selected.</p>");
        }

        if (errors.length > 0 || fileError) {
            event.preventDefault();
        }
    });
});
</script>
{/literal}
