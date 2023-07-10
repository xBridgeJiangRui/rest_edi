<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class lite_b2b_update_model extends CI_Model
{
    public $tb_lite_b2b = 'lite_b2b';
    public $tb_lite_b2b_apps = 'lite_b2b_apps';

    public function __construct()
    {
        parent::__construct();
    }

    public function view_doc($db, $table, $customer_guid, $refno)
    {
        $table = $db . '.' . $table;

        $data = array(
            'status' => 'viewed',
        );

        $this->db->where('customer_guid', $customer_guid);
        $this->db->where('refno', $refno);
        $this->db->where('status', '');
        $this->db->update($table, $data);

        return $refno;
    }

    public function view_doc_pci($db, $table, $customer_guid, $refno)
    {
        $table = $db . '.' . $table;

        $data = array(
            'status' => 'viewed',
        );

        $this->db->where('customer_guid', $customer_guid);
        $this->db->where('inv_refno', $refno);
        $this->db->where('status', '');
        $this->db->update($table, $data);

        return $refno;
    }

    public function accept_po($db, $customer_guid, $refno)
    {
        $table = $db . '.pomain';

        $data = array(
            'status' => 'Accepted',
            'b2b_status' => 'readysend',
        );

        $this->db->where('customer_guid', $customer_guid);
        $this->db->where_in('refno', $refno);
        $this->db->update($table, $data);

        return $refno;
    }

    public function reject_po($db, $customer_guid, $refno, $reason)
    {
        $nowtime = $this->db->query("SELECT now() as nowtime")->row('nowtime');

        $table = $db . '.pomain';

        $data = array(
            'status' => 'rejected',
            'b2b_status' => 'readysend',
            'rejected_remark' => $reason,
            'rejected' => '1',
            'rejected_at' => $nowtime,
        );

        $this->db->where('customer_guid', $customer_guid);
        $this->db->where('refno', $refno);
        $this->db->update($table, $data);

        return $refno;
    }

    public function change_user_password($confirm_password, $user_id, $user_guid, $acc_module_group_guid)
    {
        $nowtime = $this->db->query("SELECT now() as nowtime")->row('nowtime');

        $table = $this->tb_lite_b2b . '.set_user';

        $confirm_password = md5($confirm_password);

        $data = array(
            'user_password' => $confirm_password,
            'updated_by' => $user_id,
            'module_group_guid' => $acc_module_group_guid,
            'updated_at' => $nowtime,
        );

        $this->db->where('user_guid', $user_guid);
        $this->db->update($table, $data);

        return $user_guid;
    }

    public function notification_read($app_notification_guid)
    {
        $nowtime = $this->db->query("SELECT now() as nowtime")->row('nowtime');

        $table = $this->tb_lite_b2b_apps . '.app_notification';

        $data = array(
            'isread' => '1',
            'updated_at' => $nowtime,
        );

        $this->db->where('app_notification_guid', $app_notification_guid);
        $this->db->update($table, $data);

        return $app_notification_guid;
    }

    public function notification_all_read($user_guid, $customer_guid, $location_in)
    {
        $nowtime = $this->db->query("SELECT now() as nowtime")->row('nowtime');

        $table = $this->tb_lite_b2b_apps . '.app_notification';

        $data = array(
            'isread' => '1',
            'updated_at' => $nowtime,
        );

        $this->db->where_in('branch_code', $location_in);
        $this->db->where('customer_guid', $customer_guid);
        $this->db->where('user_guid', $user_guid);
        $this->db->where('issend', '1');
        $this->db->update($table, $data);
    }

    public function update_grn_header($data, $customer_guid, $refno)
    {

        $table = 'b2b_summary.grmain_proposed';

        $this->db->insert_batch($table, $data);

        $this->db->query("UPDATE b2b_summary.grmain_proposed AS a
        INNER JOIN b2b_summary.grmain AS b
        ON a.customer_guid = b.customer_guid
        AND a.refno = b.refno
        SET a.location = b.location
        , a.grdate = b.grdate
        , a.issuestamp = b.issuestamp
        , a.laststamp = b.laststamp
        , a.code = b.code
        , a.name = b.name
        where a.customer_guid = '$customer_guid' and a.refno = '$refno' and posted = '0'");
    }

    public function accept_rb($username, $customer_guid, $refno)
    {

        // $table = 'b2b_summary.grmain_proposed';

        // $this->db->insert_batch($table, $data);

        $this->db->query("UPDATE b2b_summary.dbnote_batch set status = '1' ,
        accepted_by = '$username', accepted_at =  now(),action_date = CURDATE(),uploaded = 0
        where customer_guid ='$customer_guid'
        and batch_no = '$refno'
        and status = '0'");
    }

    public function module_update_data($table, $col_guid, $guid, $data)
    {
        $this->db->where($col_guid, $guid);
        $this->db->update($table, $data);
    }
}
