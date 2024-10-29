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
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
if (!is_admin($current_user)) {
    sugar_die("Unauthorized access to administration.");
}
global $sugar_config;
// Retrieve necessary configuration values for GitHub API requests.
$token = $sugar_config['git_token'];
$owner = $sugar_config['git_repo_owner'];
$repo = $sugar_config['git_repo_nme'];
$branch = $sugar_config['git_branch'];
// Construct the URL to get commits from the specified branch of the repository.
$url = "https://api.github.com/repos/$owner/$repo/commits?sha=$branch";
// Initialize cURL session for GitHub API request.
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'User-Agent: PHP',
    'Authorization: token ' . $token
));
// Execute the cURL request and get the response.
$response = curl_exec($ch);
curl_close($ch);
// Decode the JSON response to an array.
$commits = json_decode($response, true);$sugar_smarty = new Sugar_Smarty();$sugar_smarty->assign('commits', $commits);$sugar_smarty->display('custom/modules/Administration/tpls/createPullRequest.tpl');exit;
?>
