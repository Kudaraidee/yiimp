<?php

/** @var yii\web\View $this */

$this->title = 'Privacy Policy';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4>Privacy Policy</h4>
        </div>
        <div class="card-body">
            <h5>1. Information We Collect</h5>
            <p><?= YAAMP_SITE_NAME ?> collects the following information:</p>
            <ul>
                <li><strong>Wallet Addresses:</strong> Used as usernames for mining and payouts</li>
                <li><strong>IP Addresses:</strong> Logged for security and troubleshooting purposes</li>
                <li><strong>Mining Statistics:</strong> Hashrate, shares submitted, workers, and earnings</li>
                <li><strong>Browser Cookies:</strong> Used for session management and bookmarked wallets</li>
            </ul>
            
            <h5 class="mt-4">2. How We Use Your Information</h5>
            <p>We use collected information to:</p>
            <ul>
                <li>Process mining rewards and payouts</li>
                <li>Display mining statistics and performance metrics</li>
                <li>Detect and prevent fraudulent activity</li>
                <li>Improve pool performance and user experience</li>
                <li>Troubleshoot technical issues</li>
            </ul>
            
            <h5 class="mt-4">3. Information Sharing</h5>
            <p>We do not sell, trade, or rent your personal information to third parties. Information may be shared only in the following circumstances:</p>
            <ul>
                <li>When required by law or legal process</li>
                <li>To protect the rights and safety of the pool and its users</li>
                <li>With service providers who assist in pool operations (under strict confidentiality agreements)</li>
            </ul>
            
            <h5 class="mt-4">4. Public Information</h5>
            <p>The following information is publicly visible:</p>
            <ul>
                <li>Wallet addresses and associated mining statistics</li>
                <li>Block finds and payout transactions (on blockchain explorers)</li>
                <li>Pool-wide statistics and performance metrics</li>
            </ul>
            
            <h5 class="mt-4">5. Data Security</h5>
            <p>We implement reasonable security measures to protect your information, including:</p>
            <ul>
                <li>Encrypted connections (SSL/TLS)</li>
                <li>Secure server infrastructure</li>
                <li>Regular security audits and updates</li>
                <li>Access controls and monitoring</li>
            </ul>
            
            <h5 class="mt-4">6. Data Retention</h5>
            <p>We retain mining statistics and transaction records for operational and legal purposes. 
            Historical data may be archived or deleted after a reasonable period.</p>
            
            <h5 class="mt-4">7. Cookies and Tracking</h5>
            <p>We use cookies to:</p>
            <ul>
                <li>Remember bookmarked wallet addresses</li>
                <li>Maintain session state and preferences</li>
                <li>Analyze pool usage and performance</li>
            </ul>
            <p>You can disable cookies in your browser settings, but this may affect functionality.</p>
            
            <h5 class="mt-4">8. Third-Party Services</h5>
            <p>Our service may link to third-party websites and services (exchanges, block explorers, etc.). 
            We are not responsible for their privacy practices.</p>
            
            <h5 class="mt-4">9. Children's Privacy</h5>
            <p>Our service is not intended for users under 18 years of age. We do not knowingly collect information from children.</p>
            
            <h5 class="mt-4">10. Your Rights</h5>
            <p>You have the right to:</p>
            <ul>
                <li>Access your mining statistics and transaction history</li>
                <li>Request deletion of your data (subject to legal and operational requirements)</li>
                <li>Opt out of non-essential data collection</li>
            </ul>
            
            <h5 class="mt-4">11. Changes to Privacy Policy</h5>
            <p>We may update this privacy policy from time to time. Continued use of the service constitutes acceptance of any changes.</p>
            
            <h5 class="mt-4">12. Contact</h5>
            <p>For privacy-related questions or requests, please contact us through our community channels.</p>
            
            <p class="mt-4"><small>Last updated: <?= date('F Y') ?></small></p>
        </div>
    </div>
</div>
