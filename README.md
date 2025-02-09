# Pezesha Laravel Package

A Laravel package for seamless integration with the Pezesha API, enabling loan management, payments, and financial services.

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://www.buymeacoffee.com/chebon)

## Installation

Install the package via composer:
```bash
composer require chebon/pezesha-laravel
```

## Configuration

1. Publish the configuration file:

```bash
php artisan vendor:publish --provider="Chebon\PezeshaLaravel\PezeshaServiceProvider"
```

2. Add the following variables to your `.env` file:

```env
PEZESHA_CLIENT_ID=your_client_id
PEZESHA_CLIENT_SECRET=your_client_secret
PEZESHA_BASE_URL=https://api.pezesha.com
PEZESHA_CHANNEL=your_channel
```

## Usage

### Basic Setup

```php
use Chebon\PezeshaLaravel\Pezesha;

$pezesha = new Pezesha();
```

### Authentication

The package handles authentication automatically, but you can manually authenticate:

```php
try {
    $result = $pezesha->authenticate();
    $token = $result['access_token'];
} catch (PezeshaException $e) {
    // Handle authentication error
}
```

### Loan Management

#### 1. Get Loan Offers

```php
try {
    $result = $pezesha->getLoanOffers('MERCHANT_ID');
    $loanLimit = $result['data']['loan_limit'];
    $interestRate = $result['data']['interest_rate'];
} catch (PezeshaException $e) {
    // Handle error
}
```

#### 2. Apply for Loan

```php
$loanDetails = [
    'amount' => '10000',
    'duration' => '12',
    'interest' => '1200',
    'rate' => '12',
    'fee' => '500',
    'payment_details' => [
        'type' => 'mobile_money',
        'number' => '254712345678',
        'callback_url' => 'https://example.com/callback'
    ]
];

try {
    $result = $pezesha->applyLoan('PEZ123', $loanDetails);
    $loanId = $result['data']['loan_id'];
} catch (PezeshaException $e) {
    // Handle error
}
```

#### 3. Check Loan Status

```php
try {
    $result = $pezesha->getLoanStatus('MERCHANT_ID');
    $status = $result['data']['loan_status'];
} catch (PezeshaException $e) {
    // Handle error
}
```

#### 4. Get Loan History

```php
try {
    $result = $pezesha->getLoanHistory('MERCHANT_ID', 1); // page number optional
    $loans = $result['data']['loans'];
} catch (PezeshaException $e) {
    // Handle error
}
```

#### 5. Get Active Loans

```php
try {
    $result = $pezesha->getActiveLoans('MERCHANT_KEY');
    $activeLoans = $result['data']['active_loans'];
} catch (PezeshaException $e) {
    // Handle error
}
```

#### 6. Get Loan Repayment Schedule

```php
try {
    $result = $pezesha->getLoanRepaymentSchedule('MERCHANT_ID');
    $schedule = $result['data']['schedule'];
} catch (PezeshaException $e) {
    // Handle error
}
```
#### 7. Upload Transaction Data

Upload historical transaction data for credit scoring:

```php
$transactions = [
    [
        'transaction_id' => 'TRX123',
        'merchant_id' => 'MERCH123',
        'face_amount' => 1000,
        'transaction_time' => '2024-01-01 12:00:00',
        'other_details' => [
            [
                'key' => 'product',
                'value' => 'laptop'
            ]
        ]
    ]
];

$otherDetails = [
    'business_type' => 'electronics',
    'years_in_business' => '5'
];

try {
    $result = $pezesha->uploadDataTransactions(
        'MERCHANT123',
        $transactions,
        $otherDetails
    );
    
    if ($result['status'] === 'success') {
        // Data uploaded successfully
    }
} catch (PezeshaException $e) {
    // Handle error
}
```

Note: 
- Maximum 200 transactions allowed per request
- Required fields for each transaction:
  - transaction_id (string)
  - merchant_id (string)
  - face_amount (numeric)
  - transaction_time (format: YYYY-MM-DD HH:mm:ss)
- Optional: other_details array with key-value pairs

### Payments

#### Initiate STK Push

```php
try {
    $result = $pezesha->initiateStkPush(
        amount: '1000',
        phone: '+254712345678',
        account: 'MERCHANT123'
    );
    
    if ($result['status'] === 200 && !$result['error']) {
        // STK push successful
        $message = $result['message'];
    }
} catch (PezeshaException $e) {
    // Handle error
}
```

Note: Phone number must be in the format +254XXXXXXXXX

## Error Handling

The package throws `PezeshaException` for all errors. Always wrap API calls in try-catch blocks:

```php
use Chebon\PezeshaLaravel\Exceptions\PezeshaException;

try {
    $result = $pezesha->someMethod();
} catch (PezeshaException $e) {
    // Log error
    Log::error('Pezesha API Error: ' . $e->getMessage());
    
    // Handle error appropriately
    return response()->json(['error' => $e->getMessage()], 500);
}
```

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit
```
## Requirements

- PHP ^7.4|^8.0
- Laravel ^8.0|^9.0|^10.0
- Guzzle ^7.0

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.

## Support

For support, email chebonv@gmail.com or create an issue in the GitHub repository.

