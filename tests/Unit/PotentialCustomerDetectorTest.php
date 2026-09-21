<?php

use App\Services\Conversations\PotentialCustomerDetector;

it('detects commercial intent', function (string $message) {
    expect(app(PotentialCustomerDetector::class)->isPotentialCustomer($message))->toBeTrue();
})->with([
    'quote request' => 'Could you send me a quote for 500 units?',
    'pricing request' => 'What is your pricing for enterprise customers?',
    'purchase intent' => 'I would like to purchase this product.',
    'order intent' => 'How can I place an order?',
    'availability request' => 'Is this available in Germany?',
    'MOQ request' => 'What is the MOQ?',
    'shipping request' => 'Do you offer international shipping?',
    'delivery request' => 'What is the delivery time?',
    'sample request' => 'Can I request a sample?',
    'wholesale request' => 'Do you offer wholesale pricing?',
    'distributor request' => 'I want to become a distributor.',
]);

it('does not classify non-commercial support questions as lead intent', function () {
    expect(app(PotentialCustomerDetector::class)->isPotentialCustomer('How do I reset my password?'))->toBeFalse();
});
