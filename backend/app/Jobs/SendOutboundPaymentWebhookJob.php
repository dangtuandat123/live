<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOutboundPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected int $transactionId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $transaction = Transaction::with('paymentConfig')->find($this->transactionId);
        if (! $transaction || ! $transaction->paymentConfig) {
            Log::warning("Outbound webhook skipped: Transaction or PaymentConfig not found for ID {$this->transactionId}");

            return;
        }

        $config = $transaction->paymentConfig;
        if (empty($config->webhook_url)) {
            return;
        }

        // Prepare placeholder variables
        $placeholders = [
            '{user_id}' => $transaction->user_id,
            '{userId}' => $transaction->user_id,
            '{amount}' => $transaction->amount,
            '{transaction_id}' => $transaction->transaction_id,
            '{transactionId}' => $transaction->transaction_id,
            '{prefix}' => $config->prefix ?? '',
            '{Prefix}' => $config->prefix ?? '',
            '{suffix}' => $config->suffix ?? '',
            '{Suffix}' => $config->suffix ?? '',
        ];

        // Parse headers template
        $headers = [];
        if (! empty($config->headers_template)) {
            $headersJson = json_encode($config->headers_template);
            foreach ($placeholders as $placeholder => $value) {
                $headersJson = str_replace($placeholder, (string) $value, $headersJson);
            }
            $headers = json_decode($headersJson, true) ?? [];
        }

        // Parse params template
        $params = [];
        if (! empty($config->params_template)) {
            $paramsJson = json_encode($config->params_template);
            foreach ($placeholders as $placeholder => $value) {
                $paramsJson = str_replace($placeholder, (string) $value, $paramsJson);
            }
            $params = json_decode($paramsJson, true) ?? [];
        }

        $method = strtoupper($config->method ?? 'POST');
        $url = $config->webhook_url;

        $options = [];
        if ($method === 'GET') {
            $options['query'] = $params;
        } else {
            $options['json'] = $params;
        }

        Http::withHeaders($headers)
            ->timeout(10)
            ->connectTimeout(3)
            ->send($method, $url, $options)
            ->throw();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendOutboundPaymentWebhookJob failed for transaction ID {$this->transactionId}: ".$exception->getMessage());
    }
}
