<?php
/******************************************************************************************
 * The contents of this file are subject to the SOURCE CODE AGREEMENT ("License Agreement")
 * which can be viewed at http://www.letrium.com/jetmobile-agreement
 *
 * BY DOWNLOADING AND/OR INSTALLING AND/OR USING THIS FILE YOU AGREE TO BE BOUND BY THE TERMS
 * OF THIS LICENSE AGREEMENT. IF YOU DO NOT AGREE TO THE TERMS OF THIS LICENSE AGREEMENT,
 * PLEASE DO NOT DOWNLOAD, INSTALL, RUN, OR OTHERWISE USE THE SOURCES OF THE SOFTWARE.
 * Under the terms of the license, You shall not, among other things:
 * 1) permit, authorize, license or sublicense any third party to view or use
 * the Source Code; 2) sell, rent, lease, distribute, make available, publish or
 * otherwise transfer the Source Code; 3) develop Forked Software;
 * 4) use the Source Code for anything other than its intended, legitimate,
 * and legal purpose.
 *
 * You do not have the right to remove Letrium copyrights from the source code
 * or user interface.
 *
 * To the maximum extent permitted by applicable law, Letrium shall not be liable
 * to Licensee for any incidental, consequential, special, punitive or
 * indirect damages, including without limitation, damages for loss of profits,
 * business opportunity, data or use, incurred by Licensee or any third party,
 * even if it has been advised of the possibility of such damages.
 * Letrium makes no representations or warranties with respect to the Source Code.
 * All express or implied representations and warranties, including without
 * limitation any implied warranty of merchantability, of fitness for a particular
 * purpose, of reliability or availability, of accuracy or completeness of responses,
 * of results, of workmanlike effort, of lack of viruses, and of lack of negligence,
 * is hereby expressly disclaimed. Licensee specifically acknowledges that the Source Code
 * is provided "as is" and may have bugs, errors, defects or deficiencies.
 *
 * Copyright (C) 2012 Letrium ltd.; All Rights Reserved.
 ******************************************************************************************/ // Add Git Commit link at admin side
$admin_option_defs = array();
$admin_option_defs['Administration']['git_commit'] = array(
	'ConfigureTabs',
	'LBL_GIT_UTILITY',
	'LBL_GIT_UTILITY_DESC',
	'./index.php?module=Administration&action=gitCommit'
);// Add Git Create Pull Request link at admin side
$admin_option_defs['Administration']['create_pull_request'] = array(
	'ConfigureTabs',
	'LBL_GIT_PR',
	'LBL_GIT_PR_DESC',
	'./index.php?module=Administration&action=createPullRequest'
);// Add Git Merge Pull Request link at admin side 
$admin_group_header[] = array('LBL_SYSTEM_CUSTOMIZATION_TITLE', '', false, $admin_option_defs, ''); 
?>