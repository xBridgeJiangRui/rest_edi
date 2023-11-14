<?php

require(APPPATH.'/libraries/REST_Controller.php');

class Bridge_ftp_process extends REST_Controller{

    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        $this->load->helper('url');
        $this->load->database();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 0);
    }

    public function test_api_get()
    {
        //$get_data = $this->db->query("SELECT * FROM lite_b2b.acc limit 1")->result_array();

        print_r(phpinfo()); die;
    }

    //po sftp
    public function ftp_bridge_post()
    {
        $sftp_host = $this->input->post('sftp_host');
        $sftp_port = $this->input->post('sftp_port');
        $sftp_username = $this->input->post('sftp_username');
        $sftp_password = $this->input->post('sftp_password');
        // $sftp_remote_path = $this->input->post('sftp_remote_path');
        $local_file_path = $this->input->post('local_file_path'); // to location;
        // $from_destination = $this->input->post('from_destination');
        $to_destination = $this->input->post('to_destination');
        $sftp_step = $this->input->post('sftp_step');

        // check connection to client host
        $connection = ssh2_connect($sftp_host, intval($sftp_port));
        // print_r($sftp_host); echo "\n\n";
        // print_r($sftp_port);  echo "\n\n";
        // print_r($sftp_username);  echo "\n\n";
        // print_r($sftp_password);  echo "\n\n";
        // print_r($sftp_remote_path);  echo "\n\n";
        // print_r($local_file_path);  echo "\n\n";
        // print_r($from_destination);  echo "\n\n";
        // print_r($to_destination);  echo "\n\n";
        // die;
        //print_r($connection); die;
        if ($connection === FALSE) {

            $result = array('status' => 'FAIL', 'message' => 'Fail connect client Host',);
        } else {

            // login client SFTP
            $state = ssh2_auth_password($connection, $sftp_username, $sftp_password);
            if ($state === FALSE) {

                $result = array('status' => 'FAIL', 'message' => 'Fail login in SFTP,username or passowrd invalid',);
            } else {

                // Retrieve the file sent via cURL
                if ($_FILES['file']['error'] === UPLOAD_ERR_OK) 
                {
                    if($sftp_step == '0')
                    {
                        $fileContent = file_get_contents($_FILES['file']['tmp_name']);

                        // send file to clients
                        $send = ssh2_scp_send($connection, $_FILES['file']['tmp_name'], $to_destination,   0644);
                        // $send = ssh2_scp_send($connection, $local_file_path, str_replace(' ', '', $remote_file),   0644);
    
                        if ($send === FALSE) {
    
                            $result = array('status' => 'FAIL', 'message' => 'Fail send file to client',);
                        } else {
    
                            $result = array('status' => 'SUCCESS', 'message' => 'SUCCESS send file to client',);
                        }
                    }
                    else
                    {
                        // Create SFTP session
                        $sftp = ssh2_sftp($connection);
                        
                        $sftpStream = fopen('ssh2.sftp://'.$sftp.$to_destination, 'w');

                        $response = array('status' => 'SUCCESS', 'message' => 'SUCCESS send file to client',);
                        
                        try {
                            if (!$sftpStream) {
                                $response = array('status' => 'FAIL', 'message' => 'Could not open remote file',);
                            }
                        
                            $data_to_send = file_get_contents($_FILES['file']['tmp_name']);
                        
                            if ($data_to_send === false) {
                                $response = array('status' => 'FAIL', 'message' => 'Could not open local file',);
                            }
                        
                            if (fwrite($sftpStream, $data_to_send) === false) {
                                $response = array('status' => 'FAIL', 'message' => 'Could not send data from file',);
                            }
                        
                            fclose($sftpStream);
                        
                        } catch (Exception $e) {
                            $response = array('status' => 'FAIL', 'message' => 'Fail send file to client',);

                            fclose($sftpStream);
                        }
                    }
                } 
                else 
                {
                    $result = array('status' => 'FAIL', 'message' => 'Fail file retrieve',);
                }
            }
        }

        // $state = ssh2_auth_password($connection, 'webdev', 'Panda@Web$4321');

        ssh2_exec($connection, 'exit');
        // unset($connection);

        $this->response($result);

    }

    //invoice sftp
    public function ftp_inv_bridge_post()
    {
        $status = '';
        $message = '';
        $customer_guid = $this->input->post('customer_guid');
        $supplier_guid = $this->input->post('supplier_guid');
        $sftp_host = $this->input->post('sftp_host');
        $sftp_port = $this->input->post('sftp_port');
        $sftp_username = $this->input->post('sftp_username');
        $sftp_password = $this->input->post('sftp_password');
        $from_destination = $this->input->post('sftp_remote_path'); // from location
        $final_to_destination = $this->input->post('local_file_path'); // to location;
        $to_destination = '/var/www/html/rest_edi/uploads/GR/new/' . $supplier_guid . '/';
        // print_r($sftp_host); die;
        // print_r(phpinfo()); die;
        $connection = ssh2_connect($sftp_host, intval($sftp_port));
        // print_r($connection); die;

        if(!ssh2_auth_password($connection, $sftp_username, $sftp_password))
        {
            $json = array('status' => 'false', 'message' => 'Fail to connect client username and password',);
            return $this->response($json);
            // die("Unable to connect.");
        }

        if(!$stream = ssh2_sftp($connection))
        {
            $json = array('status' => 'false', 'message' => 'Unable to create stream',);
            return $this->response($json);
            // die("Unable to create stream");
        }

        if(!$dir = opendir("ssh2.sftp://{$stream}/{$from_destination}"))
        {
            $json = array('status' => 'false', 'message' => 'Could not open directory',);
            return $this->response($json);
            // die("Could not open dir");
        }

        $b2b_store_path = explode('/', substr($to_destination, 0, -1));

        $file_path_string = '';

        // check path exists
        foreach ($b2b_store_path as $row) {
            $file_path_string .= $row . '/';

            if (!file_exists($file_path_string)) {
                mkdir($file_path_string, 0777, true);
                chmod($file_path_string, 0777);
            }
        }

        $array_filename = array();
        while (false !== ($file = readdir($dir)))
        {
            if ($file == "." || $file == "..")
            {
                continue;
            }

            // Check if the file has the CSV extension
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'csv') {
                continue;
            }

            $check_data_log_file = $this->db->query("SELECT a.guid FROM lite_b2b.edi_filename_log a WHERE a.customer_guid = '$customer_guid' AND a.supplier_guid = '$supplier_guid' AND a.filename = '$file' AND a.`status` NOT IN ('99') ")->result_array();

            if(count($check_data_log_file) > 0 )
            {
                continue;
            }

            $log_guid = $this->db->query("SELECT REPLACE(UPPER(UUID()),'-','') AS uuid")->row('uuid');

            $process_info_log1 = array(
                'guid' => $log_guid,
                'customer_guid' => $customer_guid,
                'supplier_guid' => $supplier_guid,
                'sftp_host' => $sftp_host,
                'filename' => $file,
                'status' => '0',
                'created_at' => $this->db->query("SELECT NOW() as now")->row('now'),
                'created_by' => 'system',
            );
            $this->db->insert('lite_b2b.edi_filename_log',$process_info_log1);

            $array_filename[] = array(
                'file_name' => $file,
                'file_guid' => $log_guid,
            );
        }

        //print_r($array_filename); die;

        $data_inv_ftp = array();
        foreach ($array_filename as $row)
        {
            $file_guid = $row['file_guid'];
            $file = $row['file_name'];

            // print_r($file_guid); echo "\n\n";
            // print_r($file); die;
            $remotePath = "ssh2.sftp://{$stream}/{$from_destination}{$file}";
            $localPath = $to_destination . $file;
            $file_move_from = $from_destination . $file;
            $from_destination_move_file = '/DOWNLOAD/'; 
            $file_move_to = "ssh2.sftp://{$stream}/{$from_destination_move_file}{$file}";

            // print_r($file_move_to); die;

            if(!$remote = @fopen($remotePath,"r")){

                $update_log1 = $this->db->query("UPDATE lite_b2b.edi_filename_log SET `status` = '99', `message` = 'Unable to open remote file' WHERE customer_guid = '$customer_guid' AND supplier_guid = '$supplier_guid' AND guid = '$file_guid' ");

                continue;
            }
            
            if(!$local = @fopen($localPath, "w")){

                $update_log2 = $this->db->query("UPDATE lite_b2b.edi_filename_log SET `status` = '99', `message` = 'Unable to create local file' WHERE customer_guid = '$customer_guid' AND supplier_guid = '$supplier_guid' AND guid = '$file_guid' ");

                continue;
            }

            $read = 0;
            $filesize = filesize($remotePath);
            while ($read < $filesize && ($buffer = fread($remote, $filesize - $read))){
                $read += strlen($buffer);
                
                if(fwrite($local, $buffer) === false)
                {
                    $update_log3 = $this->db->query("UPDATE lite_b2b.edi_filename_log SET `status` = '99', `message` = 'Failed Download File' WHERE `customer_guid` = '$customer_guid' AND `supplier_guid` = '$supplier_guid' AND `guid` = '$file_guid' ");

                    $status = 'false';
                    $message = 'Failed Download file';
                }
                else
                {
                    $update_log3 = $this->db->query("UPDATE lite_b2b.edi_filename_log SET `status` = '1', `message` = 'Success Download File' WHERE `customer_guid` = '$customer_guid' AND `supplier_guid` = '$supplier_guid' AND `guid` = '$file_guid' ");

                    $status = 'true';
                    $message = 'Success Download file.';
                }
            }

            fclose($local);
            fclose($remote);

            //print_r($status); die;

            if($status === 'true') 
            {
                $to_shoot_back_url = 'http://10.10.0.100/rest_b2b/index.php/Edi/retrieve_ftp_invoice';

                $data_inv_ftp = array(
                    'local_file_path' => $remotePath,
                    'from_destination' => $localPath,
                    'final_to_destination' => $final_to_destination,
                    'filename' => $file,
                    "file" => new CURLFile($localPath), // from_destination
                );

                //print_r($data_inv_ftp); die;

                $cuser_name = 'ADMIN';
                $cuser_pass = '1234';

                $ch = curl_init($to_shoot_back_url);
                // curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-API-KEY: " . "CODEX1234" ));
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Api-KEY: 123456"));
                curl_setopt($ch, CURLOPT_USERPWD, "$cuser_name:$cuser_pass");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_inv_ftp);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $result = curl_exec($ch);
                $output = json_decode($result);
                // $status = json_encode($output);
                // print_r($output->result);die;
                //echo $result;die;
                curl_close($ch);

                if(isset($output->status))
                {
                    if($output->status == 'true')
                    {
                        if (file_exists($to_destination)) {
                            $result_remove = unlink($remotePath);
                        }

                        if (file_exists($localPath)) {
                            $result_remove_local = unlink($localPath);
                        }
            
                        if($result_remove == true && $result_remove_local == true) {
            
                            $update_log4 = $this->db->query("UPDATE lite_b2b.edi_filename_log SET `status` = '2', `message` = 'Success copy and unlink file' WHERE `customer_guid` = '$customer_guid' AND `supplier_guid` = '$supplier_guid' AND `guid` = '$file_guid' ");
                        } else {
            
                            $update_log4 = $this->db->query("UPDATE lite_b2b.edi_filename_log SET `status` = '1', `message` = 'Failed copy and unlink file' WHERE `customer_guid` = '$customer_guid' AND `supplier_guid` = '$supplier_guid' AND `guid` = '$file_guid' ");
                        }

                        $status = 'true';
                        $message = 'Success';
                    }
                    else
                    {
                        $status = 'false';
                        $message = 'Process Error';
                    }
                }
                else
                {
                    $status = 'false';
                    $message = 'Process Error';
                }
            }
        }

        ssh2_exec($connection, 'exit');

        $json = array(
            'status' => $status, 
            'message' => $message,
        );

        $this->response($json);
    }
}