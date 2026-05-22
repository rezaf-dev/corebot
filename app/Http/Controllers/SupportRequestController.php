<?php

namespace App\Http\Controllers;

use App\Mail\SupportRequestMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SupportRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:customization,installation,support,other'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $recipient = config('corebot.support_request_email');

        if (! filled($recipient)) {
            return back()->with('error', 'Support requests are not configured on this server yet.');
        }

        try {
            Mail::to($recipient)->send(new SupportRequestMail($data));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'We could not send your message. Please try again later or email us directly.');
        }

        return back()->with('success', 'Thanks! Your request was sent. We will get back to you soon.');
    }
}
