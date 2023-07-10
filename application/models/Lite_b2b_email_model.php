<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Lite_b2b_email_model extends CI_Model
{
    public $table_backend = 'backend.';
    
    public function __construct()
    {
        parent::__construct();
    }

    public function send_mailjet_third_party($email_add, $date, $bodyContent, $email_subject, $module,$reply_to)
    {
        // $b64Doc = chunk_split(base64_encode(file_get_contents('uploads/qr_code/4/cc.pdf'))); 
        $b64Doc = ''; 
        // $pdfBase64 = base64_encode(file_get_contents('uploads/qr_code/4/hah.pdf')); 
        // echo $b64Doc;die;      
        $from_email = $this->db->query("SELECT * FROM lite_b2b.mailjet_setup WHERE type = 'reset_password' LIMIT 1");
        $to_email = $email_add;
        $to_email_name = $email_add;
        //$to_email = 'danielweng57@gmail.com';
        //$to_email_name = 'danielweng57@gmail.com';
        $cc = 'desmondm520@gmail.com';
        $cc_name = 'desmondm520@gmail.com';
        $variable = array('api_key' => '1234','secret_key' => '123456', 'module' => 'test');

        $replyto = array('Email' => $reply_to,'Name' => $reply_to);
        $from = array('Email' => $from_email->row('sender_email'),'Name' => $from_email->row('sender_name'));
        $to = array('Email' => $to_email,'Name' => $to_email_name);
        $to_array = array($to);
        $cc = array('Email' => $cc_name,'Name' => $cc_name);
        $cc_array = array($cc);
        // $Bc = array('Email' => 'desmondm520@gmail.com','Name' => 'you1');
        $bcc_array = array();
        $variable1 = array($variable);
        $variables = array('var1' => $variable1);
        // $variables_array = array($variables);
        $templateid = 1090613;
        $Subject = $email_subject;
        $TextPart = $email_subject;
        $HTMLPart = $bodyContent; 
        $attachment = array('ContentType' => 'application/pdf','Filename' => 'sample.pdf','Base64Content' => $b64Doc);
        $attachment1 = array($attachment);
        $attachment_array = array($attachment);
        $data = array('from' => $from,'to' => $to_array,'subject' => $Subject,'textpart' => $TextPart,'htmlpart' => $HTMLPart,'variables' => $variables,'replyto' =>$replyto);
        $data2 = array($data);
        // $data3 = array('Messages' => $data2);
        // $t = array($t, "Mary", "Peter", "Sally");

        $myJSON = json_encode($data);
        // echo $myJSON;die;

        $to_shoot_url = "localhost/pandaapi3rdparty/index.php/email_agent/mj_sendemail";
        $ch = curl_init(); 

        curl_setopt_array($ch, array(
          CURLOPT_URL => $to_shoot_url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $myJSON,
          CURLOPT_HTTPHEADER => array(
            "x-api-key: 123456",
            "Content-Type: application/json"
          ),
        ));

        // $to_shoot_url = 'localhost/pandaapi3rdparty/index.php/email_agent/mj_sendemail';
        // $ch = curl_init($to_shoot_url); 
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-API-KEY: " . "123456" ));
        // curl_setopt($ch, CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        // // curl_setopt($ch, CURLOPT_USERPWD, $mailjet_user.":".$mailjet_pass);
        // curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        // curl_setopt($ch,CURLOPT_POSTFIELDS, $myJSON);
        $result = curl_exec($ch);
        $result1 = json_decode($result);
        // print_r($result);die;
        $retry = 0;
        while(curl_errno($ch) == 28 && $retry < 3){
            $response = curl_exec($ch);
            $retry++;
        }

        if(!curl_errno($ch))
        {
            if(isset($result1->Messages[0]))
            {
                $status = $result1->Messages[0]->Status;
            }
            else
            {
                $status = $result1->ErrorMessage;
            }


            if($status == 'success')
            {
                $ereponse = $result1->Messages[0]->To[0]->MessageID;
                $data = array(
                    'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                    'created_by' =>'URL_TASK',
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
                if($module != 'alert_notification')
                {
                    return array(
                            'status' => true,
                            'message' => 'Email Sent Successfully!',
                            'action'=> 'next',
                            );
                };
            }
            else
            {
                $ereponse = $result1->StatusCode.'-'.$result1->ErrorMessage;
                $data = array(
                    'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                    'created_by' =>'URL_TASK',
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
                 return array(
                    'status' => false,
                    'message' => $ereponse,
                    'action'=> 'retry',
                    );
                // };
            }

            curl_close($ch);
        }
        else
        {
                $ereponse = 'Curl error: '.curl_error($ch);

                $data = array(
                    'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                    'created_by' =>'URL_TASK',
                    'recipient' => $to_email,
                    'sender' => $from_email->row('sender_email'),
                    'subject' => $email_subject,
                    'status' => 'FAIL',
                    'respond_message' => $retry.$ereponse,
                    'smtp_server' => 'mailjet',
                    'smtp_port' => 'mailjet',
                    'smtp_security' => 'mailjet',
                    );
                $this->db->insert('email_transaction', $data);
                // $this->session->set_flashdata('message', 'Message could not be sent. Mailer Error: '. $mail->ErrorInfo);
                //redirect('Email_controller/setup');
                // if($module != 'alert_notification')
                // {
                 return array(
                    'status' => false,
                    'message' => $ereponse,
                    'action'=> 'retry',
                    );          
        }        
    }    

    public function send_mailjet_third_party_document($email_add, $date, $bodyContent, $email_subject, $module,$cc_list_string,$pdf,$reply_to,$filename,$user_guid)
    {
        // die;
        if($pdf != '' || $pdf != null)
        { 
            $b64Doc = chunk_split(base64_encode(file_get_contents($pdf))); 
            $filename = substr($pdf, strrpos($pdf, '/') + 1);
        }
        else
        {
            $b64Doc = ''; 
        }
        // $pdfBase64 = base64_encode(file_get_contents('uploads/qr_code/4/hah.pdf')); 
        // echo $b64Doc;die;      
        $from_email = $this->db->query("SELECT * FROM lite_b2b.mailjet_setup WHERE type = 'reject_po_notification' LIMIT 1");
        $to_email = $email_add;
        $to_email_name = $email_add;
        // $to_email = 'danielweng57@gmail.com';
        // $to_email_name = 'danielweng57@gmail.com';            
        $variable = array('api_key' => '1234','secret_key' => '123456', 'module' => 'test');

        $replyto = array('Email' => $reply_to,'Name' => $reply_to);
        $from = array('Email' => $from_email->row('sender_email'),'Name' => $from_email->row('sender_name'));
        $to = array('Email' => $to_email,'Name' => $to_email_name);
        $to_array = array($to);

        if($cc_list_string != '' || $cc_list_string != null)
        {
            $test_array = explode(',',$cc_list_string);
            $cc_array=array();
            foreach($test_array as $tarray)
            {
                // echo $tarray->sender_email;
                $cc = array('Email' => $tarray,'Name' => $tarray);
                array_push($cc_array, $cc);
            }
        }
        else
        {
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

        if($filename != '')
        {   
            //$pdf = ('.'.$filename);
            $pdf = ($filename);
            // echo $filename;die;
            // if(file_exists('./uploads/bataras/PO_S033_BRNPO20090296.pdf'))
            // {
            //     echo 1;
            // };
            // die;
            // $pdf = ('uploads/hwathai/Hwa Thai_B2B letter to suppliers.pdf');
            // echo $pdf;die; 
            $b64Doc = chunk_split(base64_encode(file_get_contents($pdf))); 
            // echo $b64Doc;die;
            // $b64Doc = '';
            $refno = substr($pdf, strrpos($pdf, "/") + 1);
            // echo $refno;die;
            $filename = $refno;
            // echo $refno.'asdadssadasdadasd-'.$b64Doc;die;

            $attachment = array('ContentType' => 'application/pdf','Filename' => $filename,'Base64Content' => $b64Doc);

            $attachment_array[] = $attachment;
        }
        else
        {
            $b64Doc = ''; 
        }

        if($b64Doc != '')
        {
            $attachment = array('ContentType' => 'application/pdf','Filename' => $filename,'Base64Content' => $b64Doc);
            $attachment1 = array($attachment);
            $attachment_array = array($attachment);            
            $data = array('from' => $from,'to' => $to_array,'subject' => $Subject,'textpart' => $TextPart,'htmlpart' => $HTMLPart,'variables' => $variables,'cc' => $cc_array, 'replyto' =>$replyto,'attachments' => $attachment_array);
        }
        else
        {
            $data = array('from' => $from,'to' => $to_array,'subject' => $Subject,'textpart' => $TextPart,'htmlpart' => $HTMLPart,'variables' => $variables, 'replyto' =>$replyto);
        }
        // $data2 = array($data);
        // $data3 = array('Messages' => $data2);
        // $t = array($t, "Mary", "Peter", "Sally");

        $myJSON = json_encode($data);
        // echo $myJSON;die;

        $to_shoot_url = "localhost/pandaapi3rdparty/index.php/email_agent/mj_sendemail";
        $ch = curl_init(); 

        curl_setopt_array($ch, array(
          CURLOPT_URL => $to_shoot_url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $myJSON,
          CURLOPT_HTTPHEADER => array(
            "x-api-key: 123456",
            "Content-Type: application/json"
          ),
        ));

        // $to_shoot_url = 'localhost/pandaapi3rdparty/index.php/email_agent/mj_sendemail';
        // $ch = curl_init($to_shoot_url); 
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-API-KEY: " . "123456" ));
        // curl_setopt($ch, CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        // // curl_setopt($ch, CURLOPT_USERPWD, $mailjet_user.":".$mailjet_pass);
        // curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        // curl_setopt($ch,CURLOPT_POSTFIELDS, $myJSON);
        $result = curl_exec($ch);
        $result1 = json_decode($result);
        // print_r($result);die;
        $retry = 0;
        while(curl_errno($ch) == 28 && $retry < 3){
            $response = curl_exec($ch);
            $retry++;
        }

        if(!curl_errno($ch))
        {
            if(isset($result1->Messages[0]))
            {
                $status = $result1->Messages[0]->Status;
            }
            else
            {
                $status = $result1->ErrorMessage;
            }


            if($status == 'success')
            {
                $ereponse = $result1->Messages[0]->To[0]->MessageID;
                $data = array(
                    'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                    'created_by' =>$user_guid,
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
                if($module != 'alert_notification')
                {
                    return json_encode(array(
                            'status' => true,
                            'message' => 'success',
                            'action'=> 'next',
                            ));
                };
            }
            else
            {
                $ereponse = $result1->StatusCode.'-'.$result1->ErrorMessage;
                $data = array(
                    'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                    'created_by' =>$user_guid,
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
                 return json_encode(array(
                    'status' => false,
                    'message' => $ereponse,
                    'action'=> 'retry',
                    ));
                // };
            }

            curl_close($ch);
        }
        else
        {
                $ereponse = 'Curl error: '.curl_error($ch);

                $data = array(
                    'created_at' => $this->db->query("SELECT now() as now")->row('now'),
                    'created_by' =>$user_guid,
                    'recipient' => $to_email,
                    'sender' => $from_email->row('sender_email'),
                    'subject' => $email_subject,
                    'status' => 'FAIL',
                    'respond_message' => $retry.$ereponse,
                    'smtp_server' => 'mailjet',
                    'smtp_port' => 'mailjet',
                    'smtp_security' => 'mailjet',
                    );
                $this->db->insert('lite_b2b.email_transaction', $data);
                // $this->session->set_flashdata('message', 'Message could not be sent. Mailer Error: '. $mail->ErrorInfo);
                //redirect('Email_controller/setup');
                // if($module != 'alert_notification')
                // {
                 return json_encode(array(
                    'status' => false,
                    'message' => $ereponse,
                    'action'=> 'retry',
                    ));          
        }         
    }  


}

?> 
