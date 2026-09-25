<?php

declare(strict_types=1);

namespace Credova\Library\Constants;

final class CredovaFields
{
    public const ORDER_PUBLIC_ID = 'credovaPublicId';
    public const ORDER_APPLICATION_ID = 'credovaApplicationId';
    public const ORDER_STATUS = 'credovaStatus';
    public const ORDER_PHONE = 'credovaPhone';
    public const ORDER_APPROVAL_AMOUNT = 'credovaApprovalAmount';
    public const ORDER_BORROWED_AMOUNT = 'credovaBorrowedAmount';
    public const ORDER_TOTAL_IN_STORE_PAYMENT = 'credovaTotalInStorePayment';
    public const ORDER_INVOICE_AMOUNT = 'credovaInvoiceAmount';
    public const ORDER_LENDER_CODE = 'credovaLenderCode';
    public const ORDER_LENDER_NAME = 'credovaLenderName';
    public const ORDER_LENDER_DISPLAY_NAME = 'credovaLenderDisplayName';
    public const ORDER_FINANCING_PARTNER_CODE = 'credovaFinancingPartnerCode';
    public const ORDER_FINANCING_PARTNER_NAME = 'credovaFinancingPartnerName';
    public const ORDER_FINANCING_PARTNER_DISPLAY_NAME = 'credovaFinancingPartnerDisplayName';
    public const ORDER_OFFER_ID = 'credovaOfferId';
    public const ORDER_AGENT_NAME = 'credovaAgentName';
    public const ORDER_EMAIL = 'credovaEmail';

    public const TRANSACTION_WEBHOOK_EVENT_ID = 'credovaWebhookEventId';

    public const STATUS_APPROVED = 'Approved';
    public const STATUS_SIGNED = 'Signed';
    public const STATUS_FUNDED = 'Funded';
    public const STATUS_DECLINED = 'Declined';
    public const STATUS_RETURNED = 'Returned';

    public const PRODUCT_ID_SHIPPING = 'shipping';
    public const PRODUCT_DESCRIPTION_SHIPPING = 'Shipping';
    public const PRODUCT_ID_TAX = 'sales_tax';
    public const PRODUCT_DESCRIPTION_TAX = 'Sales Tax';

    public const PAYLOAD_PRODUCT_NUMBER = 'productNumber';
}
