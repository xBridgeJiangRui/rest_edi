<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class lite_b2b_delete_model extends CI_Model
{
    public $tb_lite_b2b = 'lite_b2b';
    public $tb_lite_b2b_apps = 'lite_b2b_apps';
    
    public function __construct()
	{
		parent::__construct();
	}

    public function user_session($fcm_token)
    {
        $table = $this->tb_lite_b2b_apps.'.user_session';

        $this->db->where('fcm_token', $fcm_token);
        $this->db->delete($table);

        return $fcm_token;
    }

    public function multiple_user_session($user_guid)
    {
        $table = $this->tb_lite_b2b_apps.'.user_session';

        $this->db->where('user_guid', $user_guid);
        $this->db->delete($table);

        return $user_guid;
    }

}

?> 
