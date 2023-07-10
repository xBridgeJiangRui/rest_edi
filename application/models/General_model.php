<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class general_model extends CI_Model
{

    public $table = 'set_user';

    public function __construct()
    {
        parent::__construct();
    }

    public function check_trans_code($code)
    {
        $query = $this->db->query("SELECT * FROM rest_hub.`set_doc_code` a WHERE a.`doc_code` = '$code'
            and a.enable = '1' ");
        return $query;
    }

    public function check_module($code)
    {
        $query = $this->db->query("SELECT * FROM rest_hub.`set_module` a WHERE a.module = '$code' AND a.`enable` = '1'; ");
        return $query;
    }

    public function check_config($code)
    {
        $query = $this->db->query("SELECT * FROM rest_hub.`config` a WHERE a.code = '$code'; ");
        return $query;
    }

    public function check_edi($module, $trans_code)
    {
        $query = $this->db->query("SELECT * FROM rest_hub.`set_edi` a WHERE a.`module` = '$module' and a.`trans_code` = '$trans_code'");
        return $query;
    }

    public function date()
    {
        $date = $this->db->query("SELECT CURDATE() as curdate")->row('curdate');
        return $date;
    }

    public function datetime()
    {
        $datetime = $this->db->query("SELECT NOW() as datetime")->row('datetime');
        return $datetime;
    }

    public function guid()
    {
        $guid = $this->db->query("SELECT REPLACE(UPPER(UUID()),'-','') as guid")->row('guid');
        return $guid;
    }

    public function insert_doc_logs($guid, $key, $module, $value, $status, $message)
    {
        $data = array(
            'guid' => $guid,
            'key' => $key,
            'module' => $module,
            'value' => $value,
            'status' => $status,
            'message' => $message,
            'datetime' => $this->datetime(),
        );
        $query = $this->db->insert('rest_hub.doc_logs', $data);
        return $query;
    }

    public function update_status_logs($guid, $module, $status, $message, $variable)
    {
        $data = array(
            'guid' => $guid,
            'module' => $module,
            'status' => $status,
            'message' => $message,
            'variable' => $variable,
            'datetime' => $this->datetime(),
        );
        $query = $this->db->insert('rest_hub.status_logs', $data);
        return $query;
    }

    public function update_backend($status, $batch_no)
    {
        $data = array(
            'status' => $status,
        );

        $this->db->where('batch_no', $batch_no);
        $query = $this->db->update('backend.dbnote_batch', $data);
        return $query;
    }

    public function get_b2b_acc_trans()
    {
        $result = $this->db->query("SELECT a.* FROM backend.acc_trans a INNER JOIN b2b_hub.acc_trans b ON a.refno = b.refno WHERE b.exported = '0'")->result_array();

        return $result;
    }

    public function get_b2b_acc_trans_c2()
    {
        $result = $this->db->query("SELECT a.* FROM backend.acc_trans_c2 a INNER JOIN b2b_hub.acc_trans_c2 b ON a.refno = b.refno AND a.line = b.line WHERE b.exported = '0'")->result_array();

        return $result;
    }

    public function get_b2b_pomain()
    {
        $header = $this->db->query("SELECT a.* FROM backend.pomain a INNER JOIN b2b_hub.pomain b ON a.RefNo = b.refno WHERE b.exported = '0'")->result_array();

        return $header;
    }

    public function get_b2b_pochild()
    {

        $child = $this->db->query("SELECT a.* FROM backend.pochild a INNER JOIN b2b_hub.pochild b ON a.RefNo = b.refno AND a.Line = b.line WHERE b.exported = '0'")->result_array();

        return $child;
    }

    public function get_b2b_grmain()
    {
        $header = $this->db->query("SELECT a.* FROM backend.grmain a INNER JOIN b2b_hub.grmain b ON a.RefNo = b.refno WHERE b.exported = '0'")->result_array();

        return $header;
    }

    public function get_b2b_grchild()
    {

        $child = $this->db->query("SELECT a.* FROM backend.grchild a INNER JOIN b2b_hub.grchild b ON a.RefNo = b.refno AND a.Line = b.line WHERE b.exported = '0'")->result_array();

        return $child;
    }

    public function get_b2b_supcus()
    {
        // $this->db->query("SET global net_buffer_length=1000000");
        // $this->db->query("set global max_allowed_packet=1000000000");
        $result = $this->db->query("SELECT a.* FROM backend.supcus a INNER JOIN b2b_hub.supcus b ON a.Type = b.type AND a.Code = b.code WHERE b.exported = '0'")->result_array();

        return $result;
    }

    public function get_b2b_cp_set_branch()
    {
        $result = $this->db->query("SELECT a.* FROM backend.cp_set_branch a INNER JOIN b2b_hub.cp_set_branch b ON a.BRANCH_GUID = b.branch_guid WHERE b.exported = '0'")->result_array();

        return $result;
    }

    public function get_b2b_locationgroup()
    {
        $result = $this->db->query("SELECT a.* FROM backend.locationgroup a INNER JOIN b2b_hub.locationgroup b ON a.Code = b.code WHERE b.exported = '0'")->result_array();

        return $result;
    }

    public function get_b2b_companyprofile()
    {
        $result = $this->db->query("SELECT a.* FROM backend.companyprofile a INNER JOIN b2b_hub.companyprofile b ON a.CompanyName = b.companyname WHERE b.exported = '0'")->result_array();

        return $result;
    }

    public function count_b2b_sku_cs_date($perioddate)
    {
        $newmonth = explode("-", $perioddate);
        $lastmonth = date("Y-m", mktime(0, 0, 0, date($newmonth['1']) - 1, 1, date($newmonth['0'])));
        $result = $this->db->query("SELECT COUNT('PeriodCode') AS cnt FROM report_summary.`sku_cs_date` WHERE PeriodCode IN ('$perioddate','$lastmonth')")->row('cnt');

        return $result;
    }

    public function count_b2b_sku_cs_date_b2b_status($perioddate)
    {
        $newmonth = explode("-", $perioddate);
        $lastmonth = date("Y-m", mktime(0, 0, 0, date($newmonth['1']) - 1, 1, date($newmonth['0'])));
        $result = $this->db->query("SELECT COUNT('PeriodCode') AS cnt FROM report_summary.`sku_cs_date` WHERE PeriodCode IN ('$perioddate','$lastmonth') AND b2b_status = 0")->row('cnt');

        return $result;
    }

    public function get_b2b_sku_cs_date_log($perioddate)
    {
        $header = $this->db->query("SELECT * FROM b2b_hub.sku_cs_date WHERE periodcode = '$perioddate' AND exported = '0'")->result_array();

        return $header;
    }

    public function get_b2b_sku_cs_date($perioddate)
    {
        $newmonth = explode("-", $perioddate);
        $lastmonth = date("Y-m", mktime(0, 0, 0, date($newmonth['1']) - 1, 1, date($newmonth['0'])));
        $check_row = $this->db->query("SELECT * FROM b2b_hub.sku_cs_date WHERE periodcode = '$perioddate' AND exported = '0'");

        if ($check_row->num_rows() > 0) {

            $result = $this->db->query("SELECT a.* FROM report_summary.sku_cs_date a INNER JOIN b2b_hub.sku_cs_date b ON a.PeriodCode = b.periodcode WHERE a.PeriodCode IN ('$perioddate','$lastmonth') ORDER BY a.bizdate")->result_array();
        } else {

            $result = $check_row->result_array();
        }

        return $result;
    }

    public function get_b2b_sku_cs_date_limit($perioddate, $doc_date)
    {
        $newmonth = explode("-", $perioddate);
        $lastmonth = date("Y-m", mktime(0, 0, 0, date($newmonth['1']) - 1, 1, date($newmonth['0'])));
        $check_status = $this->db->query("SELECT transfer_status FROM b2b_hub.transfer_log WHERE period_code = '$perioddate' AND doc_date = '$doc_date'")->row('transfer_status');

        if ($check_status != 1) {

            $current_complete = $this->db->query("SELECT current_complete FROM b2b_hub.transfer_log WHERE period_code = '$perioddate' AND doc_date = '$doc_date'")->row('current_complete');

            $result = $this->db->query("SELECT a.* FROM report_summary.sku_cs_date a INNER JOIN b2b_hub.sku_cs_date b ON a.PeriodCode = b.periodcode WHERE a.PeriodCode IN ('$perioddate','$lastmonth') ORDER BY a.bizdate LIMIT $current_complete,10000")->result_array();
        } else {

            $result = array();
        }

        return $result;
    }

    public function get_customer_guid()
    {
        $result = $this->db->query("SELECT customer_guid FROM rest_api.run_once_config WHERE active = '1'")->row('customer_guid');

        return $result;
    }

    public function result_to_array_to_string($result, $array_key)
    {
        $array = array();

        foreach ($result->result() as $key) {
            array_push($array, $key->$array_key);
        }

        $string = implode("','", $array);

        $string = "'" . $string . "'";

        return $string;
    }

    public function update_data($table, $col_guid, $guid, $data)
    {
        $this->db->where($col_guid, $guid);
        $this->db->update($table, $data);
    }

    public function send_mailjet_third_party($email_add, $date, $bodyContent, $email_subject, $module, $cc_list_string, $pdf, $reply_to, $filename)
    {
        if ($pdf != '' || $pdf != null) {

            $b64Doc = base64_encode(file_get_contents($pdf));

            $filename = $filename;
        } else {
            $b64Doc = '';
        }

        $from_email = $this->db->query("SELECT * FROM lite_b2b.mailjet_setup WHERE type = 'daily_po_notification' LIMIT 1");
        $to_email = $email_add;
        $to_email_name = $email_add;
        // $to_email = 'danielweng57@gmail.com';
        // $to_email_name = 'danielweng57@gmail.com';         
        $variable = array('api_key' => '1234', 'secret_key' => '123456', 'module' => 'test');

        $replyto = array('Email' => $reply_to, 'Name' => $reply_to);
        $from = array('Email' => $from_email->row('sender_email'), 'Name' => $from_email->row('sender_name'));
        $to = array('Email' => $to_email, 'Name' => $to_email_name);
        $to_array = array($to);

        if ($cc_list_string != '' || $cc_list_string != null) {

            $cc_array = array();

            foreach ($cc_list_string as $tarray) {

                $cc = array('Email' => $tarray, 'Name' => $tarray);
                array_push($cc_array, $cc);
            }
        } else {
            $cc_array = '';
        }

        $bcc_array = array();
        $variable1 = array($variable);
        $variables = array('var1' => $variable1);
        // $variables_array = array($variables);
        $templateid = 1090613;
        $Subject = $email_subject;
        $TextPart = $email_subject;
        $HTMLPart = $bodyContent;
        $attachment = array('ContentType' => 'application/pdf', 'Filename' => $filename, 'Base64Content' => $b64Doc);
        $attachment1 = array($attachment);
        $attachment_array = array($attachment);

        if ($b64Doc != '') {
            $data = array('from' => $from, 'to' => $to_array, 'subject' => $Subject, 'textpart' => $TextPart, 'htmlpart' => $HTMLPart, 'variables' => $variables, 'cc' => $cc_array, 'replyto' => $replyto, 'attachments' => $attachment_array);
        } else {
            $data = array('from' => $from, 'to' => $to_array, 'subject' => $Subject, 'textpart' => $TextPart, 'htmlpart' => $HTMLPart, 'variables' => $variables, 'cc' => $cc_array, 'replyto' => $replyto);
        }
        // $data2 = array($data);
        // $data3 = array('Messages' => $data2);
        // $t = array($t, "Mary", "Peter", "Sally");

        $myJSON = json_encode($data);
        // echo $myJSON;die;
        //52.163.112.202
        // $to_shoot_url = $this->local_ip . "/pandaapi3rdparty/index.php/email_agent/mj_sendemail";
        $to_shoot_url = "10.10.0.251/pandaapi3rdparty/index.php/email_agent/mj_sendemail";
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

        // $to_shoot_url = $this->local_ip.'/pandaapi3rdparty/index.php/email_agent/mj_sendemail';
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
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result1 = json_decode($result);

        $retry = 0;
        while (curl_errno($ch) == 28 && $retry < 3) {
            $response = curl_exec($ch);
            $retry++;
        }

        return $httpcode;
    }

    public function amount_to_word($number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $decimal_part = $decimal;
        $hundred = null;
        $hundreds = null;
        $digits_length = strlen($no);
        $decimal_length = strlen($decimal);

        $i = 0;
        $str = array();
        $str2 = array();
        $words = array(
            0 => '', 1 => 'one', 2 => 'two',
            3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
            7 => 'seven', 8 => 'eight', 9 => 'nine',
            10 => 'ten', 11 => 'eleven', 12 => 'twelve',
            13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
            16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
            19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
            40 => 'forty', 50 => 'fifty', 60 => 'sixty',
            70 => 'seventy', 80 => 'eighty', 90 => 'ninety'
        );
        $digits = array('', 'hundred', 'thousand', 'million');

        while ($i < $digits_length) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? '' : null;
                $hundred = ($counter == 1 && $str[0]) ? '' : null;
                $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else $str[] = null;
        }

        $d = 0;
        while ($d < $decimal_length) {
            $divider = ($d == 2) ? 10 : 100;
            $decimal_number = floor($decimal % $divider);
            $decimal = floor($decimal / $divider);
            $d += $divider == 10 ? 1 : 2;
            if ($decimal_number) {
                $plurals = (($counter = count($str2)) && $decimal_number > 9) ? 's' : null;
                $hundreds = ($counter == 1 && $str2[0]) ? '' : null;
                @$str2[] = ($decimal_number < 21) ? $words[$decimal_number] . ' ' . $digits[$decimal_number] . $plural . ' ' . $hundred : $words[floor($decimal_number / 10) * 10] . ' ' . $words[$decimal_number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else $str2[] = null;
        }

        $ringgit = implode('', array_reverse($str));
        $sen = implode('', array_reverse($str2));
        if ($ringgit == '') {
            if ($decimal_part > 0) {
                $sen = 'cents ' . $sen;
            } else {
                $sen = '';
            }
        } else {
            if ($decimal_part > 0) {
                $sen = 'and cents ' . $sen;
            } else {
                $sen = '';
            }
        }
        //$sen = ($decimal_part > 0) ? 'and cents '.$sen : '';
        return ($ringgit ? $ringgit : '') . $sen;
    }
}
