<?php
require_once 'modules/ACLRoles/ACLRole.php';
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class r_Permit_cstm{
	function update_owner($bean, $event, $arguments){
		$bean->assigned_user_id = $bean->company_c;
		if(empty($bean->status_id)){
			$bean->status_id = '1';
		}
	}
	function add_record_number($bean, $event, $arguments){
		global $db;
		$sql = "UPDATE `{$bean->table_name}` SET `document_name` = CONCAT('PER_', record_number) WHERE id='{$bean->id}'";
		$db->query($sql);
	}
	function update_bean($bean, $event, $arguments){
		$bean->record_number = 'PER_'.$bean->record_number;
	}
	function parent_status_color($bean, $event, $arguments){
		global $app_list_strings;
		$status_color_codes = array(
			'Draft' => '#FF0000',
			'Open' => '#ff7700',
			'Completed' => '#00D97E',
			'CompletePendingClosure' => '#ff7700',

		);
		$bean->status_id = '<span class="btn disabled" style="color:#FFF;background-color: '.$status_color_codes[$bean->status_id].'" title="'.$app_list_strings[$bean->field_defs['status_id']['options']][$bean->status_id].'">'.$app_list_strings[$bean->field_defs['status_id']['options']][$bean->status_id].'</span>';
	}
	function update_workers(&$bean, $event, $arguments){
		if ($bean->list_work_c != $bean->fetched_row['list_work_c']){
			$rel_name = 'r_permit_r_permitworkers_1';
			if ($bean->load_relationship($rel_name)){
				$rel_info = SugarRelationshipFactory::getInstance()->getRelationshipDef($rel_name);
				$sql = "SELECT rhs.id, rhs_c.user_id_c
						FROM {$rel_info['rhs_table']} rhs
						INNER JOIN {$rel_info['rhs_table']}_cstm rhs_c ON rhs.id = rhs_c.id_c
						INNER JOIN {$rel_info['join_table']} jt ON rhs.id = jt.{$rel_info['join_key_rhs']} AND jt.deleted = 0
						WHERE rhs.deleted = 0 AND jt.{$rel_info['join_key_lhs']} = '{$bean->id}'";
				$workers = $GLOBALS['db']->fetchAll($sql, 'id', 'user_id_c');
				$workers_list = unencodeMultienum($bean->list_work_c);
				// delete workers that are no longer in Permit
				foreach ($workers as $record_id => $user_id){
					if (!in_array($user_id, $workers_list)){
						$worker_bean = BeanFactory::getBean('r_PermitWorkers', $record_id);
						$worker_bean->mark_deleted($record_id);
					}
				}
				// create new workers and add them to subpanel
				foreach ($workers_list as $user_id){
					if (!empty($user_id) && !in_array($user_id, $workers)){
						$worker_bean = BeanFactory::getBean('r_PermitWorkers');
						$worker_bean->user_id_c = $user_id;
						$worker_bean->save();
						$bean->$rel_name->add($worker_bean->id);
					}
				}
			}
		}
	}
	function change_child_assigned_to($bean, $event, $arguments){
		global $app_list_strings;
		if(isset($app_list_strings['permit_types_list'][$bean->module_name]) && $bean->status_id == '7'){
			$parentBean = '';
			if(isset($_REQUEST['parent_type']) && !empty($_REQUEST['parent_type']) && isset($_REQUEST['parent_id']) && !empty($_REQUEST['parent_id'])){
				$parentBean = BeanFactory::getBean($_REQUEST['parent_type'], $_REQUEST['parent_id']);
			}
			if(isset($_REQUEST['parent_module']) && !empty($_REQUEST['parent_module']) && isset($_REQUEST['parent_id']) && !empty($_REQUEST['parent_id'])){
				$parentBean = BeanFactory::getBean($_REQUEST['parent_module'], $_REQUEST['parent_id']);
			}
			if(isset($_REQUEST['return_module']) && !empty($_REQUEST['return_module']) && isset($_REQUEST['return_id']) && !empty($_REQUEST['return_id'])){
				$parentBean = BeanFactory::getBean($_REQUEST['return_module'], $_REQUEST['return_id']);
			}
			if(!empty($bean->parent_type) && !empty($bean->parent_id)){
				$parentBean = BeanFactory::getBean($bean->parent_type, $bean->parent_id);
			}
			//$permitBean = BeanFactory::getBean($bean->parent_type, $bean->parent_id);
			if($parentBean != null){
				$bean->assigned_user_id = $parentBean->assigned_user_id;
			}
		}
	}
	function change_status_to_open($bean, $event, $arguments){
		global $app_list_strings;
		if(isset($app_list_strings['permit_types_list'][$bean->module_name]) && $bean->status_id == '7'){
			$parentBean = '';
			if(isset($_REQUEST['parent_type']) && !empty($_REQUEST['parent_type']) && isset($_REQUEST['parent_id']) && !empty($_REQUEST['parent_id'])){
				$parentBean = BeanFactory::getBean($_REQUEST['parent_type'], $_REQUEST['parent_id']);
			}
			if(isset($_REQUEST['parent_module']) && !empty($_REQUEST['parent_module']) && isset($_REQUEST['parent_id']) && !empty($_REQUEST['parent_id'])){
				$parentBean = BeanFactory::getBean($_REQUEST['parent_module'], $_REQUEST['parent_id']);
			}
			if(isset($_REQUEST['return_module']) && !empty($_REQUEST['return_module']) && isset($_REQUEST['return_id']) && !empty($_REQUEST['return_id'])){
				$parentBean = BeanFactory::getBean($_REQUEST['return_module'], $_REQUEST['return_id']);
			}
			if(!empty($bean->parent_type) && !empty($bean->parent_id)){
				$parentBean = BeanFactory::getBean($bean->parent_type, $bean->parent_id);
			}
			//$permitBean = BeanFactory::getBean($bean->parent_type, $bean->parent_id);
			if($parentBean != null){
				if($parentBean->status_id == 'Draft'){
					$parentBean->status_id = 'Open';
					$parentBean->save();
				}
			}
		}
	}
	function update_status_color($bean, $event, $arguments){
        global $app_list_strings;
        $status_color_codes = array(
			1 => '#FF0000', // 'Draft',
			7 => '#ff7700', // 'Submitted',
			2 => '#ff7700', // 'Pending Approval',
			3 => '#FF0000', // 'Rejected (RS)',
			8 => '#00D97E', // 'Approved (RS)',
			4 => '#ff7700', // 'Pending Authorisation (SME)',
			5 => '#FF0000', // 'Rejected (SME)',
			6 => '#00D97E', // 'Authorised',
			9 => '#ff7700', // 'Pending Closeout Approval',
			10 => '#FF0000', // 'Closeout Rejected (RS)',
			11 => '#ff7700', // 'Pending Closeout Authorisation',
			12 => '#FF0000', // 'Closeout Rejected (SME)',
			13 => '#00D97E', // 'Closed',
			14 => '#ff7700', // 'Pending Authorisation (CUIO)',
			15 => '#FF0000', // 'Rejected (CUIO)',
			16 => '#ff7700', // 'Request Closeout',
        );
		if(isset($app_list_strings['permit_types_list'][$bean->module_name])){
			$bean->status_id = '<a class="btn disabled" style="color:#FFF;background-color: '.$status_color_codes[$bean->status_id].'" title="'.$app_list_strings[$bean->field_defs['status_id']['options']][$bean->status_id].'">'.$app_list_strings[$bean->field_defs['status_id']['options']][$bean->status_id].'</a>';
		}
	}
}
