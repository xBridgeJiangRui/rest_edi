<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Reminder_model extends CI_Model
{
  
  public function __construct()
  {
    parent::__construct();
  }

  //using
  public function select_reminder_supplier($month_end,$sec_month_end,$interval_inv_date, $interval_over_date, $interval_reg_date,$count_block,$count_warning,$count_gentle)
  {
    $query = $this->db->query("
        SELECT
        COUNT(supplier_guid) AS num_rows
        FROM
        (
        SELECT
        dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
        Registration_Invoice_Date,
        Overdue_Registration_Fees,
        Overdue_Subscriptions_Invoice_Amt,
        Total_Overdue,
        Overdue_Invoices_Count,
        Overdue_Invoice_Date_From,
        Overdue_Invoice_Date_To,
        Overdue_Invoice_Due_Date,
        Last_Subscriptions_Invoice_Count,
        Last_Invoice_Date,
        Last_Due_Date,
        Last_Invoice_Amt
        FROM
        (
        SELECT
        GROUP_CONCAT(dockey) AS dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Registration Invoice Date` ORDER BY `Registration Invoice Date` ASC ),',','1'),'') AS 'Registration_Invoice_Date',
        SUM(`Overdue Registraion Fees`) AS 'Overdue_Registration_Fees',
        SUM(`Overdue Subscriptions Invoice Amt`) AS 'Overdue_Subscriptions_Invoice_Amt',
        SUM(`Total Overdue`) AS 'Total_Overdue',
        SUM(`Overdue Invoices Count`) AS 'Overdue_Invoices_Count',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date From` ORDER BY `Overdue Invoice Date From` ASC ),',','1'),'') AS 'Overdue_Invoice_Date_From',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date To` ORDER BY `Overdue Invoice Date To` DESC ),',','1'),'') AS 'Overdue_Invoice_Date_To',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Due Date` ORDER BY `Overdue Invoice Due Date` DESC ),',','1'),'') AS 'Overdue_Invoice_Due_Date',
        SUM(`Last Subscriptions Invoice Count`) AS 'Last_Subscriptions_Invoice_Count',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Invoice Date` ORDER BY `Last Invoice Date` ASC ),',','1'),'') AS 'Last_Invoice_Date',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Due Date` ORDER BY `Last Due Date` DESC ),',','1'),'') AS 'Last_Due_Date',
        SUM(`Last Invoice Amt`) AS 'Last_Invoice_Amt'
        FROM
        (
        SELECT
         GROUP_CONCAT(dockey) AS dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
         CAST(IF(aa.DisplayTerm = 'CASH' ,aa.docdate,'') AS DATE) AS 'Registration Invoice Date',
         ROUND(IF(aa.DisplayTerm = 'CASH' ,SUM(IF(aa.`group_data` = '1',aa.outstanding,'')),'0.00'),2) AS 'Overdue Registraion Fees',
         ROUND(IF(aa.DisplayTerm = '30' ,SUM(IF(aa.`group_data` = '1',aa.outstanding,'')),'0.00'),2) AS 'Overdue Subscriptions Invoice Amt',
         ROUND(IF(aa.`group_data` = '1',SUM(aa.outstanding),''),2) AS 'Total Overdue',
         IF(aa.`group_data` = '1',COUNT(aa.docno),'0') AS 'Overdue Invoices Count',
         CAST(IF(aa.`group_data` = '1',MIN(aa.docdate),'') AS DATE) AS 'Overdue Invoice Date From',
         CAST(IF(group_data = '1',MAX(aa.docdate),'') AS DATE) AS 'Overdue Invoice Date To',
         DATE_ADD(CAST(IF(aa.`group_data` = '1',MAX(aa.docdate),'') AS DATE), INTERVAL '$interval_over_date' DAY) AS 'Overdue Invoice Due Date',
         IF(group_data = '2',COUNT(aa.docno),'0')AS 'Last Subscriptions Invoice Count',
         CAST(IF(aa.`group_data` = '2',MIN(aa.docdate),'') AS DATE) AS 'Last Invoice Date',
         ROUND(IF(aa.`group_data` = '2',SUM(aa.outstanding),''),2) AS 'Last Invoice Amt',
         DATE_ADD(CAST(IF(aa.`group_data` = '2',MIN(aa.docdate),'') AS DATE), INTERVAL '$interval_inv_date' DAY) AS 'Last Due Date',
         '1' AS final_group_data
        FROM
        (
        SELECT 
          c.`backend_supplier_code` AS `Code`,
          b.`supplier_name`,
          b.supplier_guid,
          b.`reg_no`,
          a.`DebtorCode`,
          a.`DocDate`,
          a.`Outstanding`,
          a.`DisplayTerm`,
          a.dockey,
          a.`DocNo`,
          a.`LastModified`,
          IF(a.`DocDate` != '$month_end','1','2') AS group_data,
          IFNULL(e.amount,'') AS cn_amount
        FROM
          b2b_account.`arinvoice` a 
          INNER JOIN lite_b2b.`set_supplier` b
          ON a.`DebtorCode` = b.acc_code
          LEFT JOIN lite_b2b.`set_supplier_group` c
          ON b.`supplier_guid` = c.`supplier_guid`
          LEFT JOIN b2b_account.`arcn` d
          ON a.debtorcode = d.debtorcode
          AND a.outstanding = d.knockoffamt
          LEFT JOIN b2b_account.`arcnknockoff` e
          ON d.dockey = e.C_DocKey
          AND a.DocNo = e.I_DocNo
          LEFT JOIN lite_b2b.reminder_tag_exclude f
          ON b.supplier_guid = f.supplier_guid
        WHERE a.outstanding != '0.00' 
        AND f.supplier_guid IS NULL
        GROUP BY a.DocNo,d.dockey
        )aa
        WHERE aa.outstanding != aa.cn_amount
        GROUP BY aa.debtorcode,aa.group_data,aa.displayterm
        ORDER BY aa.supplier_name ASC , aa.`DocNo` DESC
        )aaa
        GROUP BY aaa.final_group_data,aaa.supplier_name
        )aaaa
        )aaaaa
      ")->row('num_rows');
      
       return $query; 
  }

  //using
  public function insert_reminder_supplier($month_end,$sec_month_end,$interval_inv_date, $interval_over_date, $interval_reg_date,$count_block,$count_warning,$count_gentle)
  {
    $query = $this->db->query("
        INSERT INTO `lite_b2b`.`query_outstanding_new` (
            `dockey`,
            `Code`,
            `DebtorCode`,
            `supplier_guid`,
            `supplier_name`,
            `reg_no`,
            `LastModified`,
            `Registration_Invoice_Date`,
            `Overdue_Registration_Fees`,
            `Overdue_Subscriptions_Invoice_Amt`,
            `Total_Overdue`,
            `Overdue_Invoices_Count`,
            `Overdue_Invoice_Date_From`,
            `Overdue_Invoice_Date_To`,
            `Overdue_Invoice_Due_Date`,
            `Last_Subscriptions_Invoice_Count`,
            `Last_Invoice_Date`,
            `Last_Due_Date`,
            `Last_Invoice_Amt`,
            `Variance`,
            `created_at`,
            `created_by`
          ) 
        SELECT
        *,
        IF(Overdue_Invoices_Count = '$count_gentle' AND Last_Subscriptions_Invoice_Count > '$count_gentle','3',
        IF(Overdue_Invoice_Date_From = '$sec_month_end','2',
        IF(DATE_ADD(Overdue_Invoice_Date_To, INTERVAL $interval_reg_date DAY) > CURDATE(),'2',
        IF(DATE_ADD(Overdue_Invoice_Due_Date, INTERVAL $interval_reg_date DAY) > CURDATE(),IF(Overdue_Subscriptions_Invoice_Amt = '0.00',IF(Last_Invoice_Amt = '0.00',IF(Overdue_Registration_Fees != '0.00','1','2' ),'2'),'2'),
        IF(Overdue_Invoice_Due_Date < CURDATE(),IF(Overdue_Invoices_Count >= '$count_block','1',IF(Overdue_Invoices_Count = '$count_warning','2','99')),
        '1')
        )
        )
        ) 
        )AS 'Variance',
        NOW() AS created_at,
        'BOT' AS created_by
        FROM
        (
        SELECT
        dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
        Registration_Invoice_Date,
        Overdue_Registration_Fees,
        Overdue_Subscriptions_Invoice_Amt,
        Total_Overdue,
        Overdue_Invoices_Count,
        Overdue_Invoice_Date_From,
        Overdue_Invoice_Date_To,
        Overdue_Invoice_Due_Date,
        Last_Subscriptions_Invoice_Count,
        Last_Invoice_Date,
        Last_Due_Date,
        Last_Invoice_Amt
        FROM
        (
        SELECT
        GROUP_CONCAT(dockey) AS dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Registration Invoice Date` ORDER BY `Registration Invoice Date` ASC ),',','1'),'') AS 'Registration_Invoice_Date',
        SUM(`Overdue Registraion Fees`) AS 'Overdue_Registration_Fees',
        SUM(`Overdue Subscriptions Invoice Amt`) AS 'Overdue_Subscriptions_Invoice_Amt',
        SUM(`Total Overdue`) AS 'Total_Overdue',
        SUM(`Overdue Invoices Count`) AS 'Overdue_Invoices_Count',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date From` ORDER BY `Overdue Invoice Date From` ASC ),',','1'),'') AS 'Overdue_Invoice_Date_From',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date To` ORDER BY `Overdue Invoice Date To` DESC ),',','1'),'') AS 'Overdue_Invoice_Date_To',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Due Date` ORDER BY `Overdue Invoice Due Date` DESC ),',','1'),'') AS 'Overdue_Invoice_Due_Date',
        SUM(`Last Subscriptions Invoice Count`) AS 'Last_Subscriptions_Invoice_Count',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Invoice Date` ORDER BY `Last Invoice Date` ASC ),',','1'),'') AS 'Last_Invoice_Date',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Due Date` ORDER BY `Last Due Date` DESC ),',','1'),'') AS 'Last_Due_Date',
        SUM(`Last Invoice Amt`) AS 'Last_Invoice_Amt'
        FROM
        (
        SELECT
         GROUP_CONCAT(dockey) AS dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
         CAST(IF(aa.DisplayTerm = 'CASH' ,aa.docdate,'') AS DATE) AS 'Registration Invoice Date',
         ROUND(IF(aa.DisplayTerm = 'CASH' ,SUM(IF(aa.`group_data` = '1',aa.outstanding,'')),'0.00'),2) AS 'Overdue Registraion Fees',
         ROUND(IF(aa.DisplayTerm = '30' ,SUM(IF(aa.`group_data` = '1',aa.outstanding,'')),'0.00'),2) AS 'Overdue Subscriptions Invoice Amt',
         ROUND(IF(aa.`group_data` = '1',SUM(aa.outstanding),''),2) AS 'Total Overdue',
         IF(aa.`group_data` = '1',COUNT(aa.docno),'0') AS 'Overdue Invoices Count',
         CAST(IF(aa.`group_data` = '1',MIN(aa.docdate),'') AS DATE) AS 'Overdue Invoice Date From',
         CAST(IF(group_data = '1',MAX(aa.docdate),'') AS DATE) AS 'Overdue Invoice Date To',
         DATE_ADD(CAST(IF(aa.`group_data` = '1',MAX(aa.docdate),'') AS DATE), INTERVAL '$interval_over_date' DAY) AS 'Overdue Invoice Due Date',
         IF(group_data = '2',COUNT(aa.docno),'0')AS 'Last Subscriptions Invoice Count',
         CAST(IF(aa.`group_data` = '2',MIN(aa.docdate),'') AS DATE) AS 'Last Invoice Date',
         ROUND(IF(aa.`group_data` = '2',SUM(aa.outstanding),''),2) AS 'Last Invoice Amt',
         DATE_ADD(CAST(IF(aa.`group_data` = '2',MIN(aa.docdate),'') AS DATE), INTERVAL '$interval_inv_date' DAY) AS 'Last Due Date',
         '1' AS final_group_data
        FROM
        (
        SELECT 
          c.`backend_supplier_code` AS `Code`,
          b.`supplier_name`,
          b.supplier_guid,
          b.`reg_no`,
          a.`DebtorCode`,
          a.`DocDate`,
          a.`Outstanding`,
          a.`DisplayTerm`,
          a.dockey,
          a.`DocNo`,
          a.`LastModified`,
          IF(a.`DocDate` != '$month_end','1','2') AS group_data,
          IFNULL(e.amount,'') AS cn_amount
        FROM
          b2b_account.`arinvoice` a 
          INNER JOIN lite_b2b.`set_supplier` b
          ON a.`DebtorCode` = b.acc_code
          LEFT JOIN lite_b2b.`set_supplier_group` c
          ON b.`supplier_guid` = c.`supplier_guid`
          LEFT JOIN b2b_account.`arcn` d
          ON a.debtorcode = d.debtorcode
          AND a.outstanding = d.knockoffamt
          LEFT JOIN b2b_account.`arcnknockoff` e
          ON d.dockey = e.C_DocKey
          AND a.DocNo = e.I_DocNo
          LEFT JOIN lite_b2b.reminder_tag_exclude f
          ON b.supplier_guid = f.supplier_guid
        WHERE a.outstanding != '0.00' 
        AND f.supplier_guid IS NULL
        GROUP BY a.DocNo,d.dockey
        )aa
        WHERE aa.outstanding != aa.cn_amount
        GROUP BY aa.debtorcode,aa.group_data,aa.displayterm
        ORDER BY aa.supplier_name ASC , aa.`DocNo` DESC
        )aaa
        GROUP BY aaa.final_group_data,aaa.supplier_name
        )aaaa
        )aaaaa
      ");
        return $query; 
  }

  //no use due to need foreach 
  public function insert_reminder_retailer($month_end,$sec_month_end,$interval_inv_date, $interval_over_date, $interval_reg_date,$count_block,$count_warning,$count_gentle,$dockey)
  {
    $query = $this->db->query("
      INSERT INTO `lite_b2b`.`query_outstanding_retailer` (`dockey`,`DebtorCode`,`customer_guid`,`supplier_guid`,`supplier_name`,`reg_no`,`Registration_Invoice_Date`,`Registration_AddON_Invoice_Amt`,`Subscription_OneOFF_Invoice_Amt`,`Training_Invoice_Amt`,`Ad_Hoc_Service_Invoice_Amt`,`Overdue_Registration_Fees`,`Overdue_Subscriptions_Invoice_Amt`,`Total_Overdue`,`Overdue_Invoices_Count`,`Overdue_Invoice_Date_From`,`Overdue_Invoice_Date_To`,`Overdue_Invoice_Due_Date`,`Last_Subscriptions_Invoice_Count`,`Last_Invoice_Date`,`Last_Due_Date`,`Last_Invoice_Amt`,`invoice_number`,`Variance`,`created_at`,`created_by`)
      SELECT
      *,
      IF(Overdue_Invoices_Count = '$count_gentle' AND Last_Subscriptions_Invoice_Count > '$count_gentle','3',
      IF(Overdue_Invoice_Date_From = '$sec_month_end',IF(Overdue_Subscriptions_Invoice_Amt != '0.00','1','2'),
      IF(CURDATE() < DATE_ADD(Overdue_Invoice_Date_To, INTERVAL 9 DAY),IF(Overdue_Invoices_Count = '1',IF(Last_Invoice_Amt = '0.00','2','1'),'1'),
      IF(DATE_ADD(Overdue_Invoice_Date_To, INTERVAL $interval_reg_date DAY) > CURDATE(),'2',
      IF(DATE_ADD(Overdue_Invoice_Due_Date, INTERVAL $interval_reg_date DAY) > CURDATE(),IF(Overdue_Subscriptions_Invoice_Amt = '0.00',IF(Last_Invoice_Amt = '0.00',IF(Overdue_Registration_Fees != '0.00','1','2' ),'2'),
      IF(Overdue_Registration_Fees != '0.00','1','2')),
      IF(Overdue_Invoice_Due_Date < CURDATE(),IF(Overdue_Invoices_Count >= '$count_block','1',IF(Overdue_Invoices_Count = '$count_warning',IF(Overdue_Registration_Fees != '0.00','1','3'),'3')),
      '1')
      )
      )
      )
      ) 
      )AS 'Variance',
      NOW() AS created_at,
      'BOT' AS created_by
      FROM
      (
      SELECT
      dockey,
      DebtorCode,
      proj_guid,
      supplier_guid,
      supplier_name,
      `reg_no`,
      Registration_Invoice_Date,
      Registration_AddON_Invoice_Amt,
      Subscription_OneOFF_Invoice_Amt,
      Training_Invoice_Amt,
      Ad_Hoc_Service_Invoice_Amt,
      Overdue_Registration_Fees,
      Overdue_Subscriptions_Invoice_Amt,
      Total_Overdue,
      Overdue_Invoices_Count,
      Overdue_Invoice_Date_From,
      Overdue_Invoice_Date_To,
      Overdue_Invoice_Due_Date,
      Last_Subscriptions_Invoice_Count,
      Last_Invoice_Date,
      Last_Due_Date,
      Last_Invoice_Amt,
      invoice_number
      FROM
      (
      SELECT
      GROUP_CONCAT(DISTINCT dockey) AS dockey,
      DebtorCode,
      proj_guid,
      supplier_guid,
      supplier_name,
      `reg_no`,
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Registration Invoice Date` ORDER BY `Registration Invoice Date` ASC ),',','1'),'') AS 'Registration_Invoice_Date',
      SUM(`Registration & Add ON Invoice Amt`) AS 'Registration_AddON_Invoice_Amt',
      SUM(`Subscription One OFF Invoice Amt`) AS 'Subscription_OneOFF_Invoice_Amt',
      SUM(`Training Invoice Amt`) AS 'Training_Invoice_Amt',
      SUM(`Ad Hoc Service Invoice Amt`) AS 'Ad_Hoc_Service_Invoice_Amt',
      SUM(`Overdue Registraion Fees`) AS 'Overdue_Registration_Fees',
      SUM(`Overdue Subscriptions Invoice Amt`) AS 'Overdue_Subscriptions_Invoice_Amt',
      SUM(`Total Overdue`) AS 'Total_Overdue',
      (COUNT(DISTINCT docno) - SUM(`Last Subscriptions Invoice Count`)) AS 'Overdue_Invoices_Count',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date From` ORDER BY `Overdue Invoice Date From` ASC ),',','1'),'') AS 'Overdue_Invoice_Date_From',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date To` ORDER BY `Overdue Invoice Date To` DESC ),',','1'),'') AS 'Overdue_Invoice_Date_To',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Due Date` ORDER BY `Overdue Invoice Due Date` DESC ),',','1'),'') AS 'Overdue_Invoice_Due_Date',
      SUM(`Last Subscriptions Invoice Count`) AS 'Last_Subscriptions_Invoice_Count',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Invoice Date` ORDER BY `Last Invoice Date` ASC ),',','1'),'') AS 'Last_Invoice_Date',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Due Date` ORDER BY `Last Due Date` DESC ),',','1'),'') AS 'Last_Due_Date',
      SUM(`Last Invoice Amt`) AS 'Last_Invoice_Amt',
      GROUP_CONCAT(DISTINCT docno ORDER BY `DocNo` ASC) AS invoice_number
      FROM
      (
      SELECT
      *,
       CAST(IF(aaa.DisplayTerm = 'CASH' ,aaa.docdate,'') AS DATE) AS 'Registration Invoice Date',
       ROUND(IF(aaa.AccNo = '500-009' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Registration & Add ON Invoice Amt',
       ROUND(IF(aaa.AccNo = '500-010' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Subscription One OFF Invoice Amt',
       ROUND(IF(aaa.AccNo = '500-011' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Training Invoice Amt',
       ROUND(IF(aaa.AccNo = '500-012' ,SUM(IF(aaa.`group_data` IN ('1','2'),aaa.amount_due,'')),'0.00'),2) AS 'Ad Hoc Service Invoice Amt',
       ROUND(IF(aaa.DisplayTerm = 'CASH' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Overdue Registraion Fees',
       ROUND(IF(aaa.DisplayTerm = '30' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Overdue Subscriptions Invoice Amt',
       ROUND(IF(aaa.`group_data` = '1',SUM(aaa.amount_due),''),2) AS 'Total Overdue',
       CAST(IF(aaa.`group_data` = '1',MIN(aaa.docdate),'') AS DATE) AS 'Overdue Invoice Date From',
       CAST(IF(aaa.group_data = '1',MAX(aaa.docdate),'') AS DATE) AS 'Overdue Invoice Date To',
       DATE_ADD(CAST(IF(aaa.`group_data` = '1',MAX(aaa.docdate),'') AS DATE), INTERVAL '$interval_over_date' DAY) AS 'Overdue Invoice Due Date',
       IF(aaa.group_data = '2',COUNT(aaa.docno),'0')AS 'Last Subscriptions Invoice Count',
       CAST(IF(aaa.`group_data` = '2',MIN(aaa.docdate),'') AS DATE) AS 'Last Invoice Date',
       ROUND(IF(aaa.`group_data` = '2',SUM(aaa.amount_due),''),2) AS 'Last Invoice Amt',
       DATE_ADD(CAST(IF(aaa.`group_data` = '2',MIN(aaa.docdate),'') AS DATE), INTERVAL '$interval_inv_date' DAY) AS 'Last Due Date'
      FROM
      (
      SELECT
      *,
      SUM(aa.after_knock_amount) AS amount_due,
      IF(aa.`AccNo` = '500-012',IF(aa.`DocDate` >= '$month_end','2','1'),IF(aa.`DocDate` != '$month_end','1','2')) AS group_data
      FROM
      (
      SELECT 
      DISTINCT
       c.`supplier_guid`,
       c.`supplier_name`,
       c.`reg_no`,
       a.`DebtorCode`,
       a.`DocKey`,
       b.`ProjNo`,
       b.`AccNo`,
       IFNULL(
         IF(
           b.`ProjNo` IS NULL,
           f.`customer_guid`,
           d.`value_guid`
         ),
         g.customer_guid
       ) AS proj_guid,
       (
         b.`NetAmount` - b.`KnockOffAmount`
       ) AS after_knock_amount,
       a.`DocNo`,
       a.`DisplayTerm`,
       a.`DocDate`,
       a.`LastModified`,
       IFNULL(j.amount,'') AS child_cn_amount
      FROM
        b2b_account.`arinvoice` a 
        INNER JOIN b2b_account.`arinvoicedetail` b 
          ON a.`DocKey` = b.`DocKey` 
        INNER JOIN lite_b2b.`set_supplier` c 
          ON a.`DebtorCode` = c.acc_code 
        LEFT JOIN b2b_invoice.`account_setting` d 
          ON b.`ProjNo` = d.`value` 
          AND d.`module` = 'projno' 
        LEFT JOIN b2b_invoice.`supplier_monthly_main` e 
          ON a.`DocNo` = e.`invoice_number` 
        LEFT JOIN b2b_invoice.`supplier_monthly_child` f 
          ON e.`inv_guid` = f.`inv_guid` 
          AND (
            b.`NetAmount` = f.`amount` 
            OR b.`NetAmount` = f.`total_include_tax`
          ) 
        LEFT JOIN lite_b2b.reminder_link g 
          ON a.`DocNo` = g.invoice_number 
        LEFT JOIN b2b_account.`arcn` h
          ON a.debtorcode = h.debtorcode
        LEFT JOIN b2b_account.`arcndetail` i
          ON h.dockey = i.dockey
          AND a.outstanding = i.netamount
        LEFT JOIN b2b_account.`arcnknockoff` j
          ON h.dockey = j.C_DocKey
          AND a.DocNo = j.I_DocNo
      WHERE a.outstanding != '0.00' 
        AND a.dockey IN ($dockey)
      GROUP BY b.`DtlKey`,h.dockey
      )aa
      WHERE aa.child_cn_amount = ''
      GROUP BY aa.docno,proj_guid,aa.accno
      )aaa
      GROUP BY aaa.docno,aaa.proj_guid,aaa.accno
      )aaaa
      GROUP BY aaaa.proj_guid
      )aaaaa
      GROUP BY aaaaa.proj_guid
      )aaaaaa
      GROUP BY aaaaaa.proj_guid
    ");
    return $query; 
  }

  //using
  public function select_reminder_retailer($month_end,$sec_month_end,$interval_inv_date, $interval_over_date, $interval_reg_date,$count_block,$count_warning,$count_gentle,$dockey)
  {
    $query = $this->db->query("
      SELECT
      *,
      IF(Overdue_Invoices_Count = '$count_gentle' AND Last_Subscriptions_Invoice_Count > '$count_gentle','3',
      IF(Overdue_Invoice_Date_From = '$sec_month_end',IF(Overdue_Subscriptions_Invoice_Amt != '0.00','1',IF(CURDATE() > DATE_ADD(Registration_Invoice_Date, INTERVAL 7 DAY),'1',IF(CURDATE() > DATE_ADD(One_Off_Invoice_Date, INTERVAL 7 DAY) ,'1','2'))),
      IF(CURDATE() > DATE_ADD(Registration_Invoice_Date, INTERVAL 7 DAY) ,'1',
      IF(CURDATE() > DATE_ADD(One_Off_Invoice_Date, INTERVAL 7 DAY) ,'1',
      IF(CURDATE() < DATE_ADD(Overdue_Invoice_Date_To, INTERVAL 9 DAY),IF(Overdue_Invoices_Count = '1',IF(Last_Invoice_Amt = '0.00','2',IF(CURDATE() < DATE_ADD(One_Off_Invoice_Date, INTERVAL 7 DAY) ,'2', IF(CURDATE() < DATE_ADD(Registration_Invoice_Date, INTERVAL 7 DAY),'2' ,'1') ) ),'1'),
      IF(DATE_ADD(Overdue_Invoice_Date_To, INTERVAL $interval_reg_date DAY) > CURDATE(),'2',
      IF(DATE_ADD(Overdue_Invoice_Due_Date, INTERVAL $interval_reg_date DAY) > CURDATE(),IF(Overdue_Subscriptions_Invoice_Amt = '0.00',IF(Last_Invoice_Amt = '0.00',IF(Overdue_Registration_Fees != '0.00','1','2' ),'2'),
      IF(Overdue_Registration_Fees != '0.00','1','2')),
      IF(Overdue_Invoice_Due_Date < CURDATE(),IF(Overdue_Invoices_Count >= '$count_block',IF(EDI_Service_Invoice_Amt = '0.00',IF(Overdue_Invoices_Count >= '$count_block','1','2'), IF(CURDATE() < DATE_ADD(EDI_Invoice_Date, INTERVAL 1 MONTH),'2','1')),IF(Overdue_Invoices_Count = '$count_warning',IF(Overdue_Registration_Fees != '0.00','1','3'),'3')),
      IF(EDI_Service_Invoice_Amt = '0.00', IF(Overdue_Invoices_Count >= '$count_block', '1', '2' ), IF( CURDATE() < DATE_ADD( EDI_Invoice_Date, INTERVAL 1 MONTH ), '2', '1' )))
      )
      )
      )
      )
      ) 
      )
      ) AS 'Variance',
      NOW() AS created_at,
      'BOT' AS created_by
      FROM
      (
      SELECT
      dockey,
      DebtorCode,
      proj_guid,
      supplier_guid,
      supplier_name,
      `reg_no`,
      Registration_Invoice_Date,
      One_Off_Invoice_Date,
      EDI_Invoice_Date,
      Registration_AddON_Invoice_Amt,
      Subscription_OneOFF_Invoice_Amt,
      Training_Invoice_Amt,
      Ad_Hoc_Service_Invoice_Amt,
      EDI_Service_Invoice_Amt,
      Overdue_Registration_Fees,
      Overdue_Subscriptions_Invoice_Amt,
      Total_Overdue,
      Overdue_Invoices_Count,
      Overdue_Invoice_Date_From,
      Overdue_Invoice_Date_To,
      Overdue_Invoice_Due_Date,
      Last_Subscriptions_Invoice_Count,
      Last_Invoice_Date,
      Last_Due_Date,
      Last_Invoice_Amt,
      invoice_number
      FROM
      (
      SELECT
      GROUP_CONCAT(DISTINCT dockey) AS dockey,
      DebtorCode,
      proj_guid,
      supplier_guid,
      supplier_name,
      `reg_no`,
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Registration Invoice Date` ORDER BY `Registration Invoice Date` ASC ),',','1'),'') AS 'Registration_Invoice_Date',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`One OFF Invoice Date` ORDER BY `One OFF Invoice Date` ASC ),',','1'),'') AS 'One_Off_Invoice_Date',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`EDI Invoice Date` ORDER BY `EDI Invoice Date` ASC ),',','1'),'') AS 'EDI_Invoice_Date',
      SUM(`Registration & Add ON Invoice Amt`) AS 'Registration_AddON_Invoice_Amt',
      SUM(`Subscription One OFF Invoice Amt`) AS 'Subscription_OneOFF_Invoice_Amt',
      SUM(`Training Invoice Amt`) AS 'Training_Invoice_Amt',
      SUM(`Ad Hoc Service Invoice Amt`) AS 'Ad_Hoc_Service_Invoice_Amt',
      SUM(`EDI Service Invoice Amt`) AS 'EDI_Service_Invoice_Amt',
      SUM(`Overdue Registraion Fees`) AS 'Overdue_Registration_Fees',
      SUM(`Overdue Subscriptions Invoice Amt`) AS 'Overdue_Subscriptions_Invoice_Amt',
      SUM(`Total Overdue`) AS 'Total_Overdue',
      (COUNT(DISTINCT docno) - SUM(`Last Subscriptions Invoice Count`)) AS 'Overdue_Invoices_Count',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date From` ORDER BY `Overdue Invoice Date From` ASC ),',','1'),'') AS 'Overdue_Invoice_Date_From',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date To` ORDER BY `Overdue Invoice Date To` DESC ),',','1'),'') AS 'Overdue_Invoice_Date_To',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Due Date` ORDER BY `Overdue Invoice Due Date` DESC ),',','1'),'') AS 'Overdue_Invoice_Due_Date',
      SUM(`Last Subscriptions Invoice Count`) AS 'Last_Subscriptions_Invoice_Count',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Invoice Date` ORDER BY `Last Invoice Date` ASC ),',','1'),'') AS 'Last_Invoice_Date',
      IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Due Date` ORDER BY `Last Due Date` DESC ),',','1'),'') AS 'Last_Due_Date',
      SUM(`Last Invoice Amt`) AS 'Last_Invoice_Amt',
      GROUP_CONCAT(DISTINCT docno ORDER BY `DocNo` ASC) AS invoice_number
      FROM
      (
      SELECT
      *,
       CAST(IF(aaa.AccNo = '500-009' ,aaa.docdate,'') AS DATE) AS 'Registration Invoice Date',
       CAST(IF(aaa.AccNo = '500-010' AND aaa.DisplayTerm = 'CASH' ,aaa.docdate,'') AS DATE) AS 'One OFF Invoice Date',
       CAST(IF(aaa.AccNo = '500-013' ,aaa.docdate,'') AS DATE) AS 'EDI Invoice Date',
       ROUND(IF(aaa.AccNo = '500-009' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Registration & Add ON Invoice Amt',
       ROUND(IF(aaa.AccNo = '500-010' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Subscription One OFF Invoice Amt',
       ROUND(IF(aaa.AccNo = '500-011' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Training Invoice Amt',
       ROUND(IF(aaa.AccNo = '500-012' ,SUM(IF(aaa.`group_data` IN ('1','2'),aaa.amount_due,'')),'0.00'),2) AS 'Ad Hoc Service Invoice Amt',
       ROUND(IF(aaa.AccNo = '500-013' ,SUM(IF(aaa.`group_data` IN ('3'),aaa.amount_due,'')),'0.00'),2) AS 'EDI Service Invoice Amt',
       ROUND(IF(aaa.DisplayTerm = 'CASH' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Overdue Registraion Fees',
       ROUND(IF(aaa.DisplayTerm = '30' ,SUM(IF(aaa.`group_data` = '1',aaa.amount_due,'')),'0.00'),2) AS 'Overdue Subscriptions Invoice Amt',
       ROUND(IF(aaa.`group_data` IN ('1','3'),SUM(aaa.amount_due),''),2) AS 'Total Overdue',
       CAST(IF(aaa.`group_data` = '1',MIN(aaa.docdate),'') AS DATE) AS 'Overdue Invoice Date From',
       CAST(IF(aaa.group_data = '1',MAX(aaa.docdate),'') AS DATE) AS 'Overdue Invoice Date To',
       DATE_ADD(CAST(IF(aaa.`group_data` = '1',MAX(aaa.docdate),'') AS DATE), INTERVAL '$interval_over_date' DAY) AS 'Overdue Invoice Due Date',
       IF(aaa.group_data = '2',COUNT(aaa.docno),'0')AS 'Last Subscriptions Invoice Count',
       CAST(IF(aaa.`group_data` = '2',MIN(aaa.docdate),'') AS DATE) AS 'Last Invoice Date',
       ROUND(IF(aaa.`group_data` = '2',SUM(aaa.amount_due),''),2) AS 'Last Invoice Amt',
       DATE_ADD(CAST(IF(aaa.`group_data` = '2',MIN(aaa.docdate),'') AS DATE), INTERVAL '$interval_inv_date' DAY) AS 'Last Due Date'
      FROM
      (
      SELECT
      *,
      SUM(aa.after_knock_amount) AS amount_due,
      IF(aa.`AccNo` = '500-012',IF(aa.`DocDate` >= '$month_end','2','1'),IF(aa.`DocDate` != '$month_end',IF(aa.`AccNo` = '500-013','3',IF(aa.`accno` = '500-010' AND aa.DisplayTerm = '30' AND aa.docdate >= '$month_end','2','1')),IF(aa.`accno` = '500-010'  AND aa.DisplayTerm = '30' AND aa.docdate >= '$month_end','2','1'))) AS group_data
      FROM
      (
      SELECT 
      DISTINCT
       c.`supplier_guid`,
       c.`supplier_name`,
       c.`reg_no`,
       a.`DebtorCode`,
       a.`DocKey`,
       b.`ProjNo`,
       b.`AccNo`,
       IFNULL(
         IF(
           b.`ProjNo` IS NULL,
           f.`customer_guid`,
           d.`value_guid`
         ),
         g.customer_guid
       ) AS proj_guid,
       (
         b.`NetAmount` - b.`KnockOffAmount`
       ) AS after_knock_amount,
       a.`DocNo`,
       a.`DisplayTerm`,
       a.`DocDate`,
       a.`LastModified`,
       IFNULL(j.amount,'') AS child_cn_amount,
       k.KnockOffKey
      FROM
        b2b_account.`arinvoice` a 
        INNER JOIN b2b_account.`arinvoicedetail` b 
          ON a.`DocKey` = b.`DocKey` 
        INNER JOIN lite_b2b.`set_supplier` c 
          ON a.`DebtorCode` = c.acc_code 
        LEFT JOIN b2b_invoice.`account_setting` d 
          ON b.`ProjNo` = d.`value` 
          AND d.`module` = 'projno' 
        LEFT JOIN b2b_invoice.`supplier_monthly_main` e 
          ON a.`DocNo` = e.`invoice_number` 
        LEFT JOIN b2b_invoice.`supplier_monthly_child` f 
          ON e.`inv_guid` = f.`inv_guid` 
          AND (
            b.`NetAmount` = f.`amount` 
            OR b.`NetAmount` = f.`total_include_tax`
          ) 
        LEFT JOIN lite_b2b.reminder_link g 
          ON a.`DocNo` = g.invoice_number 
        LEFT JOIN b2b_account.`arcn` h
          ON a.debtorcode = h.debtorcode
          AND a.`PaymentAmt` = h.`Total`
        LEFT JOIN b2b_account.`arcndetail` i
          ON h.dockey = i.dockey
          AND a.outstanding = i.netamount
        LEFT JOIN b2b_account.`arcnknockoff` j
          ON h.dockey = j.C_DocKey
          AND a.DocNo = j.I_DocNo
        LEFT JOIN b2b_account.`arcnknockoff` k
          ON a.`DocNo` = k.I_DocNo
          AND a.outstanding = k.amount
      WHERE a.outstanding != '0.00' 
        AND a.dockey IN ($dockey)
      GROUP BY b.`DtlKey`,h.dockey
      )aa
      WHERE aa.after_knock_amount != aa.child_cn_amount
      AND aa.KnockOffKey IS NULL
      GROUP BY aa.docno,proj_guid,aa.accno
      )aaa
      WHERE aaa.amount_due != '0.00'
      GROUP BY aaa.docno,aaa.proj_guid,aaa.accno
      )aaaa
      GROUP BY aaaa.proj_guid
      )aaaaa
      GROUP BY aaaaa.proj_guid
      )aaaaaa
      GROUP BY aaaaaa.proj_guid
    ");

    return $query->result();
  }

  public function testing_insert_reminder_supplier($month_end,$sec_month_end,$interval_inv_date, $interval_over_date, $interval_reg_date,$count_block,$count_warning,$count_gentle)
  {
    $query = $this->db->query("
        INSERT INTO `lite_b2b`.`query_outstanding_new_testing` (
            `dockey`,
            `Code`,
            `DebtorCode`,
            `supplier_guid`,
            `supplier_name`,
            `reg_no`,
            `LastModified`,
            `Registration_Invoice_Date`,
            `Overdue_Registration_Fees`,
            `Overdue_Subscriptions_Invoice_Amt`,
            `Total_Overdue`,
            `Overdue_Invoices_Count`,
            `Overdue_Invoice_Date_From`,
            `Overdue_Invoice_Date_To`,
            `Overdue_Invoice_Due_Date`,
            `Last_Subscriptions_Invoice_Count`,
            `Last_Invoice_Date`,
            `Last_Due_Date`,
            `Last_Invoice_Amt`,
            `Variance`,
            `created_at`,
            `created_by`
          ) 
        SELECT
        *,
        IF(Overdue_Invoices_Count = '$count_gentle' AND Last_Subscriptions_Invoice_Count > '$count_gentle','3',
        IF(Overdue_Invoice_Date_From = '$sec_month_end','2',
        IF(DATE_ADD(Overdue_Invoice_Date_To, INTERVAL $interval_reg_date DAY) > CURDATE(),'2',
        IF(DATE_ADD(Overdue_Invoice_Due_Date, INTERVAL $interval_reg_date DAY) > CURDATE(),IF(Overdue_Subscriptions_Invoice_Amt = '0.00',IF(Last_Invoice_Amt = '0.00',IF(Overdue_Registration_Fees != '0.00','1','2' ),'2'),'2'),
        IF(Overdue_Invoice_Due_Date < CURDATE(),IF(Overdue_Invoices_Count >= '$count_block','1',IF(Overdue_Invoices_Count = '$count_warning','2','99')),
        '1')
        )
        )
        ) 
        )AS 'Variance',
        NOW() AS created_at,
        'BOT' AS created_by
        FROM
        (
        SELECT
        dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
        Registration_Invoice_Date,
        Overdue_Registration_Fees,
        Overdue_Subscriptions_Invoice_Amt,
        Total_Overdue,
        Overdue_Invoices_Count,
        Overdue_Invoice_Date_From,
        Overdue_Invoice_Date_To,
        Overdue_Invoice_Due_Date,
        Last_Subscriptions_Invoice_Count,
        Last_Invoice_Date,
        Last_Due_Date,
        Last_Invoice_Amt
        FROM
        (
        SELECT
        GROUP_CONCAT(dockey) AS dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Registration Invoice Date` ORDER BY `Registration Invoice Date` ASC ),',','1'),'') AS 'Registration_Invoice_Date',
        SUM(`Overdue Registraion Fees`) AS 'Overdue_Registration_Fees',
        SUM(`Overdue Subscriptions Invoice Amt`) AS 'Overdue_Subscriptions_Invoice_Amt',
        SUM(`Total Overdue`) AS 'Total_Overdue',
        SUM(`Overdue Invoices Count`) AS 'Overdue_Invoices_Count',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date From` ORDER BY `Overdue Invoice Date From` ASC ),',','1'),'') AS 'Overdue_Invoice_Date_From',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Date To` ORDER BY `Overdue Invoice Date To` DESC ),',','1'),'') AS 'Overdue_Invoice_Date_To',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Overdue Invoice Due Date` ORDER BY `Overdue Invoice Due Date` DESC ),',','1'),'') AS 'Overdue_Invoice_Due_Date',
        SUM(`Last Subscriptions Invoice Count`) AS 'Last_Subscriptions_Invoice_Count',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Invoice Date` ORDER BY `Last Invoice Date` ASC ),',','1'),'') AS 'Last_Invoice_Date',
        IFNULL(SUBSTRING_INDEX(GROUP_CONCAT(`Last Due Date` ORDER BY `Last Due Date` DESC ),',','1'),'') AS 'Last_Due_Date',
        SUM(`Last Invoice Amt`) AS 'Last_Invoice_Amt'
        FROM
        (
        SELECT
         GROUP_CONCAT(dockey) AS dockey,
        `Code`,
        DebtorCode,
        supplier_guid,
        supplier_name,
        reg_no,
        `LastModified`,
         CAST(IF(aa.DisplayTerm = 'CASH' ,aa.docdate,'') AS DATE) AS 'Registration Invoice Date',
         ROUND(IF(aa.DisplayTerm = 'CASH' ,SUM(IF(aa.`group_data` = '1',aa.outstanding,'')),'0.00'),2) AS 'Overdue Registraion Fees',
         ROUND(IF(aa.DisplayTerm = '30' ,SUM(IF(aa.`group_data` = '1',aa.outstanding,'')),'0.00'),2) AS 'Overdue Subscriptions Invoice Amt',
         ROUND(IF(aa.`group_data` = '1',SUM(aa.outstanding),''),2) AS 'Total Overdue',
         IF(aa.`group_data` = '1',COUNT(aa.docno),'0') AS 'Overdue Invoices Count',
         CAST(IF(aa.`group_data` = '1',MIN(aa.docdate),'') AS DATE) AS 'Overdue Invoice Date From',
         CAST(IF(group_data = '1',MAX(aa.docdate),'') AS DATE) AS 'Overdue Invoice Date To',
         DATE_ADD(CAST(IF(aa.`group_data` = '1',MAX(aa.docdate),'') AS DATE), INTERVAL '$interval_over_date' DAY) AS 'Overdue Invoice Due Date',
         IF(group_data = '2',COUNT(aa.docno),'0')AS 'Last Subscriptions Invoice Count',
         CAST(IF(aa.`group_data` = '2',MIN(aa.docdate),'') AS DATE) AS 'Last Invoice Date',
         ROUND(IF(aa.`group_data` = '2',SUM(aa.outstanding),''),2) AS 'Last Invoice Amt',
         DATE_ADD(CAST(IF(aa.`group_data` = '2',MIN(aa.docdate),'') AS DATE), INTERVAL '$interval_inv_date' DAY) AS 'Last Due Date',
         '1' AS final_group_data
        FROM
        (
        SELECT 
          c.`backend_supplier_code` AS `Code`,
          b.`supplier_name`,
          b.supplier_guid,
          b.`reg_no`,
          a.`DebtorCode`,
          a.`DocDate`,
          a.`Outstanding`,
          a.`DisplayTerm`,
          a.dockey,
          a.`DocNo`,
          a.`LastModified`,
          IF(a.`DocDate` != '$month_end','1','2') AS group_data,
          IFNULL(e.amount,'') AS cn_amount
        FROM
          b2b_account.`arinvoice` a 
          INNER JOIN lite_b2b.`set_supplier` b
          ON a.`DebtorCode` = b.acc_code
          LEFT JOIN lite_b2b.`set_supplier_group` c
          ON b.`supplier_guid` = c.`supplier_guid`
          LEFT JOIN b2b_account.`arcn` d
          ON a.debtorcode = d.debtorcode
          AND a.outstanding = d.knockoffamt
          LEFT JOIN b2b_account.`arcnknockoff` e
          ON d.dockey = e.C_DocKey
          AND a.DocNo = e.I_DocNo
        WHERE a.outstanding != '0.00' 
        GROUP BY a.DocNo,d.dockey
        )aa
        WHERE aa.outstanding != aa.cn_amount
        GROUP BY aa.debtorcode,aa.group_data,aa.displayterm
        ORDER BY aa.supplier_name ASC , aa.`DocNo` DESC
        )aaa
        GROUP BY aaa.final_group_data,aaa.supplier_name
        )aaaa
        )aaaaa
      ");
        return $query; 
  }
}
?> 
