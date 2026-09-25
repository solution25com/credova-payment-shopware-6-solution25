<?php

declare(strict_types=1);

namespace Credova\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1768754675AlterApproveToPaidTransition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768754675;
    }

    public function update(Connection $connection): void
    {
        $stateMachineId = $connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :name',
            ['name' => 'order_transaction.state']
        );

        if (!$stateMachineId) {
            throw new \RuntimeException('order_transaction.state not found');
        }

        $approvedStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state
             WHERE technical_name = :name AND state_machine_id = :sm',
            [
            'name' => 'credova_approved',
            'sm' => $stateMachineId,
            ]
        );

        $paidStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state
             WHERE technical_name = :name AND state_machine_id = :sm',
            [
            'name' => 'paid',
            'sm' => $stateMachineId,
            ]
        );

        if (!$approvedStateId || !$paidStateId) {
            throw new \RuntimeException('Required states not found');
        }

        $exists = $connection->fetchOne(
            'SELECT id FROM state_machine_transition
             WHERE state_machine_id = :sm
               AND action_name = :action
               AND from_state_id = :from
               AND to_state_id = :to',
            [
            'sm' => $stateMachineId,
            'action' => 'paid',
            'from' => $approvedStateId,
            'to' => $paidStateId,
            ]
        );

        if (!$exists) {
            $connection->insert('state_machine_transition', [
            'id' => random_bytes(16),
            'state_machine_id' => $stateMachineId,
            'action_name' => 'paid',
            'from_state_id' => $approvedStateId,
            'to_state_id' => $paidStateId,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], [
            'id' => ParameterType::BINARY,
            'state_machine_id' => ParameterType::BINARY,
            'from_state_id' => ParameterType::BINARY,
            'to_state_id' => ParameterType::BINARY,
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
