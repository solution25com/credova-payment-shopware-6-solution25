<?php

declare(strict_types=1);

namespace Credova\Service\OrderTransactionMapper;

use Credova\Gateways\CredovaHandler;
use Credova\Library\Constants\CredovaFields;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderTransactionMapper
{
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $customerRepository
    ) {
    }

    public function getOrderTransactionsById(string $transactionId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.billingAddress');
        $criteria->addAssociation('order.billingAddress.countryState');
        $criteria->addAssociation('order.lineItems');

        $entity = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();
        return $entity instanceof OrderTransactionEntity ? $entity : null;
    }

    public function getOrderTransactionById(string $transactionId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');

        $entity = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();
        return $entity instanceof OrderTransactionEntity ? $entity : null;
    }

    public function setTransactionWebhookEventId(string $transactionId, string $eventId, Context $context): void
    {
        $transaction = $this->orderTransactionRepository->search(new Criteria([$transactionId]), $context)->getEntities()->first();
        if (!$transaction instanceof OrderTransactionEntity) {
            return;
        }
        $customFields = $transaction->getCustomFields() ?? [];
        $customFields[CredovaFields::TRANSACTION_WEBHOOK_EVENT_ID] = $eventId;
        $this->orderTransactionRepository->update([[
            'id' => $transactionId,
            'customFields' => $customFields,
        ]], $context);
    }

    public function setCredovaCustomFieldOnTransaction(string $transactionId, Context $context, array $credovaData): void
    {
        $transaction = $this->orderTransactionRepository->search(new Criteria([$transactionId]), $context)->getEntities()->first();
        if (!$transaction instanceof OrderTransactionEntity) {
            return;
        }

        $this->orderTransactionRepository->update([[
            'id' => $transactionId,
            'customFields' => array_merge(
                $transaction->getCustomFields() ?? [],
                $credovaData
            ),
        ]], $context);
    }

    public function updateCredovaFieldsFromWebhook(string $transactionId, Context $context, array $webhookData): void
    {
        $fieldsToStore = [
            CredovaFields::ORDER_APPLICATION_ID => $webhookData['applicationId'] ?? null,
            CredovaFields::ORDER_PUBLIC_ID => $webhookData['publicId'] ?? null,
            CredovaFields::ORDER_PHONE => $webhookData['phone'] ?? null,
            CredovaFields::ORDER_STATUS => $webhookData['status'] ?? null,
            CredovaFields::ORDER_APPROVAL_AMOUNT => $webhookData['approvalAmount'] ?? null,
            CredovaFields::ORDER_BORROWED_AMOUNT => $webhookData['borrowedAmount'] ?? null,
            CredovaFields::ORDER_TOTAL_IN_STORE_PAYMENT => $webhookData['totalInStorePayment'] ?? null,
            CredovaFields::ORDER_INVOICE_AMOUNT => $webhookData['invoiceAmount'] ?? null,
            CredovaFields::ORDER_LENDER_CODE => $webhookData['lenderCode'] ?? null,
            CredovaFields::ORDER_LENDER_NAME => $webhookData['lenderName'] ?? null,
            CredovaFields::ORDER_LENDER_DISPLAY_NAME => $webhookData['lenderDisplayName'] ?? null,
            CredovaFields::ORDER_FINANCING_PARTNER_CODE => $webhookData['financingPartnerCode'] ?? null,
            CredovaFields::ORDER_FINANCING_PARTNER_NAME => $webhookData['financingPartnerName'] ?? null,
            CredovaFields::ORDER_FINANCING_PARTNER_DISPLAY_NAME => $webhookData['financingPartnerDisplayName'] ?? null,
            CredovaFields::ORDER_OFFER_ID => $webhookData['offerId'] ?? null,
        ];

        $this->setCredovaCustomFieldOnTransaction($transactionId, $context, $fieldsToStore);
    }

    public function updateCredovaOrderFieldsFromWebhook(OrderEntity $order, Context $context, array $webhookData): void
    {
        $fieldsToStore = [
            CredovaFields::ORDER_APPLICATION_ID => $webhookData['applicationId'] ?? null,
            CredovaFields::ORDER_PUBLIC_ID => $webhookData['publicId'] ?? null,
            CredovaFields::ORDER_PHONE => $webhookData['phone'] ?? null,
            CredovaFields::ORDER_STATUS => $webhookData['status'] ?? null,
            CredovaFields::ORDER_APPROVAL_AMOUNT => $webhookData['approvalAmount'] ?? null,
            CredovaFields::ORDER_BORROWED_AMOUNT => $webhookData['borrowedAmount'] ?? null,
            CredovaFields::ORDER_TOTAL_IN_STORE_PAYMENT => $webhookData['totalInStorePayment'] ?? null,
            CredovaFields::ORDER_INVOICE_AMOUNT => $webhookData['invoiceAmount'] ?? null,
            CredovaFields::ORDER_LENDER_CODE => $webhookData['lenderCode'] ?? null,
            CredovaFields::ORDER_LENDER_NAME => $webhookData['lenderName'] ?? null,
            CredovaFields::ORDER_LENDER_DISPLAY_NAME => $webhookData['lenderDisplayName'] ?? null,
            CredovaFields::ORDER_FINANCING_PARTNER_CODE => $webhookData['financingPartnerCode'] ?? null,
            CredovaFields::ORDER_FINANCING_PARTNER_NAME => $webhookData['financingPartnerName'] ?? null,
            CredovaFields::ORDER_FINANCING_PARTNER_DISPLAY_NAME => $webhookData['financingPartnerDisplayName'] ?? null,
            CredovaFields::ORDER_OFFER_ID => $webhookData['offerId'] ?? null,
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
        $orderCustomer = $order->getOrderCustomer();
        if ($orderCustomer === null) {
            return;
        }
        $fieldToStore = [
            CredovaFields::ORDER_APPLICATION_ID => $webhookData['applicationId'] ?? null,
            CredovaFields::ORDER_PUBLIC_ID => $webhookData['publicId'] ?? null,
            CredovaFields::ORDER_PHONE => $webhookData['phone'] ?? null,
            CredovaFields::ORDER_APPROVAL_AMOUNT => $webhookData['approvalAmount'] ?? null,
            CredovaFields::ORDER_BORROWED_AMOUNT => $webhookData['borrowedAmount'] ?? null,
        ];

        $this->customerRepository->update([[
            'id' => $orderCustomer->getCustomerId(),
            'customFields' => array_merge(
                $orderCustomer->getCustomFields() ?? [],
                $fieldToStore
            ),
        ]], $context);
    }

    public function findOrderByCredovaPublicId(string $publicId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . CredovaFields::ORDER_PUBLIC_ID, $publicId));
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();
        return $order instanceof OrderEntity ? $order : null;
    }

    public function findOrderTransactionByCredovaPublicId(string $publicId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . CredovaFields::ORDER_PUBLIC_ID, $publicId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress.countryState');
        $criteria->addAssociation('stateMachineState');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $entity = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();
        return $entity instanceof OrderTransactionEntity ? $entity : null;
    }

    public function findLatestCredovaOrderTransactionByOrderId(string $orderId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('paymentMethod.handlerIdentifier', CredovaHandler::class));
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress.countryState');
        $criteria->addAssociation('stateMachineState');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $entity = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();
        return $entity instanceof OrderTransactionEntity ? $entity : null;
    }
}
