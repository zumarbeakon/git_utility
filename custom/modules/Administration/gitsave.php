<?php
global $sugar_config;
require_once('custom/include/gitphp/Git.php');
$repo = Git::open($sugar_config['gitrepo']);
if (verifyGitTokenAndUsername($_REQUEST['git_token'], $_REQUEST['git_usersname'])) {
    if (isset($_REQUEST['changes']) && !empty($_REQUEST['changes'])) {
        foreach ($_REQUEST['changes'] as $file) {
            $repo->add($file);
        }
		// Assign GIT user to global and execute commands
        $repo->run($sugar_config['GITHome']." git config --global user.email 'info@beakon.com.au'");
        $repo->run($sugar_config['GITHome']." git config --global user.name '{$_REQUEST['git_usersname']}'");
        $repo->run("git remote set-url origin https://{$_REQUEST['git_usersname']}:{$_REQUEST['git_token']}@github.com/".$sugar_config['git_repo_owner']."/".$sugar_config['git_repo_nme'].".git");
        $repo->commit($_REQUEST['message'], false);
        $repo->push('origin', 'dev');
        $repo->run("git remote set-url origin ".$sugar_config['RemoteGitRepo']);
    }
    SugarApplication::appendErrorMessage('The selected changes have been committed into GIT repository.');
    SugarApplication::redirect('index.php?module=Administration&action=gitCommit');
} else {
    SugarApplication::appendErrorMessage('Token or username are invalid or User does not have access to the repository.');
    SugarApplication::redirect('index.php?module=Administration&action=gitCommit');
}
// Verify the provided username, token, and repository access
function verifyGitTokenAndUsername($token, $expectedUsername) {
	global $sugar_config;
    $repositoryOwner = $sugar_config['git_repo_owner'];
	$repositoryName = $sugar_config['git_repo_nme'];
	// Verify the token and username first
    $url = "https://api.github.com/user";
    $ch = curl_init($url);
    $headers = [
        'Authorization: token ' . $token,
        'User-Agent: PHP'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $userData = json_decode($response, true);
        // Check if username matches
        if ($userData['login'] === $expectedUsername) {
            
            // Now verify repository access
            $repoUrl = "https://api.github.com/repos/{$repositoryOwner}/{$repositoryName}";
            $ch = curl_init($repoUrl);
            $headers = [
                'Authorization: token ' . $token,
                'User-Agent: PHP'
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            $repoResponse = curl_exec($ch);
            $repoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // If the HTTP status code is 200, the user has access to the repository
            if ($repoHttpCode == 200) {
                return true;  // User has valid token, username matches, and has access to the repository
            } else {
                return false; // User does not have access to the repository
            }
        } else {
            return false; // Token is valid but username does not match
        }
    } else {
        return false; // Token is invalid
    }
} 