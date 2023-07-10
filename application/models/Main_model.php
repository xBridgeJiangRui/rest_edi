<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

  class Main_model extends CI_Model {

    public function __construct()
    {
      $this->load->database();
    }

    public function query_call($controller_model, $function, $data = null)
    {     
        
        if(!empty($data))
        {
            $child_guid = $data['child_guid'];
            foreach ($data AS $set_data => $value) {
                $this->db->query("SET @".$set_data." = '".mb_convert_encoding(addslashes($value), "GB18030")."'");
            }
        }

if(!empty($child_guid))
{
        $query = $this->db->query("
                SELECT b.query_select, b.query_insert, b.query_update, b.query_delete, b.more_equal_than, b.less_than, b.query_type,
                b.return, b.fail_message, b.query_parameter_guid, b.return_name, b.seq FROM set_query_main AS a
                INNER JOIN set_query_child AS b ON a.main_guid = b.main_guid
                WHERE a.module = 'report_panda_api' AND a.controller_model = '$controller_model' AND a.function = '$function' AND b.enable = '1' AND b.child_guid = '$child_guid' ORDER BY b.seq
        ");
}
else
{
        $query = $this->db->query("
                SELECT b.query_select, b.query_insert, b.query_update, b.query_delete, b.more_equal_than, b.less_than, b.query_type,
                b.return, b.fail_message, b.query_parameter_guid, b.return_name, b.seq FROM set_query_main AS a
                INNER JOIN set_query_child AS b ON a.main_guid = b.main_guid
                WHERE a.module = 'report_panda_api' AND a.controller_model = '$controller_model' AND a.function = '$function' AND b.enable = '1' ORDER BY b.seq
        ");    
}
        $datas = array();
        $return_index = 0;

        foreach ($query->result() AS $row)
        {
            $set_query_parameter = $this->db->query("SELECT database_name, table_name FROM set_query_parameter WHERE guid IN ("."'".str_replace(',' ,"','", $row->query_parameter_guid)."'".") AND enable = '1'");

            if($row->query_type == "SELECT")
            {
                $exe_query = $row->query_select;

                if(isset($data['order']) && isset($data['dir']))
                {
                    if(strpos($exe_query, "order") && strpos($exe_query, "@order @dir"))
                    {
                        $exe_query = preg_replace("/\s@order\s/", " ".$data['order']." ", $exe_query);
                        $exe_query = preg_replace("/\s@dir\s/", " ".$data['dir']." ", $exe_query);
                    }
                }

                if(isset($data['limit']) && isset($data['start']))
                {
                    if(strpos($exe_query, "LIMIT") && strpos($exe_query, "@start , @limit"))
                    {
                        $exe_query = preg_replace("/\s@start\s/", " ".$data['start']." ", $exe_query);
                        $exe_query = preg_replace("/\s@limit\s/", " ".$data['limit']." ", $exe_query);
                    }
                }

                if(!empty($data))
                {
                    foreach($data AS $key => $value) {
                        if(preg_match('/_IN/', $key) || preg_match('/IN/', $key))
                        {
                            $exe_query = preg_replace("/\s@".$key."\s/", " ".$value." ", $exe_query);
                        }
                    }
                }

                foreach ($set_query_parameter->result() AS $sqp) {
                    $exe_query = preg_replace('/\s'.$sqp->table_name.'\s/', ' '.$sqp->database_name.'.'.$sqp->table_name.' ', $exe_query);
                }

                $result = $this->db->query($exe_query);

                if(!$result)
                {
                    $error_message = $this->db->error();
                    $query = $this->db->last_query();

                    $log = array(
                        "guid" => $this->db->query("SELECT UPPER(REPLACE(UUID(), '-', '')) AS guid")->row("guid"),
                        "module" => 'report_panda_api',
                        "controller_model" => $controller_model,
                        "function" => $function,
                        "seq" => $row->seq,
                        "code" => $error_message['code'],
                        "run_script" => $query,
                        "message" => $error_message['message'],
                        "created_at" => $this->db->query("SELECT NOW() AS datetime")->row('datetime'),
                    );

                    $this->db->insert("report_panda_api.query_logs", $log);

                    return array(
                        "message" => "fails",
                    );
                }

                if($row->more_equal_than > 0)
                {
                    if($result->num_rows() >= $row->more_equal_than)
                    {
                        if($row->return == 1)
                        {
                            $datas[$row->return_name] = $this->convert_to_chinese($result->result_array());
                            $datas['message'] = "success";
                            $return_index++;
                        }
                        else
                        {
                            return array(
                                "message" => $row->fail_message,
                            );
                        }
                    }
                    else
                    {
                        if($row->return == 1)
                        {
                            $datas['message'] = $row->fail_message;
                        }
                        else
                        {
                            $datas['message'] = "success";
                        }
                    }
                }
                else if($row->less_than > 0)
                {
                    if($result->num_rows() < $row->less_than)
                    {
                        if($row->return == 1)
                        {
                            $datas[$row->return_name] = $this->convert_to_chinese($result->result_array());
                            $datas['message'] = "success";
                            $return_index++;
                        }
                        else
                        {
                            return array(
                                "message" => $row->fail_message,
                            );
                        }
                    }
                    else
                    {
                        if($row->return == 1)
                        {
                            $datas['message'] = $row->fail_message;
                        }
                        else
                        {
                            $datas['message'] = "success";
                        }
                    }
                }
            }
            elseif($row->query_type == "UPDATE" || $row->query_type == "INSERT")
            {
                $exe_query = $row->query_select;

                if(!empty($exe_query) && $exe_query != NULL)
                {
                    foreach ($set_query_parameter->result() AS $sqp) {
                        $exe_query = preg_replace('/\s'.$sqp->table_name.'\s/', ' '.$sqp->database_name.'.'.$sqp->table_name.' ', $exe_query);
                    }

                    if(!empty($data))
                    {
                        foreach($data AS $key => $value) {
                            if(preg_match('/_IN/', $key) || preg_match('/IN/', $key))
                            {
                                $exe_query = preg_replace("/\s@".$key."\s/", " ".$value." ", $exe_query);
                            }
                        }
                    }

                    $result = $this->db->query($exe_query);

                    if(!$result)
                    {
                        $error_message = $this->db->error();
                        $query = $this->db->last_query();

                        $log = array(
                            "guid" => $this->db->query("SELECT UPPER(REPLACE(UUID(), '-', '')) AS guid")->row("guid"),
                            "module" => 'report_panda_api',
                            "controller_model" => $controller_model,
                            "function" => $function,
                            "seq" => $row->seq,
                            "code" => $error_message['code'],
                            "run_script" => $query,
                            "message" => $error_message['message'],
                            "created_at" => $this->db->query("SELECT NOW() AS datetime")->row('datetime'),
                        );

                        $this->db->insert("report_panda_api.query_logs", $log);

                        return array(
                            "message" => "fails",
                        );
                    }

                    if($row->more_equal_than > 0)
                    {
                        if($result->num_rows() >= $row->more_equal_than)
                        {
                            if($row->query_type == "UPDATE")
                            {
                                $update_query = $row->query_update;
                            }
                            else
                            {
                                $update_query = $row->query_insert;
                            }

                            foreach ($set_query_parameter->result() AS $sqp) {
                                $update_query = preg_replace('/\s'.$sqp->table_name.'\s/', ' '.$sqp->database_name.'.'.$sqp->table_name.' ', $update_query);
                            }

                            $query_result = $this->db->query($update_query);

                            if(!$query_result)
                            {
                                $error_message = $this->db->error();
                                $query = $this->db->last_query();

                                $log = array(
                                    "guid" => $this->db->query("SELECT UPPER(REPLACE(UUID(), '-', '')) AS guid")->row("guid"),
                                    "module" => 'report_panda_api',
                                    "controller_model" => $controller_model,
                                    "function" => $function,
                                    "seq" => $row->seq,
                                    "code" => $error_message['code'],
                                    "run_script" => $query,
                                    "message" => $error_message['message'],
                                    "created_at" => $this->db->query("SELECT NOW() AS datetime")->row('datetime'),
                                );

                                $this->db->insert("report_panda_api.query_logs", $log);

                                return array(
                                    "message" => "fails",
                                );
                            }
                        }
                    }
                    else if($row->less_than > 0)
                    {
                        if($result->num_rows() < $row->less_than)
                        {
                            if($row->query_type == "UPDATE")
                            {
                                $update_query = $row->query_update;
                            }
                            else
                            {
                                $update_query = $row->query_insert;
                            }

                            foreach ($set_query_parameter->result() AS $sqp) {
                                $update_query = preg_replace('/\s'.$sqp->table_name.'\s/', ' '.$sqp->database_name.'.'.$sqp->table_name.' ', $update_query);
                            }

                            $query_result = $this->db->query($update_query);

                            if(!$query_result)
                            {
                                $error_message = $this->db->error();
                                $query = $this->db->last_query();

                                $log = array(
                                    "guid" => $this->db->query("SELECT UPPER(REPLACE(UUID(), '-', '')) AS guid")->row("guid"),
                                    "module" => 'report_panda_api',
                                    "controller_model" => $controller_model,
                                    "function" => $function,
                                    "seq" => $row->seq,
                                    "code" => $error_message['code'],
                                    "run_script" => $query,
                                    "message" => $error_message['message'],
                                    "created_at" => $this->db->query("SELECT NOW() AS datetime")->row('datetime'),
                                );

                                $this->db->insert("report_panda_api.query_logs", $log);

                                return array(
                                    "message" => "fails",
                                );
                            }
                        }
                    }

                    $datas['message'] = "success";
                }
                else
                {
                    if($row->query_type == "UPDATE")
                    {
                        $update_query = $row->query_update;
                    }
                    else
                    {
                        $update_query = $row->query_insert;
                    }

                    foreach ($set_query_parameter->result() AS $sqp) {
                        $update_query = preg_replace('/\s'.$sqp->table_name.'\s/', ' '.$sqp->database_name.'.'.$sqp->table_name.' ', $update_query);
                    }

                    if(!empty($data))
                    {
                        foreach($data AS $key => $value) {
                            if(preg_match('/_IN/', $key) || preg_match('/IN/', $key))
                            {
                                $update_query = preg_replace("/\s@".$key."\s/", " ".$value." ", $update_query);
                            }
                        }
                    }

                    $result = $this->db->query($update_query);

                    if(!$result)
                    {
                        $error_message = $this->db->error();
                        $query = $this->db->last_query();

                        $log = array(
                            "guid" => $this->db->query("SELECT UPPER(REPLACE(UUID(), '-', '')) AS guid")->row("guid"),
                            "module" => 'report_panda_api',
                            "controller_model" => $controller_model,
                            "function" => $function,
                            "seq" => $row->seq,
                            "code" => $error_message['code'],
                            "run_script" => $query,
                            "message" => $error_message['message'],
                            "created_at" => $this->db->query("SELECT NOW() AS datetime")->row('datetime'),
                        );

                        $this->db->insert("report_panda_api.query_logs", $log);

                        return array(
                            "message" => "fails",
                        );
                    }

                    if($this->db->affected_rows() == 0)
                    {
                        return array(
                            "message" => $row->fail_message,
                        );
                    }
                    else
                    {
                        $datas['message'] = "success";
                    }
                }
            }
            elseif($row->query_type == "DELETE")
            {
                $delete_query = $row->query_delete;
                foreach ($set_query_parameter->result() AS $sqp) {
                    $delete_query = preg_replace('/\s'.$sqp->table_name.'\s/', ' '.$sqp->database_name.'.'.$sqp->table_name.' ', $delete_query);
                }

                if(!empty($data))
                {
                    foreach($data AS $key => $value) {
                        if(preg_match('/_IN/', $key) || preg_match('/IN/', $key))
                        {
                            $delete_query = preg_replace("/\s@".$key."\s/", " ".$value." ", $delete_query);
                        }
                    }
                }

                $query_result = $this->db->query($delete_query);

                if(!$query_result)
                {
                    $error_message = $this->db->error();
                    $query = $this->db->last_query();

                    $log = array(
                        "guid" => $this->db->query("SELECT UPPER(REPLACE(UUID(), '-', '')) AS guid")->row("guid"),
                        "module" => 'report_panda_api',
                        "controller_model" => $controller_model,
                        "function" => $function,
                        "seq" => $row->seq,
                        "code" => $error_message['code'],
                        "run_script" => $query,
                        "message" => $error_message['message'],
                        "created_at" => $this->db->query("SELECT NOW() AS datetime")->row('datetime'),
                    );

                    $this->db->insert("report_panda_api.query_logs", $log);

                    return array(
                        "message" => "fails",
                    );
                }

                if($query_result)
                {
                    $datas['message'] = "success";
                }
                else
                {
                    return array(
                        "message" => $row->fail_message,
                    );
                }
            }
        }
        return $datas;
    }

    public function convert_to_chinese($array) {
        foreach($array as $key => $value)
        {
            if(is_array($value))
            {
                $array[$key] = $this->convert_to_chinese($value);
            }
            else
            {
                $array[$key] = mb_convert_encoding($value, "UTF-8", "GB-18030");
            }
        }

        return $array;
    }
}
