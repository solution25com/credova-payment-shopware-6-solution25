<?php

namespace Credova\Service\OrderTransactionMapper;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderTransactionMapper
{
  private EntityRepository $orderTransactionRepository;
  private EntityRepository $orderRepository;

  public function __construct(EntityRepository $orderTransactionRepository,  EntityRepository $orderRepository)
  {
    $this->orderTransactionRepository = $orderTransactionRepository;
    $this->orderRepository = $orderRepository;
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
      'credovaApplicationId' => $webhookData['applicationId'],
      'credovaPublicId' => $webhookData['publicId'],
      'credovaPhone' => $webhookData['phone'],
      'credovaStatus' => $webhookData['status'],
      'credovaApprovalAmount' => $webhookData['approvalAmount'] ?? null,
      'credovaBorrowedAmount' => $webhookData['borrowedAmount'] ?? null,
      'credovaTotalInStorePayment' => $webhookData['totalInStorePayment'] ?? null,
      'credovaInvoiceAmount' => $webhookData['invoiceAmount'] ?? null,
      'credovaLenderCode' => $webhookData['lenderCode'] ?? null,
      'credovaLenderName' => $webhookData['lenderName'] ?? null,
      'credovaLenderDisplayName' => $webhookData['lenderDisplayName'] ?? null,
      'credovaFinancingPartnerCode' => $webhookData['financingPartnerCode'] ?? null,
      'credovaFinancingPartnerName' => $webhookData['financingPartnerName'] ?? null,
      'credovaFinancingPartnerDisplayName' => $webhookData['financingPartnerDisplayName'] ?? null,
      'credovaOfferId' => $webhookData['offerId'] ?? null,
    ];

    $this->orderRepository->update([[
      'id' => $order->getId(),
      'customFields' => array_merge(
        $order->getCustomFields() ?? [],
        $fieldsToStore
      ),
    ]], $context);
  }


  public function findOrderByCredovaPublicId(string $publicId, Context $context): ?OrderEntity
  {
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('customFields.credovaPublicId', $publicId));
    $criteria->addAssociation('transactions');
    $criteria->addAssociation('order');

    return $this->orderRepository->search($criteria, $context)->first();
  }
}