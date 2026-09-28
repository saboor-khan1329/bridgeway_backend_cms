<?php

namespace App\Services;

/**
 * Spam guard for Service Finder quote-lead submissions.
 *
 * Inherits the complete inquiry spam stack (captcha verification, timing
 * gate, link counting, blocked keywords, admin field rules, SiteSetting
 * overrides) and swaps only the honeypot field name: the quote popup has a
 * genuine "company" input, which is the inquiry guard's default decoy —
 * reusing it verbatim would flag every lead that names their company.
 */
class ServiceFinderSpamGuard extends InquirySpamGuard
{
    protected function honeypotField(): string
    {
        return (string) config('service_finder.leads.honeypot_field', 'website');
    }
}
