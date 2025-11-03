<?php

namespace Credova\Service\OrderTransactionMapper;

use Credova\Library\Constants\CredovaFields;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderTransactionMapper
{
    public function __construct(private readonly EntityRepository $orderTransactionRepository, private readonly EntityRepository $orderRepository, private readonly EntityRepository $customerRepository)
    {
    }

    public function getOrderTransactionsById(string $transactionId, Context $context)
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.orderCustomer.customer.addresses');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.billingAddress');
        $criteria->addAssociation('order.billingAddress.country');
        $criteria->addAssociation('order.billingAddress.countryState');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');
        $criteria->addAssociation('paymentMethod');

        return $this->orderTransactionRepository->search($criteria, $context)->first();
    }

    public function setCredovaCustomFieldFromOrder(OrderEntity $order, Context $context, array $credovaData): void
    {
        $this->orderRepository->update([[
        'id' => $order->getId(),
        'customFields' => array_merge(
            $order->getCustomFields() ?? [],
            $credovaData
        ),
        ]], $context);
    }

    public function updateCredovaFieldsFromWebhook(OrderEntity $order, Context $context, array $webhookData): void
    {
        $fieldsToStore = [
        CredovaFields::ORDER_CF_APPLICATION_ID => $webhookData['applicationId'],
        CredovaFields::ORDER_CF_PUBLIC_ID => $webhookData['publicId'],
        CredovaFields::ORDER_CF_PHONE => $webhookData['phone'],
        CredovaFields::ORDER_CF_STATUS => $webhookData['status'],
        CredovaFields::ORDER_CF_APPROVAL_AMOUNT => $webhookData['approvalAmount'] ?? null,
        CredovaFields::ORDER_CF_BORROWED_AMOUNT => $webhookData['borrowedAmount'] ?? null,
        CredovaFields::ORDER_CF_TOTAL_IN_STORE_PAYMENT => $webhookData['totalInStorePayment'] ?? null,
        CredovaFields::ORDER_CF_INVOICE_AMOUNT => $webhookData['invoiceAmount'] ?? null,
        CredovaFields::ORDER_CF_LENDER_CODE => $webhookData['lenderCode'] ?? null,
        CredovaFields::ORDER_CF_LENDER_NAME => $webhookData['lenderName'] ?? null,
        CredovaFields::ORDER_CF_LENDER_DISPLAY_NAME => $webhookData['lenderDisplayName'] ?? null,
        CredovaFields::ORDER_CF_FP_CODE => $webhookData['financingPartnerCode'] ?? null,
        CredovaFields::ORDER_CF_FP_NAME => $webhookData['financingPartnerName'] ?? null,
        CredovaFields::ORDER_CF_FP_DISPLAY_NAME => $webhookData['financingPartnerDisplayName'] ?? null,
        CredovaFields::ORDER_CF_OFFER_ID => $webhookData['offerId'] ?? null,
        ];

        $this->orderRepository->update([[
        'id' => $order->getId(),
        'customFields' => array_merge(
            $order->getCustomFields() ?? [],
            $fieldsToStore
        ),
        ]], $context);
    }

    public function updateCredovaCustomer(OrderEntity $order, Context $context, array $webhookData): void
    {
        $fieldToStore = [
        CredovaFields::CUSTOMER_CF_APPLICATION_ID => $webhookData['applicationId'],
        CredovaFields::CUSTOMER_CF_PUBLIC_ID => $webhookData['publicId'],
        CredovaFields::CUSTOMER_CF_PHONE => $webhookData['phone'],
        CredovaFields::CUSTOMER_CF_APPROVAL_AMOUNT => $webhookData['approvalAmount'] ?? null,
        CredovaFields::CUSTOMER_CF_BORROWED_AMOUNT => $webhookData['borrowedAmount'] ?? null,
        ];

        $this->customerRepository->update([[
        'id' => $order->getOrderCustomer()->getCustomerId(),
        'customFields' => array_merge(
            $order->getOrderCustomer()->getCustomFields() ?? [],
            $fieldToStore
        ),
        ]], $context);
    }


    public function findOrderByCredovaPublicId(string $publicId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.credovaPublicId', $publicId));
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('order');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');

        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order instanceof OrderEntity) {
            return $order;
        }
        return null;
    }
}
