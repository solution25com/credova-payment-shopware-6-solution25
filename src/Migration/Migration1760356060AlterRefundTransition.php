<?php

declare(strict_types=1);

namespace Credova\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1760356060AlterRefundTransition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760356060;
    }

    public function update(Connection $connection): void
    {
        $this->addRefundTransition($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addRefundTransition(Connection $connection): void
    {
        $stateMachineId = $connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :technicalName',
            ['technicalName' => 'order_transaction.state']
        );

        if (!$stateMachineId) {
            throw new \RuntimeException('State machine "order_transaction.state" not found.');
        }

        $signedStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state 
             WHERE technical_name = :technicalName 
               AND state_machine_id = :stateMachineId',
            [
            'technicalName' => 'credova_signed',
            'stateMachineId' => $stateMachineId,
            ]
        );

        $refundedStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state 
             WHERE technical_name = :technicalName 
               AND state_machine_id = :stateMachineId',
            [
            'technicalName' => 'refunded',
            'stateMachineId' => $stateMachineId,
            ]
        );

        if (!$signedStateId || !$refundedStateId) {
            throw new \RuntimeException('Required states not found for refund transition.');
        }

        $existing = $connection->fetchOne(
            'SELECT id FROM state_machine_transition 
             WHERE state_machine_id = :stateMachineId 
               AND action_name = :actionName 
               AND from_state_id = :fromStateId 
               AND to_state_id = :toStateId',
            [
            'stateMachineId' => $stateMachineId,
            'actionName' => 'refund',
            'fromStateId' => $signedStateId,
            'toStateId' => $refundedStateId,
            ]
        );

        if (!$existing) {
            $connection->executeStatement(
                'INSERT INTO state_machine_transition 
                (id, state_machine_id, action_name, from_state_id, to_state_id, created_at)
                 VALUES (:id, :stateMachineId, :actionName, :fromStateId, :toStateId, NOW())',
                [
                'id' => random_bytes(16),
                'stateMachineId' => $stateMachineId,
                'actionName' => 'refund',
                'fromStateId' => $signedStateId,
                'toStateId' => $refundedStateId,
                ],
                [
                'id' => \Doctrine\DBAL\ParameterType::BINARY,
                'stateMachineId' => \Doctrine\DBAL\ParameterType::BINARY,
                'fromStateId' => \Doctrine\DBAL\ParameterType::BINARY,
                'toStateId' => \Doctrine\DBAL\ParameterType::BINARY,
                ]
            );
        }
    }
}
