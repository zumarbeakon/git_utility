<form id="gitSettings" action="index.php?module=Administration&action=CreatePR" method="post" class="edit view">
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
                <input type="password" id="git_token" name="git_token">&nbsp;&nbsp;<a target="_blank" style="color: blue !important; text-decoration: underline !important;" href="https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens">Guide to generate access token</a>
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
        <tr>
            <td>
                <div style="padding-top: 2px;">
                    <input class="btn btn-primary" type="submit" value="Create Pull Request">
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="4"><div id="errorMessages" style="color: red;"></div></td>
        </tr>
        <tr>
            <th align="left" scope="row" colspan="4" style="margin:50px;"><h4>Please choose commits to create a pull request</h4></th>
        </tr>
        {foreach from=$commits item=commit}
            <tr><td scope="row" colspan="4"><div><input type="checkbox" name="commits[]" value="{$commit.sha|escape}">&nbsp;&nbsp<a style="color: blue !important; text-decoration: underline !important;" href="{$commit.html_url|escape}" target="_blank">{$commit.commit.message|escape}</a></div></td></tr>
        {/foreach}
    </table>
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
        $("#titleError").empty();
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
        if ($("input[name='commits[]']:checked").length === 0) {
            fileError = true;
            $("#errorMessages").append("<p>At least one commit must be selected.</p>");
        }
        if (errors.length > 0 || fileError) {
            event.preventDefault();
        }
    });
});
</script>
{/literal}
