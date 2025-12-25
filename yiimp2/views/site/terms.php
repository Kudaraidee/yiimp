<?php

/** @var yii\web\View $this */

$this->title = 'Terms of Service';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4>Terms of Service</h4>
        </div>
        <div class="card-body">
            <h5>1. Acceptance of Terms</h5>
            <p>By accessing and using <?= YAAMP_SITE_NAME ?>, you accept and agree to be bound by the terms and provision of this agreement.</p>
            
            <h5 class="mt-4">2. Use of Service</h5>
            <p>You agree to use the mining pool service in accordance with all applicable laws and regulations. You are responsible for:</p>
            <ul>
                <li>Maintaining the security of your wallet addresses</li>
                <li>Ensuring your mining equipment is properly configured</li>
                <li>Complying with all local laws regarding cryptocurrency mining</li>
            </ul>
            
            <h5 class="mt-4">3. Payouts</h5>
            <p>Payouts are made automatically every <?= (YAAMP_PAYMENTS_FREQ / 3600) ?> hours for balances above the minimum threshold. 
            The pool operator reserves the right to adjust payout schedules and minimum thresholds as needed.</p>
            
            <h5 class="mt-4">4. Fees</h5>
            <p>Mining fees are clearly displayed for each algorithm and coin. Fees may be adjusted with reasonable notice to users.</p>
            
            <h5 class="mt-4">5. Service Availability</h5>
            <p>While we strive for maximum uptime, the pool operator does not guarantee uninterrupted service. 
            Maintenance and upgrades may require temporary service interruptions.</p>
            
            <h5 class="mt-4">6. Limitation of Liability</h5>
            <p>The pool operator is not liable for:</p>
            <ul>
                <li>Loss of mining rewards due to network issues or orphaned blocks</li>
                <li>Cryptocurrency price fluctuations</li>
                <li>Issues with third-party services (exchanges, wallets, etc.)</li>
                <li>Hardware failures or configuration errors on the user's end</li>
            </ul>
            
            <h5 class="mt-4">7. Prohibited Activities</h5>
            <p>The following activities are strictly prohibited:</p>
            <ul>
                <li>Attempting to exploit or attack the pool infrastructure</li>
                <li>Using stolen or compromised wallet addresses</li>
                <li>Submitting invalid shares or attempting to manipulate statistics</li>
                <li>Any activity that violates applicable laws or regulations</li>
            </ul>
            
            <h5 class="mt-4">8. Account Termination</h5>
            <p>The pool operator reserves the right to terminate access for users who violate these terms or engage in suspicious activity.</p>
            
            <h5 class="mt-4">9. Changes to Terms</h5>
            <p>These terms may be updated from time to time. Continued use of the service constitutes acceptance of any changes.</p>
            
            <h5 class="mt-4">10. Contact</h5>
            <p>For questions about these terms, please contact us through our community channels.</p>
            
            <p class="mt-4"><small>Last updated: <?= date('F Y') ?></small></p>
        </div>
    </div>
</div>
