<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePresentationRequest;
use App\Models\PresentationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PresentationRequestController extends Controller
{
    public function store(StorePresentationRequest $request): JsonResponse
    {
        $demoRequest = PresentationRequest::create($request->validated());

        $this->notify($demoRequest);

        return response()->json([
            'message' => __('landing.request_form_success'),
        ], 201);
    }

    /**
     * Announce a new request to the sales inbox.
     *
     * The row is already saved by the time this runs, so a mail failure must
     * not reach the visitor: losing the notification costs an alert, telling
     * the visitor their request failed costs the lead. The exception is logged
     * instead, and the request stays visible in the dashboard either way.
     */
    private function notify(PresentationRequest $demoRequest): void
    {
        $recipient = config('landing.request_notify_email');

        if (! $recipient) {
            return;
        }

        $body = __('landing.request_mail_body', [
            'name' => $demoRequest->name,
            'phone' => $demoRequest->phone,
            'whatsapp' => 'https://wa.me/'.$this->internationalise($demoRequest->phone),
            'message' => $demoRequest->message ?: __('landing.request_mail_no_message'),
            'date' => $demoRequest->created_at->format('Y-m-d H:i'),
        ]);

        try {
            Mail::raw($body, fn ($m) => $m
                ->to($recipient)
                ->subject(__('landing.request_mail_subject', ['name' => $demoRequest->name])));
        } catch (\Throwable $e) {
            Log::error('Demo request notification failed', [
                'presentation_request_id' => $demoRequest->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Turn any of the shapes the form accepts — 05XXXXXXXX, 5XXXXXXXX,
     * +9665XXXXXXXX — into the bare 9665XXXXXXXX that wa.me links need.
     */
    private function internationalise(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '966')) {
            return $digits;
        }

        return '966'.ltrim($digits, '0');
    }
}
