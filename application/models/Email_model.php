<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class email_model extends CI_Model
{
    public function mailjet_api($email_add, $date, $bodyContent, $email_subject, $module, $cc_list_string, $pdf, $reply_to, $username)
    {
        die;
        if ($pdf != '' || $pdf != null) {
            $b64Doc = chunk_split(base64_encode(file_get_contents($pdf)));
            $filename = substr($pdf, strrpos($pdf, '/') + 1);
        } else {
            $b64Doc = '';
        }
        // $pdfBase64 = base64_encode(file_get_contents('uploads/qr_code/4/hah.pdf'));
        // echo $b64Doc;die;
        $from_email = $this->db->query("SELECT * FROM lite_b2b.mailjet_setup WHERE type = 'alert_retailer_supplier_setup' LIMIT 1");
        $to_email = $email_add;
        $to_email_name = $email_add;
        $variable = array('api_key' => '1234', 'secret_key' => '123456', 'module' => 'test');

        $replyto = array('Email' => $reply_to, 'Name' => $reply_to);
        $from = array('Email' => $from_email->row('sender_email'), 'Name' => $from_email->row('sender_name'));
        $to = array('Email' => $to_email, 'Name' => $to_email_name);
        $to_array = array($to);

        if ($cc_list_string != '' || $cc_list_string != null) {
            $test_array = explode(',', $cc_list_string);
            $cc_array = array();
            foreach ($test_array as $tarray) {
                // echo $tarray->sender_email;
                $cc = array('Email' => $tarray, 'Name' => $tarray);
                array_push($cc_array, $cc);
            }
        } else {
            $cc_array = '';
        }

        // $Bc = array('Email' => 'desmondm520@gmail.com','Name' => 'you1');
        $bcc_array = array();
        $variable1 = array($variable);
        $variables = array('var1' => $variable1);
        // $variables_array = array($variables);
        $templateid = 1090613;
        $Subject = $email_subject;
        $TextPart = $email_subject;
        $HTMLPart = $bodyContent;

        if ($b64Doc != '') {
            $attachment = array('ContentType' => 'application/pdf', 'Filename' => $filename, 'Base64Content' => $b64Doc);
            $attachment1 = array($attachment);
            $attachment_array = array($attachment);
            $data = array('from' => $from, 'to' => $to_array,
                'subject' => $Subject, 'textpart' => $TextPart,
                'htmlpart' => $HTMLPart, 'variables' => $variables,
                'cc' => $cc_array, 'replyto' => $replyto,
                'attachments' => $attachment_array);
        } else {
            $data = array('from' => $from, 'to' => $to_array,
                'subject' => $Subject, 'textpart' => $TextPart,
                'htmlpart' => $HTMLPart, 'variables' => $variables,
                'replyto' => $replyto);
        }

        $json_postfields = json_encode($data);

        $to_shoot_url = "localhost/pandaapi3rdparty/index.php/email_agent/mj_sendemail";

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $to_shoot_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($json_postfields, true),
            CURLOPT_HTTPAUTH => CURLAUTH_ANY,
            CURLOPT_HTTPHEADER => array(
                "x-api-key: 123456",
                "Content-Type: application/json",
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response, true);

        $retry = 0;
        while (curl_errno($curl) == 28 && $retry < 3) {
            $response = curl_exec($curl);
            $retry++;
        }

        if (!curl_errno($curl)) {
            if (isset($response->Messages[0])) {
                $status = $response->Messages[0]->Status;
            } else {
                $status = $response->ErrorMessage;
            }

            if ($status == 'success') {
                $ereponse = $response->Messages[0]->To[0]->MessageID;
                $data = array(
                    'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                    'created_by' => $username,
                    'recipient' => $to_email,
                    'sender' => $from_email->row('sender_email'),
                    'subject' => $email_subject,
                    'status' => 'SUCCESS',
                    'respond_message' => $ereponse,
                    'smtp_server' => 'mailjet',
                    'smtp_port' => 'mailjet',
                    'smtp_security' => 'mailjet',
                );
                $this->db->insert('lite_b2b.email_transaction', $data);
                // $this->session->set_flashdata('message', 'Message could not be sent. Mailer Error: '. $mail->ErrorInfo);
                //redirect('Email_controller/setup');
                if ($module != 'alert_notification') {
                    echo json_encode(array(
                        'status' => true,
                        'message' => 'success',
                        'action' => 'next',
                    ));
                };
            } else {
                $ereponse = $response->StatusCode . '-' . $response->ErrorMessage;
                $data = array(
                    'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                    'created_by' => $username,
                    'recipient' => $to_email,
                    'sender' => $from_email->row('sender_email'),
                    'subject' => $email_subject,
                    'status' => 'FAIL',
                    'respond_message' => $ereponse,
                    'smtp_server' => 'mailjet',
                    'smtp_port' => 'mailjet',
                    'smtp_security' => 'mailjet',
                );
                $this->db->insert('lite_b2b.email_transaction', $data);
                // $this->session->set_flashdata('message', 'Message could not be sent. Mailer Error: '. $mail->ErrorInfo);
                //redirect('Email_controller/setup');
                // if($module != 'alert_notification')
                // {
                echo json_encode(array(
                    'status' => false,
                    'message' => $ereponse,
                    'action' => 'retry',
                ));
                // };
            }

            curl_close($curl);
        } else {
            $ereponse = 'Curl error: ' . curl_error($curl);

            $data = array(
                'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                'created_by' => $_SESSION["userid"],
                'recipient' => $to_email,
                'sender' => $from_email->row('sender_email'),
                'subject' => $email_subject,
                'status' => 'FAIL',
                'respond_message' => $retry . $ereponse,
                'smtp_server' => 'mailjet',
                'smtp_port' => 'mailjet',
                'smtp_security' => 'mailjet',
            );
            $this->db->insert('lite_b2b.email_transaction', $data);
            // $this->session->set_flashdata('message', 'Message could not be sent. Mailer Error: '. $mail->ErrorInfo);
            //redirect('Email_controller/setup');
            // if($module != 'alert_notification')
            // {
            echo json_encode(array(
                'status' => false,
                'message' => $ereponse,
                'action' => 'retry',
            ));
        }

        curl_close($curl);

        return $response;
    }

}
// $email = array();
// foreach ($json_postfields['to'] as $key) {

//     // $email = $key['Email'];
//     array_push($email, $key['Email']);
// }
// $email = implode("','", $email);
// $email = "'" . $email . "'";

// $check_email_valid = $this->db->query("SELECT
// c.Email,
// c.Name,
// c.email_notification
// FROM
// (
// SELECT a.email_notification,a.email AS email,CONCAT(a.firstname,' ',a.lastname) AS name
// FROM osticket.ost_staff_test AS a
// UNION ALL
// SELECT b.email_notification,b.user_email AS email,b.user_name AS name
// FROM osticket.ost_user_test AS b
// ) AS c
// WHERE c.email IN($email)
// AND c.email_notification = '1'
// GROUP BY c.email");

// $json_value = json_encode($check_email_valid->result_array());

// $inset_log = $this->db->query("INSERT INTO osticket.email_log (`email_log_guid`,`json_value`,`created_at`)
// VALUES (REPLACE(UPPER(UUID()),'-',''),'$json_value', NOW())");

// $check_email_valid = array('to' => $check_email_valid->result_array());

// unset($json_postfields['to']);

// $json_postfields = $json_postfields + $check_email_valid;
