<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Get_model extends CI_Model
{
  
    public function __construct()
	{
		parent::__construct();
	}


    public function check_config($code)
    {
        $query = $this->db->query("SELECT * FROM rest_hub.`config` a WHERE a.code = '$code'; ");
        return $query; 
    }

    public function check_limit($code)
    {
        $query = $this->db->query("SELECT IF(a.`enable` = '1',a.`limit`,'1') AS query_limit FROM rest_hub.`set_limit_query` a WHERE a.`doc_code` = '$code'; ");
        return $query; 
    }

    public function location()
    {
        $result = $this->db->query("SELECT * FROM rest_hub.`location` a ;");
        return $result;
    }

    public function supcus_address($Code)
    {
        $result = $this->db->query("SELECT CONCAT(a.`Add1`,a.`Add2`,a.`Add3`,a.`Add4`) AS address FROM rest_hub.supcus a WHERE a.`Code` = '$Code'");
        return $result;
    }

    public function dnbatch($key)
    {
        if($key == '')/*parameter key not pass*/
        {
            $key = "a.`exported` = 0";
        }
        else
        {
            $key = "a.`batch_no` = '$key'";
        }

        if($this->check_config('LIMIT_CTRL')->row('enable') == '1')// if query control limit
        {
            $limit = $this->check_limit('dnbatch')->row('query_limit');// check based on pre set table
        }
        else
        {
            $limit = "1";// put default limit
        }


        $result = $this->db->query("SELECT * FROM rest_hub.`dbnote_batch` a WHERE ".$key." ");
       // echo $this->db->last_query();die;
        return $result;
    }

    public function dnbatch_child($dbnote_guid)
    {

        $result = $this->db->query("SELECT 
              a.`itemcode`,
              a.`itemlink`,
              a.`description`,
              a.`packsize`,
              a.`qty`,
              a.`um`,
              a.`AverageCost`,
              a.`SellingPrice`,
              a.`lastcost`,
              a.`subdept`,
              a.`dept`,
              a.`category`,
              a.`reason`,
              a.`scan_barcode` 
            FROM
              rest_hub.`dbnote_batch_c` a 
            WHERE a.`dbnote_guid` = '$dbnote_guid' ;");
        return $result;
    }

    public function DeleteXml($type,$data)
    {
        ini_set("default_socket_timeout", 120);
        ini_set('max_execution_time','0');
        ini_set('memory_limit', '-1'); // Unlimited RAM
        $client = new SOAPClient($this->wsdl_url,array('soap_version' => SOAP_1_1,'trace' => 1,'connection_timeout' => 120));
        
        // var_dump($client);die;
        // $result = $client->$type(array($colname=>$colvalue));
        try {
            $result = $client->$type($data);
        } catch (SoapFault $e) {
            var_dump($client);die;
        }
        // var_dump($client);die;
        $object = $type.'Result';
        
        if(isset($result->$object))
        {
            return array('array' => simplexml_load_string($result->$object),'xml' => $result->$object);
        }
        else
        {
            return array('status'=>false,'message'=>'Nothing to delete/ delete error.');
        }
    }

    public function GetXml($type,$colname = null,$colvalue = null)
    {
        ini_set("default_socket_timeout", 120);
        ini_set('max_execution_time','0');
        ini_set('memory_limit', '-1'); // Unlimited RAM
        $client = new SOAPClient($this->wsdl_url,array('soap_version' => SOAP_1_1,'trace' => 1,'connection_timeout' => 120));
        
        if($colname == null || $colvalue == null)
        {
            $result = $client->$type();
        }
        else
        {
            // var_dump($client);die;
            // $result = $client->$type(array($colname=>$colvalue));
            try {
                $result = $client->$type(array($colname=>$colvalue));
            } catch (SoapFault $e) {
                var_dump($client);die;
            }
        }
        // var_dump($client);die;
        $object = $type.'Result';
        
        if(isset($result->$object))
        {
            return array('array' => simplexml_load_string($result->$object),'xml' => $result->$object);
        }
        else
        {
            return array('status'=>false,'message'=>'Nothing to update.');
        }
    }

    public function PostXml($type,$data,$child_data = null)
    {
        $client = new SOAPClient($this->wsdl_url);

        if($child_data == null)
        {
            $databyte = array(
                'bytes' => $data
            );
        }
        else
        {
            $databyte = array(
                'Headerbytes' => $data,
                'Detailbytes' => $child_data
            );
        }

        $result = $client->$type($databyte);
        $object = $type.'Result';
        
        return $result->$object;
    }

    //this function is different from others as rest hub price change only store trans guid
    public function getPriceChange($refno)
    {
        $result1 = array();
        foreach($refno as $val)
        {
            $result = $this->db->query("SELECT a.RefNo,b.itemcode,b.`Approved_Sell` AS SellingPrice, b.`MinPrice` AS MinimumPrice, 
            MAX(CASE WHEN c.CardType = 'MEMBER' 
                THEN ROUND(IF(c.Disc2Type='%',IF(c.Disc1Type='%',b.`Approved_Sell`*(1-c.Approved_Disc1Value/100),b.`Approved_Sell`-c.Approved_Disc1Value)*(1-c.Approved_Disc2Value/100),
            IF(c.Disc1Type='%',b.`Approved_Sell`*(1-c.Approved_Disc1Value/100),b.`Approved_Sell`-c.Approved_Disc1Value)-c.Approved_Disc2Value),2) ELSE 0 END) AS MEMBER,
            MAX(CASE WHEN c.CardType = 'WHOLESALE' 
                THEN ROUND(IF(c.Disc2Type='%',IF(c.Disc1Type='%',b.`Approved_Sell`*(1-c.Approved_Disc1Value/100),b.`Approved_Sell`-c.Approved_Disc1Value)*(1-c.Approved_Disc2Value/100),
            IF(c.Disc1Type='%',b.`Approved_Sell`*(1-c.Approved_Disc1Value/100),b.`Approved_Sell`-c.Approved_Disc1Value)-c.Approved_Disc2Value),2) ELSE 0 END) AS WHOLESALE,
            MAX(CASE WHEN c.CardType = 'CORPORATE' 
                THEN ROUND(IF(c.Disc2Type='%',IF(c.Disc1Type='%',b.`Approved_Sell`*(1-c.Approved_Disc1Value/100),b.`Approved_Sell`-c.Approved_Disc1Value)*(1-c.Approved_Disc2Value/100),
            IF(c.Disc1Type='%',b.`Approved_Sell`*(1-c.Approved_Disc1Value/100),b.`Approved_Sell`-c.Approved_Disc1Value)-c.Approved_Disc2Value),2) ELSE 0 END) AS CORPORATE
            FROM backend.price_change_req a LEFT JOIN backend.price_change_req2item b ON a.`TRANS_GUID` = b.`TRANS_GUID` 
            LEFT JOIN backend.price_change_req4disc c ON b.`CHILD_GUID` = c.`CHILD_GUID` WHERE a.`RefNo` = '".$val['RefNo']."' GROUP BY b.`CHILD_GUID`;")->result_array();
            
            $result1 = array_merge($result1,$result);
        }

        $run_no = 0;
        date_default_timezone_set("Asia/Kuala_Lumpur");
        //rearrange to meet the data dictionary of SAP
        foreach($result1 as $key => $val)
        {
            $final_result[$run_no]['ListNum'] = '6';
            $final_result[$run_no]['ItemCode'] = $val['itemcode'];
            $final_result[$run_no]['Price'] = $val['CORPORATE'];
            $final_result[$run_no]['ChangeDate'] = date('YmdHis');
            $run_no++;
            $final_result[$run_no]['ListNum'] = '3';
            $final_result[$run_no]['ItemCode'] = $val['itemcode'];
            $final_result[$run_no]['Price'] = $val['MEMBER'];
            $final_result[$run_no]['ChangeDate'] = date('YmdHis');
            $run_no++;
            $final_result[$run_no]['ListNum'] = '4';
            $final_result[$run_no]['ItemCode'] = $val['itemcode'];
            $final_result[$run_no]['Price'] = $val['WHOLESALE'];
            $final_result[$run_no]['ChangeDate'] = date('YmdHis');
            $run_no++;
            $final_result[$run_no]['ListNum'] = '2';
            $final_result[$run_no]['ItemCode'] = $val['itemcode'];
            $final_result[$run_no]['Price'] = $val['SellingPrice'];
            $final_result[$run_no]['ChangeDate'] = date('YmdHis');
            $run_no++;
            $final_result[$run_no]['ListNum'] = '5';
            $final_result[$run_no]['ItemCode'] = $val['itemcode'];
            $final_result[$run_no]['Price'] = $val['MinimumPrice'];
            $final_result[$run_no]['ChangeDate'] = date('YmdHis');
            $run_no++;
        }

        return $final_result;
    }

    public function getSalesQty()
    {
        $result = $this->db->query("SELECT RefNo DocNum,location OutletID,DocDate,itemcode ItemCode,SoldQty,remarks Remarks FROM rest_hub.sales_qty WHERE exported = '0' AND SoldQty > '0';")->result_array();

        return $result;
    }

    public function getReturnQty()
    {
        $result = $this->db->query("SELECT RefNo DocNum,location OutletID,DocDate,itemcode ItemCode,SoldQty,remarks Remarks FROM rest_hub.sales_qty WHERE exported = '0' AND SoldQty < '0';")->result_array();

        return $result;
    }

    public function getSuggestOrderQty()
    {
        $result = $this->db->query("SELECT RefNo DocNum,location OutletID,SCode CardCode,PODate DocDate,'0' DocTotal,Remark Remarks FROM rest_hub.pomain WHERE exported = '0' ORDER BY RefNo")->result_array();

        return $result;
    }

    public function getSuggestOrderQtyChild()
    {
        $result = $this->db->query("SELECT a.RefNo DocNum,a.location OutletID,Line RowID,itemcode ItemCode,Description ItemName,Qty Quantity,'0' Price,'0' TotalBefTax,'0' LineTotal,'0' LineTax,'-' VatGroup,a.DeliverDate DelDate FROM rest_hub.pomain a INNER JOIN rest_hub.pochild b ON a.RefNo = b.RefNo WHERE a.exported = '0' ORDER BY a.RefNo")->result_array();

        return $result;
    }
    
    public function getSalesHeader()
    {
        $result = $this->db->query("SELECT RefNo DocNum,location OutletID,DocDate,total_sales DocTotal,total_payment PayTotal,remarks Remarks FROM rest_hub.sales_main WHERE exported = '0' ORDER BY RefNo")->result_array();

        return $result;
    }

    public function getSalesChild()
    {
        $result = $this->db->query("SELECT RefNo DocNum,location OutletID,line RowID,line_desc RowDescription,amt_exc_tax TotalBefTax,tax_amount LineTax,amt_inc_tax LineTotal,tax_code VatGroup FROM rest_hub.sales_child WHERE exported = '0' ORDER BY RefNo")->result_array();

        return $result;
    }

    public function getPayment()
    {
        $result = $this->db->query("SELECT RefNo DocNum,location OutletID,line RowID,PayType,PayAmt FROM rest_hub.sales_payment WHERE exported = '0' ORDER BY RefNo")->result_array();

        return $result;
    }

    public function getCashDeposit()
    {
        $result = $this->db->query("SELECT docno AS DocNum
        , location AS OutletID
        , bizdate AS DocDate
        , SUM(amount) AS Amount
        , LEFT(GROUP_CONCAT(CONCAT(DATE_FORMAT(trans_date,'%m%d'),'/',amount) SEPARATOR ','),50) AS Remarks
        , debit_acc AS BankDetail
        FROM panda_cm.`actual_amount`
        WHERE doctype IN ('SETTLEMENT','SCS') 
        AND `status` = '1' AND Amount != '0' GROUP BY docno ORDER BY bizdate ASC LIMIT 10000")->result_array();

        return $result;
    }
}

?> 
