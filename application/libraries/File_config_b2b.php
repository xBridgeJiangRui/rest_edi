<?php 
if (!defined('BASEPATH')) exit('No direct script access allowed');  
 
// require_once APPPATH."/third_party/PHPExcel.php";
 
class File_config_b2b {
    public function __construct()
    {
      $this->ci =& get_instance();
      $this->database1 = 'lite_b2b';
      // $this->load->library(array('database', 'session', 'panda', 'form_validation','message'));
    }

    public function file_path($customer_guid,$device_type,$module_type,$type,$code)
    {
        $file_config = $this->ci->db->query("SELECT file_host,file_path FROM $this->database1.acc WHERE acc_guid = '$customer_guid' LIMIT 1");
        // echo $this->ci->db->last_query();die;
        $file_config_host = $file_config->row('file_host');
        $file_config_main_path = $this->ci->db->query("SELECT value FROM $this->database1.config WHERE device = '$device_type' AND module = '$module_type' AND type = '$type' AND code = '$code'")->row('value');
        $file_config_second_path = $file_config->row('file_path');
        // echo $this->db->last_query();die;
        $file_config_final_path = $file_config_host.$file_config_main_path.$file_config_second_path;

        return $file_config_final_path;
    }

    public function merge_print_file_path($customer_guid,$device_type,$module_type,$type,$code)
    {
        $file_config = $this->ci->db->query("SELECT file_host,file_path FROM $this->database1.acc WHERE acc_guid = '$customer_guid' LIMIT 1");
        // echo $this->ci->db->last_query();die;
        $file_config_host = $file_config->row('file_host');
        $file_config_main_path = $this->ci->db->query("SELECT value FROM $this->database1.config WHERE device = '$device_type' AND module = '$module_type' AND type = '$type' AND code = '$code'")->row('value');
        $file_config_second_path = $file_config->row('file_path');
        // echo $this->db->last_query();die;
        $file_config_final_path = $file_config_main_path.$file_config_second_path;

        return $file_config_final_path;
    } 

    public function merge_print_create_file_path($customer_guid,$device_type,$module_type,$type,$code)
    {
        $file_config = $this->ci->db->query("SELECT file_host,file_path FROM $this->database1.acc WHERE acc_guid = '$customer_guid' LIMIT 1");
        // echo $this->ci->db->last_query();die;
        $file_config_host = $file_config->row('file_host');
        $file_config_main_path = $this->ci->db->query("SELECT value FROM $this->database1.config WHERE device = '$device_type' AND module = '$module_type' AND type = '$type' AND code = '$code'")->row('value');
        $file_config_second_path = $file_config->row('file_path');
        // echo $this->db->last_query();die;
        $file_config_final_path = $file_config_main_path;

        return $file_config_final_path;
    }

    public function path_seperator($customer_guid,$device_type,$module_type,$type,$code)
    {
        $file_config = $this->ci->db->query("SELECT file_host,file_path FROM $this->database1.acc WHERE acc_guid = '$customer_guid' LIMIT 1");
        // echo $this->ci->db->last_query();die;
        $file_config_host = $file_config->row('file_host');
        $file_config_main_path = $this->ci->db->query("SELECT value FROM $this->database1.config WHERE device = '$device_type' AND module = '$module_type' AND type = '$type' AND code = '$code'")->row('value');
        $file_config_second_path = $file_config->row('file_path');
        // echo $this->db->last_query();die;
        $file_config_final_path = $file_config_main_path;

        return $file_config_final_path;
    }

    public function file_path_name($customer_guid,$device_type,$module_type,$type,$code)
    {
        $file_config = $this->ci->db->query("SELECT file_host,file_path FROM $this->database1.acc WHERE acc_guid = '$customer_guid' LIMIT 1");
        // echo $this->ci->db->last_query();die;
        $file_config_host = $file_config->row('file_host');
        $file_config_main_path = $this->ci->db->query("SELECT value FROM $this->database1.config WHERE device = '$device_type' AND module = '$module_type' AND type = '$type' AND code = '$code'")->row('value');
        $file_config_second_path = $file_config->row('file_path');
        // echo $this->db->last_query();die;
        $file_config_final_path = $file_config_main_path;

        return $file_config_final_path;
    }

    public function auth($customer_guid,$device_type,$module_type,$type,$code)
    {
        $file_config = $this->ci->db->query("SELECT file_host,file_path FROM $this->database1.acc WHERE acc_guid = '$customer_guid' LIMIT 1");
        // echo $this->ci->db->last_query();die;
        $file_config_host = $file_config->row('file_host');
        $file_config_main_path = $this->ci->db->query("SELECT value FROM $this->database1.config WHERE device = '$device_type' AND module = '$module_type' AND type = '$type' AND code = '$code'")->row('value');
        $file_config_second_path = $file_config->row('file_path');
        // echo $this->db->last_query();die;
        $file_config_final_path = $file_config_main_path;

        return $file_config_final_path;
    }    
}