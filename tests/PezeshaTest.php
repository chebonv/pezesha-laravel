<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Chebon\PezeshaLaravel\Pezesha;
use Chebon\PezeshaLaravel\Exceptions\PezeshaException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

class PezeshaTest extends TestCase
{
    protected $pezesha;
    protected $mockHandler;
    protected $container;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock handler and history container
        $this->mockHandler = new MockHandler();
        $this->container = [];
        $history = Middleware::history($this->container);
        
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);
        
        // Mock the client
        $client = new Client(['handler' => $handlerStack]);
        
        // Create Pezesha instance with mocked client and test config
        $this->pezesha = new Pezesha($client, [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'base_url' => 'https://api.test.pezesha.com',
            'channel' => 'test_channel'
        ]);
    }

    public function testAuthentication()
    {
        // Mock successful authentication response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ]))
        );

        $result = $this->pezesha->authenticate();

        $this->assertEquals('test_token', $result['access_token']);
        $this->assertEquals('test_token', $this->pezesha->getAccessToken());
    }

    public function testLoanOffers()
    {
        // Mock authentication
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ]))
        );

        // Mock loan offers response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => [
                    'loan_limit' => 10000,
                    'interest_rate' => 12
                ]
            ]))
        );

        $result = $this->pezesha->getLoanOffers('MERCHANT123');

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('loan_limit', $result['data']);
    }

    public function testLoanApplication()
    {
        // Mock authentication
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ]))
        );

        // Mock loan application response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => [
                    'loan_id' => 'LOAN123',
                    'status' => 'Processing'
                ]
            ]))
        );

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

        $result = $this->pezesha->applyLoan('PEZ123', $loanDetails);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('LOAN123', $result['data']['loan_id']);
    }

    public function testLoanStatus()
    {
        // Mock authentication and response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ])),
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => [
                    'loan_status' => 'Processing'
                ]
            ]))
        );

        $result = $this->pezesha->getLoanStatus('MERCHANT123');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Processing', $result['data']['loan_status']);
    }

    public function testLoanHistory()
    {
        // Mock responses
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ])),
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => [
                    'loans' => [
                        ['loan_id' => 'LOAN1'],
                        ['loan_id' => 'LOAN2']
                    ]
                ]
            ]))
        );

        $result = $this->pezesha->getLoanHistory('MERCHANT123', 1);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(2, $result['data']['loans']);
    }

    public function testActiveLoans()
    {
        // Mock responses
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ])),
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => [
                    'active_loans' => [
                        ['loan_id' => 'LOAN1']
                    ]
                ]
            ]))
        );

        $result = $this->pezesha->getActiveLoans('MERCHANT_KEY');

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['data']['active_loans']);
    }

    public function testLoanRepaymentSchedule()
    {
        // Mock responses
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ])),
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => [
                    'schedule' => [
                        ['status' => 'Active on Schedule']
                    ]
                ]
            ]))
        );

        $result = $this->pezesha->getLoanRepaymentSchedule('MERCHANT123');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Active on Schedule', $result['data']['schedule'][0]['status']);
    }

    public function testInitiateStkPush()
    {
        // Mock responses
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ])),
            new Response(200, [], json_encode([
                'status' => 200,
                'response_code' => 0,
                'error' => false,
                'message' => 'STK Request Submitted Successfully'
            ]))
        );

        $result = $this->pezesha->initiateStkPush('1000', '+254712345678', 'MERCHANT123');

        $this->assertEquals(200, $result['status']);
        $this->assertFalse($result['error']);
    }

    public function testInvalidPhoneNumberFormat()
    {
        // Mock successful authentication response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ]))
        );

        $this->expectException(PezeshaException::class);
        $this->expectExceptionMessage('Invalid phone number format');

        $this->pezesha->initiateStkPush('1000', '0712345678', 'MERCHANT123');
    }

    public function testInvalidAmount()
    {
        // Mock successful authentication response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ]))
        );

        $this->expectException(PezeshaException::class);
        $this->expectExceptionMessage('Amount must be numeric');

        $this->pezesha->initiateStkPush('abc', '+254712345678', 'MERCHANT123');
    }

    public function testUploadDataTransactions()
    {
        // Mock responses
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ])),
            new Response(200, [], json_encode([
                'status' => 'success',
                'message' => 'Data uploaded successfully'
            ]))
        );

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

        $result = $this->pezesha->uploadDataTransactions(
            'MERCHANT123',
            $transactions,
            $otherDetails
        );

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Data uploaded successfully', $result['message']);
    }

    public function testUploadDataTransactionsValidation()
    {
        // Mock authentication response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'access_token' => 'test_token'
            ]))
        );

        $this->expectException(PezeshaException::class);
        $this->expectExceptionMessage('Missing required field');

        $invalidTransactions = [
            [
                'transaction_id' => 'TRX123',
                // missing required fields
            ]
        ];

        $this->pezesha->uploadDataTransactions('MERCHANT123', $invalidTransactions);
    }
} 