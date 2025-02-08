<?php

namespace Chebon\PezeshaLaravel;

use GuzzleHttp\Client;
use Chebon\PezeshaLaravel\Exceptions\PezeshaException;
use Chebon\PezeshaLaravel\Enums\UserType;

/* 
 * Pezesha class for interacting with the Pezesha API
 */
class Pezesha
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $accessToken;
    protected $channel;

    /**
     * Constructor for the Pezesha class 
     */
    public function __construct($client = null, array $config = [])
    {
        $this->clientId = $config['client_id'] ?? config('pezesha.client_id');
        $this->clientSecret = $config['client_secret'] ?? config('pezesha.client_secret');
        $this->baseUrl = $config['base_url'] ?? config('pezesha.base_url');
        $this->channel = $config['channel'] ?? config('pezesha.channel');
        
        if (empty($this->channel)) {
            throw new PezeshaException('Pezesha channel is not configured. Contact pezesha support for assistance.');
        }
        
        if (empty($this->clientId)) {
            throw new PezeshaException('Pezesha client ID is not configured. Contact pezesha support for to get one.');
        }
        
        if (empty($this->clientSecret)) {
            throw new PezeshaException('Pezesha client secret is not configured. Contact pezesha support for to get one.');
        }
        
        if (empty($this->baseUrl)) {
            throw new PezeshaException('Pezesha base URL is not configured. Check your .env or config/pezesha.php file.');
        }
        
        $this->client = $client ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => false
        ]);
    }

    /**
     * Authenticate with Pezesha API
     * 
     * @return array
     * @throws PezeshaException
     */
    public function authenticate()
    {
        try {
            $response = $this->client->post('/oauth/token', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'grant_type' => 'client_credentials',
                    'provider' => 'users',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            if (isset($result['access_token'])) {
                $this->accessToken = $result['access_token'];
                return $result;
            }

            throw new PezeshaException('Authentication failed: Invalid response format');
        } catch (\Exception $e) {
            throw new PezeshaException('Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the access token
     * 
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Register a new user (borrower, merchant, or agent)
     *
     * @param array $userData
     * @param UserType $userType
     * @return array
     * @throws PezeshaException
     */
    public function registerUser(array $userData, UserType $userType)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        // Add channel to userData
        $userData['channel'] = $this->channel;

        // Validate required fields
        $requiredFields = [
            'terms' => 'boolean',
            'location' => 'string',
            'merchant_reg_date' => 'string',
            'merchant_id' => 'string',
            'email' => 'string',
            'dob' => 'string',
            'phone' => 'string',
            'full_names' => 'string',
            'national_id' => 'string',
            'channel' => 'string'
        ];

        foreach ($requiredFields as $field => $type) {
            if (!isset($userData[$field])) {
                throw new PezeshaException("Missing required field: {$field}");
            }

            // Type validation
            switch ($type) {
                case 'boolean':
                    if (!is_bool($userData[$field])) {
                        throw new PezeshaException("{$field} must be a boolean");
                    }
                    break;
                case 'string':
                    if (!is_string($userData[$field])) {
                        throw new PezeshaException("{$field} must be a string");
                    }
                    break;
            }
        }

        // Validate date format for DOB (Y-m-d format)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $userData['dob'])) {
            throw new PezeshaException('DOB must be in Y-m-d format');
        }

        // Optional fields
        $optionalFields = [
            'other_phone_nos' => [],
            'geo_location' => [
                'long' => '',
                'lat' => ''
            ],
            'meta_data' => []
        ];

        // Merge optional fields with defaults if not provided
        foreach ($optionalFields as $field => $default) {
            if (!isset($userData[$field])) {
                $userData[$field] = $default;
            }
        }

        try {
            $response = $this->client->post('/mfi/v1/borrowers', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $userData
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['customer_id'])) {
                return $result;
            }

            throw new PezeshaException('User registration failed: Invalid response format');
        } catch (\Exception $e) {
            throw new PezeshaException('User registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Register a borrower
     *
     * @param array $userData
     * @return array
     * @throws PezeshaException
     */
    public function registerBorrower(array $userData)
    {
        return $this->registerUser($userData, UserType::BORROWER);
    }

    /**
     * Register a merchant
     *
     * @param array $userData
     * @return array
     * @throws PezeshaException
     */
    public function registerMerchant(array $userData)
    {
        return $this->registerUser($userData, UserType::MERCHANT);
    }

    /**
     * Register an agent
     *
     * @param array $userData
     * @return array
     * @throws PezeshaException
     */
    public function registerAgent(array $userData)
    {
        return $this->registerUser($userData, UserType::AGENT);
    }

    /**
     * Handle terms and conditions acceptance/opt-out
     *
     * @param string $identifier Merchant ID or National ID
     * @param bool $terms True to accept, False to decline
     * @return array
     * @throws PezeshaException
     */
    public function handleTerms(string $identifier, bool $terms)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post('/mfi/v1/borrowers/terms', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'channel' => $this->channel,
                    'identifier' => $identifier,
                    'terms' => $terms
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Terms operation failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Terms operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Accept terms and conditions
     *
     * @param string $identifier Merchant ID or National ID
     * @return array
     */
    public function acceptTerms(string $identifier)
    {
        return $this->handleTerms($identifier, true);
    }

    /**
     * Decline terms and conditions (opt-out)
     *
     * @param string $identifier Merchant ID or National ID
     * @return array
     */
    public function declineTerms(string $identifier)
    {
        return $this->handleTerms($identifier, false);
    }

    /**
     * Opt merchant out of Pezesha eco system
     *
     * @param string $identifier Can be merchant_id or national_id
     * @return array
     * @throws PezeshaException
     */
    public function optOut(string $identifier)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post('/mfi/v1/borrowers/opt_out', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'channel' => $this->channel,
                    'identifier' => $identifier
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Opt-out operation failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Opt-out operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload merchant's historical transactional data
     *
     * @param string $identifier Merchant ID or National ID
     * @param array $transactions Array of transaction records (max 200)
     * @param array $otherDetails Additional information requested by credit scoring team
     * @return array
     * @throws PezeshaException
     */
    public function uploadDataTransactions(string $identifier, array $transactions, array $otherDetails = [])
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        // Validate transactions count
        if (count($transactions) > 200) {
            throw new PezeshaException('Maximum number of transactions allowed is 200');
        }

        if (empty($transactions)) {
            throw new PezeshaException('Transactions array cannot be empty');
        }

        // Validate each transaction
        foreach ($transactions as $index => $transaction) {
            // Required fields validation
            $requiredFields = [
                'transaction_id' => 'string',
                'merchant_id' => 'string',
                'face_amount' => 'numeric',
                'transaction_time' => 'datetime'
            ];

            foreach ($requiredFields as $field => $type) {
                if (!isset($transaction[$field])) {
                    throw new PezeshaException("Missing required field '{$field}' in transaction at index {$index}");
                }

                switch ($type) {
                    case 'string':
                        if (!is_string($transaction[$field])) {
                            throw new PezeshaException("Field '{$field}' must be a string in transaction at index {$index}");
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($transaction[$field])) {
                            throw new PezeshaException("Field '{$field}' must be numeric in transaction at index {$index}");
                        }
                        break;
                    case 'datetime':
                        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $transaction['transaction_time'])) {
                            throw new PezeshaException("Invalid datetime format for 'transaction_time' in transaction at index {$index}. Expected format: YYYY-MM-DD HH:mm:ss");
                        }
                        break;
                }
            }

            // Validate other_details if present
            if (isset($transaction['other_details'])) {
                if (!is_array($transaction['other_details'])) {
                    throw new PezeshaException("Field 'other_details' must be an array in transaction at index {$index}");
                }

                foreach ($transaction['other_details'] as $detailIndex => $detail) {
                    if (!isset($detail['key']) || !is_string($detail['key'])) {
                        throw new PezeshaException("Missing or invalid 'key' in other_details at index {$detailIndex} for transaction {$index}");
                    }
                    if (!isset($detail['value']) || !is_string($detail['value'])) {
                        throw new PezeshaException("Missing or invalid 'value' in other_details at index {$detailIndex} for transaction {$index}");
                    }
                }
            }
        }

        try {
            $response = $this->client->post('/mfi/v1.1/data', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'channel' => $this->channel,
                    'identifier' => $identifier,
                    'transactions' => $transactions,
                    'other_details' => $otherDetails
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Data upload failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Data upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Get user's loan limit based on credit score
     *
     * @param string $identifier Merchant ID
     * @return array
     * @throws PezeshaException
     */
    public function getLoanOffers(string $identifier)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post('/mfi/v1/borrowers/options', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'channel' => $this->channel,
                    'identifier' => $identifier
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Loan offers request failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Loan offers request failed: ' . $e->getMessage());
        }
    }

    /**
     * Apply for a loan on behalf of a user
     *
     * @param string $pezeshaId Pezesha ID returned during registration
     * @param array $loanDetails Loan application details
     * @return array
     * @throws PezeshaException
     */
    public function applyLoan(string $pezeshaId, array $loanDetails)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        // Required fields validation
        $requiredFields = [
            'amount' => 'string',
            'duration' => 'string',
            'interest' => 'string',
            'rate' => 'string',
            'fee' => 'string',
            'payment_details' => [
                'type' => 'string',
                'number' => 'string',
                'callback_url' => 'string'
            ]
        ];

        // Validate required fields
        foreach ($requiredFields as $field => $type) {
            if (is_array($type)) {
                if (!isset($loanDetails[$field]) || !is_array($loanDetails[$field])) {
                    throw new PezeshaException("Missing or invalid field: {$field}");
                }
                foreach ($type as $subField => $subType) {
                    if (!isset($loanDetails[$field][$subField])) {
                        throw new PezeshaException("Missing required field: {$field}.{$subField}");
                    }
                    if (!is_string($loanDetails[$field][$subField])) {
                        throw new PezeshaException("Field {$field}.{$subField} must be a string");
                    }
                }
            } else {
                if (!isset($loanDetails[$field])) {
                    throw new PezeshaException("Missing required field: {$field}");
                }
                if (!is_string($loanDetails[$field])) {
                    throw new PezeshaException("Field {$field} must be a string");
                }
            }
        }

        try {
            $response = $this->client->post('/mfi/v1/borrowers/loans', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => array_merge($loanDetails, [
                    'channel' => $this->channel,
                    'pezesha_id' => $pezeshaId
                ])
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Loan application failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Loan application failed: ' . $e->getMessage());
        }
    }

    /**
     * Get loan information for a user's latest loan
     *
     * Possible statuses:
     * - Processing: Loan is being prepared for disbursement
     * - Score: Merchant/Customer is being scored to confirm eligibility
     * - Funding: Loan is being sent to payment provider
     * - Funded: Loan has been disbursed and duration is on course
     * - Paid: Loan has been paid for
     * - Cancelled: Loan has been cancelled
     * - Late: User is late on payment
     *
     * @param string $identifier Merchant ID or National ID
     * @return array
     * @throws PezeshaException
     */
    public function getLoanStatus(string $identifier)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post('/mfi/v1/borrowers/loan/status', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'channel' => $this->channel,
                    'identifier' => $identifier
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Loan status request failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Loan status request failed: ' . $e->getMessage());
        }
    }

    /**
     * Get a merchant's loan history
     *
     * @param string $identifier Borrower identifier
     * @param int $page Page number for statements
     * @return array
     * @throws PezeshaException
     */
    public function getLoanHistory(string $identifier, int $page = 1)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post('/mfi/v1/borrowers/statement', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'channel' => $this->channel,
                    'identification' => $identifier,
                    'page' => $page
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Loan history request failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Loan history request failed: ' . $e->getMessage());
        }
    }

    /**
     * Get all active loans for a merchant
     *
     * @param string $merchantKey Merchant key provided by Pezesha
     * @return array
     * @throws PezeshaException
     */
    public function getActiveLoans(string $merchantKey)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = $this->client->get('/mfi/v1/borrowers/active/' . $merchantKey, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Active loans request failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Active loans request failed: ' . $e->getMessage());
        }
    }

    /**
     * Get loan repayment schedule for a borrower
     * 
     * Possible statuses:
     * - Active on Schedule: the schedule is active and payment is on schedule
     * - Paid: the schedule has already been paid
     * - Overdue: the schedule is overdue i.e payment is late
     *
     * @param string $merchantId Merchant ID for whom to see the loan repayment schedule
     * @return array
     * @throws PezeshaException
     */
    public function getLoanRepaymentSchedule(string $merchantId)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = $this->client->get('/mfi/v1/borrowers/repayment-shedules', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'channel' => $this->channel,
                    'merchant_id' => $merchantId
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('Loan repayment schedule request failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('Loan repayment schedule request failed: ' . $e->getMessage());
        }
    }

    /**
     * Initiate STK Push for merchant payment
     *
     * @param string $amount Amount merchant wants to pay
     * @param string $phone Phone number to make payment (+254)
     * @param string $account Account merchant wants to direct funds to
     * @return array
     * @throws PezeshaException
     */
    public function initiateStkPush(string $amount, string $phone, string $account)
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        // Validate phone number format (+254)
        if (!preg_match('/^\+254\d{9}$/', $phone)) {
            throw new PezeshaException('Invalid phone number format. Must be in format: +254XXXXXXXXX');
        }

        // Validate amount is numeric
        if (!is_numeric($amount)) {
            throw new PezeshaException('Amount must be numeric');
        }

        try {
            $response = $this->client->post('/mfi/v2/mpesa/stk', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'amount' => $amount,
                    'phone' => $phone,
                    'account' => $account
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['status'])) {
                throw new PezeshaException('STK push request failed: Invalid response format');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PezeshaException('STK push request failed: ' . $e->getMessage());
        }
    }
} 