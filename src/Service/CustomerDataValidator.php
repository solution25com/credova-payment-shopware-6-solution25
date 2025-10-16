<?php

namespace Credova\Service;

use DateTime;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CustomerDataValidator
{
    public function __construct(private readonly EntityRepository $customerRepository, private readonly ConfigService $configs)
    {
    }

  /**
   * Validates customer data and returns array of validation errors (field => message).
   */
    public function validate(string $customerId, Context $context): array
    {
        $errors = [];

        if (!$customerId) {
            return ['customerId' => 'Customer ID is required.'];
        }

        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('defaultBillingAddress.countryState');
        $criteria->addAssociation('activeBillingAddress');
        $criteria->addAssociation('activeBillingAddress.country');
        $criteria->addAssociation('activeBillingAddress.countryState');
        $criteria->addAssociation('defaultShippingAddress');
        $criteria->addAssociation('activeShippingAddress');
        $criteria->addAssociation('addresses');

        $customer = $this->customerRepository->search($criteria, $context)->first();

        if (!$customer instanceof CustomerEntity) {
            return ['customer' => 'Customer not found.'];
        }

        $billingAddress = $customer->getActiveBillingAddress();

        if (!$billingAddress) {
            return ['billingAddress' => 'Billing address is required.'];
        }

        $data = [
        'firstName' => trim($billingAddress->getFirstName()),
        'lastName' => trim($billingAddress->getLastName()),
        'email' => trim($customer->getEmail()),
        'phoneNumber' => trim((string) $billingAddress->getPhoneNumber()),
        'street' => trim($billingAddress->getStreet()),
        'city' => trim($billingAddress->getCity()),
        'zip' => trim((string) $billingAddress->getZipcode()),
        'stateFull' => $billingAddress->getCountryState()?->getShortCode(),
        ];

        $data['stateShort'] = null;
        if ($data['stateFull']) {
            $parts = explode('-', $data['stateFull']);
            $data['stateShort'] = strtoupper(end($parts));
        }

        $requiredFields = [
        'firstName' => 'First name is required.',
        'lastName' => 'Last name is required.',
        'email' => 'Email is required.',
        'phoneNumber' => 'Phone number is required.',
        'street' => 'Street is required.',
        'city' => 'City is required.',
        'zip' => 'Zip code is required.',
        'stateShort' => 'State code is required.',
        ];

        foreach ($requiredFields as $field => $errorMessage) {
            if (empty($data[$field])) {
                $errors[$field] = $errorMessage;
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format.';
        }

        if (!empty($data['zip']) && !preg_match('/^\d{4,10}$/', $data['zip'])) {
            $errors['zip'] = 'Invalid zip code.';
        }

        if (!empty($data['stateShort']) && !preg_match('/^[A-Z]{2}$/', $data['stateShort'])) {
            $errors['stateShort'] = 'Invalid state code. Must be 2-letter format (e.g., NY, CA).';
        }

        if (!empty($data['phoneNumber'])) {
            try {
                $this->formatPhoneNumber($data['phoneNumber']);
            } catch (\Exception $e) {
                $errors['phoneNumber'] = $e->getMessage();
            }
        }

        $birthday = $customer->getBirthday();
        $birthdayString = $birthday instanceof \DateTimeInterface ? $birthday->format('Y-m-d') : null;

        if (!$this->isValidDOB($birthdayString)) {
            $errors['birthday'] = 'Customer must be at least 18 years old.';
        }

        return $errors;
    }

    private function isValidDOB(?string $dob): bool
    {
        if (!$dob) {
            return false;
        }

        try {
            $dobDate = new DateTime($dob);
            $now = new DateTime();
            $age = $now->diff($dobDate)->y;

            return $dobDate < $now && $age >= 18;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function formatPhoneNumber(string $phone): void
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) !== 10) {
            throw new \InvalidArgumentException('Phone number must match US format (123-456-7890) or international format (1234567890).');
        }
    }

    public function validateCredovaPayment(float $cartAmount, string $salesChannelId): bool
    {
        $minFinanceAmount = $this->configs->getConfig('minFinanceAmount', $salesChannelId);
        $maxFinanceAmount = $this->configs->getConfig('maxFinanceAmount', $salesChannelId);

        return $cartAmount >= $minFinanceAmount && $cartAmount <= $maxFinanceAmount;
    }
}
