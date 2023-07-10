<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class lite_b2b_model extends CI_Model
{
    public $tb_lite_b2b = 'lite_b2b';
    public $tb_lite_b2b_apps = 'lite_b2b_apps';
    public $module_group_guid = '6595A39AD4AE11E7861FA81E8453CCF0';
    public $tb_b2b_invoice = 'b2b_invoice';

    public function __construct()
    {
        parent::__construct();
    }

    public function check_login($userid, $password)
    {
        $query = $this->db->query("SELECT a.*,b.`user_group_name`,d.`module_name`,e.`module_group_name`,module_code,c.`isenable`, a.user_guid FROM " . $this->tb_lite_b2b . ".set_user a
             INNER JOIN " . $this->tb_lite_b2b . ".set_user_group b ON a.`user_group_guid` = b.`user_group_guid`
             INNER JOIN " . $this->tb_lite_b2b . ".set_user_module c ON c.`user_group_guid` = b.`user_group_guid`
             INNER JOIN " . $this->tb_lite_b2b . ".set_module d ON d.`module_guid` = c.`module_guid`
             INNER JOIN " . $this->tb_lite_b2b . ".set_module_group e ON e.`module_group_guid`= d.`module_group_guid`
             AND e.`module_group_guid` = a.`module_group_guid`
             WHERE a.user_id = '$userid' AND a.`user_password` = '$password' AND a.`isactive` = 1
             AND c.`isenable` = 1 AND module_group_name in ('Panda B2B') AND a.ismobile = '1'");
        /*  $query = $this->db->query("SELECT * FROM acc_user a INNER JOIN acc_user_group b ON a.`acc_user_group_guid` = b.`acc_user_group_guid`
        WHERE a.acc_user_id = '$userid' AND a.`acc_user_password` = '$password'");*/
        return $query;
    }

    public function app_check_login($fcm_token)
    {
        $query = $this->db->query("SELECT user_guid FROM " . $this->tb_lite_b2b_apps . ".user_session WHERE fcm_token = '$fcm_token'");
        return $query;
    }

    public function user_info($user_guid)
    {
        $query = $this->db->query("SELECT user_id,user_password,user_name FROM " . $this->tb_lite_b2b . ".set_user WHERE user_guid = '$user_guid' LIMIT 1");
        return $query;
    }

    public function check_super_admin($user_guid, $customer_guid)
    {
        if ($customer_guid == '') {
            $query = $this->db->query("SELECT COUNT(*) as counting FROM " . $this->tb_lite_b2b . ".set_user WHERE user_group_guid = '3379ECDBDB0711E7B504A81E8453CCF0' AND user_guid = '$user_guid'");
            return $query;
        } else {
            $query = $this->db->query("SELECT COUNT(*) as counting FROM " . $this->tb_lite_b2b . ".set_user WHERE user_group_guid = '3379ECDBDB0711E7B504A81E8453CCF0' AND user_guid = '$user_guid' AND acc_guid = '$customer_guid' ");
            return $query;
        }
    }

    public function check_customer_admin($user_guid, $customer_guid)
    {
        $query = $this->db->query("SELECT COUNT(*) as counting FROM " . $this->tb_lite_b2b . ".set_user WHERE user_group_guid = '8D585024DE4F11E79A3AA81E8453CCF0' AND user_guid = '$user_guid' AND acc_guid = '$customer_guid'");
        return $query;
    }

    public function check_customer_admin_testing($user_guid, $customer_guid)
    {
        $query = $this->db->query("SELECT COUNT(*) as counting FROM " . $this->tb_lite_b2b . ".set_user WHERE user_group_guid = '40AAC453BD2B11EB85C1000D3AA2838A' AND user_guid = '$user_guid' AND acc_guid = '$customer_guid'");
        return $query;
    }

    public function get_customer($check_super_admin, $user_guid)
    {
        #WHERE acc_guid IN ('13EE932D98EB11EAB05B000D3AA2838A','8D5B38E931FA11E79E7E33210BD612D3')
        $module_group_guid = $this->module_group_guid;

        if ($check_super_admin > 0) {
            $query = $this->db->query("SELECT acc_guid,UPPER(acc_name) as acc_name,seq,
              IF(acc_guid = '13EE932D98EB11EAB05B000D3AA2838A', 'https://file.xbridge.my/b2b-img/app/customer/pandamart/pandamart.jpg' , CONCAT('https://file.xbridge.my/b2b-img/app/customer/',SUBSTRING_INDEX(file_path, '/', -1),'/',SUBSTRING_INDEX(file_path, '/', -1) , '.jpg') ) AS image
            FROM " . $this->tb_lite_b2b . ".`acc`

            WHERE isactive = '1'

             ORDER BY seq ASC,row_seq ASC");
        } else {

            $query = $this->db->query("SELECT DISTINCT d.acc_guid, UPPER(e.acc_name) as acc_name
              , IF(e.acc_guid = '13EE932D98EB11EAB05B000D3AA2838A', 'https://file.xbridge.my/b2b-img/app/customer/pandamart/pandamart.jpg' , CONCAT('https://file.xbridge.my/b2b-img/app/customer/',SUBSTRING_INDEX(file_path, '/', -1),'/',SUBSTRING_INDEX(file_path, '/', -1) , '.jpg') ) AS image
             FROM " . $this->tb_lite_b2b . ".`set_user` AS a
            INNER JOIN " . $this->tb_lite_b2b . ".`set_user_branch` b ON a.user_guid = b.user_guid
            INNER JOIN " . $this->tb_lite_b2b . ".`acc_branch` c ON b.branch_guid = c.branch_guid
            INNER JOIN " . $this->tb_lite_b2b . ".`acc_concept` d ON c.concept_guid = d.concept_guid
            INNER JOIN " . $this->tb_lite_b2b . ".`acc` e ON d.`acc_guid` = e.`acc_guid`
            WHERE a.user_guid = '$user_guid'
            AND e.isactive = '1'
            AND a.module_group_guid = '$module_group_guid'

            #AND e.redirect = '0'

            ORDER BY e.seq ASC,e.row_seq ASC ");
        }

        return $query;
    }

    public function customer_info($customer_guid)
    {

        $query = $this->db->query("SELECT acc_name,b2b_database,b2b_hub_database,file_host,file_path,
        rest_url,accounting_doc, '0' AS redirect,'b2b_summary' as old_db,azure_container_name
        FROM " . $this->tb_lite_b2b . ".acc
        WHERE acc_guid = '$customer_guid'");

        return $query;
    }

    public function get_branch($customer_guid, $user_guid)
    {
        $module_group_guid = $this->module_group_guid;

        $query = $this->db->query("SELECT

            DISTINCT c.branch_code,
            IF(d.branch_desc IS NOT NULL OR d.branch_desc != '',CONCAT(c.branch_code, ' - ',d.branch_desc), c.branch_code)  AS branch_name,
            #IF(d.branch_desc IS NOT NULL OR d.branch_desc != '',CONCAT(c.branch_code, ' - ',c.branch_name), c.branch_code)  AS branch_name,
            c.is_hq

            FROM " . $this->tb_lite_b2b . ".set_user a
            INNER JOIN " . $this->tb_lite_b2b . ".set_user_branch b ON a.user_guid = b.user_guid
            INNER JOIN " . $this->tb_lite_b2b . ".acc_branch c on b.branch_guid = c.branch_guid
            INNER JOIN b2b_summary.cp_set_branch d on c.branch_code = d.branch_code
            WHERE a.user_guid = '$user_guid'
            AND a.isactive = '1'
            AND a.module_group_guid = '$module_group_guid'
            AND b.acc_guid = '$customer_guid'
            AND d.customer_guid = '$customer_guid'
            AND c.isactive = '1' ORDER BY is_hq DESC , branch_code ASC");

        return $query;
    }

    public function get_notification($customer_guid, $user_guid)
    {
        $query = $this->db->query("SELECT a.*,DATE_FORMAT(Registration_Invoice_Date,'%e-%M-%Y') AS reg_date,
        DATE_FORMAT(NOW(), '%e-%M-%Y') AS NOW,
        (Total_Overdue + Last_Invoice_Amt) AS total_amount_due
        FROM
        lite_b2b.`query_outstanding_new` a
        INNER JOIN lite_b2b.`set_supplier_user_relationship` b
        ON a.`supplier_guid` = b.`supplier_guid`
        AND b.`customer_guid` = '$customer_guid'
        WHERE b.user_guid = '$user_guid'
        GROUP BY a.`supplier_guid`");

        return $query;
    }

    public function get_customerName($customer_guid)
    {
        $customer_name = $this->db->query("SELECT *
        FROM " . $this->tb_lite_b2b . ".acc WHERE acc_guid = '$customer_guid'");
        return $customer_name;
    }

    public function get_user_group($user_guid, $customer_guid)
    {
        $query = $this->db->query("SELECT
            b.user_group_guid
            #,b.user_group_name
            FROM " . $this->tb_lite_b2b . ".set_user a
            INNER JOIN " . $this->tb_lite_b2b . ".set_user_group b
            ON a.user_group_guid = b.user_group_guid
            WHERE user_guid = '$user_guid'
            AND a.acc_guid = '$customer_guid'");

        return $query;
    }

    public function get_module($user_guid, $customer_guid)
    {
        $query = $this->db->query("SELECT module_code FROM " . $this->tb_lite_b2b . ".set_user a INNER JOIN " . $this->tb_lite_b2b . ".set_user_group b ON a.user_group_guid = b.user_group_guid INNER JOIN " . $this->tb_lite_b2b . ".set_user_module c ON b.user_group_guid = c.user_group_guid INNER JOIN " . $this->tb_lite_b2b . ".set_module d ON c.module_guid = d.module_guid INNER JOIN " . $this->tb_lite_b2b . ".set_module_group e ON d.module_group_guid = e.module_group_guid WHERE a.user_guid = '$user_guid' AND a.isactive = 1 AND a.acc_guid = '$customer_guid' AND e.module_group_name = 'Panda B2B' AND c.isenable = 1 GROUP BY a.user_guid,a.acc_guid,c.module_guid");
        return $query;
    }

    public function dashboard_num_days_data($customer_guid)
    {

        $query = $this->db->query("SELECT dashboard_num_days_data FROM " . $this->tb_lite_b2b . ".acc where acc_guid = '$customer_guid'")->row('dashboard_num_days_data');

        return $query;
    }

    public function get_scode($customer_guid, $user_guid, $module_code)
    {

        /*A2C41C08DE4F11E79A3AA81E8453CCF0 SUPP_ADMIN
        A694CB46DE4F11E79A3AA81E8453CCF0 SUPP_CLERK
        F6E92188DF5D11E9814B000D3AA2838A LIMITED_SUPP_ADMIN*/
        // $array = array("A2C41C08DE4F11E79A3AA81E8453CCF0", "A694CB46DE4F11E79A3AA81E8453CCF0", "F6E92188DF5D11E9814B000D3AA2838A");
        $check_block = $this->db->query("SELECT a.supplier_guid,a.customer_guid,a.variance FROM lite_b2b.`query_outstanding_retailer` a INNER JOIN lite_b2b.`set_supplier_user_relationship` b ON a.`customer_guid` = b.customer_guid AND a.`supplier_guid` = b.`supplier_guid` WHERE a.`customer_guid` = '$customer_guid' AND b.user_guid = '$user_guid' AND a.variance = '1' GROUP BY a.`customer_guid`, a.`supplier_guid`")->result_array();

        $blocked_guid = implode("','",array_filter(array_column($check_block,'supplier_guid')));
    
        if ($blocked_guid != '') {
            $query_blocked = "AND b.supplier_guid NOT IN ('$blocked_guid')";
        }
        else
        {
            $query_blocked = '';
        }

        if (!in_array('IAVA', $module_code)) {

            $query = $this->db->query("SELECT distinct backend_supplier_code from " . $this->tb_lite_b2b . ".set_supplier_user_relationship as a inner join " . $this->tb_lite_b2b . ".set_supplier_group as b on a.supplier_group_guid = b.supplier_group_guid INNER JOIN " . $this->tb_lite_b2b . ".set_supplier c ON a.supplier_guid = c.supplier_guid where a.user_guid = '$user_guid' $query_blocked AND b.customer_guid = '$customer_guid' AND c.isactive = 1");
        } else {
            // $query = $this->db->query("SELECT distinct backend_supplier_code from " . $this->tb_lite_b2b . ".set_supplier_user_relationship as a inner join " . $this->tb_lite_b2b . ".set_supplier_group as b on a.supplier_group_guid = b.supplier_group_guid where a.user_guid = '$user_guid'");
            $query = $this->db->query("SELECT distinct backend_supplier_code from " . $this->tb_lite_b2b . ".set_supplier_user_relationship as a inner join " . $this->tb_lite_b2b . ".set_supplier_group as b on a.supplier_group_guid = b.supplier_group_guid where a.user_guid = '$user_guid'");
        }

        return $query;
    }

    public function get_debtor_code($customer_guid, $scode_in)
    {
        $query = $this->db->query("SELECT accpdebit
        FROM b2b_summary.supcus
        WHERE customer_guid = '$customer_guid'
        AND CODE IN ($scode_in)
        AND (accpdebit <> '' AND accpdebit IS NOT NULL)");
        return $query;
    }

    public function get_other_doc_list($customer_guid)
    {
        $query = $this->db->query("SELECT a.code AS type, a.description AS display_name,
        IF(a.code = 'SCM','#421e0e','#931adf') AS background_color,'dashboard/DII.png' AS icon
        FROM lite_b2b.other_doc_setting AS a
        WHERE a.customer_guid = '$customer_guid'
        AND a.isactive = 1
        ORDER BY a.seq ASC");

        return $query;
    }

    public function dashboard($db, $customer_guid, $location_in, $scode_in, $scode_where, $dashboard_num_days_data, $check_super_admin)
    {
        if ($scode_where == 1) {
            $scode_where = 'AND scode IN (' . $scode_in . ')';
            $code_where = 'AND code IN (' . $scode_in . ')';
            $sup_code_where = 'AND sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
            $code_where = '';
            $sup_code_where = '';
        }
        $check_outstanding_pomain = $this->db->query("
              SELECT
              count(refno) as count_doc,
              'PO' as type,
              'Purchase Order' as display_name,
              '#00c0ef' AS background_color,
              'dashboard/PO.png' AS icon

              FROM $db.pomain WHERE status = '' AND customer_guid = '$customer_guid' AND loc_group IN ($location_in) $scode_where AND podate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()");

        $check_grn = $this->db->query("SELECT
              COUNT(refno) AS count_doc,
              'GRN' as type,
              'Goods Received Note' as display_name,
              '#00a65a' AS background_color,
              'dashboard/GRN.png' AS icon

              FROM $db.grmain WHERE status = '' AND customer_guid = '$customer_guid' AND loc_group IN ($location_in) $code_where AND grdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE() ");

        $check_grda = $this->db->query("SELECT count(a.refno) as count_doc,
             'GRDA' as type,
             'GR Difference Advice' as display_name,
             '#f39c12' AS background_color,
             'dashboard/GRDA.png' AS icon

             FROM $db.grmain AS a
              INNER JOIN  (SELECT * FROM $db.grmain_dncn WHERE customer_guid = '$customer_guid' GROUP BY refno) AS b
              ON a.refno = b.refno  AND a.customer_guid = b.customer_guid

             WHERE b.`customer_guid` = '$customer_guid' AND a.loc_group IN ($location_in) $code_where AND a.grdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

        $prdncn = $this->db->query("SELECT
              COUNT(refno) AS count_doc,
              'PRDNCN' as type,
              'Purchase Return DN/CN' as display_name,
              '#dd4b39' AS background_color,
              'dashboard/PRDNCN.png' AS icon

            FROM
              $db.dbnotemain
            WHERE customer_guid = '$customer_guid'
              AND locgroup IN ($location_in)
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
            WHERE customer_guid = '$customer_guid'
              AND locgroup IN ($location_in)
              $code_where
              AND DocDate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE() ;

              ");

        $pdncn = $this->db->query("SELECT count(refno) as count_doc,
             'PDNCN' as type,
             'Purchase DN/CN' as display_name,
             '#0b738c' AS background_color,
             'dashboard/PDNCN.png' AS icon

             FROM $db.cndn_amt

              WHERE customer_guid = '$customer_guid'
              AND loc_group IN ($location_in)
              $code_where
              AND docdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

        $pci = $this->db->query("SELECT count(refno) as count_doc,
             'PCI' as type,
             'Promotion Claim Tax Invoice' as display_name,
             '#36815f' AS background_color,
             'dashboard/PCI.png' AS icon

             FROM $db.promo_taxinv

              WHERE customer_guid = '$customer_guid'
              AND loc_group IN ($location_in)
              $sup_code_where
              AND docdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

        $dii = $this->db->query("SELECT count(refno) as count_doc,
             'DI' as type,
             'Display Incentive Tax Invoice' as display_name,
             '#aa6e0e' AS background_color,
             'dashboard/DII.png' AS icon

             FROM $db.discheme_taxinv

              WHERE customer_guid = '$customer_guid'
              AND loc_group IN ($location_in)
              $sup_code_where
              AND docdate BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

        /*$no_respond =  $this->db->query("SELECT count(refno) as count_doc,
        'NORESPONDPO' as type,
        'No Respond PO' as display_name,
        '#dd4b39' AS background_color,
        'dashboard/NO_RESPOND_PO.png' AS icon

        FROM $db.pomain where status = '' AND customer_guid = '$customer_guid' AND loc_group in ($location_in) $scode_where AND podate < CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE() ");*/

        $rb = $this->db->query("SELECT count(batch_no) as count_doc,
             'STRB' as type,
             'Stock Return Batch Document' as display_name,
             '#9F34FE' AS background_color,
             'dashboard/DII.png' AS icon

             FROM $db.dbnote_batch

              WHERE customer_guid = '$customer_guid'
              AND loc_group IN ($location_in)
              AND status = '0'
              $sup_code_where
              AND doc_date BETWEEN CURDATE() - INTERVAL $dashboard_num_days_data DAY AND CURDATE()  ");

        $consign = $this->db->query("SELECT '' AS count_doc,
             'Consignment' AS type,
             'Consignment' AS display_name,
             '#21618C' AS background_color,
             'dashboard/DII.png' AS icon ");

        // tzu haw
        $accounting_document = $this->db->query("SELECT '' AS count_doc,
            'Accounting_Documents' AS type,
            'Accounting Documents' AS display_name,
            '#0E6655' AS background_color,
            'dashboard/DII.png' AS icon ");

        $b2b_monthly_billing_invoice = $this->db->query("SELECT '' AS count_doc,
            'B2B_monthly_billing_invoices' AS type,
            'B2B Monthly Billing Invoices' AS display_name,
            '#68D589' AS background_color,
            'dashboard/DII.png' AS icon ");

        $propose_po = $this->db->query("SELECT '' AS count_doc,
        'propose_po' AS type,
        'Propose Po' AS display_name,
        '#aa6e0e' AS background_color,
        'dashboard/DII.png' AS icon ");

        $si = $this->db->query("SELECT '' AS count_doc,
        'SI' AS type,
        'Sales Invoice' AS display_name,
        '#aa6e0e' AS background_color,
        'dashboard/DII.png' AS icon ");

        // $data = array(
        //     $check_outstanding_pomain[0],
        //     $check_grn->result()[0],
        //     $check_grda->result()[0],
        //     $prdncn->result()[0],
        //     $pdncn->result()[0],
        //     $pci->result()[0],
        //     $dii->result()[0],
        //     #$no_respond->result()[0],
        //     #$rb->result()[0],
        //     $consign->result()[0],
        //     $accounting_document->result()[0],
        // );

        #panda
        if ($customer_guid == '13EE932D98EB11EAB05B000D3AA2838A') {
            $data = array(
                $check_outstanding_pomain->result()[0],
                $check_grn->result()[0],
                $check_grda->result()[0],
                $prdncn->result()[0],
                $pdncn->result()[0],
                $pci->result()[0],
                $dii->result()[0],
                #$no_respond->result()[0],
                $rb->result()[0],
                $consign->result()[0],
                $accounting_document->result()[0],
                $b2b_monthly_billing_invoice->result()[0],
                $propose_po->result()[0],
            );
        }
        #everrise dont have pci and dii
        elseif ($customer_guid == 'D361F8521E1211EAAD7CC8CBB8CC0C93') {
            $data = array(

                $check_outstanding_pomain->result()[0],
                $check_grn->result()[0],
                $check_grda->result()[0],
                $prdncn->result()[0],
                $pdncn->result()[0],
                $pci->result()[0],
                $dii->result()[0],
                #$no_respond->result()[0],
                $rb->result()[0],
                $consign->result()[0],
                $accounting_document->result()[0],
                $b2b_monthly_billing_invoice->result()[0],
            );

            #tf big bataras only got consignment
        } elseif ($customer_guid == '8D5B38E931FA11E79E7E33210BD612D3' || $customer_guid == '403810171FA711EA9BB8E4E7491C3E1E' || $customer_guid == '1F90F5EF90DF11EA818B000D3AA2CAA9') {
            $data = array(

                $check_outstanding_pomain->result()[0],
                $check_grn->result()[0],
                $check_grda->result()[0],
                $prdncn->result()[0],
                $pdncn->result()[0],
                $pci->result()[0],
                $dii->result()[0],
                #$no_respond->result()[0],
                $rb->result()[0],
                $consign->result()[0],
                $accounting_document->result()[0],
                $b2b_monthly_billing_invoice->result()[0],
                // $propose_po->result()[0],
                $si->result()[0],
            );
        } else {

            $data = array(

                $check_outstanding_pomain->result()[0],
                $check_grn->result()[0],
                $check_grda->result()[0],
                $prdncn->result()[0],
                $pdncn->result()[0],
                $pci->result()[0],
                $dii->result()[0],
                #$no_respond->result()[0],
                // $rb->result()[0],
                $consign->result()[0],
                $accounting_document->result()[0],
                $b2b_monthly_billing_invoice->result()[0],
                // $propose_po->result()[0],

            );
        }

        return $data;
    }

    // tzuhaw
    public function cancel_po_extend_po_notification($customer_guid, $col_query)
    {
        $query = $this->db->query("SELECT b.*,
        $col_query AS `query`
        FROM lite_b2b.notification_modal_subscribe a
        INNER JOIN lite_b2b.notification_modal b ON a.notification_guid = b.notification_guid
        WHERE b.isactive = 1
        AND a.customer_guid = '$customer_guid'
        ORDER BY b.seq ASC");

        return $query;
    }

    public function sub_dashboard_consignment($db, $customer_guid, $location_in, $scode_in, $scode_where, $dashboard_num_days_data, $check_super_admin)
    {
        if ($scode_where == 1) {
            $scode_where = 'AND scode IN (' . $scode_in . ')';
            $code_where = 'AND code IN (' . $scode_in . ')';
            $sup_code_where = 'AND sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
            $code_where = '';
            $sup_code_where = '';
        }

        $consignss = $this->db->query("SELECT '' as count_doc,
             'ConsignSS' as type,
             'Consignment Monthly Sales Statement' as display_name,
             '#aa6e0e' AS background_color,
             'dashboard/DII.png' AS icon

             /*FROM
             (SELECT *,
                     COUNT(1) AS n_inv,
                     IF(MAX(b2b_inv_no_time) <= '1001-01-01 00:00:00', '', b2b_inv_no_time) AS e_inv_create_time
              FROM $db.acc_trans
              WHERE trans_type = 'INV-CS'
                $scode_where
              GROUP BY LEFT(date_trans, 7),
                       supcus_code)a*/ ");

        $consignsr = $this->db->query("SELECT '' as count_doc,
             'ConsignSR' as type,
             'Consignment Sales Report' as display_name,
             '#421e0e' AS background_color,
             'dashboard/DII.png' AS icon ");

        $consignsrs = $this->db->query("SELECT '' as count_doc,
             'ConsignSRS' as type,
             'Consignment Sales Report Summary' as display_name,
             '#931adf' AS background_color,
             'dashboard/DII.png' AS icon ");

        if ($customer_guid == '13EE932D98EB11EAB05B000D3AA2838A') {
            $data = array(

                $consignss->result()[0],
                $consignsr->result()[0],
                $consignsrs->result()[0],

            );

            #TF, big bataras statement close
        } elseif ($customer_guid == '8D5B38E931FA11E79E7E33210BD612D3' || $customer_guid == '403810171FA711EA9BB8E4E7491C3E1E' || $customer_guid == '1F90F5EF90DF11EA818B000D3AA2CAA9') {

            $data = array(

                $consignss->result()[0],
                $consignsr->result()[0],
                $consignsrs->result()[0],

            );
        } else {

            $data = array(

                $consignss->result()[0],
                $consignsr->result()[0],
                $consignsrs->result()[0],

            );
        }

        return $data;
    }

    // tzu haw  sub_dashboard_accounting_documents
    public function sub_dashboard_accounting_document($db, $customer_guid, $location_in, $scode_in, $scode_where, $dashboard_num_days_data, $check_super_admin)
    {
        if ($scode_where == 1) {
            $scode_where = 'AND scode IN (' . $scode_in . ')';
            $code_where = 'AND code IN (' . $scode_in . ')';
            $sup_code_where = 'AND sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
            $code_where = '';
            $sup_code_where = '';
        }

        $accounting_incoive_sales_invoice = $this->db->query("SELECT '' as count_doc,
             'SIN' as type,
             'Sales Invoice' as display_name,
             '#aa6e0e' AS background_color,
             'dashboard/DII.png' AS icon

             /*FROM
             (SELECT *,
                     COUNT(1) AS n_inv,
                     IF(MAX(b2b_inv_no_time) <= '1001-01-01 00:00:00', '', b2b_inv_no_time) AS e_inv_create_time
              FROM $db.acc_trans
              WHERE trans_type = 'INV-CS'
                $scode_where
              GROUP BY LEFT(date_trans, 7),
                       supcus_code)a*/ ");

        $accounting_incoive_sales_credit_memo = $this->db->query("SELECT '' as count_doc,
             'SCM' as type,
             'Sales Credit Memo' as display_name,
             '#421e0e' AS background_color,
             'dashboard/DII.png' AS icon ");

        $accounting_incoive_payment_voucher = $this->db->query("SELECT '' as count_doc,
             'PVV' as type,
             'Payment Voucher' as display_name,
             '#931adf' AS background_color,
             'dashboard/DII.png' AS icon ");

        $accounting_incoive_payment_discount_rebate = $this->db->query("SELECT '' as count_doc,
            'PVI' as type,
            'Payment Discount/Rebate' as display_name,
            '#931adf' AS background_color,
            'dashboard/DII.png' AS icon ");

        $accounting_incoive_debit_memo = $this->db->query("SELECT '' as count_doc,
            'PCM' as type,
            'Debit Memo' as display_name,
            '#931adf' AS background_color,
            'dashboard/DII.png' AS icon ");

        $accounting_incoive_ap_debit_memo = $this->db->query("SELECT '' as count_doc,
            'PDN' as type,
            'AP Debit Memo' as display_name,
            '#931adf' AS background_color,
            'dashboard/DII.png' AS icon ");

        $accounting_incoive_ap_invoice = $this->db->query("SELECT '' as count_doc,
            'PIN' as type,
            'AP Invoice' as display_name,
            '#931adf' AS background_color,
            'dashboard/DII.png' AS icon ");

        $accounting_incoive_ar_debit_note = $this->db->query("SELECT '' as count_doc,
            'SDN' as type,
            'AR Debit Note' as display_name,
            '#931adf' AS background_color,
            'dashboard/DII.png' AS icon ");

        $accounting_incoive_ar_credit_note = $this->db->query("SELECT '' as count_doc,
            'SVI' as type,
            'AR Credit Note' as display_name,
            '#931adf' AS background_color,
            'dashboard/DII.png' AS icon ");

        if ($customer_guid == '13EE932D98EB11EAB05B000D3AA2838A') {
            $data = array(

                $accounting_incoive_sales_invoice->result()[0],
                $accounting_incoive_sales_credit_memo->result()[0],
                $accounting_incoive_payment_voucher->result()[0],
                $accounting_incoive_payment_discount_rebate->result()[0],
                $accounting_incoive_debit_memo->result()[0],
                $accounting_incoive_ap_debit_memo->result()[0],
                $accounting_incoive_ap_invoice->result()[0],
                $accounting_incoive_ar_debit_note->result()[0],
                $accounting_incoive_ar_credit_note->result()[0],

            );
        } else {

            $data = array(

                $accounting_incoive_sales_invoice->result()[0],
                $accounting_incoive_sales_credit_memo->result()[0],
                $accounting_incoive_payment_voucher->result()[0],
                $accounting_incoive_payment_discount_rebate->result()[0],
                $accounting_incoive_debit_memo->result()[0],
                $accounting_incoive_ap_debit_memo->result()[0],
                $accounting_incoive_ap_invoice->result()[0],
                $accounting_incoive_ar_debit_note->result()[0],
                $accounting_incoive_ar_credit_note->result()[0],

            );
        }

        return $data;
    }

    // tzu haw  sub_dashboard_monthly_billing_invoices
    public function sub_dashboard_monthly_billing_invoices($db, $customer_guid, $location_in, $scode_in, $scode_where, $dashboard_num_days_data, $check_super_admin)
    {
        if ($scode_where == 1) {
            $scode_where = 'AND scode IN (' . $scode_in . ')';
            $code_where = 'AND code IN (' . $scode_in . ')';
            $sup_code_where = 'AND sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
            $code_where = '';
            $sup_code_where = '';
        }

        $monthly_billing_invoices_by_retailer = $this->db->query("SELECT '' as count_doc,
             'IBR' as type,
             'Invoices By Retailer' as display_name,
             '#aa6e0e' AS background_color,
             'dashboard/DII.png' AS icon");

        $monthly_billing_invoices = $this->db->query("SELECT '' as count_doc,
             'INV' as type,
             'Invoices' as display_name,
             '#421e0e' AS background_color,
             'dashboard/DII.png' AS icon ");

        $monthly_billing_invoices_break_down = $this->db->query("SELECT '' as count_doc,
             'IBD' as type,
             'Invoice Break Down' as display_name,
             '#931adf' AS background_color,
             'dashboard/DII.png' AS icon ");

        $official_receipt = $this->db->query("SELECT '' as count_doc,
              'official_receipt' as type,
              'Official Receipt' as display_name,
              '#931adf' AS background_color,
              'dashboard/DII.png' AS icon ");

        $account_statement = $this->db->query("SELECT '' as count_doc,
            'account_statement' as type,
            'Account Statement' as display_name,
            '#931adf' AS background_color,
            'dashboard/DII.png' AS icon ");


        if ($customer_guid == '13EE932D98EB11EAB05B000D3AA2838A') {
            $data = array(

                $monthly_billing_invoices_by_retailer->result()[0],
                $monthly_billing_invoices->result()[0],
                $monthly_billing_invoices_break_down->result()[0],
                $official_receipt->result()[0],
                $account_statement->result()[0],

            );
        } else {

            $data = array(

                $monthly_billing_invoices_by_retailer->result()[0],
                $monthly_billing_invoices->result()[0],
                $monthly_billing_invoices_break_down->result()[0],
                $official_receipt->result()[0],
                $account_statement->result()[0],

            );
        }

        return $data;
    }

    // tzu haw  sub_dashboard_propose_po
    public function sub_dashboard_propose_po($db, $customer_guid, $location_in, $scode_in, $scode_where, $dashboard_num_days_data, $check_super_admin)
    {
        if ($scode_where == 1) {
            $scode_where = 'AND scode IN (' . $scode_in . ')';
            $code_where = 'AND code IN (' . $scode_in . ')';
            $sup_code_where = 'AND sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
            $code_where = '';
            $sup_code_where = '';
        }

        $propose_po = $this->db->query("SELECT '' as count_doc,
             'propose_po' as type,
             'Propose Po' as display_name,
             '#421e0e' AS background_color,
             'dashboard/DII.png' AS icon ");

        $proposed_po = $this->db->query("SELECT '' as count_doc,
             'proposed_po_record' as type,
             'Proposed Po Record' as display_name,
             '#931adf' AS background_color,
             'dashboard/DII.png' AS icon ");

        $proposed_po_supplier = $this->db->query("SELECT '' as count_doc,
              'proposed_po_approval' as type,
              'Proposed Po Approval' as display_name,
              '#931adf' AS background_color,
              'dashboard/DII.png' AS icon ");

        $proposed_po_settings = $this->db->query("SELECT '' as count_doc,
              'propose_po_settings' as type,
              'Proposed Po Settings' as display_name,
              '#931adf' AS background_color,
              'dashboard/DII.png' AS icon ");

        if ($customer_guid == '13EE932D98EB11EAB05B000D3AA2838A') {
            $data = array(

                $propose_po->result()[0],
                $proposed_po->result()[0],
                $proposed_po_supplier->result()[0],
                $proposed_po_settings->result()[0],
            );
        } else {

            $data = array(

                $propose_po->result()[0],
                $proposed_po->result()[0],
                $proposed_po_supplier->result()[0],
                $proposed_po_settings->result()[0],
            );
        }

        return $data;
    }

    public function po_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type)
    {

        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.scode IN (' . $scode_in . ')';
        } else {
            // $scode_where = 'AND a.scode IS NOT NULL';
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(podate,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(podate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(podate, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(podate, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($exp_from == '' && $exp_to == '') {
            $exp_where = "";
        } elseif ($exp_from == '') {
            $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $exp_to . "'";
        } elseif ($exp_to == '') {
            $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '" . $exp_from . "' AND '3000-01-01'";
        } else {
            $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '" . $exp_from . "' AND '" . $exp_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

          a.scode,
          a.sname,
          a.refno,
          a.loc_group,
          DATE_FORMAT(a.podate, '%Y-%m-%d %a') AS doc_date,
          DATE_FORMAT(a.DeliverDate, '%Y-%m-%d %a') AS deliver_date,
          DATE_FORMAT(a.expiry_date, '%Y-%m-%d %a') expiry_date,
          '' AS doc_type,
          a.status AS doc_status,
          FORMAT(a.total_include_tax, 2) AS amount,
          IF(a.tax_code_purchase = '' , '0' , '1' ) AS include_tax,
          a.rejected_remark,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status = '', '#7fbfe1', IF(a.status IN ('viewed', 'printed'), '#048ad1', IF(a.status IN ('expired', 'cancel','rejected'),
          '#ff4e25', IF(a.status IN ('accepted', 'gr_completed'), '#00c92b', '#000000' ) ) ) ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,

          d.reason AS `status`,
          '' AS 'preview_description'

          ";

            $select1 = "

          *

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";

            $select1 = "COUNT(counting) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("
                      SELECT $select1 FROM

                      (
                      SELECT
                      $select
                      FROM b2b_summary.pomain a
                      LEFT JOIN b2b_summary.`po_grn_inv` b
                      ON a.refno = b.`po_refno`
                      AND b.`customer_guid` = '$customer_guid'
                      LEFT JOIN lite_b2b.status_setting c ON a.rejected_remark = c.code
                      AND c.type = 'reject_po'
                      LEFT JOIN
                      " . $this->tb_lite_b2b . ".set_setting d
                      ON a.status = d.code
                      AND d.module_name = 'PO_FILTER_STATUS'
		              WHERE a.status != 'HFSP'
                      AND a.in_kind = '0'
                      $scode_where
                      $refno_where
                      AND a.customer_guid = '$customer_guid'
                      AND loc_group IN
                      (
                         $location_in
                      )
                      $status_in
                      $period_code_where
                      $date_where
                      $exp_where


                      GROUP BY a.refno
                      ORDER BY
                      a.podate DESC
                      )zz


                      $limit ");

        return $query;
    }

    public function gr_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(a.grdate,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        //passing name temporary is $exp_where
        if ($exp_from == '' && $exp_to == '') {
            $exp_where = "";
        } elseif ($exp_from == '') {
            $exp_where = "AND DATE_FORMAT(a.docdate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $exp_to . "'";
        } elseif ($exp_to == '') {
            $exp_where = "AND DATE_FORMAT(a.docdate, '%Y-%m-%d') BETWEEN '" . $exp_from . "' AND '3000-01-01'";
        } else {
            $exp_where = "AND DATE_FORMAT(a.docdate, '%Y-%m-%d') BETWEEN '" . $exp_from . "' AND '" . $exp_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.code AS scode,
            a.name AS sname,
            a.refno,
            a.loc_group,
            DATE_FORMAT(a.grdate, '%Y-%m-%d %a') as doc_date,
            DATE_FORMAT(a.docdate, '%Y-%m-%d %a') as inv_date,
            a.DOno AS dono,
            a.InvNo AS invno,
            '' AS doc_type,
            c.reason AS status,
            a.cross_ref,
            FORMAT(a.total_include_tax, 2) AS amount,
            IF(tax_code_purchase = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status = '', '#7fbfe1', IF(a.status IN ('viewed', 'printed'), '#048ad1', IF(a.status = 'Invoice Generated', '#ff4e25',  IF(a.status = 'confirmed', '#00c92b', '#000000' ) ) ) ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            d.einvno,
            d.inv_date as einvdate

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

          SELECT

          $select

        FROM
          $db.grmain  AS a
          LEFT JOIN  (SELECT * FROM $db.grmain_dncn WHERE customer_guid =  '$customer_guid' GROUP BY refno) AS b
          ON a.refno = b.refno AND a.customer_guid = b.customer_guid
          LEFT JOIN
          " . $this->tb_lite_b2b . ".set_setting c
          ON a.status = c.code
          AND c.module_name = 'GR_FILTER_STATUS'
          LEFT JOIN b2b_summary.einv_main d	
          ON a.refno = d.refno 	
          AND a.customer_guid = d.customer_guid
        WHERE a.customer_guid =  '$customer_guid'
        AND a.loc_group IN ($location_in)
        $refno_where
        $scode_where
        $status_in
        $date_where
        $exp_where
        $period_code_where

        ORDER BY a.grdate DESC

        $limit

        ");

        return $query;
    }

    public function grda_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type)
    {
        if ($refno == '') {
            $refno_where = 'WHERE a.refno IS NOT NULL ';
        } else {
            $refno_where = "WHERE a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND ap_sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(a.grdate,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.grdate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
            $status_in = 'AND b.transtype IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            b.ap_sup_code AS scode,
            a.name AS sname,
            a.refno,
            a.loc_group,
            DATE_FORMAT(a.grdate, '%Y-%m-%d %a') as doc_date,
            DATE_FORMAT(a.docdate, '%Y-%m-%d %a') as inv_date,
            #a.DOno AS dono,
            a.InvNo AS invno,
            '' AS doc_type,
            c.reason AS status,
            FORMAT(b.VarianceAmt, 2) AS amount,
            IF(tax_code_purchase = '' , '0' , '1' ) AS include_tax,
            '0' AS ischeck,
            IF(a.status = '', '#7fbfe1', '#048ad1' ) AS color,
            '0' AS ispreview,
            '' AS 'preview_description',
            b.transtype

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

        SELECT

        $select

        FROM b2b_summary.grmain AS a
        INNER JOIN  (SELECT * from b2b_summary.grmain_dncn WHERE customer_guid = '$customer_guid' group by refno) AS b
        ON a.refno = b.refno  AND a.customer_guid = b.customer_guid
        LEFT JOIN
        " . $this->tb_lite_b2b . ".set_setting c
        ON b.transtype = c.code
        AND c.module_name = 'GRDA_FILTER_DOCTYPE'
        $refno_where
        $scode_where
        $period_code_where
        $date_where
        $status_in
        AND b.`customer_guid` ='$customer_guid'
        AND a.loc_group in ($location_in)

        ORDER BY grdate DESC
        $limit");

        return $query;
    }

    public function prdncn_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $doc_type_where, $doc_type_in, $limit, $offset, $select_type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.RefNo LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(a.DocDate,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        if ($doc_type_where == 1) {
            $doc_type_in = 'AND a.type IN (' . $doc_type_in . ')';
        } else {
            $doc_type_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

          *

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(*) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

          SELECT

          $select

          FROM(

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
          '' AS 'preview_description',
          d.uploaded_image,
          CONCAT(d.location,'/STRB/',DATE_FORMAT(d.doc_date,'%Y%m'),'/',d.batch_no,'/') AS azure_directory_path

        FROM
          $db.dbnotemain AS a
          LEFT JOIN
          " . $this->tb_lite_b2b . ".set_setting b
          ON a.status = b.code
          AND b.module_name = 'PRDNCN_FILTER_DOC_STATUS'
          LEFT JOIN
          " . $this->tb_lite_b2b . ".set_setting c
          ON a.type = c.code
          AND c.module_name = 'PRDNCN_FILTER_STATUS'
          LEFT JOIN $db.dbnote_batch as d
          ON a.DocNo = d.batch_no
          AND a.customer_guid = d.customer_guid
        WHERE a.customer_guid = '$customer_guid'
          $scode_where
          AND a.locgroup IN ($location_in)
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
          '' AS 'preview_description',
          '0' AS uploaded_image,
          '' AS azure_directory_path

        FROM
          $db.cnnotemain AS a
          LEFT JOIN
          " . $this->tb_lite_b2b . ".set_setting b
          ON a.status = b.code
          AND b.module_name = 'PRDNCN_FILTER_DOC_STATUS'
          LEFT JOIN
          " . $this->tb_lite_b2b . ".set_setting c
          ON a.type = c.code
          AND c.module_name = 'PRDNCN_FILTER_STATUS'
        WHERE a.customer_guid = '$customer_guid'
          $scode_where
          AND a.locgroup IN ($location_in)
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

    public function pdncn_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $doc_type_where, $doc_type_in, $limit, $offset, $select_type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.RefNo LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(a.DocDate,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        if ($doc_type_where == 1) {
            $doc_type_in = 'AND a.trans_type IN (' . $doc_type_in . ')';
        } else {
            $doc_type_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

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

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.RefNo) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

          SELECT

          $select

        FROM
          $db.cndn_amt AS a

          LEFT JOIN " . $this->tb_lite_b2b . ".set_setting b
        ON a.status = b.code
        AND b.module_name = 'PDNCN_FILTER_STATUS'
        LEFT JOIN
          " . $this->tb_lite_b2b . ".set_setting c
          ON a.trans_type = c.code
          AND c.module_name = 'PDNCN_FILTER_TYPE'
        WHERE a.customer_guid = '$customer_guid'

          $scode_where
          AND a.loc_group IN ($location_in)
          $status_in
          $doc_type_in
          $refno_where
          $period_code_where
          $date_where

          ORDER BY a.DocDate DESC

          $limit
           ;");

        return $query;
    }

    public function pci_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.inv_refno LIKE '%" . $refno . "%' OR a.promo_refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(a.DocDate,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

          a.sup_code AS scode,
          a.sup_name AS sname,
          a.inv_refno AS refno,
          a.loc_group AS loc_group,
          DATE_FORMAT(a.DocDate, '%Y-%m-%d %a') AS doc_date,
          a.promo_refno AS doc_no,
          a.promo_refno AS invno,
          '' as inv_date,
          '' AS doc_type,
          IF(a.status = '', 'New', a.status ) AS status,
          FORMAT(a.total_net, 2) AS amount,
          IF(tax_code_supply = '' , '0' , '1' ) AS include_tax,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,
          '' AS 'preview_description'

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.inv_refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

          SELECT

          $select


        FROM
          $db.promo_taxinv AS a
        WHERE a.customer_guid = '$customer_guid'
          #AND trans_type IN ('PCNAMT' , 'PDNAMT')
          $scode_where
          AND a.loc_group IN ($location_in)
          $status_in

          $refno_where
          $period_code_where
          $date_where

          ORDER BY a.DocDate DESC

          $limit
           ;");

        return $query;
    }

    public function di_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.inv_refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(a.DocDate,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.DocDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

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

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.inv_refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

          SELECT

          $select


        FROM
          $db.discheme_taxinv AS a
        WHERE a.customer_guid = '$customer_guid'
          #AND trans_type IN ('PCNAMT' , 'PDNAMT')
          $scode_where
          AND a.loc_group IN ($location_in)
          $status_in

          $refno_where
          $period_code_where
          $date_where

          ORDER BY a.DocDate DESC

          $limit
           ;");

        return $query;
    }

    public function rb_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND batch_no LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.sup_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(doc_date,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(doc_date, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(doc_date, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(doc_date, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($exp_from == '' && $exp_to == '') {
            $exp_where = "";
        } elseif ($exp_from == '') {
            $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $exp_to . "'";
        } elseif ($exp_to == '') {
            $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '" . $exp_from . "' AND '3000-01-01'";
        } else {
            $exp_where = "AND DATE_FORMAT(expiry_date, '%Y-%m-%d') BETWEEN '" . $exp_from . "' AND '" . $exp_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND STATUS IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {
            //DATEDIFF(a.expiry_date,now()) as old_due_date,
            $select = "
            dbnote_guid,
            IFNULL(a.b2b_dn_refno, '') AS prdn_refno,
            batch_no as refno,
            LOCATION as loc_group,
            doc_date,
            IF(a.srb_accept_days <= '0', a.expiry_date , DATE_ADD(a.doc_date, INTERVAL a.srb_accept_days DAY)) AS expiry_date,
            sup_code AS scode,
            sup_name AS sname,
            canceled,
            IF(STATUS = '0', 'Pending Accept',
            IF(STATUS = '1', 'Accepted',
            IF(STATUS = '2', 'Pending PRDN',
            IF(STATUS = '3', 'PRDN generated',
            IF(STATUS = '8', 'Amended',
            IF(STATUS = '9', 'Cancel', 'Undefined')))))) AS status,
            IF(STATUS = '0', '#7fbfe1',
            IF(STATUS = '1', '#00c92b',
            IF(STATUS = '2', '#048ad1',
            IF(STATUS = '3', '#048ad1',
            IF(STATUS = '8', '#F77628',
            IF(STATUS = '9', '#ff4e25', '#048ad1')))))) AS color,
            STATUS,
            accepted_at,
            DATEDIFF(IF(a.srb_accept_days <= '0', a.expiry_date , DATE_ADD(a.doc_date, INTERVAL a.srb_accept_days DAY)),NOW()) AS due_date, 
            cancel_remark,
            IF(STATUS ='8'OR STATUS = '9','0',uploaded_image) AS uploaded_image,
            CONCAT(a.location,'/STRB/',DATE_FORMAT(a.doc_date,'%Y%m'),'/',a.batch_no,'/') AS azure_directory_path
          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(batch_no) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

          SELECT

          $select


        FROM
          $db.dbnote_batch AS a
          INNER JOIN b2b_summary.supcus AS b
          ON a.sup_code = b.`code` 
          AND a.customer_guid = b.customer_guid 
          AND b.b2b_registration = '1'
        WHERE a.customer_guid = '$customer_guid'

          AND LOCATION IN ($location_in)
          $status_in
          $refno_where
          $period_code_where
          $date_where
          $exp_where
          $scode_where
          ORDER BY FIELD(STATUS,'0', '1', '2', '3' ,'9','8') ASC, expiry_date ASC
          $limit
           ;");

        return $query;
    }

    public function ConsignSS_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $search_value, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $filter_supplier)
    {
        if ($search_value == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND supcus_name LIKE '%" . $search_value . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND supcus_code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(date_trans,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND date_trans BETWEEN '1900-01-01' AND '$date_to'";
        } elseif ($date_to == '') {
            $date_where = "AND date_trans BETWEEN '$date_from' AND '3000-01-01'";
        } else {
            $date_where = "AND date_trans BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
            $status_in = 'AND status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        // only everrise split half month
        if ($customer_guid == 'D361F8521E1211EAAD7CC8CBB8CC0C93' || $customer_guid == 'B00CA0BE403611EBA2FC000D3AC8DFD7') {
            $add_group_by = 'date_trans';
            $add_left_join_on = 'a.date_trans = b.date_trans';
        } else {
            $add_group_by = 'LEFT(date_trans, 7)';
            $add_left_join_on = 'LEFT(a.date_trans,7) = LEFT(b.date_trans,7)';
        }

        if ($filter_supplier == '') {
            $condition_filter = "";
        } else {
            $condition_filter = "AND supcus_code = '$filter_supplier'";
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "
            c.acc_name,
            a.company_id,
            a.company_name,
            a.supcus_code AS scode,
            a.supcus_name AS sname,
            a.refno AS refno,
            a.locgroup AS loc_group,
            DATE_FORMAT(a.date_trans, '%Y-%m-%d %a') AS doc_date,
            DATE_FORMAT(a.date_trans, '%Y-%m') AS period_code,
            DATE_FORMAT(a.date_from, '%Y-%m-%d %a') AS date_from,
            DATE_FORMAT(a.date_to, '%Y-%m-%d %a') AS date_to,
            a.e_inv_create_time,
            a.sup_doc_no AS doc_no,
            a.b2b_inv_no AS invno,
            a.b2b_inv_no_time AS inv_date,
            a.trans_type AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            IF(a.n_inv = b.gen_inv, 'Invoice Generated', '') AS doc_status,
            FORMAT(a.total_inc_tax, 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            a.date_trans


          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

          SELECT

          $select

        FROM
             (SELECT *,
                     COUNT(1) AS n_inv,
                     IF(MAX(b2b_inv_no_time) <= '1001-01-01 00:00:00', '', b2b_inv_no_time) AS e_inv_create_time
              FROM $db.acc_trans
              WHERE trans_type = 'INV-CS'
                AND locgroup IN ($location_in)
                $refno_where
                $period_code_where
                $date_where
                $scode_where
                $status_in
                $condition_filter
              GROUP BY $add_group_by,
                        unique_key,
                       company_id)a
           LEFT JOIN
             (SELECT *,
                     COUNT(1) AS gen_inv
              FROM $db.acc_trans
              WHERE trans_type = 'INV-CS'
                AND STATUS = 'Invoice Generated'
                AND locgroup IN ($location_in)
                $date_where
                $scode_where
                $status_in
                $condition_filter
              GROUP BY $add_group_by,
                        unique_key,
                       company_id)b
            ON $add_left_join_on
            AND a.unique_key = b.unique_key
            AND a.company_id = b.company_id
            INNER JOIN lite_b2b.acc AS c
            WHERE c.acc_guid = '$customer_guid'

            #ORDER BY a.date_trans DESC
            ORDER BY a.supcus_name ASC

            $limit
           ;");

        return $query;
    }

    // tzuhaw sals invoice list
    public function Sales_Invoice_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

          a.supcode AS scode,
          a.supname AS sname,
          a.refno AS refno,
          '' AS loc_group,
          DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
          '' AS doc_no,
          a.refno AS invno,
          '' AS inv_date,
          a.doctype AS doc_type,
          IF(a.status = '', 'New', a.status ) AS status,
          FORMAT('', 2) AS amount,
          IF('' = '' , '0' , '1' ) AS include_tax,
          IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
          IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
          IF(a.status = '', '1', '0' ) AS ispreview,
          '' AS 'preview_description',
          IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

          SELECT

          $select

        FROM
          $db.other_doc AS a
          LEFT JOIN $db.supcus b
          ON a.customer_guid = b.customer_guid
          AND a.supcode = b.code
          AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
          #AND trans_type IN ('' , 'viewed', 'printed')
          $scode_where
          $status_in
          $refno_where
          $date_where
          AND a.doctype IN ('$type')
          #ORDER BY a.doctime DESC
          $limit
           ;");
        return $query;
    }
    // tzuhaw sals credit memo list
    public function Sales_Credit_Memo_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.supcode AS scode,
            a.supname AS sname,
            a.refno AS refno,
            '' AS loc_group,
            DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
            '' AS doc_no,
            a.refno AS invno,
            '' AS inv_date,
            a.doctype AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            FORMAT('', 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code
       ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

          SELECT

          $select

        FROM
          $db.other_doc AS a
          LEFT JOIN $db.supcus b
          ON a.customer_guid = b.customer_guid
          AND a.supcode = b.code
          AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
          #AND trans_type IN ('' , 'viewed', 'printed')
          $scode_where
          $status_in
          $refno_where
          $date_where
          AND a.doctype IN ('$type')
          #ORDER BY a.doctime DESC
          $limit
           ;");
        return $query;
    }
    // tzuhaw payment voucher list
    public function Payment_Voucher_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.supcode AS scode,
            a.supname AS sname,
            a.refno AS refno,
            '' AS loc_group,
            DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
            '' AS doc_no,
            a.refno AS invno,
            '' AS inv_date,
            a.doctype AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            FORMAT('', 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code

     ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

        SELECT

        $select

        FROM
        $db.other_doc AS a
        LEFT JOIN $db.supcus b
        ON a.customer_guid = b.customer_guid
        AND a.supcode = b.code
        AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
        #AND trans_type IN ('' , 'viewed', 'printed')
        $scode_where
        $status_in
        $refno_where
        $date_where
        AND a.doctype IN ('$type')
        #ORDER BY a.doctime DESC
        $limit
        ;");
        return $query;
    }
    // tzuhaw payment discount/rebate list
    public function Payment_Discount_Rebate_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.supcode AS scode,
            a.supname AS sname,
            a.refno AS refno,
            '' AS loc_group,
            DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
            '' AS doc_no,
            a.refno AS invno,
            '' AS inv_date,
            a.doctype AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            FORMAT('', 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code
            ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

        SELECT

        $select

        FROM
        $db.other_doc AS a
        LEFT JOIN $db.supcus b
        ON a.customer_guid = b.customer_guid
        AND a.supcode = b.code
        AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
        #AND trans_type IN ('' , 'viewed', 'printed')
        $scode_where
        $status_in
        $refno_where
        $date_where
        AND a.doctype IN ('$type')
        #ORDER BY a.doctime DESC
        $limit
        ;");
        return $query;
    }
    // tzuhaw debit memo list
    public function Debit_Memo_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.supcode AS scode,
            a.supname AS sname,
            a.refno AS refno,
            '' AS loc_group,
            DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
            '' AS doc_no,
            a.refno AS invno,
            '' AS inv_date,
            a.doctype AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            FORMAT('', 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code

            ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

        SELECT

        $select

        FROM
        $db.other_doc AS a
        LEFT JOIN $db.supcus b
        ON a.customer_guid = b.customer_guid
        AND a.supcode = b.code
        AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
        #AND trans_type IN ('' , 'viewed', 'printed')
        $scode_where
        $status_in
        $refno_where
        $date_where
        AND a.doctype IN ('$type')
        #ORDER BY a.doctime DESC
        $limit
        ;");
        return $query;
    }

    public function Payment_debit_note_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.supcode AS scode,
            a.supname AS sname,
            a.refno AS refno,
            '' AS loc_group,
            DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
            '' AS doc_no,
            a.refno AS invno,
            '' AS inv_date,
            a.doctype AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            FORMAT('', 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code

            ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

        SELECT

        $select

        FROM
        $db.other_doc AS a
        LEFT JOIN $db.supcus b
        ON a.customer_guid = b.customer_guid
        AND a.supcode = b.code
        AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
        #AND trans_type IN ('' , 'viewed', 'printed')
        $scode_where
        $status_in
        $refno_where
        $date_where
        AND a.doctype IN ('$type')
        #ORDER BY a.doctime DESC
        $limit
        ;");
        return $query;
    }

    public function ap_invoice($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.supcode AS scode,
            a.supname AS sname,
            a.refno AS refno,
            '' AS loc_group,
            DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
            '' AS doc_no,
            a.refno AS invno,
            '' AS inv_date,
            a.doctype AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            FORMAT('', 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code

            ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

        SELECT

        $select

        FROM
        $db.other_doc AS a
        LEFT JOIN $db.supcus b
        ON a.customer_guid = b.customer_guid
        AND a.supcode = b.code
        AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
        #AND trans_type IN ('' , 'viewed', 'printed')
        $scode_where
        $status_in
        $refno_where
        $date_where
        AND a.doctype IN ('$type')
        #ORDER BY a.doctime DESC
        $limit
        ;");
        return $query;
    }

    public function ar_debit_note($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.supcode AS scode,
            a.supname AS sname,
            a.refno AS refno,
            '' AS loc_group,
            DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
            '' AS doc_no,
            a.refno AS invno,
            '' AS inv_date,
            a.doctype AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            FORMAT('', 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code

            ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

        SELECT

        $select

        FROM
        $db.other_doc AS a
        LEFT JOIN $db.supcus b
        ON a.customer_guid = b.customer_guid
        AND a.supcode = b.code
        AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
        #AND trans_type IN ('' , 'viewed', 'printed')
        $scode_where
        $status_in
        $refno_where
        $date_where
        AND a.doctype IN ('$type')
        #ORDER BY a.doctime DESC
        $limit
        ;");
        return $query;
    }

    public function ar_credit_note($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type, $type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.refno LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.supcode IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '1900-01-01' AND '" . $date_to . "'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.doctime, '%Y-%m-%d') BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "

            a.supcode AS scode,
            a.supname AS sname,
            a.refno AS refno,
            '' AS loc_group,
            DATE_FORMAT(a.doctime, '%Y-%m-%d %a') AS doc_date,
            '' AS doc_no,
            a.refno AS invno,
            '' AS inv_date,
            a.doctype AS doc_type,
            IF(a.status = '', 'New', a.status ) AS status,
            FORMAT('', 2) AS amount,
            IF('' = '' , '0' , '1' ) AS include_tax,
            IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description',
            IF(b.code IS NULL, a.supcode, b.accpdebit) AS debtor_code

            ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.refno) AS counting";
        } else {

            echo 'select type not found';
            die;
        }
        $query = $this->db->query("

        SELECT

        $select

        FROM
        $db.other_doc AS a
        LEFT JOIN $db.supcus b
        ON a.customer_guid = b.customer_guid
        AND a.supcode = b.code
        AND b.type <> 'C'
        WHERE a.customer_guid = '$customer_guid'
        #AND trans_type IN ('' , 'viewed', 'printed')
        $scode_where
        $status_in
        $refno_where
        $date_where
        AND a.doctype IN ('$type')
        #ORDER BY a.doctime DESC
        $limit
        ;");
        return $query;
    }

    public function si_list($db, $customer_guid, $location_in, $scode_in, $scode_where, $status_in, $refno, $period_code, $date_from, $date_to, $exp_from, $exp_to, $status_where, $limit, $offset, $select_type)
    {
        if ($refno == '') {
            $refno_where = '';
        } else {
            $refno_where = "AND a.RefNo LIKE '%" . $refno . "%'";
        }

        if ($scode_where == 1) {
            $scode_where = 'AND a.Code IN (' . $scode_in . ')';
        } else {
            $scode_where = '';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = "AND left(a.InvoiceDate,7)  = '" . $period_code . "'";
        }

        if ($date_from == '' && $date_to == '') {
            $date_where = "";
        } elseif ($date_from == '') {
            $date_where = "AND DATE_FORMAT(a.InvoiceDate, '%Y-%m-%d') BETWEEN '1900-01-01' AND '$date_to'";
        } elseif ($date_to == '') {
            $date_where = "AND DATE_FORMAT(a.InvoiceDate, '%Y-%m-%d') BETWEEN '$date_from' AND '3000-01-01'";
        } else {
            $date_where = "AND DATE_FORMAT(a.InvoiceDate, '%Y-%m-%d') BETWEEN '$date_from' AND '$date_to'";
        }

        if ($status_where == 1) {
            $status_in = 'AND a.status IN (' . $status_in . ')';
        } else {
            $status_in = '';
        }

        $limit = "LIMIT " . $limit . " OFFSET " . $offset;

        if ($select_type == 'data') {

            $select = "
            a.RefNo AS refno,a.loc_group,a.Code as scode,a.supplier_name as sname,a.InvoiceDate AS inv_date,a.DocNo AS dono,ROUND(JSON_UNQUOTE(JSON_EXTRACT(a.`si_json_info`,'$.simain[0].Total')), 2) AS amount,
            ROUND(JSON_UNQUOTE(JSON_EXTRACT(a.`si_json_info`,'$.simain[0].gst_tax_sum')), 2) AS tax,
            ROUND(JSON_UNQUOTE(JSON_EXTRACT(a.`si_json_info`,'$.simain[0].total_include_tax')), 2) AS total_include_tax,
            IF(a.status='','New',a.status) AS status,
            #IF(a.status IN('', 'viewed', 'printed'), 1, 0 ) AS ischeck,
            IF(a.status IN('', 'viewed', 'printed'), '#7fbfe1', '#ff4e25' ) AS color,
            #IF(a.status = '', '1', '0' ) AS ispreview,
            '' AS 'preview_description'

          ";
        } elseif ($select_type == 'count') {

            $select = "COUNT(a.RefNo) AS counting";
        } else {

            echo 'select type not found';
            die;
        }

        $query = $this->db->query("

          SELECT

          $select


        FROM
          $db.simain_info AS a
        WHERE a.customer_guid = '$customer_guid'
          $scode_where
          AND a.loc_group IN ($location_in)
          $status_in
          $refno_where
          $period_code_where
          $date_where
          ORDER BY a.InvoiceDate DESC

          $limit
           ;");

        return $query;
    }

    public function status($module_name)
    {

        $query = $this->db->query("SELECT module_name,IF(module_name = 'GRDA_FILTER_DOCTYPE' AND reason = 'ALL', 'ALL', code) AS code, reason FROM " . $this->tb_lite_b2b . ".`set_setting` WHERE module_name = '$module_name' ORDER BY reason ASC ");

        return $query;
    }

    public function status_setting($type)
    {

        $query = $this->db->query("SELECT setting_guid,code,portal_description AS reason FROM " . $this->tb_lite_b2b . ".status_setting where type = '$type' AND isactive = 1 ORDER BY portal_description ASC");

        return $query;
    }

    public function status_setting_info($setting_guid)
    {

        $query = $this->db->query("SELECT * FROM " . $this->tb_lite_b2b . ".status_setting WHERE setting_guid = '$setting_guid' ");

        return $query;
    }

    public function po_info($refno, $db, $customer_guid)
    {

        /*$query = $this->db->query("SELECT
        refno,
        if(status = '', 'Pending', status) AS status,
        rejected_remark,
        scode FROM $db.pomain WHERE refno = '$refno' AND customer_guid = '$customer_guid'");*/
        $this->db->where('customer_guid', $customer_guid);
        $this->db->where_in('refno', $refno);

        $this->db->select("refno, if(status = '', 'Pending', status) AS status, REPLACE(scode,'/','+-+') AS scode,loc_group,SName");
        $this->db->from("$db.pomain");
        return $this->db->get();
    }

    public function grn_info($refno, $db, $customer_guid)
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
                    , REPLACE(a.code,'/','+-+') AS scode
                    FROM $db.grmain AS a
                    LEFT JOIN $db.grmain_proposed AS b
                    ON a.refno = b.refno
                    AND a.customer_guid = b.customer_guid where a.refno = '$refno' and a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function grda_info($refno, $db, $customer_guid)
    {
        $query = $this->db->query("SELECT
                    a.`RefNo` AS refno
                    , a.`transtype` AS status
                    , REPLACE(a.code,'/','+-+') AS scode
                    FROM $db.grmain_dncn AS a
                    WHERE a.refno = '$refno' and a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function prdncn_info($set_row, $db, $DBNOTE_table, $customer_guid, $refno, $pdncn)
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
          REPLACE(a.Code,'/','+-+') AS scode,
          IFNULL(c.uploaded_image,0) AS uploaded_image,
          CONCAT(c.location,'/STRB/',DATE_FORMAT(c.doc_date,'%Y%m'),'/',c.batch_no,'/') AS azure_directory_path
          FROM $db.$DBNOTE_table AS a 
          LEFT JOIN (SELECT * FROM $db.ecn_main WHERE customer_guid = '$customer_guid' AND refno = '$refno' AND `type` = 'PRDNCN') AS b 
          ON a.refno = b.refno
          LEFT JOIN b2b_summary.dbnote_batch as c
          ON a.DocNo = c.batch_no
          AND a.customer_guid = c.customer_guid
          WHERE a.refno = '$refno' 
          AND a.customer_guid = '$customer_guid' 
          AND a.type = '$pdncn' ");

        return $query;
    }

    public function pdncn_info($refno, $db, $customer_guid)
    {
        $query = $this->db->query("SELECT
                    a.`refno` AS refno
                    , a.`trans_type` AS status
                    , REPLACE(a.code,'/','+-+') AS scode
                    , a.trans_type
                    FROM $db.cndn_amt AS a
                    WHERE a.refno = '$refno' and a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function pci_info($refno, $db, $customer_guid)
    {
        $query = $this->db->query("SELECT
                    a.`promo_refno` AS refno
                    , a.inv_refno
                    , a.`status` AS status
                    , REPLACE(a.sup_code,'/','+-+') AS scode
                    FROM $db.promo_taxinv AS a
                    WHERE a.inv_refno = '$refno' and a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function di_info($refno, $db, $customer_guid)
    {
        $query = $this->db->query("SELECT
                    a.`inv_refno` AS refno
                    , a.`status` AS status
                    , REPLACE(a.sup_code,'/','+-+') AS scode
                    FROM $db.discheme_taxinv AS a
                    WHERE a.inv_refno = '$refno' and a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function rb_info($refno, $db, $customer_guid)
    {
        $query = $this->db->query("SELECT
                    a.`batch_no` AS refno
                    , a.`status` AS status,
                    IF(STATUS ='8'OR STATUS = '9','0',uploaded_image) AS uploaded_image,
                    CONCAT(a.location,'/STRB/',DATE_FORMAT(a.doc_date,'%Y%m'),'/',a.batch_no,'/') AS azure_directory_path
                    , REPLACE(a.sup_code,'/','+-+') AS scode
                    FROM $db.dbnote_batch AS a
                    WHERE a.batch_no = '$refno' and a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function si_info($refno, $db, $customer_guid)
    {
        $query = $this->db->query("SELECT a.refno,a.status,a.code
        FROM $db.simain_info AS a
        WHERE a.refno = '$refno'
        AND a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function ConsignSS_info($refno, $db, $customer_guid)
    {

        return $this->db->get();
    }
    //tzuhaw
    public function accounting_docuemnts_info($refno, $customer_guid, $code)
    {
        $query = $this->db->query("SELECT * FROM b2b_summary.other_doc WHERE refno = '$refno' AND customer_guid = '$customer_guid' AND doctype = '$code' LIMIT 1");

        return $query;
    }

    //tzuhaw
    public function get_other_doc_table($customer_guid, $type)
    {
        $query = $this->db->query("SELECT a.table FROM " . $this->tb_lite_b2b . ".other_doc_setting AS a WHERE a.customer_guid = '$customer_guid' AND a.code = '$type'");

        return $query;
    }

    public function menu($type)
    {

        $query = $this->db->query("SELECT * FROM " . $this->tb_lite_b2b . ".menu WHERE type = '$type'");

        return $query;
    }

    public function menu_filename($type, $code, $refno)
    {

        if ($type == 'PDN' || $type == 'PCN') {
            $column_type = 'PDNCN';
        } else {
            $column_type = $type;
        }

        $query = $this->db->query("SELECT REPLACE(REPLACE(REPLACE(filename_format, 'type', '$type'), 'code', '$code'), 'refno' , '$refno') AS query FROM " . $this->tb_lite_b2b . ".menu WHERE type = '$column_type'")->row('query');

        return $query;
    }

    public function acc_module_group()
    {

        $query = $this->db->query("SELECT acc_module_group_guid FROM " . $this->tb_lite_b2b . ".acc_module_group WHERE acc_module_group_name = 'Panda B2B'");

        return $query;
    }

    public function announcement($customer_guid)
    {

        $query = $this->db->query("SELECT title,content,publish_at FROM " . $this->tb_lite_b2b . ".announcement WHERE customer_guid = '$customer_guid' AND posted= '1' AND now() >= publish_at AND acknowledgement=0 ORDER BY publish_at DESC, created_at DESC LIMIT 100 ");

        return $query;
    }

    public function notification($customer_guid, $user_guid, $location_in, $list_where, $db, $redirect)
    {

        if ($redirect == '0') {
            $query = $this->db->query("SELECT
          a.app_notification_guid,
          a.`type`,
          a.ref_no,
          a.title,
          a.message,
          DATE(a.created_at) AS created_at,
          a.isread,
          b.acc_name,
          IFNULL(c.dbnote_guid,'') AS dbnote_guid 
          FROM " . $this->tb_lite_b2b_apps . ".app_notification a

          INNER JOIN
          " . $this->tb_lite_b2b . ".acc b
          ON a.customer_guid = b.acc_guid
          LEFT JOIN b2b_summary.dbnote_batch AS c
          ON a.ref_no = c.batch_no
          WHERE a.user_guid = '$user_guid' AND
          a.customer_guid = '$customer_guid' AND
          a.branch_code IN ($location_in) AND
          a.issend = '1'
          $list_where");
        } elseif ($redirect == '1') {

            $query = $this->db->query("SELECT
          a.app_notification_guid,
          a.`type`,
          a.ref_no,
          a.title,
          a.message,
          DATE(a.created_at) AS created_at,
          a.isread,
          IF(a.type = 'PO',c.description, d.description) AS preview_description,
          b.acc_name

          FROM " . $this->tb_lite_b2b_apps . ".app_notification a

          INNER JOIN
          " . $this->tb_lite_b2b . ".acc b
          ON a.customer_guid = b.acc_guid

          LEFT JOIN
          $db.pochild c
          ON a.ref_no = c.refno
          AND c.line = '1'

          LEFT JOIN
          $db.grchild d
          ON a.ref_no = d.refno
          AND d.line = '1'

          WHERE a.user_guid = '$user_guid' AND
          a.customer_guid = '$customer_guid' AND
          a.branch_code IN ($location_in) AND
          a.issend = '1'
          $list_where");
        } else {

            echo 'Redirect Version Incorrect';
            die;
        }

        return $query;
    }

    public function period_code()
    {

        /*$query = $this->db->query("SELECT
        LEFT(`b2b_summary`.`pomain`.`PODate`,7) AS `period_code`
        FROM `b2b_summary`.`pomain`
        WHERE (`b2b_summary`.`pomain`.`PODate` BETWEEN(CURDATE() + INTERVAL - (5)MONTH)
        AND CURDATE())
        GROUP BY LEFT(`b2b_summary`.`pomain`.`PODate`,7)
        ORDER BY `b2b_summary`.`pomain`.`PODate` DESC");*/

        $query = $this->db->query("
      SELECT DATE_FORMAT(NOW(),'%Y-%m') AS period_code
      UNION ALL
      SELECT DATE_FORMAT(NOW() - INTERVAL 1 MONTH ,'%Y-%m') AS period_code
      UNION ALL
      SELECT DATE_FORMAT(NOW() - INTERVAL 2 MONTH ,'%Y-%m') AS period_code
      UNION ALL
      SELECT DATE_FORMAT(NOW() - INTERVAL 3 MONTH ,'%Y-%m') AS period_code
      UNION ALL
      SELECT DATE_FORMAT(NOW() - INTERVAL 4 MONTH ,'%Y-%m') AS period_code
      UNION ALL
      SELECT DATE_FORMAT(NOW() - INTERVAL 5 MONTH ,'%Y-%m') AS period_code");

        return $query;
    }

    public function app_image_path()
    {

        $query = $this->db->query("SELECT `value`
        FROM " . $this->tb_lite_b2b_apps . ".config
        WHERE type = 'apps_image' ");

        return $query;
    }

    public function download_path()
    {

        $query = $this->db->query("SELECT `value`
        FROM " . $this->tb_lite_b2b_apps . ".config
        WHERE type = 'download_path' ");

        return $query;
    }

    public function get_filepath_config($device, $module, $type, $code)
    {
        $query = $this->db->query("SELECT value
        FROM " . $this->tb_lite_b2b . ".config
        WHERE device = '$device'
        AND module = '$module'
        AND type = '$type'
        AND code = '$code'");

        return $query;
    }

    public function grmain_dncn($db, $customer_guid, $refno)
    {

        $query = $this->db->query("SELECT a.customer_guid, @row:=@row+1 AS rowx,
        IFNULL(b.ecn_guid, 'Pending') AS ecn_guid,
        IFNULL(b.status, 'Pending' ) AS ecn_status,
        IFNULL(b.type, 'Pending') AS ecn_type,   ext_doc1 ,
        ifnull(ext_date1, curdate()) as ext_date1,
        IFNULL(b.posted, '0') as posted, a.status, a.location, a.RefNo, a.VarianceAmt,
        a.Created_at, a.Created_by, a.Updated_at, a.Updated_by, a.hq_update, a.EXPORT_ACCOUNT,
        a.EXPORT_AT, a.EXPORT_BY, a.transtype, a.share_cost, a.gst_tax_sum, a.gst_adjust,
        a.gl_code, a.tax_invoice, a.ap_sup_code, a.refno2, a.rounding_adj, a.sup_cn_no,
        a.sup_cn_date, a.dncn_date, a.dncn_date_acc
        FROM $db.grmain_dncn AS a
        LEFT JOIN (SELECT *
        FROM $db.ecn_main
        WHERE customer_guid = '$customer_guid'
        AND refno = '$refno' ) AS b
        ON a.refno = b.refno
        AND a.transtype = b.type
        WHERE a.refno = '$refno'
        AND a.customer_guid = '$customer_guid'
        order by transtype asc ");

        return $query;
    }

    public function einv_main($db, $customer_guid, $refno)
    {

        $query = $this->db->query("SELECT * FROM $db.einv_main where refno = '$refno' and customer_guid = '$customer_guid'");

        return $query;
    }

    public function multiple_login_checking()
    {

        $query = $this->db->query("SELECT value FROM " . $this->tb_lite_b2b_apps . ".config WHERE `type` = 'apps_multiple_login'");

        return $query;
    }

    public function set_setting_reason_by_code($module_name, $code)
    {

        $this->db->where('module_name', $module_name);
        $this->db->where_in('code', $code);
        $this->db->select("reason");
        $this->db->from($this->tb_lite_b2b . ".set_setting");

        return $this->db->get();
    }

    public function email_group($loc_group, $customer_guid)
    {

        $query = $this->db->query("SELECT a.user_id as email,a.user_name as first_name
                    FROM lite_b2b.set_user a
                    INNER JOIN lite_b2b.set_user_group b
                    ON a.user_group_guid = b.user_group_guid
                    INNER JOIN lite_b2b.set_user_module c
                    ON b.user_group_guid = c.user_group_guid
                    INNER JOIN lite_b2b.set_module d
                    ON c.module_guid = d.module_guid
                    INNER JOIN lite_b2b.set_module_group e
                    ON d.module_group_guid = e.module_group_guid
                    INNER JOIN lite_b2b.set_user_branch f
                    ON a.user_guid = f.user_guid
                    INNER JOIN lite_b2b.acc_branch g
                    ON f.branch_guid = g.branch_guid
                    INNER JOIN lite_b2b.acc_concept h
                    ON g.concept_guid = h.concept_guid

                    WHERE a.isactive = 1 AND a.acc_guid = '$customer_guid' AND e.module_group_name = 'Panda B2B' AND c.isenable = 1 AND d.module_code = 'RERPO' AND f.acc_guid = '$customer_guid' AND h.acc_guid = '$customer_guid' AND g.branch_code = '$loc_group' GROUP BY a.user_guid");

        return $query;
    }

    public function supplier_checklist($customer_guid, $status)
    {

        if ($status == "'All'") {
            $status_where = '';
        } else {
            $status_where = 'WHERE b.status IN (' . $status . ')';
        }

        $query = $this->db->query("SELECT
          #IFNULL(a.customer_guid, b.customer_guid) AS customer_guid

          IFNULL(a.AccountCode, b.AccountCode) AS `AccountCode`
          , IFNULL(a.name, b.name) AS `name`
          , IFNULL(b.sup_name, '') AS sup_name
          #, IFNULL(b.remark, '') AS remark
          , IF(b.remark1 IS NULL OR b.remark1 = '', '', b.remark1) AS remark1
          , IFNULL(b.PIC, '' ) AS PIC_email
          , IFNULL(b.tel, '') AS tel
          , IFNULL(b.status, '') AS status
          , IF(b.status = '' , '#36815f', '#86815f') AS status_color
          , IF(consign = '1', CONCAT('CONSIGN'), CONCAT('OUTRIGHT')) AS supply_type
          , IF(consign = '1', '#36815f', '#86815f') AS supply_type_color
          #, IFNULL(a.type, b.type) AS type
          #, IFNULL(a.code, b.code) AS `code`

          #, IFNULL(a.reg_no, b.reg_no) AS `reg_no`
          #, IFNULL(a.block, b.block) AS `block`

          #, IFNULL(a.supcus_guid, b.supcus_guid) AS supcus_guid
          #, IFNULL(b.IsActive, '') AS IsActive
          #, IFNULL(IF(b.PAYMENT = '',0,b.PAYMENT),0) AS PAYMENT


          #, IFNULL(b.invoice_no, '') AS invoice_no


          #, IFNULL(b.ACCEPT_FORM, '') AS ACCEPT_FORM
          #, IFNULL(b.REG_FORM, '') AS REG_FORM
          #, IFNULL(b.training_pax, '') AS training_pax

          #, '' AS folder

          FROM
          (SELECT * FROM b2b_summary.supcus WHERE customer_guid = '$customer_guid' AND TYPE IN ('S','P')) a
          LEFT JOIN
          (SELECT  a.type,a.block,a.customer_guid, a.code, a.name, a.supcus_guid,a.reg_no,a.AccountCode,
          MAX(b.remark1) AS remark1
          ,MAX(CASE WHEN b.`title1` = 'IsActive' THEN b.`value1` ELSE NULL END ) AS IsActive
          , MAX(CASE WHEN b.`title1` = 'PAYMENT' THEN b.`value1` ELSE NULL END ) AS PAYMENT
          , MAX(CASE WHEN b.`title1` = 'PIC' THEN b.`value1` ELSE NULL END ) AS PIC
          , MAX(CASE WHEN b.`title1` = 'sup_name' THEN b.`value1` ELSE NULL END ) AS sup_name
          , MAX(CASE WHEN b.`title1` = 'STATUS' THEN b.`value1` ELSE NULL END ) AS STATUS
          , MAX(CASE WHEN b.`title1` = 'invoice_no' THEN b.`value1` ELSE NULL END ) AS invoice_no
          , MAX(CASE WHEN b.`title1` = 'tel' THEN b.`value1` ELSE NULL END ) AS tel
          , MAX(CASE WHEN b.`title1` = 'ACCEPT_FORM' THEN b.`value1` ELSE NULL END ) AS ACCEPT_FORM
          , MAX(CASE WHEN b.`title1` = 'REG_FORM' THEN b.`value1` ELSE NULL END ) AS REG_FORM
          , MAX(CASE WHEN b.`title1` = 'training_pax' THEN b.`value1` ELSE NULL END ) AS training_pax
          , MAX(CASE WHEN b.`title1` = 'STATUS' THEN b.`remark1` ELSE NULL END ) AS remark
           FROM b2b_summary.supcus  AS a
          LEFT JOIN lite_b2b.`supplier_checklist` AS b
          ON a.`customer_guid` = b.`customer_guid`
          AND a.code = b.scode
          WHERE a.`customer_guid` = '$customer_guid'
          GROUP BY scode,TYPE
          ORDER BY name ASC
          ) b
          ON a.customer_guid = b.customer_guid
          AND a.code = b.code
          AND a.supcus_guid = b.supcus_guid
          $status_where
          ORDER BY FIELD( b.status, 'PAID', 'PENDING ACC CREATION', 'PENDING DOCUMENT', 'PENDING PAYMENT' , 'INVOICED_P_PAYMENT') DESC");

        return $query;
    }

    //tzuhaw get supplier guid
    public function get_supplier_guid($user_guid)
    {
        $query = $this->db->query("SELECT supplier_guid
        from " . $this->tb_lite_b2b . ".set_supplier_user_relationship
        WHERE user_guid = '$user_guid'");

        return $query;
    }

    public function get_customer_supplier_guid($customer_guid, $user_guid, $module_code)
    {
        if (in_array('IAVA', $module_code)) {
            $select = "a.supplier_guid, a.supplier_name";

            $from_database = "" . $this->tb_lite_b2b . ".set_supplier a";

            $condition = "ON a.supplier_guid = b.supplier_guid
            AND b.customer_guid = '$customer_guid'
            GROUP BY a.supplier_guid
            ORDER BY a.supplier_name ASC";
        } else {
            $select = "c.supplier_guid, c.supplier_name";

            $from_database = "" . $this->tb_lite_b2b . ".`set_supplier_user_relationship` a";

            $condition = "ON a.`supplier_group_guid` = b.`supplier_group_guid`
            AND b.`customer_guid` = '$customer_guid'
            INNER JOIN lite_b2b.`set_supplier` c
            ON b.`supplier_guid` = c.`supplier_guid`
            WHERE a.user_guid = '$user_guid'
            AND a.`customer_guid` = '$customer_guid'
            GROUP BY b.`supplier_guid`
            ORDER BY c.supplier_name ASC";
        }
        $query = $this->db->query("SELECT $select
        FROM $from_database
        INNER JOIN " . $this->tb_lite_b2b . ".set_supplier_group b
        $condition");

        return $query;
    }

    public function check_is_b2b_invoice($inv_no, $type)
    {
        // if ($type == 'inv') {
        //     $query = $this->db->query("SELECT *
        //     FROM b2b_invoice.supplier_monthly_main
        //     WHERE invoice_number = '$inv_no'");
        // } else if ($type == 'receipt') {
        //     $query = $this->db->query("SELECT *
        //     FROM b2b_invoice.autocount_payment_hub
        //     WHERE PayDocNo = '$inv_no'");
        // } else if ($type == 'cn') {
        //     $query = $this->db->query("SELECT *
        //     FROM b2b_invoice.autocount_cn_hub
        //     WHERE CNDocNo = '$inv_no'");
        // }

        if ($type == 'inv') {
            $query = $this->db->query("SELECT *
            FROM b2b_invoice.supplier_monthly_main
            WHERE invoice_number = '$inv_no'");
        } elseif ($type == 'receipt') {
            $query = $this->db->query("SELECT *
            FROM b2b_account.arpayment
            WHERE DocNo = '$inv_no'");
        } elseif ($type == 'cn') {
            $query = $this->db->query("SELECT *
            FROM b2b_account.arcn
            WHERE DocNo = '$inv_no'");
        }

        return $query;
    }

    public function inv_docdate($inv_no)
    {

        $query = $this->db->query("SELECT docdate as DocDate
        FROM b2b_account.arinvoice
        WHERE docno = '$inv_no'");

        return $query;
    }

    public function check_official_receipt_supplier_guid($customer_guid, $user_guid, $supplier_guid, $module_code)
    {
        if (in_array('IAVA', $module_code)) {
            $query = $this->db->query("SELECT a.acc_code
            FROM " . $this->tb_lite_b2b . ".set_supplier a
            WHERE a.supplier_guid IN($supplier_guid)
            LIMIT 20");
        } else {
            $query = $this->db->query("SELECT c.acc_code
            FROM " . $this->tb_lite_b2b . ".`set_supplier_user_relationship` a
            INNER JOIN lite_b2b.`set_supplier_group` b
            ON a.`supplier_group_guid` = b.`supplier_group_guid`
            AND b.`customer_guid` = '$customer_guid'
            INNER JOIN lite_b2b.`set_supplier` c
            ON b.`supplier_guid` = c.`supplier_guid`
            WHERE a.user_guid = '$user_guid'
            AND a.`customer_guid` = '$customer_guid'
            AND c.supplier_guid = '$supplier_guid'
            GROUP BY b.`supplier_guid`");
        }

        return $query;
    }

    public function official_receipt_list($con_deb_code)
    {

        $query = $this->db->query("SELECT a.DebtorCode, a.DocDate AS PayDocDate, d.DocDate,
        b.supplier_name AS supp_name, a.DocNo AS receipt_no, a.DocDate AS receipt_date,
        GROUP_CONCAT(DISTINCT d.DocNo) AS t_inv_no,
        ROUND(a.KnockOffAmt, 2) AS inv_apply_amount_total,
        ROUND(SUM(d.NetTotal), 2) AS inv_amount_total,
        COUNT(c.I_DocNo) AS inv_count,
        ROUND(a.LocalPaymentAmt, 2) AS receipt_total,
        ROUND(a.LocalUnappliedAmount, 2) AS receipt_unapply,
        ROUND(a.KnockOffAmt, 2) AS knock_off_amount
        FROM b2b_account.arpayment a
        INNER JOIN lite_b2b.set_supplier b
        ON a.DebtorCode = b.acc_code
        LEFT JOIN b2b_account.arpaymentknockoff c
        ON a.DocNo = c.R_DocNo
        LEFT JOIN b2b_account.arinvoice d
        ON c.I_DocNo = d.DocNo
        WHERE a.DebtorCode IN ($con_deb_code)
        GROUP BY a.DocNo
        ORDER BY PayDocDate DESC");

        return $query;
    }

    public function b2b_invoice_list($con_deb_code, $select_type, $status)
    {
        if ($select_type == 'data') {
            $select = '*';
        } elseif ($select_type == 'counting') {
            $select = "SUM(CASE WHEN inv_payment_status_a_cn ='Paid' THEN inv_amount ELSE 0 END) AS total_paid,
            SUM(CASE WHEN inv_payment_status_a_cn ='CN' THEN inv_amount ELSE 0 END) AS total_cn,
            SUM(CASE WHEN inv_payment_status_a_cn ='Not Paid' THEN inv_amount ELSE 0 END) AS total_not_paid,
            SUM(inv_amount) AS total_amount,
            (SUM(inv_amount) -
            SUM(CASE WHEN inv_payment_status_a_cn ='CN' THEN inv_amount ELSE 0 END) -
            SUM(CASE WHEN inv_payment_status_a_cn ='Paid' THEN inv_amount ELSE 0 END)) AS total_outstading,
            COUNT(IF(inv_payment_status_a_cn = 'Paid',1, NULL)) AS total_paid_count,
            COUNT(IF(inv_payment_status_a_cn = 'Not Paid',1, NULL)) AS total_not_paid_count,
            COUNT(*) AS total_amount_count";
        } else {
            echo 'Variable No Valid';
            die;
        }

        if ($status == '') {
            $status_in = '';
        } else {
            $status_in = "WHERE a.inv_payment_status_a_cn = '$status'";
        }

        $query = $this->db->query("SELECT $select
        FROM
          (SELECT 'payment_table' AS query_type,
                  COUNT(DISTINCT d.DocNo) AS receipt_count,
                  '' AS inv_payment_status,
                  ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) AS receipt_apply_total,
                  a.debtorcode AS DebtorCode,
                  IF(DATEDIFF(CURDATE(), a.DocDate) > 30, 1, 0) AS overdue_status,
                  IF(DATEDIFF(CURDATE(), a.DocDate) > 30, '#fffff', '#eb4034') AS overdue_status_color,
                  b.supplier_name AS CompanyName,
                  a.DocNo,
                  a.DocDate,
                  a.outstanding AS inv_balance,
                  ROUND(a.NetTotal, 2) AS inv_amount,
                  ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0), 2) AS inv_applied_amount,
                  IFNULL(ROUND(SUM(c.amount), 2), 0) AS receipt_apply_amount,
                  GROUP_CONCAT(DISTINCT d.DocNo) AS t_receipt_no,
                  COUNT(DISTINCT d.DocNo) AS t_receipt_count,
                  GROUP_CONCAT(DISTINCT f.DocNo) AS cn_no,
                  COUNT(DISTINCT f.DocNo) AS cn_apply_count,
                  IFNULL(ROUND(SUM(e.Amount), 2), 0) AS total_cn_amount,
                  CASE
                      WHEN a.outstanding = 0
                           AND COUNT(DISTINCT f.DocNo) = 0
                           AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) != 0 THEN 'Paid'
                      WHEN a.outstanding = 0
                           AND COUNT(DISTINCT f.DocNo) != 0
                           AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) != 0 THEN 'Paid + CN'
                      WHEN a.outstanding = 0
                           AND COUNT(DISTINCT f.DocNo) != 0
                           AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) = 0 THEN 'CN'
                      WHEN a.outstanding != 0
                           AND COUNT(DISTINCT f.DocNo) = 0
                           AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) = 0 THEN 'Not Paid'
                      WHEN a.outstanding != 0
                           AND COUNT(DISTINCT f.DocNo) != 0
                           AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) != 0 THEN 'Partial Paid + CN'
                      WHEN a.outstanding != 0
                           AND COUNT(DISTINCT f.DocNo) != 0
                           AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) = 0 THEN 'Partial CN'
                      WHEN a.outstanding != 0
                           AND COUNT(DISTINCT f.DocNo) = 0
                           AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) != 0 THEN 'Partial Paid'
                      ELSE 'Other'
                  END AS inv_payment_status_a_cn,
                  CASE
                        WHEN a.outstanding = 0
                            AND COUNT(DISTINCT f.DocNo) = 0
                            AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) != 0 THEN '#03fc4e'
                        WHEN a.outstanding = 0
                            AND COUNT(DISTINCT f.DocNo) != 0
                            AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) != 0 THEN '#53ff1a'
                        WHEN a.outstanding = 0
                            AND COUNT(DISTINCT f.DocNo) != 0
                            AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) = 0 THEN '#80b3ff'
                        WHEN a.outstanding != 0
                            AND COUNT(DISTINCT f.DocNo) = 0
                            AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) = 0 THEN '#ff1a1a'
                        WHEN a.outstanding != 0
                            AND COUNT(DISTINCT f.DocNo) != 0
                            AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) != 0 THEN '#fcd703'
                        WHEN a.outstanding != 0
                            AND COUNT(DISTINCT f.DocNo) != 0
                            AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) = 0 THEN '#fcd703'
                        WHEN a.outstanding != 0
                            AND COUNT(DISTINCT f.DocNo) = 0
                            AND ROUND(IFNULL(ROUND(SUM(c.amount), 2), 0)) != 0 THEN '#fcd703'
                        ELSE '#80b3ff'
                    END AS inv_payment_status_a_cn_color
           FROM b2b_account.arinvoice a
           INNER JOIN lite_b2b.set_supplier b ON a.DebtorCode = b.acc_code
           LEFT JOIN b2b_account.arpaymentknockoff c ON a.DocNo = c.I_DocNo
           LEFT JOIN b2b_account.arpayment d ON c.R_DocNo = d.DocNo
           LEFT JOIN b2b_account.arcnknockoff e ON a.DocNo = e.I_DocNo
           LEFT JOIN b2b_account.arcn f ON e.C_DocNo = f.DocNo
           WHERE a.DebtorCode IN ($con_deb_code)
           GROUP BY a.DocNo
           ORDER BY a.DocDate DESC) a
           $status_in");

        return $query;
    }

    public function b2b_cn_list($con_deb_code)
    {

        $query = $this->db->query("SELECT a.DebtorCode,
        b.`supplier_name` AS supplier_name,
        a.DocNo AS CNDocNo,
        a.DocDate AS CNDocDate,
        ROUND(a.LocalTotal, 2) AS total_cn_amount,
        ROUND(a.RefundAmt, 2) AS cn_balance_amount,
        GROUP_CONCAT(DISTINCT c.I_DocNo) AS invoice_number,
        ROUND(a.KnockOffAmt, 2) AS apply_cn_amount,
        ROUND(SUM(d.NetTotal), 2) AS total_invoice_number,
        COUNT(DISTINCT c.I_DocNo) AS inv_count
        FROM b2b_account.`arcn` a
        INNER JOIN lite_b2b.set_supplier b ON a.DebtorCode = b.acc_code
        LEFT JOIN b2b_account.`arcnknockoff` c ON a.`DocNo` = c.`C_DocNo`
        LEFT JOIN b2b_account.`arinvoice` d ON c.`I_DocNo` = d.`DocNo`
        WHERE a.debtorcode IN ($con_deb_code)
        GROUP BY a.`DocNo`
        ORDER BY CNDocDate DESC");

        return $query;
    }

    public function b2b_refund_list($con_deb_code)
    {

        $query = $this->db->query("SELECT *
        FROM
        (SELECT
          a.DebtorCode,
          b.`supplier_name` AS supplier_name,
          a.DocNo AS DocNo,
          DATE_FORMAT(a.DocDate,'%Y-%m-%d') AS DocDate,
          GROUP_CONCAT(DISTINCT d.DocNo) AS invoice_number,
          ROUND(a.KnockOffAmt, 2) AS apply_refund_amount,
          ROUND(SUM(d.LocalPaymentAmt), 2) AS total_invoice_number,
          COUNT(DISTINCT c.knockoffdockey) AS inv_count,
          knockoffdoctype
        FROM
          b2b_account.`arrefund` a
          INNER JOIN lite_b2b.set_supplier b
            ON a.DebtorCode = b.acc_code
          LEFT JOIN b2b_account.`arrefundknockoff` c
            ON a.`DocKey` = c.`Dockey`
          INNER JOIN b2b_account.`arpayment` d
            ON c.`knockoffdockey` = d.`dockey`
        WHERE a.debtorcode IN ($con_deb_code)
        GROUP BY a.`DocNo`) a
      UNION
      ALL
      SELECT
        *
      FROM
        (SELECT
          a.DebtorCode,
          b.`supplier_name` AS supplier_name,
          a.DocNo AS DocNo,
          DATE_FORMAT(a.DocDate,'%Y-%m-%d') AS DocDate,
          GROUP_CONCAT(DISTINCT d.DocNo) AS invoice_number,
          ROUND(a.KnockOffAmt, 2) AS apply_refund_amount,
          ROUND(SUM(d.NetTotal), 2) AS total_invoice_number,
          COUNT(DISTINCT c.knockoffdockey) AS inv_count,
          knockoffdoctype
        FROM
          b2b_account.`arrefund` a
          INNER JOIN lite_b2b.set_supplier b
            ON a.DebtorCode = b.acc_code
          LEFT JOIN b2b_account.`arrefundknockoff` c
            ON a.`DocKey` = c.`Dockey`
          INNER JOIN b2b_account.`arcn` d
            ON c.`knockoffdockey` = d.`dockey`
        WHERE a.debtorcode IN ($con_deb_code)
        GROUP BY a.`DocNo`) b");

        return $query;
    }

    //tzuhaw get b2b monthly billing invoice break down period code
    public function get_b2b_monthly_billing_invoice_break_down_period_code($supplier_guid, $module_code)
    {
        if (in_array('IAVA', $module_code)) {
            $query = $this->db->query("
            SELECT period_code
            FROM " . $this->tb_b2b_invoice . ".supplier_monthly_doc_count
            GROUP BY period_code DESC
            ");
        } else {
            $query = $this->db->query("
            SELECT period_code
            FROM " . $this->tb_b2b_invoice . ".supplier_monthly_doc_count
            WHERE supplier_guid IN (" . $supplier_guid . ")
            GROUP BY period_code DESC
            ");
        }

        return $query;
    }

    public function monthly_invoice_list($status, $isadmin, $supplier_guid, $select_type)
    {

        if ($status == "All") {

            $status_where = '';
        } else {
            $status_where = 'AND a.inv_status IN (' . $status . ')';
        }

        // if ($slip_status == '') {
        //     $slip_status_where = "";
        // } else {
        //     $slip_status_where = "AND b.status IN (" . $slip_status . ")";
        // }

        if ($supplier_guid == "''") {

            $supplier_guid_where = "";
        } else {
            $supplier_guid_where = "AND a.biller_guid IN (" . $supplier_guid . ")";
        }


        // $limit = "LIMIT " . $limit . " OFFSET " . $offset;
        // if ($select_type == 'data') {
        //     if ($isadmin == '1') {
        //         $select = "
        //       ";
        //     } else {
        //         $select = "";
        //     }
        // } elseif ($select_type == 'count') {
        //     $select = 'COUNT(invoice_number) AS counting';
        // } else {
        //     echo 'select type not found';
        //     die;
        // }

        if ($isadmin == '1') {

            $query = $this->db->query("SELECT *
            FROM(
            SELECT a.name as supplier_name,a.biller_guid,
            a.invoice_number as inv_no,
            a.period_code,
            IF(a.period_code >= '2021-05',a.total_include_tax,a.final_amount) AS amount,
            a.inv_status as status,
            IF(a.inv_status = 'New' ,'#80b3ff',
            IF(a.inv_status = 'Paid', '#53ff1a',
            IF(a.inv_status = 'Emailed','#ff751a',
            IF(a.inv_status = 'cn','#ff1a1a','#80b3ff'
            ))))as status_color,
            a.created_at,
            a.inv_guid,
            IF(a.period_code >= '2021-05','1','0') AS include_tax,
            IF(a.inv_status = 'Paid' AND b.invoice_number is null OR a.inv_status ='CN' OR a.inv_status = 'cn' OR b.invoice_number IS NOT NULL,'0','1') AS upload_button,
            IF(a.inv_status = 'Paid' OR a.inv_status ='CN' OR a.inv_status = 'cn','0','1') AS delete_button,
            IF(b.invoice_number IS NOT NULL,'1','0') AS view_button,
            IFNULL(b.status,'') AS slip_status,
            IF(b.status = 'Uploaded' ,'#80b3ff',
            IF(b.status = 'Processed', '#53ff1a','#80b3ff'
            ))as slip_status_color,
            '1' AS change_status_button
            FROM " . $this->tb_b2b_invoice . ".supplier_monthly_main As a
            LEFT JOIN lite_b2b.`invoice_slip` AS b 
            ON a.`invoice_number` = b.`invoice_number` 
            WHERE a.invoice_number IS NOT NULL
            $status_where
            $supplier_guid_where
            
       
            UNION ALL
            
            SELECT a.name as supplier_name,a.biller_guid,
            a.invoice_number as inv_no,
            a.period_code,
            IF(a.period_code >= '2021-05',a.total_include_tax,a.final_amount) AS amount,
            a.inv_status as status,
            IF(a.inv_status = 'New' ,'#80b3ff',
            IF(a.inv_status = 'Paid', '#53ff1a',
            IF(a.inv_status = 'Emailed','#ff751a',
            IF(a.inv_status = 'cn','#ff1a1a','#80b3ff'
            ))))as status_color,
            a.created_at,
            a.inv_guid,
            IF(a.period_code >= '2021-05','1','0') AS include_tax,
            IF(a.inv_status = 'Paid' OR a.inv_status ='CN' OR a.inv_status = 'cn' OR b.invoice_number IS NOT NULL,'0','1') AS upload_button,
            IF(a.inv_status = 'Paid' OR a.inv_status ='CN' OR a.inv_status = 'cn','0','1') AS delete_button,
            IF(b.invoice_number IS NOT NULL,'1','0') AS view_button,
            IFNULL(b.status,'') AS slip_status,
            IF(b.status = 'Uploaded' ,'#80b3ff',
            IF(b.status = 'Processed', '#53ff1a','#80b3ff'
            ))as slip_status_color,
            '1' AS change_status_button
            FROM b2b_invoice.inv_doc a 
            LEFT JOIN lite_b2b.`invoice_slip` b 
            ON a.`invoice_number` = b.`invoice_number`
            WHERE a.invoice_number IS NOT NULL
            $status_where
            $supplier_guid_where
            )aa
            GROUP BY aa.`inv_guid`
            ORDER BY FIELD(aa.slip_status,'Uploaded','Processed','') ASC,aa.inv_no DESC
         
            ");
        } else {

            $query = $this->db->query("SELECT *
            FROM(
            SELECT a.name as supplier_name,a.biller_guid,
            a.invoice_number as inv_no,
            a.period_code,
            IF(a.period_code >= '2021-05',a.total_include_tax,a.final_amount) AS amount,
            a.inv_status as status,
            IF(a.inv_status = 'New' ,'#80b3ff',
            IF(a.inv_status = 'Paid', '#53ff1a',
            IF(a.inv_status = 'Emailed','#ff751a',
            IF(a.inv_status = 'cn','#ff1a1a','#80b3ff'
            ))))as status_color,
            IF(b.status = 'Uploaded' ,'#80b3ff',
            IF(b.status = 'Processed', '#53ff1a','#80b3ff'
            ))as slip_status_color,
            a.created_at,
            a.inv_guid,
            IF(a.period_code >= '2021-05','1','0') AS include_tax,
            IF(a.inv_status = 'Paid' OR a.inv_status ='CN' OR a.inv_status = 'cn' OR b.invoice_number IS NOT NULL,'0','1') AS upload_button,
            IF(a.inv_status = 'Paid' OR a.inv_status ='CN' OR a.inv_status = 'cn','0','1') AS delete_button,
            IF(b.invoice_number IS NOT NULL,'1','0') AS view_button
            FROM " . $this->tb_b2b_invoice . ".supplier_monthly_main AS a
            LEFT JOIN lite_b2b.`invoice_slip` b 
            ON a.`invoice_number` = b.`invoice_number`
            WHERE a.biller_guid IN (" . $supplier_guid . ")
            AND a.inv_status != 'New'
            $status_where

            UNION ALL

            SELECT a.name as supplier_name,a.biller_guid,
            a.invoice_number as inv_no,
            a.period_code,
            IF(a.period_code >= '2021-05',a.total_include_tax,a.final_amount) AS amount,
            a.inv_status as status,
            IF(a.inv_status = 'New' ,'#80b3ff',
            IF(a.inv_status = 'Paid', '#53ff1a',
            IF(a.inv_status = 'Emailed','#ff751a',
            IF(a.inv_status = 'cn','#ff1a1a','#80b3ff'
            ))))as status_color,
            IF(b.status = 'Uploaded' ,'#80b3ff',
            IF(b.status = 'Processed', '#53ff1a','#80b3ff'
            ))as slip_status_color,
            a.created_at,
            a.inv_guid,
            IF(a.period_code >= '2021-05','1','0') AS include_tax,
            IF(a.inv_status = 'Paid' OR a.inv_status ='CN' OR a.inv_status = 'cn' OR b.invoice_number IS NOT NULL,'0','1') AS upload_button,
            IF(a.inv_status = 'Paid' OR a.inv_status ='CN' OR a.inv_status = 'cn','0','1') AS delete_button,
            IF(b.invoice_number IS NOT NULL,'1','0') AS view_button
            FROM " . $this->tb_b2b_invoice . ".inv_doc AS a
            LEFT JOIN lite_b2b.`invoice_slip` b 
            ON a.`invoice_number` = b.`invoice_number`
            WHERE a.biller_guid IN (" . $supplier_guid . ")
            AND a.inv_status != 'New'
            $status_where)aa
            GROUP BY aa.`inv_guid`
            ORDER BY aa.inv_no DESC
            ");
        }

        return $query;
    }

    //tzuhaw b2b monthly invoice break down
    public function monthly_invoice_break_down_list($status, $period_code, $module_code, $select_type, $customer_guid, $supplier_guid)
    {
        // echo $status;die;
        $period = $this->db->query("SELECT DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL - 1 MONTH), '%Y-%m') AS period_code")->row('period_code');
        if ($status == "All") {
            $doc_type_where = '';
            //$period_code_where = 'AND a.period_code IN ("' . $period . '")';
        } else {
            $doc_type_where = 'AND doc_type IN (' . $status . ')';
        }

        if ($period_code == '') {
            $period_code_where = '';
        } else {
            $period_code_where = 'AND a.period_code IN ("' . $period_code . '")';
        }

        // $limit = "LIMIT " . $limit . " OFFSET " . $offset;
        if ($select_type == 'data') {
            $select = '*';
        } elseif ($select_type == 'count') {
            $select = 'COUNT(reg_no) AS counting';
        } else {
            echo 'select type not found';
            die;
        }

        if (in_array('IAVA', $module_code)) {
            $query = $this->db->query("
            SELECT $select FROM
            (
            SELECT
            reg_no ,
            a.period_code,
            acc_doc_name as customer_name,
            c.supplier_name,
            a.supplier_guid,
            customer_guid,
            IF(doc_type = 'PO', 'Purchase Order'
            , IF(doc_type = 'GR', 'Goods Received Note'
            , IF(doc_type =  'promo_taxinv', 'Promo Tax Inv'
            , IF(doc_type = 'CN', 'Credit Note'
            , IF(doc_type = 'DBnote', 'Return Note'
            , IF(doc_type = 'discheme_taxinv', 'Display Incentive'
            , IF(doc_type = 'acc_doc', 'Accounting Document'
            , IF(doc_type = 'PDN', 'Purchase Debit Note'
            , IF(doc_type = 'PCN', 'Purchase Credit Note'
            , IF(doc_type = 'SI', 'Sales Invoice','Others'
            )))))))))) AS doc_type,
            IF(doc_type = 'PO', '#00c0ef'
            , IF(doc_type = 'GR', '#00a65a'
            , IF(doc_type =  'promo_taxinv', '#36815f'
            , IF(doc_type = 'CN', '#0b738c'
            , IF(doc_type = 'DBnote', '#dd4b39'
            , IF(doc_type = 'discheme_taxinv', '#aa6e0e'
            , IF(doc_type = 'acc_doc', '#aa6e0e'
            , IF(doc_type = 'PDN', '#0b738c'
            , IF(doc_type = 'PCN', '#dd4b39'
            , IF(doc_type = 'SI', '#dd4b39','#00c0ef'
            )))))))))) AS doc_type_color,
            '#99d6ff' AS doc_count_color,
            SUM(doc_count) AS doc_count
            FROM " . $this->tb_b2b_invoice . ".supplier_monthly_doc_count AS a
            INNER JOIN " . $this->tb_lite_b2b . ".acc AS b ON a.customer_guid = b.acc_guid
            LEFT JOIN " . $this->tb_lite_b2b . ".set_supplier AS c ON a.`supplier_guid` = c.supplier_guid
            WHERE hide_supplier_invoice = '0'
            AND isissued = '1'
            $period_code_where
            AND a.customer_guid = '$customer_guid'
            AND doc_type != 'CONSIGN'
            $doc_type_where
            GROUP BY a.customer_guid,
                a.supplier_guid,
                a.doc_type,
                a.period_code
            UNION ALL
            SELECT reg_no,
            a.period_code,
            acc_doc_name,
            c.supplier_name,
            a.supplier_guid,
            customer_guid,
            'Consign Outlet' AS doc_count,
            '#00c0ef'AS doc_type_color,
            '#99d6ff' AS doc_count_color,
            SUM(doc_count) AS doc_count
            FROM " . $this->tb_b2b_invoice . ".supplier_monthly_doc_count_consignment AS a
            INNER JOIN " . $this->tb_lite_b2b . ".acc AS b ON a.customer_guid = b.acc_guid
            LEFT JOIN " . $this->tb_lite_b2b . ".set_supplier AS c ON a.`supplier_guid` = c.supplier_guid
            WHERE hide_supplier_invoice = '0'
            AND isissued = '1'
            $period_code_where
            AND a.customer_guid = '$customer_guid'
            $doc_type_where
            GROUP BY a.customer_guid,
                a.supplier_guid,
                a.doc_type,
                a.period_code
            ORDER BY period_code DESC,supplier_name ASC)aa
            ");
        } else {
            $query = $this->db->query("
            SELECT $select FROM
            (
            SELECT c.reg_no,
            a.period_code,
            acc_doc_name,
            c.supplier_name,
            a.supplier_guid,
            customer_guid,
            IF(doc_type = 'PO', 'Purchase Order'
            , IF(doc_type = 'GR', 'Goods Received Note'
            , IF(doc_type =  'promo_taxinv', 'Promo Tax Inv'
            , IF(doc_type = 'CN', 'Credit Note'
            , IF(doc_type = 'DBnote', 'Return Note'
            , IF(doc_type = 'discheme_taxinv', 'Display Incentive'
            , IF(doc_type = 'acc_doc', 'Accounting Document'
            , IF(doc_type = 'PDN', 'Purchase Debit Note'
            , IF(doc_type = 'PCN', 'Purchase Credit Note'
            , IF(doc_type = 'SI', 'Sales Invoice','Others'
            )))))))))) AS doc_type,
            IF(doc_type = 'PO', '#00c0ef'
            , IF(doc_type = 'GR', '#00a65a'
            , IF(doc_type =  'promo_taxinv', '#36815f'
            , IF(doc_type = 'CN', '#0b738c'
            , IF(doc_type = 'DBnote', '#dd4b39'
            , IF(doc_type = 'discheme_taxinv', '#aa6e0e'
            , IF(doc_type = 'acc_doc', '#aa6e0e'
            , IF(doc_type = 'PDN', '#0b738c'
            , IF(doc_type = 'PCN', '#dd4b39'
            , IF(doc_type = 'SI', '#dd4b39','#00c0ef'
            )))))))))) AS doc_type_color,
            '#99d6ff' AS doc_count_color,
            SUM(doc_count) AS doc_count
            FROM " . $this->tb_b2b_invoice . ".supplier_monthly_doc_count AS a
            INNER JOIN " . $this->tb_lite_b2b . ".acc AS b ON a.customer_guid = b.acc_guid
            LEFT JOIN " . $this->tb_lite_b2b . ".set_supplier AS c ON a.`supplier_guid` = c.supplier_guid
            INNER JOIN " . $this->tb_b2b_invoice . ".supplier_monthly_main d ON a.supplier_guid = d.biller_guid
            AND a.period_code = d.period_code
            WHERE a.supplier_guid IN (" . $supplier_guid . ")
            AND hide_supplier_invoice = '0'
            AND isissued = '1'
            AND d.inv_status != 'New'
            $period_code_where
            AND a.customer_guid = '$customer_guid'
            AND doc_type != 'CONSIGN'
            $doc_type_where
            GROUP BY a.customer_guid,
            a.supplier_guid,
            a.doc_type,
            a.period_code
            UNION ALL
            SELECT c.reg_no,
            a.period_code,
            acc_doc_name,
            c.supplier_name,
            a.supplier_guid,
            customer_guid,
            'Consign Outlet' AS doc_type,
            '#00c0ef'AS doc_type_color,
            '#99d6ff' AS doc_count_color,
            SUM(doc_count) AS doc_count
            FROM " . $this->tb_b2b_invoice . ".supplier_monthly_doc_count_consignment AS a
            INNER JOIN " . $this->tb_lite_b2b . ".acc AS b ON a.customer_guid = b.acc_guid
            LEFT JOIN " . $this->tb_lite_b2b . ".set_supplier AS c ON a.`supplier_guid` = c.supplier_guid
            INNER JOIN " . $this->tb_b2b_invoice . ".supplier_monthly_main d ON a.supplier_guid = d.biller_guid
            AND a.period_code = d.period_code
            WHERE a.supplier_guid IN (" . $supplier_guid . ")
            AND hide_supplier_invoice = '0'
            AND isissued = '1'
            AND d.inv_status != 'New'
            $period_code_where
            AND a.customer_guid = '$customer_guid'
            $doc_type_where
            GROUP BY a.customer_guid,
            a.supplier_guid,
            a.doc_type,
            a.period_code
            ORDER BY period_code DESC,supplier_name ASC)aa
            ");
        }

        return $query;
    }

    //tzuhaw b2b monthly invoice by retail
    public function monthly_invoice_by_retailer_list($status, $isadmin, $supplier_guid, $customer_guid, $select_type)
    {
        if ($status == "All") {
            $status_where = '';
        } else {
            $status_where = 'AND a.inv_status IN (' . $status . ')';
        }

        // $limit = "LIMIT " . $limit . " OFFSET " . $offset;
        if ($select_type == 'data') {
            if ($isadmin == '1') {
                $select = "
                a.name as supplier_name,
                a.invoice_number as inv_no,
                a.period_code,
                IF(a.period_code >= '2021-05',a.total_include_tax,a.final_amount) AS amount,
                a.inv_status as status,
                IF(a.inv_status = 'New' ,'#80b3ff',
                IF(a.inv_status = 'Paid', '#53ff1a',
                IF(a.inv_status = 'Emailed','#ff751a',
                IF(a.inv_status = 'cn','#ff1a1a','#80b3ff'
                ))))as status_color,
                a.created_at,
                a.inv_guid,
                IF(a.period_code >= '2021-05','1','0') AS include_tax";
                $select1 = '*';
            } else {
                $select = "a.name as supplier_name,
                a.invoice_number as inv_no,
                a.period_code,
                IF(a.period_code >= '2021-05',a.total_include_tax,a.final_amount) AS amount,
                a.inv_status as status,
                IF(a.inv_status = 'New' ,'#80b3ff',
                IF(a.inv_status = 'Paid', '#53ff1a',
                IF(a.inv_status = 'Emailed','#ff751a',
                IF(a.inv_status = 'cn','#ff1a1a','#80b3ff'
                ))))as status_color,
                a.created_at,
                a.inv_guid,
                IF(a.period_code >= '2021-05','1','0') AS include_tax";
                $select1 = '*';
            }
        } elseif ($select_type == 'count') {
            $select = 'COUNT(a.invoice_number) AS counting';
            $select1 = 'COUNT(counting) AS counting';
        } else {
            echo 'select type not found';
            die;
        }

        if ($isadmin == '1') {
            $query = $this->db->query("
            SELECT $select1 FROM (
            SELECT $select
            from " . $this->tb_b2b_invoice . ".supplier_monthly_main As a
            LEFT JOIN " . $this->tb_b2b_invoice . ".supplier_monthly_child b ON a.inv_guid = b.inv_guid
            WHERE b.customer_guid = '$customer_guid'
            $status_where
            GROUP BY a.invoice_number
            ORDER BY a.invoice_number DESC)aa
            ");
        } else {
            $query = $this->db->query("
            SELECT $select1 FROM (
            SELECT $select
            from " . $this->tb_b2b_invoice . ".supplier_monthly_main As a
            LEFT JOIN " . $this->tb_b2b_invoice . ".supplier_monthly_child b ON a.inv_guid = b.inv_guid
            WHERE a.biller_guid IN (" . $supplier_guid . ")
              AND b.customer_guid = '$customer_guid'
              AND inv_status != 'New'
              $status_where
            GROUP BY a.invoice_number
            ORDER BY a.invoice_number DESC)aa
            ");
        }

        return $query;
    }

    //tzuhaw b2b monthly invoice break down info
    public function monthly_invoice_break_down_info($period_code, $doc_type, $supplier_guid, $customer_guid)
    {

        if ($period_code <= '2020-02' || $customer_guid == '13EE932D98EB11EAB05B000D3AA2838A') {
            if ($doc_type == 'Purchase Order') {
                $query = $this->db->query("SELECT
                refno , loc_group as loc_group,postdatetime
                FROM b2b_summary.pomain  AS a
                INNER JOIN lite_b2b.acc AS zz
                ON a.`customer_guid` = zz.acc_guid
                INNER JOIN lite_b2b.`set_supplier_group` AS b
                ON a.`customer_guid` = b.`customer_guid`
                INNER JOIN lite_b2b.`set_supplier` AS c
                ON b.supplier_guid = c.`supplier_guid`
                WHERE c.isactive = '1'
                AND a.`cancel` = '0'
                AND a.`BillStatus` = '1'
                AND a.scode = b.`supplier_group_name`
                AND a.status != 'HFSP'
                AND zz.`trial_mode` = '0'
                AND LEFT(postdatetime,7) = '$period_code'
                AND rejected = '0'
                AND b.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid' ");
                if (count($query) <= 0) {
                    $query = $this->db->query("SELECT
                    refno , loc_group as loc_group,postdatetime
                    FROM b2b_archive.pomain  AS a
                    INNER JOIN lite_b2b.acc AS zz
                    ON a.`customer_guid` = zz.acc_guid
                    INNER JOIN lite_b2b.`set_supplier_group` AS b
                    ON a.`customer_guid` = b.`customer_guid`
                    INNER JOIN lite_b2b.`set_supplier` AS c
                    ON b.supplier_guid = c.`supplier_guid`
                    WHERE c.isactive = '1'
                    AND a.`cancel` = '0'
                    AND a.`BillStatus` = '1'
                    AND a.scode = b.`supplier_group_name`
                    AND a.status != 'HFSP'
                    AND zz.`trial_mode` = '0'
                    AND LEFT(postdatetime,7) = '$period_code'
                    AND rejected = '0'
                    AND b.supplier_guid = '$supplier_guid'
                    AND a.customer_guid = '$customer_guid' ");
                }
            } elseif ($doc_type == 'Goods Received Note') {
                $query = $this->db->query("SELECT
                refno ,loc_group as loc_group, postdatetime
                FROM b2b_summary.`grmain`  AS a
                INNER JOIN lite_b2b.acc AS zz
                ON a.`customer_guid` = zz.acc_guid
                INNER JOIN lite_b2b.`set_supplier_group` AS b
                ON a.`customer_guid` = b.`customer_guid`
                INNER JOIN lite_b2b.`set_supplier` AS c
                ON b.supplier_guid = c.`supplier_guid`
                WHERE c.isactive = '1'
                AND a.`Cancelled` = '0'
                AND a.`BillStatus` = '1'
                AND a.code = b.`supplier_group_name`
                AND zz.trial_mode = '0'
                AND LEFT(postdatetime,7) = '$period_code'
                AND b.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid' ");
                if (count($query) <= 0) {
                    $query = $this->db->query("SELECT
                    refno ,loc_group as loc_group, postdatetime
                    FROM b2b_archive.`grmain`  AS a
                    INNER JOIN lite_b2b.acc AS zz
                    ON a.`customer_guid` = zz.acc_guid
                    INNER JOIN lite_b2b.`set_supplier_group` AS b
                    ON a.`customer_guid` = b.`customer_guid`
                    INNER JOIN lite_b2b.`set_supplier` AS c
                    ON b.supplier_guid = c.`supplier_guid`
                    WHERE c.isactive = '1'
                    AND a.`Cancelled` = '0'
                    AND a.`BillStatus` = '1'
                    AND a.code = b.`supplier_group_name`
                    AND zz.trial_mode = '0'
                    AND LEFT(postdatetime,7) = '$period_code'
                    AND b.supplier_guid = '$supplier_guid'
                    AND a.customer_guid = '$customer_guid' ");
                }
            } elseif ($doc_type == 'Promo Tax Inv') {

                $query = $this->db->query("SELECT
                refno , loc_group as loc_group,posted_at as postdatetime
                FROM b2b_summary.`promo_taxinv`  AS a
                INNER JOIN lite_b2b.acc AS zz
                ON a.`customer_guid` = zz.acc_guid
                INNER JOIN lite_b2b.`set_supplier_group` AS b
                ON a.`customer_guid` = b.`customer_guid`
                AND a.sup_code = b.`supplier_group_name`
                INNER JOIN lite_b2b.`set_supplier` AS c
                ON b.supplier_guid = c.`supplier_guid`
                WHERE c.isactive = '1'
                AND zz.`trial_mode` = '0'
                AND LEFT(posted_at,7) = '$period_code'
                #AND LEFT(docdate,7) = '$period_code'
                AND b.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'  ");
                if (count($query) <= 0) {
                    $query = $this->db->query("SELECT
                    refno , loc_group as loc_group,posted_at as postdatetime
                    FROM b2b_archive.`promo_taxinv`  AS a
                    INNER JOIN lite_b2b.acc AS zz
                    ON a.`customer_guid` = zz.acc_guid
                    INNER JOIN lite_b2b.`set_supplier_group` AS b
                    ON a.`customer_guid` = b.`customer_guid`
                    AND a.sup_code = b.`supplier_group_name`
                    INNER JOIN lite_b2b.`set_supplier` AS c
                    ON b.supplier_guid = c.`supplier_guid`
                    WHERE c.isactive = '1'
                    AND zz.`trial_mode` = '0'
                    AND LEFT(posted_at,7) = '$period_code'
                    #AND LEFT(docdate,7) = '$period_code'
                    AND b.supplier_guid = '$supplier_guid'
                    AND a.customer_guid = '$customer_guid'  ");
                }
            } elseif ($doc_type == 'Credit Note') {

                $query = $this->db->query("SELECT
                refno ,locgroup as loc_group, postdatetime
                FROM b2b_summary.`cnnotemain`  AS a
                INNER JOIN lite_b2b.acc AS zz
                ON a.`customer_guid` = zz.acc_guid
                INNER JOIN lite_b2b.`set_supplier_group` AS b
                ON a.`customer_guid` = b.`customer_guid`
                INNER JOIN lite_b2b.`set_supplier` AS c
                ON b.supplier_guid = c.`supplier_guid`
                WHERE c.isactive = '1'
                AND a.`Closed` = '0'
                AND a.`BillStatus` = '1'
                AND a.code = b.`supplier_group_name`
                AND zz.`trial_mode` = '0'
                AND LEFT(postdatetime,7) = '$period_code'
                AND b.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'  ");

                if (count($query) <= 0) {
                    $query = $this->db->query("SELECT
                    refno ,locgroup as loc_group, postdatetime
                    FROM b2b_archive.`cnnotemain`  AS a
                    INNER JOIN lite_b2b.acc AS zz
                    ON a.`customer_guid` = zz.acc_guid
                    INNER JOIN lite_b2b.`set_supplier_group` AS b
                    ON a.`customer_guid` = b.`customer_guid`
                    INNER JOIN lite_b2b.`set_supplier` AS c
                    ON b.supplier_guid = c.`supplier_guid`
                    WHERE c.isactive = '1'
                    AND a.`Closed` = '0'
                    AND a.`BillStatus` = '1'
                    AND a.code = b.`supplier_group_name`
                    AND zz.`trial_mode` = '0'
                    AND LEFT(postdatetime,7) = '$period_code'
                    AND b.supplier_guid = '$supplier_guid'
                    AND a.customer_guid = '$customer_guid'  ");
                }
            } elseif ($doc_type == 'Return Note') {

                $query = $this->db->query("SELECT
                refno ,locgroup as loc_group, postdatetime
                FROM b2b_summary.`dbnotemain`  AS a
                INNER JOIN lite_b2b.acc AS zz
                ON a.`customer_guid` = zz.acc_guid
                INNER JOIN lite_b2b.`set_supplier_group` AS b
                ON a.`customer_guid` = b.`customer_guid`
                INNER JOIN lite_b2b.`set_supplier` AS c
                ON b.supplier_guid = c.`supplier_guid`
                WHERE c.isactive = '1'
                AND a.`Closed` = '0'
                AND a.`BillStatus` = '1'
                AND a.code = b.`supplier_group_name`
                AND zz.`trial_mode` = '0'
                AND LEFT(postdatetime,7) = '$period_code'
                AND b.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'   ");
                if (count($query) <= 0) {
                    $query = $this->db->query("SELECT
                    refno ,locgroup as loc_group, postdatetime
                    FROM b2b_archive.`dbnotemain`  AS a
                    INNER JOIN lite_b2b.acc AS zz
                    ON a.`customer_guid` = zz.acc_guid
                    INNER JOIN lite_b2b.`set_supplier_group` AS b
                    ON a.`customer_guid` = b.`customer_guid`
                    INNER JOIN lite_b2b.`set_supplier` AS c
                    ON b.supplier_guid = c.`supplier_guid`
                    WHERE c.isactive = '1'
                    AND a.`Closed` = '0'
                    AND a.`BillStatus` = '1'
                    AND a.code = b.`supplier_group_name`
                    AND zz.`trial_mode` = '0'
                    AND LEFT(postdatetime,7) = '$period_code'
                    AND b.supplier_guid = '$supplier_guid'
                    AND a.customer_guid = '$customer_guid'   ");
                }
            } elseif ($doc_type == 'Display Incentive') {

                $query = $this->db->query("SELECT
                inv_refno as refno , loc_group as loc_group,posted_at as postdatetime
                FROM b2b_summary.`discheme_taxinv`  AS a
                INNER JOIN lite_b2b.acc AS zz
                ON a.`customer_guid` = zz.acc_guid
                INNER JOIN lite_b2b.`set_supplier_group` AS b
                ON a.`customer_guid` = b.`customer_guid`
                INNER JOIN lite_b2b.`set_supplier` AS c
                ON b.supplier_guid = c.`supplier_guid`
                WHERE c.isactive = '1'
                AND a.`posted` = '1'
                AND a.sup_code = b.`supplier_group_name`
                AND zz.`trial_mode` = '0'
                AND LEFT(posted_at,7) = '$period_code'
                AND b.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid' ");
                if (count($query) <= 0) {
                    $query = $this->db->query("SELECT
                    inv_refno as refno , loc_group as loc_group,posted_at as postdatetime
                    FROM b2b_archive.`discheme_taxinv`  AS a
                    INNER JOIN lite_b2b.acc AS zz
                    ON a.`customer_guid` = zz.acc_guid
                    INNER JOIN lite_b2b.`set_supplier_group` AS b
                    ON a.`customer_guid` = b.`customer_guid`
                    INNER JOIN lite_b2b.`set_supplier` AS c
                    ON b.supplier_guid = c.`supplier_guid`
                    WHERE c.isactive = '1'
                    AND a.`posted` = '1'
                    AND a.sup_code = b.`supplier_group_name`
                    AND zz.`trial_mode` = '0'
                    AND LEFT(posted_at,7) = '$period_code'
                    AND b.supplier_guid = '$supplier_guid'
                    AND a.customer_guid = '$customer_guid' ");
                }
            } elseif ($doc_type == 'Accounting Document') {

                $query = $this->db->query("SELECT
                refno AS refno ,'-' as loc_group, a.created_at AS postdatetime
                FROM b2b_summary.`other_doc`  AS a
                INNER JOIN lite_b2b.acc AS zz
                ON a.`customer_guid` = zz.acc_guid
                INNER JOIN lite_b2b.`set_supplier_group` AS b
                ON a.`customer_guid` = b.`customer_guid`
                INNER JOIN lite_b2b.`set_supplier` AS c
                ON b.supplier_guid = c.`supplier_guid`
                WHERE c.isactive = '1'
                AND a.supcode = b.`supplier_group_name`
                AND zz.`trial_mode` = '0'
                AND LEFT(a.created_at,7) = '$period_code'
                AND b.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
                if (count($query) <= 0) {
                    $query = $this->db->query("SELECT
                    refno AS refno ,'-' as loc_group, a.created_at AS postdatetime
                    FROM b2b_archive.`other_doc`  AS a
                    INNER JOIN lite_b2b.acc AS zz
                    ON a.`customer_guid` = zz.acc_guid
                    INNER JOIN lite_b2b.`set_supplier_group` AS b
                    ON a.`customer_guid` = b.`customer_guid`
                    INNER JOIN lite_b2b.`set_supplier` AS c
                    ON b.supplier_guid = c.`supplier_guid`
                    WHERE c.isactive = '1'
                    AND a.supcode = b.`supplier_group_name`
                    AND zz.`trial_mode` = '0'
                    AND LEFT(a.created_at,7) = '$period_code'
                    AND b.supplier_guid = '$supplier_guid'
                    AND a.customer_guid = '$customer_guid'");
                }
            }
        } else {
            if ($doc_type == 'Purchase Order') {
                $query = $this->db->query("SELECT a.refno,b.loc_group as loc_group, a.postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_po AS a
                INNER JOIN b2b_summary.pomain b
                ON a.refno = b.refno
                AND b.customer_guid = '$customer_guid'
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Goods Received Note') {

                $query = $this->db->query("SELECT a.refno, b.loc_group AS loc_group, a.postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_gr AS a
                INNER JOIN b2b_summary.grmain b
                ON a.refno = b.refno
                AND b.customer_guid = '$customer_guid'
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Promo Tax Inv') {

                $query = $this->db->query("SELECT a.refno, b.loc_group as loc_group, a.postdatetime AS postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_promo_taxinv AS a
                INNER JOIN b2b_summary.promo_taxinv b
                ON a.refno = b.inv_refno
                AND b.customer_guid = '$customer_guid'
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Credit Note') {

                $query = $this->db->query("SELECT a.refno, b.locgroup AS loc_group, a.postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_cn AS a
                INNER JOIN b2b_summary.cnnotemain b
                ON a.refno = b.refno
                AND b.customer_guid = '$customer_guid'
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Return Note') {

                $query = $this->db->query("SELECT a.refno, b.locgroup AS loc_group, a.postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_dbnote AS a
                INNER JOIN b2b_summary.dbnotemain b
                ON a.refno = b.refno
                AND b.customer_guid = '$customer_guid'
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Display Incentive') {

                $query = $this->db->query("SELECT a.refno AS refno, b.loc_group, a.postdatetime AS postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_discheme_taxinv a
                INNER JOIN b2b_summary.discheme_taxinv b
                ON a.refno = b.inv_refno
                AND b.customer_guid = '$customer_guid'
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Accounting Document') {

                $query = $this->db->query("SELECT b.refno AS refno, doctype AS loc_group, a.postdatetime AS postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_acc_doc a
                INNER JOIN b2b_summary.other_doc b
                ON a.refno = b.refno
                AND a.doc_type = b.doctype
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'
                AND b.customer_guid = '$customer_guid'
                GROUP BY a.doc_type,a.refno");
            } elseif ($doc_type == 'Consign Outlet') {

                $query = $this->db->query("SELECT a.refno AS refno, '-' AS loc_group, a.postdatetime AS postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_consignment_outlet a
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Purchase Debit Note') {

                $query = $this->db->query("SELECT a.refno AS refno, b.loc_group AS loc_group, a.postdatetime AS postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_pdn a
                LEFT JOIN b2b_summary.cndn_amt b
                ON a.refno = b.refno
                AND a.customer_guid = b.customer_guid
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Purchase Credit Note') {

                $query = $this->db->query("SELECT a.refno AS refno, b.loc_group AS loc_group, a.postdatetime AS postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_pcn a
                LEFT JOIN b2b_summary.cndn_amt b
                ON a.refno = b.refno
                AND a.customer_guid = b.customer_guid
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            } elseif ($doc_type == 'Sales Invoice') {

                $query = $this->db->query("SELECT a.refno AS refno, b.Code AS loc_group, a.postdatetime AS postdatetime
                FROM b2b_invoice.supplier_monthly_doc_count_si a
                LEFT JOIN b2b_summary.simain_info b
                ON a.refno = b.refno
                AND a.customer_guid = b.customer_guid
                WHERE a.period_code = '$period_code'
                AND a.supplier_guid = '$supplier_guid'
                AND a.customer_guid = '$customer_guid'");
            }
        }

        return $query;
    }

    public function get_grn_header_detail($refno, $customer_guid)
    {
        $query = $this->db->query("SELECT a.`customer_guid`
        , a.`status`
        , a.`RefNo`
        , a.`Location`
        , IF(b.DONo IS NULL, a.`DONo`, b.DONo) AS DONo
        -- , IF(b.InvNo IS NULL, a.`InvNo`, b.InvNo) AS InvNo
        , a.`InvNo` AS InvNo
        -- , IF(b.DocDate IS NULL, a.`DocDate`, b.DocDate) AS DocDate
        , a.`DocDate` AS DocDate
        , a.`GRDate`
        , a.`Code`
        , a.`Name`
        , a.`consign`
        , a.Total
        , a.gst_tax_sum
        , ROUND((a.`Total` + a.gst_tax_sum),2) AS grn_total_include_tax
        , a.`InvNetAmt_Vendor` AS e_inv_total_excl_tax
        , a.`gst_tax_sum_inv`
        , a.total_include_tax
        , IF(c.einvno IS NOT NULL,c.einvno,IF(b.invno IS NULL,'',b.invno)) AS einvno
        , IF(c.inv_date IS NOT NULL,c.inv_date,IF(b.docdate IS NULL,CURDATE(),b.docdate)) AS einv_date
        , a.cross_ref,IFNULL((a.total_include_tax - d.`VarianceAmt`),a.Total) AS Total
        FROM b2b_summary.grmain AS a
        LEFT JOIN b2b_summary.grmain_proposed AS b
        ON a.refno = b.refno
        AND a.customer_guid = b.customer_guid
        LEFT JOIN b2b_summary.einv_main c ON a.refno = c.refno AND a.customer_guid = c.customer_guid 
        LEFT JOIN b2b_summary.`grmain_dncn` d ON a.`RefNo` = d.`RefNo`
        where a.refno = '$refno'
        and a.customer_guid = '" . $customer_guid . "'");

        return $query;
    }

    public function upload_grn_cn_setting($customer_guid)
    {
        $query = $this->db->query("SELECT a.upload_cn_setting,a.upload_grn_cn_setting,a.upload_consign_invoice
        FROM lite_b2b.acc_settings AS a
        WHERE a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function check_supplier_guid($customer_guid, $refno)
    {
        $query = $this->db->query("SELECT b.`supplier_guid`
        FROM b2b_summary.grmain a
        INNER JOIN lite_b2b.`set_supplier_group` b
        ON a.code = b.`supplier_group_name`
        AND b.`customer_guid` = '$customer_guid'
        WHERE a.customer_guid = '$customer_guid'
        AND a.refno = '$refno'");

        return $query;
    }

    public function get_dn_detail($refno, $customer_guid, $upload_grn_cn_setting_flag)
    {
        if ($upload_grn_cn_setting_flag == 1) {
            $select = "(SELECT CONCAT('https://b2b.xbridge.my/',file_path) AS file_path
           FROM b2b_summary.`upload_doc_log`
           WHERE customer_guid = '$customer_guid'
           AND refno = CONCAT(a.refno,'-',a.transtype)
           ORDER BY created_at DESC LIMIT 1) AS file_path";

            $condition = "LEFT JOIN b2b_summary.`upload_doc_log` d
           ON c.customer_guid = d.customer_guid
           AND CONCAT(a.refno, '-', a.transtype) = d.refno";
        } else {
            $select = "'#' as file_path";

            $condition = "";
        }
        $query = $this->db->query("SELECT a.customer_guid, @row := @row + 1 AS rowx,
        IFNULL(b.ecn_guid, 'Pending') AS ecn_guid,
        IFNULL(b.status, 'Pending') AS ecn_status,
        IFNULL(b.type, 'Pending') AS ecn_type, ext_doc1,
        IFNULL(ext_date1, CURDATE()) AS ext_date1,
        IFNULL(b.posted, '0') AS posted, a.status, a.location, a.RefNo, a.VarianceAmt, a.Created_at, a.Created_by, a.Updated_at, a.Updated_by, a.hq_update, a.EXPORT_ACCOUNT, a.EXPORT_AT, a.EXPORT_BY, a.transtype, a.share_cost, a.gst_tax_sum, a.gst_adjust, a.gl_code, a.tax_invoice, a.ap_sup_code, a.refno2, a.rounding_adj, a.sup_cn_no, a.sup_cn_date, a.dncn_date, a.dncn_date_acc, c.upload_cn_setting,
        $select
        FROM b2b_summary.grmain_dncn AS a
        LEFT JOIN (SELECT * FROM b2b_summary.ecn_main
        WHERE customer_guid = '$customer_guid'
        AND refno = '$refno') AS b
        ON a.refno = b.refno
        AND a.transtype = b.type
        LEFT JOIN lite_b2b.acc_settings c
        ON a.customer_guid = c.customer_guid
        $condition
        WHERE a.refno = '$refno'
        AND a.customer_guid = '" . $customer_guid . "'
        ORDER BY transtype ASC");

        return $query;
    }

    public function check_ecn_main($refno, $customer_guid)
    {
        $query = $this->db->query("SELECT a.*, COUNT(a.refno) AS first_count,
        (SELECT COUNT(refno) AS scount
        FROM b2b_summary.`ecn_main`
        WHERE refno = '$refno'
        AND customer_guid = '" . $customer_guid . "') AS second_count
        FROM b2b_summary.grmain_dncn a
        WHERE a.refno = '$refno'
        AND a.customer_guid = '" . $customer_guid . "'
        HAVING second_count = first_count");

        return $query;
    }

    public function check_scode($refno, $customer_guid)
    {
        $query = $this->db->query("SELECT code
        from b2b_summary.grmain
        where refno = '$refno'
        and customer_guid = '" . $customer_guid . "'");

        return $query;
    }

    public function check_status($refno, $customer_guid)
    {
        $query = $this->db->query("SELECT refno, if(status = '', 'Pending', status) as status
        FROM b2b_summary.grmain
        WHERE refno = '$refno'
        AND customer_guid = '" . $customer_guid . "'");

        return $query;
    }

    public function get_set_code($type)
    {
        $query = $this->db->query("SELECT code,reason
        FROM  lite_b2b.set_setting
        WHERE module_name = '$type'
        ORDER BY reason ASC");

        return $query;
    }

    public function check_e_invoice($refno, $customer_guid)
    {
        $query = $this->db->query("SELECT a.*,
        IFNULL(IF(DATE_FORMAT(a.created_at,'%Y-%m-%d') < DATE_FORMAT(NOW(),'%Y-%m-%d')+INTERVAL 14 DAY,'1','0'),'1') AS reupload_duration
        from b2b_summary.einv_main as a
        where refno = '$refno'
        and customer_guid = '" . $customer_guid . "'");

        return $query;
    }

    public function check_e_inv_child($get_einv_guid)
    {
        $query = $this->db->query("SELECT a.*
        from b2b_summary.einv_child as a
         where a.einv_guid = '" . $get_einv_guid . "'");

        return $query;
    }

    public function check_grn_based_on_pocost($customer_guid, $check_scode)
    {
        $query = $this->db->query("SELECT a.*
        FROM b2b_summary.supcus as a
        WHERE customer_guid = '" . $customer_guid . "'
        AND code = '$check_scode'");

        return $query;
    }

    public function check_grn_exist($customer_guid, $refno, $db)
    {
        if ($db == 'grmain_proposed') {
            $db1 = "from b2b_summary.grmain_proposed as a";
        } elseif ($db == 'einv_main') {
            $db1 = " FROM b2b_summary.einv_main as a";
        } elseif ($db == 'grmain') {
            $db1 = " FROM b2b_summary.grmain as a";
        }
        $query = $this->db->query("SELECT a.*
        $db1
        where customer_guid = '$customer_guid'
        and refno = '$refno' ");

        return $query;
    }

    public function delete_grn_header($customer_guid, $refno)
    {
        $query = $this->db->query("DELETE FROM b2b_summary.grmain_proposed
        where customer_guid = '$customer_guid'
        and refno = '$refno' ");

        return $query;
    }

    public function update_e_invoice($einv_no, $customer_guid, $refno)
    {
        $query = $this->db->query("UPDATE b2b_summary.einv_main
        SET einvno = '$einv_no'
        where customer_guid = '$customer_guid'
        and refno = '$refno'");

        return $query;
    }

    public function check_if_exists_ecn($sup_cn_no, $customer_guid, $refno, $transtype)
    {
        // $query = $this->db->query("SELECT *
        // FROM b2b_summary.ecn_main
        // WHERE CONCAT(refno,'-',type) != '$con_req_no'
        // AND customer_guid = '$customer_guid'
        // AND ext_doc1 = '$sup_cn_no'");

        $query = $this->db->query("SELECT *
        FROM b2b_summary.ecn_main
        WHERE refno != '$refno'
        AND type = '$transtype'
        AND customer_guid = '$customer_guid'
        AND ext_doc1 = '$sup_cn_no[0]'");

        return $query;
    }

    public function check_if_exists_ecn2($refno, $customer_guid)
    {

        $query = $this->db->query("SELECT a.Code
        FROM b2b_summary.dbnotemain AS a
        WHERE refno = '$refno'
        AND customer_guid = '$customer_guid'");

        return $query;
    }

    public function check_if_exists_ecn2_supcode($check_if_exists_ecn2_code, $customer_guid)
    {
        $query = $this->db->query("SELECT b.*
        FROM b2b_summary.supcus a
        LEFT JOIN b2b_summary.`supcus` b
        ON a.`AccountCode` = b.`AccountCode`
        AND a.`customer_guid` = b.customer_guid
        WHERE a.code = '$check_if_exists_ecn2_code'
        AND a.customer_guid = '$customer_guid'
        GROUP BY b.`customer_guid`,b.code");

        return $query;
    }

    public function check_grn_if_exists_ecn3($sup_cn_no, $customer_guid, $con_req_no, $check_if_exists_ecn2_supcode_string2)
    {
        $query = $this->db->query("SELECT b.*
        FROM b2b_summary.ecn_main a
        INNER JOIN b2b_summary.grmain b
        ON a.`customer_guid` = b.`customer_guid`
        AND a.refno = b.refno
        WHERE CONCAT(a.refno,'-',a.type) != '$con_req_no'
        AND a.customer_guid = '$customer_guid'
        AND a.ext_doc1 = '$sup_cn_no'
        AND CODE IN($check_if_exists_ecn2_supcode_string2)");

        return $query;
    }

    public function check_prdncn_if_exists_ecn3($sup_cn_no, $customer_guid, $refno, $check_if_exists_ecn2_supcode_string2)
    {

        $query = $this->db->query("SELECT b.*
        FROM b2b_summary.ecn_main a
        INNER JOIN b2b_summary.dbnotemain b
        ON a.`customer_guid` = b.`customer_guid`
        AND a.refno = b.refno
        WHERE a.refno != '$refno'
        AND a.customer_guid = '$customer_guid'
        AND a.ext_doc1 = '$sup_cn_no'
        AND CODE IN($check_if_exists_ecn2_supcode_string2)");

        return $query;
    }

    public function check_grncn_if_exists_ecn3($sup_cn_no, $customer_guid, $refno, $check_if_exists_ecn2_supcode_string2)
    {

        $query = $this->db->query("SELECT b.*
        FROM b2b_summary.ecn_main a
        INNER JOIN b2b_summary.grmain b
        ON a.`customer_guid` = b.`customer_guid`
        AND a.refno = b.refno
        WHERE a.refno != '$refno'
        AND a.customer_guid = '$customer_guid'
        AND a.ext_doc1 = '$sup_cn_no[0]'
        AND CODE IN($check_if_exists_ecn2_supcode_string2)");

        return $query;
    }

    public function ecnmain($customer_guid, $refno, $type)
    {
        $query = $this->db->query("SELECT *
        FROM b2b_summary.ecn_main
        WHERE customer_guid = '$customer_guid'
        AND refno = '$refno'
        AND type = '$type'");

        return $query;
    }

    public function ecnchild($customer_guid, $refno, $type)
    {
        $query = $this->db->query("SELECT *
        FROM b2b_summary.ecn_child
        WHERE customer_guid = '$customer_guid'
        AND refno = '$refno'
        AND transtype = '$type'");

        return $query;
    }

    public function check_exist($customer_guid, $refno, $type, $i)
    {
        $query = $this->db->query("SELECT a.revision
        FROM b2b_summary.ecn_main AS a
        WHERE customer_guid = '$customer_guid'
        AND refno = '$refno[$i]'
        AND type = '$type'");

        return $query;
    }

    public function check_if_exists_einv($refno, $customer_guid, $check_einvno)
    {
        $query = $this->db->query("SELECT *
        FROM b2b_summary.einv_main
        WHERE refno != '$refno'
        AND customer_guid = '$customer_guid'
        AND invno = '$check_einvno'");

        return $query;
    }

    public function check_if_exists_einv2($refno, $customer_guid)
    {
        $query = $this->db->query("SELECT a.Code
        FROM b2b_summary.grmain as a
        WHERE a.refno = '$refno'
        AND a.customer_guid = '$customer_guid'");

        return $query;
    }

    public function check_if_exists_einv2_supcode($check_if_exists_einv2_code, $customer_guid)
    {
        $query = $this->db->query("SELECT b.*
        FROM b2b_summary.supcus a
        LEFT JOIN b2b_summary.`supcus` b
        ON a.`AccountCode` = b.`AccountCode`
        AND a.`customer_guid` = b.customer_guid
        WHERE a.code = '$check_if_exists_einv2_code'
        AND a.customer_guid = '$customer_guid'
        GROUP BY b.`customer_guid`,b.code");

        return $query;
    }

    public function check_if_exists_einv3($refno, $customer_guid, $check_einvno, $check_if_exists_einv2_supcode_string2)
    {
        $query = $this->db->query("SELECT b.*
        FROM b2b_summary.einv_main a
        INNER JOIN b2b_summary.grmain b
        ON a.`customer_guid` = b.`customer_guid`
        AND a.refno = b.refno
        WHERE a.refno != '$refno'
        AND a.customer_guid = '$customer_guid'
        AND a.invno = '$check_einvno'
        AND CODE IN($check_if_exists_einv2_supcode_string2)");

        return $query;
    }
    public function get_einv_info($refno, $customer_guid)
    {
        $query = $this->db->query("SELECT a.*
        FROM b2b_summary.einv_main as a
        WHERE refno = '$refno'
        AND customer_guid = '$customer_guid'");

        return $query;
    }
    public function total_child($get_einv_guid)
    {
        $query = $this->db->query("SELECT round(sum(total_amt_excl_tax),2) as total_excl_tax ,
        round(sum(total_tax_amt),2) as tax_amount,
        round(sum(total_amt_incl_tax),2) as total_incl_tax
        from b2b_summary.einv_child
        where einv_guid = '$get_einv_guid' ");

        return $query;
    }

    public function gr_info($refno)
    {
        $query = $this->db->query("SELECT  b.*,a.created_at,a.updated_at,
        IFNULL(IF(DATE_FORMAT(a.created_at,'%Y-%m-%d') < DATE_FORMAT(NOW(),'%Y-%m-%d')+INTERVAL 14 DAY,'1','0'),'1') AS reupload_duration
        FROM b2b_summary.ecn_main AS a
        INNER JOIN b2b_summary.dbnotemain AS b
        ON a.refno = b.refno
        AND a.customer_guid = b.customer_guid
        where a.type = 'PRDNCN'
        and a.refno = '$refno' ");

        return $query;
    }

    public function grnn_info($refno, $customer_guid)
    {
        $query = $this->db->query("SELECT
        a.`loc_group` as Location
        , a.`Code`
        , a.`Name`
        , ifnull(b.invno,a.`Invno`) as Invno
        FROM b2b_summary.grmain AS a
        LEFT JOIN b2b_summary.grmain_proposed AS b
        ON a.refno = b.refno
        AND a.customer_guid = b.customer_guid where a.refno = '$refno'
        and a.customer_guid = '$customer_guid' ");

        return $query;
    }

    public function get_child_dncn($check_url, $req_refno, $transtype)
    {

        $to_shoot_url = $check_url->row('rest_url') . "/childdata?table=grdncn" . "&refno=" . $req_refno . "&transtype=" . $transtype;
        $ch = curl_init($to_shoot_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        //from here get child, then we need insert child
        if ($response !== false) {
            $get_child_dncn = json_decode(file_get_contents($to_shoot_url), true);
            $child_result_validation = $get_child_dncn[0]['line'];
        } else {
            $get_child_dncn = array();
            $child_result_validation = '0';
            $message = 'Connection fail at customer server.Generation of E CN is currently not available.';
        }
        return $get_child_dncn;
    }

    public function to_shoot_url_propose_po($url)
    {
        $to_shoot_url = $url;

        $cuser_name = 'ADMIN';
        $cuser_pass = '1234';

        $ch = curl_init($to_shoot_url);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-API-KEY: " . "CODEX1234" ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Api-KEY: 123456"));
        curl_setopt($ch, CURLOPT_USERPWD, "$cuser_name:$cuser_pass");
        curl_setopt($ch, CURLOPT_POST, 1);

        $response = curl_exec($ch);
        $output = json_decode($response);
        //$status = json_encode($output);
        // print_r($output->result);die;
        //echo $result;die;
        //close connection
        curl_close($ch);
        //echo $output->status;
        //die;
        return $output;
    }

    public function proposed_po_main_list($db, $batch_guid)
    {

        $query = $this->db->query("SELECT a.batch_guid,a.poex_guid,a.trans_type,a.sup_code,a.sup_name,a.remark,a.docdate,a.delivery_date,
        a.created_at,a.created_by,a.updated_at,a.updated_by,a.doc_type
        FROM $db.po_ex_proposed AS a
        WHERE batch_guid = '$batch_guid'");

        return $query;
    }

    public function proposed_po_main_child_list($db, $poex_guid)
    {
        $query = $this->db->query("SELECT a.batch_guid,a.detail_guid,a.poex_guid,a.seq,a.itemcode,
        a.description,a.qty_propose,a.qty_actual,a.price_propose,a.price_actual,
        a.qty_foc_propose,a.qty_foc_actual,a.amount_propose,a.amount_actual,a.packsize,
        a.created_by,a.created_at,a.updated_by,a.updated_at
        FROM $db.po_ex_c_proposed as a
        WHERE batch_guid = '$poex_guid'
        ORDER BY seq ASC");

        return $query;
    }

    public function propose_po_sup_code($db, $user_guid, $customer_guid, $module_code)
    {
        if (in_array('IAVA', $module_code)) {
            // IF(c.`branch_desc` = '' OR c.`branch_desc` IS NULL,CONCAT(c.branch_code,' - ',b.branch_name),CONCAT(c.branch_code,' - ',c.branch_desc)) AS branch_description
            $query = $this->db->query("SELECT a.code,CONCAT(a.code,' - ',a.name)as name,a.supcus_guid
            FROM $db.supcus a
            WHERE a.type = 'S'
            ORDER BY a.code ASC");
        } else {
            $query = $this->db->query("SELECT c.code,c.name,c.supcus_guid
            FROM " . $this->tb_lite_b2b . ".set_supplier_user_relationship a
            INNER JOIN " . $this->tb_lite_b2b . ".set_supplier_group b
            ON a.supplier_group_guid = b.supplier_group_guid
            AND a.customer_guid = b.customer_guid
            INNER JOIN $db.supcus c
            ON b.supplier_group_name = c.Code
            WHERE a.user_guid = '$user_guid'
            AND a.customer_guid = '$customer_guid'");
        }

        return $query;
    }

    public function propose_po_loc_group($db, $user_guid, $customer_guid, $module_code)
    {
        if (in_array('IAVA', $module_code)) {
            $from_database = "" . $this->tb_lite_b2b . ".acc_branch b";

            $condition = "";
        } else {
            $from_database = "" . $this->tb_lite_b2b . ".set_user_branch a
            INNER JOIN " . $this->tb_lite_b2b . ".acc_branch b
            ON a.branch_guid = b.branch_guid";

            $condition = "WHERE a.user_guid = '$user_guid'
            AND a.acc_guid = '$customer_guid'";
        }

        $query = $this->db->query("SELECT c.branch_code,b.branch_name,
            IF(c.`branch_desc` = '' OR c.`branch_desc` IS NULL,CONCAT(c.branch_code,' - ',b.branch_name),CONCAT(c.branch_code,' - ',c.branch_desc)) AS branch_description
            FROM $from_database
            INNER JOIN $db.cp_set_branch c
            ON b.branch_code = c.branch_code
            $condition
            GROUP BY c.BRANCH_GUID
            ORDER BY b.branch_code ASC");

        return $query;
    }

    public function propose_po_main_list($db, $user_guid, $sup_code, $data)
    {
        if ($data == 'data') {

            $select = "a.poex_guid,a.trans_type,a.sup_code,a.sup_name,a.docdate,
            a.delivery_date,a.created_at,a.created_by,a.updated_at,a.updated_by";
        } elseif ($data == 'last_created') {

            $select = "MAX(created_at) as last_created";
        } else {
            echo 'Invalid Input';
            die;
        }

        $query = $this->db->query("SELECT $select
        FROM $db.po_ex as a
        WHERE sup_code = '$sup_code'
        AND created_by = '$user_guid'
        ORDER BY branch ASC");

        return $query;
    }

    public function propose_po_child_list($db, $poex_guid)
    {

        $query = $this->db->query("SELECT a.detail_guid,a.poex_guid,a.itemcode,
        a.description,a.qty_propose,a.qty_actual,a.price_propose,a.price_actual,
        a.created_at,a.created_by,a.updated_at,a.updated_by
        FROM $db.po_ex_c as a
        WHERE poex_guid = '$poex_guid'
        ORDER BY seq ASC");

        return $query;
    }

    public function generate_propose_po($db, $sup_code)
    {

        $query = $this->db->query("SELECT a.guid,a.code,a.status FROM $db.store_loader as a
        WHERE code = '$sup_code'");

        return $query;
    }

    public function generate_propose_po_duration($customer_guid)
    {

        $query = $this->db->query("SELECT a.acc_guid,a.acc_name,a.load_duration
        FROM " . $this->tb_lite_b2b . ".store_duration as a
        WHERE acc_guid = '$customer_guid'");

        return $query;
    }
    public function check_insert_main($db, $sup_code, $user_guid)
    {

        $query = $this->db->query("SELECT *
        FROM $db.po_ex_proposed
        WHERE sup_code = '$sup_code'
        AND created_by = '$user_guid'
        ORDER BY branch ASC");

        return $query;
    }

    //proposed batch
    public function po_ex_batch_proposed($db, $sup_code, $user_guid)
    {

        $query = $this->db->query("SELECT a.batch_guid,a.sup_code,a.created_at,a.created_by,a.updated_at,a.updated_by,a.proposed_at,a.proposed_by, b.user_name
        FROM $db.po_ex_batch_proposed a
        INNER JOIN lite_b2b.`set_user` b
        ON a.`created_by` = b.`user_guid`
        WHERE a.sup_code = '$sup_code'
        AND a.created_by = '$user_guid'
        GROUP BY b.`user_guid`
        ORDER BY proposed_at DESC");

        return $query;
    }

    public function check_propose_main_list($db, $sup_code, $user_guid)
    {

        $query = $this->db->query("SELECT COUNT(*) AS number_main
        FROM $db.po_ex a
        WHERE a.`sup_code` = '$sup_code'
        AND a.`created_by` = '$user_guid'");

        return $query;
    }

    public function check_propose_child_list($db, $sup_code, $user_guid)
    {

        $query = $this->db->query("SELECT COUNT(*) AS numbering
        FROM $db.po_ex a
        INNER JOIN $db.po_ex_c b
        ON a.`poex_guid` = b.`poex_guid`
        WHERE a.`sup_code` = '$sup_code'
        AND a.`created_by` = '$user_guid'
        AND b.`qty_actual` >= '1'");

        return $query;
    }

    public function proposed_batch_list_by_approval($db, $status, $user_guid)
    {

        $query = $this->db->query("SELECT a.* , b.user_id,
        IF(a.`status` = '1','Approved','No Approve') AS status_naming
        FROM $db.po_ex_batch_proposed a
        LEFT JOIN lite_b2b.`set_user` b
        ON a.`created_by` = b.`user_guid`
        WHERE a.created_by = '$user_guid'
        AND status = '$status'
        GROUP BY b.`user_guid`
        ORDER BY proposed_at DESC");

        return $query;
    }

    public function get_official_receipt_file_path($doc_type, $check_is_b2b_invoice, $refno, $sup_code)
    {
        $doctype = $doc_type;
        $doctime = $check_is_b2b_invoice->row('DocDate');

        $input_array = $this->db->query("SELECT * FROM b2b_doc.other_doc_mapping WHERE file_refno = '$refno' AND file_supcode = '$sup_code'");
        // print_r($input_array->result());die;
        $supcode = $input_array->row('file_supcode');
        $refno = $input_array->row('file_refno');

        $file_format = $this->db->query("SELECT value FROM b2b_doc.b2b_setting_parameter WHERE module = 'autocount' and type = 'file_format'")->row('value');
        $time_format_column = $this->db->query("SELECT * FROM b2b_doc.b2b_setting_parameter WHERE module = 'autocount' AND type = 'time_format_column'")->row('value');
        $time_format = $this->db->query("SELECT * FROM b2b_doc.b2b_setting_parameter WHERE module = 'autocount' AND type = 'time_format'")->row('value');
        $to_location = $this->db->query("SELECT DATE_FORMAT('$doctime','%Y-%m') as value")->row('value');
        $cut = explode('_', $file_format);

        $file_name = '';
        $i = 1;
        foreach ($cut as $row) {
            if ($i == $time_format_column) {
                $time = $$row;
                $row = $this->db->query("SELECT DATE_FORMAT('$time','$time_format') as xdate")->row('xdate');
                $file_name .= $row . '_';
                // echo $this->db->last_query().'asd'.$row;die;
            } else {
                $file_name .= $$row . '_';
                // echo $file_name;
            }

            $i++;
        }
        $filename = rtrim($file_name, '_');
        // $file = $to_location . '/' . $to_location2 . '/' . $filename . '.pdf';
        $file = $to_location . '/' . $filename . '.pdf';
        // $filename = $filename . '.pdf';
        return $file;
    }

    public function registration_list($customer_guid)
    {

        $query = $this->db->query("SELECT aa.`supplier_info_guid`,aa.`register_no`,aa.`supplier_name`,
        aa.`acc_name`,aa.`comp_email`,aa.`acc_no`,aa.`create_at`,aa.`create_by`,aa.`update_at`,
        aa.`update_by`,aa.`register_guid`,aa.`cnt`,aa.`part_cnt`,aa.`customer_guid`,aa.`form_status`
        FROM
        (SELECT c.`supplier_name`,
           e.`supplier_info_guid`,
           a.*,
           d.*
           FROM register_new a
           INNER JOIN acc b ON b.acc_guid = a.`customer_guid`
           LEFT JOIN set_supplier c ON c.supplier_guid = a.`supplier_guid`
           LEFT JOIN set_supplier_info e ON a.`register_guid` = e.register_guid
           LEFT JOIN
           (SELECT DISTINCT register_guid AS register_id,COUNT(`ven_name`) AS cnt,COUNT(`part_name`) AS part_cnt
           FROM register_child_new
           GROUP BY register_guid) d ON a.`register_guid` = d.register_id) aa
           WHERE aa.customer_guid = '$customer_guid'");

        return $query;
    }

    public function user_module_list($module_group_guid, $user_group_guid)
    {
        if ($user_group_guid != '') {
            $user_group_guid_where = "AND d.`user_group_guid` = '$user_group_guid'";
        } else {
            $user_group_guid_where = "";
        }
        $query = $this->db->query("SELECT a.*,
        b.module_code,
        b.`module_name`,
        c.`module_group_name`,
        d.`user_group_name`
        FROM " . $this->tb_lite_b2b . ".set_user_module a
        INNER JOIN " . $this->tb_lite_b2b . ".set_module b
        ON a.`module_guid` = b.`module_guid`
        INNER JOIN " . $this->tb_lite_b2b . ".set_module_group c
        ON a.`module_group_guid` = c.`module_group_guid`
        INNER JOIN " . $this->tb_lite_b2b . ".set_user_group d
        ON d.`user_group_guid` = a.`user_group_guid`
        WHERE c.`module_group_guid` = '$module_group_guid'
        $user_group_guid_where
        ORDER BY a.created_at DESC
        ");

        return $query;
    }

    public function module_group_list()
    {

        $query = $this->db->query("SELECT *
        FROM " . $this->tb_lite_b2b . ".set_module_group
        ORDER BY module_group_seq ASC");

        return $query;
    }



    public function user_group_list($module_group_guid, $customer_guid)
    {
        $query = $this->db->query("SELECT a.*,d.`module_group_name`,e.`user_group_name`	
        FROM lite_b2b.set_user a	
        INNER JOIN lite_b2b.set_user_module b	
        ON a.`user_group_guid` = b.`user_group_guid`	
        INNER JOIN lite_b2b.set_module c	
        ON c.`module_guid` = b.`module_guid`	
        INNER JOIN lite_b2b.set_module_group d	
        ON d.`module_group_guid` = c.`module_group_guid`	
        AND d.`module_group_guid` = a.`module_group_guid`	
        INNER JOIN lite_b2b.set_user_group e	
        ON e.`user_group_guid` = a.`user_group_guid`	
        WHERE d.`module_group_guid` = '$module_group_guid'	
        AND a.acc_guid = '$customer_guid'	
        GROUP BY a.user_id");
        return $query;
    }

    public function user_list($module_group_guid, $customer_guid)
    {

        $query = $this->db->query("SELECT a.*,d.`module_group_name`,e.`user_group_name`
        FROM " . $this->tb_lite_b2b . ".set_user a
        INNER JOIN " . $this->tb_lite_b2b . ".set_user_module b
        ON a.`user_group_guid` = b.`user_group_guid`
        INNER JOIN " . $this->tb_lite_b2b . ".set_module c
        ON c.`module_guid` = b.`module_guid`
        INNER JOIN " . $this->tb_lite_b2b . ".set_module_group d
        ON d.`module_group_guid` = c.`module_group_guid`
        AND d.`module_group_guid` = a.`module_group_guid`
        INNER JOIN " . $this->tb_lite_b2b . ".set_user_group e
        ON e.`user_group_guid` = a.`user_group_guid`
        WHERE d.`module_group_guid` = '$module_group_guid'
        AND a.acc_guid = '$customer_guid'
        GROUP BY a.user_id");

        return $query;
    }

    public function module_list($module_group_guid)
    {

        $query = $this->db->query("SELECT a.*,c.`module_group_name`
        FROM " . $this->tb_lite_b2b . ".set_module a
        INNER JOIN " . $this->tb_lite_b2b . ".acc_module b
        ON a.`module_guid` = b.`acc_module_guid`
        INNER JOIN " . $this->tb_lite_b2b . ".set_module_group c
        ON c.`module_group_guid` = a.`module_group_guid`
        WHERE c.`module_group_guid` = '$module_group_guid'
        ORDER BY module_seq ASC");

        return $query;
    }

    public function get_prdn_header_detail($refno, $customer_guid, $db_table, $type)
    {

        $query = $this->db->query("SELECT @row := @row + 1 AS rowx, a.customer_guid,
        a.status, a.Type, a.RefNo, a.Location, a.DocNo, a.DocDate, a.IssueStamp,
        a.LastStamp, a.PONo, a.SCType, a.Code, a.Name, a.Term, a.Issuedby, a.Remark,
        a.BillStatus, a.AccStatus, a.DueDate, a.Amount, a.Closed, a.SubDeptCode, a.postby,
        a.postdatetime, a.Consign, a.EXPORT_ACCOUNT, a.EXPORT_AT, a.EXPORT_BY, a.hq_update,
        a.locgroup, a.ibt, a.SubTotal1, a.Discount1, a.Discount1Type, a.SubTotal2, a.Discount2,
        a.Discount2Type, a.gst_tax_sum, a.tax_code_purchase,
        IF(b.ext_doc1 IS NULL, a.sup_cn_no, b.ext_doc1 ) AS sup_cn_no,
        IF(b.ext_date1 IS NULL, a.sup_cn_date, b.ext_date1) AS sup_cn_date,
        a.doc_name_reg, a.gst_tax_rate, a.multi_tax_code, a.refno2, a.surchg_tax_sum, a.gst_adj,
        a.rounding_adj, a.unpostby, a.unpostdatetime, a.ibt_gst, a.acc_posting_date,a.RoundAdjNeed
        FROM b2b_summary.$db_table AS a
        LEFT JOIN (
            SELECT * FROM b2b_summary.ecn_main
            WHERE customer_guid = '$customer_guid'
            AND refno = '$refno'
            AND `type` = 'PRDNCN') AS b
            ON a.refno = b.refno
            WHERE a.refno = '$refno'
            AND a.customer_guid = '$customer_guid'
            AND a.type = '$type'");

        return $query;
    }

    public function check_prdncn_supplier_guid($refno, $customer_guid)
    {

        $query = $this->db->query("SELECT b.`supplier_guid`
        FROM b2b_summary.dbnotemain a
        INNER JOIN lite_b2b.`set_supplier_group` b
        ON a.code = b.`supplier_group_name`
        AND b.`customer_guid` = '$customer_guid'
        WHERE a.customer_guid = '$customer_guid'
        AND a.refno = '$refno'");

        return $query;
    }

    public function check_grn_supplier_guid($refno, $customer_guid)
    {

        $query = $this->db->query("SELECT b.`supplier_guid`
        FROM b2b_summary.grmain a
        INNER JOIN lite_b2b.`set_supplier_group` b
        ON a.code = b.`supplier_group_name`
        AND b.`customer_guid` = '$customer_guid'
        WHERE a.customer_guid = '$customer_guid'
        AND a.refno = '$refno'");

        return $query;
    }

    public function check_consign_supplier_guid($db, $supcode, $customer_guid, $period_code)
    {

        $query = $this->db->query("SELECT b.`supplier_guid`
        FROM $db.acc_trans a
        INNER JOIN lite_b2b.`set_supplier_group` b
        ON a.supcus_code = b.`supplier_group_name`
        AND b.`customer_guid` = '$customer_guid'
        WHERE a.unique_key = '$supcode'
        AND LEFT(a.date_trans,7) = '$period_code'");

        return $query;
    }

    public function total_ticket($ticket_status)
    {

        $query = $this->db->query(" SELECT SUM(ticket_status = 'New') AS total_open,
        SUM(ticket_status = 'In-Progress') AS total_in_progress,
        SUM(ticket_status = 'Closed') AS total_closed
         FROM (
            SELECT
            a.ticket_status
     FROM lite_b2b.ticket a
            INNER JOIN lite_b2b.ticket_topic b ON a.topic_guid = b.t_topic_guid
            INNER JOIN lite_b2b.ticket_sub_topic c ON a.sub_topic_guid = c.t_sub_topic_guid
            LEFT JOIN
              (SELECT *
               FROM lite_b2b.set_user
               GROUP BY user_guid) d ON a.created_by = d.user_guid
            LEFT JOIN
              (SELECT *
               FROM lite_b2b.set_user
               GROUP BY user_guid) e ON a.assigned = e.user_guid
            LEFT JOIN lite_b2b.set_supplier f ON d.supplier_guid = f.supplier_guid
            LEFT JOIN
              (SELECT *
               FROM lite_b2b.set_supplier_group
               GROUP BY supplier_guid) g ON f.`supplier_guid` = g.`supplier_guid`
            LEFT JOIN
              lite_b2b.acc h
              ON a.`acc_guid` = h.`acc_guid`
        )zz
        WHERE zz.ticket_status IN ($ticket_status)");

        return $query;
    }

    public function module_setup_check_add_user($module_group_guid, $user_id, $user_guid)
    {

        $query = $this->db->query("SELECT a.*,d.`module_group_name`,e.`user_group_name`
        FROM lite_b2b.set_user a
        INNER JOIN lite_b2b.set_user_module b
        ON a.`user_group_guid` = b.`user_group_guid`
        INNER JOIN lite_b2b.set_module c
        ON c.`module_guid` = b.`module_guid`
        INNER JOIN lite_b2b.set_module_group d
        ON d.`module_group_guid` = c.`module_group_guid`
        AND d.`module_group_guid` = a.`module_group_guid`
        INNER JOIN lite_b2b.set_user_group e
        ON e.`user_group_guid` = a.`user_group_guid`
        WHERE d.`module_group_guid` = '$module_group_guid'
        AND a.`user_id` = '$user_id'
        AND user_guid != '$user_guid'
        GROUP BY a.`user_id`");

        return $query;
    }

    public function scode_dropdown_list($customer_guid)
    {

        $query = $this->db->query("SELECT *
        FROM lite_b2b.set_supplier a
        INNER JOIN lite_b2b.set_supplier_group b
        ON a.supplier_guid = b.supplier_guid
        WHERE b.customer_guid = '$customer_guid'
        GROUP BY a.supplier_guid
        ORDER BY a.supplier_name ASC");

        return $query;
    }

    public function registered_supplier_list($supplier_name)
    {
        if ($supplier_name == '') {
            $supplier_where = "";
        } else {
            $supplier_where = "WHERE a.supplier_name LIKE '%'.$supplier_name.'%'";
        }

        $query = $this->db->query("SELECT *
        FROM lite_b2b.set_supplier AS a
        $supplier_where
        GROUP BY a.supplier_guid
        ORDER BY a.supplier_name ASC");

        return $query;
    }

    public function erp_supplier_group_list($supplier_name, $supplier_guid, $customer_guid)
    {
        if ($supplier_guid == '') {
            $supplier_guid_where = "";
        } else {
            $supplier_guid_where = "AND b.supplier_guid = '$supplier_guid' ";
        }

        if ($supplier_name == '') {
            $supplier_where = "";
        } else {
            $supplier_where = "AND b.supplier_name LIKE '%'.$supplier_name.'%'";
        }

        $query = $this->db->query("SELECT a.supplier_guid,
        a.supplier_group_name,
        a.supplier_group_guid,
        b.supplier_name
        FROM lite_b2b.set_supplier_group AS a
        INNER JOIN lite_b2b.set_supplier AS b
        ON a.supplier_guid = b.supplier_guid
        WHERE customer_guid = '$customer_guid'
        $supplier_guid_where
        $supplier_where
        ORDER BY b.supplier_name ASC");

        return $query;
    }

    public function registered_supplier_user_list($supplier_name, $supplier_guid, $customer_guid)
    {
        if ($supplier_guid == '') {
            $supplier_guid_where = "";
            $join = 'LEFT JOIN';
        } else {
            $supplier_guid_where = "AND supplier_guid = '$supplier_guid' ";
            $join = 'INNER JOIN';
        }

        if ($supplier_name == '') {
            $supplier_where = "";
        } else {
            $supplier_where = "AND b.supplier_name LIKE '%'.$supplier_name.'%'";
        }

        if ($customer_guid == '') {
            $customer_guid_where = "";
        } else {
            $customer_guid_where = "WHERE acc_guid = '$customer_guid'";
        }

        $query = $this->db->query("SELECT b.supplier_guid,a.user_guid,user_id,user_name,
        f.supplier_name,all_sup_assigned,f.`name_reg`,b.acc_guid
        FROM
        (SELECT `acc_guid`,`branch_guid`,`module_group_guid`,`user_group_guid`,a.`user_guid`,
        a.`supplier_guid`,`user_id`,`user_password`,`user_name`,a.`created_at`,a.`created_by`,
        a.`updated_at`,a.`updated_by`,`supplier_name`,`reg_no`,`gst_no`,`name_reg`
        FROM lite_b2b.set_user AS a
        LEFT JOIN lite_b2b.set_supplier AS b
        ON a.supplier_guid = b.supplier_guid
        GROUP BY a.user_id
        ORDER BY a.updated_at DESC) AS a
        $join
        (SELECT supplier_guid,user_guid,GROUP_CONCAT(supplier_group_name) AS all_sup_assigned,acc_guid
        FROM lite_b2b.`check_user_supplier_customer_relationship`
        $customer_guid_where
        $supplier_guid_where
        $supplier_where
        GROUP BY user_guid,acc_guid) b
        ON a.user_guid = b.user_guid
        LEFT JOIN lite_b2b.set_supplier f
        ON b.supplier_guid = f.supplier_guid
        ");

        return $query;
    }

    public function check_current_supplier_data($reg_no, $s_guid)
    {
        if ($s_guid == '') {
            $query = $this->db->query("SELECT *
            from lite_b2b.set_supplier
            where reg_no = '$reg_no'
            union all
            select *
            from lite_b2b.set_supplier
            where reg_no = replace('$reg_no','-','')
            union all
            select *
            from lite_b2b.set_supplier
            where reg_no  = CONCAT(SUBSTRING('$reg_no','1',LENGTH('$reg_no')-1),'-', SUBSTRING('$reg_no',LENGTH('$reg_no'),LENGTH('$reg_no')-1))");
        } else {
            $query = $this->db->query("SELECT *
            from (SELECT *
            from lite_b2b.set_supplier
            where reg_no = '$reg_no'
            union all
            select *
            from lite_b2b.set_supplier
            where reg_no = replace('$reg_no','-','')
            union all
            select *
            from lite_b2b.set_supplier
            where reg_no  = CONCAT(SUBSTRING('$reg_no','1',LENGTH('$reg_no')-1),'-', SUBSTRING('$reg_no',LENGTH('$reg_no'),LENGTH('$reg_no')-1)) ) a
            where supplier_guid <> '$s_guid'");
        }

        return $query;
    }

    public function check_max_acc_code($query_supplier_name)
    {

        $query = $this->db->query("SELECT MAX(acc_code) as max_code
        FROM lite_b2b.set_supplier
        WHERE acc_code
        LIKE CONCAT('D', LEFT('$query_supplier_name', 1), '%')");

        return $query;
    }

    public function get_supplier_user_relationship($guid, $customer_guid, $supplier_group_guid)
    {
        if ($customer_guid == '') {
            $customer_guid_where = '';
        } else {
            $customer_guid_where = "AND customer_guid = '$customer_guid'";
        }

        if ($supplier_group_guid == '') {
            $supplier_group_guid_where = '';
        } else {
            $supplier_group_guid_where = "AND supplier_group_guid = '$supplier_group_guid'";
        }

        $query = $this->db->query("SELECT *
        from lite_b2b.set_supplier_user_relationship
        where supplier_guid = '$guid'
        $customer_guid_where
        $supplier_group_guid_where");

        return $query;
    }

    public function get_supplier($guid)
    {
        if ($guid == '') {
            $guid_where = "";
        } else {
            $guid_where = "WHERE supplier_guid = '$guid'";
        }

        $query = $this->db->query("SELECT *
        FROM lite_b2b.set_supplier
        $guid_where
        ORDER BY supplier_name ASC");

        return $query;
    }

    public function get_email_group($customer_guid)
    {

        $query = $this->db->query("SELECT a.user_id as email,a.user_name as first_name
        FROM lite_b2b.set_user a
        INNER JOIN lite_b2b.set_user_group b
        ON a.user_group_guid = b.user_group_guid
        INNER JOIN lite_b2b.set_user_module c
        ON b.user_group_guid = c.user_group_guid
        INNER JOIN lite_b2b.set_module d
        ON c.module_guid = d.module_guid
        INNER JOIN lite_b2b.set_module_group e
        ON d.module_group_guid = e.module_group_guid
        WHERE a.isactive = 1
        AND a.acc_guid = '$customer_guid'
        AND e.module_group_name = 'Panda B2B'
        AND c.isenable = 1
        AND d.module_code = 'RENSS'
        AND a.acc_guid != 'D361F8521E1211EAAD7CC8CBB8CC0C93'
        AND a.acc_guid != '1F90F5EF90DF11EA818B000D3AA2CAA9'
        AND a.acc_guid != '599348EDCB2F11EA9A81000C29C6CEB2'
        AND a.acc_guid != '907FAFE053F011EB8099063B6ABE2862'
        AND a.acc_guid != '13EE932D98EB11EAB05B000D3AA2838A'
        GROUP BY a.user_guid");

        return $query;
    }
    public function get_supplier_group($supplier_group_guid, $customer_guid, $supplier_guid)
    {
        if ($supplier_guid == '') {
            $supplier_where = '';
        } else {
            $supplier_where = "WHERE supplier_guid ='$supplier_guid'";
        }

        if ($supplier_group_guid == '') {
            $supplier_group_where = '';
        } else {
            $supplier_group_where = "WHERE supplier_group_guid = '$supplier_group_guid'";
        }

        if ($customer_guid == '') {
            $customer_guid_where = '';
        } else {
            $customer_guid_where = "AND customer_guid = '$customer_guid'";
        }

        $query = $this->db->query("SELECT customer_guid,supplier_guid,supplier_group_guid
        FROM lite_b2b.set_supplier_group
        $supplier_where
        $supplier_group_where
        $customer_guid_where");

        return $query;
    }

    public function get_supcus($customer_guid)
    {
        if ($customer_guid == '') {
            $customer_guid_where = '';
        } else {
            $customer_guid_where = "WHERE customer_guid = '$customer_guid'";
        }

        $query = $this->db->query("SELECT code, name, supcus_guid
        FROM b2b_summary.supcus
        $customer_guid_where
        ORDER BY NAME ASC");

        return $query;
    }

    public function get_set_supplier_group($customer_guid)
    {
        if ($customer_guid == '') {
            $customer_guid_where = '';
        } else {
            $customer_guid_where = "WHERE customer_guid = '$customer_guid'";
        }

        $query = $this->db->query("SELECT a.supplier_guid, a.supplier_group_name , a.supplier_group_guid, b.supplier_name
        FROM lite_b2b.set_supplier_group AS a
        INNER JOIN lite_b2b.set_supplier  AS b
        ON a.supplier_guid = b.supplier_guid
        $customer_guid_where
        ORDER BY a.created_at DESC");

        return $query;
    }

    public function get_acc_concept_list($customer_guid)
    {

        $query = $this->db->query("SELECT a.*,b.`acc_name`
        FROM lite_b2b.acc_concept a
        INNER JOIN lite_b2b.acc b
        ON a.`acc_guid` = b.`acc_guid`
        INNER JOIN lite_b2b.acc_branch c
        ON c.`concept_guid` = a.`concept_guid`
        WHERE a.isactive = 1
        AND a.acc_guid = '" . $customer_guid . "'
        GROUP BY a.`concept_guid`
        ORDER BY a.updated_at DESC");

        return $query;
    }

    public function get_branch_group_list($customer_guid)
    {

        $query = $this->db->query("SELECT a.*,b.`concept_name`
        FROM lite_b2b.acc_branch_group a
        INNER JOIN lite_b2b.acc_concept b
        ON a.`concept_guid` = b.`concept_guid`
        INNER JOIN lite_b2b.acc_branch c
        ON c.`branch_group_guid` = a.`branch_group_guid`
        WHERE a.isactive = 1
        AND b.acc_guid = '" . $customer_guid . "'
        GROUP BY a.`branch_group_guid`
        ORDER BY a.updated_at DESC");

        return $query;
    }

    public function get_branch_list($customer_guid)
    {

        $query = $this->db->query("SELECT b.concept_name, a.*, c.`group_name`,d.branch_desc as branch_description
        FROM lite_b2b.acc_branch a
        INNER JOIN lite_b2b.acc_concept b
        ON a.`concept_guid` = b.`concept_guid`
        INNER JOIN lite_b2b.acc_branch_group c
        ON c.`branch_group_guid` = a.`branch_group_guid`
        INNER JOIN (SELECT *
        FROM b2b_summary.cp_set_branch
        WHERE customer_guid = '" . $customer_guid . "') d
        ON a.branch_code = d.branch_code
        AND b.acc_guid = d.customer_guid
        WHERE a.isactive = 1
        and b.acc_guid = '" . $customer_guid . "'
        GROUP BY a.`branch_guid`
        ORDER BY a.branch_code ASC , a.updated_at DESC");

        return $query;
    }

    public function get_supplier_list($module_code, $user_guid, $customer_guid)
    {
        if (in_array('IAVA', $module_code)) {
            $query = $this->db->query("SELECT a.supplier_guid,a.supplier_name
            FROM lite_b2b.set_supplier a 
            INNER JOIN lite_b2b.set_supplier_group b 
            ON a.supplier_guid = b.supplier_guid 
            AND b.customer_guid = '$customer_guid' 
            GROUP BY a.supplier_guid 
            ORDER BY a.supplier_name ASC");
        } else {
            $query = $this->db->query("SELECT c.`supplier_guid`,c.`supplier_name`
            FROM lite_b2b.`set_supplier_user_relationship` a 
            INNER JOIN lite_b2b.`set_supplier_group` b 
            ON a.`supplier_group_guid` = b.`supplier_group_guid` 
            AND b.`customer_guid` = '$customer_guid' 
            INNER JOIN lite_b2b.`set_supplier` c 
            ON b.`supplier_guid` = c.`supplier_guid` 
            WHERE a.user_guid = '$user_guid' 
            AND a.`customer_guid` = '$customer_guid' 
            GROUP BY b.`supplier_guid` 
            ORDER BY c.supplier_name ASC");
        }

        return $query;
    }

    public function get_email_template($description, $type, $mail_type)
    {

        $query = $this->db->query("SELECT a.`mail_subject`,a.`body_content`,a.`body_header`,a.`body_footer`
        FROM lite_b2b.`email_template` AS a
        WHERE a.`description` = '$description'
        AND a.`type` = '$type'
        AND a.`mail_type` = '$mail_type'
        LIMIT 1");

        return $query;
    }

    public function get_edi_created_at($type, $filename, $customer_guid, $supplier_guid)
    {

        $query = $this->db->query("SELECT IFNULL(
        (SELECT IF(a.`created_at` IS NOT NULL,a.`created_at`,NOW()) AS created_at
        FROM lite_b2b.`edi_log` AS a
        WHERE a.`type` = '$type'
        AND a.`file_name` = '$filename'
        AND a.`customer_guid` = '$customer_guid'
        AND a.`supplier_guid` = '$supplier_guid'),NOW()) as created_at,
        IFNULL(
        (SELECT a.guid
        FROM lite_b2b.`edi_log` AS a
        WHERE a.`type` = '$type'
        AND a.`file_name` = '$filename'
        AND a.`customer_guid` = '$customer_guid'
        AND a.`supplier_guid` = '$supplier_guid'),REPLACE(UPPER(UUID()),'-','')) as guid");

        return $query;
    }

    public function get_email_user_list($supplier_guid, $customer_guid)
    {

        $query = $this->db->query("SELECT a.`type`,a.`email_group_name`,a.`description`,b.`customer_guid`,b.`supplier_guid`,b.`user_email`,b.`cc_email`
        FROM lite_b2b.`set_email` AS a
        INNER JOIN lite_b2b.`set_email_group` AS b
        ON a.`guid` = b.`email_group_guid`
        WHERE a.`type` = 'GRN'
        AND a.`email_group_name` = 'EDI'
        AND b.`supplier_guid` = '$supplier_guid'
        AND b.`customer_guid` = '$customer_guid'
        AND b.`is_active` = '1'");

        return $query;
    }

    public function run_filter_supplier($customer_guid,$user_guid,$module_code)
    {
        $database2 = $this->db->query("SELECT b2b_database FROM lite_b2b.acc WHERE acc_guid = '$customer_guid'")->row('b2b_database');

        if(in_array('IAVA', $module_code))
        {
            $query = $this->db->query("SELECT b.`supplier_name`,d.supcus_code
            FROM lite_b2b.`set_supplier` AS b 
            INNER JOIN lite_b2b.set_supplier_group AS c 
            ON b.`supplier_guid` = c.`supplier_guid` 
            INNER JOIN $database2.acc_trans AS d
            ON c.supplier_group_name = d.supcus_code
            WHERE c.customer_guid = '$customer_guid' 
            AND b.isactive = '1' 
            AND b.suspended = '0' 
            GROUP BY c.supplier_guid
            ORDER BY b.supplier_name ASC");
        }
        else
        {
            $query = $this->db->query("SELECT d.`supcus_name` AS `supplier_name`,d.supcus_code
            FROM lite_b2b.`set_user` AS a
            INNER JOIN lite_b2b.`set_supplier_user_relationship` AS b
            ON a.`user_guid` = b.`user_guid`
            INNER JOIN lite_b2b.set_supplier_group AS c
            ON b.`supplier_guid` = c.`supplier_guid`
            AND a.acc_guid = c.customer_guid
            INNER JOIN $database2.acc_trans AS d
            ON c.supplier_group_name = d.supcus_code
            WHERE a.`user_guid` = '$user_guid'
            AND c.customer_guid = '$customer_guid'
            GROUP BY c.supplier_guid
            ORDER BY d.supcus_name ASC");

            // $query = $this->db->query("SELECT b.`supplier_name`,d.supcus_code
            // FROM lite_b2b.`set_user` AS a
            // INNER JOIN lite_b2b.`set_supplier` AS b
            // ON a.`supplier_guid` = b.`supplier_guid`
            // AND b.isactive = '1'
            // AND b.suspended = '0'
            // INNER JOIN lite_b2b.set_supplier_group AS c
            // ON b.`supplier_guid` = c.`supplier_guid`
            // AND a.acc_guid = c.customer_guid
            // INNER JOIN $database2.acc_trans AS d
            // ON c.supplier_group_name = d.supcus_code
            // WHERE a.`user_guid` = '$user_guid'
            // AND c.customer_guid = '$customer_guid'
            // GROUP BY c.supplier_guid");
        }  
        
        return $query;
    }

    public function strb_json_data($customer_guid,$refno)
    {
        $query = $this->db->query("SELECT strb_json_info,dbnote_guid,`status` FROM b2b_summary.`dbnote_batch_info` WHERE batch_no = '$refno' AND customer_guid = '$customer_guid'");

        return $query;
    }
}
