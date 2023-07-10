<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class lite_b2b_notification_model extends CI_Model
{
    public $table_backend = 'backend.';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('lite_b2b_model');
    }

    public function push()
    {
        $query = $this->db->query("SELECT 
            a.ref_no,
            a.app_notification_guid,
            a.title,
            a.message,
            a.type,
            a.doc_type,
            a.platform,
            a.user_guid,
            a.customer_guid,
            b.fcm_token AS token,
            IFNULL(c.dbnote_guid,'') AS dbnote_guid 
        FROM lite_b2b_apps.app_notification a 
        INNER JOIN lite_b2b_apps.user_session b 
        ON a.`user_guid` = b.`user_guid` 
        LEFT JOIN b2b_summary.dbnote_batch AS c
        ON a.ref_no = c.batch_no
        WHERE a.issend = '0' AND expired_at > NOW() ");

        $now = $this->db->query("SELECT NOW() as now")->row('now');

        $app_fcm_server_key = $this->db->query("SELECT value FROM lite_b2b_apps.config WHERE type = 'app_fcm_server_key'")->row('value');

        $success_count = 0;

        $firebase_error_message = array();

        //$status = '';

        foreach ($query->result() as $key) {

            $redirect = $this->lite_b2b_model->customer_info($key->customer_guid)->row('redirect');

            if ($key->platform == 'web') {

                /*$web_notification_server_key = $this->db->query("SELECT value FROM ost_config_test WHERE id = '207'")->row('value');

                    $logostaff = $this->db->query("SELECT * FROM ost_file_test WHERE type = 'logo' AND default_staff = '1'");

                    $backdropstaff = $this->db->query("SELECT * FROM ost_file_test WHERE type = 'backdrop' AND default_staff = '1'");

                    $icon_image = base_url('/uploads/'.$logostaff->row('name'));

                    $background_image = base_url('/uploads/'.$backdropstaff->row('name'));

                    $content ='{
                        "notification":{
                            "title":"'.$notification_title.'",
                            "body":"'.addslashes($key->message).'",
                            "icon": $icon_image.
                            "image": $background_image,
                            "click_action": "https://helpdesk.panda-eco.com"
                        },
                        "to":"'.$key->token.'",
                    }';

                    $ch = curl_init();

                    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
                                        
                    $headers = array();
                    $headers[] = 'Authorization: key='.$web_notification_server_key;
                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    $result = curl_exec($ch);
                    if (curl_errno($ch)) {
                        echo 'Error:' . curl_error($ch);
                    }

                    curl_close($ch);*/

                // // print("<pre>".print_r($result,true)."</pre>");

            } else {

                $opts = [
                    "http" => [
                        "method" => "POST",
                        "header" => "Authorization:key=" . $app_fcm_server_key . "\r\n" .
                            "Content-Type:application/json\r\n",
                        'content' => '{
                                  "notification":{
                                    "title":"' . $key->title . '",
                                    "body":"' . addslashes($key->message) . '",
                                    "sound":"default",
                                    
                                    "icon":"fcm_push_icon",
                                  },
                                  "data":{
                                    "click_action":"FLUTTER_NOTIFICATION_CLICK",
                                    "ref_no":"' . $key->ref_no . '",
                                    "type": "' . $key->type . '",
                                    "customer_guid": "' . $key->customer_guid . '",
                                    "customer_redirect": "' . $redirect . '",
                                    "doc_type": "' . $key->doc_type . '",
                                    "dbnote_guid": "' . $key->dbnote_guid . '",
                                  },
                                    "to":"' . $key->token . '",
                                    "priority": "high",
                                    "restricted_package_name":"",
                                    "time_to_live": 86400,

                                }'
                    ]
                ];

                $context = stream_context_create($opts);
                $firebase_result = json_decode(
                    file_get_contents('https://fcm.googleapis.com/fcm/send', false, $context)
                );
                //print_r($firebase_result);die;
                if ($firebase_result->failure == 1) {

                    $data = array(

                        'firebase_error_message' => $firebase_result->results[0]->error,
                        'user_guid' => $key->user_guid,
                        'ref_no' => $key->ref_no,
                        'token' => $key->token,

                    );

                    array_push($firebase_error_message, $data);
                } elseif ($firebase_result->success == 1) {

                    $this->db->set('issend', '1');
                    $this->db->set('send_at', $now);
                    $this->db->where('app_notification_guid', $key->app_notification_guid);
                    $this->db->update('lite_b2b_apps.app_notification');

                    $success_count++;
                } else {

                    echo 'Error';
                };
            }
        }

        if ($query->num_rows() > 0) {
            $message_from_panda_developer = 'Success Send ' . $success_count . ' Notification';
        } else {
            $message_from_panda_developer = 'No notification sent either all is_send is 1 or expired already';
        } //close else 

        if (sizeof($firebase_error_message) > 0) {
            $haveerror = true;


            $guid = $this->db->query("SELECT REPLACE(UPPER(UUID()),'-','') as guid ")->row('guid');
            $now_datetime = $this->db->query("SELECT NOW() AS now_datetime")->row('now_datetime');

            $app_notification_log_value = json_encode($firebase_error_message);

            /*$checking = $this->db->query("SELECT * FROM app_notification_log WHERE `value` = '$app_notification_log_value'");

            if ($checking->num_rows() == 0 ) {
                $data = array(
                        'app_notification_log_guid' => $guid, 
                        'value' => $app_notification_log_value, 
                        'created_at' => $now_datetime, 
                        'created_by' => 'AgentTask', 
                );

                $this->db->insert('lite_b2b_apps.app_notification_log', $data);
            }*/
        } else {
            $haveerror = false;
        }

        $data = array(

            'firebase_error_message' => $firebase_error_message,
            'message_from_panda_developer' => $message_from_panda_developer,
            'haveerror' => $haveerror,
            //'status' => $status

        );

        return $data;
    }
}
