<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\ReceiptPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePaymentReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $paymentId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReceiptPdfService $pdfService): void
    {
        $payment = Payment::with('contractor')->find($this->paymentId);

        if (! $payment || $payment->status !== 'paid') {
            return;
        }

        try {
            $path = $pdfService->generate($payment);
            $payment->update(['receipt_pdf_path' => $path]);
        } catch (\Exception $e) {
            Log::error("Failed to generate PDF for Payment {$payment->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
