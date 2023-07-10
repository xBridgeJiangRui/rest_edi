<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class lite_b2b_insert_model extends CI_Model
{
    public $tb_lite_b2b = 'lite_b2b';
    
    public function __construct()
	{
		parent::__construct();
	}

    function user_session($user_guid, $fcm_token, $type, $created_by, $platform, $version)
    {
        $guid = $this->db->query("SELECT REPLACE(UPPER(UUID()),'-','') as guid ")->row('guid');
        $now_datetime = $this->db->query("SELECT NOW() AS now_datetime")->row('now_datetime');

        $data = array(
            'user_session_guid' => $guid,
            'user_guid' => $user_guid,
            'fcm_token' => $fcm_token,
            'type' => $type,
            'created_at' => $now_datetime,
            'created_by' => $created_by,
            'updated_at' => $now_datetime,
            'updated_by' => $created_by,
            'platform' => $platform,
            'version' => $version,
        );

        $this->db->insert('lite_b2b_apps.user_session', $data);

        return $guid;
    }

    function supplier_movement($customer_guid,$user_guid,$action,$type,$refno)
    {

        if (is_array($refno) == false) {
            $guid = $this->db->query("SELECT REPLACE(UPPER(UUID()),'-','') as guid ")->row('guid');
            $now_datetime = $this->db->query("SELECT NOW() AS now_datetime")->row('now_datetime');

            $data = array(
                    'movement_guid' => $guid, 
                    'customer_guid' => $customer_guid, 
                    'user_guid' => $user_guid, 
                    'action' => $action, 
                    'module' => $type, 
                    'value' => $refno, 
                    'created_at' => $now_datetime, 
            );

            $this->db->insert('lite_b2b_apps.supplier_movement', $data);
        } else {

            foreach ($refno as $key) {
                $guid = $this->db->query("SELECT REPLACE(UPPER(UUID()),'-','') as guid ")->row('guid');
                $now_datetime = $this->db->query("SELECT NOW() AS now_datetime")->row('now_datetime');

                $data = array(
                        'movement_guid' => $guid, 
                        'customer_guid' => $customer_guid, 
                        'user_guid' => $user_guid, 
                        'action' => $action, 
                        'module' => $type, 
                        'value' => $key, 
                        'created_at' => $now_datetime, 
                );

                $this->db->insert('lite_b2b_apps.supplier_movement', $data);
            }
        }

        
    }

    function user_log($user_guid,$userid,$ip_addr)
    {
        $guid = $this->db->query("SELECT REPLACE(UPPER(UUID()),'-','') as guid ")->row('guid');
        $now_datetime = $this->db->query("SELECT NOW() AS now_datetime")->row('now_datetime');
        
        $data = array(
                'user_logs_guid' => $guid, 
                'user_guid' => $user_guid, 
                'id' => $userid, 
                'date_time' => $now_datetime, 
                'ip_address' => $ip_addr, 
                'device' => 'app', 
        );

        $this->db->insert('lite_b2b_apps.user_logs', $data);
    }

    function reset_pwd_self($user_guid,$old_password,$new_password,$user_id)
    {
        $guid = $this->db->query("SELECT REPLACE(UPPER(UUID()),'-','') as guid ")->row('guid');
        $now_datetime = $this->db->query("SELECT NOW() AS now_datetime")->row('now_datetime');

        $data = array(
                'transaction_guid' => $guid, 
                'user_guid' => $user_guid, 
                'from_value' => $old_password, 
                'to_value' => $new_password, 
                'created_by' => $user_id, 
                'created_at' => $now_datetime, 
        );

        $this->db->insert(''.$this->tb_lite_b2b.'.reset_pwd_self', $data);

        return $guid;
    }
}
