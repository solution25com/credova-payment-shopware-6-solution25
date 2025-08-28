<?php

namespace Credova\Subscriber;

use Credova\Gateways\CredovaHandler;
use Credova\Service\PaymentClientApi;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefundEventSubscriber implements EventSubscriberInterface
{
  private EntityRepository $orderTransactionRepository;
  private PaymentClientApi $paymentClient;

  public function __construct(EntityRepository $orderTransactionRepository, PaymentClientApi $paymentClient)
  {
    $this->orderTransactionRepository = $orderTransactionRepository;
    $this->paymentClient = $paymentClient;
  }

  public static function getSubscribedEvents()
  {
    return [
      StateMachineTransitionEvent::class => 'onStateMachineTransition',
    ];
  }

  public function onStateMachineTransition(StateMachineTransitionEvent $event): void
  {

    $nextState = $event->getToPlace()->getTechnicalName();
    $entityName = $event->getEntityName();
    $transactionId = $event->getEntityId();

    if ($entityName !== "order_transaction") {
      return;
    }

    $criteria = (new Criteria([$transactionId]))
      ->addAssociation('paymentMethod')
      ->addAssociation('order');

    $orderTransaction = $this->orderTransactionRepository->search($criteria, $event->getContext())->first();
    $handlerIdentifier = $orderTransaction->getPaymentMethod()->getHandlerIdentifier();

    if ($handlerIdentifier !== CredovaHandler::class) {
      return;
    }


    if ($nextState === 'refunded') {
      $order = $orderTransaction->getOrder();
      $customFields = $order->getCustomFields();

      if($customFields['credovaStatus'] == 'Signed') {
        $publicId = $customFields['credovaPublicId'] ?? null;

        if ($publicId) {
          $payload = [
            'agentName' => $customFields['credovaAgentName'] ?? 'Edon Agent',
            'phone' => $customFields['credovaPhone'] ?? null,
            'email' => $customFields['credovaEmail'] ?? null,
            'reason' => $customFields['credovaReason'] ?? 'Client requested a return.',
            'returnType' => $customFields['credovaReturnType'] ?? '2',
          ];

          foreach ($payload as $key => $value) {
            if ($value === null) {
              unset($payload[$key]);
            }
          }

          $this->paymentClient->returnApplication($publicId, $payload);
        }
      }
    }

  }
}