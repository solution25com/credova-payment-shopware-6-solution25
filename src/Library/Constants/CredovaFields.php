<?php

namespace Credova\Library\Constants;

class CredovaFields
{
    public const ORDER_CF_PUBLIC_ID = 'credovaPublicId';
    public const ORDER_CF_APPLICATION_ID = 'credovaApplicationId';
    public const ORDER_CF_PHONE = 'credovaPhone';
    public const ORDER_CF_STATUS = 'credovaStatus';
    public const ORDER_CF_APPROVAL_AMOUNT = 'credovaApprovalAmount';
    public const ORDER_CF_BORROWED_AMOUNT = 'credovaBorrowedAmount';
    public const ORDER_CF_TOTAL_IN_STORE_PAYMENT = 'credovaTotalInStorePayment';
    public const ORDER_CF_INVOICE_AMOUNT = 'credovaInvoiceAmount';
    public const ORDER_CF_LENDER_CODE = 'credovaLenderCode';
    public const ORDER_CF_LENDER_NAME = 'credovaLenderName';
    public const ORDER_CF_LENDER_DISPLAY_NAME = 'credovaLenderDisplayName';
    public const ORDER_CF_FP_CODE = 'credovaFinancingPartnerCode';
    public const ORDER_CF_FP_NAME = 'credovaFinancingPartnerName';
    public const ORDER_CF_FP_DISPLAY_NAME = 'credovaFinancingPartnerDisplayName';
    public const ORDER_CF_OFFER_ID = 'credovaOfferId';

    public const CUSTOMER_CF_APPLICATION_ID = 'credovaApplicationId';
    public const CUSTOMER_CF_PUBLIC_ID = 'credovaPublicId';
    public const CUSTOMER_CF_PHONE = 'credovaPhone';
    public const CUSTOMER_CF_APPROVAL_AMOUNT = 'credovaApprovalAmount';
    public const CUSTOMER_CF_BORROWED_AMOUNT = 'credovaBorrowedAmount';

    public const STATUS_APPROVED = 'Approved';
    public const STATUS_SIGNED = 'Signed';
    public const STATUS_FUNDED = 'Funded';
    public const STATUS_DECLINED = 'Declined';
    public const STATUS_RETURNED = 'Returned';
}
