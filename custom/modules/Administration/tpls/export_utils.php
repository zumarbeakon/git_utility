<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

/**
 * gets the system default delimiter or an user-preference based override
 * @return string the delimiter
 */
function getDelimiter() {
    global $sugar_config;
    global $current_user;

    if (!empty($sugar_config['export_excel_compatible'])) {
        return "\t";
    }

    $delimiter = ','; // default to "comma"
    $userDelimiter = $current_user->getPreference('export_delimiter');
    $delimiter = empty($sugar_config['export_delimiter']) ? $delimiter : $sugar_config['export_delimiter'];
    $delimiter = empty($userDelimiter) ? $delimiter : $userDelimiter;

    return $delimiter;
}

function get_listview_fields($module){
    global $db, $app_list_strings, $sugr_config, $beanList;
//    $module = $_REQUEST['export_module'];
    $view = 'DetailView';
    $EMBean = BeanFactory::getBean($module);
    //List View Fields
    $listviewFile = get_custom_file_if_exists("modules/$module/metadata/listviewdefs.php");
    require_once($listviewFile);
    $listViewFields = $listViewDefs[$module];
    $export_fields = array();
    foreach($listViewFields as $field_index => $field){
        $dbname = strtolower($field_index);
        $export_fields[$dbname] = $field['label'];
    }

    $field_all_list = array();

    $unset = array();
    $skip_fields = array('view_pdf_c');
    $field_all_list['core_module']['label'] = $app_list_strings['moduleList'][$module];
    foreach($export_fields as $name => $label){
        if(!empty($EMBean->field_defs[$name])){
            $arr = $EMBean->field_defs[$name];
            if($arr['type'] != 'link' && $arr['type'] != 'file' && !in_array($name,$skip_fields) && ((!isset($arr['source']) || $arr['source'] != 'non-db') || (($arr['type'] == 'relate' || $arr['type'] == 'parent') && isset($arr['id_name']))) && (empty($valid) || in_array($arr['type'], $valid)) && $arr['source'] != 'function') {
                $t_label = rtrim(translate($label, $EMBean->module_dir), ':');
                if (empty($t_label) && isset($arr['vname']) && $arr['vname'] != '') {
                    $t_label = rtrim(translate($arr['vname'], $EMBean->module_dir), ':');
                }
                $field_all_list['core_module']['fields'][$name] = $t_label;
                if (($arr['type'] == 'relate' || $arr['type'] == 'parent') && isset($arr['id_name']) && $arr['id_name'] != '') {
                    $unset[] = $arr['id_name'];
                }
            }
        }
    }
    foreach($unset as $name){
        if(isset($fields[$name])) unset( $fields[$name]);
    }
    return implode("," ,array_flip($field_all_list['core_module']['fields']));
}

/**
 * builds up a delimited string for export
 * @param string type the bean-type to export
 * @param array records an array of records if coming directly from a query
 * @return string delimited string for export
 */
function export($type, $records = null, $members = false, $sample=false) {
	echo "work in progress";
	exit;
    require_once"modules/AOW_WorkFlow/aow_utils.php";
    global $locale;
    global $beanList;
    global $beanFiles;
    global $current_user;
    global $app_strings;
    global $app_list_strings;
    global $timedate;
    global $mod_strings;
    global $current_language;
    $sampleRecordNum = 5;

    //Array of fields that should not be exported, and are only used for logic
    $remove_from_members = array("ea_deleted", "ear_deleted", "primary_address");
    $focus = 0;

    $bean = $beanList[$type];
    require_once($beanFiles[$bean]);
    $focus = new $bean;
    $searchFields = array();
    $db = DBManagerFactory::getInstance();

    if($records) {
        $records = explode(',', $records);
        $records = "'" . implode("','", $records) . "'";
        $where = "{$focus->table_name}.id in ($records)";
    } elseif (isset($_REQUEST['all']) ) {
        $where = '';
    } else {
        if(!empty($_REQUEST['current_post'])) {
        	/* Roman - 10.10.2019 - Start */
            // Deleting empty search parameters and relate non-db fields to make sure search_where woun't have empty fields conditions or conditions on non-existing fields
            $curr_post = unserialize(base64_decode($_REQUEST['current_post']));
            foreach ($curr_post as $field_name => $field_value) {
                if (strpos($field_name, '_basic')) {
                    $f_name = str_replace('_basic', '', $field_name);
                    if ($field_value === '' || (($focus->field_defs[$f_name]['type'] == 'relate' || $focus->field_defs[$f_name]['type'] == 'parent') && $focus->field_defs[$f_name]['source'] == 'non-db'))
                        unset($curr_post[$field_name]);
                    else if (is_array($field_value)) {
                        $empty = true;
                        foreach ($field_value as $f_key => $f_value) {
                            if ($f_value !== '') {
                                $empty = false;
                                break ;
                            }
                        }
                        if ($empty === true)
                            unset($curr_post[$field_name]);
                    }
                }
            }
            $_REQUEST['current_post'] = base64_encode(serialize($curr_post));
            /* Roman - 10.10.2019 - End */
            $ret_array = generateSearchWhere($type, $_REQUEST['current_post']);
            $where = $ret_array['where'];
            $searchFields = $ret_array['searchFields'];
        } else {
            $where = '';
        }
    }

    $order_by = "";
    if($focus->bean_implements('ACL')){
        if(!ACLController::checkAccess($focus->module_dir, 'export', true)){
            ACLController::displayNoAccess();
            sugar_die('');
        }
        if(ACLController::requireOwner($focus->module_dir, 'export')){
            if(!empty($where)){
                $where .= ' AND ';
            }
            $where .= $focus->getOwnerWhere($current_user->id);
        }
		/* BEGIN - SECURITY GROUPS */
    	if(ACLController::requireSecurityGroup($focus->module_dir, 'export') )
    	{
			require_once('modules/SecurityGroups/SecurityGroup.php');
    		global $current_user;
    		$owner_where = $focus->getOwnerWhere($current_user->id);
	    	$group_where = SecurityGroup::getGroupWhere($focus->table_name,$focus->module_dir,$current_user->id);
	    	if(!empty($owner_where)) {
				if(empty($where))
	    		{
	    			$where = " (".  $owner_where." or ".$group_where.")";
	    		} else {
	    			$where .= " AND (".  $owner_where." or ".$group_where.")";
	    		}
			} else {
				if(!empty($where)){
					$where .= ' AND ';
				}
				$where .= $group_where;
			}
    	}
    	/* END - SECURITY GROUPS */

    }
    // Export entire list was broken because the where clause already has "where" in it
    // and when the query is built, it has a "where" as well, so the query was ill-formed.
    // Eliminating the "where" here so that the query can be constructed correctly.

    if($members == true){
           $query = $focus->create_export_members_query($records);
    }else{

		require_once('modules/AOW_WorkFlow/aow_utils.php');

        $beginWhere = substr(trim($where), 0, 5);

        if ($beginWhere == "where")
            $where = substr(trim($where), 5, strlen($where));

        $query = $focus->create_export_query($order_by,$where);

        if (empty($_REQUEST['fields_tree'])){
            $_REQUEST['fields_tree'] = get_listview_fields($_REQUEST['module']);
        }

		if(!empty($_REQUEST['fields_tree'])){
			$filter = explode(',', $_REQUEST['fields_tree']);
			if(!is_array($query)){
				$query_list = $focus->create_new_list_query($order_by, $where, $filter, array(), 0, '', true, $focus, true, true);
			}
			$query = '';
			if(in_array('parent_name', $filter)) $filter[] = 'parent_type';
			$query_array = build_export_query_select($focus, $filter);
			if(isset($extra['where']) && $extra['where']) {
				$query_array['where'][] = implode(' AND ', $extra['where']) . ' AND ';
			}

			foreach ($query_array['select'] as $select){
				$query .=  ($query == '' ? 'SELECT ' : ', ').$select;
			}

			$query .= ' FROM '.$focus->table_name.' ';

 			if(isset($query_array['join'])){
				foreach ($query_array['join'] as $join){
					$query .= $join;
				}
			}

			if($type == "Bkn_Operator_Skills"){
			// $query  .= 'LEFT JOIN bkn_operator_skills_cstm ON bkn_operator_skills.id = bkn_operator_skills_cstm.id_c';
              $query  .= 'LEFT JOIN bmd_jobs jt0 ON bkn_operator_skills_cstm.bmd_jobs_id_c = jt0.id AND jt0.deleted=0';
			 }

			if(!empty($where)){
				$query_where = '';
				$query_where .=  ($query_where == '' ? 'WHERE ' : ' ').$where;
				$query .= ' '.$query_where;
			}



			/* Yura 11.02.2019 start */
			// add 'deleted' to WHERE
			if (strpos($query,"{$focus->table_name}.deleted") === false){
				if (empty($where))
					$query .= ' WHERE ';
				else
					$query .= ' AND ';
				$query .= "{$focus->table_name}.deleted = 0";
			}

			/* Yura 11.02.2019 end */
		}
    }
	$result = '';
	$populate = false;
    if($sample) {
       $result = $db->limitQuery($query, 0, $sampleRecordNum, true, $app_strings['ERR_EXPORT_TYPE'].$type.": <BR>.".$query);
        if( $focus->_get_num_rows_in_query($query)<1 ){
            $populate = true;
        }
    }
    else {
        $result = $db->query($query, true, $app_strings['ERR_EXPORT_TYPE'].$type.": <BR>.".$query);
    }


    $fields_array = $db->getFieldsArray($result);

    //set up labels to be used for the header row
    $field_labels = array();
	$focus_orig =  $focus;
	$module_old =  $focus->module_dir;
	$prepend = '';
	global $app_list_strings;

    foreach($fields_array as $key=>$dbname){
        //Remove fields that are only used for logic
        if($members && (in_array($dbname, $remove_from_members)))
            continue;
		if ($dbname == 'id_for_parent')
            continue;
        //default to the db name of label does not exist
		$f_pro = explode(':', $dbname);
		$dbname = $f_pro[0];
		if(sizeof($f_pro) > 1 && $module_old !=  $f_pro[1]){
			$focus = BeanFactory::getBean($f_pro[1]);
			$module_old = $module_lbl = $f_pro[1];
			if(!empty($app_list_strings['moduleList'][$module_old])){
				$module_lbl = $app_list_strings['moduleList'][$module_old];
			}
			$prepend = $module_lbl.':';
		}
		$field_labels[$key] = $prepend.translateForExport($dbname,$focus);
    }
	$focus = $focus_orig;
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if ($locale->getExportCharset() == 'UTF-8' &&
        ! preg_match('/macintosh|mac os x|mac_powerpc/i', $user_agent)) // Bug 60377 - Mac Excel doesn't support UTF-8
    {
        //Bug 55520 - add BOM to the exporting CSV so any symbols are displayed correctly in Excel
        $BOM = "\xEF\xBB\xBF";
        $content = $BOM;
    }
    else
    {
        $content = '';
    }

    if(isset($_REQUEST['customLogic']) && $_REQUEST['customLogic']){
	    if(in_array('bmd_jobs_id_c', $field_labels)){
	    	unset($field_labels[array_search('bmd_jobs_id_c', $field_labels)]);
			$field_labels = array_merge(array_slice($field_labels, 0, 3, true), array("Job ID", "Work ID"), array_slice($field_labels, 3, count($field_labels)-3, true));
		}
	}
    // setup the "header" line with proper delimiters
    $content .= "\"".implode("\"".getDelimiter()."\"", array_values($field_labels))."\"\r\n";
    $pre_id = '';

    if($populate){
        //this is a sample request with no data, so create fake datarows
         $content .= returnFakeDataRow($focus,$fields_array,$sampleRecordNum);
    }else{
        $records = array();

        //process retrieved record
    	while($val = $db->fetchByAssoc($result, false)) {

			if($type == "rreg_RiskRegister" && isset($val['risk_sub_owner']) && !empty($val['risk_sub_owner'])){
				$risk_own_name = array();
				$risk_own = explode('^,^',$val['risk_sub_owner']);
				foreach($risk_own as $risk_owns){
					$bean_users_risk = BeanFactory::getBean('Users', trim($risk_owns,'^'));
					$risk_own_name[] = $bean_users_risk->full_name;
				}
				$val['risk_sub_owner'] = implode(',',$risk_own_name);
			}
        	if ($members)
			$focus = BeanFactory::getBean($val['related_type']);
			$new_arr = array();
			if($members){
				if($pre_id == $val['id'])
					continue;
				if($val['ea_deleted']==1 || $val['ear_deleted']==1){
					$val['primary_email_address'] = '';
				}
				unset($val['ea_deleted']);
				unset($val['ear_deleted']);
				unset($val['primary_address']);
			}
			$pre_id = $val['id'];
			
			if (isset($val['id_for_parent'])){
                $id_for_parent = $val['id_for_parent'];
                unset($val['id_for_parent']);
            }
			foreach ($val as $key => $value)
			{

				//getting content values depending on their types
				// $fieldNameMapKey = $fields_array[$key];
				$fieldNameMapKey = $key;
				$f_pro = explode(':', $fieldNameMapKey);
				if(sizeof($f_pro) > 1){
					$f_pro_bean = BeanFactory::getBean($f_pro[1]);
					if(!empty($value)){
						$_REQUEST['action'] = 'Export';
						if ($f_pro_bean){
                            $att = $f_pro_bean->field_name_map[$f_pro[0]];
                            if ($att['type'] == 'parent'){
                                $value = getModuleField($f_pro[1], $att['id_name'], $att['id_name'], 'DetailView', $value, '', '', array('record_id' => $id_for_parent));
                            }
                            else{
                                $value = getModuleField($f_pro[1], $f_pro[0], $f_pro[0], 'DetailView', $value);
                            }
                        }
                        else{
                            $value = getModuleField($f_pro[1], $f_pro[0], $f_pro[0], 'DetailView', $value);
                        }
						unset($_REQUEST['action']);
					}
				}
				if (isset($focus->field_name_map[$fieldNameMapKey])  && $focus->field_name_map[$fieldNameMapKey]['type'])
				{
					$fieldType = $focus->field_name_map[$fieldNameMapKey]['type'];

					switch ($fieldType)
					{
						//if our value is a currency field, then apply the users locale
						case 'currency':
							require_once('modules/Currencies/Currency.php');
							$value = currency_format_number($value);
							break;
						case 'parent':
						if(!empty($value)){
							$att = $focus->field_name_map[$fieldNameMapKey];
							$_REQUEST['action'] = 'Export';
							$value = getModuleField($focus->module_dir, $att['id_name'], $att['id_name'], 'DetailView', $value, '', '', array('record_id' => $id_for_parent));
							unset($_REQUEST['action']);
						}
						break;
						case 'relate':
							if(!empty($value)){
								$att = $focus->field_name_map[$fieldNameMapKey];
								$_REQUEST['action'] = 'Export';
								$value = getModuleField($focus->module_dir, $att['name'], $att['name'], 'DetailView', $value);
								unset($_REQUEST['action']);
							}
							break;
						//if our value is a datetime field, then apply the users locale
						case 'datetime':
						case 'datetimecombo':
							if (!empty($value) && $value != '0000-00-00 00:00:00'){
								$value = $timedate->to_display_date_time($db->fromConvert($value, 'datetime'));
								$value = preg_replace('/([pm|PM|am|AM]+)/', ' \1', $value);
							}
							else{
								$value = '';
							}
							break;

						//kbrill Bug #16296
						case 'date':
							if (!empty($value) && $value != '0000-00-00'){
								$value = $timedate->to_display_date($db->fromConvert($value, 'date'), false);
							}
							else{
								$value = '';
							}
							break;

						// Bug 32463 - Properly have multienum field translated into something useful for the client
						case 'multienum':
							$valueArray = unencodeMultiEnum($value);
                            if(isset($focus->field_name_map[$fieldNameMapKey]['function'])){
                                $app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']] = $focus->field_name_map[$fieldNameMapKey]['function']();
                            }
							if (isset($focus->field_name_map[$fieldNameMapKey]['options']) && isset($app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']]) )
							{
								foreach ($valueArray as $multikey => $multivalue )
								{
									if (isset($app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']][$multivalue]) )
									{
										$valueArray[$multikey] = $app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']][$multivalue];
									}
								}
							}
							$value = implode(",",$valueArray);
							break;
                        case 'MultiRelate':
                            $valueArray = unencodeMultiEnum($value);
                            if (isset($focus->field_name_map[$fieldNameMapKey]['related_module']) ){
                                foreach ($valueArray as $multikey => $multivalue ){
                                    $relate_module = $focus->field_name_map[$fieldNameMapKey]['related_module'];
                                    $relate_module = BeanFactory::getBean($relate_module);
                                    $relate_module->retrieve($multivalue);
                                    $valueArray[$multikey] = $relate_module->name;
                                }
                            }
                            $value = implode(",",$valueArray);
                            break;
						case 'enum':
							if (	isset($focus->field_name_map[$fieldNameMapKey]['options']) &&
								isset($app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']]) &&
								isset($app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']][$value])
							)
								$value = $app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']][$value];

							break;
						case 'dynamicenum':
							if (	isset($focus->field_name_map[$fieldNameMapKey]['options']) &&
								isset($app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']]) &&
								isset($app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']][$value])
							)
								$value = $app_list_strings[$focus->field_name_map[$fieldNameMapKey]['options']][$value];

							break;
                        case 'MatrixSelect':
                            $value = matrix_dispay_value($value);

                            break;
						/* Yura 11.02.2019 start */
						// remove new lines from text fields
						case 'text':
							$value = preg_replace("/\r|\n/", " ", $value);

							break;
						/* Yura 11.02.2019 end */
					}
				}



				// Yura 13.05.2024 - fix value for proper csv column
				$value = ltrim($value,"=+-@\t\r\n");
				$value = html_entity_decode($value, ENT_QUOTES);
				$value = strip_tags($value);
				$value = preg_replace("/\r|\n/", " ", $value);
				$value = preg_replace("/\"/","\"\"", $value);

				// Keep as $key => $value for post-processing
				$new_arr[$key] = $value;
			}

			// Use Bean ID as key for records
			$records[] = $new_arr;
		}

		// Check if we're going to export non-primary emails
        if ($focus->hasEmails() && in_array('email_addresses_non_primary', $fields_array))
        {
            // $records keys are bean ids
            $keys = array_keys($records);

            // Split the ids array into chunks of size 100
            $chunks = array_chunk($keys, 100);

            foreach ($chunks as $chunk)
            {
                // Pick all the non-primary mails for the chunk
                $query =
                    "
                      SELECT eabr.bean_id, ea.email_address
                      FROM email_addr_bean_rel eabr
                      LEFT JOIN email_addresses ea ON ea.id = eabr.email_address_id
                      WHERE eabr.bean_module = '{$focus->module_dir}'
                      AND eabr.primary_address = '0'
                      AND eabr.bean_id IN ('" . implode("', '", $chunk) . "')
                      AND eabr.deleted != '1'
                      ORDER BY eabr.bean_id, eabr.reply_to_address, eabr.primary_address DESC
                    ";

                $result = $db->query($query, true, $app_strings['ERR_EXPORT_TYPE'] . $type . ": <BR>." . $query);

                while ($val = $db->fetchByAssoc($result, false)) {
                    if (empty($records[$val['bean_id']]['email_addresses_non_primary'])) {
                        $records[$val['bean_id']]['email_addresses_non_primary'] = $val['email_address'];
                    } else {
                        // No custom non-primary mail delimeter yet, use semi-colon
                        $records[$val['bean_id']]['email_addresses_non_primary'] .= ';' . $val['email_address'];
                    }
                }
            }
        }

        foreach($records as $record)
        {
        	if(isset($_REQUEST['customLogic']) && $_REQUEST['customLogic']){
	        	if(isset($record['bmd_jobs_id_c'])){
	        		$jobsId     = $record['bmd_jobs_id_c'];
	        		$jobDetails = getJobDetails($jobsId);
	        		unset($record['bmd_jobs_id_c']);
	        		if(isset($jobDetails['job_id']) && $jobDetails['job_id']!=""){
	        			$record = array_merge(array_slice($record, 0, 3, true), $jobDetails, array_slice($record, 3, count($record)-3, true));
	        		} else {
	        			$record = array_merge(array_slice($record, 0, 3, true), array("job_id"=>"","work_id"=>""), array_slice($record, 3, count($record)-3, true));
	        		}
	        	}
	        }
			if(isset($record['parent_name']) && isset($record['parent_type'])){
				$parent_bean = BeanFactory::getBean($record['parent_type'], $record['parent_name']);
				$record['parent_name'] = $parent_bean->name;
				unset($record['parent_type']);
			}
            $line = implode("\"" . getDelimiter() . "\"", $record);
            $line = "\"" . $line;
            $line .= "\"\r\n";
            $content .= $line;
        }

    }
	return $content;

}
function build_export_query_select($module, $fields, $query = array())
{
	global $beanList, $db;

	foreach($fields as $field_pro){
		//field:module:relation:label
		$f_pro = explode(':', $field_pro);
		$field = $f_pro[0];
		$label =  (isset($f_pro[1])) ? $f_pro[0].':'.$f_pro[1] : $field;
		$field_module = $module;
		$table_alias = $field_module->table_name;
        $oldAlias = $table_alias;
		if(!empty($f_pro[1]) && $f_pro[1] != $module->module_dir){
			$rel = $f_pro[2];
			$new_field_module = new $beanList[getRelatedModule($field_module->module_dir,$rel)];
			$oldAlias = $table_alias;
			$table_alias = $table_alias.":".$rel;
			$query = build_export_query_join($rel, $table_alias, $oldAlias, $field_module, 'relationship', $query, $new_field_module);
			$field_module = $new_field_module;
		}
		$data = $field_module->field_defs[$field];
		if(($data['type'] == 'relate' || $data['type'] == 'parent') && isset($data['id_name'])) {
			$field = $data['id_name'];
			$data_new = $field_module->field_defs[$field];
			if(isset($data_new['source']) && $data_new['source'] == 'non-db' && $data_new['type'] != 'link' && isset($data['link'])){
				$data_new['type'] = 'link';
				$data_new['relationship'] = $data['link'];
			}
			$data = $data_new;
		}
		if($data['type'] == 'parent' && isset($data['type_name']) && isset($data['id_name'])){
            $field = $data['id_name'];
            $query['select'][] = $db->quoteIdentifier($table_alias).'.id as id_for_parent';
        }
		if($data['type'] == 'link' && $data['source'] == 'non-db') {
			$new_field_module = new $beanList[getRelatedModule($field_module->module_dir,$data['relationship'])];
			$table_alias = $data['relationship'];
			$query = build_export_query_join($data['relationship'],$table_alias, $oldAlias, $field_module, 'relationship', $query, $new_field_module);
			$field_module = $new_field_module;
			$field = 'id';
		}

		if((isset($data['source']) && $data['source'] == 'custom_fields')) {
			$table_alias = trim($table_alias, '`');
			$select_field = $db->quoteIdentifier($table_alias.'_cstm').'.`'.$field."`";
			$query = build_export_query_join($table_alias.'_cstm', $table_alias.'_cstm',$table_alias, $field_module, 'custom', $query);
		} else {
			$select_field= $db->quoteIdentifier($table_alias).'.'.$field;
        }
        $query['select'][] = $select_field ." AS '".$label."'";

	}
    // BW-471 Roman - 17.02.2021 - Do not join custom table if modules has no custom fields
    if ($module->hasCustomFields()) {
        $query = build_export_query_join($module->table_name.'_cstm', $module->table_name.'_cstm', $module->table_name, $module, 'custom', $query);
    }
	return $query;
}
function build_export_query_join($name, $alias, $parentAlias, SugarBean $module, $type, $query = array(),SugarBean $rel_module = null ){
    global $db;
    if(!isset($query['join'][$alias])){

        switch ($type){
            case 'custom':
                $query['join'][$alias] = 'LEFT JOIN '.$db->quoteIdentifier($module->get_custom_table_name()).' '.$db->quoteIdentifier($name).' ON '.$db->quoteIdentifier($parentAlias).'.id = '. $db->quoteIdentifier($name).'.id_c ';
                break;

            case 'relationship':
                if($module->load_relationship($name)){
                    $params['join_type'] = 'LEFT JOIN';
                    if($module->$name->relationship_type != 'one-to-many'){
                        if($module->$name->getSide() == REL_LHS){
                            $params['right_join_table_alias'] = $db->quoteIdentifier($alias);
                            $params['join_table_alias'] = $db->quoteIdentifier($alias);
                            $params['left_join_table_alias'] = $db->quoteIdentifier($parentAlias);
                        }else{
                            $params['right_join_table_alias'] = $db->quoteIdentifier($parentAlias);
                            $params['join_table_alias'] = $db->quoteIdentifier($alias);
                            $params['left_join_table_alias'] = $db->quoteIdentifier($alias);
                        }

                    }else{
                        $params['right_join_table_alias'] = $db->quoteIdentifier($parentAlias);
                        $params['join_table_alias'] = $db->quoteIdentifier($alias);
                        $params['left_join_table_alias'] = $db->quoteIdentifier($parentAlias);
                    }
                    $linkAlias = $parentAlias."|".$alias;
                    $params['join_table_link_alias'] = $db->quoteIdentifier($linkAlias);
                    $join = $module->$name->getJoin($params, true);
                    $query['join'][$alias] = $join['join'];
                    if($rel_module != null) {
                        $query['join'][$alias] .= build_export_access_query($rel_module, $name);
                    }
                   //$query['select'][] = $join['select']." AS '".$alias."_id'";
                }
                break;
            default:
                break;

        }

    }
    return $query;
}

function build_export_access_query(SugarBean $module, $alias){

    $where = '';
    if($module->bean_implements('ACL') && ACLController::requireOwner($module->module_dir, 'list') )
    {
        global $current_user;
        $owner_where = $module->getOwnerWhere($current_user->id);
        $where = ' AND '.$owner_where;

    }

    if(file_exists('modules/SecurityGroups/SecurityGroup.php')){
        /* BEGIN - SECURITY GROUPS */
        if($module->bean_implements('ACL') && ACLController::requireSecurityGroup($module->module_dir, 'list') )
        {
            require_once('modules/SecurityGroups/SecurityGroup.php');
            global $current_user;
            $owner_where = $module->getOwnerWhere($current_user->id);
            $group_where = SecurityGroup::getGroupWhere($alias,$module->module_dir,$current_user->id);
            if(!empty($owner_where)){
                $where .= " AND (".  $owner_where." or ".$group_where.") ";
            } else {
                $where .= ' AND '.  $group_where;
            }
        }
        /* END - SECURITY GROUPS */
    }

    return $where;
}



function generateSearchWhere($module, $query) {//this function is similar with function prepareSearchForm() in view.list.php

	$seed = loadBean($module);
    if(file_exists('modules/'.$module.'/SearchForm.html')){
        if(file_exists('modules/' . $module . '/metadata/SearchFields.php')) {
            require_once('include/SearchForm/SearchForm.php');
            $searchForm = new SearchForm($module, $seed);

        }

        elseif(!empty($_SESSION['export_where'])) { //bug 26026, sometimes some module doesn't have a metadata/SearchFields.php, the searchfrom is generated in the ListView.php.
        // Currently, massupdate will not generate the where sql. It will use the sql stored in the SESSION. But this will cause bug 24722, and it cannot be avoided now.
            $where = $_SESSION['export_where'];
            $whereArr = explode (" ", trim($where));
            if ($whereArr[0] == trim('where')) {
                $whereClean = array_shift($whereArr);
            }
            $where = implode(" ", $whereArr);
            //rrs bug: 31329 - previously this was just returning $where, but the problem is the caller of this function
            //expects the results in an array, not just a string. So rather than fixing the caller, I felt it would be best for
            //the function to return the results in a standard format.
            $ret_array['where'] = $where;
            $ret_array['searchFields'] =array();
            return $ret_array;
        }
        else {
            return;
        }
    }
    else{
        require_once('include/SearchForm/SearchForm2.php');

        if(file_exists('custom/modules/'.$module.'/metadata/metafiles.php')){
            require('custom/modules/'.$module.'/metadata/metafiles.php');
        }elseif(file_exists('modules/'.$module.'/metadata/metafiles.php')){
            require('modules/'.$module.'/metadata/metafiles.php');
        }

        if (file_exists('custom/modules/'.$module.'/metadata/searchdefs.php'))
        {
            require_once('custom/modules/'.$module.'/metadata/searchdefs.php');
        }
        elseif (!empty($metafiles[$module]['searchdefs']))
        {
            require_once($metafiles[$module]['searchdefs']);
        }
        elseif (file_exists('modules/'.$module.'/metadata/searchdefs.php'))
        {
            require_once('modules/'.$module.'/metadata/searchdefs.php');
        }

        //fixing bug #48483: Date Range search on custom date field then export ignores range filter
        // first of all custom folder should be checked
        if(file_exists('custom/modules/'.$module.'/metadata/SearchFields.php'))
        {
            require_once('custom/modules/'.$module.'/metadata/SearchFields.php');
        }
        elseif(!empty($metafiles[$module]['searchfields']))
        {
            require_once($metafiles[$module]['searchfields']);
        }
        elseif(file_exists('modules/'.$module.'/metadata/SearchFields.php'))
        {
            require_once('modules/'.$module.'/metadata/SearchFields.php');
        }
        if(empty($searchdefs) || empty($searchFields)) {
           //for some modules, such as iframe, it has massupdate, but it doesn't have search function, the where sql should be empty.
            return;
        }
        $searchForm = new SearchForm($seed, $module);
        $searchForm->setup($searchdefs, $searchFields, 'SearchFormGeneric.tpl');
    }
	    if(!empty($_SESSION['export_where']) && $module == "Bkn_Operator_Skills"){
            $where = $_SESSION['export_where'];
            $whereArr = explode (" ", trim($where));
            if ($whereArr[0] == trim('where')) {
                $whereClean = array_shift($whereArr);
            }
            $where = implode(" ", $whereArr);
            $ret_array['where'] = $where;
            $ret_array['searchFields'] =array();
			//echo "<pre>";print_r($ret_array);echo "</pre>";die;
            return $ret_array;
	    }else{
			  $searchForm->populateFromArray(unserialize(base64_decode($query)));
			  $where_clauses = $searchForm->generateSearchWhere(true, $module);
			/* Simbanic Start */
			  $where_clauses = user_export_set_where(unserialize(base64_decode($query)),$where_clauses);
			/* Simbanic End */
			if (count($where_clauses) > 0 )
				$where = '('. implode(' ) AND ( ', $where_clauses) . ')';
				$GLOBALS['log']->info("Export Where Clause: {$where}");
			$ret_array['where'] = $where;

			$ret_array['searchFields'] = $searchForm->searchFields;
			return $ret_array;
        }
}
/**
  * calls export method to build up a delimited string and some sample instructional text on how to use this file
  * @param string type the bean-type to export
  * @return string delimited string for export with some tutorial text
  */
     function exportSample($type) {
         global $app_strings;

         //first grab the
         $_REQUEST['all']=true;

         //retrieve the export content
         $content = export($type, null, false, true);

         // Add a new row and add details on removing the sample data
         // Our Importer will stop after he gets to the new row, ignoring the text below
         return $content . "\n" . $app_strings['LBL_IMPORT_SAMPLE_FILE_TEXT'];

     }
 //this function will take in the bean and field mapping and return a proper value
 function returnFakeDataRow($focus,$field_array,$rowsToReturn = 5){

    if(empty($focus) || empty($field_array))
     return ;

     //include the file that defines $sugar_demodata
     include('install/demoData.en_us.php');

    $person_bean = false;
    if( isset($focus->first_name)){
        $person_bean = true;
    }

     global $timedate;
     $returnContent = '';
     $counter = 0;
     $new_arr = array();

     //iterate through the record creation process as many times as defined.  Each iteration will create a new row
     while($counter < $rowsToReturn){
         $counter++;
         //go through each field and populate with dummy data if possible
         foreach($field_array as $field_name){

            if(empty($focus->field_name_map[$field_name]) || empty($focus->field_name_map[$field_name]['type'])){
                //type is not set, fill in with empty string and continue;
                $returnContent .= '"",';
                continue;
            }
            $field = $focus->field_name_map[$field_name];
                         //fill in value according to type
            $type = $field['type'];

             switch ($type) {

                 case "id":
                 case "assigned_user_name":
                     //return new guid string
                    $returnContent .= '"'.create_guid().'",';
                     break;
                 case "int":
                     //return random number`
                    $returnContent .= '"'.mt_rand(0,4).'",';
                     break;
                 case "name":
                     //return first, last, user name, or random name string
                     if($field['name'] == 'first_name'){
                         $count = count($sugar_demodata['first_name_array']) - 1;
                        $returnContent .= '"'.$sugar_demodata['last_name_array'][mt_rand(0,$count)].'",';
                     }elseif($field['name'] == 'last_name'){
                         $count = count($sugar_demodata['last_name_array']) - 1;
                         $returnContent .= '"'.$sugar_demodata['last_name_array'][mt_rand(0,$count)].'",';
                     }elseif($field['name'] == 'user_name'){
                       $count = count($sugar_demodata['first_name_array']) - 1;
                        $returnContent .= '"'.$sugar_demodata['last_name_array'][mt_rand(0,$count)].'_'.mt_rand(1,111).'",';
                     }else{
                         //return based on bean
                         if($focus->module_dir =='Accounts'){
                             $count = count($sugar_demodata['company_name_array']) - 1;
                            $returnContent .= '"'.$sugar_demodata['company_name_array'][mt_rand(0,$count)].'",';

                         }elseif($focus->module_dir =='Bugs'){
                             $count = count($sugar_demodata['bug_seed_names']) - 1;
                            $returnContent .= '"'.$sugar_demodata['bug_seed_names'][mt_rand(0,$count)].'",';
                         }elseif($focus->module_dir =='Notes'){
                             $count = count($sugar_demodata['note_seed_names_and_Descriptions']) - 1;
                            $returnContent .= '"'.$sugar_demodata['note_seed_names_and_Descriptions'][mt_rand(0,$count)].'",';

                         }elseif($focus->module_dir =='Calls'){
                              $count = count($sugar_demodata['call_seed_data_names']) - 1;
                            $returnContent .= '"'.$sugar_demodata['call_seed_data_names'][mt_rand(0,$count)].'",';

                         }elseif($focus->module_dir =='Tasks'){
                             $count = count($sugar_demodata['task_seed_data_names']) - 1;
                           $returnContent .= '"'.$sugar_demodata['task_seed_data_names'][mt_rand(0,$count)].'",';

                         }elseif($focus->module_dir =='Meetings'){
                             $count = count($sugar_demodata['meeting_seed_data_names']) - 1;
                           $returnContent .= '"'.$sugar_demodata['meeting_seed_data_names'][mt_rand(0,$count)].'",';

                         }elseif($focus->module_dir =='ProductCategories'){
                             $count = count($sugar_demodata['productcategory_seed_data_names']) - 1;
                           $returnContent .= '"'.$sugar_demodata['productcategory_seed_data_names'][mt_rand(0,$count)].'",';


                         }elseif($focus->module_dir =='ProductTypes'){
                             $count = count($sugar_demodata['producttype_seed_data_names']) - 1;
                           $returnContent .= '"'.$sugar_demodata['producttype_seed_data_names'][mt_rand(0,$count)].'",';


                         }elseif($focus->module_dir =='ProductTemplates'){
                             $count = count($sugar_demodata['producttemplate_seed_data']) - 1;
                           $returnContent .= '"'.$sugar_demodata['producttemplate_seed_data'][mt_rand(0,$count)].'",';

                         }else{
                           $returnContent .= '"Default Name for '.$focus->module_dir.'",';

                         }

                     }
                    break;
                 case "parent":
                 case "relate":
                     if($field['name'] == 'team_name'){
                         //apply team names and user_name
                         $teams_count = count($sugar_demodata['teams']) - 1;
                         $users_count = count($sugar_demodata['users']) - 1;

                     $returnContent .= '"'.$sugar_demodata['teams'][mt_rand(0,$teams_count)]['name'].','.$sugar_demodata['users'][mt_rand(0,$users_count)]['user_name'].'",';

                     }else{
                         //apply GUID
                         $returnContent .= '"'.create_guid().'",';
                     }
                     break;
                 case "bool":
                     //return 0 or 1
                     $returnContent .= '"'.mt_rand(0,1).'",';
                     break;

                 case "text":
                     //return random text
                     $returnContent .= '"Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Maecenas porttitor congue massa. Fusce posuere, magna sed pulvinar ultricies, purus lectus malesuada libero, sit amet commodo magna eros quis urna",';
                     break;

                 case "team_list":
                     $teams_count = count($sugar_demodata['teams']) - 1;
                     //give fake team names (East,West,North,South)
                     $returnContent .= '"'.$sugar_demodata['teams'][mt_rand(0,$teams_count)]['name'].'",';
                     break;

                 case "date":
                     //return formatted date
                     $timeStamp = strtotime('now');
                     $value =    date($timedate->dbDayFormat, $timeStamp);
                     $returnContent .= '"'.$timedate->to_display_date_time($value).'",';
                     break;

                 case "datetime":
                 case "datetimecombo":
                     //return formatted date time
                     $timeStamp = strtotime('now');
                     //Start with db date
                     $value =    date($timedate->dbDayFormat.' '.$timedate->dbTimeFormat, $timeStamp);
                     //use timedate to convert to user display format
                     $value = $timedate->to_display_date_time($value);
                     //finally forma the am/pm to have a space so it can be recognized as a date field in excel
                     $value = preg_replace('/([pm|PM|am|AM]+)/', ' \1', $value);
                     $returnContent .= '"'.$value.'",';

                     break;
                case "phone":
                    $value = '('.mt_rand(300,999).') '.mt_rand(300,999).'-'.mt_rand(1000,9999);
                      $returnContent .= '"'.$value.'",';
                     break;
                 case "varchar":
                                     //process varchar for possible values
                                     if($field['name'] == 'first_name'){
                                         $count = count($sugar_demodata['first_name_array']) - 1;
                                        $returnContent .= '"'.$sugar_demodata['last_name_array'][mt_rand(0,$count)].'",';
                                     }elseif($field['name'] == 'last_name'){
                                         $count = count($sugar_demodata['last_name_array']) - 1;
                                         $returnContent .= '"'.$sugar_demodata['last_name_array'][mt_rand(0,$count)].'",';
                                     }elseif($field['name'] == 'user_name'){
                                       $count = count($sugar_demodata['first_name_array']) - 1;
                                        $returnContent .= '"'.$sugar_demodata['last_name_array'][mt_rand(0,$count)].'_'.mt_rand(1,111).'",';
                                     }elseif($field['name'] == 'title'){
                                         $count = count($sugar_demodata['titles']) - 1;
                                         $returnContent .= '"'.$sugar_demodata['titles'][mt_rand(0,$count)].'",';
                                     }elseif(strpos($field['name'],'address_street')>0){
                                       $count = count($sugar_demodata['street_address_array']) - 1;
                                        $returnContent .= '"'.$sugar_demodata['street_address_array'][mt_rand(0,$count)].'",';
                                     }elseif(strpos($field['name'],'address_city')>0){
                                       $count = count($sugar_demodata['city_array']) - 1;
                                        $returnContent .= '"'.$sugar_demodata['city_array'][mt_rand(0,$count)].'",';
                                     }elseif(strpos($field['name'],'address_state')>0){
                                         $state_arr = array('CA','NY','CO','TX','NV');
                                       $count = count($state_arr) - 1;
                                        $returnContent .= '"'.$state_arr[mt_rand(0,$count)].'",';
                                     }elseif(strpos($field['name'],'address_postalcode')>0){
                                        $returnContent .= '"'.mt_rand(12345,99999).'",';
                                     }else{
                                         $returnContent .= '"",';

                                     }
                     break;
                case "url":
                     $returnContent .= '"https://www.beakon.com.au",';
                     break;

                case "enum":
                    //get the associated enum if available
                    global $app_list_strings;

                    if(isset($focus->field_name_map[$field_name]['type']) && !empty($focus->field_name_map[$field_name]['options'])){
                        if ( !empty($app_list_strings[$focus->field_name_map[$field_name]['options']]) ) {

                            //get the values into an array
                            $dd_values = $app_list_strings[$focus->field_name_map[$field_name]['options']];
                            $dd_values = array_values($dd_values);

                            //grab the count
                            $count = count($dd_values) - 1;

                            //choose one at random
                            $returnContent .= '"'.$dd_values[mt_rand(0,$count)].'",';
                            } else{
                                //name of enum options array was found but is empty, return blank
                                $returnContent .= '"",';
                            }
                    }else{
                        //name of enum options array was not found on field, return blank
                        $returnContent .= '"",';
                    }
                     break;
                default:
                    //type is not matched, fill in with empty string and continue;
                    $returnContent .= '"",';

             }
         }
         $returnContent .= "\r\n";
     }
     return $returnContent;
 }




 //expects the field name to translate and a bean of the type being translated (to access field map and mod_strings)
 function translateForExport($field_db_name,$focus){
     global $mod_strings,$app_strings;

     if (empty($field_db_name) || empty($focus)){
        return false;
     }

    //grab the focus module strings
    $temp_mod_strings = $mod_strings;
    global $current_language;
    $mod_strings = return_module_language($current_language, $focus->module_dir);
    $fieldLabel = '';

     //!! first check to see if we are overriding the label for export.
     if (!empty($mod_strings['LBL_EXPORT_'.strtoupper($field_db_name)])){
         //entry exists which means we are overriding this value for exporting, use this label
         $fieldLabel = $mod_strings['LBL_EXPORT_'.strtoupper($field_db_name)];

     }
     //!! next check to see if we are overriding the label for export on app_strings.
     elseif (!empty($app_strings['LBL_EXPORT_'.strtoupper($field_db_name)])){
         //entry exists which means we are overriding this value for exporting, use this label
         $fieldLabel = $app_strings['LBL_EXPORT_'.strtoupper($field_db_name)];

     }//check to see if label exists in mapping and in mod strings
     elseif (!empty($focus->field_name_map[$field_db_name]['vname']) && !empty($mod_strings[$focus->field_name_map[$field_db_name]['vname']])){
         $fieldLabel = $mod_strings[$focus->field_name_map[$field_db_name]['vname']];

     }//check to see if label exists in mapping and in app strings
     elseif (!empty($focus->field_name_map[$field_db_name]['vname']) && !empty($app_strings[$focus->field_name_map[$field_db_name]['vname']])){
         $fieldLabel = $app_strings[$focus->field_name_map[$field_db_name]['vname']];

     }//field is not in mapping, so check to see if db can be uppercased and found in mod strings
     elseif (!empty($mod_strings['LBL_'.strtoupper($field_db_name)])){
         $fieldLabel = $mod_strings['LBL_'.strtoupper($field_db_name)];

     }//check to see if db can be uppercased and found in app strings
     elseif (!empty($app_strings['LBL_'.strtoupper($field_db_name)])){
         $fieldLabel = $app_strings['LBL_'.strtoupper($field_db_name)];

     }else{
         //we could not find the label in mod_strings or app_strings based on either a mapping entry
         //or on the db_name itself or being overwritten, so default to the db name as a last resort
         $fieldLabel = $field_db_name;

     }
     //strip the label of any columns
     $fieldLabel= preg_replace("/([:]|\xEF\xBC\x9A)[\\s]*$/", '', trim($fieldLabel));

     //reset the bean mod_strings back to original import strings
     $mod_strings = $temp_mod_strings;
     return $fieldLabel;

 }

//call this function to return the desired order to display columns for export in.
//if you pass in an array, it will reorder the array and send back to you.  It expects the array
//to have the db names as key values, or as labels
function get_field_order_mapping($name='',$reorderArr = '', $exclude = true){

    //define the ordering of fields, note that the key value is what is important, and should be the db field name
    $field_order_array = array();
    $field_order_array['accounts'] = array( 'name'=>'Name', 'id'=>'ID', 'website'=>'Website', 'email_address' =>'Email Address', 'email_addresses_non_primary' => 'Non Primary E-mails', 'phone_office' =>'Office Phone', 'phone_alternate' => 'Alternate Phone', 'phone_fax' => 'Fax', 'billing_address_street' => 'Billing Street', 'billing_address_city' => 'Billing City', 'billing_address_state' => 'Billing State', 'billing_address_postalcode' => 'Billing Postal Code', 'billing_address_country' => 'Billing Country', 'shipping_address_street' => 'Shipping Street', 'shipping_address_city' => 'Shipping City', 'shipping_address_state' => 'Shipping State', 'shipping_address_postalcode' => 'Shipping Postal Code', 'shipping_address_country' => 'Shipping Country', 'description' => 'Description', 'account_type' => 'Type', 'industry' =>'Industry', 'annual_revenue' => 'Annual Revenue', 'employees' => 'Employees', 'sic_code' => 'SIC Code', 'ticker_symbol' => 'Ticker Symbol', 'parent_id' => 'Parent Account ID', 'ownership' =>'Ownership', 'campaign_id' =>'Campaign ID', 'rating' =>'Rating', 'assigned_user_name' =>'Assigned to',  'assigned_user_id' =>'Assigned User ID', 'team_id' =>'Team Id', 'team_name' =>'Teams', 'team_set_id' =>'Team Set ID', 'date_entered' =>'Date Created', 'date_modified' =>'Date Modified', 'modified_user_id' =>'Modified By', 'created_by' =>'Created By', 'deleted' =>'Deleted');
    $field_order_array['contacts'] = array( 'first_name' => 'First Name', 'last_name' => 'Last Name', 'id'=>'ID', 'salutation' => 'Salutation', 'title' => 'Title', 'department' => 'Department', 'account_name' => 'Account Name', 'email_address' => 'Email Address', 'email_addresses_non_primary' => 'Non Primary E-mails for Import', 'phone_mobile' => 'Phone Mobile','phone_work' => 'Phone Work', 'phone_home' => 'Phone Home',  'phone_other' => 'Phone Other','phone_fax' => 'Phone Fax', 'primary_address_street' => 'Primary Address Street', 'primary_address_city' => 'Primary Address City', 'primary_address_state' => 'Primary Address State', 'primary_address_postalcode' => 'Primary Address Postal Code', 'primary_address_country' => 'Primary Address Country', 'alt_address_street' => 'Alternate Address Street', 'alt_address_city' => 'Alternate Address City', 'alt_address_state' => 'Alternate Address State', 'alt_address_postalcode' => 'Alternate Address Postal Code', 'alt_address_country' => 'Alternate Address Country', 'description' => 'Description', 'birthdate' => 'Birthdate', 'lead_source' => 'Lead Source', 'campaign_id' => 'campaign_id', 'do_not_call' => 'Do Not Call', 'portal_name' => 'Portal Name', 'portal_active' => 'Portal Active', 'portal_password' => 'Portal Password', 'portal_app' => 'Portal Application', 'reports_to_id' => 'Reports to ID', 'assistant' => 'Assistant', 'assistant_phone' => 'Assistant Phone', 'picture' => 'Picture', 'assigned_user_name' => 'Assigned User Name', 'assigned_user_id' => 'Assigned User ID', 'team_name' => 'Teams', 'team_id' => 'Team id', 'team_set_id' => 'Team Set ID', 'date_entered' =>'Date Created', 'date_modified' =>'Date Modified', 'modified_user_id' =>'Modified By', 'created_by' =>'Created By', 'deleted' =>'Deleted');
    $field_order_array['leads']    = array( 'first_name' => 'First Name', 'last_name' => 'Last Name', 'id'=>'ID', 'salutation' => 'Salutation', 'title' => 'Title', 'department' => 'Department', 'account_name' => 'Account Name', 'account_description' =>  'Account Description', 'website' =>  'Website', 'email_address' =>  'Email Address', 'email_addresses_non_primary' => 'Non Primary E-mails for Import', 'phone_mobile' =>  'Phone Mobile', 'phone_work' =>  'Phone Work', 'phone_home' =>  'Phone Home', 'phone_other' =>  'Phone Other', 'phone_fax' =>  'Phone Fax', 'primary_address_street' =>  'Primary Address Street', 'primary_address_city' =>  'Primary Address City', 'primary_address_state' =>  'Primary Address State', 'primary_address_postalcode' =>  'Primary Address Postal Code', 'primary_address_country' =>  'Primary Address Country', 'alt_address_street' =>  'Alt Address Street', 'alt_address_city' =>  'Alt Address City', 'alt_address_state' =>  'Alt Address State', 'alt_address_postalcode' =>  'Alt Address Postalcode', 'alt_address_country' =>  'Alt Address Country', 'status' =>  'Status', 'status_description' =>  'Status Description', 'lead_source' =>  'Lead Source', 'lead_source_description' =>  'Lead Source Description', 'description'=>'Description', 'converted' =>  'Converted', 'opportunity_name' =>  'Opportunity Name', 'opportunity_amount' =>  'Opportunity Amount', 'refered_by' =>  'Referred By', 'campaign_id' =>  'campaign_id', 'do_not_call' =>  'Do Not Call', 'portal_name' =>  'Portal Name', 'portal_app' =>  'Portal Application', 'reports_to_id' =>  'Reports To ID', 'assistant' =>  'Assistant', 'assistant_phone' =>  'Assistant Phone', 'birthdate'=>'Birthdate', 'contact_id' =>  'Contact ID', 'account_id' =>  'Account ID', 'opportunity_id' =>  'Opportunity ID',  'assigned_user_name' =>  'Assigned User Name', 'assigned_user_id' =>  'Assigned User ID', 'team_name' =>  'Teams', 'team_id' =>  'Team id', 'team_set_id' =>  'Team Set ID', 'date_entered' =>  'Date Created', 'date_modified' =>  'Date Modified', 'created_by' =>  'Created By ID', 'modified_user_id' =>  'Modified By ID', 'deleted' =>  'Deleted');
    $field_order_array['opportunities'] = array( 'name' => 'Opportunity Name', 'id'=>'ID', 'amount' => 'Opportunity Amount', 'currency_id' => 'Currency', 'date_closed' => 'Expected Close Date', 'sales_stage' => 'Sales Stage', 'probability' => 'Probability (%)', 'next_step' => 'Next Step', 'opportunity_type' => 'Opportunity Type', 'account_name' => 'Account Name', 'description' => 'Description', 'amount_usdollar' => 'Amount', 'lead_source' => 'Lead Source', 'campaign_id' => 'campaign_id', 'assigned_user_name' => 'Assigned User Name', 'assigned_user_id' => 'Assigned User ID', 'team_name' => 'Teams', 'team_id' => 'Team id', 'team_set_id' => 'Team Set ID', 'date_entered' => 'Date Created', 'date_modified' => 'Date Modified', 'created_by' => 'Created By ID', 'modified_user_id' => 'Modified By ID', 'deleted' => 'Deleted');
    $field_order_array['notes'] =         array( 'name' => 'Name', 'id'=>'ID', 'description' => 'Description', 'filename' => 'Attachment', 'parent_type' => 'Parent Type', 'parent_id' => 'Parent ID', 'contact_id' => 'Contact ID', 'portal_flag' => 'Display in Portal?', 'assigned_user_name' =>'Assigned to', 'assigned_user_id' => 'assigned_user_id', 'team_id' => 'Team id', 'team_set_id' => 'Team Set ID', 'date_entered' => 'Date Created', 'date_modified' => 'Date Modified',  'created_by' => 'Created By ID', 'modified_user_id' => 'Modified By ID', 'deleted' => 'Deleted' );
    $field_order_array['bugs'] =   array('bug_number' => 'Bug Number', 'id'=>'ID', 'name' => 'Subject', 'description' => 'Description', 'status' => 'Status', 'type' => 'Type', 'priority' => 'Priority', 'resolution' => 'Resolution', 'work_log' => 'Work Log', 'found_in_release' => 'Found In Release', 'fixed_in_release' => 'Fixed In Release', 'found_in_release_name' => 'Found In Release Name', 'fixed_in_release_name' => 'Fixed In Release', 'product_category' => 'Category', 'source' => 'Source', 'portal_viewable' => 'Portal Viewable', 'system_id' => 'System ID', 'assigned_user_id' => 'Assigned User ID', 'assigned_user_name' => 'Assigned User Name', 'team_name'=>'Teams', 'team_id' => 'Team id', 'team_set_id' => 'Team Set ID', 'date_entered' =>'Date Created', 'date_modified' =>'Date Modified', 'modified_user_id' =>'Modified By', 'created_by' =>'Created By', 'deleted' =>'Deleted');
    $field_order_array['tasks'] =   array( 'name'=>'Subject', 'id'=>'ID', 'description'=>'Description', 'status'=>'Status', 'date_start'=>'Date Start', 'date_due'=>'Date Due','priority'=>'Priority', 'parent_type'=>'Parent Type', 'parent_id'=>'Parent ID', 'contact_id'=>'Contact ID', 'assigned_user_name' =>'Assigned to', 'assigned_user_id'=>'Assigned User ID', 'team_name'=>'Teams', 'team_id'=>'Team id', 'team_set_id'=>'Team Set ID', 'date_entered'=>'Date Created', 'date_modified'=>'Date Modified', 'created_by'=>'Created By ID', 'modified_user_id'=>'Modified By ID', 'deleted'=>'Deleted');
    $field_order_array['calls'] =   array( 'name'=>'Subject', 'id'=>'ID', 'description'=>'Description', 'status'=>'Status', 'direction'=>'Direction', 'date_start'=>'Date', 'date_end'=>'Date End', 'duration_hours'=>'Duration Hours', 'duration_minutes'=>'Duration Minutes', 'reminder_time'=>'Reminder Time', 'parent_type'=>'Parent Type', 'parent_id'=>'Parent ID', 'outlook_id'=>'Outlook ID', 'assigned_user_name' =>'Assigned to', 'assigned_user_id'=>'Assigned User ID', 'team_name'=>'Teams', 'team_id'=>'Team id', 'team_set_id'=>'Team Set ID', 'date_entered'=>'Date Created', 'date_modified'=>'Date Modified', 'created_by'=>'Created By ID', 'modified_user_id'=>'Modified By ID', 'deleted'=>'Deleted');
    $field_order_array['meetings'] =array( 'name'=>'Subject', 'id'=>'ID', 'description'=>'Description', 'status'=>'Status', 'location'=>'Location', 'date_start'=>'Date', 'date_end'=>'Date End', 'duration_hours'=>'Duration Hours', 'duration_minutes'=>'Duration Minutes', 'reminder_time'=>'Reminder Time', 'type'=>'Meeting Type', 'external_id'=>'External ID', 'password'=>'Meeting Password', 'join_url'=>'Join Url', 'host_url'=>'Host Url', 'displayed_url'=>'Displayed Url', 'creator'=>'Meeting Creator', 'parent_type'=>'Related to', 'parent_id'=>'Related to', 'outlook_id'=>'Outlook ID','assigned_user_name' =>'Assigned to','assigned_user_id' => 'Assigned User ID', 'team_name' => 'Teams', 'team_id' => 'Team id', 'team_set_id' => 'Team Set ID', 'date_entered' => 'Date Created', 'date_modified' => 'Date Modified', 'created_by' => 'Created By ID', 'modified_user_id' => 'Modified By ID', 'deleted' => 'Deleted');
    $field_order_array['cases'] =array( 'case_number'=>'Case Number', 'id'=>'ID', 'name'=>'Subject', 'description'=>'Description', 'status'=>'Status', 'type'=>'Type', 'priority'=>'Priority', 'resolution'=>'Resolution', 'work_log'=>'Work Log', 'portal_viewable'=>'Portal Viewable', 'account_name'=>'Account Name', 'account_id'=>'Account ID', 'assigned_user_id'=>'Assigned User ID', 'team_name'=>'Teams', 'team_id'=>'Team id', 'team_set_id'=>'Team Set ID', 'date_entered'=>'Date Created', 'date_modified'=>'Date Modified', 'created_by'=>'Created By ID', 'modified_user_id'=>'Modified By ID', 'deleted'=>'Deleted');
    $field_order_array['prospects'] =array( 'first_name'=>'First Name', 'last_name'=>'Last Name', 'id'=>'ID', 'salutation'=>'Salutation', 'title'=>'Title', 'department'=>'Department', 'account_name'=>'Account Name', 'email_address'=>'Email Address', 'email_addresses_non_primary' => 'Non Primary E-mails for Import', 'phone_mobile' => 'Phone Mobile', 'phone_work' => 'Phone Work', 'phone_home' => 'Phone Home', 'phone_other' => 'Phone Other', 'phone_fax' => 'Phone Fax',  'primary_address_street' => 'Primary Address Street', 'primary_address_city' => 'Primary Address City', 'primary_address_state' => 'Primary Address State', 'primary_address_postalcode' => 'Primary Address Postal Code', 'primary_address_country' => 'Primary Address Country', 'alt_address_street' => 'Alternate Address Street', 'alt_address_city' => 'Alternate Address City', 'alt_address_state' => 'Alternate Address State', 'alt_address_postalcode' => 'Alternate Address Postal Code', 'alt_address_country' => 'Alternate Address Country', 'description' => 'Description', 'birthdate' => 'Birthdate', 'assistant'=>'Assistant', 'assistant_phone'=>'Assistant Phone', 'campaign_id'=>'campaign_id', 'tracker_key'=>'Tracker Key', 'do_not_call'=>'Do Not Call', 'lead_id'=>'Lead Id', 'assigned_user_name'=>'Assigned User Name', 'assigned_user_id'=>'Assigned User ID', 'team_id' =>'Team Id', 'team_name' =>'Teams', 'team_set_id' =>'Team Set ID', 'date_entered' =>'Date Created', 'date_modified' =>'Date Modified', 'modified_user_id' =>'Modified By', 'created_by' =>'Created By', 'deleted' =>'Deleted');

	// Yura 08.02.2018
	// added partial order of some fields for csv export
	$field_order_array['users'] =array( 'first_name'=>'First Name', 'last_name'=>'Last Name', 'id'=>'ID', 'user_name'=>'Username', 'title'=>'Title', 'location_list'=>'Location', 'dept_departments_id_c'=>'Department');

    $fields_to_exclude = array();
    $fields_to_exclude['accounts'] = array('account_name');
    $fields_to_exclude['bugs'] = array('system_id');
    $fields_to_exclude['cases'] = array('system_id', 'modified_by_name', 'modified_by_name_owner', 'modified_by_name_mod', 'created_by_name', 'created_by_name_owner', 'created_by_name_mod', 'assigned_user_name', 'assigned_user_name_owner', 'assigned_user_name_mod', 'team_count', 'team_count_owner', 'team_count_mod', 'team_name_owner', 'team_name_mod', 'account_name_owner', 'account_name_mod', 'modified_user_name',  'modified_user_name_owner', 'modified_user_name_mod');
    $fields_to_exclude['notes'] = array('first_name','last_name', 'file_mime_type','embed_flag');
    $fields_to_exclude['tasks'] = array('date_start_flag', 'date_due_flag');

    //of array is passed in for reordering, process array
    if(!empty($name) && !empty($reorderArr) && is_array($reorderArr)){

        //make sure reorderArr has values as keys, if not then iterate through and assign the value as the key
        $newReorder = array();
        foreach($reorderArr as $rk=> $rv){
            if(is_int($rk)){
                $newReorder[$rv]=$rv;
            }else{
                $newReorder[$rk]=$rv;
            }
        }

        //if module is not defined, lets default the order to another module of the same type
        //this would apply mostly to custom modules
        if(!isset($field_order_array[strtolower($name)]) && isset($_REQUEST['module'])){

            $exemptModuleList = array('ProspectLists');
            if(in_array($name, $exemptModuleList))
                return $newReorder;

            //get an instance of the bean
            global $beanList;
            global $beanFiles;

            $bean = $beanList[$_REQUEST['module']];
            require_once($beanFiles[$bean]);
            $focus = new $bean;


            //if module is of type person
            if($focus instanceof Person){
                $name = 'contacts';
            }
            //if module is of type company
            else if ($focus instanceof Company){
                $name = 'accounts';
            }
            //if module is of type Sale
            else if ($focus instanceof Sale){
                $name = 'opportunities';
            }//if module is of type File
            else if ($focus instanceof Issue){
                $name = 'bugs';
            }//all others including type File can use basic
            else{
                $name = 'Notes';
            }

        }

        //lets iterate through and create a reordered temporary array using
        //the  newly formatted copy of passed in array
        $temp_result_arr = array();
        $lname = strtolower($name);
        if(!empty($field_order_array[$lname])) {
	        foreach($field_order_array[$lname] as $fk=> $fv){

	            //if the value exists as a key in the passed in array, add to temp array and remove from reorder array.
	            //Do not force into the temp array as we don't want to violate acl's
	            if(array_key_exists($fk,$newReorder)){
	                $temp_result_arr[$fk] = $newReorder[$fk];
	                unset($newReorder[$fk]);
	            }
	        }
        }
        //add in all the left over values that were not in our ordered list
        //array_splice($temp_result_arr, count($temp_result_arr), 0, $newReorder);
        foreach($newReorder as $nrk=>$nrv){
            $temp_result_arr[$nrk] = $nrv;
        }


        if($exclude){
            //Some arrays have values we wish to exclude
            if (isset($fields_to_exclude[$lname])){
                foreach($fields_to_exclude[$lname] as $exclude_field){
                    unset($temp_result_arr[$exclude_field]);
                }
            }
        }

        //return temp ordered list
        return $temp_result_arr;
    }

    //if no array was passed in, pass back either the list of ordered columns by module, or the entireorder array
    if(empty($name)){
        return $field_order_array;
    }else{
        return $field_order_array[strtolower($name)];
    }

}

function matrix_dispay_value($v_val) {
    global $sugar_config, $current_language;
    $ret_value = '';
    if (!empty($v_val)) {
        $s_val = $sugar_config['matrix_values'][$v_val];
        $label = $sugar_config['matrix_select'][$s_val];
        $mat_strings = return_module_language($current_language, 'Matrix');
        if (!empty($label) && !empty($mat_strings[$label])) {
            $ret_value = $mat_strings[$label];
        }
    }
    return $ret_value;
}

function getJobDetails($jobId){
	global $db;
	$sql = "SELECT job_id, work_id FROM bmd_jobs WHERE id = '{$jobId}'";
	$res = $db->query($sql, true);
	$row = $db->fetchByAssoc($res, false);
	return $row;
}
