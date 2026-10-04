<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support chatbot keyword map
    |--------------------------------------------------------------------------
    | When a player opens a ticket, the first matching keyword's reply is
    | posted instantly as an automated (is_bot) reply. Matching is
    | case-insensitive substring on subject + message. The ticket stays
    | OPEN — the bot assists, it never closes or resolves.
    */

    'keywords' => [
        'withdraw' => 'Withdrawals are processed within 24 hours on working days. '.
            'Make sure your UPI ID / bank details are correct in the withdrawal section. '.
            'A 2% platform commission and applicable TDS apply as shown on the preview screen. '.
            'If your withdrawal is older than 24 hours, reply here and our team will check it.',
        'deposit' => 'To add money: go to Wallet → Deposit and pay via UPI. '.
            'Deposits reflect within a few minutes of payment. If yours is missing, '.
            'reply with your UPI transaction reference (UTR) number and we will trace it.',
        'otp' => 'Not receiving the OTP? Wait 60 seconds and tap Resend. '.
            'Check that DND is not blocking transactional SMS, and confirm the mobile '.
            'number on your profile is correct.',
        'refund' => 'Match entry fees are non-refundable once a match starts. '.
            'Deposits that failed but were debited by your bank are auto-reversed '.
            'within 5–7 working days; reply with the UTR if it has been longer.',
        'referral' => 'Your referral bonus is credited after your friend verifies their '.
            'mobile number. Bonus money unlocks for play after you wager 5× the bonus '.
            'amount in matches — see your wallet for the locked balance.',
        'kyc' => 'KYC is not required to play. Withdrawals above the daily limit may '.
            'need a one-time verification — our team will reach out on this ticket.',
        'password' => 'To reset your password, log out and use "Forgot password" on the '.
            'login page. Never share your password or OTP with anyone, including '.
            'people claiming to be support staff.',
        'cheat' => 'If you suspect cheating in a match, reply with the match ID and '.
            'what happened. Our anti-cheat system also reviews every match automatically.',
        'ban' => 'If your account or device was suspended, reply here with your '.
            'username. Suspensions from the anti-cheat system are reviewed manually — '.
            'decisions are usually shared within 48 hours.',
    ],

    'fallback' => 'Thanks for reaching out! Our support team will reply here soon. '.
        'Meanwhile, you can check the FAQ answers above for withdrawals, deposits and OTP issues.',

];
