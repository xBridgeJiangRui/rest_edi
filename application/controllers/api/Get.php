<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// This can be removed if you use __autoload() in config.php OR use Modular Extensions
require APPPATH . '/libraries/REST_Controller.php';

/**
 * This is an example of a few basic user interaction methods you could use
 * all done with a hardcoded array
 *
 * @package         CodeIgniter
 * @subpackage      Rest Server
 * @category        Controller
 * @author          Phil Sturgeon, Chris Kacerguis
 * @license         MIT
 * @link            https://github.com/chriskacerguis/codeigniter-restserver
 */
class get extends REST_Controller {

    function __construct()
    {
        // Construct the parent class
        parent::__construct();

        $this->load->model('Get_model');
        
        // Configure limits on our controller methods
        // Ensure you have created the 'limits' table and enabled 'limits' within application/config/rest.php
        $this->methods['account_get']['limit'] = 500; // 500 requests per hour per user/key
        $this->methods['users_post']['limit'] = 100; // 100 requests per hour per user/key
        $this->methods['users_delete']['limit'] = 50; // 50 requests per hour per user/key
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

    public function check_trans_code($code)
    {
        $query = $this->db->query("SELECT * FROM rest_hub.`set_doc_code` a WHERE a.`doc_code` = '$code'
            and a.enable = '1'");
        return $query; 
    }

    public function check_config($code)
    {
        $query = $this->db->query("SELECT * FROM rest_hub.`config` a WHERE a.code = '$code'; ");
        return $query; 
    }

    public function check_division($code)
    {
        $query = $this->db->query("SELECT a.`GROUP_CODE`,b.`GROUP_DESC` FROM backend.`set_group_dept` a INNER JOIN backend.`set_group` b ON a.`GROUP_CODE` = b.`GROUP_CODE` WHERE a.`DEPT_CODE` = '$code';");
        return $query;
    }

    public function check_itemmaster($itemlink)
    {
        $query = $this->db->query("SELECT * FROM backend.`itemmaster` a WHERE a.`ItemLink` = '$itemlink';");
        return $query;
    }


    function return_result_false($status,$message,$module)
    {
        $result = $this->response([
                'status' => FALSE,
                'message' => $message,
                'module' => $module,
        ], REST_Controller::HTTP_NOT_FOUND);
        return $result;
    }

    function return_result_true($status,$message,$module)
    {
        $result = $this->response([
                'status' => TRUE,
                'message' => $message,
                'module' => $module,
        ], REST_Controller::HTTP_OK);
        return $result;
    }



    public function index_get()
    {
        if($this->get('module') == 'itemmaster'  && $this->check_trans_code('itemmaster')->row('path') == 'OUT')
        {
            $query = $this->Get_model->itemmaster($this->get('key'));
            //$query = $this->Get_model->itemmaster_barcode($this->get('barcode'));
        }
        elseif($this->get('module') == 'dnbatch' && $this->check_trans_code('dnbatch')->row('path') == 'OUT')
        {
            $query = $this->Get_model->dnbatch($this->get('key'));    
        }
        else
        {
            if($this->check_trans_code($this->get('module'))->num_rows() == 0)
            {
                $this->return_result_false(FALSE,'Invalid Module or Parameter',$this->get('module')); 
            }
            else
            {
                if($this->check_trans_code($this->get('module'))->row('path') == 'IN')
                {
                    $this->return_result_false(FALSE,'Path incorrect.Please contact administrator',$this->get('module')); 
                };
            }
        }

        if($query->num_rows() == 0)
        {
            $this->return_result_false(FALSE,'No available record',$this->get('module')); 
        }
        elseif($query->num_rows() > 0 && $this->get('module') == 'dnbatch')
        {
            foreach($query->result() as $key )
            {
                $data[] = array(
                    "owner_code" => $this->check_config('OWNER_CODE')->row('description'),
                    "refno" => $key->batch_no,
                    "sup_code" => $key->sup_code,
                    "sup_name" => $key->sup_name,
                    "posted_at" => $key->posted_at,
                    "posted_by" => $key->posted_by,
                    "location" => $key->location,  
                    "order_line" => $this->Get_model->dnbatch_child($key->dbnote_guid)->result()
                );
            }

            header('Content-Type: application/json');
            echo json_encode(array(
                'status' => true,
                'module' => $this->get('module'),
                'data' => $data
            ));

        }
        else
        {   
            $this->response([
                'status' => true,
                'module' => $this->get('module'),
                'data' => $query->result(),
            ], REST_Controller::HTTP_OK);
        }
    }

    public function b2b_pomain_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_pomain'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $pomain_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $refno = $i['refno'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('refno',$refno);
                    $this->db->update($hub_db.'.pomain', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'];
                    } else{
                        $failed_refno[] =  $i['refno'];
                    }

                } else{

                    $this->db->delete($hub_db.'.pomain', array('refno' => $refno)); 
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Reference No doesn\'t exist.';
                    }
                    elseif(!isset($i['PODate']) || $i['PODate'] == '')
                    {
                        $status = 'Failed';
                        $message = 'PO date doesn\'t exist';
                    }
                    elseif(!isset($i['DeliverDate']) || $i['DeliverDate'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Deliver date doesn\'t exist';
                    }
                    elseif(!isset($i['DueDate']) || $i['DueDate'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Due date doesn\'t exist';
                    }
                    elseif(!isset($i['Location']) || $i['Location'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Location doesn\'t exist';
                    }
                    elseif(!isset($i['SCode']) || $i['SCode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'SCode doesn\'t exist';
                    }
                    elseif(!isset($i['Total']) || $i['Total'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Total doesn\'t exist';
                    }
                    elseif(!isset($i['total_include_tax']) || $i['total_include_tax'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Total include tax doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['RefNo']) || $i['RefNo'] == ''?$i['RefNo']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $pomain_data[] = array(
                            'RefNo' => $i['RefNo'],
                            'PODate' => $i['PODate'],
                            'DeliverDate' => $i['DeliverDate'],
                            'DueDate' => $i['DueDate'],
                            'IssueStamp' => isset($i['IssueStamp'])?$i['IssueStamp']:'',
                            'IssuedBy' => isset($i['IssuedBy'])?$i['IssuedBy']:'',
                            'laststamp' => isset($i['laststamp'])?$i['laststamp']:'',
                            'Dept' => isset($i['Dept'])?$i['Dept']:'',
                            'Location' => $i['Location'],
                            'ApprovedBy' => isset($i['ApprovedBy'])?$i['ApprovedBy']:'',
                            'SCode' => $i['SCode'],
                            'SName' => isset($i['SName'])?$i['SName']:'',
                            'STerm' => isset($i['STerm'])?$i['STerm']:'',
                            'STel' => isset($i['STel'])?$i['STel']:'',
                            'SFax' => isset($i['SFax'])?$i['SFax']:'',
                            'Remark' => isset($i['Remark'])?$i['Remark']:'',
                            'SubTotal1' => isset($i['SubTotal1'])?$i['SubTotal1']:'',
                            'Discount1' => isset($i['Discount1'])?$i['Discount1']:'',
                            'Discount1Type' => isset($i['Discount1Type'])?$i['Discount1Type']:'',
                            'SubTotal2' => isset($i['SubTotal2'])?$i['SubTotal2']:'',
                            'Discount2' => isset($i['Discount2'])?$i['Discount2']:'',
                            'Discount2Type' => isset($i['Discount2Type'])?$i['Discount2Type']:'',
                            'Total' => $i['Total'],
                            'BillStatus' => isset($i['BillStatus'])?$i['BillStatus']:'',
                            'AccStatus' => isset($i['AccStatus'])?$i['AccStatus']:'',
                            'Closed' => isset($i['Closed'])?$i['Closed']:'',
                            'Amendment' => isset($i['Amendment'])?$i['Amendment']:'',
                            'Completed' => isset($i['Completed'])?$i['Completed']:'',
                            'Disc1Percent' => isset($i['Disc1Percent'])?$i['Disc1Percent']:'',
                            'Disc2Percent' => isset($i['Disc2Percent'])?$i['Disc2Percent']:'',
                            'SubDeptCode' => isset($i['SubDeptCode'])?$i['SubDeptCode']:'',
                            'postby' => isset($i['postby'])?$i['postby']:'',
                            'postdatetime' => isset($i['postdatetime'])?$i['postdatetime']:'',
                            'CalDueDateby' => isset($i['CalDueDateby'])?$i['CalDueDateby']:'',
                            'expiry_date' => isset($i['expiry_date'])?$i['expiry_date']:'',
                            'pur_expiry_days' => isset($i['pur_expiry_days'])?$i['pur_expiry_days']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'cp_main_guid' => isset($i['cp_main_guid'])?$i['cp_main_guid']:'',
                            'AutoClosePO' => isset($i['AutoClosePO'])?$i['AutoClosePO']:'',
                            'stockday_min' => isset($i['stockday_min'])?$i['stockday_min']:'',
                            'stockday_max' => isset($i['stockday_max'])?$i['stockday_max']:'',
                            'send' => isset($i['send'])?$i['send']:'',
                            'send_remark' => isset($i['send_remark'])?$i['send_remark']:'',
                            'send_at' => isset($i['send_at'])?$i['send_at']:'',
                            'send_by' => isset($i['send_by'])?$i['send_by']:'',
                            'rejected' => isset($i['rejected'])?$i['rejected']:'',
                            'rejected_remark' => isset($i['rejected_remark'])?$i['rejected_remark']:'',
                            'rejected_at' => isset($i['rejected_at'])?$i['rejected_at']:'',
                            'rejected_by' => isset($i['rejected_by'])?$i['rejected_by']:'',
                            'approved' => isset($i['approved'])?$i['approved']:'',
                            'approved_remark' => isset($i['approved_remark'])?$i['approved_remark']:'',
                            'approved_at' => isset($i['approved_at'])?$i['approved_at']:'',
                            'approved_by' => isset($i['approved_by'])?$i['approved_by']:'',
                            'loc_group' => isset($i['loc_group'])?$i['loc_group']:'',
                            'run_cost' => isset($i['run_cost'])?$i['run_cost']:'',
                            'rebate_amt' => isset($i['rebate_amt'])?$i['rebate_amt']:'',
                            'dn_amt' => isset($i['dn_amt'])?$i['dn_amt']:'',
                            'in_kind' => isset($i['in_kind'])?$i['in_kind']:'',
                            'cross_ref' => isset($i['cross_ref'])?$i['cross_ref']:'',
                            'cross_ref_module' => isset($i['cross_ref_module'])?$i['cross_ref_module']:'',
                            'hq_issue' => isset($i['hq_issue'])?$i['hq_issue']:'',
                            'gst_tax_sum' => isset($i['gst_tax_sum'])?$i['gst_tax_sum']:'',
                            'tax_code_purchase' => isset($i['tax_code_purchase'])?$i['tax_code_purchase']:'',
                            'total_include_tax' => $i['total_include_tax'],
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'price_include_tax' => isset($i['price_include_tax'])?$i['price_include_tax']:'',
                            'surchg_tax_sum' => isset($i['surchg_tax_sum'])?$i['surchg_tax_sum']:'',
                            'tax_inclusive' => isset($i['tax_inclusive'])?$i['tax_inclusive']:'',
                            'doc_name_reg' => isset($i['doc_name_reg'])?$i['doc_name_reg']:'',
                            'ibt' => isset($i['ibt'])?$i['ibt']:'',
                            'multi_tax_code' => isset($i['multi_tax_code'])?$i['multi_tax_code']:'',
                            'refno2' => isset($i['refno2'])?$i['refno2']:'',
                            'discount_as_inv' => isset($i['discount_as_inv'])?$i['discount_as_inv']:'',
                            'ibt_gst' => isset($i['ibt_gst'])?$i['ibt_gst']:'',
                            'rebate_as_inv' => isset($i['rebate_as_inv'])?$i['rebate_as_inv']:'',
                            'uploaded' => isset($i['uploaded'])?$i['uploaded']:'',
                            'uploaded_at' => isset($i['uploaded_at'])?$i['uploaded_at']:'',
                            'unpost' => isset($i['unpost'])?$i['unpost']:'',
                            'unpost_at' => isset($i['unpost_at'])?$i['unpost_at']:'',
                            'unpost_by' => isset($i['unpost_by'])?$i['unpost_by']:'',
                            'cancel' => isset($i['cancel'])?$i['cancel']:'',
                            'cancel_at' => isset($i['cancel_at'])?$i['cancel_at']:'',
                            'cancel_by' => isset($i['cancel_by'])?$i['cancel_by']:'',
                            'cancel_reason' => isset($i['cancel_reason'])?$i['cancel_reason']:'',
                            'b2b_status' => isset($i['b2b_status'])?$i['b2b_status']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['RefNo'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'pomain',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){
                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($pomain_data) > 0)
            {
                $pomain_data_chunk = array_chunk($pomain_data,1000);
                foreach($pomain_data_chunk AS $pomain_child)
                {
                    $this->db->insert_batch($hub_db.'.pomain',$pomain_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'PO main successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'PO main successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'PO main failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
        
    }

    public function b2b_pochild_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_pochild'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $pochild_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $parameter = array(
                    'refno' => $i['refno'],
                    'line' => $i['line'],
                );

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $update_parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
 
                    $this->db->where($parameter);
                    $this->db->update($hub_db.'.pochild', $update_parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'].'-Line'.$i['line'];
                    } else{
                        $failed_refno[] =  $i['refno'].'-Line'.$i['line'];
                    }

                } else{

                    $this->db->delete($hub_db.'.pochild', $parameter);
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Reference No doesn\'t exist.';
                    }
                    elseif(!isset($i['Line']) || $i['Line'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Line doesn\'t exist';
                    }
                    elseif(!isset($i['Barcode']) || $i['Barcode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Barcode doesn\'t exist';
                    }
                    elseif(!isset($i['Itemcode']) || $i['Itemcode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Itemcode doesn\'t exist';
                    }
                    elseif(!isset($i['TotalPrice']) || $i['TotalPrice'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Total Price doesn\'t exist';
                    }
                    elseif(!isset($i['ItemLink']) || $i['ItemLink'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Item link doesn\'t exist';
                    }
                    elseif(!isset($i['Dept']) || $i['Dept'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Department doesn\'t exist';
                    }
                    elseif(!isset($i['price_include_tax']) || $i['price_include_tax'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Price include tax doesn\'t exist';
                    }
                    elseif(!isset($i['TotalPrice_include_tax']) || $i['TotalPrice_include_tax'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Total price include tax doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => (isset($i['RefNo']) && isset($i['Line'])) || $i['RefNo'] != ''?$i['RefNo'].'-Line'.$i['Line']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $pochild_data[] = array(
                            'RefNo' => $i['RefNo'],
                            'Line' => $i['Line'],
                            'EntryType' => isset($i['EntryType'])?$i['EntryType']:'',
                            'PriceType' => isset($i['PriceType'])?$i['PriceType']:'',
                            'Barcode' => $i['Barcode'],
                            'Itemcode' => $i['Itemcode'],
                            'Description' => isset($i['Description'])?$i['Description']:'',
                            'Qty' => isset($i['Qty'])?$i['Qty']:'',
                            'UM' => isset($i['UM'])?$i['UM']:'',
                            'UnitPrice' => isset($i['UnitPrice'])?$i['UnitPrice']:'',
                            'Disc1Type' => isset($i['Disc1Type'])?$i['Disc1Type']:'',
                            'Disc1Value' => isset($i['Disc1Value'])?$i['Disc1Value']:'',
                            'Disc2Type' => isset($i['Disc2Type'])?$i['Disc2Type']:'',
                            'Disc2Value' => isset($i['Disc2Value'])?$i['Disc2Value']:'',
                            'NetUnitPrice' => isset($i['NetUnitPrice'])?$i['NetUnitPrice']:'',
                            'DiscAmt' => isset($i['DiscAmt'])?$i['DiscAmt']:'',
                            'TotalPrice' => $i['TotalPrice'],
                            'PackSize' => isset($i['PackSize'])?$i['PackSize']:'',
                            'Colour' => isset($i['Colour'])?$i['Colour']:'',
                            'Size' => isset($i['Size'])?$i['Size']:'',
                            'Brand' => isset($i['Brand'])?$i['Brand']:'',
                            'ArticleNo' => isset($i['ArticleNo'])?$i['ArticleNo']:'',
                            'TempRecvQty' => isset($i['TempRecvQty'])?$i['TempRecvQty']:'',
                            'ReceivedQty' => isset($i['ReceivedQty'])?$i['ReceivedQty']:'',
                            'BalanceQty' => isset($i['BalanceQty'])?$i['BalanceQty']:'',
                            'TempItem' => isset($i['TempItem'])?$i['TempItem']:'',
                            'TempItemChanged' => isset($i['TempItemChanged'])?$i['TempItemChanged']:'',
                            'DiscValue' => isset($i['DiscValue'])?$i['DiscValue']:'',
                            'CostAftDisc' => isset($i['CostAftDisc'])?$i['CostAftDisc']:'',
                            'CostFactor' => isset($i['CostFactor'])?$i['CostFactor']:'',
                            'InvActCost' => isset($i['InvActCost'])?$i['InvActCost']:'',
                            'InvActTotCost' => isset($i['InvActTotCost'])?$i['InvActTotCost']:'',
                            'SysQOH' => isset($i['SysQOH'])?$i['SysQOH']:'',
                            'SysAvgCost' => isset($i['SysAvgCost'])?$i['SysAvgCost']:'',
                            'WeightAvgCost' => isset($i['WeightAvgCost'])?$i['WeightAvgCost']:'',
                            'GroupNo' => isset($i['GroupNo'])?$i['GroupNo']:'',
                            'GroupNo2' => isset($i['GroupNo2'])?$i['GroupNo2']:'',
                            'ItemLink' => $i['ItemLink'],
                            'Amendment' => isset($i['Amendment'])?$i['Amendment']:'',
                            'AmendQty' => isset($i['AmendQty'])?$i['AmendQty']:'',
                            'BulkQty' => isset($i['BulkQty'])?$i['BulkQty']:'',
                            'UMBulk' => isset($i['UMBulk'])?$i['UMBulk']:'',
                            'BQty' => isset($i['BQty'])?$i['BQty']:'',
                            'PQty' => isset($i['PQty'])?$i['PQty']:'',
                            'ItemRemark' => isset($i['ItemRemark'])?$i['ItemRemark']:'',
                            'Dept' => $i['Dept'],
                            'SubDept' => isset($i['SubDept'])?$i['SubDept']:'',
                            'Category' => isset($i['Category'])?$i['Category']:'',
                            'OnHandQty' => isset($i['OnHandQty'])?$i['OnHandQty']:'',
                            'AvgPESalesQty' => isset($i['AvgPESalesQty'])?$i['AvgPESalesQty']:'',
                            'LastPESalesQty' => isset($i['LastPESalesQty'])?$i['LastPESalesQty']:'',
                            'SellingPrice' => isset($i['SellingPrice'])?$i['SellingPrice']:'',
                            'mrank' => isset($i['mrank'])?$i['mrank']:'',
                            'cartonprice' => isset($i['cartonprice'])?$i['cartonprice']:'',
                            'LastCost' => isset($i['LastCost'])?$i['LastCost']:'',
                            'POItemAvgCost' => isset($i['POItemAvgCost'])?$i['POItemAvgCost']:'',
                            'GroupCost' => isset($i['GroupCost'])?$i['GroupCost']:'',
                            'InvTurnOver' => isset($i['InvTurnOver'])?$i['InvTurnOver']:'',
                            'SoldByWeight' => isset($i['SoldByWeight'])?$i['SoldByWeight']:'',
                            'sales_current' => isset($i['sales_current'])?$i['sales_current']:'',
                            'group_status' => isset($i['group_status'])?$i['group_status']:'',
                            'group_sequence' => isset($i['group_sequence'])?$i['group_sequence']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'cp_child_guid' => isset($i['cp_child_guid'])?$i['cp_child_guid']:'',
                            'PurTolerance_Std_plus' => isset($i['PurTolerance_Std_plus'])?$i['PurTolerance_Std_plus']:'',
                            'PurTolerance_Std_Minus' => isset($i['PurTolerance_Std_Minus'])?$i['PurTolerance_Std_Minus']:'',
                            'WeightTraceQty' => isset($i['WeightTraceQty'])?$i['WeightTraceQty']:'',
                            'WeightTraceQtyUOM' => isset($i['WeightTraceQtyUOM'])?$i['WeightTraceQtyUOM']:'',
                            'WeightTraceQtyCount' => isset($i['WeightTraceQtyCount'])?$i['WeightTraceQtyCount']:'',
                            'pe_qty_rec' => isset($i['pe_qty_rec'])?$i['pe_qty_rec']:'',
                            'pe_qty_pos' => isset($i['pe_qty_pos'])?$i['pe_qty_pos']:'',
                            'pe_qty_si' => isset($i['pe_qty_si'])?$i['pe_qty_si']:'',
                            'pe_qty_dn' => isset($i['pe_qty_dn'])?$i['pe_qty_dn']:'',
                            'pe_qty_cn' => isset($i['pe_qty_cn'])?$i['pe_qty_cn']:'',
                            'pe_qty_adj' => isset($i['pe_qty_adj'])?$i['pe_qty_adj']:'',
                            'pe_qty_hamper' => isset($i['pe_qty_hamper'])?$i['pe_qty_hamper']:'',
                            'price_posnet' => isset($i['price_posnet'])?$i['price_posnet']:'',
                            'price_future' => isset($i['price_future'])?$i['price_future']:'',
                            'link_guid' => isset($i['link_guid'])?$i['link_guid']:'',
                            'stockday_min_qty' => isset($i['stockday_min_qty'])?$i['stockday_min_qty']:'',
                            'stockday_max_qty' => isset($i['stockday_max_qty'])?$i['stockday_max_qty']:'',
                            'stockday_first_grn_date' => isset($i['stockday_first_grn_date'])?$i['stockday_first_grn_date']:'',
                            'stockday_interval_days' => isset($i['stockday_interval_days'])?$i['stockday_interval_days']:'',
                            'stockday_pos_qty_sum' => isset($i['stockday_pos_qty_sum'])?$i['stockday_pos_qty_sum']:'',
                            'stockday_pos_qty_avg' => isset($i['stockday_pos_qty_avg'])?$i['stockday_pos_qty_avg']:'',
                            'stockday_OpeningQty' => isset($i['stockday_OpeningQty'])?$i['stockday_OpeningQty']:'',
                            'stockday_OnHandQty' => isset($i['stockday_OnHandQty'])?$i['stockday_OnHandQty']:'',
                            'stockday_PosSalesQty' => isset($i['stockday_PosSalesQty'])?$i['stockday_PosSalesQty']:'',
                            'stockday_InvSalesQty' => isset($i['stockday_InvSalesQty'])?$i['stockday_InvSalesQty']:'',
                            'stockday_AdjustQty' => isset($i['stockday_AdjustQty'])?$i['stockday_AdjustQty']:'',
                            'stockday_SOQty' => isset($i['stockday_SOQty'])?$i['stockday_SOQty']:'',
                            'stockday_POQty' => isset($i['stockday_POQty'])?$i['stockday_POQty']:'',
                            'stockday_RecQty' => isset($i['stockday_RecQty'])?$i['stockday_RecQty']:'',
                            'stockday_HamperQty' => isset($i['stockday_HamperQty'])?$i['stockday_HamperQty']:'',
                            'stockday_CreditQty' => isset($i['stockday_CreditQty'])?$i['stockday_CreditQty']:'',
                            'stockday_DebitQty' => isset($i['stockday_DebitQty'])?$i['stockday_DebitQty']:'',
                            'stockday_ExchangeQty' => isset($i['stockday_ExchangeQty'])?$i['stockday_ExchangeQty']:'',
                            'stockday_POAmount' => isset($i['stockday_POAmount'])?$i['stockday_POAmount']:'',
                            'hcost_po' => isset($i['hcost_po'])?$i['hcost_po']:'',
                            'hcost_po_unit' => isset($i['hcost_po_unit'])?$i['hcost_po_unit']:'',
                            'cost_manual' => isset($i['cost_manual'])?$i['cost_manual']:'',
                            'cost_manual_value' => isset($i['cost_manual_value'])?$i['cost_manual_value']:'',
                            'cat_max' => isset($i['cat_max'])?$i['cat_max']:'',
                            'cat_min' => isset($i['cat_min'])?$i['cat_min']:'',
                            'last_grndate' => isset($i['last_grndate'])?$i['last_grndate']:'',
                            'last_qty' => isset($i['last_qty'])?$i['last_qty']:'',
                            'last_supcode' => isset($i['last_supcode'])?$i['last_supcode']:'',
                            'pe_qty_firstdate' => isset($i['pe_qty_firstdate'])?$i['pe_qty_firstdate']:'',
                            'pe_qty_open' => isset($i['pe_qty_open'])?$i['pe_qty_open']:'',
                            'pgrqty' => isset($i['pgrqty'])?$i['pgrqty']:'',
                            'pother' => isset($i['pother'])?$i['pother']:'',
                            'psoldqty' => isset($i['psoldqty'])?$i['psoldqty']:'',
                            'rebate_value' => isset($i['rebate_value'])?$i['rebate_value']:'',
                            'postdatetime' => isset($i['postdatetime'])?$i['postdatetime']:'',
                            'gst_tax_type' => isset($i['gst_tax_type'])?$i['gst_tax_type']:'',
                            'gst_tax_code' => isset($i['gst_tax_code'])?$i['gst_tax_code']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'gst_tax_amount' => isset($i['gst_tax_amount'])?$i['gst_tax_amount']:'',
                            'price_include_tax' => $i['price_include_tax'],
                            'TotalPrice_include_tax' => $i['TotalPrice_include_tax'],
                            'SurchgValue' => isset($i['SurchgValue'])?$i['SurchgValue']:'',
                            'E_FOC' => isset($i['E_FOC'])?$i['E_FOC']:'',
                            'E_FOC_Line' => isset($i['E_FOC_Line'])?$i['E_FOC_Line']:'',
                            'E_Price' => isset($i['E_Price'])?$i['E_Price']:'',
                            'E_Discount_Rule' => isset($i['E_Discount_Rule'])?$i['E_Discount_Rule']:'',
                            'E_Discount_Value' => isset($i['E_Discount_Value'])?$i['E_Discount_Value']:'',
                            'E_Gross' => isset($i['E_Gross'])?$i['E_Gross']:'',
                            'E_Price_Net' => isset($i['E_Price_Net'])?$i['E_Price_Net']:'',
                            'E_Total_bf_Tax' => isset($i['E_Total_bf_Tax'])?$i['E_Total_bf_Tax']:'',
                            'E_TaxAmt' => isset($i['E_TaxAmt'])?$i['E_TaxAmt']:'',
                            'E_Total_af_Tax' => isset($i['E_Total_af_Tax'])?$i['E_Total_af_Tax']:'',
                            'TaxIntNo' => isset($i['TaxIntNo'])?$i['TaxIntNo']:'',
                            'TaxCodeMap' => isset($i['TaxCodeMap'])?$i['TaxCodeMap']:'',
                            'TaxValue' => isset($i['TaxValue'])?$i['TaxValue']:'',
                            'TaxAmount' => isset($i['TaxAmount'])?$i['TaxAmount']:'',
                            'TaxAmountVariance' => isset($i['TaxAmountVariance'])?$i['TaxAmountVariance']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['RefNo'].'-Line'.$i['Line'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'].'-Line'.$i['Line'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'pochild',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($pochild_data) > 0)
            {
                $pochild_data_chunk = array_chunk($pochild_data,1000);
                foreach($pochild_data_chunk AS $pochild_child)
                {
                    $this->db->insert_batch($hub_db.'.pochild',$pochild_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'PO child successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'PO child successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'PO child failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_acc_trans_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_acc_trans'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $acc_trans_data = array();
            $log_child = array();

            // foreach($data as $i)
            // {
            //     $trans_guid = $i['trans_guid'];
            //     $operation = $i['operation'];
            //     // $this->db->delete($hub_db.'.acc_trans', array('trans_guid' => $trans_guid)); 
            //     $this->db->where('trans_guid',$trans_guid);
            //     $this->db->update($hub_db.'.acc_trans', array('operation' => $operation));
            // }

            foreach($data as $i)
            {
                $refno = $i['refno'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('refno',$refno);
                    $this->db->update($hub_db.'.acc_trans', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'];
                    } else{
                        $failed_refno[] =  $i['refno'];
                    }

                } else{

                    $this->db->delete($hub_db.'.acc_trans', array('refno' => $refno)); 
                }
                
            }

            // echo $data->result();

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['trans_guid']) || $i['trans_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Trans Guid doesn\'t exist.';
                    }
                    elseif(!isset($i['trans_type']) || $i['trans_type'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Trans Type doesn\'t exist';
                    }
                    elseif(!isset($i['refno']) || $i['refno'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Reference Number doesn\'t exist';
                    }
                    elseif(!isset($i['date_trans']) || $i['date_trans'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Transaction Date doesn\'t exist';
                    }
                    // elseif(!isset($i['supcus_code']) || $i['supcus_code'] == '')
                    // {
                    //     $status = 'Failed';
                    //     $message = 'Supplier Code doesn\'t exist';
                    // }
                    elseif(!isset($i['amount']) || $i['amount'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Amount doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['refno']) || $i['refno'] == ''?$i['refno']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $acc_trans_data[] = array(
                            'trans_guid' => $i['trans_guid'],
                            'trans_type' => $i['trans_type'],
                            'refno' => $i['refno'],
                            'date_trans' => $i['date_trans'],
                            'date_due' => isset($i['date_due'])?$i['date_due']:'',
                            'supcus_type' => isset($i['supcus_type'])?$i['supcus_type']:'',
                            'supcus_code' => $i['supcus_code'],
                            'supcus_name' => isset($i['supcus_name'])?$i['supcus_name']:'',
                            'amount' => $i['amount'],
                            'created_at' => isset($i['created_at'])?$i['created_at']:'',
                            'created_by' => isset($i['created_by'])?$i['created_by']:'',
                            'updated_at' => isset($i['updated_at'])?$i['updated_at']:'',
                            'updated_by' => isset($i['updated_by'])?$i['updated_by']:'',
                            'remarks' => isset($i['remarks'])?$i['remarks']:'',
                            'posted_at' => isset($i['posted_at'])?$i['posted_at']:'',
                            'posted_by' => isset($i['posted_by'])?$i['posted_by']:'',
                            'posted' => isset($i['posted'])?$i['posted']:'',
                            'refno_cross' => isset($i['refno_cross'])?$i['refno_cross']:'',
                            'EXPORT_ACCOUNT' => isset($i['EXPORT_ACCOUNT'])?$i['EXPORT_ACCOUNT']:'',
                            'EXPORT_AT' => isset($i['EXPORT_AT'])?$i['EXPORT_AT']:'',
                            'EXPORT_BY' => isset($i['EXPORT_BY'])?$i['EXPORT_BY']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'locgroup' => isset($i['locgroup'])?$i['locgroup']:'',
                            'subdept' => isset($i['subdept'])?$i['subdept']:'',
                            'gst_tax_sum' => isset($i['gst_tax_sum'])?$i['gst_tax_sum']:'',
                            'ibt' => isset($i['ibt'])?$i['ibt']:'',
                            'date_from' => isset($i['date_from'])?$i['date_from']:'',
                            'date_to' => isset($i['date_to'])?$i['date_to']:'',
                            'sup_doc_no' => isset($i['sup_doc_no'])?$i['sup_doc_no']:'',
                            'sup_doc_date' => isset($i['sup_doc_date'])?$i['sup_doc_date']:'',
                            'acc_post_date' => isset($i['acc_post_date'])?$i['acc_post_date']:'',
                            'total_inc_tax' => isset($i['total_inc_tax'])?$i['total_inc_tax']:'',
                            'rounding_adj' => isset($i['rounding_adj'])?$i['rounding_adj']:'',
                            'b2b_status' => isset($i['b2b_status'])?$i['b2b_status']:'',
                            'approval' => isset($i['approval'])?$i['approval']:'',
                            'approved_by' => isset($i['approved_by'])?$i['approved_by']:'',
                            'approved_at' => isset($i['approved_at'])?$i['approved_at']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'status' => isset($i['status'])?$i['status']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['refno'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['refno'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'acc_trans',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($acc_trans_data) > 0)
            {
                $acc_trans_data_chunk = array_chunk($acc_trans_data,1000);
                foreach($acc_trans_data_chunk AS $acc_trans_child)
                {
                    $this->db->insert_batch($hub_db.'.acc_trans',$acc_trans_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'Acc Trans successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'Acc Trans successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'Acc Trans failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_acc_trans_c2_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');

        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_acc_trans_c2'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $acc_trans_c2_data = array();
            $log_child = array();

            // $delete_data = array();
            // foreach ($data as $key => $delete) {
            //     $delete_data[$delete['trans_guid']][$key] = $delete;
            // }

            foreach($data as $i)
            {
                $parameter = array(
                    'refno' => $i['refno'],
                    'line' => $i['line'],
                );

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $update_parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
 
                    $this->db->where($parameter);
                    $this->db->update($hub_db.'.acc_trans_c2', $update_parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'].'-Line'.$i['line'];
                    } else{
                        $failed_refno[] =  $i['refno'].'-Line'.$i['line'];
                    }

                } else{

                    $this->db->delete($hub_db.'.acc_trans_c2', $parameter);
                }
                
            }

            // foreach($data as $i)
            // {
            //     $parameter = array(
            //         'trans_guid' => $i['trans_guid'],
            //         'line' => $i['line'],
            //     );
            //     $operation = $i['operation'];

            //     $this->db->where($parameter);
            //     $this->db->update($hub_db.'.acc_trans_c2', array('operation' => $operation));

            //     // $this->db->delete($hub_db.'.acc_trans_c2', $parameter); 
            // }

            // for($count = 0 ; $count < count($data)  ; $count++ )
            // {
            //     $tmpArr[$data[$count]['trans_guid']] = $data[$count]['trans_guid'];
            // }
            // $vmpArr = array_keys($tmpArr);

            // foreach($tmpArr as $d)
            // {
            //     // print_r($d); echo 'abcdefghijkl';
            //     // $trans_guid = $d[$delete_count];
            //     // echo 'qweqweqweqweqqew'; echo ($trans_guid);
            //     $this->db->delete($hub_db.'.acc_trans_c2', array('trans_guid' => $d));
            // }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['trans_guid']) || $i['trans_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Trans Guid doesn\'t exist.';
                    }
                    elseif(!isset($i['trans_type']) || $i['trans_type'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Trans Type doesn\'t exist';
                    }
                    elseif(!isset($i['refno']) || $i['refno'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Reference Number doesn\'t exist';
                    }
                    elseif(!isset($i['line']) || $i['line'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Line Number doesn\'t exist';
                    }
                    elseif(!isset($i['bizdate']) || $i['bizdate'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Business Dates doesn\'t exist';
                    }
                    elseif(!isset($i['outlet']) || $i['outlet'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Outlet Code doesn\'t exist';
                    }
                    elseif(!isset($i['description']) || $i['description'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Description doesn\'t exist';
                    }
                    elseif(!isset($i['amount']) || $i['amount'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Amount doesn\'t exist';
                    }
                    elseif(!isset($i['taxableamt']) || $i['taxableamt'] == '')
                    {
                        $status = 'Failed';
                        $message = 'taxablamt doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }

                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => (isset($i['refno']) && isset($i['line'])) || $i['refno'] != ''?$i['refno'].'-Line'.$i['line']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $acc_trans_c2_data[] = array(
                            'trans_guid' => $i['trans_guid'],
                            'trans_type' => $i['trans_type'],
                            'refno' => $i['refno'],
                            'line' => $i['line'],
                            'bizdate' => $i['bizdate'],
                            'outlet' => $i['outlet'],
                            'division' => $i['division'],
                            'description' => $i['description'],
                            'amount' => $i['amount'],
                            'taxableamt' => $i['taxableamt'],
                            'amt_dr' => isset($i['amt_dr'])?$i['amt_dr']:'',
                            'amt_cr' => isset($i['amt_cr'])?$i['amt_cr']:'',
                            'taxable_dr' => isset($i['taxable_dr'])?$i['taxable_dr']:'',
                            'taxable_cr' => isset($i['taxable_cr'])?$i['taxable_cr']:'',
                            'tax_code' => isset($i['tax_code'])?$i['tax_code']:'',
                            'gst_amt' => isset($i['gst_amt'])?$i['gst_amt']:'',
                            'tax_rate' => isset($i['tax_rate'])?$i['tax_rate']:'',
                            'gl_type' => isset($i['gl_type'])?$i['gl_type']:'',
                            'gl_code' => isset($i['gl_code'])?$i['gl_code']:'',
                            'gst_adj' => isset($i['gst_adj'])?$i['gst_adj']:'',
                            'created_at' => isset($i['created_at'])?$i['created_at']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['refno'].'-Line'.$i['line'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['refno'].'-Line'.$i['line'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'acc_trans_c2',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($acc_trans_c2_data) > 0)
            {
                $acc_trans_c2_data_chunk = array_chunk($acc_trans_c2_data,1000);
                foreach($acc_trans_c2_data_chunk AS $acc_trans_c2_child)
                {
                    $this->db->insert_batch($hub_db.'.acc_trans_c2',$acc_trans_c2_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'Acc Trans C2 successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'Acc Trans C2 successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'Acc Trans C2 failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_grmain_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_grmain'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $grmain_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $refno = $i['refno'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('RefNo',$refno);
                    $this->db->update($hub_db.'.grmain', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'];
                    } else{
                        $failed_refno[] =  $i['refno'];
                    }

                } else{

                    $this->db->delete($hub_db.'.grmain', array('RefNo' => $refno)); 
                }

                // $refno = $i['RefNo'];
                // $operation = $i['operation'];
                // // $this->db->delete($hub_db.'.acc_trans', array('trans_guid' => $trans_guid)); 
                // $this->db->where('RefNo',$refno);
                // $this->db->update($hub_db.'.grmain', array('operation' => $operation));
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Reference No doesn\'t exist.';
                    }
                    elseif(!isset($i['Location']) || $i['Location'] == '')
                    {   
                        $status = 'Failed';
                        $message = 'Location doesn\'t exist';
                    }
                    elseif(!isset($i['DONo']) || $i['DONo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'DONo doesn\'t exist';
                    }
                    elseif(!isset($i['InvNo']) || $i['InvNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'InvNo doesn\'t exist';
                    }
                    elseif(!isset($i['Code']) || $i['Code'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Code doesn\'t exist';
                    }
                    elseif(!isset($i['Total']) || $i['Total'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Total doesn\'t exist';
                    }
                    elseif(!isset($i['Subtotal1']) || $i['Subtotal1'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Sub total 1 doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else{
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['RefNo']) || $i['RefNo'] == ''?$i['RefNo']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $grmain_data[] = array(
                            'RefNo' => $i['RefNo'],
                            'Location' => $i['Location'],
                            'DONo' => $i['DONo'],
                            'InvNo' => $i['InvNo'],
                            'DocDate' => isset($i['DocDate'])?$i['DocDate']:'',
                            'GRDate' => isset($i['GRDate'])?$i['GRDate']:'',
                            'IssueStamp' => isset($i['IssueStamp'])?$i['IssueStamp']:'',
                            'LastStamp' => isset($i['LastStamp'])?$i['LastStamp']:'',
                            'Code' => $i['Code'],
                            'Name' => isset($i['Name'])?$i['Name']:'',
                            'Term' => isset($i['Term'])?$i['Term']:'',
                            'Receivedby' => isset($i['Receivedby'])?$i['Receivedby']:'',
                            'Remark' => isset($i['Remark'])?$i['Remark']:'',
                            'BillStatus' => isset($i['BillStatus'])?$i['BillStatus']:'',
                            'AccStatus' => isset($i['AccStatus'])?$i['AccStatus']:'',
                            'DueDate' => isset($i['DueDate'])?$i['DueDate']:'',
                            'Total' => $i['Total'],
                            'Closed' => isset($i['Closed'])?$i['Closed']:'',
                            'Subtotal1' => $i['Subtotal1'],
                            'Discount1' => isset($i['Discount1'])?$i['Discount1']:'',
                            'Discount1Type' => isset($i['Discount1Type'])?$i['Discount1Type']:'',
                            'Subtotal2' => isset($i['Subtotal2'])?$i['Subtotal2']:'',
                            'Discount2' => isset($i['Discount2'])?$i['Discount2']:'',
                            'Discount2Type' => isset($i['Discount2Type'])?$i['Discount2Type']:'',
                            'Disc1Percent' => isset($i['Disc1Percent'])?$i['Disc1Percent']:'',
                            'Disc2Percent' => isset($i['Disc2Percent'])?$i['Disc2Percent']:'',
                            'Cancelled' => isset($i['Cancelled'])?$i['Cancelled']:'',
                            'DOState' => isset($i['DOState'])?$i['DOState']:'',
                            'InvState' => isset($i['InvState'])?$i['InvState']:'',
                            'InvRefno' => isset($i['InvRefno'])?$i['InvRefno']:'',
                            'subdept' => isset($i['subdept'])?$i['subdept']:'',
                            'CalcCost' => isset($i['CalcCost'])?$i['CalcCost']:'',
                            'SubDeptCode' => isset($i['SubDeptCode'])?$i['SubDeptCode']:'',
                            'consign' => isset($i['consign'])?$i['consign']:'',
                            'postby' => isset($i['postby'])?$i['postby']:'',
                            'postdatetime' => isset($i['postdatetime'])?$i['postdatetime']:'',
                            'unpostby' => isset($i['unpostby'])?$i['unpostby']:'',
                            'unpostdatetime' => isset($i['unpostdatetime'])?$i['unpostdatetime']:'',
                            'CalDueDateby' => isset($i['CalDueDateby'])?$i['CalDueDateby']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'EXPORT_ACCOUNT' => isset($i['EXPORT_ACCOUNT'])?$i['EXPORT_ACCOUNT']:'',
                            'EXPORT_AT' => isset($i['EXPORT_AT'])?$i['EXPORT_AT']:'',
                            'EXPORT_BY' => isset($i['EXPORT_BY'])?$i['EXPORT_BY']:'',
                            'InvAmount_Vendor' => isset($i['InvAmount_Vendor'])?$i['InvAmount_Vendor']:'',
                            'InvSurchargeDisc_Vendor' => isset($i['InvSurchargeDisc_Vendor'])?$i['InvSurchargeDisc_Vendor']:'',
                            'InvNetAmt_Vendor' => isset($i['InvNetAmt_Vendor'])?$i['InvNetAmt_Vendor']:'',
                            'loc_group' => isset($i['loc_group'])?$i['loc_group']:'',
                            'pay_by_invoice' => isset($i['pay_by_invoice'])?$i['pay_by_invoice']:'',
                            'rebate_amt' => isset($i['rebate_amt'])?$i['rebate_amt']:'',
                            'ibt' => isset($i['ibt'])?$i['ibt']:'',
                            'dn_amt' => isset($i['dn_amt'])?$i['dn_amt']:'',
                            'm_trans_type' => isset($i['m_trans_type'])?$i['m_trans_type']:'',
                            'in_kind' => isset($i['in_kind'])?$i['in_kind']:'',
                            'rebate' => isset($i['rebate'])?$i['rebate']:'',
                            'gst_tax_sum' => isset($i['gst_tax_sum'])?$i['gst_tax_sum']:'',
                            'tax_code_purchase' => isset($i['tax_code_purchase'])?$i['tax_code_purchase']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'gst_tax_sum_inv' => isset($i['gst_tax_sum_inv'])?$i['gst_tax_sum_inv']:'',
                            'InvSurcharge' => isset($i['InvSurcharge'])?$i['InvSurcharge']:'',
                            'price_include_tax' => isset($i['price_include_tax'])?$i['price_include_tax']:'',
                            'surchg_tax_sum' => isset($i['surchg_tax_sum'])?$i['surchg_tax_sum']:'',
                            'surchg_tax_sum_inv' => isset($i['surchg_tax_sum_inv'])?$i['surchg_tax_sum_inv']:'',
                            'total_include_tax' => isset($i['total_include_tax'])?$i['total_include_tax']:'',
                            'doc_name_reg' => isset($i['doc_name_reg'])?$i['doc_name_reg']:'',
                            'multi_tax_code' => isset($i['multi_tax_code'])?$i['multi_tax_code']:'',
                            'refno2' => isset($i['refno2'])?$i['refno2']:'',
                            'gst_adj' => isset($i['gst_adj'])?$i['gst_adj']:'',
                            'rounding_adj' => isset($i['rounding_adj'])?$i['rounding_adj']:'',
                            'discount_as_inv' => isset($i['discount_as_inv'])?$i['discount_as_inv']:'',
                            'rebate_as_inv' => isset($i['rebate_as_inv'])?$i['rebate_as_inv']:'',
                            'ibt_gst' => isset($i['ibt_gst'])?$i['ibt_gst']:'',
                            'acc_post_date' => isset($i['acc_post_date'])?$i['acc_post_date']:'',
                            'uploaded' => isset($i['uploaded'])?$i['uploaded']:'',
                            'uploaded_at' => isset($i['uploaded_at'])?$i['uploaded_at']:'',
                            'input_amt_exc_tax' => isset($i['input_amt_exc_tax'])?$i['input_amt_exc_tax']:'',
                            'input_gst' => isset($i['input_gst'])?$i['input_gst']:'',
                            'input_amt_inc_tax' => isset($i['input_amt_inc_tax'])?$i['input_amt_inc_tax']:'',
                            'amt_matched' => isset($i['amt_matched'])?$i['amt_matched']:'',
                            'ibt_qty_actual' => isset($i['ibt_qty_actual'])?$i['ibt_qty_actual']:'',
                            'ibt_qty_grda' => isset($i['ibt_qty_grda'])?$i['ibt_qty_grda']:'',
                            'send_for_app' => isset($i['send_for_app'])?$i['send_for_app']:'',
                            'send_at' => isset($i['send_at'])?$i['send_at']:'',
                            'send_by' => isset($i['send_by'])?$i['send_by']:'',
                            'cross_ref' => isset($i['cross_ref'])?$i['cross_ref']:'',
                            'cross_ref_module' => isset($i['cross_ref_module'])?$i['cross_ref_module']:'',
                            'status' => isset($i['status'])?$i['status']:'',
                            'landed_cost_total' => isset($i['landed_cost_total'])?$i['landed_cost_total']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['RefNo'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'grmain',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($grmain_data) > 0)
            {
                $grmain_data_chunk = array_chunk($grmain_data,1000);
                foreach($grmain_data_chunk AS $grmain_child)
                {
                    $this->db->insert_batch($hub_db.'.grmain',$grmain_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'grmain successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'grmain successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'grmain failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }

            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
        
    }

    public function b2b_grchild_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
    
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_grchild'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $grchild_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $parameter = array(
                    'RefNo' => $i['refno'],
                    'Line' => $i['line'],
                );

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $update_parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
 
                    $this->db->where($parameter);
                    $this->db->update($hub_db.'.grchild', $update_parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'].'-Line'.$i['line'];
                    } else{
                        $failed_refno[] =  $i['refno'].'-Line'.$i['line'];
                    }

                } else{

                    $this->db->delete($hub_db.'.grchild', $parameter);
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Reference No doesn\'t exist.';
                    }
                    elseif(!isset($i['Line']) || $i['Line'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Line doesn\'t exist';
                    }
                    elseif(!isset($i['Itemcode']) || $i['Itemcode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Item code doesn\'t exist';
                    }
                    elseif(!isset($i['TotalPrice']) || $i['TotalPrice'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Total price doesn\'t exist';
                    }
                    elseif(!isset($i['PORefNo']) || $i['PORefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'PO Reference no doesn\'t exist';
                    }
                    elseif(!isset($i['ItemLink']) || $i['ItemLink'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Item link doesn\'t exist';
                    }
                    elseif(!isset($i['barcode']) || $i['barcode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Barcode doesn\'t exist';
                    }
                    elseif(!isset($i['Inv_NetUnitPrice']) || $i['Inv_NetUnitPrice'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Inv NetUnitPrice doesn\'t exist';
                    }
                    elseif(!isset($i['Inv_TotalPrice']) || $i['Inv_TotalPrice'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Inv Total Price doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => (isset($i['RefNo']) && isset($i['Line'])) || $i['RefNo'] == ''?$i['RefNo'].'-Line'.$i['Line']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $grchild_data[] = array(
                            'RefNo' => $i['RefNo'],
                            'Line' => $i['Line'],
                            'EntryType' => isset($i['EntryType'])?$i['EntryType']:'',
                            'PriceType' => isset($i['PriceType'])?$i['PriceType']:'',
                            'Itemcode' => $i['Itemcode'],
                            'Description' => isset($i['Description'])?$i['Description']:'',
                            'Qty' => isset($i['Qty'])?$i['Qty']:'',
                            'UnitPrice' => isset($i['UnitPrice'])?$i['UnitPrice']:'',
                            'Disc1Type' => isset($i['Disc1Type'])?$i['Disc1Type']:'',
                            'Disc1Value' => isset($i['Disc1Value'])?$i['Disc1Value']:'',
                            'Disc2Type' => isset($i['Disc2Type'])?$i['Disc2Type']:'',
                            'Disc2Value' => isset($i['Disc2Value'])?$i['Disc2Value']:'',
                            'NetUnitPrice' => isset($i['NetUnitPrice'])?$i['NetUnitPrice']:'',
                            'DiscAmt' => isset($i['DiscAmt'])?$i['DiscAmt']:'',
                            'TotalPrice' => $i['TotalPrice'],
                            'Colour' => isset($i['Colour'])?$i['Colour']:'',
                            'Size' => isset($i['Size'])?$i['Size']:'',
                            'ArticleNo' => isset($i['ArticleNo'])?$i['ArticleNo']:'',
                            'POLine' => isset($i['POLine'])?$i['POLine']:'',
                            'PORefNo' => $i['PORefNo'],
                            'UM' => isset($i['UM'])?$i['UM']:'',
                            'PackSize' => isset($i['PackSize'])?$i['PackSize']:'',
                            'Brand' => isset($i['Brand'])?$i['Brand']:'',
                            'DiscValue' => isset($i['DiscValue'])?$i['DiscValue']:'',
                            'CostAftDisc' => isset($i['CostAftDisc'])?$i['CostAftDisc']:'',
                            'CostFactor' => isset($i['CostFactor'])?$i['CostFactor']:'',
                            'InvActCost' => isset($i['InvActCost'])?$i['InvActCost']:'',
                            'InvActTotCost' => isset($i['InvActTotCost'])?$i['InvActTotCost']:'',
                            'SysQOH' => isset($i['SysQOH'])?$i['SysQOH']:'',
                            'SysAvgCost' => isset($i['SysAvgCost'])?$i['SysAvgCost']:'',
                            'WeightAvgCost' => isset($i['WeightAvgCost'])?$i['WeightAvgCost']:'',
                            'GroupNo' => isset($i['GroupNo'])?$i['GroupNo']:'',
                            'ItemLink' => $i['ItemLink'],
                            'GroupNo2' => isset($i['GroupNo2'])?$i['GroupNo2']:'',
                            'BasketQty' => isset($i['BasketQty'])?$i['BasketQty']:'',
                            'TotalQty' => isset($i['TotalQty'])?$i['TotalQty']:'',
                            'POPacksize' => isset($i['POPacksize'])?$i['POPacksize']:'',
                            'POQty' => isset($i['POQty'])?$i['POQty']:'',
                            'POUnitPrice' => isset($i['POUnitPrice'])?$i['POUnitPrice']:'',
                            'PerfectLineRecv' => isset($i['PerfectLineRecv'])?$i['PerfectLineRecv']:'',
                            'InvRefno' => isset($i['InvRefno'])?$i['InvRefno']:'',
                            'barcode' => $i['barcode'],
                            'BulkQty' => isset($i['BulkQty'])?$i['BulkQty']:'',
                            'UMBulk' => isset($i['UMBulk'])?$i['UMBulk']:'',
                            'BQty' => isset($i['BQty'])?$i['BQty']:'',
                            'PQty' => isset($i['PQty'])?$i['PQty']:'',
                            'ItemRemark' => isset($i['ItemRemark'])?$i['ItemRemark']:'',
                            'Dept' => isset($i['Dept'])?$i['Dept']:'',
                            'SubDept' => isset($i['SubDept'])?$i['SubDept']:'',
                            'Category' => isset($i['Category'])?$i['Category']:'',
                            'GroupCost' => isset($i['GroupCost'])?$i['GroupCost']:'',
                            'BillItemAvgCost' => isset($i['BillItemAvgCost'])?$i['BillItemAvgCost']:'',
                            'Posted' => isset($i['Posted'])?$i['Posted']:'',
                            'group_status' => isset($i['group_status'])?$i['group_status']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'PurTolerance_Std_plus' => isset($i['PurTolerance_Std_plus'])?$i['PurTolerance_Std_plus']:'',
                            'PurTolerance_Std_Minus' => isset($i['PurTolerance_Std_Minus'])?$i['PurTolerance_Std_Minus']:'',
                            'WeightTraceQty' => isset($i['WeightTraceQty'])?$i['WeightTraceQty']:'',
                            'WeightTraceQtyUOM' => isset($i['WeightTraceQtyUOM'])?$i['WeightTraceQtyUOM']:'',
                            'WeightTraceQtyCount' => isset($i['WeightTraceQtyCount'])?$i['WeightTraceQtyCount']:'',
                            'Inv_Qty' => isset($i['Inv_Qty'])?$i['Inv_Qty']:'',
                            'Inv_UnitPrice' => isset($i['Inv_UnitPrice'])?$i['Inv_UnitPrice']:'',
                            'Inv_Disc1Type' => isset($i['Inv_Disc1Type'])?$i['Inv_Disc1Type']:'',
                            'Inv_Disc1Value' => $i['Inv_Disc1Value'],
                            'Inv_Disc2Type' => isset($i['Inv_Disc2Type'])?$i['Inv_Disc2Type']:'',
                            'Inv_Disc2Value' => isset($i['Inv_Disc2Value'])?$i['Inv_Disc2Value']:'',
                            'Inv_NetUnitPrice' => $i['Inv_NetUnitPrice'],
                            'Inv_TotalPrice' => $i['Inv_TotalPrice'],
                            'Inv_Variance' => isset($i['Inv_Variance'])?$i['Inv_Variance']:'',
                            'SellingPrice' => isset($i['SellingPrice'])?$i['SellingPrice']:'',
                            'price_posnet' => isset($i['price_posnet'])?$i['price_posnet']:'',
                            'price_future' => isset($i['price_future'])?$i['price_future']:'',
                            'InvActCostVendor' => isset($i['InvActCostVendor'])?$i['InvActCostVendor']:'',
                            'InvActTotCostVendor' => isset($i['InvActTotCostVendor'])?$i['InvActTotCostVendor']:'',
                            'CostFactor_Vendor' => isset($i['CostFactor_Vendor'])?$i['CostFactor_Vendor']:'',
                            'poActCost' => isset($i['poActCost'])?$i['poActCost']:'',
                            'AutoClosePO' => isset($i['AutoClosePO'])?$i['AutoClosePO']:'',
                            'POTotalPrice' => isset($i['POTotalPrice'])?$i['POTotalPrice']:'',
                            'POCostFactor' => isset($i['POCostFactor'])?$i['POCostFactor']:'',
                            'POQty_Expected' => isset($i['POQty_Expected'])?$i['POQty_Expected']:'',
                            'POActTotCost' => isset($i['POActTotCost'])?$i['POActTotCost']:'',
                            'variance_qty' => isset($i['variance_qty'])?$i['variance_qty']:'',
                            'variance_cost' => isset($i['variance_cost'])?$i['variance_cost']:'',
                            'reason' => isset($i['reason'])?$i['reason']:'',
                            'hcost_gr' => isset($i['hcost_gr'])?$i['hcost_gr']:'',
                            'hcost_gr_unit' => isset($i['hcost_gr_unit'])?$i['hcost_gr_unit']:'',
                            'hcost_iv' => isset($i['hcost_iv'])?$i['hcost_iv']:'',
                            'hcost_iv_unit' => isset($i['hcost_iv_unit'])?$i['hcost_iv_unit']:'',
                            'cost_manual' => isset($i['cost_manual'])?$i['cost_manual']:'',
                            'cost_manual_value' => isset($i['cost_manual_value'])?$i['cost_manual_value']:'',
                            'rebate_value' => isset($i['rebate_value'])?$i['rebate_value']:'',
                            'cat_max' => isset($i['cat_max'])?$i['cat_max']:'',
                            'cat_min' => isset($i['cat_min'])?$i['cat_min']:'',
                            'postdatetime' => isset($i['postdatetime'])?$i['postdatetime']:'',
                            'gst_tax_type' => isset($i['gst_tax_type'])?$i['gst_tax_type']:'',
                            'gst_tax_code' => isset($i['gst_tax_code'])?$i['gst_tax_code']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'gst_tax_amount' => isset($i['gst_tax_amount'])?$i['gst_tax_amount']:'',
                            'gst_rebate_amt' => isset($i['gst_rebate_amt'])?$i['gst_rebate_amt']:'',
                            'gst_tax_amt_inv' => isset($i['gst_tax_amt_inv'])?$i['gst_tax_amt_inv']:'',
                            'gst_var_cost' => isset($i['gst_var_cost'])?$i['gst_var_cost']:'',
                            'gst_var_qty' => isset($i['gst_var_qty'])?$i['gst_var_qty']:'',
                            'Inv_SurchgValue' => isset($i['Inv_SurchgValue'])?$i['Inv_SurchgValue']:'',
                            'SurchgValue' => isset($i['SurchgValue'])?$i['SurchgValue']:'',
                            'gst_manual' => isset($i['gst_manual'])?$i['gst_manual']:'',
                            'expiry_date' => isset($i['expiry_date'])?$i['expiry_date']:'',
                            'ibt_qty_actual' => isset($i['ibt_qty_actual'])?$i['ibt_qty_actual']:'',
                            'ibt_qty_grda' => isset($i['ibt_qty_grda'])?$i['ibt_qty_grda']:'',
                            'TaxIntNo' => isset($i['TaxIntNo'])?$i['TaxIntNo']:'',
                            'TaxCodeMap' => isset($i['TaxCodeMap'])?$i['TaxCodeMap']:'',
                            'TaxValue' => isset($i['TaxValue'])?$i['TaxValue']:'',
                            'TaxAmount' => isset($i['TaxAmount'])?$i['TaxAmount']:'',
                            'TaxAmountVariance' => isset($i['TaxAmountVariance'])?$i['TaxAmountVariance']:'',
                            'unit_volume' => isset($i['unit_volume'])?$i['unit_volume']:'',
                            'unit_volume_total' => isset($i['unit_volume_total'])?$i['unit_volume_total']:'',
                            'unit_landed_cost' => isset($i['unit_landed_cost'])?$i['unit_landed_cost']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['RefNo'].'-Line'.$i['Line'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'].'-Line'.$i['Line'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'grchild',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($grchild_data) > 0)
            {
                $grchild_data_chunk = array_chunk($grchild_data,1000);
                foreach($grchild_data_chunk AS $grchild_child)
                {
                    $this->db->insert_batch($hub_db.'.grchild',$grchild_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'grchild successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'grchild successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'grchild failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
        
    }

    public function b2b_supcus_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');

        // var_dump($data);die;

        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_supcus'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $supcus_data = array();

            foreach($data as $i)
            {
                $parameter = array(
                    'Type' => $i['Type'],
                    'Code' => $i['Code'],
                );

                $this->db->delete($hub_db.'.supcus', $parameter); 
            }


            foreach($data as $i)
            {
                //checking
                if(!isset($i['Type']) || $i['Type'] == '')
                {
                    $status = 'Failed';
                    $message = 'Type doesn\'t exist.';
                }
                elseif(!isset($i['Code']) || $i['Code'] == '')
                {
                    $status = 'Failed';
                    $message = 'Code doesn\'t exist';
                }
                elseif(!isset($i['Term']) || $i['Term'] == '')
                {
                    $status = 'Failed';
                    $message = 'Term doesn\'t exist';
                }
                elseif(!isset($i['AccountCode']) || $i['AccountCode'] == '')
                {
                    $status = 'Failed';
                    $message = 'Account code doesn\'t exist';
                }
                elseif(!isset($i['supcus_guid']) || $i['supcus_guid'] == '')
                {
                    $status = 'Failed';
                    $message = 'Supcus guid doesn\'t exist';
                }
                elseif(!isset($i['price_include_tax']) || $i['price_include_tax'] == '')
                {
                    $status = 'Failed';
                    $message = 'Price include tax doesn\'t exist';
                }
                else
                {
                    $status = 'Success';
                    $message = 'Success';
                }

                $log_child[] = array(
                    'line_guid' => $this->guid(),
                    'guid' => $guid,
                    'panda_refno' => '',
                    'refno' => $log_refno,
                    'line' => $line,
                    'itemcode' => (isset($i['Type']) && isset($i['Code'])) || $i['Type'] == ''?$i['Type'].'-Code'.$i['Code']:'',
                    'status' => $status,
                    'message' => $message,
                    'session' => $session_guid
                );

                if($status == 'Success')
                {
                    $supcus_data[] = array(
                        'Type' => $i['Type'],
                        'Code' => $i['Code'],
                        'Name' => isset($i['Name'])?$i['Name']:'',
                        'Add1' => isset($i['Add1'])?$i['Add1']:'',
                        'Add2' => isset($i['Add2'])?$i['Add2']:'',
                        'Add3' => isset($i['Add3'])?$i['Add3']:'',
                        'City' => isset($i['City'])?$i['City']:'',
                        'State' => isset($i['State'])?$i['State']:'',
                        'Country' => isset($i['Country'])?$i['Country']:'',
                        'Postcode' => isset($i['Postcode'])?$i['Postcode']:'',
                        'Tel' => isset($i['Tel'])?$i['Tel']:'',
                        'Fax' => isset($i['Fax'])?$i['Fax']:'',
                        'Contact' => isset($i['Contact'])?$i['Contact']:'',
                        'Mobile' => isset($i['Mobile'])?$i['Mobile']:'',
                        'Term' => $i['Term'],
                        'PaymentDay' => isset($i['PaymentDay'])?$i['PaymentDay']:'',
                        'BankAcc' => isset($i['BankAcc'])?$i['BankAcc']:'',
                        'CreditLimit' => isset($i['CreditLimit'])?$i['CreditLimit']:'',
                        'MonitorCredit' => isset($i['MonitorCredit'])?$i['MonitorCredit']:'',
                        'Remark' => isset($i['Remark'])?$i['Remark']:'',
                        'PointBF' => isset($i['PointBF'])?$i['PointBF']:'',
                        'PointCumm' => isset($i['PointCumm'])?$i['PointCumm']:'',
                        'PointSum' => isset($i['PointSum'])?$i['PointSum']:'',
                        'Member' => isset($i['Member'])?$i['Member']:'',
                        'memberno' => isset($i['memberno'])?$i['memberno']:'',
                        'ExpiryDate' => isset($i['ExpiryDate'])?$i['ExpiryDate']:'',
                        'CycleVisit' => isset($i['CycleVisit'])?$i['CycleVisit']:'',
                        'DeliveryTerm' => isset($i['DeliveryTerm'])?$i['DeliveryTerm']:'',
                        'IssuedStamp' => isset($i['IssuedStamp'])?$i['IssuedStamp']:'',
                        'LastStamp' => isset($i['LastStamp'])?$i['LastStamp']:'',
                        'dadd1' => isset($i['dadd1'])?$i['dadd1']:'',
                        'dadd2' => isset($i['dadd2'])?$i['dadd2']:'',
                        'dadd3' => isset($i['dadd3'])?$i['dadd3']:'',
                        'dattn' => isset($i['dattn'])?$i['dattn']:'',
                        'dtel' => isset($i['dtel'])?$i['dtel']:'',
                        'dfax' => isset($i['dfax'])?$i['dfax']:'',
                        'email' => isset($i['email'])?$i['email']:'',
                        'AccountCode' => $i['AccountCode'],
                        'AccPDebit' => isset($i['AccPDebit'])?$i['AccPDebit']:'',
                        'AccPCredit' => isset($i['AccPCredit'])?$i['AccPCredit']:'',
                        'CalDueDateby' => isset($i['CalDueDateby'])?$i['CalDueDateby']:'',
                        'supcusGroup' => isset($i['supcusGroup'])?$i['supcusGroup']:'',
                        'region' => isset($i['region'])?$i['region']:'',
                        'pcode' => isset($i['pcode'])?$i['pcode']:'',
                        'Add4' => isset($i['Add4'])?$i['Add4']:'',
                        'Contact2' => isset($i['Contact2'])?$i['Contact2']:'',
                        'DAdd4' => isset($i['DAdd4'])?$i['DAdd4']:'',
                        'poprice_method' => isset($i['poprice_method'])?$i['poprice_method']:'',
                        'stockday_min' => isset($i['stockday_min'])?$i['stockday_min']:'',
                        'stockday_max' => isset($i['stockday_max'])?$i['stockday_max']:'',
                        'stock_returnable' => isset($i['stock_returnable'])?$i['stock_returnable']:'',
                        'stock_return_cost_type' => isset($i['stock_return_cost_type'])?$i['stock_return_cost_type']:'',
                        'AutoClosePO' => isset($i['AutoClosePO'])?$i['AutoClosePO']:'',
                        'Consign' => isset($i['Consign'])?$i['Consign']:'',
                        'Block' => isset($i['Block'])?$i['Block']:'',
                        'exclude_orderqty_control' => isset($i['exclude_orderqty_control'])?$i['exclude_orderqty_control']:'',
                        'supcus_guid' => $i['supcus_guid'],
                        'acc_no' => isset($i['acc_no'])?$i['acc_no']:'',
                        'Ord_W1' => isset($i['Ord_W1'])?$i['Ord_W1']:'',
                        'Ord_W2' => isset($i['Ord_W2'])?$i['Ord_W2']:'',
                        'Ord_W3' => isset($i['Ord_W3'])?$i['Ord_W3']:'',
                        'Ord_W4' => isset($i['Ord_W4'])?$i['Ord_W4']:'',
                        'Ord_D1' => isset($i['Ord_D1'])?$i['Ord_D1']:'',
                        'Ord_D2' => isset($i['Ord_D2'])?$i['Ord_D2']:'',
                        'Ord_D3' => isset($i['Ord_D3'])?$i['Ord_D3']:'',
                        'Ord_D4' => isset($i['Ord_D4'])?$i['Ord_D4']:'',
                        'Ord_D5' => isset($i['Ord_D5'])?$i['Ord_D5']:'',
                        'Ord_D6' => isset($i['Ord_D6'])?$i['Ord_D6']:'',
                        'Ord_D7' => isset($i['Ord_D7'])?$i['Ord_D7']:'',
                        'Rec_Method_1' => isset($i['Rec_Method_1'])?$i['Rec_Method_1']:'',
                        'Rec_Method_2' => isset($i['Rec_Method_2'])?$i['Rec_Method_2']:'',
                        'Rec_Method_3' => isset($i['Rec_Method_3'])?$i['Rec_Method_3']:'',
                        'Rec_Method_4' => isset($i['Rec_Method_4'])?$i['Rec_Method_4']:'',
                        'Rec_Method_5' => isset($i['Rec_Method_5'])?$i['Rec_Method_5']:'',
                        'pur_expiry_days' => isset($i['pur_expiry_days'])?$i['pur_expiry_days']:'',
                        'grn_baseon_pocost' => isset($i['grn_baseon_pocost'])?$i['grn_baseon_pocost']:'',
                        'Ord_set_global' => isset($i['Ord_set_global'])?$i['Ord_set_global']:'',
                        'rules_code' => isset($i['rules_code'])?$i['rules_code']:'',
                        'po_negative_qty' => isset($i['po_negative_qty'])?$i['po_negative_qty']:'',
                        'grpo_variance_qty' => isset($i['grpo_variance_qty'])?$i['grpo_variance_qty']:'',
                        'grpo_variance_price' => isset($i['grpo_variance_price'])?$i['grpo_variance_price']:'',
                        'price_include_tax' => $i['price_include_tax'],
                        'delivery_early_in_day' => isset($i['delivery_early_in_day'])?$i['delivery_early_in_day']:'',
                        'delivery_late_in_day' => isset($i['delivery_late_in_day'])?$i['delivery_late_in_day']:'',
                        'tax_code' => isset($i['tax_code'])?$i['tax_code']:'',
                        'gst_start_date' => isset($i['gst_start_date'])?$i['gst_start_date']:'',
                        'gst_no' => isset($i['gst_no'])?$i['gst_no']:'',
                        'reg_no' => isset($i['reg_no'])?$i['reg_no']:'',
                        'name_reg' => isset($i['name_reg'])?$i['name_reg']:'',
                        'multi_tax_rate' => isset($i['multi_tax_rate'])?$i['multi_tax_rate']:'',
                        'grn_allow_negative_margin' => isset($i['grn_allow_negative_margin'])?$i['grn_allow_negative_margin']:'',
                        'rebate_as_inv' => isset($i['rebate_as_inv'])?$i['rebate_as_inv']:'',
                        'discount_as_inv' => isset($i['discount_as_inv'])?$i['discount_as_inv']:'',
                        'poso_line_max' => isset($i['poso_line_max'])?$i['poso_line_max']:'',
                        'apply_actual_cn' => isset($i['apply_actual_cn'])?$i['apply_actual_cn']:'',
                        'PromoRebateAsTaxInv' => isset($i['PromoRebateAsTaxInv'])?$i['PromoRebateAsTaxInv']:'',
                        'PurchaseDNAmtAsTaxInv' => isset($i['PurchaseDNAmtAsTaxInv'])?$i['PurchaseDNAmtAsTaxInv']:'',
                        'member_accno' => isset($i['member_accno'])?$i['member_accno']:'',
                        'RoundingAdjust' => isset($i['RoundingAdjust'])?$i['RoundingAdjust']:'',
                        'mobile_po' => isset($i['mobile_po'])?$i['mobile_po']:'',
                        'auto_grn_mobile_po' => isset($i['auto_grn_mobile_po'])?$i['auto_grn_mobile_po']:'',
                        'min_expiry_day' => isset($i['min_expiry_day'])?$i['min_expiry_day']:'',
                        'currency_code' => isset($i['currency_code'])?$i['currency_code']:'',
                        'SSTRegNo' => isset($i['SSTRegNo'])?$i['SSTRegNo']:'',
                        'SSTEffectiveDate' => isset($i['SSTEffectiveDate'])?$i['SSTEffectiveDate']:'',
                        'SSTDefaultCode' => isset($i['SSTDefaultCode'])?$i['SSTDefaultCode']:'',
                        'SSTDefaultTaxIntNo' => isset($i['SSTDefaultTaxIntNo'])?$i['SSTDefaultTaxIntNo']:'',
                        'replenish_date' => isset($i['replenish_date'])?$i['replenish_date']:'',
                        'replenish_stockbalance' => isset($i['replenish_stockbalance'])?$i['replenish_stockbalance']:'',
                        'b2b_registration' => isset($i['b2b_registration'])?$i['b2b_registration']:'',
                        'cdi' => isset($i['cdi'])?$i['cdi']:'',
                        'cpm' => isset($i['cpm'])?$i['cpm']:'',
                        'imported' => '1',
                        'imported_at' => $this->datetime(),
                        'exported' => '0',
                        'exported_at' => '1001-01-01 00:00:00'
                    );

                    $success_refno[] =  $i['Type'].'-Code'.$i['Code'];
                }
                else
                {
                    $main_status = 'Failed';
                    array_push($line_fail,$line);
                    $failed_refno[] = $i['Type'].'-Code'.$i['Code'];
                }
                $line++;
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'supcus',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            $log_child_chunk = array_chunk($log_child,1000);

            foreach($log_child_chunk AS $child)
            {
                $this->db->insert_batch($hub_db.'.post_log_c',$child);
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($supcus_data) > 0)
            {
                $supcus_data_chunk = array_chunk($supcus_data,1000);
                foreach($supcus_data_chunk AS $supcus_child)
                {
                    $this->db->insert_batch($hub_db.'.supcus',$supcus_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'supcus successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                $response = array(
                    'status' => FALSE,
                    'message' => 'supcus failed to sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
        
    }

    public function b2b_cp_set_branch_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_cp_set_branch'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $cp_set_branch_data = array();

            foreach($data as $i)
            {
                $parameter = array(
                    'BRANCH_GUID' => $i['BRANCH_GUID'],
                    'BRANCH_CODE' => $i['BRANCH_CODE'],
                );

                $this->db->delete($hub_db.'.cp_set_branch', $parameter); 
            }

            foreach($data as $i)
            {
                //checking
                if(!isset($i['BRANCH_GUID']) || $i['BRANCH_GUID'] == '')
                {
                    $status = 'Failed';
                    $message = 'Branch doesn\'t exist.';
                }
                elseif(!isset($i['BRANCH_CODE']) || $i['BRANCH_CODE'] == '')
                {
                    $status = 'Failed';
                    $message = 'Branch code doesn\'t exist';
                }
                elseif(!isset($i['PeriodEndOn']) || $i['PeriodEndOn'] == '')
                {
                    $status = 'Failed';
                    $message = 'Period end on doesn\'t exist';
                }
                else
                {
                    $status = 'Success';
                    $message = 'Success';
                }

                $log_child[] = array(
                    'line_guid' => $this->guid(),
                    'guid' => $guid,
                    'panda_refno' => '',
                    'refno' => $log_refno,
                    'line' => $line,
                    'itemcode' => isset($i['BRANCH_GUID']) || $i['BRANCH_GUID'] == ''?$i['BRANCH_GUID']:'',
                    'status' => $status,
                    'message' => $message,
                    'session' => $session_guid
                );

                if($status == 'Success')
                {
                    $cp_set_branch_data[] = array(
                        'BRANCH_GUID' => $i['BRANCH_GUID'],
                        'BRANCH_CODE' => $i['BRANCH_CODE'],
                        'BRANCH_NAME' => isset($i['BRANCH_NAME'])?$i['BRANCH_NAME']:'',
                        'BRANCH_ADD' => isset($i['BRANCH_ADD'])?$i['BRANCH_ADD']:'',
                        'BRANCH_TEL' => isset($i['BRANCH_TEL'])?$i['BRANCH_TEL']:'',
                        'BRANCH_FAX' => isset($i['BRANCH_FAX'])?$i['BRANCH_FAX']:'',
                        'SCRIPT_TABLENAME' => isset($i['SCRIPT_TABLENAME'])?$i['SCRIPT_TABLENAME']:'',
                        'SET_RATIO' => isset($i['SET_RATIO'])?$i['SET_RATIO']:'',
                        'SET_PRIORITY' => isset($i['SET_PRIORITY'])?$i['SET_PRIORITY']:'',
                        'CREATED_AT' => isset($i['CREATED_AT'])?$i['CREATED_AT']:'',
                        'CREATED_BY' => isset($i['CREATED_BY'])?$i['CREATED_BY']:'',
                        'UPDATED_AT' => isset($i['UPDATED_AT'])?$i['UPDATED_AT']:'',
                        'UPDATED_BY' => isset($i['UPDATED_BY'])?$i['UPDATED_BY']:'',
                        'SET_SUPPLIER_CODE' => isset($i['SET_SUPPLIER_CODE'])?$i['SET_SUPPLIER_CODE']:'',
                        'SET_CUSTOMER_CODE' => isset($i['SET_CUSTOMER_CODE'])?$i['SET_CUSTOMER_CODE']:'',
                        'sshHostname' => isset($i['sshHostname'])?$i['sshHostname']:'',
                        'sshPort' => isset($i['sshPort'])?$i['sshPort']:'',
                        'sshUser' => isset($i['sshUser'])?$i['sshUser']:'',
                        'sshPass' => isset($i['sshPass'])?$i['sshPass']:'',
                        'databaset_default' => isset($i['databaset_default'])?$i['databaset_default']:'',
                        'mysql_user' => isset($i['mysql_user'])?$i['mysql_user']:'',
                        'mysql_pass' => isset($i['mysql_pass'])?$i['mysql_pass']:'',
                        'sshCDestHost' => isset($i['sshCDestHost'])?$i['sshCDestHost']:'',
                        'sshCDestPort' => isset($i['sshCDestPort'])?$i['sshCDestPort']:'',
                        'sshCSourcePort' => isset($i['sshCSourcePort'])?$i['sshCSourcePort']:'',
                        'script_database_tablename' => isset($i['script_database_tablename'])?$i['script_database_tablename']:'',
                        'OUTLET_CODE_ACC' => isset($i['OUTLET_CODE_ACC'])?$i['OUTLET_CODE_ACC']:'',
                        'PeriodEndOn' => $i['PeriodEndOn'],
                        'LastRecalDateTime' => isset($i['LastRecalDateTime'])?$i['LastRecalDateTime']:'',
                        'RecalTime' => isset($i['RecalTime'])?$i['RecalTime']:'',
                        'nontrade_as_stock' => isset($i['nontrade_as_stock'])?$i['nontrade_as_stock']:'',
                        'set_active' => isset($i['set_active'])?$i['set_active']:'',
                        'is_dc' => isset($i['is_dc'])?$i['is_dc']:'',
                        'rep_all_ads' => isset($i['rep_all_ads'])?$i['rep_all_ads']:'',
                        'branch_desc' => isset($i['branch_desc'])?$i['branch_desc']:'',
                        'fifo_calc' => isset($i['fifo_calc'])?$i['fifo_calc']:'',
                        'imported' => '1',
                        'imported_at' => $this->datetime(),
                        'exported' => '0',
                        'exported_at' => '1001-01-01 00:00:00'
                    );

                    $success_refno[] =  $i['BRANCH_GUID'];
                }
                else
                {
                    $main_status = 'Failed';
                    array_push($line_fail,$line);
                    $failed_refno[] = $i['BRANCH_GUID'];
                }
                $line++;
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'cp_set_branch',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            $log_child_chunk = array_chunk($log_child,1000);
            foreach($log_child_chunk AS $child)
            {
                $this->db->insert_batch($hub_db.'.post_log_c',$child);
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($cp_set_branch_data) > 0)
            {
                $cp_set_branch_data_chunk = array_chunk($cp_set_branch_data,1000);
                foreach($cp_set_branch_data_chunk AS $cp_set_branch_child)
                {
                    $this->db->insert_batch($hub_db.'.cp_set_branch',$cp_set_branch_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'cp_set_branch successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                $response = array(
                    'status' => FALSE,
                    'message' => 'cp_set_branch failed to sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
        
    }

    public function b2b_locationgroup_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_locationgroup'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $locationgroup_data = array();

            foreach($data as $i)
            {
                $code = $i['Code'];
                $this->db->delete($hub_db.'.locationgroup', array('Code' => $code)); 
            }

            foreach($data as $i)
            {
                //checking
                if(!isset($i['Code']) || $i['Code'] == '')
                {
                    $status = 'Failed';
                    $message = 'Code doesn\'t exist.';
                }
                else
                {
                    $status = 'Success';
                    $message = 'Success';
                }

                $log_child[] = array(
                    'line_guid' => $this->guid(),
                    'guid' => $guid,
                    'panda_refno' => '',
                    'refno' => $log_refno,
                    'line' => $line,
                    'itemcode' => isset($i['Code']) || $i['Code'] == ''?$i['Code']:'',
                    'status' => $status,
                    'message' => $message,
                    'session' => $session_guid
                );

                if($status == 'Success')
                {
                    $locationgroup_data[] = array(
                        'Code' => $i['Code'],
                        'Description' => isset($i['Description'])?$i['Description']:'',
                        'Remark' => isset($i['Remark'])?$i['Remark']:'',
                        'set_active' => isset($i['set_active'])?$i['set_active']:'',
                        'imported' => '1',
                        'imported_at' => $this->datetime()
                    );

                    $success_refno[] =  $i['Code'];
                }
                else
                {
                    $main_status = 'Failed';
                    array_push($line_fail,$line);
                    $failed_refno[] = $i['Code'];
                }
                $line++;
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'locationgroup',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            $log_child_chunk = array_chunk($log_child,1000);
            foreach($log_child_chunk AS $child)
            {
                $this->db->insert_batch($hub_db.'.post_log_c',$child);
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($locationgroup_data) > 0)
            {
                $locationgroup_data_chunk = array_chunk($locationgroup_data,1000);
                foreach($locationgroup_data_chunk AS $locationgroup_child)
                {
                    $this->db->insert_batch($hub_db.'.locationgroup',$locationgroup_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'locationgroup successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                $response = array(
                    'status' => FALSE,
                    'message' => 'locationgroup failed to sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }
        
        echo json_encode($response);
        
    }

    public function b2b_companyprofile_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);
        
        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_companyprofile'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $companyprofile_data = array();

            foreach($data as $i)
            {
                $name = $i['CompanyName'];
                // echo $name;
                $this->db->delete($hub_db.'.companyprofile', array('CompanyName' => $name)); 
            }

            foreach($data as $i)
            {
                //checking
                if(!isset($i['CompanyName']) || $i['CompanyName'] == '')
                {
                    $status = 'Failed';
                    $message = 'Company name doesn\'t exist.';
                }
                else
                {
                    $status = 'Success';
                    $message = 'Success';
                }

                $log_child[] = array(
                    'line_guid' => $this->guid(),
                    'guid' => $guid,
                    'panda_refno' => '',
                    'refno' => $log_refno,
                    'line' => $line,
                    'itemcode' => isset($i['CompanyName']) || $i['CompanyName'] == ''?$i['CompanyName']:'',
                    'status' => $status,
                    'message' => $message,
                    'session' => $session_guid
                );

                if($status == 'Success')
                {
                    $companyprofile_data[] = array(
                        'CompanyName' => $i['CompanyName'],
                        'Address1' => isset($i['Address1'])?$i['Address1']:'',
                        'Address2' => isset($i['Address2'])?$i['Address2']:'',
                        'Tel' => isset($i['Tel'])?$i['Tel']:'',
                        'Fax' => isset($i['Fax'])?$i['Fax']:'',
                        'asHQ' => isset($i['asHQ'])?$i['asHQ']:'',
                        'locgroup_branch' => isset($i['locgroup_branch'])?$i['locgroup_branch']:'',
                        'locgroup_dc' => isset($i['locgroup_dc'])?$i['locgroup_dc']:'',
                        'comp_code' => isset($i['comp_code'])?$i['comp_code']:'',
                        'system_start_date' => isset($i['system_start_date'])?$i['system_start_date']:'',
                        'gst_no' => isset($i['gst_no'])?$i['gst_no']:'',
                        'Address3' => isset($i['Address3'])?$i['Address3']:'',
                        'City' => isset($i['City'])?$i['City']:'',
                        'postalcode' => isset($i['postalcode'])?$i['postalcode']:'',
                        'state' => isset($i['state'])?$i['state']:'',
                        'country' => isset($i['country'])?$i['country']:'',
                        'gst_start_date' => isset($i['gst_start_date'])?$i['gst_start_date']:'',
                        'comp_reg_no' => isset($i['comp_reg_no'])?$i['comp_reg_no']:'',
                        'tax_inclusive' => isset($i['tax_inclusive'])?$i['tax_inclusive']:'',
                        'gst_submission_cycle' => isset($i['gst_submission_cycle'])?$i['gst_submission_cycle']:'',
                        'item_runningno_type' => isset($i['item_runningno_type'])?$i['item_runningno_type']:'',
                        'item_runningno_digit' => isset($i['item_runningno_digit'])?$i['item_runningno_digit']:'',
                        'sst_start_date' => isset($i['sst_start_date'])?$i['sst_start_date']:'',
                        'gst_end_date' => isset($i['gst_end_date'])?$i['gst_end_date']:'',
                        'sst_no' => isset($i['sst_no'])?$i['sst_no']:'',
                        'sst_end_date' => isset($i['sst_end_date'])?$i['sst_end_date']:'',
                        'imported' => '1',
                        'imported_at' => $this->datetime(),
                        'exported' => '0',
                        'exported_at' => '1001-01-01 00:00:00'
                    );

                    $success_refno[] =  $i['CompanyName'];
                }
                else
                {
                    $main_status = 'Failed';
                    array_push($line_fail,$line);
                    $failed_refno[] = $i['CompanyName'];
                }
                $line++;
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'companyprofile',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            $log_child_chunk = array_chunk($log_child,1000);
            foreach($log_child_chunk AS $child)
            {
                $this->db->insert_batch($hub_db.'.post_log_c',$child);
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($companyprofile_data) > 0)
            {
                $companyprofile_data_chunk = array_chunk($companyprofile_data,1000);
                foreach($companyprofile_data_chunk AS $companyprofile_child)
                {
                    $this->db->insert_batch($hub_db.'.companyprofile',$companyprofile_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'companyprofile successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                $response = array(
                    'status' => FALSE,
                    'message' => 'companyprofile failed to sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
        
    }

    public function b2b_sku_cs_date_log_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = $this->post('data');
        $period_code = $this->post('period_code');
        $customer_guid = $this->post('customer_guid');

        $newmonth = explode("-",$period_code);
        $lastmonth = date("Y-m",mktime(0,0,0,date($newmonth['1'])-1,1,date($newmonth['0'])));
        date_default_timezone_set("Asia/Kuala_Lumpur");
        $doc_date = date('Y-m-d',strtotime("-1 days"));
        // var_dump($data);die;
        if($data > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $guid = $this->guid();

            $this->db->query("DELETE FROM $hub_db.sku_cs_date WHERE PeriodCode IN ('$period_code','$lastmonth')");

            $transfer_log_data = array(
                'guid' => $guid,
                'module' => 'sku_cs_date',
                'period_code' => $period_code,
                'doc_date' => $doc_date,
                'total_data' => $data,
                'created_at' => $this->datetime()
            );

            $check_transfer_log = $this->db->query("SELECT * FROM $hub_db.transfer_log WHERE period_code = '$period_code' AND doc_date = '$doc_date'")->result_array();

            if(sizeof($check_transfer_log) < 1 ){

                $this->db->insert($hub_db.'.transfer_log', $transfer_log_data);
                $message = "transfer log successfully created";

                $response = array(
                    'status' => true,
                    'flag' => 1,
                    'message' => $message,
                    'success_refno' => $period_code,
                    'failed_refno' => ''
                );

            } else if(sizeof($check_transfer_log) > 0 ){

                $message = "transfer log already exist";

                $response = array(
                    'status' => true,
                    'flag' => 0,
                    'message' => $message,
                    'success_refno' => $period_code,
                    'failed_refno' => ''
                );

            } else {

                $message = "failed to create transfer log";

                $response = array(
                    'status' => false,
                    'flag' => 2,
                    'message' => $message,
                    'success_refno' => '',
                    'failed_refno' => $period_code
                );

            }

            // if($this->db->affected_rows() > 0)
            // {

            //     $response = array(
            //         'status' => true,
            //         'message' => $message,
            //         'success_refno' => $period_code,
            //         'failed_refno' => ''
            //     );
            // } else {

            //     $response = array(
            //         'status' => false,
            //         'message' => $message,
            //         'success_refno' => '',
            //         'failed_refno' => $period_code
            //     );
            // }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        // var_dump($response); die;

        echo json_encode($response);
        
    }

    public function b2b_sku_cs_date_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $period_code = $this->post('period_code');
        $customer_guid = $this->post('customer_guid');
        $total_data = $this->post('total_data');
        $doc_date = $this->post('doc_date');

        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $guid = $this->guid();
            $session_guid = $this->guid();
            $sku_cs_date_data = array();

            foreach($data as $i)
            {
                    $sku_cs_date_data[] = array(
                        'bizdate' => isset($i['bizdate'])?$i['bizdate']:'',
                        'PeriodCode' => isset($i['PeriodCode'])?$i['PeriodCode']:'',
                        'location_group' => isset($i['location_group'])?$i['location_group']:'',
                        'Location' => isset($i['Location'])?$i['Location']:'',
                        'Division' => isset($i['Division'])?$i['Division']:'',
                        'DivDesc' => isset($i['DivDesc'])?$i['DivDesc']:'',
                        'Dept' => isset($i['Dept'])?$i['Dept']:'',
                        'DeptDesc' => isset($i['DeptDesc'])?$i['DeptDesc']:'',
                        'SubDept' => isset($i['SubDept'])?$i['SubDept']:'',
                        'SubDeptDesc' => isset($i['SubDeptDesc'])?$i['SubDeptDesc']:'',
                        'Category' => isset($i['Category'])?$i['Category']:'',
                        'CategoryDesc' => isset($i['CategoryDesc'])?$i['CategoryDesc']:'',
                        'Manufacturer' => isset($i['Manufacturer'])?$i['Manufacturer']:'',
                        'ManufacturerDesc' => isset($i['ManufacturerDesc'])?$i['ManufacturerDesc']:'',
                        'Brand' => isset($i['Brand'])?$i['Brand']:'',
                        'BrandDesc' => isset($i['BrandDesc'])?$i['BrandDesc']:'',
                        'itemcode' => isset($i['itemcode'])?$i['itemcode']:'',
                        'itemlink' => isset($i['itemlink'])?$i['itemlink']:'',
                        'barcode' => isset($i['barcode'])?$i['barcode']:'',
                        'description' => isset($i['description'])?$i['description']:'',
                        'packsize' => isset($i['packsize'])?$i['packsize']:'',
                        'itemtype' => isset($i['itemtype'])?$i['itemtype']:'',
                        'SoldbyWeight' => isset($i['SoldbyWeight'])?$i['SoldbyWeight']:'',
                        'Consign' => isset($i['Consign'])?$i['Consign']:'',
                        'costmargin' => isset($i['costmargin'])?$i['costmargin']:'',
                        'Costmarginvalue' => isset($i['Costmarginvalue'])?$i['Costmarginvalue']:'',
                        'Colour' => isset($i['Colour'])?$i['Colour']:'',
                        'size' => isset($i['size'])?$i['size']:'',
                        'um' => isset($i['um'])?$i['um']:'',
                        'umbulk' => isset($i['umbulk'])?$i['umbulk']:'',
                        'BulkQty' => isset($i['BulkQty'])?$i['BulkQty']:'',
                        'HamperInQty' => isset($i['HamperInQty'])?$i['HamperInQty']:'',
                        'HamperInAmt' => isset($i['HamperInAmt'])?$i['HamperInAmt']:'',
                        'HamperOutQty' => isset($i['HamperOutAmt'])?$i['HamperOutAmt']:'',
                        'HamperOutAmt' => isset($i['HamperOutAmt'])?$i['HamperOutAmt']:'',
                        'DebitQty_cs' => isset($i['DebitQty_cs'])?$i['DebitQty_cs']:'',
                        'DebitAmt_cs' => isset($i['DebitAmt_cs'])?$i['DebitAmt_cs']:'',
                        'CreditQty_cs' => isset($i['CreditQty_cs'])?$i['CreditQty_cs']:'',
                        'CreditAmt_cs' => isset($i['CreditAmt_cs'])?$i['CreditAmt_cs']:'',
                        'Sales_POS_Qty_CS' => isset($i['Sales_POS_Qty_CS'])?$i['Sales_POS_Qty_CS']:'',
                        'Sales_POS_Amt_CS' => isset($i['Sales_POS_Amt_CS'])?$i['Sales_POS_Amt_CS']:'',
                        'Sales_SI_Qty_CS' => isset($i['Sales_SI_Qty_CS'])?$i['Sales_SI_Qty_CS']:'',
                        'Sales_SI_Amt_CS' => isset($i['Sales_SI_Amt_CS'])?$i['Sales_SI_Amt_CS']:'',
                        'Cost_CS' => isset($i['Cost_CS'])?$i['Cost_CS']:'',
                        'pos_disc_cs' => isset($i['pos_disc_cs'])?$i['pos_disc_cs']:'',
                        'cogs' => isset($i['cogs'])?$i['cogs']:'',
                        'profit' => isset($i['profit'])?$i['profit']:'',
                        'CREATED_AT' => isset($i['CREATED_AT'])?$i['CREATED_AT']:'',
                        'CREATED_BY' => isset($i['CREATED_BY'])?$i['CREATED_BY']:'',
                        'UPDATED_AT' => isset($i['UPDATED_AT'])?$i['UPDATED_AT']:'',
                        'UPDATED_BY' => isset($i['UPDATED_BY'])?$i['UPDATED_BY']:'',
                        'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                        'cost_cs_last' => isset($i['cost_cs_last'])?$i['cost_cs_last']:'',
                        'imported' => '1',
                        'imported_at' => $this->datetime(),
                        'exported' => '0',
                        'exported_at' => '1001-01-01 00:00:00'
                    );

            }

            if(sizeof($sku_cs_date_data) > 0)
            {
                
                $sku_cs_date_data_chunk = array_chunk($sku_cs_date_data,10000);
                foreach($sku_cs_date_data_chunk AS $sku_cs_data_child)
                {
                    $this->db->insert_batch($hub_db.'.sku_cs_date',$sku_cs_data_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                    $current_complete = $this->db->query("SELECT current_complete FROM $hub_db.transfer_log WHERE period_code = '$period_code' AND doc_date = '$doc_date'")->row('current_complete');
                    $count_data = sizeof($data);
                    $current_data = intval($current_complete) + intval($count_data);

                    if($total_data==$current_data){
                        $transfer_flag = 1;
                    } else {
                        $transfer_flag = 0;
                    }

                    $transfer_log_update = array(
                        'current_complete' => $current_data,
                        'transfer_status' => $transfer_flag,
                        'updated_at' => $this->datetime()
                    );

                    $where = array(
                        'period_code' => $period_code,
                        'doc_date' => $doc_date
                    );

                    $this->db->where($where);
                    $this->db->update($hub_db.'.transfer_log', $transfer_log_update);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'sku_cs_date successfully sync.',
                    'current_data' => $current_data,
                    'transfer_status' => $transfer_flag,
                );
            }
            else
            {
                $response = array(
                    'status' => FALSE,
                    'message' => 'sku_cs_date failed to sync.',
                    'current_data' => $current_data,
                    'transfer_status' => $transfer_flag,
                );
            }

        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
        
    }

    public function b2b_grmain_dncn_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_grmain_dncn'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $grmain_dncn_data = array();
            $log_child = array();

            // print_r($data); die;

            foreach($data as $i)
            {
                $parameter = array(
                    'RefNo' => $i['refno'],
                    'transtype' => $i['transtype'],
                    'trans_seq' => $i['trans_seq']
                );

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $update_parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );

                    $this->db->where($parameter);
                    $this->db->update($hub_db.'.grmain_dncn', $update_parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'].'-Type'.$i['transtype'].'-Seq'.$i['trans_seq'];
                    } else{
                        $failed_refno[] =  $i['refno'].'-Type'.$i['transtype'].'-Seq'.$i['trans_seq'];
                    }

                } else{

                    $this->db->delete($hub_db.'.grmain_dncn', $parameter);
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Reference No doesn\'t exist.';
                    }
                    elseif(!isset($i['transtype']) || $i['transtype'] == '')
                    {   
                        $status = 'Failed';
                        $message = 'Transtype doesn\'t exist';
                    }
                    elseif(!isset($i['trans_seq']) || $i['trans_seq'] == '')
                    {   
                        $status = 'Failed';
                        $message = 'trans_seq doesn\'t exist';
                    }
                    elseif(!isset($i['gl_code']) || $i['gl_code'] == '')
                    {
                        $status = 'Failed';
                        $message = 'gl_code doesn\'t exist';
                    }
                    elseif(!isset($i['ap_sup_code']) || $i['ap_sup_code'] == '')
                    {
                        $status = 'Failed';
                        $message = 'ap_sup_code doesn\'t exist';
                    }
                    elseif(!isset($i['loc_group']) || $i['loc_group'] == '')
                    {
                        $status = 'Failed';
                        $message = 'loc_group doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else{
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['RefNo']) || $i['RefNo'] == ''?$i['RefNo']:'',
                        'itemcode' => (isset($i['RefNo']) && isset($i['transtype']) && isset($i['trans_seq'])) || $i['RefNo'] != ''?$i['RefNo'].'-Type'.$i['transtype'].'-Seq'.$i['trans_seq']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $grmain_dncn_data[] = array(
                            'RefNo' => $i['RefNo'],
                            'VarianceAmt' => isset($i['VarianceAmt'])?$i['VarianceAmt']:'',
                            'Created_at' => isset($i['Created_at'])?$i['Created_at']:'',
                            'Created_by' => isset($i['Created_by'])?$i['Created_by']:'',
                            'Updated_at' => isset($i['Updated_at'])?$i['Updated_at']:'',
                            'Updated_by' => isset($i['Updated_by'])?$i['Updated_by']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'EXPORT_ACCOUNT' => isset($i['EXPORT_ACCOUNT'])?$i['EXPORT_ACCOUNT']:'',
                            'EXPORT_AT' => isset($i['EXPORT_AT'])?$i['EXPORT_AT']:'',
                            'EXPORT_BY' => isset($i['EXPORT_BY'])?$i['EXPORT_BY']:'',
                            'transtype' => $i['transtype'],
                            'share_cost' => isset($i['share_cost'])?$i['share_cost']:'',
                            'gst_tax_sum' => isset($i['gst_tax_sum'])?$i['gst_tax_sum']:'',
                            'gst_adjust' => isset($i['gst_adjust'])?$i['gst_adjust']:'',
                            'gl_code' => $i['gl_code'],
                            'tax_invoice' => isset($i['tax_invoice'])?$i['tax_invoice']:'',
                            'ap_sup_code' => $i['ap_sup_code'],
                            'refno2' => isset($i['refno2'])?$i['refno2']:'',
                            'rounding_adj' => isset($i['rounding_adj'])?$i['rounding_adj']:'',
                            'sup_cn_no' => isset($i['sup_cn_no'])?$i['sup_cn_no']:'',
                            'sup_cn_date' => isset($i['sup_cn_date'])?$i['sup_cn_date']:'',
                            'dncn_date' => isset($i['dncn_date'])?$i['dncn_date']:'',
                            'dncn_date_acc' => isset($i['dncn_date_acc'])?$i['dncn_date_acc']:'',
                            'uploaded' => isset($i['uploaded'])?$i['uploaded']:'',
                            'uploaded_at' => isset($i['uploaded_at'])?$i['uploaded_at']:'',
                            'trans_seq' => isset($i['trans_seq'])?$i['trans_seq']:'',
                            'landed_cost' => isset($i['landed_cost'])?$i['landed_cost']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'loc_group' => $i['loc_group'],
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] = $i['RefNo'].'-Type'.$i['transtype'].'-Seq'.$i['trans_seq'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'].'-Type'.$i['transtype'].'-Seq'.$i['trans_seq'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'grmain_dncn',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){
                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($grmain_dncn_data) > 0)
            {
                $grmain_dncn_data_chunk = array_chunk($grmain_dncn_data,1000);
                foreach($grmain_dncn_data_chunk AS $grmain_dncn_child)
                {
                    $this->db->insert_batch($hub_db.'.grmain_dncn',$grmain_dncn_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'grmain_dncn successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'grmain_dncn successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'grmain_dncn failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }

            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
        
    }

    public function b2b_dbnotemain_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_dbnotemain'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $dbnotemain_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $refno = $i['refno'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );

                    $this->db->where('refno',$refno);
                    $this->db->update($hub_db.'.dbnotemain', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'];
                    } else{
                        $failed_refno[] =  $i['refno'];
                    }

                } else{

                    $this->db->delete($hub_db.'.dbnotemain', array('refno' => $refno)); 
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['Type']) || $i['Type'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Type doesn\'t exist.';
                    }
                    elseif(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Ref No doesn\'t exist';
                    }
                    elseif(!isset($i['Location']) || $i['Location'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Location doesn\'t exist';
                    }
                    elseif(!isset($i['SCType']) || $i['SCType'] == '')
                    {
                        $status = 'Failed';
                        $message = 'SCType doesn\'t exist';
                    }
                    elseif(!isset($i['Code']) || $i['Code'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Code doesn\'t exist';
                    }
                    elseif(!isset($i['Name']) || $i['Name'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Name doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else{
                    $status = 'Success';
                    $message = 'Success'; 
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['RefNo']) || $i['RefNo'] == ''?$i['RefNo']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $dbnotemain_data[] = array(
                            'Type' => $i['Type'],
                            'RefNo' => $i['RefNo'],
                            'Location' => $i['Location'],
                            'DocNo' => isset($i['DocNo'])?$i['DocNo']:'',
                            'DocDate' => isset($i['DocDate'])?$i['DocDate']:'',
                            'IssueStamp' => isset($i['IssueStamp'])?$i['IssueStamp']:'',
                            'LastStamp' => isset($i['LastStamp'])?$i['LastStamp']:'',
                            'PONo' => isset($i['PONo'])?$i['PONo']:'',
                            'SCType' => $i['SCType'],
                            'Code' => $i['Code'],
                            'Name' => $i['Name'],
                            'Term' => isset($i['Term'])?$i['Term']:'',
                            'Issuedby' => isset($i['Issuedby'])?$i['Issuedby']:'',
                            'Remark' => isset($i['Remark'])?$i['Remark']:'',
                            'BillStatus' => isset($i['BillStatus'])?$i['BillStatus']:'',
                            'AccStatus' => isset($i['AccStatus'])?$i['AccStatus']:'',
                            'DueDate' => isset($i['DueDate'])?$i['DueDate']:'',
                            'Amount' => isset($i['Amount'])?$i['Amount']:'',
                            'Closed' => isset($i['Closed'])?$i['Closed']:'',
                            'SubDeptCode' => isset($i['SubDeptCode'])?$i['SubDeptCode']:'',
                            'postby' => isset($i['postby'])?$i['postby']:'',
                            'postdatetime' => isset($i['postdatetime'])?$i['postdatetime']:'',
                            'Consign' => isset($i['Consign'])?$i['Consign']:'',
                            'EXPORT_ACCOUNT' => isset($i['EXPORT_ACCOUNT'])?$i['EXPORT_ACCOUNT']:'',
                            'EXPORT_AT' => isset($i['EXPORT_AT'])?$i['EXPORT_AT']:'',
                            'EXPORT_BY' => isset($i['EXPORT_BY'])?$i['EXPORT_BY']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'locgroup' => isset($i['locgroup'])?$i['locgroup']:'',
                            'ibt' => isset($i['ibt'])?$i['ibt']:'',
                            'SubTotal1' => isset($i['SubTotal1'])?$i['SubTotal1']:'',
                            'Discount1' => isset($i['Discount1'])?$i['Discount1']:'',
                            'Discount1Type' => isset($i['Discount1Type'])?$i['Discount1Type']:'',
                            'SubTotal2' => isset($i['SubTotal2'])?$i['SubTotal2']:'',
                            'Discount2' => isset($i['Discount2'])?$i['Discount2']:'',
                            'Discount2Type' => isset($i['Discount2Type'])?$i['Discount2Type']:'',
                            'gst_tax_sum' => isset($i['gst_tax_sum'])?$i['gst_tax_sum']:'',
                            'tax_code_purchase' => isset($i['tax_code_purchase'])?$i['tax_code_purchase']:'',
                            'sup_cn_no' => isset($i['sup_cn_no'])?$i['sup_cn_no']:'',
                            'sup_cn_date' => isset($i['sup_cn_date'])?$i['sup_cn_date']:'',
                            'doc_name_reg' => isset($i['doc_name_reg'])?$i['doc_name_reg']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'multi_tax_code' => isset($i['multi_tax_code'])?$i['multi_tax_code']:'',
                            'refno2' => isset($i['refno2'])?$i['refno2']:'',
                            'surchg_tax_sum' => isset($i['surchg_tax_sum'])?$i['surchg_tax_sum']:'',
                            'gst_adj' => isset($i['gst_adj'])?$i['gst_adj']:'',
                            'rounding_adj' => isset($i['rounding_adj'])?$i['rounding_adj']:'',
                            'unpostby' => isset($i['unpostby'])?$i['unpostby']:'',
                            'unpostdatetime' => isset($i['unpostdatetime'])?$i['unpostdatetime']:'',
                            'ibt_gst' => isset($i['ibt_gst'])?$i['ibt_gst']:'',
                            'acc_posting_date' => isset($i['acc_posting_date'])?$i['acc_posting_date']:'',
                            'RoundAdjNeed' => isset($i['RoundAdjNeed'])?$i['RoundAdjNeed']:'',
                            'uploaded' => isset($i['uploaded'])?$i['uploaded']:'',
                            'uploaded_at' => isset($i['uploaded_at'])?$i['uploaded_at']:'',
                            'CONVERTED_FROM_MODULE' => isset($i['CONVERTED_FROM_MODULE'])?$i['CONVERTED_FROM_MODULE']:'',
                            'CONVERTED_FROM_GUID' => isset($i['CONVERTED_FROM_GUID'])?$i['CONVERTED_FROM_GUID']:'',
                            'b2b_status' => isset($i['b2b_status'])?$i['b2b_status']:'',
                            'stock_collected' => isset($i['stock_collected'])?$i['stock_collected']:'',
                            'date_collected' => isset($i['date_collected'])?$i['date_collected']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['RefNo'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'dbnotemain',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($dbnotemain_data) > 0)
            {
                $dbnotemain_data_chunk = array_chunk($dbnotemain_data,1000);
                foreach($dbnotemain_data_chunk AS $dbnotemain_child)
                {
                    $this->db->insert_batch($hub_db.'.dbnotemain',$dbnotemain_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'dbnotemain successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'dbnotemain successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'dbnotemain failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_dbnotechild_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');

        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_dbnotechild'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $dbnotechild_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $parameter = array(
                    'RefNo' => $i['RefNo'],
                    'Line' => $i['Line'],
                );

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $update_parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );

                    $this->db->where($parameter);
                    $this->db->update($hub_db.'.dbnotechild', $update_parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['RefNo'].'-Line'.$i['Line'];
                    } else{
                        $failed_refno[] =  $i['RefNo'].'-Line'.$i['Line'];
                    }

                } else{

                    $this->db->delete($hub_db.'.dbnotechild', $parameter);
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['Type']) || $i['Type'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Type doesn\'t exist.';
                    }
                    elseif(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Refno doesn\'t exist';
                    }
                    elseif(!isset($i['Line']) || $i['Line'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Line doesn\'t exist';
                    }
                    elseif(!isset($i['Itemcode']) || $i['Itemcode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Itemcode doesn\'t exist';
                    }
                    elseif(!isset($i['Description']) || $i['Description'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Description doesn\'t exist';
                    }
                    elseif(!isset($i['ArticleNo']) || $i['ArticleNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Article No doesn\'t exist';
                    }
                    elseif(!isset($i['Dept']) || $i['Dept'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Dept doesn\'t exist';
                    }
                    elseif(!isset($i['SubDept']) || $i['SubDept'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Subdept doesn\'t exist';
                    }
                    elseif(!isset($i['Category']) || $i['Category'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Category doesn\'t exist';
                    }
                    elseif(!isset($i['Barcode']) || $i['Barcode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Barcode doesn\'t exist';
                    }
                    elseif(!isset($i['ItemLink']) || $i['ItemLink'] == '')
                    {
                        $status = 'Failed';
                        $message = 'ItemLink doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else{
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => (isset($i['RefNo']) && isset($i['Line'])) || $i['RefNo'] != ''?$i['RefNo'].'-Line'.$i['Line']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $dbnotechild_data[] = array(
                            'Type' => $i['Type'],
                            'RefNo' => $i['RefNo'],
                            'Line' => $i['Line'],
                            'EntryType' => isset($i['EntryType'])?$i['EntryType']:'',
                            'PriceType' => isset($i['PriceType'])?$i['PriceType']:'',
                            'Itemcode' => $i['Itemcode'],
                            'Description' => $i['Description'],
                            'Qty' => isset($i['Qty'])?$i['Qty']:'',
                            'UnitPrice' => isset($i['UnitPrice'])?$i['UnitPrice']:'',
                            'TotalPrice' => isset($i['TotalPrice'])?$i['TotalPrice']:'',
                            'Colour' => isset($i['Colour'])?$i['Colour']:'',
                            'Size' => isset($i['Size'])?$i['Size']:'',
                            'ArticleNo' => $i['ArticleNo'],
                            'Packsize' => isset($i['Packsize'])?$i['Packsize']:'',
                            'UM' => isset($i['UM'])?$i['UM']:'',
                            'Brand' => isset($i['Brand'])?$i['Brand']:'',
                            'Location' => isset($i['Location'])?$i['Location']:'',
                            'Dept' => $i['Dept'],
                            'SubDept' => $i['SubDept'],
                            'Category' => $i['Category'],
                            'Barcode' => $i['Barcode'],
                            'Manufacturer' => isset($i['Manufacturer'])?$i['Manufacturer']:'',
                            'AverageCost' => isset($i['AverageCost'])?$i['AverageCost']:'',
                            'LastCost' => isset($i['LastCost'])?$i['LastCost']:'',
                            'StdCost' => isset($i['StdCost'])?$i['StdCost']:'',
                            'SellingPrice' => isset($i['SellingPrice'])?$i['SellingPrice']:'',
                            'WeightedAvgCost' => isset($i['WeightedAvgCost'])?$i['WeightedAvgCost']:'',
                            'ItemLink' => $i['ItemLink'],
                            'TotalSysQoH' => isset($i['TotalSysQoH'])?$i['TotalSysQoH']:'',
                            'TotalSysAvgCost' => isset($i['TotalSysAvgCost'])?$i['TotalSysAvgCost']:'',
                            'SysAvgCost' => isset($i['SysAvgCost'])?$i['SysAvgCost']:'',
                            'SysQoH' => isset($i['SysQoH'])?$i['SysQoH']:'',
                            'TotalSysQoHAfter' => isset($i['TotalSysQoHAfter'])?$i['TotalSysQoHAfter']:'',
                            'TotalSysAvgCostAfter' => isset($i['TotalSysAvgCostAfter'])?$i['TotalSysAvgCostAfter']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'PurTolerance_Std_plus' => isset($i['PurTolerance_Std_plus'])?$i['PurTolerance_Std_plus']:'',
                            'PurTolerance_Std_Minus' => isset($i['PurTolerance_Std_Minus'])?$i['PurTolerance_Std_Minus']:'',
                            'WeightTraceQty' => isset($i['WeightTraceQty'])?$i['WeightTraceQty']:'',
                            'WeightTraceQtyUOM' => isset($i['WeightTraceQtyUOM'])?$i['WeightTraceQtyUOM']:'',
                            'WeightTraceQtyCount' => isset($i['WeightTraceQtyCount'])?$i['WeightTraceQtyCount']:'',
                            'reason' => isset($i['reason'])?$i['reason']:'',
                            'itemtype' => isset($i['itemtype'])?$i['itemtype']:'',
                            'gst_tax_type' => isset($i['gst_tax_type'])?$i['gst_tax_type']:'',
                            'gst_tax_code' => isset($i['gst_tax_code'])?$i['gst_tax_code']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'gst_tax_amount' => isset($i['gst_tax_amount'])?$i['gst_tax_amount']:'',
                            'discvalue' => isset($i['discvalue'])?$i['discvalue']:'',
                            'ori_inv_no' => isset($i['ori_inv_no'])?$i['ori_inv_no']:'',
                            'surchg_value' => isset($i['surchg_value'])?$i['surchg_value']:'',
                            'ori_inv_date' => isset($i['ori_inv_date'])?$i['ori_inv_date']:'',
                            'itemremark' => isset($i['itemremark'])?$i['itemremark']:'',
                            'unitactcost' => isset($i['unitactcost'])?$i['unitactcost']:'',
                            'postdatetime_c' => isset($i['postdatetime_c'])?$i['postdatetime_c']:'',
                            'surchg_disc_gst' => isset($i['surchg_disc_gst'])?$i['surchg_disc_gst']:'',
                            'consign' => isset($i['consign'])?$i['consign']:'',
                            'apply_crosslink_guid' => isset($i['apply_crosslink_guid'])?$i['apply_crosslink_guid']:'',
                            'apply_cancel' => isset($i['apply_cancel'])?$i['apply_cancel']:'',
                            'apply_cancel_by' => isset($i['apply_cancel_by'])?$i['apply_cancel_by']:'',
                            'apply_cancel_at' => isset($i['apply_cancel_at'])?$i['apply_cancel_at']:'',
                            'apply_qty' => isset($i['apply_qty'])?$i['apply_qty']:'',
                            'gst_manual' => isset($i['gst_manual'])?$i['gst_manual']:'',
                            'TaxIntNo' => isset($i['TaxIntNo'])?$i['TaxIntNo']:'',
                            'TaxCodeMap' => isset($i['TaxCodeMap'])?$i['TaxCodeMap']:'',
                            'TaxValue' => isset($i['TaxValue'])?$i['TaxValue']:'',
                            'TaxAmount' => isset($i['TaxAmount'])?$i['TaxAmount']:'',
                            'TaxAmountVariance' => isset($i['TaxAmountVariance'])?$i['TaxAmountVariance']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['RefNo'].'-Line'.$i['Line'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'].'-Line'.$i['Line'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'dbnotechild',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($dbnotechild_data) > 0)
            {
                $dbnotechild_data_chunk = array_chunk($dbnotechild_data,1000);
                foreach($dbnotechild_data_chunk AS $dbnotechild_child)
                {
                    $this->db->insert_batch($hub_db.'.dbnotechild',$dbnotechild_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'dbnotechild successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'dbnotechild successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'dbnotechild failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_cnnotemain_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        // print_r($data); die;
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_cnnotemain'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $cnnotemain_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $refno = $i['refno'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('refno',$refno);
                    $this->db->update($hub_db.'.cnnotemain', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'];
                    } else{
                        $failed_refno[] =  $i['refno'];
                    }

                } else{

                    $this->db->delete($hub_db.'.cnnotemain', array('refno' => $refno)); 
                }
                
            }

            // echo $data->result();

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'RefNo doesn\'t exist.';
                    }
                    elseif(!isset($i['Location']) || $i['Location'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Location doesn\'t exist';
                    }
                    elseif(!isset($i['DocNo']) || $i['DocNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'DocNo doesn\'t exist';
                    }
                    elseif(!isset($i['PONo']) || $i['PONo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'PO No doesn\'t exist';
                    }
                    elseif(!isset($i['SCType']) || $i['SCType'] == '')
                    {
                        $status = 'Failed';
                        $message = 'SCType doesn\'t exist';
                    }
                    elseif(!isset($i['Code']) || $i['Code'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Code doesn\'t exist';
                    }
                    elseif(!isset($i['Name']) || $i['Name'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Name doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['RefNo']) || $i['RefNo'] == ''?$i['RefNo']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $cnnotemain_data[] = array(
                            'Type' => isset($i['Type'])?$i['Type']:'',
                            'RefNo' => $i['RefNo'],
                            'Location' => $i['Location'],
                            'DocNo' => $i['DocNo'],
                            'DocDate' => isset($i['DocDate'])?$i['DocDate']:'',
                            'IssueStamp' => isset($i['IssueStamp'])?$i['IssueStamp']:'',
                            'LastStamp' => isset($i['LastStamp'])?$i['LastStamp']:'',
                            'PONo' => $i['PONo'],
                            'SCType' => $i['SCType'],
                            'Code' => $i['Code'],
                            'Name' => $i['Name'],
                            'term' => isset($i['term'])?$i['term']:'',
                            'Issuedby' => isset($i['Issuedby'])?$i['Issuedby']:'',
                            'Remark' => isset($i['Remark'])?$i['Remark']:'',
                            'BillStatus' => isset($i['BillStatus'])?$i['BillStatus']:'',
                            'AccStatus' => isset($i['AccStatus'])?$i['AccStatus']:'',
                            'DueDate' => isset($i['DueDate'])?$i['DueDate']:'',
                            'Amount' => isset($i['Amount'])?$i['Amount']:'',
                            'Closed' => isset($i['Closed'])?$i['Closed']:'',
                            'postby' => isset($i['postby'])?$i['postby']:'',
                            'postdatetime' => isset($i['postdatetime'])?$i['postdatetime']:'',
                            'subdeptcode' => isset($i['subdeptcode'])?$i['subdeptcode']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'EXPORT_ACCOUNT' => isset($i['EXPORT_ACCOUNT'])?$i['EXPORT_ACCOUNT']:'',
                            'EXPORT_AT' => isset($i['EXPORT_AT'])?$i['EXPORT_AT']:'',
                            'EXPORT_BY' => isset($i['EXPORT_BY'])?$i['EXPORT_BY']:'',
                            'Consign' => isset($i['Consign'])?$i['Consign']:'',
                            'locgroup' => isset($i['locgroup'])?$i['locgroup']:'',
                            'ibt' => isset($i['ibt'])?$i['ibt']:'',
                            'SubTotal1' => isset($i['SubTotal1'])?$i['SubTotal1']:'',
                            'Discount1' => isset($i['Discount1'])?$i['Discount1']:'',
                            'Discount1Type' => isset($i['Discount1Type'])?$i['Discount1Type']:'',
                            'SubTotal2' => isset($i['SubTotal2'])?$i['SubTotal2']:'',
                            'Discount2' => isset($i['Discount2'])?$i['Discount2']:'',
                            'Discount2Type' => isset($i['Discount2Type'])?$i['Discount2Type']:'',
                            'gst_tax_sum' => isset($i['gst_tax_sum'])?$i['gst_tax_sum']:'',
                            'tax_code_purchase' => isset($i['tax_code_purchase'])?$i['tax_code_purchase']:'',
                            'sup_cn_no' => isset($i['sup_cn_no'])?$i['sup_cn_no']:'',
                            'sup_cn_date' => isset($i['sup_cn_date'])?$i['sup_cn_date']:'',
                            'refno2' => isset($i['refno2'])?$i['refno2']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'multi_tax_code' => isset($i['multi_tax_code'])?$i['multi_tax_code']:'',
                            'doc_name_reg' => isset($i['doc_name_reg'])?$i['doc_name_reg']:'',
                            'ibt_gst' => isset($i['ibt_gst'])?$i['ibt_gst']:'',
                            'gst_adj' => isset($i['gst_adj'])?$i['gst_adj']:'',
                            'rounding_adj' => isset($i['rounding_adj'])?$i['rounding_adj']:'',
                            'surchg_tax_sum' => isset($i['surchg_tax_sum'])?$i['surchg_tax_sum']:'',
                            'unpostby' => isset($i['unpostby'])?$i['unpostby']:'',
                            'unpostdatetime' => isset($i['unpostdatetime'])?$i['unpostdatetime']:'',
                            'acc_posting_date' => isset($i['acc_posting_date'])?$i['acc_posting_date']:'',
                            'RoundAdjNeed' => isset($i['RoundAdjNeed'])?$i['RoundAdjNeed']:'',
                            'uploaded' => isset($i['uploaded'])?$i['uploaded']:'',
                            'uploaded_at' => isset($i['uploaded_at'])?$i['uploaded_at']:'',
                            'm_trans_type' => isset($i['m_trans_type'])?$i['m_trans_type']:'',
                            'CONVERTED_FROM_MODULE' => isset($i['CONVERTED_FROM_MODULE'])?$i['CONVERTED_FROM_MODULE']:'',
                            'CONVERTED_FROM_GUID' => isset($i['CONVERTED_FROM_GUID'])?$i['CONVERTED_FROM_GUID']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['RefNo'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'cnnotemain',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($cnnotemain_data) > 0)
            {
                $cnnotemain_data_chunk = array_chunk($cnnotemain_data,1000);
                foreach($cnnotemain_data_chunk AS $cnnotemain_child)
                {
                    $this->db->insert_batch($hub_db.'.cnnotemain',$cnnotemain_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'cnnotemain successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'cnnotemain successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'cnnotemain failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_cnnotechild_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');

        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_cnnotechild'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $cnnotechild_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $parameter = array(
                    'RefNo' => $i['refno'],
                    'Line' => $i['line'],
                );

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $update_parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );

                    $this->db->where($parameter);
                    $this->db->update($hub_db.'.cnnotechild', $update_parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['refno'].'-Line'.$i['line'];
                    } else{
                        $failed_refno[] =  $i['refno'].'-Line'.$i['line'];
                    }

                } else{

                    $this->db->delete($hub_db.'.cnnotechild', $parameter);
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['Type']) || $i['Type'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Type doesn\'t exist.';
                    }
                    elseif(!isset($i['RefNo']) || $i['RefNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'RefNo doesn\'t exist';
                    }
                    elseif(!isset($i['Line']) || $i['Line'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Line doesn\'t exist';
                    }
                    elseif(!isset($i['Itemcode']) || $i['Itemcode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Itemcode doesn\'t exist';
                    }
                    elseif(!isset($i['Description']) || $i['Description'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Description doesn\'t exist';
                    }
                    elseif(!isset($i['ArticleNo']) || $i['ArticleNo'] == '')
                    {
                        $status = 'Failed';
                        $message = 'ArticleNo doesn\'t exist';
                    }
                    elseif(!isset($i['Dept']) || $i['Dept'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Dept doesn\'t exist';
                    }
                    elseif(!isset($i['SubDept']) || $i['SubDept'] == '')
                    {
                        $status = 'Failed';
                        $message = 'SubDept doesn\'t exist';
                    }
                    elseif(!isset($i['Category']) || $i['Category'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Category doesn\'t exist';
                    }
                    elseif(!isset($i['Barcode']) || $i['Barcode'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Barcode doesn\'t exist';
                    }
                    elseif(!isset($i['ItemLink']) || $i['ItemLink'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Item Link doesn\'t exist';
                    }
                    elseif(!isset($i['ori_inv_no']) || $i['ori_inv_no'] == '')
                    {
                        $status = 'Failed';
                        $message = 'ori_inv_no doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else{
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => (isset($i['RefNo']) && isset($i['Line'])) || $i['RefNo'] != ''?$i['RefNo'].'-Line'.$i['Line']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $cnnotechild_data[] = array(
                            'Type' => $i['Type'],
                            'RefNo' => $i['RefNo'],
                            'Line' => $i['Line'],
                            'EntryType' => isset($i['EntryType'])?$i['EntryType']:'',
                            'PriceType' => isset($i['PriceType'])?$i['PriceType']:'',
                            'Itemcode' => $i['Itemcode'],
                            'Description' => $i['Description'],
                            'Qty' => isset($i['Qty'])?$i['Qty']:'',
                            'UnitPrice' => isset($i['UnitPrice'])?$i['UnitPrice']:'',
                            'TotalPrice' => isset($i['TotalPrice'])?$i['TotalPrice']:'',
                            'Colour' => isset($i['Colour'])?$i['Colour']:'',
                            'Size' => isset($i['Size'])?$i['Size']:'',
                            'ArticleNo' => $i['ArticleNo'],
                            'PackSize' => isset($i['PackSize'])?$i['PackSize']:'',
                            'UM' => isset($i['UM'])?$i['UM']:'',
                            'Brand' => isset($i['Brand'])?$i['Brand']:'',
                            'Dept' => $i['Dept'],
                            'SubDept' => $i['SubDept'],
                            'Category' => $i['Category'],
                            'Barcode' => $i['Barcode'],
                            'WeightedAvgCost' => isset($i['WeightedAvgCost'])?$i['WeightedAvgCost']:'',
                            'ItemLink' => $i['ItemLink'],
                            'TotalSysQoH' => isset($i['TotalSysQoH'])?$i['TotalSysQoH']:'',
                            'TotalSysAvgCost' => isset($i['TotalSysAvgCost'])?$i['TotalSysAvgCost']:'',
                            'SysAvgCost' => isset($i['SysAvgCost'])?$i['SysAvgCost']:'',
                            'SysQoH' => isset($i['SysQoH'])?$i['SysQoH']:'',
                            'TotalSysQoHAfter' => isset($i['TotalSysQoHAfter'])?$i['TotalSysQoHAfter']:'',
                            'TotalSysAvgCostAfter' => isset($i['TotalSysAvgCostAfter'])?$i['TotalSysAvgCostAfter']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'PurTolerance_Std_plus' => isset($i['PurTolerance_Std_plus'])?$i['PurTolerance_Std_plus']:'',
                            'PurTolerance_Std_Minus' => isset($i['PurTolerance_Std_Minus'])?$i['PurTolerance_Std_Minus']:'',
                            'WeightTraceQty' => isset($i['WeightTraceQty'])?$i['WeightTraceQty']:'',
                            'WeightTraceQtyUOM' => isset($i['WeightTraceQtyUOM'])?$i['WeightTraceQtyUOM']:'',
                            'WeightTraceQtyCount' => isset($i['WeightTraceQtyCount'])?$i['WeightTraceQtyCount']:'',
                            'itemtype' => isset($i['itemtype'])?$i['itemtype']:'',
                            'Lastcost' => isset($i['Lastcost'])?$i['Lastcost']:'',
                            'Lastprice' => isset($i['Lastprice'])?$i['Lastprice']:'',
                            'reason' => isset($i['reason'])?$i['reason']:'',
                            'Sellingprice' => isset($i['Sellingprice'])?$i['Sellingprice']:'',
                            'stdcost' => isset($i['stdcost'])?$i['stdcost']:'',
                            'gst_tax_type' => isset($i['gst_tax_type'])?$i['gst_tax_type']:'',
                            'gst_tax_code' => isset($i['gst_tax_code'])?$i['gst_tax_code']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'gst_tax_amount' => isset($i['gst_tax_amount'])?$i['gst_tax_amount']:'',
                            'discvalue' => isset($i['discvalue'])?$i['discvalue']:'',
                            'ori_inv_no' => $i['ori_inv_no'],
                            'ori_inv_date' => isset($i['ori_inv_date'])?$i['ori_inv_date']:'',
                            'itemremark' => isset($i['itemremark'])?$i['itemremark']:'',
                            'postdatetime_c' => isset($i['postdatetime_c'])?$i['postdatetime_c']:'',
                            'surchg_value' => isset($i['surchg_value'])?$i['surchg_value']:'',
                            'unitactcost' => isset($i['unitactcost'])?$i['unitactcost']:'',
                            'surchg_disc_gst' => isset($i['surchg_disc_gst'])?$i['surchg_disc_gst']:'',
                            'consign' => isset($i['consign'])?$i['consign']:'',
                            'gst_manual' => isset($i['gst_manual'])?$i['gst_manual']:'',
                            'TaxIntNo' => isset($i['TaxIntNo'])?$i['TaxIntNo']:'',
                            'TaxCodeMap' => isset($i['TaxCodeMap'])?$i['TaxCodeMap']:'',
                            'TaxValue' => isset($i['TaxValue'])?$i['TaxValue']:'',
                            'TaxAmount' => isset($i['TaxAmount'])?$i['TaxAmount']:'',
                            'TaxAmountVariance' => isset($i['TaxAmountVariance'])?$i['TaxAmountVariance']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['RefNo'].'-Line'.$i['Line'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['RefNo'].'-Line'.$i['Line'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'cnnotechild',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($cnnotechild_data) > 0)
            {
                $cnnotechild_data_chunk = array_chunk($cnnotechild_data,1000);
                foreach($cnnotechild_data_chunk AS $cnnotechild_child)
                {
                    $this->db->insert_batch($hub_db.'.cnnotechild',$cnnotechild_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'cnnotechild successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'cnnotechild successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'cnnotechild failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_cndn_amt_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_cndn_amt'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $cndn_amt_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $cndn_guid = $i['cndn_guid'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('cndn_guid',$cndn_guid);
                    $this->db->update($hub_db.'.cndn_amt', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['cndn_guid'];
                    } else{
                        $failed_refno[] =  $i['cndn_guid'];
                    }

                } else{

                    $this->db->delete($hub_db.'.cndn_amt', array('cndn_guid' => $cndn_guid)); 
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['cndn_guid']) || $i['cndn_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'cndn guid doesn\'t exist.';
                    }
                    elseif(!isset($i['location']) || $i['location'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Location doesn\'t exist';
                    }
                    elseif(!isset($i['refno']) || $i['refno'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Reference Number doesn\'t exist';
                    }
                    elseif(!isset($i['code']) || $i['code'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Code doesn\'t exist';
                    }
                    elseif(!isset($i['name']) || $i['name'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Name doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['cndn_guid']) || $i['cndn_guid'] == ''?$i['cndn_guid']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $cndn_amt_data[] = array(
                            'cndn_guid' => $i['cndn_guid'],
                            'trans_type' => isset($i['trans_type'])?$i['trans_type']:'',
                            'loc_group' => isset($i['loc_group'])?$i['loc_group']:'',
                            'location' => $i['location'],
                            'refno' => $i['refno'],
                            'docno' => isset($i['docno'])?$i['docno']:'',
                            'docdate' => isset($i['docdate'])?$i['docdate']:'',
                            'code' => $i['code'],
                            'name' => $i['name'],
                            'tax_code' => isset($i['tax_code'])?$i['tax_code']:'',
                            'remark' => isset($i['remark'])?$i['remark']:'',
                            'term' => isset($i['term'])?$i['term']:'',
                            'amount' => isset($i['amount'])?$i['amount']:'',
                            'gst_tax_sum' => isset($i['gst_tax_sum'])?$i['gst_tax_sum']:'',
                            'amount_include_tax' => isset($i['amount_include_tax'])?$i['amount_include_tax']:'',
                            'cndn_group' => isset($i['cndn_group'])?$i['cndn_group']:'',
                            'created_at' => isset($i['created_at'])?$i['created_at']:'',
                            'created_by' => isset($i['created_by'])?$i['created_by']:'',
                            'updated_at' => isset($i['updated_at'])?$i['updated_at']:'',
                            'updated_by' => isset($i['updated_by'])?$i['updated_by']:'',
                            'posted' => isset($i['posted'])?$i['posted']:'',
                            'posted_at' => isset($i['posted_at'])?$i['posted_at']:'',
                            'posted_by' => isset($i['posted_by'])?$i['posted_by']:'',
                            'Consign' => isset($i['Consign'])?$i['Consign']:'',
                            'sup_cn_no' => isset($i['sup_cn_no'])?$i['sup_cn_no']:'',
                            'sup_cn_date' => isset($i['sup_cn_date'])?$i['sup_cn_date']:'',
                            'doc_name_reg' => isset($i['doc_name_reg'])?$i['doc_name_reg']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'multi_tax_code' => isset($i['multi_tax_code'])?$i['multi_tax_code']:'',
                            'refno2' => isset($i['refno2'])?$i['refno2']:'',
                            'gst_adj' => isset($i['gst_adj'])?$i['gst_adj']:'',
                            'rounding_adj' => isset($i['rounding_adj'])?$i['rounding_adj']:'',
                            'unpostby' => isset($i['unpostby'])?$i['unpostby']:'',
                            'unpostdatetime' => isset($i['unpostdatetime'])?$i['unpostdatetime']:'',
                            'ibt_gst' => isset($i['ibt_gst'])?$i['ibt_gst']:'',
                            'subdeptcode' => isset($i['subdeptcode'])?$i['subdeptcode']:'',
                            'EXPORT_ACCOUNT' => isset($i['EXPORT_ACCOUNT'])?$i['EXPORT_ACCOUNT']:'',
                            'EXPORT_AT' => isset($i['EXPORT_AT'])?$i['EXPORT_AT']:'',
                            'EXPORT_BY' => isset($i['EXPORT_BY'])?$i['EXPORT_BY']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'ibt' => isset($i['ibt'])?$i['ibt']:'',
                            'acc_posting_date' => isset($i['acc_posting_date'])?$i['acc_posting_date']:'',
                            'trans_type_acc' => isset($i['trans_type_acc'])?$i['trans_type_acc']:'',
                            'RoundAdjNeed' => isset($i['RoundAdjNeed'])?$i['RoundAdjNeed']:'',
                            'uploaded' => isset($i['uploaded'])?$i['uploaded']:'',
                            'uploaded_at' => isset($i['uploaded_at'])?$i['uploaded_at']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['cndn_guid'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['cndn_guid'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'cndn_amt',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($cndn_amt_data) > 0)
            {
                $cndn_amt_data_chunk = array_chunk($cndn_amt_data,1000);
                foreach($cndn_amt_data_chunk AS $cndn_amt_child)
                {
                    $this->db->insert_batch($hub_db.'.cndn_amt',$cndn_amt_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'cndn_amt successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'cndn_amt successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'cndn_amt failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_cndn_amt_c_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_cndn_amt_c'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $cndn_amt_c_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $child_guid = $i['child_guid'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('child_guid',$child_guid);
                    $this->db->update($hub_db.'.cndn_amt_c', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['child_guid'];
                    } else{
                        $failed_refno[] =  $i['child_guid'];
                    }

                } else{

                    $this->db->delete($hub_db.'.cndn_amt_c', array('child_guid' => $child_guid)); 
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['child_guid']) || $i['child_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'child guid doesn\'t exist.';
                    }
                    elseif(!isset($i['cndn_guid']) || $i['cndn_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'cndn guid doesn\'t exist';
                    }
                    elseif(!isset($i['seq']) || $i['seq'] == '')
                    {
                        $status = 'Failed';
                        $message = 'seq doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['child_guid']) || $i['child_guid'] == ''?$i['child_guid']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $cndn_amt_c_data[] = array(
                            'child_guid' => $i['child_guid'],
                            'cndn_guid' => $i['cndn_guid'],
                            'seq' => $i['seq'],
                            'itemcode' => isset($i['itemcode'])?$i['itemcode']:'',
                            'Description' => isset($i['Description'])?$i['Description']:'',
                            'Qty' => isset($i['Qty'])?$i['Qty']:'',
                            'amount_c' => isset($i['amount_c'])?$i['amount_c']:'',
                            'gst_tax_type' => isset($i['gst_tax_type'])?$i['gst_tax_type']:'',
                            'gst_tax_code' => isset($i['gst_tax_code'])?$i['gst_tax_code']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'gst_tax_amount' => isset($i['gst_tax_amount'])?$i['gst_tax_amount']:'',
                            'amount_c_include_tax' => isset($i['amount_c_include_tax'])?$i['amount_c_include_tax']:'',
                            'remark' => isset($i['remark'])?$i['remark']:'',
                            'created_at' => isset($i['created_at'])?$i['created_at']:'',
                            'created_by' => isset($i['created_by'])?$i['created_by']:'',
                            'updated_at' => isset($i['updated_at'])?$i['updated_at']:'',
                            'updated_by' => isset($i['updated_by'])?$i['updated_by']:'',
                            'Dept' => isset($i['Dept'])?$i['Dept']:'',
                            'SubDept' => isset($i['SubDept'])?$i['SubDept']:'',
                            'Category' => isset($i['Category'])?$i['Category']:'',
                            'Brand' => isset($i['Brand'])?$i['Brand']:'',
                            'Manufacturer' => isset($i['Manufacturer'])?$i['Manufacturer']:'',
                            'Barcode' => isset($i['Barcode'])?$i['Barcode']:'',
                            'reason' => isset($i['reason'])?$i['reason']:'',
                            'itemtype' => isset($i['itemtype'])?$i['itemtype']:'',
                            'ori_inv_no' => isset($i['ori_inv_no'])?$i['ori_inv_no']:'',
                            'ori_inv_date' => isset($i['ori_inv_date'])?$i['ori_inv_date']:'',
                            'postdatetime_c' => isset($i['postdatetime_c'])?$i['postdatetime_c']:'',
                            'consign' => isset($i['consign'])?$i['consign']:'',
                            'Colour' => isset($i['Colour'])?$i['Colour']:'',
                            'Size' => isset($i['Size'])?$i['Size']:'',
                            'ArticleNo' => isset($i['ArticleNo'])?$i['ArticleNo']:'',
                            'PackSize' => isset($i['PackSize'])?$i['PackSize']:'',
                            'UM' => isset($i['UM'])?$i['UM']:'',
                            'ItemLink' => isset($i['ItemLink'])?$i['ItemLink']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'UnitPrice' => isset($i['UnitPrice'])?$i['UnitPrice']:'',
                            'byAmt' => isset($i['byAmt'])?$i['byAmt']:'',
                            'itemlink_sysqoh' => isset($i['itemlink_sysqoh'])?$i['itemlink_sysqoh']:'',
                            'itemlink_syscost' => isset($i['itemlink_syscost'])?$i['itemlink_syscost']:'',
                            'itemlink_cost_new' => isset($i['itemlink_cost_new'])?$i['itemlink_cost_new']:'',
                            'averagecost' => isset($i['averagecost'])?$i['averagecost']:'',
                            'gst_manual' => isset($i['gst_manual'])?$i['gst_manual']:'',
                            'TaxIntNo' => isset($i['TaxIntNo'])?$i['TaxIntNo']:'',
                            'TaxCodeMap' => isset($i['TaxCodeMap'])?$i['TaxCodeMap']:'',
                            'TaxValue' => isset($i['TaxValue'])?$i['TaxValue']:'',
                            'TaxAmount' => isset($i['TaxAmount'])?$i['TaxAmount']:'',
                            'TaxAmountVariance' => isset($i['TaxAmountVariance'])?$i['TaxAmountVariance']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['child_guid'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['child_guid'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'cndn_amt_c',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($cndn_amt_c_data) > 0)
            {
                $cndn_amt_c_data_chunk = array_chunk($cndn_amt_c_data,1000);
                foreach($cndn_amt_c_data_chunk AS $cndn_amt_c_child)
                {
                    $this->db->insert_batch($hub_db.'.cndn_amt_c',$cndn_amt_c_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'cndn_amt_c successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'cndn_amt_c successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'cndn_amt_c failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_promo_taxinv_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_promo_taxinv'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $promo_taxinv_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $taxinv_guid = $i['taxinv_guid'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );

                    $this->db->where('taxinv_guid',$taxinv_guid);
                    $this->db->update($hub_db.'.promo_taxinv', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['taxinv_guid'];
                    } else{
                        $failed_refno[] =  $i['taxinv_guid'];
                    }

                } else{

                    $this->db->delete($hub_db.'.promo_taxinv', array('taxinv_guid' => $taxinv_guid)); 
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['taxinv_guid']) || $i['taxinv_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'taxinv_guid doesn\'t exist.';
                    }
                    elseif(!isset($i['loc_group']) || $i['loc_group'] == '')
                    {
                        $status = 'Failed';
                        $message = 'loc_group doesn\'t exist';
                    }
                    elseif(!isset($i['seq']) || $i['seq'] == '')
                    {
                        $status = 'Failed';
                        $message = 'seq doesn\'t exist';
                    }
                    // elseif(!isset($i['doc_date']) || $i['doc_date'] == '')
                    // {
                    //     $status = 'Failed';
                    //     $message = 'doc_date doesn\'t exist';
                    // }
                    elseif(!isset($i['refno']) || $i['refno'] == '')
                    {
                        $status = 'Failed';
                        $message = 'refno doesn\'t exist';
                    }
                    elseif(!isset($i['refno_line']) || $i['refno_line'] == '')
                    {
                        $status = 'Failed';
                        $message = 'refno_line doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['taxinv_guid']) || $i['taxinv_guid'] == ''?$i['taxinv_guid']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $promo_taxinv_data[] = array(
                            'taxinv_guid' => $i['taxinv_guid'],
                            'loc_group' => $i['loc_group'],
                            'loc_group_issue' => isset($i['loc_group_issue'])?$i['loc_group_issue']:'',
                            'seq' => $i['seq'],
                            'docdate' => $i['docdate'],
                            'term' => isset($i['term'])?$i['term']:'',
                            'datedue' => isset($i['datedue'])?$i['datedue']:'',
                            'tax_inclusive' => isset($i['tax_inclusive'])?$i['tax_inclusive']:'',
                            'sup_code' => isset($i['sup_code'])?$i['sup_code']:'',
                            'sup_name' => isset($i['sup_name'])?$i['sup_name']:'',
                            'total_bf_tax' => isset($i['total_bf_tax'])?$i['total_bf_tax']:'',
                            'tax_code_supply' => isset($i['tax_code_supply'])?$i['tax_code_supply']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'gst_value' => isset($i['gst_value'])?$i['gst_value']:'',
                            'total_af_tax' => isset($i['total_af_tax'])?$i['total_af_tax']:'',
                            'gst_adj' => isset($i['gst_adj'])?$i['gst_adj']:'',
                            'rounding_adj' => isset($i['rounding_adj'])?$i['rounding_adj']:'',
                            'total_net' => isset($i['total_net'])?$i['total_net']:'',
                            'remark' => isset($i['remark'])?$i['remark']:'',
                            'created_at' => isset($i['created_at'])?$i['created_at']:'',
                            'created_by' => isset($i['created_by'])?$i['created_by']:'',
                            'updated_at' => isset($i['updated_at'])?$i['updated_at']:'',
                            'updated_by' => isset($i['updated_by'])?$i['updated_by']:'',
                            'posted' => isset($i['posted'])?$i['posted']:'',
                            'posted_at' => isset($i['posted_at'])?$i['posted_at']:'',
                            'posted_by' => isset($i['posted_by'])?$i['posted_by']:'',
                            'inv_refno' => isset($i['inv_refno'])?$i['inv_refno']:'',
                            'promo_refno' => isset($i['promo_refno'])?$i['promo_refno']:'',
                            'promo_guid' => isset($i['promo_guid'])?$i['promo_guid']:'',
                            'AR_cuscode' => isset($i['AR_cuscode'])?$i['AR_cuscode']:'',
                            'gl_code' => isset($i['gl_code'])?$i['gl_code']:'',
                            'EXPORT_ACCOUNT' => isset($i['EXPORT_ACCOUNT'])?$i['EXPORT_ACCOUNT']:'',
                            'EXPORT_AT' => isset($i['EXPORT_AT'])?$i['EXPORT_AT']:'',
                            'EXPORT_BY' => isset($i['EXPORT_BY'])?$i['EXPORT_BY']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'refno' => $i['refno'],
                            'refno_line' => $i['refno_line'],
                            'uploaded' => isset($i['uploaded'])?$i['uploaded']:'',
                            'uploaded_at' => isset($i['uploaded_at'])?$i['uploaded_at']:'',
                            'issued_by_hq' => isset($i['issued_by_hq'])?$i['issued_by_hq']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['taxinv_guid'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['taxinv_guid'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'promo_taxinv',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($promo_taxinv_data) > 0)
            {
                $promo_taxinv_data_chunk = array_chunk($promo_taxinv_data,1000);
                foreach($promo_taxinv_data_chunk AS $promo_taxinv_child)
                {
                    $this->db->insert_batch($hub_db.'.promo_taxinv',$promo_taxinv_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'promo_taxinv successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'promo_taxinv successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'promo_taxinv failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_promo_taxinv_c_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_promo_taxinv_c'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $promo_taxinv_c_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $taxinv_c_guid = $i['taxinv_c_guid'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('taxinv_c_guid',$taxinv_c_guid);
                    $this->db->update($hub_db.'.promo_taxinv_c', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['taxinv_c_guid'];
                    } else{
                        $failed_refno[] =  $i['taxinv_c_guid'];
                    }

                } else{

                    $this->db->delete($hub_db.'.promo_taxinv_c', array('taxinv_c_guid' => $taxinv_c_guid)); 
                }
                
            }

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['taxinv_c_guid']) || $i['taxinv_c_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'taxinv_c_guid doesn\'t exist.';
                    }
                    elseif(!isset($i['taxinv_guid']) || $i['taxinv_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'taxinv_guid doesn\'t exist';
                    }
                    elseif(!isset($i['promo_refno']) || $i['promo_refno'] == '')
                    {
                        $status = 'Failed';
                        $message = 'promo_refno doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else{
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['taxinv_c_guid']) || $i['taxinv_c_guid'] == ''?$i['taxinv_c_guid']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $promo_taxinv_c_data[] = array(
                            'taxinv_c_guid' => $i['taxinv_c_guid'],
                            'taxinv_guid' => $i['taxinv_guid'],
                            'promo_refno' => $i['promo_refno'],
                            'seq_c' => isset($i['seq_c'])?$i['seq_c']:'',
                            'c_amt_bf_tax' => isset($i['c_amt_bf_tax'])?$i['c_amt_bf_tax']:'',
                            'c_tax_code' => isset($i['c_tax_code'])?$i['c_tax_code']:'',
                            'c_tax_rate' => isset($i['c_tax_rate'])?$i['c_tax_rate']:'',
                            'c_gst_value' => isset($i['c_gst_value'])?$i['c_gst_value']:'',
                            'c_amt_af_tax' => isset($i['c_amt_af_tax'])?$i['c_amt_af_tax']:'',
                            'c_cost_centre' => isset($i['c_cost_centre'])?$i['c_cost_centre']:'',
                            'created_at' => isset($i['created_at'])?$i['created_at']:'',
                            'created_by' => isset($i['created_by'])?$i['created_by']:'',
                            'updated_at' => isset($i['updated_at'])?$i['updated_at']:'',
                            'updated_by' => isset($i['updated_by'])?$i['updated_by']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'c_outlet' => isset($i['c_outlet'])?$i['c_outlet']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['taxinv_c_guid'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['taxinv_c_guid'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'promo_taxinv_c',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($promo_taxinv_c_data) > 0)
            {
                $promo_taxinv_c_data_chunk = array_chunk($promo_taxinv_c_data,1000);
                foreach($promo_taxinv_c_data_chunk AS $promo_taxinv_c_child)
                {
                    $this->db->insert_batch($hub_db.'.promo_taxinv_c',$promo_taxinv_c_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'promo_taxinv_c successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'promo_taxinv_c successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'promo_taxinv_c failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_discheme_taxinv_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_discheme_taxinv'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $discheme_taxinv_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $taxinv_guid = $i['taxinv_guid'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('taxinv_guid',$taxinv_guid);
                    $this->db->update($hub_db.'.discheme_taxinv', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['taxinv_guid'];
                    } else{
                        $failed_refno[] =  $i['taxinv_guid'];
                    }

                } else{

                    $this->db->delete($hub_db.'.discheme_taxinv', array('taxinv_guid' => $taxinv_guid)); 
                }
                
            }

            // echo $data->result();

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['taxinv_guid']) || $i['taxinv_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'taxinv_guid doesn\'t exist.';
                    }
                    elseif(!isset($i['loc_group']) || $i['loc_group'] == '')
                    {
                        $status = 'Failed';
                        $message = 'loc_group doesn\'t exist';
                    }
                    elseif(!isset($i['docdate']) || $i['docdate'] == '')
                    {
                        $status = 'Failed';
                        $message = 'docdate doesn\'t exist';
                    }
                    elseif(!isset($i['inv_refno']) || $i['inv_refno'] == '')
                    {
                        $status = 'Failed';
                        $message = 'inv_refno doesn\'t exist';
                    }
                    elseif(!isset($i['refno']) || $i['refno'] == '')
                    {
                        $status = 'Failed';
                        $message = 'refno doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['taxinv_guid']) || $i['taxinv_guid'] == ''?$i['taxinv_guid']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $discheme_taxinv_data[] = array(
                            'taxinv_guid' => $i['taxinv_guid'],
                            'loc_group' => $i['loc_group'],
                            'loc_group_issue' => isset($i['loc_group_issue'])?$i['loc_group_issue']:'',
                            'seq' => isset($i['seq'])?$i['seq']:'',
                            'docdate' => $i['docdate'],
                            'term' => isset($i['term'])?$i['term']:'',
                            'datedue' => isset($i['datedue'])?$i['datedue']:'',
                            'tax_inclusive' => isset($i['tax_inclusive'])?$i['tax_inclusive']:'',
                            'sup_code' => isset($i['sup_code'])?$i['sup_code']:'',
                            'sup_name' => isset($i['sup_name'])?$i['sup_name']:'',
                            'total_bf_tax' => isset($i['total_bf_tax'])?$i['total_bf_tax']:'',
                            'tax_code_supply' => isset($i['tax_code_supply'])?$i['tax_code_supply']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'gst_value' => isset($i['gst_value'])?$i['gst_value']:'',
                            'total_af_tax' => isset($i['total_af_tax'])?$i['total_af_tax']:'',
                            'gst_adj' => isset($i['gst_adj'])?$i['gst_adj']:'',
                            'rounding_adj' => isset($i['rounding_adj'])?$i['rounding_adj']:'',
                            'total_net' => isset($i['total_net'])?$i['total_net']:'',
                            'remark' => isset($i['remark'])?$i['remark']:'',
                            'created_at' => isset($i['created_at'])?$i['created_at']:'',
                            'created_by' => isset($i['created_by'])?$i['created_by']:'',
                            'updated_at' => isset($i['updated_at'])?$i['updated_at']:'',
                            'updated_by' => isset($i['updated_by'])?$i['updated_by']:'',
                            'posted' => isset($i['posted'])?$i['posted']:'',
                            'posted_at' => isset($i['posted_at'])?$i['posted_at']:'',
                            'posted_by' => isset($i['posted_by'])?$i['posted_by']:'',
                            'inv_refno' => $i['inv_refno'],
                            'refno' => $i['refno'],
                            'refno_line' => isset($i['refno_line'])?$i['refno_line']:'',
                            'AR_cuscode' => isset($i['AR_cuscode'])?$i['AR_cuscode']:'',
                            'gl_code' => isset($i['gl_code'])?$i['gl_code']:'',
                            'EXPORT_ACCOUNT' => isset($i['EXPORT_ACCOUNT'])?$i['EXPORT_ACCOUNT']:'',
                            'EXPORT_AT' => isset($i['EXPORT_AT'])?$i['EXPORT_AT']:'',
                            'EXPORT_BY' => isset($i['EXPORT_BY'])?$i['EXPORT_BY']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'uploaded' => isset($i['uploaded'])?$i['uploaded']:'',
                            'uploaded_at' => isset($i['uploaded_at'])?$i['uploaded_at']:'',
                            'division' => isset($i['division'])?$i['division']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['taxinv_guid'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['taxinv_guid'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'discheme_taxinv',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($discheme_taxinv_data) > 0)
            {
                $discheme_taxinv_data_chunk = array_chunk($discheme_taxinv_data,1000);
                foreach($discheme_taxinv_data_chunk AS $discheme_taxinv_child)
                {
                    $this->db->insert_batch($hub_db.'.discheme_taxinv',$discheme_taxinv_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'discheme_taxinv successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'discheme_taxinv successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'discheme_taxinv failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_discheme_taxinv_c_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_discheme_taxinv_c'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $discheme_taxinv_c_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $taxinv_c_guid = $i['taxinv_c_guid'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('taxinv_c_guid',$taxinv_c_guid);
                    $this->db->update($hub_db.'.discheme_taxinv_c', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['taxinv_c_guid'];
                    } else{
                        $failed_refno[] =  $i['taxinv_c_guid'];
                    }

                } else{

                    $this->db->delete($hub_db.'.discheme_taxinv_c', array('taxinv_c_guid' => $taxinv_c_guid)); 
                }
                
            }

            // echo $data->result();

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['taxinv_c_guid']) || $i['taxinv_c_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'taxinv_c_guid doesn\'t exist.';
                    }
                    elseif(!isset($i['taxinv_guid']) || $i['taxinv_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'taxinv_guid doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                    $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['taxinv_c_guid']) || $i['taxinv_c_guid'] == ''?$i['taxinv_c_guid']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $discheme_taxinv_c_data[] = array(
                            'taxinv_c_guid' => $i['taxinv_c_guid'],
                            'taxinv_guid' => $i['taxinv_guid'],
                            'seq_c' => isset($i['seq_c'])?$i['seq_c']:'',
                            'created_at' => isset($i['created_at'])?$i['created_at']:'',
                            'created_by' => isset($i['created_by'])?$i['created_by']:'',
                            'updated_at' => isset($i['updated_at'])?$i['updated_at']:'',
                            'updated_by' => isset($i['updated_by'])?$i['updated_by']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'operation' => isset($i['operation'])?$i['operation']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['taxinv_c_guid'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['taxinv_c_guid'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'discheme_taxinv_c',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($discheme_taxinv_c_data) > 0)
            {
                $discheme_taxinv_c_data_chunk = array_chunk($discheme_taxinv_c_data,1000);
                foreach($discheme_taxinv_c_data_chunk AS $discheme_taxinv_c_child)
                {
                    $this->db->insert_batch($hub_db.'.discheme_taxinv_c',$discheme_taxinv_c_child);
                    // $this->db->replace_batch($hub_db.'.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'discheme_taxinv_c successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'discheme_taxinv_c successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'discheme_taxinv_c failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_trans_surcharge_discount_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        // var_dump($data);die;
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_trans_surcharge_discount'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $discheme_taxinv_c_data = array();
            $log_child = array();

            foreach($data as $i)
            {
                $surcharge_disc_guid = $i['surcharge_disc_guid'];

                if($i['operation'] == "DELETE"){

                    $operation = $i['operation'];

                    $parameter = array(
                        'operation' => $operation,
                        'imported_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('surcharge_disc_guid',$surcharge_disc_guid);
                    $this->db->update($hub_db.'.trans_surcharge_discount', $parameter);

                    if($this->db->affected_rows() > 0){
                        $success_refno[] =  $i['surcharge_disc_guid'];
                    } else{
                        $failed_refno[] =  $i['surcharge_disc_guid'];
                    }

                } else{

                    $this->db->delete($hub_db.'.trans_surcharge_discount', array('surcharge_disc_guid' => $surcharge_disc_guid)); 
                }
                
            }

            // echo $data->result();

            foreach($data as $i)
            {
                if($i['operation'] != "DELETE"){
                    //checking
                    if(!isset($i['surcharge_disc_guid']) || $i['surcharge_disc_guid'] == '')
                    {
                        $status = 'Failed';
                        $message = 'surcharge_disc_guid doesn\'t exist.';
                    }
                    elseif(!isset($i['trans_type']) || $i['trans_type'] == '')
                    {
                        $status = 'Failed';
                        $message = 'trans_type doesn\'t exist';
                    }
                    elseif(!isset($i['code_Type']) || $i['code_Type'] == '')
                    {
                        $status = 'Failed';
                        $message = 'code_Type doesn\'t exist';
                    }
                    elseif(!isset($i['Code']) || $i['Code'] == '')
                    {
                        $status = 'Failed';
                        $message = 'Code doesn\'t exist';
                    }
                    elseif(!isset($i['refno']) || $i['refno'] == '')
                    {
                        $status = 'Failed';
                        $message = 'refno doesn\'t exist';
                    }
                    else
                    {
                        $status = 'Success';
                        $message = 'Success';
                    }
                } else {
                    $status = 'Success';
                    $message = 'Success';
                }

                if($i['operation'] != "DELETE"){

                   $log_child[] = array(
                        'line_guid' => $this->guid(),
                        'guid' => $guid,
                        'panda_refno' => '',
                        'refno' => $log_refno,
                        'line' => $line,
                        'itemcode' => isset($i['surcharge_disc_guid']) || $i['surcharge_disc_guid'] == ''?$i['surcharge_disc_guid']:'',
                        'status' => $status,
                        'message' => $message,
                        'session' => $session_guid
                    );

                    if($status == 'Success')
                    {
                        $trans_surcharge_discount_data[] = array(
                            'surcharge_disc_guid' => $i['surcharge_disc_guid'],
                            'trans_type' => $i['trans_type'],
                            'refno' => $i['refno'],
                            'sequence' => isset($i['sequence'])?$i['sequence']:'',
                            'code_Type' => $i['code_Type'],
                            'Code' => $i['Code'],
                            'Description' => isset($i['Description'])?$i['Description']:'',
                            'Calculate_by' => isset($i['Calculate_by'])?$i['Calculate_by']:'',
                            'Fixed_Value' => isset($i['Fixed_Value'])?$i['Fixed_Value']:'',
                            'surcharge_disc_type' => isset($i['surcharge_disc_type'])?$i['surcharge_disc_type']:'',
                            'surcharge_disc_value' => isset($i['surcharge_disc_value'])?$i['surcharge_disc_value']:'',
                            'system_generate' => isset($i['system_generate'])?$i['system_generate']:'',
                            'Value_Before' => isset($i['Value_Before'])?$i['Value_Before']:'',
                            'Value_Factor' => isset($i['Value_Factor'])?$i['Value_Factor']:'',
                            'Value_Calculated' => isset($i['Value_Calculated'])?$i['Value_Calculated']:'',
                            'Value_After' => isset($i['Value_After'])?$i['Value_After']:'',
                            'Remark' => isset($i['Remark'])?$i['Remark']:'',
                            'Created_at' => isset($i['Created_at'])?$i['Created_at']:'',
                            'Created_by' => isset($i['Created_by'])?$i['Created_by']:'',
                            'Updated_at' => isset($i['Updated_at'])?$i['Updated_at']:'',
                            'Updated_by' => isset($i['Updated_by'])?$i['Updated_by']:'',
                            'build_into_cost' => isset($i['build_into_cost'])?$i['build_into_cost']:'',
                            'value_variance' => isset($i['value_variance'])?$i['value_variance']:'',
                            'hq_update' => isset($i['hq_update'])?$i['hq_update']:'',
                            'dn' => isset($i['dn'])?$i['dn']:'',
                            'var_before' => isset($i['var_before'])?$i['var_before']:'',
                            'var_calculated' => isset($i['var_calculated'])?$i['var_calculated']:'',
                            'var_after' => isset($i['var_after'])?$i['var_after']:'',
                            'gst_amt' => isset($i['gst_amt'])?$i['gst_amt']:'',
                            'gst_tax_code' => isset($i['gst_tax_code'])?$i['gst_tax_code']:'',
                            'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                            'landed_cost' => isset($i['landed_cost'])?$i['landed_cost']:'',
                            'prorate_by_unit_volume' => isset($i['prorate_by_unit_volume'])?$i['prorate_by_unit_volume']:'',
                            'imported' => '1',
                            'imported_at' => $this->datetime(),
                            'exported' => '0',
                            'exported_at' => '1001-01-01 00:00:00'
                        );

                        $success_refno[] =  $i['surcharge_disc_guid'];
                    }
                    else
                    {
                        $main_status = 'Failed';
                        array_push($line_fail,$line);
                        $failed_refno[] = $i['surcharge_disc_guid'];
                    }
                    $line++;
                }
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'trans_surcharge_discount',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            if(sizeof($log_child) > 0){

                $log_child_chunk = array_chunk($log_child,1000);
                foreach($log_child_chunk AS $child)
                {
                    $this->db->insert_batch($hub_db.'.post_log_c',$child);
                }
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($discheme_taxinv_c_data) > 0)
            {
                $trans_surcharge_discount_data_chunk = array_chunk($trans_surcharge_discount_data,1000);
                foreach($trans_surcharge_discount_data_chunk AS $trans_surcharge_discount_child)
                {
                    $this->db->insert_batch($hub_db.'.trans_surcharge_discount',$trans_surcharge_discount_child);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'trans_surcharge_discount successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                if(sizeof($success_refno) > 0 && sizeof($failed_refno) == 0){
                    $response = array(
                        'status' => TRUE,
                        'message' => 'trans_surcharge_discount successfully sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );
                } else {

                    $response = array(
                        'status' => FALSE,
                        'message' => 'trans_surcharge_discount failed to sync.',
                        'success_refno' => $success_refno,
                        'failed_refno' => $failed_refno
                    );

                }
    
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }

        echo json_encode($response);
    }

    public function b2b_location_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_location'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $location_data = array();

            foreach($data as $i)
            {
                $code = $i['Code'];
                $this->db->delete($hub_db.'.location', array('Code' => $code)); 
            }

            foreach($data as $i)
            {
                //checking
                if(!isset($i['Code']) || $i['Code'] == '')
                {
                    $status = 'Failed';
                    $message = 'Code doesn\'t exist.';
                }
                elseif(!isset($i['Description']) || $i['Description'] == '')
                {
                    $status = 'Failed';
                    $message = 'Description doesn\'t exist';
                }
                elseif(!isset($i['LocGroup']) || $i['LocGroup'] == '')
                {
                    $status = 'Failed';
                    $message = 'LocGroup doesn\'t exist';
                }
                else
                {
                    $status = 'Success';
                    $message = 'Success';
                }

                $log_child[] = array(
                    'line_guid' => $this->guid(),
                    'guid' => $guid,
                    'panda_refno' => '',
                    'refno' => $log_refno,
                    'line' => $line,
                    'itemcode' => isset($i['Code']) || $i['Code'] == ''?$i['Code']:'',
                    'status' => $status,
                    'message' => $message,
                    'session' => $session_guid
                );

                if($status == 'Success')
                {
                    $location_data[] = array(
                        'Code' => $i['Code'],
                        'Description' => $i['Description'],
                        'LocGroup' => $i['LocGroup'],
                        'SalesLoc' => isset($i['SalesLoc'])?$i['SalesLoc']:'',
                        'replenishlevel' => isset($i['replenishlevel'])?$i['replenishlevel']:'',
                        'replenishqty' => isset($i['replenishqty'])?$i['replenishqty']:'',
                        'BadStock' => isset($i['BadStock'])?$i['BadStock']:'',
                        'Remark' => isset($i['Remark'])?$i['Remark']:'',
                        'loc_address' => isset($i['loc_address'])?$i['loc_address']:'',
                        'loc_tel' => isset($i['loc_tel'])?$i['loc_tel']:'',
                        'loc_fax' => isset($i['loc_fax'])?$i['loc_fax']:'',
                        'imported' => '1',
                        'imported_at' => $this->datetime(),
                        'exported' => '0',
                        'exported_at' => '1001-01-01 00:00:00'
                    );

                    $success_refno[] =  $i['Code'];
                }
                else
                {
                    $main_status = 'Failed';
                    array_push($line_fail,$line);
                    $failed_refno[] = $i['Code'];
                }
                $line++;
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'location',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            $log_child_chunk = array_chunk($log_child,1000);
            foreach($log_child_chunk AS $child)
            {
                $this->db->insert_batch($hub_db.'.post_log_c',$child);
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($location_data) > 0)
            {
                $location_data_chunk = array_chunk($location_data,1000);
                foreach($location_data_chunk AS $location_child)
                {
                    $this->db->insert_batch($hub_db.'.location',$location_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'location successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                $response = array(
                    'status' => FALSE,
                    'message' => 'location failed to sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }
        
        echo json_encode($response);
        
    }


    public function b2b_set_gst_table_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_set_gst_table'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $set_gst_table_data = array();

            foreach($data as $i)
            {
                $gst_guid = $i['gst_guid'];
                $this->db->delete($hub_db.'.set_gst_table', array('gst_guid' => $gst_guid)); 
            }

            foreach($data as $i)
            {
                //checking
                if(!isset($i['gst_guid']) || $i['gst_guid'] == '')
                {
                    $status = 'Failed';
                    $message = 'gst_guid doesn\'t exist.';
                }
                elseif(!isset($i['gst_trans_type']) || $i['gst_trans_type'] == '')
                {
                    $status = 'Failed';
                    $message = 'gst_trans_type doesn\'t exist';
                }
                elseif(!isset($i['seq']) || $i['seq'] == '')
                {
                    $status = 'Failed';
                    $message = 'seq doesn\'t exist';
                }
                else
                {
                    $status = 'Success';
                    $message = 'Success';
                }

                $log_child[] = array(
                    'line_guid' => $this->guid(),
                    'guid' => $guid,
                    'panda_refno' => '',
                    'refno' => $log_refno,
                    'line' => $line,
                    'itemcode' => isset($i['gst_guid']) || $i['gst_guid'] == ''?$i['gst_guid']:'',
                    'status' => $status,
                    'message' => $message,
                    'session' => $session_guid
                );

                if($status == 'Success')
                {
                    $set_gst_table_data[] = array(
                        'gst_guid' => $i['gst_guid'],
                        'gst_trans_type' => $i['gst_trans_type'],
                        'seq' => $i['seq'],
                        'gst_tax_type' => isset($i['gst_tax_type'])?$i['gst_tax_type']:'',
                        'set_active' => isset($i['set_active'])?$i['set_active']:'',
                        'set_default' => isset($i['set_default'])?$i['set_default']:'',
                        'gst_tax_code' => isset($i['gst_tax_code'])?$i['gst_tax_code']:'',
                        'gst_tax_rate' => isset($i['gst_tax_rate'])?$i['gst_tax_rate']:'',
                        'description' => isset($i['description'])?$i['description']:'',
                        'created_at' => isset($i['created_at'])?$i['created_at']:'',
                        'created_by' => isset($i['created_by'])?$i['created_by']:'',
                        'updated_at' => isset($i['updated_at'])?$i['updated_at']:'',
                        'updated_by' => isset($i['updated_by'])?$i['updated_by']:'',
                        'IsExempted' => isset($i['IsExempted'])?$i['IsExempted']:'',
                        'map_code' => isset($i['map_code'])?$i['map_code']:'',
                        'im_use' => isset($i['im_use'])?$i['im_use']:'',
                        'im_map_code' => isset($i['im_map_code'])?$i['im_map_code']:'',
                        'gst_type' => isset($i['gst_type'])?$i['gst_type']:'',
                        'gst_code_new' => isset($i['gst_code_new'])?$i['gst_code_new']:'',
                        'gst_code_old' => isset($i['gst_code_old'])?$i['gst_code_old']:'',
                        'effective_date' => isset($i['effective_date'])?$i['effective_date']:'',
                        'newcode_datefrom' => isset($i['newcode_datefrom'])?$i['newcode_datefrom']:'',
                        'imported' => '1',
                        'imported_at' => $this->datetime(),
                        'exported' => '0',
                        'exported_at' => '1001-01-01 00:00:00'
                    );

                    $success_refno[] =  $i['gst_guid'];
                }
                else
                {
                    $main_status = 'Failed';
                    array_push($line_fail,$line);
                    $failed_refno[] = $i['gst_guid'];
                }
                $line++;
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'set_gst_table',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            $log_child_chunk = array_chunk($log_child,1000);
            foreach($log_child_chunk AS $child)
            {
                $this->db->insert_batch($hub_db.'.post_log_c',$child);
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($set_gst_table_data) > 0)
            {
                $set_gst_table_data_chunk = array_chunk($set_gst_table_data,1000);
                foreach($set_gst_table_data_chunk AS $set_gst_table_child)
                {
                    $this->db->insert_batch($hub_db.'.set_gst_table',$set_gst_table_child);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'set_gst_table successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                $response = array(
                    'status' => FALSE,
                    'message' => 'set_gst_table failed to sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }
        
        echo json_encode($response);
        
    }


    public function b2b_xsetup_post()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $data = json_decode($this->post('data'),true);
        $customer_guid = $this->post('customer_guid');
        
        if(sizeof($data) > 0)
        {
            $hub_db = $this->db->query("SELECT b2b_hub_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_hub_database');
            // $hub_db = 'test_hub';
            $line = 1;
            $guid = $this->guid();
            $session_guid = $this->guid();
            date_default_timezone_set("Asia/Kuala_Lumpur");
            $log_refno = 'B2B_xsetup'.date('YmdHi');
            $line_fail = array();
            $success_refno = array();
            $failed_refno = array();
            $xsetup_data = array();

            foreach($data as $i)
            {
                $row_id = stripcslashes($i['row_id']);
                $this->db->delete($hub_db.'.xsetup', array('b2b_row_id' => $row_id)); 
            }

            foreach($data as $i)
            {
                //checking
                if(!isset($i['CompName']) || $i['CompName'] == '')
                {
                    $status = 'Failed';
                    $message = 'CompName doesn\'t exist.';
                }
                elseif(!isset($i['avgperiod']) || $i['avgperiod'] == '')
                {
                    $status = 'Failed';
                    $message = 'avgperiod doesn\'t exist.';
                }
                elseif(!isset($i['IPForDataBank']) || $i['IPForDataBank'] == '')
                {
                    $status = 'Failed';
                    $message = 'IPForDataBank doesn\'t exist.';
                }
                elseif(!isset($i['LocPO']) || $i['LocPO'] == '')
                {
                    $status = 'Failed';
                    $message = 'LocPO doesn\'t exist.';
                }
                elseif(!isset($i['curversion']) || $i['curversion'] == '')
                {
                    $status = 'Failed';
                    $message = 'curversion doesn\'t exist.';
                }
                elseif(!isset($i['locsi']) || $i['locsi'] == '')
                {
                    $status = 'Failed';
                    $message = 'locsi doesn\'t exist.';
                }
                elseif(!isset($i['pur_expiry_days']) || $i['pur_expiry_days'] == '')
                {
                    $status = 'Failed';
                    $message = 'pur_expiry_days doesn\'t exist.';
                }
                elseif(!isset($i['hq_database']) || $i['hq_database'] == '')
                {
                    $status = 'Failed';
                    $message = 'hq_database doesn\'t exist.';
                }
                elseif(!isset($i['hq_ip']) || $i['hq_ip'] == '')
                {
                    $status = 'Failed';
                    $message = 'hq_ip doesn\'t exist.';
                }
                else
                {
                    $status = 'Success';
                    $message = 'Success';
                }

                $log_child[] = array(
                    'line_guid' => $this->guid(),
                    'guid' => $guid,
                    'panda_refno' => '',
                    'refno' => $log_refno,
                    'line' => $line,
                    'itemcode' => isset($i['CompName']) || $i['CompName'] == ''?stripcslashes($i['CompName']):'',
                    'status' => $status,
                    'message' => $message,
                    'session' => $session_guid
                );

                if($status == 'Success')
                {
                    $xsetup_data[] = array(
                        'CompName' => $i['CompName'],
                        'SPrice2Title' => isset($i['SPrice2Title'])?$i['SPrice2Title']:'',
                        'SPrice3Title' => isset($i['SPrice3Title'])?$i['SPrice3Title']:'',
                        'SPrice4Title' => isset($i['SPrice4Title'])?$i['SPrice4Title']:'',
                        'SPrice5Title' => isset($i['SPrice5Title'])?$i['SPrice5Title']:'',
                        'avgperiod' => $i['avgperiod'],
                        'ReportDir' => isset($i['ReportDir'])?$i['ReportDir']:'',
                        'IPForDataBank' => $i['IPForDataBank'],
                        'LocPO' => $i['LocPO'],
                        'curversion' => $i['curversion'],
                        'ISDataBank' => isset($i['ISDataBank'])?$i['ISDataBank']:'',
                        'locsi' => $i['locsi'],
                        'sidebarview' => isset($i['sidebarview'])?$i['sidebarview']:'',
                        'locexcfrom' => isset($i['locexcfrom'])?$i['locexcfrom']:'',
                        'locexcto' => isset($i['locexcto'])?$i['locexcto']:'',
                        'custcode' => isset($i['custcode'])?$i['custcode']:'',
                        'custname' => isset($i['custname'])?$i['custname']:'',
                        'FTPIP' => isset($i['FTPIP'])?$i['FTPIP']:'',
                        'FTPUser' => isset($i['FTPUser'])?$i['FTPUser']:'',
                        'FTPPassword' => isset($i['FTPPassword'])?$i['FTPPassword']:'',
                        'FTPDelay' => isset($i['FTPDelay'])?$i['FTPDelay']:'',
                        'PicFolder' => isset($i['PicFolder'])?$i['PicFolder']:'',
                        'TypeOfWeight' => isset($i['TypeOfWeight'])?$i['TypeOfWeight']:'',
                        'ArticleNoAutoRun' => isset($i['ArticleNoAutoRun'])?$i['ArticleNoAutoRun']:'',
                        'ArticleNoAutoScript' => isset($i['ArticleNoAutoScript'])?$i['ArticleNoAutoScript']:'',
                        'ArticleNoNoofDigit' => isset($i['ArticleNoNoofDigit'])?$i['ArticleNoNoofDigit']:'',
                        'DefPurchaseCost' => isset($i['DefPurchaseCost'])?$i['DefPurchaseCost']:'',
                        'DefSOPrice' => isset($i['DefSOPrice'])?$i['DefSOPrice']:'',
                        'pur_expiry_days' => $i['pur_expiry_days'],
                        'hq_p_change_excludeprice' => isset($i['hq_p_change_excludeprice'])?$i['hq_p_change_excludeprice']:'',
                        'hq_database' => $i['hq_database'],
                        'hq_ip' => $i['hq_ip'],
                        'POMaxRecCount' => isset($i['POMaxRecCount'])?$i['POMaxRecCount']:'',
                        'PoRemark1' => isset($i['PoRemark1'])?$i['PoRemark1']:'',
                        'PoRemark2' => isset($i['PoRemark2'])?$i['PoRemark2']:'',
                        'PoRemark3' => isset($i['PoRemark3'])?$i['PoRemark3']:'',
                        'GRNRemark1' => isset($i['GRNRemark1'])?$i['GRNRemark1']:'',
                        'GRNRemark2' => isset($i['GRNRemark2'])?$i['GRNRemark2']:'',
                        'GRNRemark3' => isset($i['GRNRemark3'])?$i['GRNRemark3']:'',
                        'INVRemark1' => isset($i['INVRemark1'])?$i['INVRemark1']:'',
                        'INVRemark2' => isset($i['INVRemark2'])?$i['INVRemark2']:'',
                        'INVRemark3' => isset($i['INVRemark3'])?$i['INVRemark3']:'',
                        'DNRemark1' => isset($i['DNRemark1'])?$i['DNRemark1']:'',
                        'DNRemark2' => isset($i['DNRemark2'])?$i['DNRemark2']:'',
                        'DNRemark3' => isset($i['DNRemark3'])?$i['DNRemark3']:'',
                        'CNRemark1' => isset($i['CNRemark1'])?$i['CNRemark1']:'',
                        'CNRemark2' => isset($i['CNRemark2'])?$i['CNRemark2']:'',
                        'CNRemark3' => isset($i['CNRemark3'])?$i['CNRemark3']:'',
                        'SORemark1' => isset($i['SORemark1'])?$i['SORemark1']:'',
                        'SORemark2' => isset($i['SORemark2'])?$i['SORemark2']:'',
                        'SORemark3' => isset($i['SORemark3'])?$i['SORemark3']:'',
                        'DIRemark1' => isset($i['DIRemark1'])?$i['DIRemark1']:'',
                        'DIRemark2' => isset($i['DIRemark2'])?$i['DIRemark2']:'',
                        'DIRemark3' => isset($i['DIRemark3'])?$i['DIRemark3']:'',
                        'CPORemark1' => isset($i['CPORemark1'])?$i['CPORemark1']:'',
                        'CPORemark2' => isset($i['CPORemark2'])?$i['CPORemark2']:'',
                        'CPORemark3' => isset($i['CPORemark3'])?$i['CPORemark3']:'',
                        'IBTRemark1' => isset($i['IBTRemark1'])?$i['IBTRemark1']:'',
                        'IBTRemark2' => isset($i['IBTRemark2'])?$i['IBTRemark2']:'',
                        'IBTRemark3' => isset($i['IBTRemark3'])?$i['IBTRemark3']:'',
                        'grn_baseon_pocost' => isset($i['grn_baseon_pocost'])?$i['grn_baseon_pocost']:'',
                        'po_control_order_qty_by_minmax' => isset($i['po_control_order_qty_by_minmax'])?$i['po_control_order_qty_by_minmax']:'',
                        'po_saleshistory_days' => isset($i['po_saleshistory_days'])?$i['po_saleshistory_days']:'',
                        'po_saleshistory_invoice' => isset($i['po_saleshistory_invoice'])?$i['po_saleshistory_invoice']:'',
                        'po_saleshistory_pos' => isset($i['po_saleshistory_pos'])?$i['po_saleshistory_pos']:'',
                        'grn_ignore_costvariance_value' => isset($i['grn_ignore_costvariance_value'])?$i['grn_ignore_costvariance_value']:'',
                        'promo_by_tragetprice' => isset($i['promo_by_tragetprice'])?$i['promo_by_tragetprice']:'',
                        'down_same_price' => isset($i['down_same_price'])?$i['down_same_price']:'',
                        'down_same_cost' => isset($i['down_same_cost'])?$i['down_same_cost']:'',
                        'down_same_promo' => isset($i['down_same_promo'])?$i['down_same_promo']:'',
                        'GST' => isset($i['GST'])?$i['GST']:'',
                        'GST_Type' => isset($i['GST_Type'])?$i['GST_Type']:'',
                        'GST_Value' => isset($i['GST_Value'])?$i['GST_Value']:'',
                        'CutOffDate' => isset($i['CutOffDate'])?$i['CutOffDate']:'',
                        'run_format_year' => isset($i['run_format_year'])?$i['run_format_year']:'',
                        'run_format_month' => isset($i['run_format_month'])?$i['run_format_month']:'',
                        'alert_timer_min' => isset($i['alert_timer_min'])?$i['alert_timer_min']:'',
                        'alert_hide_after_sec' => isset($i['alert_hide_after_sec'])?$i['alert_hide_after_sec']:'',
                        'item_insert_blank' => isset($i['item_insert_blank'])?$i['item_insert_blank']:'',
                        'blocked_gr_greater_pocost' => isset($i['blocked_gr_greater_pocost'])?$i['blocked_gr_greater_pocost']:'',
                        'adjust_allow_chg_cost' => isset($i['adjust_allow_chg_cost'])?$i['adjust_allow_chg_cost']:'',
                        'grn_last_price_for_itemmaster' => isset($i['grn_last_price_for_itemmaster'])?$i['grn_last_price_for_itemmaster']:'',
                        'grn_by_weight' => isset($i['grn_by_weight'])?$i['grn_by_weight']:'',
                        'grn_by_weight_recqty_aspoqty' => isset($i['grn_by_weight_recqty_aspoqty'])?$i['grn_by_weight_recqty_aspoqty']:'',
                        'grn_by_weight_doqty_aspoqty' => isset($i['grn_by_weight_doqty_aspoqty'])?$i['grn_by_weight_doqty_aspoqty']:'',
                        'grn_by_weight_hide_po_info' => isset($i['grn_by_weight_hide_po_info'])?$i['grn_by_weight_hide_po_info']:'',
                        'grn_by_weight_hide_supplier_do_entry' => isset($i['grn_by_weight_hide_supplier_do_entry'])?$i['grn_by_weight_hide_supplier_do_entry']:'',
                        'grn_by_weight_recbyweight' => isset($i['grn_by_weight_recbyweight'])?$i['grn_by_weight_recbyweight']:'',
                        'digit_dept' => isset($i['digit_dept'])?$i['digit_dept']:'',
                        'digit_subdept' => isset($i['digit_subdept'])?$i['digit_subdept']:'',
                        'digit_cat' => isset($i['digit_cat'])?$i['digit_cat']:'',
                        'hq_create_normal_po' => isset($i['hq_create_normal_po'])?$i['hq_create_normal_po']:'',
                        'sales_start_date' => isset($i['sales_start_date'])?$i['sales_start_date']:'',
                        'tax_code_supplier' => isset($i['tax_code_supplier'])?$i['tax_code_supplier']:'',
                        'tax_code_customer' => isset($i['tax_code_customer'])?$i['tax_code_customer']:'',
                        'tax_code_item_purchase' => isset($i['tax_code_item_purchase'])?$i['tax_code_item_purchase']:'',
                        'tax_code_item_supply' => isset($i['tax_code_item_supply'])?$i['tax_code_item_supply']:'',
                        'web_url' => isset($i['web_url'])?$i['web_url']:'',
                        'grn_web_link' => isset($i['grn_web_link'])?$i['grn_web_link']:'',
                        'price_change_time' => isset($i['price_change_time'])?$i['price_change_time']:'',
                        'grn_by_weight_direct_post_grn' => isset($i['grn_by_weight_direct_post_grn'])?$i['grn_by_weight_direct_post_grn']:'',
                        'arrive_earlier_po' => isset($i['arrive_earlier_po'])?$i['arrive_earlier_po']:'',
                        'decode_receiving_barcode' => isset($i['decode_receiving_barcode'])?$i['decode_receiving_barcode']:'',
                        'grn_by_weight_hide_inv_detail' => isset($i['grn_by_weight_hide_inv_detail'])?$i['grn_by_weight_hide_inv_detail']:'',
                        'check_high_shrink' => isset($i['check_high_shrink'])?$i['check_high_shrink']:'',
                        'check_dcpick_looseitem' => isset($i['check_dcpick_looseitem'])?$i['check_dcpick_looseitem']:'',
                        'grnbyweight_send_print' => isset($i['grnbyweight_send_print'])?$i['grnbyweight_send_print']:'',
                        'allow_change_receiving_date' => isset($i['allow_change_receiving_date'])?$i['allow_change_receiving_date']:'',
                        'allow_chinese_character' => isset($i['allow_chinese_character'])?$i['allow_chinese_character']:'',
                        'requery_after_insert' => isset($i['requery_after_insert'])?$i['requery_after_insert']:'',
                        'gst_amend_date' => isset($i['gst_amend_date'])?$i['gst_amend_date']:'',
                        'check_high_shrink_by_outlet' => isset($i['check_high_shrink_by_outlet'])?$i['check_high_shrink_by_outlet']:'',
                        'check_active_po' => isset($i['check_active_po'])?$i['check_active_po']:'',
                        'check_scan_method' => isset($i['check_scan_method'])?$i['check_scan_method']:'',
                        'auth_goods' => isset($i['auth_goods'])?$i['auth_goods']:'',
                        'hide_ibt_receive_qty' => isset($i['hide_ibt_receive_qty'])?$i['hide_ibt_receive_qty']:'',
                        'hide_ibt_nett_price' => isset($i['hide_ibt_nett_price'])?$i['hide_ibt_nett_price']:'',
                        'multiple_ibt_receive' => isset($i['multiple_ibt_receive'])?$i['multiple_ibt_receive']:'',
                        'full_ibt_receive' => isset($i['full_ibt_receive'])?$i['full_ibt_receive']:'',
                        'receiving_via_password' => isset($i['receiving_via_password'])?$i['receiving_via_password']:'',
                        'sst_date' => isset($i['sst_date'])?$i['sst_date']:'',
                        'gst_tax_receive' => isset($i['gst_tax_receive'])?$i['gst_tax_receive']:'',
                        'xbridge_return_method' => isset($i['xbridge_return_method'])?$i['xbridge_return_method']:'',
                        'grwpo_show_option' => isset($i['grwpo_show_option'])?$i['grwpo_show_option']:'',
                        'grwpo_show_rtv' => isset($i['grwpo_show_rtv'])?$i['grwpo_show_rtv']:'',
                        'grwpo_hide_gr' => isset($i['grwpo_hide_gr'])?$i['grwpo_hide_gr']:'',
                        'grwpo_hide_sur_disc' => isset($i['grwpo_hide_sur_disc'])?$i['grwpo_hide_sur_disc']:'',
                        'private_key' => isset($i['private_key'])?$i['private_key']:'',
                        'grace_date' => isset($i['grace_date'])?$i['grace_date']:'',
                        'return_date' => isset($i['return_date'])?$i['return_date']:'',
                        'wms_integrate' => isset($i['wms_integrate'])?$i['wms_integrate']:'',
                        'ibt_return_confirm_by_receiver' => isset($i['ibt_return_confirm_by_receiver'])?$i['ibt_return_confirm_by_receiver']:'',
                        'prelist_multiline' => isset($i['prelist_multiline'])?$i['prelist_multiline']:'',
                        'ibt_central_view' => isset($i['ibt_central_view'])?$i['ibt_central_view']:'',
                        'grnbw_auto_hide_inv_cost' => isset($i['grnbw_auto_hide_inv_cost'])?$i['grnbw_auto_hide_inv_cost']:'',
                        'grn_ignore_totalcostvariance_value' => isset($i['grn_ignore_totalcostvariance_value'])?$i['grn_ignore_totalcostvariance_value']:'',
                        'import_stocktake_id' => isset($i['import_stocktake_id'])?$i['import_stocktake_id']:'',
                        'import_excel_stock_take_prelisting' => isset($i['import_excel_stock_take_prelisting'])?$i['import_excel_stock_take_prelisting']:'',
                        'show_image_po' => isset($i['show_image_po'])?$i['show_image_po']:'',
                        'show_image_stk' => isset($i['show_image_stk'])?$i['show_image_stk']:'',
                        'show_image_sku' => isset($i['show_image_sku'])?$i['show_image_sku']:'',
                        'auto_print_qr' => isset($i['auto_print_qr'])?$i['auto_print_qr']:'',
                        'stktake_pre_multi_item' => isset($i['stktake_pre_multi_item'])?$i['stktake_pre_multi_item']:'',
                        'b2b_row_id' => isset($i['row_id'])?$i['row_id']:'',
                        'imported' => '1',
                        'imported_at' => $this->datetime(),
                        'exported' => '0',
                        'exported_at' => '1001-01-01 00:00:00'
                    );

                    $success_refno[] =  $i['CompName'].'-B2B_ID'.$i['row_id'];
                }
                else
                {
                    $main_status = 'Failed';
                    array_push($line_fail,$line);
                    $failed_refno[] =  $i['CompName'].'-B2B_ID'.$i['row_id'];
                }
                $line++;
            }

            if(sizeof($line_fail) > 0)
            {
                $line_fail = implode(", ",$line_fail);
                $main_message = 'Failed On Line '.$line_fail;
            }
            else
            {
                $main_status = 'Success';
                $main_message = 'Success';
            }

            $log_main = array(
                'whs_code' => 'B2B',
                'owner_code' => 'B2B',
                'guid' => $guid,
                'module' => 'xsetup',
                'type' => 'GET',
                'refno' => $log_refno,
                'panda_refno' => '',
                'status' => $main_status,
                'message' => $main_message,
                'session' => $session_guid,
                'date' => $this->date(),
                'datetime' => $this->datetime(),
                'post_data' => $this->post('data')
            );

            $log_child_chunk = array_chunk($log_child,1000);
            foreach($log_child_chunk AS $child)
            {
                $this->db->insert_batch($hub_db.'.post_log_c',$child);
            }

            $this->db->insert($hub_db.'.post_log',$log_main);

            if(sizeof($xsetup_data) > 0)
            {
                $xsetup_data_chunk = array_chunk($xsetup_data,1000);
                foreach($xsetup_data_chunk AS $xsetup_child)
                {
                    $this->db->insert_batch($hub_db.'.xsetup',$xsetup_child);
                    // $this->db->replace_batch('rest_hub.itembarcode',$data_barcode);
                }

                $response = array(
                    'status' => TRUE,
                    'message' => 'xsetup successfully sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
            else
            {
                $response = array(
                    'status' => FALSE,
                    'message' => 'xsetup failed to sync.',
                    'success_refno' => $success_refno,
                    'failed_refno' => $failed_refno
                );
            }
        }
        else
        {
            $response = array(
                'status' => false,
                'message' => 'No data feed.'
            );
        }
        
        echo json_encode($response);
        
    }

}
?>
