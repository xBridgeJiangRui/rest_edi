<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class lite_b2b_update_model_v2 extends CI_Model
{
    public $tb_lite_b2b = 'lite_b2b';
    public $tb_lite_b2b_apps = 'lite_b2b_apps';
    
    public function __construct()
	{
		parent::__construct();
	}

    function view_doc($db,$table,$refno)
    {
        $table = $db.'.'.$table;

        $data = array(
                'status' => 'viewed',
        );
        
        $this->db->where('refno', $refno);
        $this->db->where('status', '');
        $this->db->update($table, $data);

        return $refno;
    }


    function accept_po($db,$refno)
    {
        $table = $db.'.pomain';

        $data = array(
                'status' => 'Accepted',
                'b2b_status' => 'readysend',
        );

        $this->db->where_in('refno', $refno);
        $this->db->update($table, $data);

        return $refno;
    }

    function reject_po($db,$refno,$reason)
    {
        $nowtime = $this->db->query("SELECT now() as nowtime")->row('nowtime'); 

        $table = $db.'.pomain';

        $data = array(
                'status' => 'rejected',
                'b2b_status' => 'readysend',
                'rejected_remark' => $reason,
                'rejected' => '1',
                'rejected_at' => $nowtime,
        );

        $this->db->where('refno', $refno);
        $this->db->update($table, $data);

        return $refno;
    }

    function change_user_password($confirm_password,$user_id,$user_guid,$acc_module_group_guid)
    {
        $nowtime = $this->db->query("SELECT now() as nowtime")->row('nowtime'); 

        $table = $this->tb_lite_b2b.'.set_user';

        $confirm_password = md5($confirm_password);

        $data = array(
                'user_password' => $confirm_password,
                'updated_by' => $user_id,
                'module_group_guid' => $acc_module_group_guid,
                'updated_at' => $nowtime,
        );

        $this->db->where('user_guid', $user_guid);
        $this->db->update($table, $data);

        return $user_guid;
    }

    function notification_read($app_notification_guid)
    {
        $nowtime = $this->db->query("SELECT now() as nowtime")->row('nowtime'); 

        $table = $this->tb_lite_b2b_apps.'.app_notification';

        $data = array(
                'isread' => '1',
                'updated_at' => $nowtime,
        );

        $this->db->where('app_notification_guid', $app_notification_guid);
        $this->db->update($table, $data);

        return $app_notification_guid;
    }

    function notification_all_read($user_guid,$customer_guid,$location_in)
    {
        $nowtime = $this->db->query("SELECT now() as nowtime")->row('nowtime'); 

        $table = $this->tb_lite_b2b_apps.'.app_notification';

        $data = array(
                'isread' => '1',
                'updated_at' => $nowtime,
        );

        $this->db->where_in('branch_code', $location_in);
        $this->db->where('customer_guid', $customer_guid);
        $this->db->where('user_guid', $user_guid);
        $this->db->where('issend', '1');
        $this->db->update($table, $data);
    }

}

?> 
