<?php

declare(strict_types=1);

namespace Credova\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1759928728CreateCustomPaymentStatuses extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759928728;
    }

    public function update(Connection $connection): void
    {
        $this->addCustomPaymentStates($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addCustomPaymentStates(Connection $connection): void
    {
        $stateMachineId = $connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :technicalName',
            ['technicalName' => 'order_transaction.state']
        );

        if (!$stateMachineId) {
            throw new \RuntimeException('State machine "order_transaction.state" not found.');
        }

        $existingStates = $connection->fetchAllAssociative(
            'SELECT technical_name FROM state_machine_state WHERE state_machine_id = :stateMachineId',
            ['stateMachineId' => $stateMachineId]
        );

        $existingStateNames = array_column($existingStates, 'technical_name');

        $newStates = [
        [
        'id' => $this->generateUuid(),
        'technical_name' => 'credova_approved',
        'name' => 'Approved',
        ],
        [
        'id' => $this->generateUuid(),
        'technical_name' => 'credova_signed',
        'name' => 'Signed',
        ],
        ];

        foreach ($newStates as $state) {
            if (in_array($state['technical_name'], $existingStateNames)) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO state_machine_state (id, state_machine_id, technical_name, created_at)
                 VALUES (:id, :stateMachineId, :technicalName, NOW())',
                [
                'id' => $state['id'],
                'stateMachineId' => $stateMachineId,
                'technicalName' => $state['technical_name'],
                ],
                [
                'id' => \Doctrine\DBAL\ParameterType::BINARY,
                'stateMachineId' => \Doctrine\DBAL\ParameterType::BINARY,
                ]
            );

            $this->addStateTranslations($connection, $state['id'], $state['name']);
        }

        $this->addStateTransitions($connection, $stateMachineId);
    }

    private function addStateTransitions(Connection $connection, string $stateMachineId): void
    {
        $existingTransitions = $connection->fetchAllAssociative(
            'SELECT action_name, from_state_id, to_state_id ' .
            'FROM state_machine_transition WHERE state_machine_id = :stateMachineId',
            [
            'stateMachineId' => $stateMachineId,
            ]
        );

        $existingTransitionKeys = array_map(function ($transition) {
            return $transition['action_name'] . '-' . $transition['from_state_id'] . '-' . $transition['to_state_id'];
        }, $existingTransitions);

        $approvedStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state ' .
            'WHERE technical_name = :technicalName AND state_machine_id = :stateMachineId',
            [
            'technicalName' => 'credova_approved',
            'stateMachineId' => $stateMachineId,
            ]
        );

        $signedStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state ' .
            'WHERE technical_name = :technicalName AND state_machine_id = :stateMachineId',
            [
            'technicalName' => 'credova_signed',
            'stateMachineId' => $stateMachineId,
            ]
        );

        $openStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state ' .
            'WHERE technical_name = :technicalName AND state_machine_id = :stateMachineId',
            [
            'technicalName' => 'open',
            'stateMachineId' => $stateMachineId,
            ]
        );

        $failedStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state ' .
            'WHERE technical_name = :technicalName AND state_machine_id = :stateMachineId',
            [
            'technicalName' => 'failed',
            'stateMachineId' => $stateMachineId,
            ]
        );

        $transitions = [
        // open -> credova_approved
        [
        'action_name' => 'credova_approved',
        'from_state_id' => $openStateId,
        'to_state_id' => $approvedStateId,
        ],
        // credova_approved -> credova_signed
        [
        'action_name' => 'credova_signed',
        'from_state_id' => $approvedStateId,
        'to_state_id' => $signedStateId,
        ],
        // credova_approved -> failed
        [
        'action_name' => 'decline',
        'from_state_id' => $approvedStateId,
        'to_state_id' => $failedStateId,
        ],
        // credova_signed -> failed
        [
        'action_name' => 'decline',
        'from_state_id' => $signedStateId,
        'to_state_id' => $failedStateId,
        ],
        ];

        foreach ($transitions as $transition) {
            $transitionKey =
            $transition['action_name'] . '-' . $transition['from_state_id'] . '-' . $transition['to_state_id'];
            if (in_array($transitionKey, $existingTransitionKeys)) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO state_machine_transition ' .
                '(id, state_machine_id, action_name, from_state_id, to_state_id, created_at)
                 VALUES (:id, :stateMachineId, :actionName, :fromStateId, :toStateId, NOW())',
                [
                'id' => $this->generateUuid(),
                'stateMachineId' => $stateMachineId,
                'actionName' => $transition['action_name'],
                'fromStateId' => $transition['from_state_id'],
                'toStateId' => $transition['to_state_id'],
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

    private function addStateTranslations(Connection $connection, string $stateId, string $stateName): void
    {
        $languages = $connection->fetchAllAssociative('SELECT id FROM language');
        foreach ($languages as $language) {
            $connection->executeStatement(
                'INSERT INTO state_machine_state_translation (state_machine_state_id, language_id, name, created_at)
                 VALUES (:stateId, :languageId, :name, NOW())
                 ON DUPLICATE KEY UPDATE name = :name',
                [
                'stateId' => $stateId,
                'languageId' => $language['id'],
                'name' => $stateName,
                ],
                [
                'stateId' => \Doctrine\DBAL\ParameterType::BINARY,
                'languageId' => \Doctrine\DBAL\ParameterType::BINARY,
                ]
            );
        }
    }
    private function generateUuid(): string
    {
        return random_bytes(16);
    }
}
