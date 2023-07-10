<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class lite_b2b_model_v2 extends CI_Model
{
    public $tb_lite_b2b = 'lite_b2b';
    public $tb_lite_b2b_apps = 'lite_b2b_apps';
    public $module_group_guid = '6595A39AD4AE11E7861FA81E8453CCF0';
    
    public function __construct()
	{
		parent::__construct();
	}

    function dashboard($db,$location_in,$scode_in,$scode_where,$dashboard_num_days_data)
    {
        if ($scode_where == 1) {
            $scode_where = 'AND scode IN ('.$scode_in.')';
            $code_where = 'AND code IN ('.$scode_in.')';
        } else {
            $scode_where = '';
            $code_where = '';
        }

            $check_outstanding_pomain = $this->db->query("
              SELECT 
              count(refno) as count_doc, 
              'PO' as type,
              'Purchase Order' as display_name, 
              '#00c0ef' AS background_color,
              'dashboard/PO.png' AS icon

              FROM $db.pomain WHERE status = '' AND loc_group IN ($location_in) $scode_where AND podate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()");

            $check_grn =  $this->db->query("SELECT 
              COUNT(refno) AS count_doc, 
              'GRN' as type,
              'Goods Received' as display_name, 
              '#00a65a' AS background_color,
              'dashboard/GRN.png' AS icon

              FROM $db.grmain WHERE status = '' AND loc_group IN ($location_in) $code_where AND grdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE() ");

            $check_grda =  $this->db->query("SELECT count(a.refno) as count_doc,
             'GRDA' as type,
             'GR Difference Advice' as display_name,
             '#f39c12' AS background_color,
             'dashboard/GRDA.png' AS icon

             FROM $db.grmain AS a
              INNER JOIN  (SELECT * FROM $db.grmain_dncn GROUP BY refno) AS b
              ON a.refno = b.refno

             WHERE a.loc_group IN ($location_in) $code_where AND a.grdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

            $prdncn =  $this->db->query("SELECT 
              COUNT(refno) AS count_doc,
              'PRDNCN' as type,
              'Purchase Return DN/CN' as display_name,
              '#dd4b39' AS background_color,
              'dashboard/PRDNCN.png' AS icon

            FROM
              $db.dbnotemain 
            WHERE location IN ($location_in)
              $code_where 
              AND DocDate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE() 

              UNION ALL

              SELECT 
              COUNT(refno) AS count_doc,
              'PRDNCN' as type,
              'Purchase Return DN/CN' as display_name,
              '#dd4b39' AS background_color,
              'dashboard/PRDNCN.png' AS icon

            FROM
              $db.cnnotemain 
            WHERE location IN ($location_in)
              $code_where 
              AND DocDate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE() ;

              ");

            $pdncn =  $this->db->query("SELECT count(refno) as count_doc,
             'PDNCN' as type,
             'Purchase DN/CN' as display_name,
             '#0b738c' AS background_color,
             'dashboard/PDNCN.png' AS icon

             FROM $db.cndn_amt

              WHERE location IN ($location_in)
              $code_where 
              AND docdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

            $pci =  $this->db->query("SELECT count(refno) as count_doc,
             'PCI' as type,
             'Promotion Claim Tax Invoice' as display_name,
             '#36815f' AS background_color,
             'dashboard/PCI.png' AS icon

             FROM $db.promo_taxinv

              WHERE loc_group IN ($location_in)
              $code_where 
              AND docdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

            $dii =  $this->db->query("SELECT count(refno) as count_doc,
             'DII' as type,
             'Display Incentive Tax Invoice' as display_name,
             '#aa6e0e' AS background_color,
             'dashboard/DII.png' AS icon

             FROM $db.discheme_taxinv

              WHERE loc_group IN ($location_in)
              $code_where 
              AND docdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

            $no_respond =  $this->db->query("SELECT count(refno) as count_doc,
             'PO' as type,
             'No Respond PO' as display_name,
             '#dd4b39' AS background_color,
             '' AS icon

             FROM $db.pomain where status = '' AND loc_group in ($location_in) $scode_where AND podate < CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE() ");

            $data = array(

                 $check_outstanding_pomain->result()[0],
                 $check_grn->result()[0],
                 $check_grda->result()[0],
                 $prdncn->result()[0],
                 $pdncn->result()[0],
                 $pci->result()[0],
                 $dii->result()[0],
                 $no_respond->result()[0],

            );

        return $data;
    }

    function po_list($db,$location_in,$scode_in,$scode_where,$status_in,$refno,$period_code,$date_from,$date_to,$exp_from,$exp_to,$status_where,$limit,$offset)
    {

        if ($refno == '') {
          $refno_where = 'WHERE refno IS NOT NULL ';
        } else {
          $refno_where = "WHERE refno LIKE '%".$refno."%'";
        }

        if ($scode_where == 1) {
          $scode_where = 'AND scode IN ('.$scode_in.')';
        } else {
          $scode_where = '';
        }

        if ($period_code == '') {
          $period_code_where = '';
        } else {
          $period_code_where = "AND left(podate,7)  = '".$period_code."'";
        }

        if($date_from == '' && $date_to == '') {
          $date_where = "";
        } else if($date_from == '') {
          $date_where = "AND DATE_FORMAT(podate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '".$date_to."'";
        } else if($date_to == '') {
          $date_where = "AND DATE_FORMAT(podate, '%Y-%m-%d') BETWEEN '".$date_from."' AND '3000-01-01'";
        } else {
          $date_where = "AND DATE_FORMAT(podate, '%Y-%m-%d') BETWEEN '".$date_from."' AND '".$date_to."'";
        }

        if($exp_from == '' && $exp_to == '') {
          $exp_where = "";
        } else if($exp_from == '') {
          $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '1900-01-01' AND '".$exp_to."'";
        } else if($exp_to == '') {
          $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '".$exp_from."' AND '3000-01-01'";
        } else {
          $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '".$exp_from."' AND '".$exp_to."'";
        }

        if ($status_where == 1) {
          $status_in = 'AND status IN ('.$status_in.')';
        } else {
          $status_in = '';
        }

        $limit = "LIMIT ".$limit." OFFSET ".$offset;

        $query = $this->db->query("SELECT
         a.*,
         c.reason AS status,
         d.description AS preview_description
      FROM
         (
            SELECT
               scode,
               sname,
               refno,
               loc_group,
               DATE_FORMAT(podate, '%Y-%m-%d %a') AS doc_date,
               DATE_FORMAT(DeliverDate, '%Y-%m-%d %a') AS deliver_date,
               IF(expiry_date IS NULL OR expiry_date = '0000-00-00' , '', DATE_FORMAT(expiry_date, '%Y-%m-%d %a') ) AS  expiry_date,
               '' AS doc_type,
               status AS doc_status,
               FORMAT(total_include_tax, 2) AS amount,
               IF(tax_code_purchase = '' , '0' , '1' ) AS include_tax,
               IF(status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
               IF(status = '', '#7fbfe1', IF(status IN ('viewed', 'printed'), '#048ad1', IF(status IN ('expired', 'cancel','rejected'), '#ff4e25',  IF(status IN ('accepted', 'gr_completed'), '#00c92b', '#000000' ) ) ) ) AS color,
               IF(status = '', '1', '0' ) AS ispreview,
               rejected_remark
            FROM
               $db.pomain 

               $refno_where
               $scode_where
               $status_in
               $period_code_where
               $date_where
               $exp_where
               AND loc_group IN 
               (
                 $location_in
               )
         )
         a 
         LEFT JOIN
            ".$this->tb_lite_b2b.".status_setting b 
            ON a.rejected_remark = b.code 
            AND b.type = 'reject_po'
          LEFT JOIN
            lite_b2b.set_setting c
            ON a.doc_status = c.code
            AND c.module_name = 'PO_FILTER_STATUS'
            INNER JOIN
            $db.pochild d
            ON a.RefNo = d.Refno
            AND d.line = '1'
            ORDER BY a.doc_date DESC

            $limit

            ");

        return $query;
    }

    function gr_list($db,$location_in,$scode_in,$scode_where,$status_in,$refno,$period_code,$date_from,$date_to,$exp_from,$exp_to,$status_where,$limit,$offset)
    {
        if ($refno == '') {
          $refno_where = '';
        } else {
          $refno_where = "AND a.refno LIKE '%".$refno."%'";
        }

        if ($scode_where == 1) {
          $scode_where = 'AND a.code IN ('.$scode_in.')';
        } else {
          $scode_where = '';
        }

        if ($period_code == '') {
          $period_code_where = '';
        } else {
          $period_code_where = "AND left(a.grdate,7)  = '".$period_code."'";
        }

        if($date_from == '' && $date_to == '') {
          $date_where = "";
        } else if($date_from == '') {
          $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } else if($date_to == '') {
          $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
          $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        //passing name temporary is $exp_where
        if($exp_from == '' && $exp_to == '') {
          $exp_where = "";
        } else if($exp_from == '') {
          $exp_where = "AND DATE_FORMAT(a.docdate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '".$exp_to."'";
        } else if($exp_to == '') {
          $exp_where = "AND DATE_FORMAT(a.docdate, '%Y-%m-%d') BETWEEN '".$exp_from."' AND '3000-01-01'";
        } else {
          $exp_where = "AND DATE_FORMAT(a.docdate, '%Y-%m-%d') BETWEEN '".$exp_from."' AND '".$exp_to."'";
        }

        if ($status_where == 1) {
          $status_in = 'AND a.status IN ('.$status_in.')';
        } else {
          $status_in = '';
        }

        $limit = "LIMIT ".$limit." OFFSET ".$offset;

        $query = $this->db->query("

          SELECT 
          
          a.code AS scode,
          a.name AS sname,
          a.refno,
          a.loc_group,
          DATE_FORMAT(a.grdate, '%Y-%m-%d %a') as doc_date,
          DATE_FORMAT(a.docdate, '%Y-%m-%d %a') as inv_date,
          #a.DOno AS dono,
          a.InvNo AS invno,
          a.status AS doc_status,
          '' AS doc_type,
          c.reason AS status,
          FORMAT(a.total_include_tax, 2) AS amount,
          IF(tax_code_purchase = '' , '0' , '1' ) AS include_tax,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status = '', '#7fbfe1', IF(a.status IN ('viewed', 'printed'), '#048ad1', IF(a.status = 'Invoice Generated', '#ff4e25',  IF(a.status = 'confirmed', '#00c92b', '#000000' ) ) ) ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,
          d.description AS preview_description

          
          /*DATE_FORMAT(a.docdate, '%Y-%m-%d %a') AS docdate,
          a.customer_guid,
          IFNULL(b.refno,'') AS grda_status,
          a.dono,
          a.invno,
          a.total,
          a.gst_tax_sum,
          a.tax_code_purchase,
          
          a.doc_name_reg,
          a.cross_ref,*/
          
        FROM
          $db.grmain  AS a
          LEFT JOIN  (SELECT * FROM $db.grmain_dncn GROUP BY refno) AS b
          ON a.refno = b.refno
          LEFT JOIN
          ".$this->tb_lite_b2b.".set_setting c
          ON a.status = c.code
          AND c.module_name = 'GR_FILTER_STATUS'
          INNER JOIN
          $db.grchild d
          ON a.RefNo = d.Refno
          AND d.line = '1'
        WHERE a.loc_group IN ($location_in) 
        $refno_where
        $scode_where 
        $status_in 
        $date_where
        $exp_where
        $period_code_where

        ORDER BY grdate DESC 

        $limit
        

        ");

        return $query;
    }

    function grda_list($db,$location_in,$scode_in,$scode_where,$status_in,$refno,$period_code,$date_from,$date_to,$exp_from,$exp_to,$status_where,$limit,$offset)
    {
        if ($refno == '') {
          $refno_where = 'WHERE a.refno IS NOT NULL ';
        } else {
          $refno_where = "WHERE a.refno LIKE '%".$refno."%'";
        }

        if ($scode_where == 1) {
          $scode_where = 'AND ap_sup_code IN ('.$scode_in.')';
        } else {
          $scode_where = '';
        }

        if ($period_code == '') {
          $period_code_where = '';
        } else {
          $period_code_where = "AND left(a.grdate,7)  = '".$period_code."'";
        }

        if($date_from == '' && $date_to == '') {
          $date_where = "";
        } else if($date_from == '') {
          $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } else if($date_to == '') {
          $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
          $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
          $status_in = 'AND b.transtype IN ('.$status_in.')';
        } else {
          $status_in = '';
        }

        $limit = "LIMIT ".$limit." OFFSET ".$offset;

          $query = $this->db->query("SELECT 

            b.ap_sup_code AS scode,
            a.name AS sname,
            a.refno,
            a.loc_group,
            DATE_FORMAT(a.grdate, '%Y-%m-%d %a') as doc_date,
            DATE_FORMAT(a.docdate, '%Y-%m-%d %a') as inv_date,
            #a.DOno AS dono,
            a.InvNo AS invno,
            b.transtype AS doc_status,
            '' AS doc_type,
            c.reason AS status,
            FORMAT(b.VarianceAmt, 2) AS amount,
            IF(tax_code_purchase = '' , '0' , '1' ) AS include_tax,
            '0' AS ischeck,
            IF(a.status = '', '#7fbfe1', '#048ad1' ) AS color,
            '0' AS ispreview,
            '' AS 'preview_description',

            b.transtype

          /*b.customer_guid
          , a.grdate 
          , b.status
          , b.refno
          , a.loc_group
          , b.`transtype`
          , ap_sup_code AS `code`
          , a.name
          , b.`varianceamt`
          , b.`sup_cn_no`
          , b.`sup_cn_date`
          , dncn_date
          , dncn_date_acc*/
          FROM b2b_summary.grmain AS a
          INNER JOIN  (SELECT * from b2b_summary.grmain_dncn group by refno) AS b
          ON a.refno = b.refno
          LEFT JOIN
          ".$this->tb_lite_b2b.".set_setting c
          ON b.transtype = c.code
          AND c.module_name = 'GRDA_FILTER_DOCTYPE'
          $refno_where
          $scode_where
          $period_code_where
          $date_where
          $status_in
          AND a.loc_group in ($location_in) 
          $limit");

        return $query;
    }

    function prdncn_list($db,$location_in,$scode_in,$scode_where,$status_in,$refno,$period_code,$date_from,$date_to,$exp_from,$exp_to,$status_where,$doc_type_where,$doc_type_in,$limit,$offset)
    {
        if ($refno == '') {
          $refno_where = '';
        } else {
          $refno_where = "AND a.RefNo LIKE '%".$refno."%'";
        }

        if ($scode_where == 1) {
          $scode_where = 'AND a.code IN ('.$scode_in.')';
        } else {
          $scode_where = '';
        }

        if ($period_code == '') {
          $period_code_where = '';
        } else {
          $period_code_where = "AND left(a.DocDate,7)  = '".$period_code."'";
        }

        if($date_from == '' && $date_to == '') {
          $date_where = "";
        } else if($date_from == '') {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } else if($date_to == '') {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
          $status_in = 'AND a.type IN ('.$status_in.')';
        } else {
          $status_in = '';
        }

        if ($doc_type_where == 1) {
          $doc_type_in = 'AND a.type IN ('.$doc_type_in.')';
        } else {
          $doc_type_in = '';
        }

        $limit = "LIMIT ".$limit." OFFSET ".$offset;

        $query = $this->db->query("

          SELECT * FROM(

          SELECT 
          a.Code AS scode,
          a.Name AS sname,
          a.RefNo AS refno,
          a.location AS loc_group,
          DATE_FORMAT(a.DocDate, '%Y-%m-%d %a') AS doc_date,
          a.DocNo AS doc_no,
          IF(a.sup_cn_date < '1500-00-00', '-' , DATE_FORMAT(a.sup_cn_date, '%Y-%m-%d %a') ) as inv_date,
          IF(a.sup_cn_no = '', '-' , a.sup_cn_no) AS invno,
          c.reason AS doc_type,
          IF(c.reason IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS doc_type_color,
          b.reason AS status,
          FORMAT(a.SubTotal2, 2) AS amount,
          IF(tax_code_purchase = '' , '0' , '1' ) AS include_tax,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,
          '' AS 'preview_description'

        FROM
          $db.dbnotemain AS a
          LEFT JOIN
          ".$this->tb_lite_b2b.".set_setting b
          ON a.status = b.code
          AND b.module_name = 'PRDNCN_FILTER_DOC_STATUS'
          LEFT JOIN
          ".$this->tb_lite_b2b.".set_setting c
          ON a.type = c.code
          AND c.module_name = 'PRDNCN_FILTER_STATUS'
        WHERE a.location IN ($location_in)
          $scode_where
          $status_in
          $doc_type_in
          $refno_where
          $period_code_where
          $date_where

          UNION ALL 

          SELECT 
          a.Code AS scode,
          a.Name AS sname,
          a.RefNo AS refno,
          a.locgroup AS loc_group,
          DATE_FORMAT(a.DocDate, '%Y-%m-%d %a') AS doc_date,
          DATE_FORMAT(a.sup_cn_date, '%Y-%m-%d %a') AS inv_date,
          a.sup_cn_no AS invno,
          a.DocNo AS doc_no,
          c.reason AS doc_type,
          IF(c.reason IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS doc_type_color,
          b.reason AS status,
          FORMAT(a.SubTotal2, 2) AS amount,
          IF(tax_code_purchase = '' , '0' , '1' ) AS include_tax,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,
          '' AS 'preview_description'

        FROM
          $db.cnnotemain AS a
          LEFT JOIN
          ".$this->tb_lite_b2b.".set_setting b
          ON a.status = b.code
          AND b.module_name = 'PRDNCN_FILTER_DOC_STATUS'
          LEFT JOIN
          ".$this->tb_lite_b2b.".set_setting c
          ON a.type = c.code
          AND c.module_name = 'PRDNCN_FILTER_STATUS'
        WHERE a.customer_guid = '$customer_guid' 
          $scode_where
          AND a.location IN ($location_in)
          $status_in
          $doc_type_in
          $refno_where
          $period_code_where
          $date_where

          )aa

          ORDER BY doc_date DESC

          $limit
           ;");


        return $query;
    }

    function pdncn_list($db,$location_in,$scode_in,$scode_where,$status_in,$refno,$period_code,$date_from,$date_to,$exp_from,$exp_to,$status_where,$doc_type_where,$doc_type_in,$limit,$offset)
    {
        if ($refno == '') {
          $refno_where = '';
        } else {
          $refno_where = "AND a.RefNo LIKE '%".$refno."%'";
        }

        if ($scode_where == 1) {
          $scode_where = 'AND a.code IN ('.$scode_in.')';
        } else {
          $scode_where = '';
        }

        if ($period_code == '') {
          $period_code_where = '';
        } else {
          $period_code_where = "AND left(a.DocDate,7)  = '".$period_code."'";
        }

        if($date_from == '' && $date_to == '') {
          $date_where = "";
        } else if($date_from == '') {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } else if($date_to == '') {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
          $status_in = 'AND a.trans_type IN ('.$status_in.')';
        } else {
          $status_in = '';
        }

        if ($doc_type_where == 1) {
          $doc_type_in = 'AND a.trans_type IN ('.$doc_type_in.')';
        } else {
          $doc_type_in = '';
        }

        $limit = "LIMIT ".$limit." OFFSET ".$offset;

        $query = $this->db->query("

          SELECT 
          a.Code AS scode,
          a.Name AS sname,
          a.RefNo AS refno,
          a.location AS loc_group,
          DATE_FORMAT(a.DocDate, '%Y-%m-%d %a') AS doc_date,
          a.DocNo AS doc_no,
          IF(a.sup_cn_no = '', '-' , a.sup_cn_no) AS invno,
          IF(a.sup_cn_date < '1500-00-00', '-' , DATE_FORMAT(a.sup_cn_date, '%Y-%m-%d %a') ) as inv_date,
          c.reason AS doc_type,
          IF(c.reason IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS doc_type_color,
          b.reason AS status,
          FORMAT(a.amount_include_tax, 2) AS amount,
          IF(gst_tax_sum = '0' , '0' , '1' ) AS include_tax,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,
          '' AS 'preview_description'

        FROM
          $db.cndn_amt AS a
        LEFT JOIN ".$this->tb_lite_b2b.".set_setting b
        ON a.status = b.code
        AND b.module_name = 'PDNCN_FILTER_STATUS'
        LEFT JOIN
          ".$this->tb_lite_b2b.".set_setting c
          ON a.trans_type = c.code
          AND c.module_name = 'PDNCN_FILTER_TYPE'
        WHERE a.location IN ($location_in)
          #AND trans_type IN ('PCNAMT' , 'PDNAMT')
          $scode_where
          $status_in
          $doc_type_in
          $refno_where
          $period_code_where
          $date_where

          ORDER BY doc_date DESC

          $limit
           ;");

        return $query;
    }

    function pci_list($db,$location_in,$scode_in,$scode_where,$status_in,$refno,$period_code,$date_from,$date_to,$exp_from,$exp_to,$status_where,$limit,$offset)
        {
        if ($refno == '') {
          $refno_where = '';
        } else {
          $refno_where = "AND a.inv_refno LIKE '%".$refno."%'";
        }

        if ($scode_where == 1) {
          $scode_where = 'AND a.sup_code IN ('.$scode_in.')';
        } else {
          $scode_where = '';
        }

        if ($period_code == '') {
          $period_code_where = '';
        } else {
          $period_code_where = "AND left(a.DocDate,7)  = '".$period_code."'";
        }

        if($date_from == '' && $date_to == '') {
          $date_where = "";
        } else if($date_from == '') {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } else if($date_to == '') {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
          $status_in = 'AND a.status IN ('.$status_in.')';
        } else {
          $status_in = '';
        }

        $limit = "LIMIT ".$limit." OFFSET ".$offset;

        $query = $this->db->query("

          SELECT 
          a.sup_code AS scode,
          a.sup_name AS sname,
          a.inv_refno AS refno,
          a.loc_group AS loc_group,
          DATE_FORMAT(a.DocDate, '%Y-%m-%d %a') AS doc_date,
          a.inv_refno AS doc_no,
          a.refno AS invno,
          '' as inv_date,
          '' AS doc_type,
          IF(a.status = '', 'New', a.status ) AS status,
          FORMAT(a.total_net, 2) AS amount,
          IF(tax_code_supply = '' , '0' , '1' ) AS include_tax,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,
          '' AS 'preview_description'

        FROM
          $db.promo_taxinv AS a
        WHERE a.loc_group IN ($location_in)
          $scode_where
          $status_in

          $refno_where
          $period_code_where
          $date_where

          ORDER BY doc_date DESC

          $limit
           ;");

        return $query;
    }

    function di_list($db,$location_in,$scode_in,$scode_where,$status_in,$refno,$period_code,$date_from,$date_to,$exp_from,$exp_to,$status_where,$limit,$offset)
        {
        if ($refno == '') {
          $refno_where = '';
        } else {
          $refno_where = "AND a.inv_refno LIKE '%".$refno."%'";
        }

        if ($scode_where == 1) {
          $scode_where = 'AND a.sup_code IN ('.$scode_in.')';
        } else {
          $scode_where = '';
        }

        if ($period_code == '') {
          $period_code_where = '';
        } else {
          $period_code_where = "AND left(a.DocDate,7)  = '".$period_code."'";
        }

        if($date_from == '' && $date_to == '') {
          $date_where = "";
        } else if($date_from == '') {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } else if($date_to == '') {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
          $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
          $status_in = 'AND a.status IN ('.$status_in.')';
        } else {
          $status_in = '';
        }

        $limit = "LIMIT ".$limit." OFFSET ".$offset;

        $query = $this->db->query("

          SELECT 
          a.sup_code AS scode,
          a.sup_name AS sname,
          a.inv_refno AS refno,
          a.loc_group AS loc_group,
          DATE_FORMAT(a.DocDate, '%Y-%m-%d %a') AS doc_date,
          a.inv_refno AS doc_no,
          a.refno AS invno,
          '' AS inv_date,
          '' AS doc_type,
          IF(a.status = '', 'New', a.status ) AS status,
          FORMAT(a.total_net, 2) AS amount,
          IF(tax_code_supply = '' , '0' , '1' ) AS include_tax,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,
          '' AS 'preview_description'

        FROM
          $db.discheme_taxinv AS a
        WHERE a.loc_group IN ($location_in)
          $scode_where
          $status_in

          $refno_where
          $period_code_where
          $date_where

          ORDER BY doc_date DESC

          $limit
           ;");

        return $query;
    }

    function po_info($refno,$db)
    {

        /*$query = $this->db->query("SELECT 
          refno, 
          if(status = '', 'Pending', status) AS status, 
          rejected_remark, 
          scode FROM $db.pomain WHERE refno = '$refno' AND customer_guid = '$customer_guid'");*/

          $this->db->where_in('refno', $refno);
          $this->db->select("refno, if(status = '', 'Pending', status) AS status, REPLACE(scode,'/','+-+') AS scode,loc_group,SName");
          $this->db->from("$db.pomain");

          return $this->db->get();

        /*$query = $this->db->query("SELECT 
          refno, 
          if(status = '', 'Pending', status) AS status, 
          scode FROM $db.pomain WHERE refno = '$refno'");

        return $query;*/
    }

    function grn_info($refno,$db)
    {

        /*$query = $this->db->query("SELECT a.`customer_guid`
                    , a.`RefNo` AS refno
                    , a.`status`
                    , a.`Location`
                    , IF(b.DONo IS NULL, a.`DONo`, b.DONo) AS DONo
                    , IF(b.InvNo IS NULL, a.`InvNo`, b.InvNo) AS InvNo
                    , IF(b.DocDate IS NULL, a.`DocDate`, b.DocDate) AS DocDate
                    , a.`GRDate`
                    , a.`Code` AS scode
                    , a.`Name`
                    , a.`consign`
                    , a.Total
                    , a.gst_tax_sum
                    , a.total_include_tax
                    FROM $db.grmain AS a 
                    LEFT JOIN $db.grmain_proposed AS b 
                    ON a.refno = b.refno 
                    AND a.customer_guid = b.customer_guid where a.refno = '$refno' and a.customer_guid = '$customer_guid'");*/

        $query = $this->db->query("SELECT
                    a.`RefNo` AS refno
                    , a.`status`
                    , REPLACE(a.Code,'/','+-+') AS scode
                    FROM $db.grmain AS a where a.refno = '$refno' ");

        return $query;
    }

    function grda_info($refno,$db)
    {
        $query = $this->db->query("SELECT
                    a.`RefNo` AS refno
                    , a.`transtype` AS status
                    , REPLACE(a.Code,'/','+-+') AS scode
                    FROM $db.grmain_dncn AS a 
                    WHERE a.refno = '$refno' ");

        return $query;
    }

    function prdncn_info($set_row,$db,$DBNOTE_table,$refno,$pdncn)
    {

        /*$query = $this->db->query("SELECT 
          @row := @row + 1 AS rowx, 
          a.customer_guid, 
          a.status, 
          a.Type, 
          a.RefNo, a.Location,
          a.DocNo,
          a.DocDate,
          a.IssueStamp,
          a.LastStamp,
          a.PONo,
          a.SCType,
          a.Code,
          a.Name,
          a.Term,
          a.Issuedby,
          a.Remark,
          a.BillStatus,
          a.AccStatus,
          a.DueDate,
          a.Amount,
          a.Closed,
          a.SubDeptCode,
          a.postby,
          a.postdatetime,
          a.Consign,
          a.EXPORT_ACCOUNT,
          a.EXPORT_AT,
          a.EXPORT_BY,
          a.hq_update,
          a.locgroup,
          a.ibt,
          a.SubTotal1,
          a.Discount1,
          a.Discount1Type,
          a.SubTotal2,
          a.Discount2,
          a.Discount2Type,
          a.gst_tax_sum,
          a.tax_code_purchase,
          IF(b.ext_doc1 IS NULL,
          a.sup_cn_no,
          b.ext_doc1 ) AS sup_cn_no,
          IF(b.ext_date1 IS NULL,
          a.sup_cn_date,
          b.ext_date1) AS sup_cn_date,
          a.doc_name_reg,
          a.gst_tax_rate,
          a.multi_tax_code,
          a.refno2,
          a.surchg_tax_sum,
          a.gst_adj,
          a.rounding_adj,
          a.unpostby,
          a.unpostdatetime,
          a.ibt_gst,
          a.acc_posting_date,
          a.RoundAdjNeed 
          FROM $db.$DBNOTE_table AS a LEFT JOIN (SELECT * FROM $db.ecn_main WHERE customer_guid = '$customer_guid' AND refno = '$refno' AND `type` = 'PRDNCN') AS b ON a.refno = b.refno WHERE a.refno = '$refno' AND a.customer_guid = '$customer_guid' AND a.type = '$pdncn' ");*/

        $query = $this->db->query("SELECT 
          @row := @row + 1 AS rowx, 
          a.RefNo AS refno,
          a.status, 
          REPLACE(a.Code,'/','+-+') AS scode
          FROM $db.$DBNOTE_table AS a LEFT JOIN (SELECT * FROM $db.ecn_main WHERE refno = '$refno' AND `type` = 'PRDNCN') AS b ON a.refno = b.refno WHERE a.refno = '$refno' AND a.type = '$pdncn' ");

        return $query;
    }
    function pdncn_info($refno,$db)
    {
        $query = $this->db->query("SELECT
                    a.`refno` AS refno
                    , a.`trans_type` AS status
                    , REPLACE(a.code,'/','+-+') AS scode
                    , a.trans_type
                    FROM $db.cndn_amt AS a 
                    WHERE a.refno = '$refno' ");

        return $query;
    }

    function pci_info($refno,$db)
    {
        $query = $this->db->query("SELECT
                    a.`refno` AS refno
                    , a.`status` AS status
                    , REPLACE(a.sup_code,'/','+-+') AS scode
                    FROM $db.promo_taxinv AS a 
                    WHERE a.refno = '$refno' ");

        return $query;
    }

    function di_info($refno,$db)
    {
        $query = $this->db->query("SELECT
                    a.`inv_refno` AS refno
                    , a.`status` AS status
                    , REPLACE(a.sup_code,'/','+-+') AS scode
                    FROM $db.discheme_taxinv AS a 
                    WHERE a.inv_refno = '$refno' ");

        return $query;
    }

    function grmain_dncn($db,$refno)
    {

        $query = $this->db->query("SELECT @row:=@row+1 AS rowx, IFNULL(b.ecn_guid, 'Pending') AS ecn_guid, IFNULL(b.status, 'Pending' ) AS ecn_status, IFNULL(b.type, 'Pending') AS ecn_type,   ext_doc1 , ifnull(ext_date1, curdate()) as ext_date1,   IFNULL(b.posted, '0') as posted, a.status, a.location, a.RefNo, a.VarianceAmt, a.Created_at, a.Created_by, a.Updated_at, a.Updated_by, a.hq_update, a.EXPORT_ACCOUNT, a.EXPORT_AT, a.EXPORT_BY, a.transtype, a.share_cost, a.gst_tax_sum, a.gst_adjust, a.gl_code, a.tax_invoice, a.ap_sup_code, a.refno2, a.rounding_adj, a.sup_cn_no, a.sup_cn_date, a.dncn_date, a.dncn_date_acc FROM $db.grmain_dncn AS a LEFT JOIN (SELECT * FROM $db.ecn_main WHERE refno = '$refno' ) AS b ON a.refno = b.refno AND a.transtype = b.type WHERE a.refno = '$refno' order by transtype asc ");

        return $query;
    }

    function einv_main($db,$refno)
    {

        $query = $this->db->query("SELECT * FROM $db.einv_main WHERE refno = '$refno' ");

        return $query;
    }

    function set_setting_reason_by_code($module_name,$code)
    {

      $this->db->where('module_name', $module_name);
      $this->db->where_in('code', $code);
      $this->db->select("reason");
      $this->db->from($this->tb_lite_b2b.".set_setting");

      return $this->db->get();

    }

    function po_child($db,$refno)
    {

        $query = $this->db->query("SELECT * FROM $db.pochild WHERE RefNo = '$refno'");

        return $query;
    }

}

?> 
