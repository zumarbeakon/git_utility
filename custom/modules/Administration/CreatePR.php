<?php
/**
 *
 * @package Advanced OpenDiscovery
 * @copyright SalesAgility Ltd http://www.salesagility.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU AFFERO GENERAL PUBLIC LICENSE
 * along with this program; if not, see http://www.gnu.org/licenses
 * or write to the Free Software Foundation,Inc., 51 Franklin Street,
 * Fifth Floor, Boston, MA 02110-1301  USA
 *
 * @author Salesagility Ltd <support@salesagility.com>
 */
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (verifyGitTokenAndUsername($_REQUEST['git_token'], $_REQUEST['git_usersname'])) {
	global $sugar_config,$newBranch;
	// Retrieve GitHub username and token from POST request.
    $userName = $_POST['git_usersname'];
    $token = $_POST['git_token'];
	$owner = $sugar_config['git_repo_owner'];
    $repo = $sugar_config['git_repo_nme'];
	$title = $_POST['message'];
    $body = $_POST['message'];
    $base = 'master'; // the branch you want to merge into
    $head = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8); // the new branch with selected commits	
	$head = $repo."_".$head;
	$newBranch = $head;
	runCommand($sugar_config['GITHome']." git config --global user.email 'info@beakon.com.au'");
	runCommand($sugar_config['GITHome']." git config --global user.name '{userName}'");
	runCommand("git remote set-url origin https://{$userName}:{$token}@github.com/$owner/$repo.git");
	
	//Git Stash
	$stashCommand="git stash";
	runCommand($stashCommand);
	
	$checkoutBranchCommand = "git checkout master";
    runCommand($checkoutBranchCommand);
    $checkoutBranchCommand = "git pull origin master";
    runCommand($checkoutBranchCommand);
    $selected_commits = $_POST['commits'];
    if (empty($selected_commits)) {
        echo "No commits selected.";
        exit;
    }
    // Create a new branch
    $createBranchCommand = "git checkout -b $head";
    runCommand($createBranchCommand);	
	$pullBranchCommand = "git pull origin $head";
    // Cherry-pick selected commits
	$cherryPickCommand = $sugar_config['GITHome']." git cherry-pick ";
	$multiCommits = '';
    foreach ($selected_commits as $commit) {
		$multiCommits .= $commit." ";		
    }
	if($multiCommits){		
		$cherryPickCommand .= $multiCommits;
    runCommand($cherryPickCommand);
	
	$gitUpstream = "git push --set-upstream origin $head";
    runCommand($gitUpstream);
	}
	
    // Push new branch to origin
    $pushBranchCommand = "git push origin $head";
    runCommand($pushBranchCommand);
    
	$checkoutBranchCommand = "git checkout dev";
    runCommand($checkoutBranchCommand);
	
	//Git Stash Apply
	$stashCommandApply="git stash apply";
	
	runCommand($stashCommandApply);
	
    // Create a pull request
    $data = array(
        'title' => $title,
        'body' => $body,
        'head' => $head,
        'base' => $base
    );

    $url = "https://api.github.com/repos/$owner/$repo/pulls";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: PHP',
        'Authorization: token ' . $token,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    if (isset($result['html_url'])) {
		runCommand("git branch -D $head",true);	
		SugarApplication::appendErrorMessage("Pull request created: <a target='_blank' style='color: blue !important; text-decoration: underline !important;' href='{$result['html_url']}'>Click to View Pull Request</a>");
    } else {
		SugarApplication::appendErrorMessage("Error creating pull request: " . json_encode($result));
    }
	SugarApplication::redirect('index.php?module=Administration&action=createPullRequest');
	}else{
		SugarApplication::appendErrorMessage('Token or username are invalid or User does not have access to the repository.');
        SugarApplication::redirect('index.php?module=Administration&action=createPullRequest');
	}
}
// Execute Git commands
function runCommand($command)
{
	global $sugar_config;
    $descriptorspec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $cwd = $sugar_config['gitrepo']; // Set your repo path here
    $env = NULL;

    // Log the command being executed
    error_log("Executing command: $command");

    $resource = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

    if (is_resource($resource)) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        $status = trim(proc_close($resource));
        if ($status) {
			// Log stdout and stderr
			$checkoutBranchCommand = "git checkout dev";
			runCommandDev($checkoutBranchCommand);
			//Git Stash Apply
			$stashCommandApply="git stash apply";
			runCommandDev($stashCommandApply);

			runCommandDev("git branch -D $newBranch",true);
			
            echo $stderr;
            throw new Exception("Command failed with status $status. Stderr: $stderr. Stdout: $stdout");
        }
        return $stdout;
    } else {
        throw new Exception("proc_open failed for command: $command");
    }
}

// Execute Git commands
function runCommandDev($command)
{
	global $sugar_config;
    $descriptorspec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $cwd = $sugar_config['gitrepo']; // Set your repo path here
    $env = NULL;

    // Log the command being executed
    error_log("Executing command: $command");

    $resource = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

    if (is_resource($resource)) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        $status = trim(proc_close($resource));
        if ($status) {
			// Log stdout and stderr
            echo $stderr;
            throw new Exception("Command failed with status $status. Stderr: $stderr. Stdout: $stdout");
        }
        return $stdout;
    } else {
        throw new Exception("proc_open failed for command: $command");
    }
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
?>
